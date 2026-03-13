"""
Phase 3: Enrich TblMailersUnique with phone numbers from TU Identity Graph.

Three-pass matching strategy:
  Pass 1: exact first_name + last_name + exact address
  Pass 2: exact first_name + last_name + normalized address
  Pass 3: first_name + last_name + zip (no address)

Works directly against SQL Server. Reads unenriched rows in chunks,
stages them in S3, runs Athena JOINs, writes phones back to SQL Server.

Usage:
    python enrich_tblmailers.py [--chunk-size 500000] [--limit 0] [--dry-run]
    python enrich_tblmailers.py --limit 5000000 --newest
"""
import argparse
import csv
import io
import re
import time

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
MSSQL_BATCH = 900
INSERT_ROWS_PER_STMT = 900

# Address normalization map
ADDR_ABBREV = {
    "STREET": "ST", "AVENUE": "AVE", "DRIVE": "DR", "ROAD": "RD",
    "BOULEVARD": "BLVD", "LANE": "LN", "COURT": "CT", "PLACE": "PL",
    "CIRCLE": "CIR", "TRAIL": "TRL", "PARKWAY": "PKWY", "HIGHWAY": "HWY",
    "TERRACE": "TER", "WAY": "WAY", "NORTH": "N", "SOUTH": "S",
    "EAST": "E", "WEST": "W", "NORTHEAST": "NE", "NORTHWEST": "NW",
    "SOUTHEAST": "SE", "SOUTHWEST": "SW",
}
ADDR_STRIP = re.compile(r'\b(APT|UNIT|SUITE|STE|BLDG|BUILDING|FLOOR|FL|RM|ROOM|#)\b.*', re.IGNORECASE)


def normalize_address(addr):
    """Normalize an address string for fuzzy matching."""
    if not addr:
        return ""
    s = addr.strip().upper()
    s = ADDR_STRIP.sub("", s).strip()
    s = re.sub(r'[^A-Z0-9 ]', ' ', s)
    s = re.sub(r'\s+', ' ', s).strip()
    parts = s.split()
    normalized = []
    for p in parts:
        normalized.append(ADDR_ABBREV.get(p, p))
    return " ".join(normalized)


# ---------------------------------------------------------------------------
# AWS
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
# SQL Server
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
    cursor.execute("SELECT COUNT(*) FROM dbo.TblMailersUnique WHERE phone1 IS NULL")
    total = cursor.fetchone()[0]
    cursor.close()
    return total


def fetch_unenriched_chunk(mssql_conn, chunk_size, newest_first=False,
                          last_drop=None, last_pk=None):
    """Fetch unenriched rows. newest_first orders by Drop_Name DESC."""
    cursor = mssql_conn.cursor(as_dict=True)
    if newest_first:
        if last_drop is not None and last_pk is not None:
            cursor.execute(f"""
                SELECT TOP {int(chunk_size)}
                    PK, Drop_Name, Client, Address, City, State, Zip
                FROM dbo.TblMailersUnique
                WHERE phone1 IS NULL
                  AND (Drop_Name < %s OR (Drop_Name = %s AND PK < %s))
                ORDER BY Drop_Name DESC, PK DESC
            """, (last_drop, last_drop, last_pk))
        else:
            cursor.execute(f"""
                SELECT TOP {int(chunk_size)}
                    PK, Drop_Name, Client, Address, City, State, Zip
                FROM dbo.TblMailersUnique
                WHERE phone1 IS NULL
                ORDER BY Drop_Name DESC, PK DESC
            """)
    else:
        if last_pk is not None:
            cursor.execute(f"""
                SELECT TOP {int(chunk_size)}
                    PK, Drop_Name, Client, Address, City, State, Zip
                FROM dbo.TblMailersUnique
                WHERE phone1 IS NULL
                  AND PK > %s
                ORDER BY PK ASC
            """, (last_pk,))
        else:
            cursor.execute(f"""
                SELECT TOP {int(chunk_size)}
                    PK, Drop_Name, Client, Address, City, State, Zip
                FROM dbo.TblMailersUnique
                WHERE phone1 IS NULL
                ORDER BY PK ASC
            """)
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
# S3 staging — now includes zip and normalized address
# ---------------------------------------------------------------------------
def results_bucket_name():
    return ATHENA_RESULTS_BUCKET.replace("s3://", "").strip("/").split("/")[0]


