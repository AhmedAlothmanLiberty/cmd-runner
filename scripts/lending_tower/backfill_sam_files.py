"""
Phase 2: Backfill phone numbers and sms_send_date from Sam's 3 CSV files
into TblMailersUnique on SQL Server.

Matching key (primary): UPPER(Address) + UPPER(first word of Client)
Tiebreaker: Debt_Amount = debt load

Uses multi-row INSERT VALUES (up to 1000 per statement) for fast staging,
then a single set-based UPDATE JOIN per file.

Usage:
    python backfill_sam_files.py [--dry-run] [--send-date 2025-03-13]
"""
import argparse
import csv
import os
import time
from collections import defaultdict
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

INSERT_ROWS_PER_STMT = 900
COMMIT_EVERY = 50000
EE_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), "EE")

MULTI_PHONE_FILES = [
    "1M_contact_list.csv",
    "4M_filtered.csv",
]
SINGLE_PHONE_FILE = "part-00000-b9ef6cbb-b231-4fbc-afd5-2f58224a86cf-c000.csv"


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def esc(val):
    """Escape a string value for SQL literal."""
    if val is None:
        return "NULL"
    return "'" + str(val).replace("'", "''") + "'"


def normalize_debt(val):
    if not val:
        return 0
    try:
        return int(float(val))
    except (ValueError, TypeError):
        return 0


