"""
Phase 4: Export enriched records from TblMailersUnique for Sam.

Outputs CSV in Sam's exact format:
    First name, address, debt load, phone1, phone2, phone3, phone4, phone5, send date

One row per person. All phones in columns phone1-phone5.
Only exports rows with debt_amount > 0 and at least one phone.
Splits output into 1M-row files automatically.
Auto-resumes from last completed drop via a .progress file.

Usage:
    python export_sam_report.py --count 4500000 --unsent-only --update-send-date
    python export_sam_report.py --count 4500000 --unsent-only --update-send-date --resume

Options:
    --count               Total phone-row limit (0 = all enriched)
    --output-dir          Directory for output files (default: current dir)
    --prefix              File prefix (default: sam_export_YYYY-MM-DD)
    --rows-per-file       Rows per file (default: 1000000)
    --update-send-date    Set sms_send_date to today for exported records
    --unsent-only         Only export rows where sms_send_date IS NULL
    --resume              Auto-resume from last completed drop in .progress file
    --ensure-index        Create index on Drop_Name before export
"""
import argparse
import csv
import os
import time
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

BATCH_SIZE = 10000
ROWS_PER_FILE = 1_000_000

FIELDNAMES = ["First name", "address", "debt load", "phone1", "phone2", "phone3", "phone4", "phone5", "send date"]


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def parse_first_name(client_str):
    parts = client_str.strip().upper().split() if client_str else []
    return parts[0] if parts else ""


def format_debt_load(value):
    if value is None:
        return ""
    try:
        numeric = float(value)
    except (TypeError, ValueError):
        return str(value)
    if numeric.is_integer():
        return str(int(numeric))
    return f"{numeric:.2f}".rstrip("0").rstrip(".")


def ensure_index(mssql_conn):
    cursor = mssql_conn.cursor()
    print("Checking index on Drop_Name...")
    cursor.execute("""
        IF NOT EXISTS (
            SELECT 1 FROM sys.indexes
            WHERE name = 'IX_TblMailersUnique_DropName'
              AND object_id = OBJECT_ID('dbo.TblMailersUnique')
        )
        BEGIN
            CREATE NONCLUSTERED INDEX IX_TblMailersUnique_DropName
            ON dbo.TblMailersUnique (Drop_Name)
            INCLUDE (phone1, sms_send_date);
            SELECT 'CREATED' AS result;
        END
        ELSE
            SELECT 'EXISTS' AS result;
    """)
    row = cursor.fetchone()
    print(f"  Index: {row[0] if row else 'UNKNOWN'}")
    mssql_conn.commit()
    cursor.close()


def fetch_distinct_drops(mssql_conn, unsent_only, resume_from_drop=None):
    cursor = mssql_conn.cursor()
    where = "WHERE phone1 IS NOT NULL AND Debt_Amount > 0"
    if unsent_only:
        where += " AND sms_send_date IS NULL"
    if resume_from_drop:
        cursor.execute(
            f"SELECT DISTINCT Drop_Name FROM dbo.TblMailersUnique "
            f"{where} AND Drop_Name < %s ORDER BY Drop_Name DESC",
            (resume_from_drop,)
        )
    else:
        cursor.execute(
            f"SELECT DISTINCT Drop_Name FROM dbo.TblMailersUnique "
            f"{where} ORDER BY Drop_Name DESC"
        )
    drops = [row[0] for row in cursor.fetchall()]
    cursor.close()
    return drops


def fetch_rows_for_drop_batched(mssql_conn, drop_name, unsent_only, batch_size=10000):
    """Generator that yields rows in batches to avoid memory/timeout issues."""
    cursor = mssql_conn.cursor(as_dict=True)
    last_pk = 0
    while True:
        where = "WHERE phone1 IS NOT NULL AND Drop_Name = %s AND Debt_Amount > 0 AND PK > %s"
        if unsent_only:
            where += " AND sms_send_date IS NULL"
        cursor.execute(
            f"SELECT TOP {batch_size} PK, Client, Address, Debt_Amount, phone1, phone2, phone3, phone4, phone5 "
            f"FROM dbo.TblMailersUnique {where} ORDER BY PK ASC",
            (drop_name, last_pk)
        )
        rows = cursor.fetchall()
        if not rows:
            break
        for row in rows:
            yield row
        last_pk = rows[-1]["PK"]
    cursor.close()


def update_send_dates(mssql_conn, pks, send_date):
    if not pks:
        return
    cursor = mssql_conn.cursor()
    for i in range(0, len(pks), BATCH_SIZE):
        batch = pks[i:i + BATCH_SIZE]
        placeholders = ",".join(["%s"] * len(batch))
        cursor.execute(
            f"UPDATE dbo.TblMailersUnique SET sms_send_date = %s WHERE PK IN ({placeholders})",
            tuple([send_date] + batch),
        )
        mssql_conn.commit()
    cursor.close()


def read_progress(progress_file):
    """Read last completed drop from progress file. Returns (last_drop, part_num, total_written)."""
    if not os.path.exists(progress_file):
        return None, 1, 0
    try:
        with open(progress_file) as f:
            lines = [l.strip() for l in f.readlines() if l.strip()]
        last_drop = lines[0] if len(lines) > 0 else None
        part_num = int(lines[1]) if len(lines) > 1 else 1
        total_written = int(lines[2]) if len(lines) > 2 else 0
        return last_drop, part_num, total_written
    except Exception:
        return None, 1, 0


def write_progress(progress_file, last_drop, part_num, total_written):
    with open(progress_file, "w") as f:
        f.write(f"{last_drop}\n{part_num}\n{total_written}\n")