def upload_staging_csv(s3_client, records):
    """Upload CSV: pk, first_name, last_name, address, norm_address, zip"""
    buf = io.StringIO()
    writer = csv.writer(buf)
    skipped = 0
    written = 0
    for rec in records:
        first_name, last_name = parse_name(rec["Client"])
        address = rec["Address"].strip().upper() if rec["Address"] else ""
        zipcode = (rec["Zip"] or "").strip()
        if not first_name:
            skipped += 1
            continue
        norm_addr = normalize_address(address)
        writer.writerow([rec["PK"], first_name, last_name, address, norm_addr, zipcode])
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
# Athena
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
            address STRING,
            norm_address STRING,
            zip STRING
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


def build_enrichment_query(pass_num):
    """Build Athena query for a given matching pass."""
    db = ATHENA_DATABASE
    stg = STAGING_TABLE

    phone_select = f"""
        SELECT
            m.mailer_pk,
            p.phone,
            TRY_CAST(p.phone_sequence_number AS INTEGER) AS seq
    """
    phone_join = f"""
        JOIN "{db}".phone p
            ON n.extern_tuid = p.extern_tuid
        WHERE p.phone_sequence_number <> ''
          AND TRY_CAST(p.phone_sequence_number AS INTEGER) <= 5
        ORDER BY m.mailer_pk, TRY_CAST(p.phone_sequence_number AS INTEGER)
    """

    if pass_num == 1:
        # Pass 1: exact name + exact address
        return phone_select + f"""
        FROM "{db}"."{stg}" m
        JOIN "{db}".name n
            ON UPPER(n.first_name) = UPPER(m.first_name)
           AND UPPER(n.last_name) = UPPER(m.last_name)
        JOIN "{db}".address a
            ON n.extern_tuid = a.extern_tuid
           AND UPPER(a.address) = UPPER(m.address)
        """ + phone_join

    elif pass_num == 2:
        # Pass 2: exact name + normalized address
        return phone_select + f"""
        FROM "{db}"."{stg}" m
        JOIN "{db}".name n
            ON UPPER(n.first_name) = UPPER(m.first_name)
           AND UPPER(n.last_name) = UPPER(m.last_name)
        JOIN "{db}".address a
            ON n.extern_tuid = a.extern_tuid
           AND UPPER(
                REGEXP_REPLACE(
                    REGEXP_REPLACE(
                        REGEXP_REPLACE(UPPER(a.address),
                            '\\b(APT|UNIT|SUITE|STE|BLDG|BUILDING|FLOOR|FL|RM|ROOM|#)\\b.*', ''),
                        '[^A-Z0-9 ]', ' '),
                    '\\s+', ' ')
              ) = UPPER(m.norm_address)
        """ + phone_join

    elif pass_num == 3:
        # Pass 3: name + zip (no address)
        return phone_select + f"""
        FROM "{db}"."{stg}" m
        JOIN "{db}".name n
            ON UPPER(n.first_name) = UPPER(m.first_name)
           AND UPPER(n.last_name) = UPPER(m.last_name)
        JOIN "{db}".address a
            ON n.extern_tuid = a.extern_tuid
           AND a.zip = m.zip
        """ + phone_join

    raise ValueError(f"Unknown pass: {pass_num}")


