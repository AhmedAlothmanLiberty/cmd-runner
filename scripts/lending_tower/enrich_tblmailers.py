"""
Phase 3: Enrich TblMailersUnique with phone numbers from TU Identity Graph.

Works directly against SQL Server. Reads unenriched rows in chunks,
stages them in S3, runs Athena JOIN against TU phone/name/address tables,
and writes matched phones back to TblMailersUnique.

Processes in configurable chunks (default 500k) so memory stays bounded.

Usage:
    python enrich_tblmailers.py [--chunk-size 500000] [--limit 0] [--dry-run]

Options:
    --chunk-size  Rows per Athena batch (default: 500000)
    --limit       Max total rows to process (0 = all unenriched)
    --dry-run     Preview without updating SQL Server
    --newest      Process newest mailer_date records first
"""
import argparse
import csv
import io
import os
import time
import sys
from datetime import datetime

import boto3
import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
    AWS_REGION, ATHENA_DATABASE, ATHENA_RESULTS_BUCKET,
    AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN,
)

STAGING_S3_PREFIX = "staging/enrich_tblmailers"
STAGING_TABLE = "tmp_tblmailers_lookup"
ATHENA_POLL_START = 1.0
ATHENA_POLL_MAX = 8.0
MSSQL_BATCH = 1000
SOURCE_BATCH = 50000


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
# SQL Server helpers
# ---------------------------------------------------------------------------
def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
    )


def count_unenriched(mssql_conn):
    cursor = mssql_conn.cursor()
    cursor.execute("""
        SELECT COUNT(*)
        FROM dbo.TblMailersUnique
        WHERE phone1 IS NULL
    """)
    total = cursor.fetchone()[0]
    cursor.close()
    return total


def fetch_unenriched_chunk(mssql_conn, last_pk, chunk_size, newest_first=False):
    """Fetch a chunk of unenriched rows by PK range."""
    order = "DESC" if newest_first else "ASC"
    op = "<" if newest_first else ">"
    cursor = mssql_conn.cursor(as_dict=True)
    cursor.execute(f"""
        SELECT TOP {int(chunk_size)}
            PK, Client, Address, City, State, Zip
        FROM dbo.TblMailersUnique
        WHERE phone1 IS NULL
          AND PK {op} %s
        ORDER BY PK {order}
    """, (last_pk,))
    rows = cursor.fetchall()
    cursor.close()
    return rows


def parse_name(client_str):
    parts = client_str.strip().upper().split() if client_str else []
    if len(parts) >= 2:
        return parts[0], parts[-1]
    elif len(parts) == 1:
        return parts[0], ""
    return "", ""


# ---------------------------------------------------------------------------
# S3 staging
# ---------------------------------------------------------------------------
def results_bucket_name():
    return ATHENA_RESULTS_BUCKET.replace("s3://", "").strip("/").split("/")[0]


