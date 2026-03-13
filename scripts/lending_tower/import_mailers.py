"""
Import mailer data from SQL Server TblMailers into RDS MySQL mailer_data table.
Handles deduplication via external_id unique key (INSERT IGNORE).

Usage:
    python import_mailers.py [--mailer-date YYYY-MM-DD] [--drop-name LTIWCO2905A]

If --mailer-date is not provided, defaults to today's date.
If --drop-name is provided, only imports records from that specific drop.
"""
import argparse
import sys
from datetime import date, datetime

import pymssql
import pymysql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE,
    DEFAULT_SMS_DATE,
)

BATCH_SIZE = 1000
SOURCE_BATCH_SIZE = 10000


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


def fetch_mailers(mssql_conn, last_pk=0, drop_name=None, batch_size=SOURCE_BATCH_SIZE):
    query = f"""
        SELECT TOP {int(batch_size)}
            PK, Drop_Name, Client, External_ID,
            City, State, Zip,
            Debt_Amount, Old_Payment, New_Payment,
            Debt_Tier, Address, County, ELT_Score, Population
        FROM dbo.TblMailers
        WHERE PK > %s
    """
    params = [last_pk]
    if drop_name:
        query += " AND Drop_Name = %s"
        params.append(drop_name)
    query += " ORDER BY PK"

    cursor = mssql_conn.cursor(as_dict=True)
    cursor.execute(query, tuple(params))
    rows = cursor.fetchall()
    cursor.close()
    return rows


def count_mailers(mssql_conn, drop_name=None):
    cursor = mssql_conn.cursor()
    if drop_name:
        cursor.execute("SELECT COUNT(*) FROM dbo.TblMailers WHERE Drop_Name = %s", (drop_name,))
    else:
        cursor.execute("SELECT COUNT(*) FROM dbo.TblMailers")
    total = cursor.fetchone()[0]
    cursor.close()
    return total


def insert_mailers(mysql_conn, rows, mailer_date):
    """Insert mailer records into RDS MySQL with dedup (INSERT IGNORE on external_id)."""
    insert_sql = """
        INSERT IGNORE INTO mailer_data (
            original_pk, drop_name, client, external_id,
            city, state, zip,
            debt_amount, old_payment, new_payment,
            debt_tier, address, county, elt_score, population,
            mailer_date, sms_date
        ) VALUES (
            %s, %s, %s, %s,
            %s, %s, %s,
            %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s
        )
    """
    cursor = mysql_conn.cursor()
    inserted = 0
    skipped = 0

    batch = []
    for row in rows:
        batch.append((
            row["PK"], row["Drop_Name"], row["Client"], row["External_ID"],
            row["City"], row["State"], row["Zip"],
            row["Debt_Amount"] or 0, row["Old_Payment"] or 0, row["New_Payment"] or 0,
            row["Debt_Tier"], row["Address"], row["County"],
            row["ELT_Score"], row["Population"],
            mailer_date, DEFAULT_SMS_DATE,
        ))

        if len(batch) >= BATCH_SIZE:
            cursor.executemany(insert_sql, batch)
            inserted += cursor.rowcount
            skipped += len(batch) - cursor.rowcount
            mysql_conn.commit()
            batch = []

    if batch:
        cursor.executemany(insert_sql, batch)
        inserted += cursor.rowcount
        skipped += len(batch) - cursor.rowcount
        mysql_conn.commit()

    cursor.close()
    return inserted, skipped


def main():
    parser = argparse.ArgumentParser(description="Import mailers from SQL Server to RDS MySQL")
    parser.add_argument("--mailer-date", type=str, default=date.today().isoformat(),
                        help="Mailer send date (YYYY-MM-DD), default: today")
    parser.add_argument("--drop-name", type=str, default=None,
                        help="Only import records from this drop name (e.g., LTIWCO2905A)")
    args = parser.parse_args()

    try:
        datetime.strptime(args.mailer_date, "%Y-%m-%d")
    except ValueError:
        print(f"ERROR: Invalid date format '{args.mailer_date}'. Use YYYY-MM-DD.")
        sys.exit(1)

    print(f"Connecting to SQL Server ({MSSQL_HOST})...")
    mssql_conn = get_mssql_connection()

    print(f"Connecting to RDS MySQL ({MYSQL_HOST})...")
    mysql_conn = get_mysql_connection()

    filter_msg = f" (drop: {args.drop_name})" if args.drop_name else " (all records)"
    print(f"Counting mailers{filter_msg}...")
    total_source_rows = count_mailers(mssql_conn, args.drop_name)
    print(f"Source rows: {total_source_rows}")

    print(f"Inserting into mailer_data (mailer_date={args.mailer_date})...")
    total_read = 0
    total_inserted = 0
    total_skipped = 0
    last_pk = 0

    while True:
        rows = fetch_mailers(mssql_conn, last_pk=last_pk, drop_name=args.drop_name)
        if not rows:
            break

        inserted, skipped = insert_mailers(mysql_conn, rows, args.mailer_date)
        total_read += len(rows)
        total_inserted += inserted
        total_skipped += skipped
        last_pk = rows[-1]["PK"]

        print(
            f"Processed {total_read}/{total_source_rows} rows "
            f"(last PK: {last_pk}, inserted: {total_inserted}, skipped: {total_skipped})"
        )

    print(f"Done. Read: {total_read}, Inserted: {total_inserted}, Duplicates skipped: {total_skipped}")

    mssql_conn.close()
    mysql_conn.close()


if __name__ == "__main__":
    main()