def paginate_results(athena_client, query_id, exclude_pks=None):
    """Collect phone results, skipping PKs already matched."""
    phone_map = {}
    exclude = exclude_pks or set()
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
            if pk in exclude:
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
# SQL Server update — fast multi-row
# ---------------------------------------------------------------------------
def esc(val):
    if val is None:
        return "NULL"
    return "'" + str(val).replace("'", "''") + "'"


def recreate_update_stage_table(cursor):
    table_name = f"##enrich_phone_update_{int(time.time())}"
    cursor.execute(f"IF OBJECT_ID('tempdb..{table_name}') IS NOT NULL DROP TABLE {table_name}")
    cursor.execute(f"""
        CREATE TABLE {table_name} (
            PK INT NOT NULL,
            phone1 VARCHAR(32) NULL,
            phone2 VARCHAR(32) NULL,
            phone3 VARCHAR(32) NULL,
            phone4 VARCHAR(32) NULL,
            phone5 VARCHAR(32) NULL
        )
    """)
    return table_name


def stage_phone_updates(cursor, mssql_conn, table_name, items):
    staged = 0
    t0 = time.time()
    for i in range(0, len(items), INSERT_ROWS_PER_STMT):
        batch = items[i:i + INSERT_ROWS_PER_STMT]
        values_parts = []
        for pk, phones in batch:
            values_parts.append(
                f"({int(pk)},{esc(phones[0])},{esc(phones[1])},{esc(phones[2])},{esc(phones[3])},{esc(phones[4])})"
            )
        sql = (
            f"INSERT INTO {table_name} (PK, phone1, phone2, phone3, phone4, phone5) VALUES "
            + ",".join(values_parts)
        )
        cursor.execute(sql)
        staged += len(batch)
        if staged % 50000 < INSERT_ROWS_PER_STMT:
            mssql_conn.commit()
            elapsed = time.time() - t0
            rate = staged / elapsed if elapsed > 0 else 0
            print(f"    Staged {staged}/{len(items)} ({rate:.0f} rows/s)")
    mssql_conn.commit()


def run_phone_update_join(cursor, table_name):
    cursor.execute(f"""
        UPDATE t
        SET t.phone1 = s.phone1,
            t.phone2 = s.phone2,
            t.phone3 = s.phone3,
            t.phone4 = s.phone4,
            t.phone5 = s.phone5
        FROM dbo.TblMailersUnique t
        JOIN {table_name} s
          ON t.PK = s.PK
        WHERE t.phone1 IS NULL
    """)
    return cursor.rowcount


