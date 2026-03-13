"""
Phase 4: Export enriched records from TblMailersUnique for Sam.

Outputs CSV in Sam's exact format:
    First name, address, debt load, phone1, phone2, phone3, phone4, phone5, send date

One row per person (not per phone). Only rows with at least one phone.
Newest mailer records first.

Usage:
    python export_sam_report.py --count 0 [--output report.csv] [--update-send-date]

Options:
    --count             Number of records (0 = all enriched)
    --output            Output CSV path (default: sam_report_YYYY-MM-DD.csv)
    --update-send-date  Set sms_send_date to today for exported records
    --unsent-only       Only export rows where sms_send_date IS NULL
"""
import argparse
import csv
import sys
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


def fetch_enriched(mssql_conn, count, unsent_only, last_pk=0):
    """Fetch enriched rows from TblMailersUnique."""
    cursor = mssql_conn.cursor(as_dict=True)

    where_clause = "WHERE phone1 IS NOT NULL AND PK > %s"
    if unsent_only:
        where_clause += " AND sms_send_date IS NULL"

    limit_clause = f"TOP {int(count)}" if count > 0 else ""

    query = f"""
        SELECT {limit_clause}
            PK, Client, Address, Debt_Amount,
            phone1, phone2, phone3, phone4, phone5,
            sms_send_date
        FROM dbo.TblMailersUnique
        {where_clause}
        ORDER BY PK ASC
    """
    cursor.execute(query, (last_pk,))
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
    args = parser.parse_args()

    output_path = args.output or f"sam_report_{date.today().isoformat()}.csv"
    send_date = date.today().isoformat()

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    print("Fetching enriched records...")
    rows = fetch_enriched(conn, args.count, args.unsent_only)
    print(f"  Found {len(rows)} enriched records.")

    if not rows:
        print("No enriched records to export.")
        conn.close()
        return

    fieldnames = [
        "First name", "address", "debt load",
        "phone1", "phone2", "phone3", "phone4", "phone5",
        "send date",
    ]

    print(f"Writing report to {output_path}...")
    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
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

    print(f"Report written: {output_path} ({len(rows)} rows)")

    if args.update_send_date:
        print("Updating sms_send_date for exported records...")
        pks = [r["PK"] for r in rows]
        update_send_dates(conn, pks, send_date)
        print(f"  Updated {len(pks)} records.")
    else:
        print("sms_send_date NOT updated (use --update-send-date to mark them).")

    conn.close()
    print("Done.")


if __name__ == "__main__":
    main()