def open_part_file(output_dir, prefix, part_num):
    filename = f"{prefix}_{part_num:03d}.csv"
    path = os.path.join(output_dir, filename)
    is_new = not os.path.exists(path)
    fh = open(path, "a", newline="", encoding="utf-8")
    writer = csv.DictWriter(fh, fieldnames=FIELDNAMES)
    if is_new:
        writer.writeheader()
    print(f"  {'Created' if is_new else 'Appending to'} file: {path}")
    return fh, writer, path


def main():
    parser = argparse.ArgumentParser(description="Export enriched Lending Tower records for Sam")
    parser.add_argument("--count", type=int, default=0,
                        help="Total phone-row limit (0 = all enriched)")
    parser.add_argument("--output-dir", type=str, default=".",
                        help="Directory for output files")
    parser.add_argument("--prefix", type=str, default=None,
                        help="File prefix (default: sam_export_YYYY-MM-DD)")
    parser.add_argument("--rows-per-file", type=int, default=ROWS_PER_FILE,
                        help="Rows per file (default: 1,000,000)")
    parser.add_argument("--update-send-date", action="store_true",
                        help="Set sms_send_date to today for exported records")
    parser.add_argument("--unsent-only", action="store_true",
                        help="Only export rows where sms_send_date IS NULL")
    parser.add_argument("--resume", action="store_true",
                        help="Auto-resume from last completed drop in .progress file")
    parser.add_argument("--ensure-index", action="store_true",
                        help="Create index on Drop_Name before export")
    args = parser.parse_args()

    prefix = args.prefix or f"sam_export_{date.today().isoformat()}"
    send_date = date.today().isoformat()
    count_limit = args.count if args.count > 0 else float("inf")
    rows_per_file = args.rows_per_file

    os.makedirs(args.output_dir, exist_ok=True)
    progress_file = os.path.join(args.output_dir, f"{prefix}.progress")

    resume_from_drop = None
    part_num = 1
    total_written = 0

    if args.resume:
        resume_from_drop, part_num, total_written = read_progress(progress_file)
        if resume_from_drop:
            print(f"Resuming from drop: {resume_from_drop} | part: {part_num:03d} | rows so far: {total_written:,}")
        else:
            print("No progress file found, starting fresh.")

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    if args.ensure_index:
        ensure_index(conn)

    print("Fetching distinct Drop_Name values...")
    drops = fetch_distinct_drops(conn, args.unsent_only, resume_from_drop)
    print(f"  Found {len(drops)} drops to process.")

    if not drops:
        print("Nothing to export.")
        conn.close()
        return

    start_time = time.time()
    total_drops = len(drops)
    rows_in_current_file = 0

    fh, writer, current_path = open_part_file(args.output_dir, prefix, part_num)

    try:
        for drop_idx, drop_name in enumerate(drops, 1):
            if total_written >= count_limit:
                print(f"\n  Reached --count limit ({int(count_limit):,}). Stopping.")
                break

            drop_start = time.time()
            pks = []
            rows_written_this_drop = 0

            for row in fetch_rows_for_drop_batched(conn, drop_name, args.unsent_only):
                if total_written >= count_limit:
                    break

                first_name = parse_first_name(row.get("Client", ""))
                address = (row.get("Address", "") or "").upper()
                debt_load = format_debt_load(row.get("Debt_Amount", ""))

                phones = {}
                for col in ["phone1", "phone2", "phone3", "phone4", "phone5"]:
                    phones[col] = (row.get(col, "") or "").strip()

                if total_written >= count_limit:
                    break

                # Rotate to new file if current file is full
                if rows_in_current_file >= rows_per_file:
                    fh.close()
                    part_num += 1
                    rows_in_current_file = 0
                    fh, writer, current_path = open_part_file(args.output_dir, prefix, part_num)

                writer.writerow({
                    "First name": first_name,
                    "address": address,
                    "debt load": debt_load,
                    "phone1": phones["phone1"],
                    "phone2": phones["phone2"],
                    "phone3": phones["phone3"],
                    "phone4": phones["phone4"],
                    "phone5": phones["phone5"],
                    "send date": send_date,
                })
                total_written += 1
                rows_in_current_file += 1
                rows_written_this_drop += 1

                pks.append(row["PK"])

                if total_written >= count_limit:
                    break

            if args.update_send_date:
                update_send_dates(conn, pks, send_date)

            write_progress(progress_file, drop_name, part_num, total_written)

            elapsed = time.time() - start_time
            drop_elapsed = time.time() - drop_start
            print(
                f"  [{drop_idx}/{total_drops}] {drop_name}: "
                f"{rows_written_this_drop:,} rows ({drop_elapsed:.1f}s) | "
                f"Total: {total_written:,} | Part: {part_num:03d} | Elapsed: {elapsed:.0f}s"
            )
    finally:
        fh.close()

    elapsed = time.time() - start_time
    print(f"\n{'='*60}")
    print(f"Done in {elapsed:.1f}s ({elapsed/60:.1f}m).")
    print(f"  Output dir: {args.output_dir}")
    print(f"  Files: {prefix}_001.csv ... {prefix}_{part_num:03d}.csv")
    print(f"  Total rows written: {total_written:,}")
    if args.update_send_date:
        print(f"  sms_send_date updated to: {send_date}")
    else:
        print("  sms_send_date NOT updated (use --update-send-date to mark them).")
    print(f"{'='*60}")

    conn.close()


if __name__ == "__main__":
    main()
