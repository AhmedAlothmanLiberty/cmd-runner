"""
Bulk enrich mailer_data with phone numbers from the TU Identity Graph.

Strategy (single Athena query via S3 staging table):
  1. Export unenriched rows from MySQL to CSV
  2. Upload CSV to S3
  3. Create/replace a temp Athena external table over that CSV
  4. Run ONE Athena JOIN query against TU graph name+address+phone
  5. Paginate results and batch-update MySQL
  6. Mark all attempted rows so unmatched ones aren't retried

Usage:
    python enrich_phones.py [--limit 0] [--dry-run]

Options:
    --limit   Max records to process (0 = all unenriched, default: 0)
    --dry-run Preview matches without updating MySQL
"""
import argparse
import csv
import io
import os
import time
import sys
from datetime import datetime

import boto3
import pymysql

from config import (
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE,
    AWS_REGION, ATHENA_DATABASE, ATHENA_RESULTS_BUCKET,
    AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN,
)

STAGING_S3_PREFIX = "staging/enrich_phones"
STAGING_TABLE = "tmp_mailer_lookup"
ATHENA_POLL_START = 0.5
ATHENA_POLL_MAX = 4.0
MYSQL_BATCH = 1000


# ---------------------------------------------------------------------------
# AWS clients
# ---------------------------------------------------------------------------
def get_boto3_kwargs():
    kwargs = {"region_name": AWS_REGION}
    if AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY:
        kwargs["aws_access_key_id"] = AWS_ACCESS_KEY_ID
        kwargs["aws_secret_access_key"] = AWS_SECRET_ACCESS_KEY
        if AWS_SESSION_TOKEN:
            kwargs["aws_session_token"] = AWS_SESSION_TOKEN
    return kwargs


# ---------------------------------------------------------------------------
# MySQL helpers
# ---------------------------------------------------------------------------
def get_mysql_connection():
    return pymysql.connect(
        host=MYSQL_HOST, port=MYSQL_PORT,
        user=MYSQL_USER, password=MYSQL_PASSWORD,
        database=MYSQL_DATABASE, charset="utf8mb4", autocommit=False,
    )


def ensure_column(mysql_conn):
    """Add enrich_attempted_at column if it doesn't exist."""
    cur = mysql_conn.cursor()
    cur.execute("""
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = %s AND TABLE_NAME = 'mailer_data'
          AND COLUMN_NAME = 'enrich_attempted_at'
    """, (MYSQL_DATABASE,))
    if cur.fetchone()[0] == 0:
        print("  Adding enrich_attempted_at column...")
        cur.execute("ALTER TABLE mailer_data ADD COLUMN enrich_attempted_at DATETIME NULL DEFAULT NULL")
        mysql_conn.commit()
    cur.close()


def fetch_unenriched(mysql_conn, limit):
    """Fetch rows where phone1 is empty AND we haven't tried yet."""
    cur = mysql_conn.cursor(pymysql.cursors.DictCursor)
    sql = """
        SELECT id, client, address, city, state, zip
        FROM mailer_data
        WHERE (phone1 IS NULL OR phone1 = '')
          AND enrich_attempted_at IS NULL
    """
    if limit > 0:
        sql += " LIMIT %s"
        cur.execute(sql, (limit,))
    else:
        cur.execute(sql)
    rows = cur.fetchall()
    cur.close()
    return rows


def parse_name(client_str):
    parts = client_str.strip().upper().split()
    if len(parts) >= 2:
        return parts[0], parts[-1]
    elif len(parts) == 1:
        return parts[0], ""
    return "", ""


# ---------------------------------------------------------------------------
# S3 staging
# ---------------------------------------------------------------------------
def results_bucket_name():
    """Extract bucket name from s3://bucket/... URI."""
    return ATHENA_RESULTS_BUCKET.replace("s3://", "").strip("/").split("/")[0]


def upload_staging_csv(s3_client, records):
    """Build CSV in memory and upload to S3. Returns S3 URI of folder."""
    buf = io.StringIO()
    writer = csv.writer(buf)
    skipped = 0
    for rec in records:
        first_name, last_name = parse_name(rec["client"])
        address = rec["address"].strip().upper() if rec["address"] else ""
        if not first_name or not address:
            skipped += 1
            continue
        writer.writerow([rec["id"], first_name, last_name, address])

    bucket = results_bucket_name()
    key = f"{STAGING_S3_PREFIX}/lookup.csv"
    s3_client.put_object(Bucket=bucket, Key=key, Body=buf.getvalue().encode("utf-8"))
    print(f"  Uploaded {len(records) - skipped} rows to s3://{bucket}/{key} (skipped {skipped})")
    return f"s3://{bucket}/{STAGING_S3_PREFIX}/"


