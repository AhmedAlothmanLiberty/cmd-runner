"""
Enrich mailer_data records with phone numbers from the TU Identity Graph.

Queries Athena to match mailer records (by name + address) against the TU graph
name + address + phone tables, then updates the RDS MySQL mailer_data table.

Usage:
    python enrich_phones.py [--limit 1000] [--dry-run]

Options:
    --limit   Max number of records to enrich per run (default: 1000)
    --dry-run Preview matches without updating
"""
import argparse
import time
import sys

import boto3
import pymysql

from config import (
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE,
    AWS_REGION, ATHENA_DATABASE, ATHENA_RESULTS_BUCKET,
    AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN,
)

ATHENA_BATCH_SIZE = 200  # records per Athena query (was 20)
ATHENA_POLL_START = 0.5
ATHENA_POLL_MAX = 3.0
MYSQL_UPDATE_BATCH = 500


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


def get_records_without_phones(mysql_conn, limit):
    """Get mailer records that have no phone1 yet."""
    cursor = mysql_conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("""
        SELECT id, client, address, city, state, zip
        FROM mailer_data
        WHERE (phone1 IS NULL OR phone1 = '')
        LIMIT %s
    """, (limit,))
    rows = cursor.fetchall()
    cursor.close()
    return rows


def parse_name(client_str):
    """Parse 'FIRST MIDDLE LAST' from Client field. Returns (first_name, last_name)."""
    parts = client_str.strip().upper().split()
    if len(parts) >= 2:
        return parts[0], parts[-1]
    elif len(parts) == 1:
        return parts[0], ""
    return "", ""


def submit_athena_query(athena_client, query):
    """Submit an Athena query, return query_id immediately."""
    response = athena_client.start_query_execution(
        QueryString=query,
        QueryExecutionContext={"Database": ATHENA_DATABASE},
        ResultConfiguration={"OutputLocation": ATHENA_RESULTS_BUCKET},
    )
    return response["QueryExecutionId"]


def wait_for_queries(athena_client, query_ids):
    """Wait for all submitted queries to finish. Returns dict {query_id: state}."""
    pending = set(query_ids)
    states = {}
    poll_interval = ATHENA_POLL_START

    while pending:
        for qid in list(pending):
            result = athena_client.get_query_execution(QueryExecutionId=qid)
            state = result["QueryExecution"]["Status"]["State"]
            if state in ("SUCCEEDED", "FAILED", "CANCELLED"):
                states[qid] = state
                pending.discard(qid)
                if state != "SUCCEEDED":
                    reason = result["QueryExecution"]["Status"].get("StateChangeReason", "Unknown")
                    print(f"  Query {qid[:8]}... failed: {reason}")
        if pending:
            time.sleep(poll_interval)
            poll_interval = min(poll_interval * 1.5, ATHENA_POLL_MAX)

    return states


def get_query_results_all(athena_client, query_id):
    """Fetch all result pages for a completed Athena query."""
    rows = []
    paginator = athena_client.get_paginator("get_query_results")
    for page in paginator.paginate(QueryExecutionId=query_id):
        page_rows = page["ResultSet"]["Rows"]
        if not rows:
            rows.extend(page_rows)  # includes header
        else:
            rows.extend(page_rows)
    return rows


