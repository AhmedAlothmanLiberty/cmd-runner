"""
Generate SMS report for Sam.

Pulls X records from mailer_data, sorted by mailer_date DESC then sms_date ASC.
Excludes:
  - Enrolled contacts (TblEnrollment via TblContacts.LLG_ID) — permanent exclusion
  - Contacted but not enrolled (TblContacts) — exclude for 6 months from Created_Date

On pull, updates sms_date to today so those records aren't pulled again next time.

Usage:
    python generate_report.py --count 500 [--output report.csv] [--dry-run]

Options:
    --count     Number of records to generate
    --output    Output CSV file path (default: sms_report_YYYY-MM-DD.csv)
    --dry-run   Preview without updating sms_date
"""
import argparse
import csv
import sys
from datetime import date, datetime, timedelta

import pymssql
import pymysql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE,
    CONTACT_EXCLUSION_MONTHS,
)


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def get_mysql_connection():
    return pymysql.connect(
        host=MYSQL_HOST,
        port=MYSQL_PORT,
        user=MYSQL_USER,
        password=MYSQL_PASSWORD,
        database=MYSQL_DATABASE,
        charset="utf8mb4",
        autocommit=False,
    )


def get_excluded_external_ids(mssql_conn):
    """
    Query SQL Server to get external_ids that should be excluded:
    1. Enrolled in TblEnrollment — permanent exclusion
    2. Contacted in TblContacts but not enrolled — exclude for 6 months from Created_Date
    """
    cursor = mssql_conn.cursor(as_dict=True)

    cutoff_date = datetime.now() - timedelta(days=CONTACT_EXCLUSION_MONTHS * 30)
    cutoff_str = cutoff_date.strftime("%Y-%m-%d")

    # Get external_ids of enrolled contacts (permanent exclusion)
    # TblContacts.LLG_ID → TblEnrollment.LLG_ID
    enrolled_query = """
        SELECT DISTINCT c.External_ID
        FROM dbo.TblContacts c
        INNER JOIN dbo.TblEnrollment e ON c.LLG_ID = e.LLG_ID
        WHERE c.External_ID IS NOT NULL
          AND e.Cancel_Date IS NULL
    """
    cursor.execute(enrolled_query)
    enrolled_ids = {row["External_ID"] for row in cursor.fetchall()}
    print(f"  Enrolled (permanent exclude): {len(enrolled_ids)} external_ids")

    # Get external_ids of contacts within last 6 months who are NOT enrolled
    contacted_query = """
        SELECT DISTINCT c.External_ID
        FROM dbo.TblContacts c
        LEFT JOIN dbo.TblEnrollment e ON c.LLG_ID = e.LLG_ID
        WHERE c.External_ID IS NOT NULL
          AND c.Created_Date >= %s
          AND e.LLG_ID IS NULL
    """
    cursor.execute(contacted_query, (cutoff_str,))
    contacted_ids = {row["External_ID"] for row in cursor.fetchall()}
    print(f"  Contacted <6mo, not enrolled (exclude): {len(contacted_ids)} external_ids")

    cursor.close()
    return enrolled_ids | contacted_ids


def fetch_candidates(mysql_conn, count, excluded_ids):
    """
    Fetch candidate records from mailer_data, sorted by:
    1. mailer_date DESC (newest mailers first)
    2. sms_date ASC (oldest SMS date = hasn't been pulled recently)

    Excludes records with phone1 IS NULL (no phone to SMS).
    Excludes records whose external_id is in the exclusion set.
    """
    cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)

    if excluded_ids:
        placeholders = ",".join(["%s"] * len(excluded_ids))
        query = f"""
            SELECT id, original_pk, drop_name, client, external_id,
                   city, state, zip, debt_amount, address,
                   phone1, phone2, phone3, phone4, phone5,
                   mailer_date, sms_date
            FROM mailer_data
            WHERE phone1 IS NOT NULL
              AND phone1 != ''
              AND external_id NOT IN ({placeholders})
            ORDER BY mailer_date DESC, sms_date ASC
            LIMIT %s
        """
        params = list(excluded_ids) + [count]
    else:
        query = """
            SELECT id, original_pk, drop_name, client, external_id,
                   city, state, zip, debt_amount, address,
                   phone1, phone2, phone3, phone4, phone5,
                   mailer_date, sms_date
            FROM mailer_data
            WHERE phone1 IS NOT NULL
              AND phone1 != ''
            ORDER BY mailer_date DESC, sms_date ASC
            LIMIT %s
        """
        params = [count]

    cursor.execute(query, params)
    rows = cursor.fetchall()
    cursor.close()
    return rows


