"""
Phase 1: Add phone1-phone5 and sms_send_date columns to TblMailersUnique.

Usage:
    python migrate_tblmailers.py [--dry-run]
"""
import argparse
import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

COLUMNS_TO_ADD = [
    ("phone1", "VARCHAR(32) NULL"),
    ("phone2", "VARCHAR(32) NULL"),
    ("phone3", "VARCHAR(32) NULL"),
    ("phone4", "VARCHAR(32) NULL"),
    ("phone5", "VARCHAR(32) NULL"),
    ("sms_send_date", "DATE NULL"),
]


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def column_exists(cursor, table, column):
    cursor.execute("""
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = %s AND COLUMN_NAME = %s
    """, (table, column))
    return cursor.fetchone()[0] > 0


def main():
    parser = argparse.ArgumentParser(description="Add phone + sms_send_date columns to TblMailersUnique")
    parser.add_argument("--dry-run", action="store_true", help="Print SQL without executing")
    args = parser.parse_args()

    conn = get_mssql_connection()
    cursor = conn.cursor()

    for col_name, col_def in COLUMNS_TO_ADD:
        if column_exists(cursor, "TblMailersUnique", col_name):
            print(f"  Column '{col_name}' already exists -- skipping.")
            continue

        sql = f"ALTER TABLE dbo.TblMailersUnique ADD {col_name} {col_def}"
        if args.dry_run:
            print(f"  [DRY RUN] {sql}")
        else:
            print(f"  Adding column '{col_name}'...")
            cursor.execute(sql)
            conn.commit()
            print(f"  Done.")

    cursor.close()
    conn.close()
    print("Migration complete.")


if __name__ == "__main__":
    main()