def update_phones_mssql(mssql_conn, phone_map, dry_run):
    if dry_run:
        for pk, phones in list(phone_map.items())[:10]:
            print(f"    PK {pk}: {phones}")
        print(f"  [DRY RUN] Would update {len(phone_map)} rows.")
        return

    if not phone_map:
        return

    cursor = mssql_conn.cursor()
    items = list(phone_map.items())
    t0 = time.time()
    table_name = recreate_update_stage_table(cursor)
    mssql_conn.commit()
    print(f"    Update stage table: {table_name}")
    stage_phone_updates(cursor, mssql_conn, table_name, items)
    elapsed = time.time() - t0
    print(f"    Running UPDATE JOIN for {len(items):,} rows...")
    updated = run_phone_update_join(cursor, table_name)
    mssql_conn.commit()
    total_elapsed = time.time() - t0
    rate = updated / total_elapsed if total_elapsed > 0 else 0
    print(f"    Updated {updated}/{len(items)} ({rate:.0f} rows/s)")
    cursor.execute(f"DROP TABLE {table_name}")
    mssql_conn.commit()
    cursor.close()


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    t0 = time.time()
    parser = argparse.ArgumentParser(description="Enrich TblMailersUnique via Athena (3-pass matching)")
    parser.add_argument("--chunk-size", type=int, default=500000,
                        help="Rows per Athena batch (default: 500000)")
    parser.add_argument("--limit", type=int, default=0,
                        help="Max total rows (0 = all unenriched)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Preview without updating SQL Server")
    parser.add_argument("--newest", action="store_true",
                        help="Process newest records first (by Drop_Name descending)")
    args = parser.parse_args()

    print("Connecting to SQL Server...")
    mssql_conn = get_mssql_connection()

    total_unenriched = count_unenriched(mssql_conn)
    target = min(total_unenriched, args.limit) if args.limit > 0 else total_unenriched
    print(f"  Unenriched rows: {total_unenriched:,}")
    print(f"  Target: {target:,}")

    if target == 0:
        print("Nothing to enrich.")
        mssql_conn.close()
        return

    bk = get_boto3_kwargs()
    s3_client = boto3.client("s3", **bk)
    athena_client = boto3.client("athena", **bk)

    total_processed = 0
    total_matched = 0
    last_drop = None
    last_pk = None
    chunk_num = 0

    while total_processed < target:
        chunk_num += 1
        remaining = target - total_processed
        this_chunk = min(args.chunk_size, remaining)

        print(f"\n{'='*60}")
        print(f"Chunk {chunk_num}: fetching up to {this_chunk:,} rows (last_drop={last_drop}, last_pk={last_pk})")
        print(f"{'='*60}")
        records = fetch_unenriched_chunk(mssql_conn, this_chunk,
                                         newest_first=args.newest,
                                         last_drop=last_drop, last_pk=last_pk)
        if not records:
            print("  No more unenriched rows.")
            break

        total_processed += len(records)
        if args.newest:
            last_drop = records[-1]["Drop_Name"]
            last_pk = records[-1]["PK"]
        else:
            last_pk = max(r["PK"] for r in records)

        print(f"  Drop range: {records[0]['Drop_Name']} -> {records[-1]['Drop_Name']}")

        print(f"  Fetched {len(records):,} rows. Uploading staging CSV...")
        s3_location = upload_staging_csv(s3_client, records)

        print("  Creating Athena staging table...")
        if not create_staging_table(athena_client, s3_location):
            print("  ERROR: Failed to create staging table. Skipping chunk.")
            cleanup_staging(s3_client)
            continue

        # Three-pass matching
        all_matched_pks = set()
        chunk_phone_map = {}

        for pass_num in range(1, 4):
            pass_labels = {1: "exact address", 2: "normalized address", 3: "name + zip"}
            label = pass_labels[pass_num]
            print(f"\n  --- Pass {pass_num}: {label} ---")

            query = build_enrichment_query(pass_num)
            qid = run_athena(athena_client, query)
            if not qid:
                print(f"  Pass {pass_num} query failed, continuing to next pass...")
                continue

            phone_map = paginate_results(athena_client, qid, exclude_pks=all_matched_pks)
            print(f"  Pass {pass_num} matched: {len(phone_map):,} new rows")

            chunk_phone_map.update(phone_map)
            all_matched_pks.update(phone_map.keys())

        total_matched += len(chunk_phone_map)
        print(f"\n  Total matched this chunk: {len(chunk_phone_map):,}/{len(records):,}")

        if chunk_phone_map:
            print("  Updating SQL Server...")
            update_phones_mssql(mssql_conn, chunk_phone_map, args.dry_run)

        print("  Cleaning up staging...")
        drop_staging_table(athena_client)
        cleanup_staging(s3_client)

        print(f"  Progress: {total_processed:,}/{target:,} processed, {total_matched:,} matched total")
        if total_processed > 0:
            print(f"  Running match rate: {total_matched/total_processed*100:.1f}%")

    elapsed = time.time() - t0
    print(f"\n{'='*60}")
    print(f"Done in {elapsed:.1f}s ({elapsed/60:.1f}m).")
    print(f"  Total processed: {total_processed:,}")
    print(f"  Total matched:   {total_matched:,}")
    if total_processed > 0:
        print(f"  Match rate:      {total_matched/total_processed*100:.1f}%")
    print(f"{'='*60}")

    mssql_conn.close()


if __name__ == "__main__":
    main()