def update_sms_dates(mysql_conn, record_ids):
    """Update sms_date to today for all pulled records."""
    if not record_ids:
        return
    cursor = mysql_conn.cursor()
    placeholders = ",".join(["%s"] * len(record_ids))
    cursor.execute(
        f"UPDATE mailer_data SET sms_date = %s WHERE id IN ({placeholders})",
        [date.today().isoformat()] + list(record_ids),
    )
    mysql_conn.commit()
    cursor.close()


def parse_first_name(client_str):
    """Extract first name from Client field (e.g. 'JOHN MICHAEL DOE' -> 'JOHN')."""
    parts = client_str.strip().upper().split() if client_str else []
    return parts[0] if parts else ""


def format_debt_load(value):
    if value in (None, ""):
        return ""
    try:
        numeric = float(value)
    except (TypeError, ValueError):
        return value
    if numeric.is_integer():
        return str(int(numeric))
    return f"{numeric:.2f}".rstrip("0").rstrip(".")


def write_report(rows, output_path):
    """Write report rows to CSV with 4 columns: First name, address, debt load, cell phone."""
    if not rows:
        print("No records to write.")
        return

    fieldnames = ["First name", "address", "debt load", "cell phone"]

    with open(output_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow({
                "First name": parse_first_name(row.get("client", "")),
                "address": (row.get("address", "") or "").upper(),
                "debt load": format_debt_load(row.get("debt_amount", "")),
                "cell phone": row.get("phone1", ""),
            })

    print(f"Report written to: {output_path}")


def main():
    parser = argparse.ArgumentParser(description="Generate SMS report for Sam")
    parser.add_argument("--count", type=int, required=True,
                        help="Number of records to pull")
    parser.add_argument("--output", type=str, default=None,
                        help="Output CSV path (default: sms_report_YYYY-MM-DD.csv)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Preview only, do not update sms_date")
    args = parser.parse_args()

    output_path = args.output or f"sms_report_{date.today().isoformat()}.csv"

    print("Step 1: Fetching exclusion lists from SQL Server...")
    mssql_conn = get_mssql_connection()
    excluded_ids = get_excluded_external_ids(mssql_conn)
    print(f"  Total excluded: {len(excluded_ids)} external_ids")
    mssql_conn.close()

    print(f"\nStep 2: Fetching top {args.count} candidates from RDS MySQL...")
    mysql_conn = get_mysql_connection()
    rows = fetch_candidates(mysql_conn, args.count, excluded_ids)
    print(f"  Found: {len(rows)} records")

    if not rows:
        print("No eligible records found.")
        mysql_conn.close()
        return

    print(f"\nStep 3: Writing report to {output_path}...")
    write_report(rows, output_path)

    if args.dry_run:
        print("\n[DRY RUN] sms_date NOT updated.")
    else:
        print("\nStep 4: Updating sms_date to today for pulled records...")
        record_ids = [r["id"] for r in rows]
        update_sms_dates(mysql_conn, record_ids)
        print(f"  Updated {len(record_ids)} records.")

    mysql_conn.close()
    print("\nDone.")


if __name__ == "__main__":
    main()
