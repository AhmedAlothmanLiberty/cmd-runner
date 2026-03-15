"""
Phase 4: Export enriched records from TblMailersUnique for Sam.

Outputs CSV in Sam's exact format:
    First name, address, debt load, phone1, phone2, phone3, phone4, phone5, send date

One row per person (not per phone). Only rows with at least one phone.
Processes one Drop_Name at a time for low memory usage and resumability.

Usage:
    python export_sam_report.py --count 0 [--output report.csv] [--update-send-date]
    python export_sam_report.py --count 4500000 --unsent-only --resume-from-drop "DROP_123"

Options:
    --count               Number of records (0 = all enriched)
    --output              Output CSV path (default: sam_report_YYYY-MM-DD.csv)
    --update-send-date    Set sms_send_date to today for exported records
    --unsent-only         Only export rows where sms_send_date IS NULL
    --resume-from-drop    Resume from this Drop_Name (skip drops already exported)
    --ensure-index        Create index on Drop_Name before export
"""
import argparse
import csv
import sys
import time
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

BATCH_SIZE = 10000


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
    """Create index on Drop_Name if it doesn't exist."""
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
    result = row[0] if row else "UNKNOWN"
    print(f"  Index: {result}")
    mssql_conn.commit()
    cursor.close()


def fetch_distinct_drops(mssql_conn, unsent_only, resume_from_drop=None):
    """Fetch distinct Drop_Name values, ordered DESC (newest first)."""
    cursor = mssql_conn.cursor()

    where_clause = "WHERE phone1 IS NOT NULL"
    if unsent_only:
        where_clause += " AND sms_send_date IS NULL"
    if resume_from_drop:
        where_clause += f" AND Drop_Name < %s"
        cursor.execute(f"""
            SELECT DISTINCT Drop_Name
            FROM dbo.TblMailersUnique
            {where_clause}
            ORDER BY Drop_Name DESC
        """, (resume_from_drop,))
    else:
        cursor.execute(f"""
            SELECT DISTINCT Drop_Name
            FROM dbo.TblMailersUnique
            {where_clause}
            ORDER BY Drop_Name DESC
        """)

    drops = [row[0] for row in cursor.fetchall()]
    cursor.close()
    return drops


def fetch_rows_for_drop(mssql_conn, drop_name, unsent_only):
    """Fetch all enriched rows for a single Drop_Name."""
    cursor = mssql_conn.cursor(as_dict=True)

    where_clause = "WHERE phone1 IS NOT NULL AND Drop_Name = %s"
    if unsent_only:
        where_clause += " AND sms_send_date IS NULL"

    cursor.execute(f"""
        SELECT PK, Client, Address, Debt_Amount,
               phone1, phone2, phone3, phone4, phone5
        FROM dbo.TblMailersUnique
        {where_clause}
        ORDER BY PK ASC
    """, (drop_name,))
    rows = cursor.fetchall()
    cursor.close()
    return rows


def update_send_dates(mssql_conn, pks, send_date):
    """Set sms_send_date for exported records."""
    if not pks:
        return
    cursor = mssql_conn.cursor()
    for i in range(0, len(pks), BATCH_SIZE):
        batch = pks[i:i + BATCH_SIZE]
        placeholders = ",".join(["%s"] * len(batch))
        cursor.execute(
            f"UPDATE dbo.TblMailersUnique SET sms_send_date = %s WHERE PK IN ({placeholders})",
            [send_date] + batch,
        )
        mssql_conn.commit()
    cursor.close()


def main():
    parser = argparse.ArgumentParser(description="Export enriched Lending Tower records for Sam")
    parser.add_argument("--count", type=int, default=0,
                        help="Number of records (0 = all enriched)")
    parser.add_argument("--output", type=str, default=None,
                        help="Output CSV path (default: sam_report_YYYY-MM-DD.csv)")
    parser.add_argument("--update-send-date", action="store_true",
                        help="Set sms_send_date to today for exported records")
    parser.add_argument("--unsent-only", action="store_true",
                        help="Only export rows where sms_send_date IS NULL")
    parser.add_argument("--resume-from-drop", type=str, default=None,
                        help="Resume from this Drop_Name (skip already exported drops)")
    parser.add_argument("--ensure-index", action="store_true",
                        help="Create index on Drop_Name before export")
    args = parser.parse_args()

    output_path = args.output or f"sam_report_{date.today().isoformat()}.csv"
    send_date = date.today().isoformat()
    count_limit = args.count if args.count > 0 else float("inf")

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    if args.ensure_index:
        ensure_index(conn)

    print("Fetching distinct Drop_Name values...")
    drops = fetch_distinct_drops(conn, args.unsent_only, args.resume_from_drop)
    print(f"  Found {len(drops)} distinct drops to export.")

    if not drops:
        print("No enriched drops to export.")
        conn.close()
        return

    fieldnames = [
        "First name", "address", "debt load",
        "phone1", "phone2", "phone3", "phone4", "phone5",
        "send date",
    ]

    total_written = 0
    total_drops = len(drops)
    start_time = time.time()

    file_mode = "a" if args.resume_from_drop else "w"
    print(f"{'Appending to' if args.resume_from_drop else 'Writing'} report: {output_path}")

    with open(output_path, file_mode, newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        if not args.resume_from_drop:
            writer.writeheader()

        for drop_idx, drop_name in enumerate(drops, 1):
            if total_written >= count_limit:
                print(f"\n  Reached --count limit ({int(count_limit):,}). Stopping.")
                break

            drop_start = time.time()
            rows = fetch_rows_for_drop(conn, drop_name, args.unsent_only)

            if not rows:
                continue

            # Trim to count limit
            remaining = int(count_limit - total_written)
            if len(rows) > remaining:
                rows = rows[:remaining]

            pks = []
            for row in rows:
                writer.writerow({
                    "First name": parse_first_name(row.get("Client", "")),
                    "address": (row.get("Address", "") or "").upper(),
                    "debt load": format_debt_load(row.get("Debt_Amount", "")),
                    "phone1": row.get("phone1", "") or "",
                    "phone2": row.get("phone2", "") or "",
                    "phone3": row.get("phone3", "") or "",
                    "phone4": row.get("phone4", "") or "",
                    "phone5": row.get("phone5", "") or "",
                    "send date": send_date,
                })
                pks.append(row["PK"])

            if args.update_send_date:
                update_send_dates(conn, pks, send_date)

            total_written += len(rows)
            elapsed = time.time() - start_time
            drop_elapsed = time.time() - drop_start

            print(
                f"  [{drop_idx}/{total_drops}] Drop {drop_name}: "
                f"{len(rows):,} rows ({drop_elapsed:.1f}s) | "
                f"Total: {total_written:,} | Elapsed: {elapsed:.0f}s"
            )

    elapsed = time.time() - start_time
    print(f"\n{'='*60}")
    print(f"Done in {elapsed:.1f}s ({elapsed/60:.1f}m).")
    print(f"  Report: {output_path}")
    print(f"  Total rows written: {total_written:,}")
    print(f"  Drops processed: {min(drop_idx, total_drops)}/{total_drops}")
    if args.update_send_date:
        print(f"  sms_send_date updated to: {send_date}")
    else:
        print("  sms_send_date NOT updated (use --update-send-date to mark them).")
    print(f"{'='*60}")


if __name__ == "__main__":
    main()