def cleanup_staging(s3_client):
    bucket = results_bucket_name()
    key = f"{STAGING_S3_PREFIX}/lookup.csv"
    try:
        s3_client.delete_object(Bucket=bucket, Key=key)
    except Exception:
        pass


# ---------------------------------------------------------------------------
# Athena helpers
# ---------------------------------------------------------------------------
def run_athena(athena_client, query):
    """Submit query, poll until done, return query_id or None."""
    resp = athena_client.start_query_execution(
        QueryString=query,
        QueryExecutionContext={"Database": ATHENA_DATABASE},
        ResultConfiguration={"OutputLocation": ATHENA_RESULTS_BUCKET},
    )
    qid = resp["QueryExecutionId"]
    poll = ATHENA_POLL_START
    while True:
        st = athena_client.get_query_execution(QueryExecutionId=qid)
        state = st["QueryExecution"]["Status"]["State"]
        if state == "SUCCEEDED":
            return qid
        if state in ("FAILED", "CANCELLED"):
            reason = st["QueryExecution"]["Status"].get("StateChangeReason", "Unknown")
            print(f"  Athena query {state}: {reason}")
            return None
        time.sleep(poll)
        poll = min(poll * 1.5, ATHENA_POLL_MAX)


def create_staging_table(athena_client, s3_location):
    """Create or replace the temp external table in Athena."""
    drop_q = f'DROP TABLE IF EXISTS "{ATHENA_DATABASE}"."{STAGING_TABLE}"'
    run_athena(athena_client, drop_q)

    create_q = f"""
        CREATE EXTERNAL TABLE "{ATHENA_DATABASE}"."{STAGING_TABLE}" (
            mailer_id INT,
            first_name STRING,
            last_name STRING,
            address STRING
        )
        ROW FORMAT SERDE 'org.apache.hadoop.hive.serde2.OpenCSVSerde'
        WITH SERDEPROPERTIES (
            'separatorChar' = ',',
            'quoteChar' = '"',
            'escapeChar' = '\\\\'
        )
        STORED AS TEXTFILE
        LOCATION '{s3_location}'
    """
    qid = run_athena(athena_client, create_q)
    if qid:
        print(f"  Staging table created: {STAGING_TABLE}")
    return qid is not None


def drop_staging_table(athena_client):
    run_athena(athena_client, f'DROP TABLE IF EXISTS "{ATHENA_DATABASE}"."{STAGING_TABLE}"')


def run_enrichment_query(athena_client):
    """Single JOIN query: staging table × TU graph → matched phones."""
    query = f"""
        SELECT
            m.mailer_id,
            p.phone,
            TRY_CAST(p.phone_sequence_number AS INTEGER) AS seq
        FROM "{ATHENA_DATABASE}"."{STAGING_TABLE}" m
        JOIN "{ATHENA_DATABASE}".name n
            ON UPPER(n.first_name) = UPPER(m.first_name)
           AND UPPER(n.last_name) = UPPER(m.last_name)
        JOIN "{ATHENA_DATABASE}".address a
            ON n.extern_tuid = a.extern_tuid
           AND UPPER(a.address) = UPPER(m.address)
        JOIN "{ATHENA_DATABASE}".phone p
            ON n.extern_tuid = p.extern_tuid
        WHERE p.phone_sequence_number <> ''
          AND TRY_CAST(p.phone_sequence_number AS INTEGER) <= 5
        ORDER BY m.mailer_id, TRY_CAST(p.phone_sequence_number AS INTEGER)
    """
    return run_athena(athena_client, query)


def paginate_results(athena_client, query_id):
    """Paginate Athena results into a dict {mailer_id: [phone1..phone5]}."""
    phone_map = {}
    paginator = athena_client.get_paginator("get_query_results")
    first_page = True

    for page in paginator.paginate(QueryExecutionId=query_id):
        rows = page["ResultSet"]["Rows"]
        start = 1 if first_page else 0  # skip header on first page
        first_page = False

        for row in rows[start:]:
            data = [col.get("VarCharValue", "") for col in row["Data"]]
            try:
                mid = int(data[0])
            except (ValueError, IndexError):
                continue
            phone = data[1] if len(data) > 1 else ""
            if not phone:
                continue
            if mid not in phone_map:
                phone_map[mid] = []
            if len(phone_map[mid]) < 5:
                phone_map[mid].append(phone)

    # Pad to 5
    for mid in phone_map:
        phone_map[mid] = (phone_map[mid] + [None] * 5)[:5]

    return phone_map


