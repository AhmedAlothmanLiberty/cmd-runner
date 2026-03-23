"""
Fast export using the IX_TblMailersUnique_Export index.
Same format as export_sam_report.py but 10x faster.

Outputs CSV in Sam's exact format:
    First name, address, debt load, phone1, phone2, phone3, phone4, phone5, send date

One row per person. All phones in columns phone1-phone5.
Only exports rows with debt_amount > 0 and at least one phone.
Splits output into 1M-row files automatically.
Auto-resumes from last completed drop via a .progress file.

Usage:
    python export_sam_report_fast.py --unsent-only --update-send-date --output-dir /var/www/cmd-runner/EE
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
        login_timeout=60,
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


def format_send_date(value):
    if value is None:
        return ""
    if hasattr(value, "isoformat"):
        return value.isoformat()
    return str(value)


def fetch_marketing_send_date(mssql_conn, drop_name):
    cursor = mssql_conn.cursor()
    cursor.execute(
        """
        SELECT TOP 1 Send_Date
        FROM dbo.TblMarketing
        WHERE Drop_Name = %s
        ORDER BY Update_Date DESC, PK DESC
        """,
        (drop_name,)
    )
    row = cursor.fetchone()
    cursor.close()
    return format_send_date(row[0]) if row and row[0] is not None else ""


def fetch_distinct_drops(mssql_conn, unsent_only, resume_from_drop=None):
    cursor = mssql_conn.cursor()
    where = "WHERE (phone1 IS NOT NULL OR phone2 IS NOT NULL OR phone3 IS NOT NULL OR phone4 IS NOT NULL OR phone5 IS NOT NULL) AND Debt_Amount > 0"
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


def fetch_rows_for_drop_batched(mssql_conn, drop_name, unsent_only, batch_size=5000):
    """Generator that yields rows in batches to avoid memory/timeout issues."""
    cursor = mssql_conn.cursor(as_dict=True)
    last_pk = 0
    while True:
        where = "WHERE (phone1 IS NOT NULL OR phone2 IS NOT NULL OR phone3 IS NOT NULL OR phone4 IS NOT NULL OR phone5 IS NOT NULL) AND Drop_Name = %s AND Debt_Amount > 0 AND PK > %s"
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
    # Update in smaller batches to avoid deadlocks
    for i in range(0, len(pks), 1000):
        batch = pks[i:i + 1000]
        placeholders = ",".join(["%s"] * len(batch))
        cursor.execute(
            f"UPDATE dbo.TblMailersUnique SET sms_send_date = %s WHERE PK IN ({placeholders})",
            tuple([send_date] + batch),
        )
        mssql_conn.commit()
    cursor.close()


def read_progress(progress_file):
    """Read progress from file. Returns (last_drop, last_pk, part_num, total_written, rows_in_part)."""
    if not os.path.exists(progress_file):
        return None, None, 1, 0, 0
    try:
        with open(progress_file) as f:
            lines = [l.strip() for l in f.readlines() if l.strip()]
        last_drop = lines[0] if len(lines) > 0 else None
        last_pk = int(lines[1]) if len(lines) > 1 and lines[1] != 'None' else None
        part_num = int(lines[2]) if len(lines) > 2 else 1
        total_written = int(lines[3]) if len(lines) > 3 else 0
        rows_in_part = int(lines[4]) if len(lines) > 4 else 0
        return last_drop, last_pk, part_num, total_written, rows_in_part
    except Exception:
        return None, None, 1, 0, 0


def write_progress(progress_file, last_drop, last_pk, part_num, total_written, rows_in_part):
    with open(progress_file, "w") as f:
        f.write(f"{last_drop}\n{last_pk}\n{part_num}\n{total_written}\n{rows_in_part}\n")


def open_part_file(output_dir, prefix, part_num, is_resume=False):
    filename = f"{prefix}_{part_num:03d}.csv"
    path = os.path.join(output_dir, filename)
    
    if is_resume and os.path.exists(path):
        # Count existing rows in file
        with open(path, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            existing_rows = sum(1 for _ in reader)
        fh = open(path, "a", newline="", encoding="utf-8")
        writer = csv.DictWriter(fh, fieldnames=FIELDNAMES)
        print(f"  Resuming file: {path} (existing rows: {existing_rows})")
        return fh, writer, path, existing_rows
    else:
        # Fresh file
        fh = open(path, "w", newline="", encoding="utf-8")
        writer = csv.DictWriter(fh, fieldnames=FIELDNAMES)
        writer.writeheader()
        print(f"  Created file: {path}")
        return fh, writer, path, 0


def main():
    parser = argparse.ArgumentParser(description="Fast export of enriched Lending Tower records for Sam (using index)")
    parser.add_argument("--max-rows", type=int, default=0,
                        help="Maximum total rows to export (0 = no limit)")
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
    args = parser.parse_args()

    prefix = args.prefix or f"sam_export_fast_{date.today().isoformat()}"
    sms_mark_date = date.today().isoformat()
    max_rows = args.max_rows if args.max_rows > 0 else float("inf")
    rows_per_file = args.rows_per_file

    os.makedirs(args.output_dir, exist_ok=True)
    progress_file = os.path.join(args.output_dir, f"{prefix}.progress")

    resume_from_drop = None
    resume_from_pk = None
    part_num = 1
    total_written = 0
    rows_in_current_file = 0

    if args.resume:
        resume_from_drop, resume_from_pk, part_num, total_written, rows_in_current_file = read_progress(progress_file)
        if resume_from_drop:
            print(f"Resuming from drop: {resume_from_drop} | PK: {resume_from_pk} | part: {part_num:03d} | rows so far: {total_written:,} | rows in part: {rows_in_current_file:,}")
        else:
            print("No progress file found, starting fresh.")

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    print("Fetching distinct Drop_Name values...")
    drops = fetch_distinct_drops(conn, args.unsent_only, resume_from_drop)
    print(f"  Found {len(drops)} drops to process.")

    if not drops:
        print("Nothing to export.")
        conn.close()
        return

    start_time = time.time()
    total_drops = len(drops)

    fh, writer, current_path, existing_rows = open_part_file(args.output_dir, prefix, part_num, is_resume=(args.resume and resume_from_drop))
    rows_in_current_file = existing_rows

    try:
        for drop_idx, drop_name in enumerate(drops, 1):
            if total_written >= max_rows:
                print(f"\n  Reached --max-rows limit ({int(max_rows):,}). Stopping.")
                break

            drop_start = time.time()
            last_pk = 0 if resume_from_pk is None else resume_from_pk
            pks = []
            rows_written_this_drop = 0
            checkpoint_counter = 0
            drop_send_date = fetch_marketing_send_date(conn, drop_name)

            try:
                for row in fetch_rows_for_drop_batched(conn, drop_name, args.unsent_only):
                    if total_written >= max_rows:
                        break

                    first_name = parse_first_name(row.get("Client", ""))
                    address = (row.get("Address", "") or "").upper()
                    debt_load = format_debt_load(row.get("Debt_Amount", ""))

                    phones = {}
                    for col in ["phone1", "phone2", "phone3", "phone4", "phone5"]:
                        phones[col] = (row.get(col, "") or "").strip()

                    if total_written >= max_rows:
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
                        "send date": drop_send_date,
                    })
                    total_written += 1
                    rows_in_current_file += 1
                    rows_written_this_drop += 1

                    pks.append(row["PK"])
                    last_pk = row["PK"]
                    checkpoint_counter += 1

                    # Checkpoint every 1000 rows
                    if checkpoint_counter % 1000 == 0:
                        write_progress(progress_file, drop_name, last_pk, part_num, total_written, rows_in_current_file)

                    if total_written >= max_rows:
                        break
            except Exception as e:
                print(f"  ERROR processing drop {drop_name}: {e}")
                rows_written_this_drop = 0

            if args.update_send_date:
                update_send_dates(conn, pks, sms_mark_date)

            write_progress(progress_file, drop_name, last_pk, part_num, total_written, rows_in_current_file)

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
        print(f"  sms_send_date updated to: {sms_mark_date}")
    else:
        print("  sms_send_date NOT updated (use --update-send-date to mark them).")
    print(f"{'='*60}")

    conn.close()


if __name__ == "__main__":
    main()