def lookup_phones_batch(athena_client, records):
    """
    Look up phone numbers for a batch of records via Athena.
    Returns dict of {mailer_id: [phone1, phone2, ..., phone5]}
    """
    if not records:
        return {}

    # Build conditions and id_map
    conditions = []
    id_map = {}  # map (first_name|address) -> mailer_id

    for rec in records:
        first_name, last_name = parse_name(rec["client"])
        address = rec["address"].strip().upper() if rec["address"] else ""

        if not first_name or not address:
            continue

        first_name_esc = first_name.replace("'", "''")
        last_name_esc = last_name.replace("'", "''")
        address_esc = address.replace("'", "''")

        key = f"{first_name}|{address}"
        id_map[key] = rec["id"]

        conditions.append(
            f"(UPPER(n.first_name) = '{first_name_esc}' "
            f"AND UPPER(n.last_name) = '{last_name_esc}' "
            f"AND UPPER(a.address) = '{address_esc}')"
        )

    if not conditions:
        return {}

    # Submit all Athena queries concurrently in batches of ATHENA_BATCH_SIZE
    query_ids = []
    num_batches = (len(conditions) + ATHENA_BATCH_SIZE - 1) // ATHENA_BATCH_SIZE
    print(f"  Submitting {num_batches} Athena queries ({len(conditions)} lookups)...")

    for i in range(0, len(conditions), ATHENA_BATCH_SIZE):
        batch_conditions = conditions[i:i + ATHENA_BATCH_SIZE]
        where_clause = " OR ".join(batch_conditions)
        query = f"""
            SELECT
                UPPER(n.first_name) as first_name,
                UPPER(a.address) as address,
                p.phone,
                p.phone_sequence_number
            FROM "{ATHENA_DATABASE}".name n
            JOIN "{ATHENA_DATABASE}".address a ON n.extern_tuid = a.extern_tuid
            JOIN "{ATHENA_DATABASE}".phone p ON n.extern_tuid = p.extern_tuid
            WHERE ({where_clause})
              AND p.phone_sequence_number <> ''
              AND TRY_CAST(p.phone_sequence_number AS INTEGER) <= 5
            ORDER BY n.first_name, a.address, TRY_CAST(p.phone_sequence_number AS INTEGER)
        """
        qid = submit_athena_query(athena_client, query)
        query_ids.append(qid)

    # Wait for all queries to finish concurrently
    print(f"  Waiting for {len(query_ids)} queries to complete...")
    states = wait_for_queries(athena_client, query_ids)

    # Collect results from all succeeded queries
    results_map = {}
    for qid in query_ids:
        if states.get(qid) != "SUCCEEDED":
            continue

        rows = get_query_results_all(athena_client, qid)
        if len(rows) <= 1:
            continue

        phone_groups = {}
        for row in rows[1:]:
            data = [col.get("VarCharValue", "") for col in row["Data"]]
            fname, addr, phone, seq = data[0], data[1], data[2], data[3]
            key = f"{fname}|{addr}"
            if key not in phone_groups:
                phone_groups[key] = []
            if phone and len(phone_groups[key]) < 5:
                phone_groups[key].append(phone)

        for key, phones in phone_groups.items():
            if key in id_map:
                padded = (phones + [None] * 5)[:5]
                results_map[id_map[key]] = padded

    return results_map


def update_phones(mysql_conn, phone_map):
    """Update phone columns in mailer_data using batch updates."""
    cursor = mysql_conn.cursor()
    items = list(phone_map.items())
    for i in range(0, len(items), MYSQL_UPDATE_BATCH):
        batch = items[i:i + MYSQL_UPDATE_BATCH]
        for mailer_id, phones in batch:
            cursor.execute("""
                UPDATE mailer_data
                SET phone1 = %s, phone2 = %s, phone3 = %s, phone4 = %s, phone5 = %s
                WHERE id = %s
            """, (*phones, mailer_id))
        mysql_conn.commit()
    cursor.close()


def main():
    parser = argparse.ArgumentParser(description="Enrich mailer_data with TU graph phone numbers")
    parser.add_argument("--limit", type=int, default=1000,
                        help="Max records to enrich per run (default: 1000)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Preview matches without updating")
    args = parser.parse_args()

    print(f"Connecting to RDS MySQL...")
    mysql_conn = get_mysql_connection()

    print(f"Fetching up to {args.limit} records without phone numbers...")
    records = get_records_without_phones(mysql_conn, args.limit)
    print(f"  Found {len(records)} records to enrich.")

    if not records:
        print("Nothing to enrich.")
        mysql_conn.close()
        return

    print("Querying TU Identity Graph via Athena...")
    boto3_kwargs = {"region_name": AWS_REGION}
    if AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY:
        boto3_kwargs["aws_access_key_id"] = AWS_ACCESS_KEY_ID
        boto3_kwargs["aws_secret_access_key"] = AWS_SECRET_ACCESS_KEY
        if AWS_SESSION_TOKEN:
            boto3_kwargs["aws_session_token"] = AWS_SESSION_TOKEN
    athena_client = boto3.client("athena", **boto3_kwargs)
    phone_map = lookup_phones_batch(athena_client, records)
    print(f"  Matched phones for {len(phone_map)} records.")

    if args.dry_run:
        for mid, phones in list(phone_map.items())[:10]:
            print(f"    ID {mid}: {phones}")
        print(f"\n[DRY RUN] No updates made.")
    else:
        print("Updating phone numbers in mailer_data...")
        update_phones(mysql_conn, phone_map)
        print(f"  Updated {len(phone_map)} records.")

    mysql_conn.close()
    print("Done.")


if __name__ == "__main__":
    main()