def upload_staging_csv(s3_client, records):
    """Build CSV in memory and upload to S3. Returns S3 URI of folder."""
    buf = io.StringIO()
    writer = csv.writer(buf)
    skipped = 0
    written = 0
    for rec in records:
        first_name, last_name = parse_name(rec["Client"])
        address = rec["Address"].strip().upper() if rec["Address"] else ""
        if not first_name or not address:
            skipped += 1
            continue
        writer.writerow([rec["PK"], first_name, last_name, address])
        written += 1

    bucket = results_bucket_name()
    key = f"{STAGING_S3_PREFIX}/lookup.csv"
    s3_client.put_object(Bucket=bucket, Key=key, Body=buf.getvalue().encode("utf-8"))
    print(f"  Uploaded {written} rows to s3://{bucket}/{key} (skipped {skipped})")
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
    drop_q = f'DROP TABLE IF EXISTS `{ATHENA_DATABASE}`.`{STAGING_TABLE}`'
    run_athena(athena_client, drop_q)

    create_q = f"""
        CREATE EXTERNAL TABLE `{ATHENA_DATABASE}`.`{STAGING_TABLE}` (
            mailer_pk INT,
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
    run_athena(athena_client, f'DROP TABLE IF EXISTS `{ATHENA_DATABASE}`.`{STAGING_TABLE}`')


def run_enrichment_query(athena_client):
    query = f"""
        SELECT
            m.mailer_pk,
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
        ORDER BY m.mailer_pk, TRY_CAST(p.phone_sequence_number AS INTEGER)
    """
    return run_athena(athena_client, query)


def paginate_results(athena_client, query_id):
    phone_map = {}
    paginator = athena_client.get_paginator("get_query_results")
    first_page = True

    for page in paginator.paginate(QueryExecutionId=query_id):
        rows = page["ResultSet"]["Rows"]
        start = 1 if first_page else 0
        first_page = False

        for row in rows[start:]:
            data = [col.get("VarCharValue", "") for col in row["Data"]]
            try:
                pk = int(data[0])
            except (ValueError, IndexError):
                continue
            phone = data[1] if len(data) > 1 else ""
            if not phone:
                continue
            if pk not in phone_map:
                phone_map[pk] = []
            if phone in phone_map[pk]:
                continue
            if len(phone_map[pk]) < 5:
                phone_map[pk].append(phone)

    for pk in phone_map:
        phone_map[pk] = (phone_map[pk] + [None] * 5)[:5]

    return phone_map


# ---------------------------------------------------------------------------
# SQL Server update
# ---------------------------------------------------------------------------
def update_phones_mssql(mssql_conn, phone_map, dry_run):
    if dry_run:
        for pk, phones in list(phone_map.items())[:10]:
            print(f"    PK {pk}: {phones}")
        print(f"  [DRY RUN] Would update {len(phone_map)} rows.")
        return

    cursor = mssql_conn.cursor()
    items = list(phone_map.items())
    for i in range(0, len(items), MSSQL_BATCH):
        batch = items[i:i + MSSQL_BATCH]
        for pk, phones in batch:
            cursor.execute("""
                UPDATE dbo.TblMailersUnique
                SET phone1=%s, phone2=%s, phone3=%s, phone4=%s, phone5=%s
                WHERE PK=%s
            """, (phones[0], phones[1], phones[2], phones[3], phones[4], pk))
        mssql_conn.commit()
        done = min(i + MSSQL_BATCH, len(items))
        print(f"    Updated {done}/{len(items)}...")
    cursor.close()


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    t0 = time.time()
    parser = argparse.ArgumentParser(description="Enrich TblMailersUnique with TU graph phones via Athena")
    parser.add_argument("--chunk-size", type=int, default=500000,
                        help="Rows per Athena batch (default: 500000)")
    parser.add_argument("--limit", type=int, default=0,
                        help="Max total rows (0 = all unenriched)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Preview without updating SQL Server")
    parser.add_argument("--newest", action="store_true",
                        help="Process newest records first (by PK descending)")
    args = parser.parse_args()

    print("Step 1: Connecting to SQL Server...")
    mssql_conn = get_mssql_connection()

    total_unenriched = count_unenriched(mssql_conn)
    target = min(total_unenriched, args.limit) if args.limit > 0 else total_unenriched
    print(f"  Unenriched rows: {total_unenriched}")
    print(f"  Target: {target}")

    if target == 0:
        print("Nothing to enrich.")
        mssql_conn.close()
        return

    bk = get_boto3_kwargs()
    s3_client = boto3.client("s3", **bk)
    athena_client = boto3.client("athena", **bk)

    total_processed = 0
    total_matched = 0
    last_pk = 0 if not args.newest else 999999999
    chunk_num = 0

    while total_processed < target:
        chunk_num += 1
        remaining = target - total_processed
        this_chunk = min(args.chunk_size, remaining)

        print(f"\n--- Chunk {chunk_num}: fetching up to {this_chunk} rows (last_pk={last_pk}) ---")
        records = fetch_unenriched_chunk(mssql_conn, last_pk, this_chunk, newest_first=args.newest)
        if not records:
            print("  No more unenriched rows.")
            break

        total_processed += len(records)
        if args.newest:
            last_pk = min(r["PK"] for r in records)
        else:
            last_pk = max(r["PK"] for r in records)

        print(f"  Fetched {len(records)} rows. Uploading staging CSV...")
        s3_location = upload_staging_csv(s3_client, records)

        print("  Creating Athena staging table...")
        if not create_staging_table(athena_client, s3_location):
            print("  ERROR: Failed to create staging table. Skipping chunk.")
            cleanup_staging(s3_client)
            continue

        print("  Running enrichment query...")
        qid = run_enrichment_query(athena_client)
        if not qid:
            print("  ERROR: Enrichment query failed. Skipping chunk.")
            drop_staging_table(athena_client)
            cleanup_staging(s3_client)
            continue

        print("  Fetching Athena results...")
        phone_map = paginate_results(athena_client, qid)
        total_matched += len(phone_map)
        print(f"  Matched {len(phone_map)}/{len(records)} rows in this chunk.")

        print("  Updating SQL Server...")
        update_phones_mssql(mssql_conn, phone_map, args.dry_run)

        print("  Cleaning up staging...")
        drop_staging_table(athena_client)
        cleanup_staging(s3_client)

        print(f"  Progress: {total_processed}/{target} processed, {total_matched} matched total.")

    elapsed = time.time() - t0
    print(f"\nDone in {elapsed:.1f}s ({elapsed/60:.1f}m).")
    print(f"  Total processed: {total_processed}")
    print(f"  Total matched:   {total_matched}")
    if total_processed > 0:
        print(f"  Match rate:      {total_matched/total_processed*100:.1f}%")

    mssql_conn.close()


if __name__ == "__main__":
    main()