# ---------------------------------------------------------------------------
# MySQL update
# ---------------------------------------------------------------------------
def update_phones(mysql_conn, phone_map):
    cur = mysql_conn.cursor()
    items = list(phone_map.items())
    for i in range(0, len(items), MYSQL_BATCH):
        batch = items[i:i + MYSQL_BATCH]
        for mailer_id, phones in batch:
            cur.execute("""
                UPDATE mailer_data
                SET phone1=%s, phone2=%s, phone3=%s, phone4=%s, phone5=%s
                WHERE id=%s
            """, (*phones, mailer_id))
        mysql_conn.commit()
        print(f"    Updated {min(i + MYSQL_BATCH, len(items))}/{len(items)}...")
    cur.close()


def mark_attempted(mysql_conn, record_ids):
    """Mark all processed rows so they won't be retried."""
    cur = mysql_conn.cursor()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    for i in range(0, len(record_ids), MYSQL_BATCH):
        batch = record_ids[i:i + MYSQL_BATCH]
        placeholders = ",".join(["%s"] * len(batch))
        cur.execute(f"UPDATE mailer_data SET enrich_attempted_at=%s WHERE id IN ({placeholders})",
                    [now] + batch)
        mysql_conn.commit()
    cur.close()


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    t0 = time.time()
    parser = argparse.ArgumentParser(description="Bulk enrich mailer_data with TU graph phones")
    parser.add_argument("--limit", type=int, default=0,
                        help="Max records (0 = all unenriched)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Preview without updating MySQL")
    args = parser.parse_args()

    print("Step 1: Connecting to RDS MySQL...")
    mysql_conn = get_mysql_connection()
    ensure_column(mysql_conn)

    print("Step 2: Fetching unenriched records...")
    records = fetch_unenriched(mysql_conn, args.limit)
    print(f"  Found {len(records)} unenriched records.")
    if not records:
        print("Nothing to enrich.")
        mysql_conn.close()
        return

    bk = get_boto3_kwargs()
    s3_client = boto3.client("s3", **bk)
    athena_client = boto3.client("athena", **bk)

    print("Step 3: Uploading staging CSV to S3...")
    s3_location = upload_staging_csv(s3_client, records)

    print("Step 4: Creating Athena staging table...")
    if not create_staging_table(athena_client, s3_location):
        print("ERROR: Failed to create staging table.")
        cleanup_staging(s3_client)
        mysql_conn.close()
        return

    print("Step 5: Running enrichment query (single JOIN)...")
    qid = run_enrichment_query(athena_client)
    if not qid:
        print("ERROR: Enrichment query failed.")
        drop_staging_table(athena_client)
        cleanup_staging(s3_client)
        mysql_conn.close()
        return

    print("Step 6: Fetching results...")
    phone_map = paginate_results(athena_client, qid)
    print(f"  Matched phones for {len(phone_map)} / {len(records)} records.")

    all_ids = [r["id"] for r in records]

    if args.dry_run:
        for mid, phones in list(phone_map.items())[:10]:
            print(f"    ID {mid}: {phones}")
        print(f"\n[DRY RUN] No MySQL updates made.")
    else:
        print("Step 7: Updating phone numbers in MySQL...")
        update_phones(mysql_conn, phone_map)
        print(f"  Updated {len(phone_map)} records with phones.")

        print("Step 8: Marking all records as attempted...")
        mark_attempted(mysql_conn, all_ids)
        print(f"  Marked {len(all_ids)} records.")

    print("Cleaning up staging...")
    drop_staging_table(athena_client)
    cleanup_staging(s3_client)

    elapsed = time.time() - t0
    print(f"\nDone in {elapsed:.1f}s ({elapsed/60:.1f}m).")
    print(f"  Records processed: {len(records)}")
    print(f"  Phones matched:    {len(phone_map)}")
    print(f"  Match rate:        {len(phone_map)/len(records)*100:.1f}%")

    mysql_conn.close()


if __name__ == "__main__":
    main()