def parse_multi_phone_csv(filepath):
    grouped = defaultdict(list)
    with open(filepath, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            first_name = (row.get("First name", "") or "").strip().upper()
            address = (row.get("address", "") or "").strip().upper()
            debt = normalize_debt(row.get("debt load", ""))
            key = (first_name, address, debt)
            for i in range(1, 6):
                p = (row.get(f"phone{i}", "") or "").strip()
                if p and p not in grouped[key] and len(grouped[key]) < 5:
                    grouped[key].append(p)
    records = []
    for (first_name, address, debt), phones in grouped.items():
        if first_name and address:
            records.append((first_name, address, debt, phones))
    return records


def parse_single_phone_csv(filepath):
    grouped = defaultdict(list)
    with open(filepath, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            first_name = (row.get("First name", "") or "").strip().upper()
            address = (row.get("address", "") or "").strip().upper()
            debt = normalize_debt(row.get("debt load", ""))
            phone = (row.get("cell phone", "") or "").strip()
            if first_name and address and phone:
                key = (first_name, address, debt)
                if phone not in grouped[key] and len(grouped[key]) < 5:
                    grouped[key].append(phone)
    records = []
    for (first_name, address, debt), phones in grouped.items():
        records.append((first_name, address, debt, phones))
    return records


def recreate_stage_table(cursor, label):
    safe_label = label.replace("-", "_").replace(".", "_").replace(" ", "_")
    table_name = f"##sam_{safe_label}"
    cursor.execute(f"IF OBJECT_ID('tempdb..{table_name}') IS NOT NULL DROP TABLE {table_name}")
    cursor.execute(f"""
        CREATE TABLE {table_name} (
            first_name VARCHAR(255) NOT NULL,
            address VARCHAR(500) NOT NULL,
            debt_amount INT NOT NULL,
            phone1 VARCHAR(32) NULL,
            phone2 VARCHAR(32) NULL,
            phone3 VARCHAR(32) NULL,
            phone4 VARCHAR(32) NULL,
            phone5 VARCHAR(32) NULL
        )
    """)
    return table_name


def bulk_insert_staged(cursor, conn, table_name, records, label):
    """Insert records using multi-row INSERT VALUES for speed."""
    total = len(records)
    inserted = 0
    t0 = time.time()

    for i in range(0, total, INSERT_ROWS_PER_STMT):
        batch = records[i:i + INSERT_ROWS_PER_STMT]
        values_parts = []
        for first_name, address, debt, phones in batch:
            p = (phones + [None] * 5)[:5]
            row_sql = f"({esc(first_name)},{esc(address)},{int(debt)},{esc(p[0])},{esc(p[1])},{esc(p[2])},{esc(p[3])},{esc(p[4])})"
            values_parts.append(row_sql)

        sql = f"INSERT INTO {table_name} (first_name,address,debt_amount,phone1,phone2,phone3,phone4,phone5) VALUES " + ",".join(values_parts)
        cursor.execute(sql)
        inserted += len(batch)

        if inserted % COMMIT_EVERY < INSERT_ROWS_PER_STMT:
            conn.commit()
            elapsed = time.time() - t0
            rate = inserted / elapsed if elapsed > 0 else 0
            print(f"  [{label}] {inserted}/{total} staged ({rate:.0f} rows/s)")

    conn.commit()
    elapsed = time.time() - t0
    print(f"  [{label}] staging done: {inserted} rows in {elapsed:.1f}s")


def create_stage_index(cursor, table_name):
    cursor.execute(f"""
        CREATE INDEX IX_{table_name.replace('#', '').replace('.', '_')}_match
        ON {table_name} (address, first_name)
    """)


def run_stage_update(cursor, table_name, send_date, dry_run):
    match_cte = f"""
        WITH matched AS (
            SELECT
                t.PK,
                s.phone1, s.phone2, s.phone3, s.phone4, s.phone5,
                ROW_NUMBER() OVER (
                    PARTITION BY s.first_name, s.address
                    ORDER BY ABS(CAST(t.Debt_Amount AS INT) - s.debt_amount) ASC
                ) AS rn
            FROM dbo.TblMailersUnique t
            JOIN {table_name} s
              ON UPPER(t.Address) = s.address
             AND UPPER(LEFT(t.Client, CASE
                    WHEN CHARINDEX(' ', t.Client) > 0 THEN CHARINDEX(' ', t.Client) - 1
                    ELSE LEN(t.Client) END)) = s.first_name
            WHERE t.phone1 IS NULL
        )
    """

    if dry_run:
        cursor.execute(match_cte + " SELECT COUNT(*) FROM matched WHERE rn = 1")
        total = cursor.fetchone()[0]
        print(f"  [DRY RUN] {total} rows would match")
        return total

    cursor.execute(match_cte + """
        UPDATE t2
        SET t2.phone1 = m.phone1,
            t2.phone2 = m.phone2,
            t2.phone3 = m.phone3,
            t2.phone4 = m.phone4,
            t2.phone5 = m.phone5,
            t2.sms_send_date = %s
        FROM dbo.TblMailersUnique t2
        JOIN matched m ON t2.PK = m.PK
        WHERE m.rn = 1
    """, (send_date,))
    return cursor.rowcount


def process_file(conn, records, label, send_date, dry_run):
    cursor = conn.cursor()
    table_name = recreate_stage_table(cursor, label)
    conn.commit()
    print(f"  [{label}] staging table: {table_name}")

    bulk_insert_staged(cursor, conn, table_name, records, label)

    print(f"  [{label}] creating index...")
    create_stage_index(cursor, table_name)
    conn.commit()

    print(f"  [{label}] running bulk UPDATE JOIN (this may take a few minutes)...")
    t0 = time.time()
    total_updated = run_stage_update(cursor, table_name, send_date, dry_run)
    if not dry_run:
        conn.commit()
    elapsed = time.time() - t0
    print(f"  [{label}] bulk update done: {total_updated} matched in {elapsed:.1f}s")

    cursor.execute(f"DROP TABLE {table_name}")
    conn.commit()
    cursor.close()
    return total_updated


def main():
    parser = argparse.ArgumentParser(description="Backfill Sam's CSV phone data into TblMailersUnique")
    parser.add_argument("--dry-run", action="store_true", help="Count matches without updating")
    parser.add_argument("--send-date", type=str, default=date.today().isoformat(),
                        help="SMS send date to set (YYYY-MM-DD, default: today)")
    args = parser.parse_args()

    print(f"EE directory: {EE_DIR}")
    print(f"Send date: {args.send_date}")
    if args.dry_run:
        print("[DRY RUN MODE]")

    conn = get_mssql_connection()
    grand_total = 0

    for filename in MULTI_PHONE_FILES:
        filepath = os.path.join(EE_DIR, filename)
        if not os.path.exists(filepath):
            print(f"  WARNING: {filepath} not found, skipping.")
            continue
        print(f"\nParsing {filename}...")
        records = parse_multi_phone_csv(filepath)
        print(f"  Loaded {len(records)} unique records.")
        updated = process_file(conn, records, filename, args.send_date, args.dry_run)
        grand_total += updated
        print(f"  {filename}: {updated} rows matched and updated.")

    filepath = os.path.join(EE_DIR, SINGLE_PHONE_FILE)
    if os.path.exists(filepath):
        print(f"\nParsing {SINGLE_PHONE_FILE} (pivoting)...")
        records = parse_single_phone_csv(filepath)
        print(f"  Loaded {len(records)} unique people.")
        updated = process_file(conn, records, "part_00000", args.send_date, args.dry_run)
        grand_total += updated
        print(f"  part-00000: {updated} rows matched and updated.")
    else:
        print(f"  WARNING: {filepath} not found, skipping.")

    conn.close()
    print(f"\nDone. Total rows updated: {grand_total}")


if __name__ == "__main__":
    main()
