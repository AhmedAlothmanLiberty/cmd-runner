"""
Phase 2: Backfill phone numbers and sms_send_date from Sam's 3 CSV files
into TblMailersUnique on SQL Server.

Matching key: UPPER(first word of Client) = First name,
              UPPER(Address) = address,
              Debt_Amount = debt load

For the part-00000 file (one row per phone), we pivot multiple phone rows
into phone1-phone5 per person before matching.

Usage:
    python backfill_sam_files.py [--dry-run] [--send-date 2025-03-13]
"""
import argparse
import csv
import os
import sys
from collections import defaultdict
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

BATCH_SIZE = 1000
EE_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), "EE")

# --- files with phone1-phone5 columns ---
MULTI_PHONE_FILES = [
    "1M_contact_list.csv",
    "4M_filtered.csv",
]
# --- file with single cell phone column (one row per phone) ---
SINGLE_PHONE_FILE = "part-00000-b9ef6cbb-b231-4fbc-afd5-2f58224a86cf-c000.csv"


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def normalize_debt(val):
    """Normalize debt load to integer string for matching."""
    if not val:
        return "0"
    try:
        return str(int(float(val)))
    except (ValueError, TypeError):
        return "0"


def parse_multi_phone_csv(filepath):
    """Parse CSV with phone1-phone5 columns. Returns list of dicts."""
    records = []
    with open(filepath, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            first_name = (row.get("First name", "") or "").strip().upper()
            address = (row.get("address", "") or "").strip().upper()
            debt = normalize_debt(row.get("debt load", ""))
            phones = []
            for i in range(1, 6):
                p = (row.get(f"phone{i}", "") or "").strip()
                if p:
                    phones.append(p)
            if first_name and address:
                records.append({
                    "first_name": first_name,
                    "address": address,
                    "debt": debt,
                    "phones": phones,
                })
    return records


def parse_single_phone_csv(filepath):
    """Parse CSV with single cell phone column. Pivot into phone1-phone5 per person."""
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
        records.append({
            "first_name": first_name,
            "address": address,
            "debt": debt,
            "phones": phones,
        })
    return records


def update_batch(cursor, conn, batch, send_date, dry_run):
    """Update TblMailersUnique for a batch of records."""
    updated = 0
    for rec in batch:
        phones = (rec["phones"] + [None] * 5)[:5]

        if dry_run:
            updated += 1
            continue

        cursor.execute("""
            UPDATE dbo.TblMailersUnique
            SET phone1 = %s, phone2 = %s, phone3 = %s, phone4 = %s, phone5 = %s,
                sms_send_date = %s
            WHERE UPPER(LEFT(Client, CASE
                    WHEN CHARINDEX(' ', Client) > 0 THEN CHARINDEX(' ', Client) - 1
                    ELSE LEN(Client) END)) = %s
              AND UPPER(Address) = %s
              AND CAST(Debt_Amount AS INT) = %s
              AND phone1 IS NULL
        """, (
            phones[0], phones[1], phones[2], phones[3], phones[4],
            send_date,
            rec["first_name"],
            rec["address"],
            int(rec["debt"]) if rec["debt"] != "0" else 0,
        ))
        updated += cursor.rowcount

    if not dry_run:
        conn.commit()
    return updated


def process_file(conn, records, label, send_date, dry_run):
    """Process all records from a parsed file."""
    cursor = conn.cursor()
    total_updated = 0

    for i in range(0, len(records), BATCH_SIZE):
        batch = records[i:i + BATCH_SIZE]
        updated = update_batch(cursor, conn, batch, send_date, dry_run)
        total_updated += updated
        processed = min(i + BATCH_SIZE, len(records))
        print(f"  [{label}] {processed}/{len(records)} processed, {total_updated} matched")

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

    # --- Multi-phone files (phone1-phone5 already present) ---
    for filename in MULTI_PHONE_FILES:
        filepath = os.path.join(EE_DIR, filename)
        if not os.path.exists(filepath):
            print(f"  WARNING: {filepath} not found, skipping.")
            continue

        print(f"\nParsing {filename}...")
        records = parse_multi_phone_csv(filepath)
        print(f"  Loaded {len(records)} records.")

        updated = process_file(conn, records, filename, args.send_date, args.dry_run)
        grand_total += updated
        print(f"  {filename}: {updated} rows matched and updated.")

    # --- Single-phone file (needs pivoting) ---
    filepath = os.path.join(EE_DIR, SINGLE_PHONE_FILE)
    if os.path.exists(filepath):
        print(f"\nParsing {SINGLE_PHONE_FILE} (pivoting phone rows)...")
        records = parse_single_phone_csv(filepath)
        print(f"  Loaded {len(records)} unique people from phone rows.")

        updated = process_file(conn, records, "part-00000", args.send_date, args.dry_run)
        grand_total += updated
        print(f"  part-00000: {updated} rows matched and updated.")
    else:
        print(f"  WARNING: {filepath} not found, skipping.")

    conn.close()
    print(f"\nDone. Total rows updated: {grand_total}")


if __name__ == "__main__":
    main()
