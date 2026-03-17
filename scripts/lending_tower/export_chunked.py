"""
Export ALL records from TblMailersUnique in 1M-row chunks.

Outputs multiple CSV files:
    sam_export_chunk_001.csv (rows 1-1,000,000)
    sam_export_chunk_002.csv (rows 1,000,001-2,000,000)
    etc.

Features:
- Exports ALL records (not just enriched)
- 1M records per file
- Resumable (tracks progress in state file)
- Unique filenames with zero-padded chunk numbers
- Same format as original export

Usage:
    python export_chunked.py --output-dir /var/www/cmd-runner/EE/chunks
    python export_chunked.py --output-dir /var/www/cmd-runner/EE/chunks --resume
"""
import argparse
import csv
import json
import os
import sys
import time
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

CHUNK_SIZE = 1_000_000
BATCH_SIZE = 10_000
STATE_FILE = "export_chunked_state.json"


def get_mssql_connection():
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
        login_timeout=30,
        timeout=300,
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


def get_total_count(conn):
    """Get total number of records in table."""
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM dbo.TblMailersUnique")
    total = cursor.fetchone()[0]
    cursor.close()
    return total


def get_pk_range(conn, offset, limit):
    """Get PK range for a specific chunk."""
    cursor = conn.cursor()
    cursor.execute(f"""
        SELECT MIN(PK), MAX(PK)
        FROM (
            SELECT PK
            FROM dbo.TblMailersUnique
            ORDER BY PK
            OFFSET {offset} ROWS
            FETCH NEXT {limit} ROWS ONLY
        ) AS chunk
    """)
    result = cursor.fetchone()
    cursor.close()
    return result if result else (None, None)


def fetch_chunk_by_pk_range(conn, min_pk, max_pk):
    """Fetch all rows within a PK range."""
    cursor = conn.cursor(as_dict=True)
    cursor.execute("""
        SELECT PK, Client, Address, Debt_Amount,
               phone1, phone2, phone3, phone4, phone5,
               sms_send_date
        FROM dbo.TblMailersUnique
        WHERE PK BETWEEN %s AND %s
        ORDER BY PK ASC
    """, (min_pk, max_pk))
    rows = cursor.fetchall()
    cursor.close()
    return rows


def load_state(state_file):
    """Load export state from file."""
    if os.path.exists(state_file):
        with open(state_file, 'r') as f:
            return json.load(f)
    return {"last_chunk": 0, "total_exported": 0}


def save_state(state_file, chunk_num, total_exported):
    """Save export state to file."""
    with open(state_file, 'w') as f:
        json.dump({
            "last_chunk": chunk_num,
            "total_exported": total_exported,
            "timestamp": date.today().isoformat()
        }, f, indent=2)


def main():
    parser = argparse.ArgumentParser(description="Export TblMailersUnique in 1M-row chunks")
    parser.add_argument("--output-dir", type=str, required=True,
                        help="Output directory for chunk files")
    parser.add_argument("--resume", action="store_true",
                        help="Resume from last completed chunk")
    parser.add_argument("--chunk-size", type=int, default=CHUNK_SIZE,
                        help="Records per chunk (default: 1,000,000)")
    args = parser.parse_args()

    output_dir = args.output_dir
    chunk_size = args.chunk_size
    state_file = os.path.join(output_dir, STATE_FILE)

    # Create output directory
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
        print(f"Created directory: {output_dir}")

    # Load state if resuming
    state = load_state(state_file) if args.resume else {"last_chunk": 0, "total_exported": 0}
    start_chunk = state["last_chunk"] + 1 if args.resume else 1
    total_exported = state["total_exported"]

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    print("Counting total records...")
    total_records = get_total_count(conn)
    total_chunks = (total_records + chunk_size - 1) // chunk_size

    print(f"Total records: {total_records:,}")
    print(f"Chunk size: {chunk_size:,}")
    print(f"Total chunks: {total_chunks}")
    if args.resume:
        print(f"Resuming from chunk {start_chunk}")
    print("")

    fieldnames = [
        "First name", "address", "debt load",
        "phone1", "phone2", "phone3", "phone4", "phone5",
        "send date",
    ]

    send_date = date.today().isoformat()
    overall_start = time.time()

    for chunk_num in range(start_chunk, total_chunks + 1):
        chunk_start = time.time()
        offset = (chunk_num - 1) * chunk_size
        
        # Get PK range for this chunk
        print(f"[Chunk {chunk_num}/{total_chunks}] Finding PK range...")
        min_pk, max_pk = get_pk_range(conn, offset, chunk_size)
        
        if min_pk is None:
            print(f"  No more records. Stopping.")
            break
        
        print(f"  PK range: {min_pk:,} to {max_pk:,}")
        
        # Fetch rows
        print(f"  Fetching rows...")
        rows = fetch_chunk_by_pk_range(conn, min_pk, max_pk)
        
        if not rows:
            print(f"  No rows found. Skipping.")
            continue
        
        # Write to CSV
        chunk_filename = f"sam_export_chunk_{chunk_num:03d}.csv"
        chunk_path = os.path.join(output_dir, chunk_filename)
        
        print(f"  Writing {len(rows):,} rows to {chunk_filename}...")
        
        with open(chunk_path, 'w', newline='', encoding='utf-8') as f:
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
        
        total_exported += len(rows)
        chunk_elapsed = time.time() - chunk_start
        overall_elapsed = time.time() - overall_start
        
        # Save state
        save_state(state_file, chunk_num, total_exported)
        
        # Progress report
        pct_complete = (chunk_num / total_chunks) * 100
        avg_time_per_chunk = overall_elapsed / (chunk_num - start_chunk + 1)
        remaining_chunks = total_chunks - chunk_num
        eta_seconds = remaining_chunks * avg_time_per_chunk
        
        print(f"  ✓ Chunk {chunk_num}/{total_chunks} complete ({pct_complete:.1f}%)")
        print(f"    Rows: {len(rows):,} | Time: {chunk_elapsed:.1f}s")
        print(f"    Total exported: {total_exported:,} / {total_records:,}")
        print(f"    ETA: {eta_seconds/3600:.1f}h ({remaining_chunks} chunks remaining)")
        print("")

    conn.close()
    
    overall_elapsed = time.time() - overall_start
    print("=" * 60)
    print(f"Export complete!")
    print(f"  Total time: {overall_elapsed:.1f}s ({overall_elapsed/3600:.1f}h)")
    print(f"  Total exported: {total_exported:,}")
    print(f"  Output directory: {output_dir}")
    print(f"  Files created: {chunk_num - start_chunk + 1}")
    print("=" * 60)


if __name__ == "__main__":
    main()
