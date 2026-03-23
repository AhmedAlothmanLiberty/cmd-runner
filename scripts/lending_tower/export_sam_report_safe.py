"""
Production-safe export of enriched Lending Tower records for Sam.

Outputs CSV in Sam's exact format:
    First name, address, debt load, phone1, phone2, phone3, phone4, phone5, send date

One row per person. All phones in columns phone1-phone5.
Only exports rows with debt_amount > 0 and at least one phone.
Splits output into 1M-row files automatically.
Safe PK-based checkpointing for reliable resume.

Usage:
    python export_sam_report_safe.py --unsent-only --update-send-date --output-dir /var/www/cmd-runner/EE
    python export_sam_report_safe.py --unsent-only --update-send-date --output-dir /var/www/cmd-runner/EE --resume
"""
import argparse
import csv
import os
import time
from datetime import date

import pymssql

from config import (
    MSSQL_HOST, MSSQL_PORT, MSSQL_USER, MSSQL_PASSWORD, MSSQL_DATABASE,
)

# Recommended batch size for memory efficiency and performance
BATCH_SIZE = 10000
ROWS_PER_FILE = 1_000_000

FIELDNAMES = ["First name", "address", "debt load", "phone1", "phone2", "phone3", "phone4", "phone5", "send date"]


def get_mssql_connection():
    """Establish connection to SQL Server with reasonable timeouts."""
    return pymssql.connect(
        server=MSSQL_HOST,
        port=MSSQL_PORT,
        user=MSSQL_USER,
        password=MSSQL_PASSWORD,
        database=MSSQL_DATABASE,
        login_timeout=60,
    )


def parse_first_name(client_str):
    """Extract first name from full client name, uppercase."""
    parts = client_str.strip().upper().split() if client_str else []
    return parts[0] if parts else ""


def format_debt_load(value):
    """Format debt amount as integer if whole, otherwise with 2 decimals."""
    if value is None:
        return ""
    try:
        numeric = float(value)
    except (TypeError, ValueError):
        return str(value)
    if numeric.is_integer():
        return str(int(numeric))
    return f"{numeric:.2f}".rstrip("0").rstrip(".")


def fetch_distinct_drops(mssql_conn, unsent_only, resume_from_drop=None):
    """
    Get distinct Drop_Name values ordered DESC for processing.
    
    Args:
        mssql_conn: Database connection
        unsent_only: If True, only get drops with unsent records
        resume_from_drop: If provided, get drops before this one (for resume)
    
    Returns:
        List of Drop_Name values
    """
    cursor = mssql_conn.cursor()
    # Filter for records with at least one phone and positive debt
    where = "WHERE (phone1 IS NOT NULL OR phone2 IS NOT NULL OR phone3 IS NOT NULL OR phone4 IS NOT NULL OR phone5 IS NOT NULL) AND Debt_Amount > 0"
    if unsent_only:
        where += " AND sms_send_date IS NULL"
    if resume_from_drop:
        cursor.execute(
            f"SELECT DISTINCT Drop_Name FROM dbo.TblMailersUnique "
            f"{where} AND Drop_Name < %s ORDER BY Drop_Name DESC",
            (resume_from_drop,)
        )
    else:
        cursor.execute(
            f"SELECT DISTINCT Drop_Name FROM dbo.TblMailersUnique "
            f"{where} ORDER BY Drop_Name DESC"
        )
    drops = [row[0] for row in cursor.fetchall()]
    cursor.close()
    return drops


def fetch_rows_batch(mssql_conn, drop_name, last_pk, unsent_only, batch_size=10000):
    """
    Fetch a batch of rows for a specific drop, starting from last_pk.
    
    This uses cursor-based pagination for safe, memory-efficient fetching.
    
    Args:
        mssql_conn: Database connection
        drop_name: The Drop_Name to fetch
        last_pk: Last PK from previous batch (0 for first batch)
        unsent_only: If True, only fetch unsent records
        batch_size: Number of rows to fetch
    
    Returns:
        List of row dictionaries or empty list if no more rows
    """
    cursor = mssql_conn.cursor(as_dict=True)
    where = "WHERE (phone1 IS NOT NULL OR phone2 IS NOT NULL OR phone3 IS NOT NULL OR phone4 IS NOT NULL OR phone5 IS NOT NULL) AND Drop_Name = %s AND Debt_Amount > 0 AND PK > %s"
    if unsent_only:
        where += " AND sms_send_date IS NULL"
    cursor.execute(
        f"SELECT TOP {batch_size} PK, Client, Address, Debt_Amount, phone1, phone2, phone3, phone4, phone5 "
        f"FROM dbo.TblMailersUnique {where} ORDER BY PK ASC",
        (drop_name, last_pk)
    )
    rows = cursor.fetchall()
    cursor.close()
    return rows


def update_send_dates_batch(mssql_conn, pks, send_date, batch_size=1000):
    """
    Update sms_send_date for a batch of PKs.
    
    Uses smaller batches to avoid deadlocks and long-running transactions.
    
    Args:
        mssql_conn: Database connection
        pks: List of PK values to update
        send_date: Date to set
        batch_size: Batch size for updates
    """
    if not pks:
        return
    cursor = mssql_conn.cursor()
    for i in range(0, len(pks), batch_size):
        batch = pks[i:i + batch_size]
        placeholders = ",".join(["%s"] * len(batch))
        cursor.execute(
            f"UPDATE dbo.TblMailersUnique SET sms_send_date = %s WHERE PK IN ({placeholders})",
            tuple([send_date] + batch),
        )
        mssql_conn.commit()
    cursor.close()


def read_progress(progress_file):
    """
    Read checkpoint data from progress file.
    
    Returns tuple: (last_drop, last_pk, part_num, total_written, rows_in_part)
    If file doesn't exist or is corrupt, returns None values.
    """
    defaults = (None, None, 1, 0, 0)
    if not os.path.exists(progress_file):
        return defaults
    
    try:
        with open(progress_file, 'r') as f:
            lines = [line.strip() for line in f.readlines() if line.strip()]
        
        if len(lines) < 5:
            print(f"  WARNING: Progress file has incomplete data, starting fresh")
            return defaults
        
        last_drop = lines[0] if lines[0] else None
        last_pk = int(lines[1]) if lines[1] and lines[1] != 'None' else None
        part_num = int(lines[2]) if len(lines) > 2 and lines[2] else 1
        total_written = int(lines[3]) if len(lines) > 3 and lines[3] else 0
        rows_in_part = int(lines[4]) if len(lines) > 4 and lines[4] else 0
        
        return last_drop, last_pk, part_num, total_written, rows_in_part
    except Exception as e:
        print(f"  WARNING: Error reading progress file: {e}. Starting fresh.")
        return defaults


def write_progress(progress_file, last_drop, last_pk, part_num, total_written, rows_in_part):
    """
    Write checkpoint data to progress file.
    
    This is called after each successful batch to ensure safe resume.
    """
    with open(progress_file, 'w') as f:
        f.write(f"{last_drop or ''}\n")
        f.write(f"{last_pk or ''}\n")
        f.write(f"{part_num}\n")
        f.write(f"{total_written}\n")
        f.write(f"{rows_in_part}\n")


def open_output_file(output_dir, prefix, part_num, is_resume=False):
    """
    Open CSV file for writing.
    
    Args:
        output_dir: Directory to write to
        prefix: Filename prefix
        part_num: Part number
        is_resume: If True, continue writing to existing file
    
    Returns:
        Tuple: (file_handle, csv_writer, filepath, existing_rows)
    """
    filename = f"{prefix}_{part_num:03d}.csv"
    filepath = os.path.join(output_dir, filename)
    
    if is_resume and os.path.exists(filepath):
        # Count existing rows in file for accurate resume
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                existing_rows = sum(1 for _ in reader)
            file_handle = open(filepath, 'a', newline='', encoding='utf-8')
            writer = csv.DictWriter(file_handle, fieldnames=FIELDNAMES)
            print(f"  Resuming file: {filename} (existing rows: {existing_rows:,})")
            return file_handle, writer, filepath, existing_rows
        except Exception as e:
            print(f"  WARNING: Error reading existing file {filename}: {e}. Creating new file.")
            # Fall through to create new file
    
    # Create new file
    file_handle = open(filepath, 'w', newline='', encoding='utf-8')
    writer = csv.DictWriter(file_handle, fieldnames=FIELDNAMES)
    writer.writeheader()
    print(f"  Created file: {filename}")
    return file_handle, writer, filepath, 0


def ensure_index(mssql_conn):
    """
    Create recommended index for better performance.
    
    This is optional and only runs if --ensure-index flag is used.
    """
    print("  Checking/creating recommended index...")
    cursor = mssql_conn.cursor()
    
    # Check if index exists
    cursor.execute("""
        SELECT 1 FROM sys.indexes 
        WHERE object_id = OBJECT_ID('dbo.TblMailersUnique') 
        AND name = 'IX_TblMailersUnique_Export_Safe'
    """)
    if cursor.fetchone():
        print("  Index already exists.")
        cursor.close()
        return
    
    # Create the index
    print("  Creating index IX_TblMailersUnique_Export_Safe...")
    cursor.execute("""
        CREATE NONCLUSTERED INDEX IX_TblMailersUnique_Export_Safe
        ON dbo.TblMailersUnique (Drop_Name, sms_send_date, PK)
        INCLUDE (Client, Address, Debt_Amount, phone1, phone2, phone3, phone4, phone5)
        WHERE (phone1 IS NOT NULL OR phone2 IS NOT NULL OR phone3 IS NOT NULL OR phone4 IS NOT NULL OR phone5 IS NOT NULL) 
               AND Debt_Amount > 0
        WITH (ONLINE = ON)
    """)
    mssql_conn.commit()
    print("  Index created successfully.")
    cursor.close()


def main():
    parser = argparse.ArgumentParser(
        description="Production-safe export of Lending Tower records for Sam",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Fresh export
  python export_sam_report_safe.py --unsent-only --update-send-date --output-dir /var/www/cmd-runner/EE
  
  # Resume from checkpoint
  python export_sam_report_safe.py --unsent-only --update-send-date --output-dir /var/www/cmd-runner/EE --resume
  
  # Limit total rows
  python export_sam_report_safe.py --unsent-only --update-send-date --max-rows 1000000 --output-dir /var/www/cmd-runner/EE
        """
    )
    parser.add_argument("--max-rows", type=int, default=0,
                        help="Maximum total rows to export (0 = no limit)")
    parser.add_argument("--output-dir", type=str, default=".",
                        help="Directory for output files")
    parser.add_argument("--prefix", type=str, default=None,
                        help="File prefix (default: sam_export_safe_YYYY-MM-DD)")
    parser.add_argument("--rows-per-file", type=int, default=ROWS_PER_FILE,
                        help="Rows per file (default: 1,000,000)")
    parser.add_argument("--update-send-date", action="store_true",
                        help="Set sms_send_date to today for exported records")
    parser.add_argument("--unsent-only", action="store_true",
                        help="Only export rows where sms_send_date IS NULL")
    parser.add_argument("--resume", action="store_true",
                        help="Resume from last checkpoint")
    parser.add_argument("--ensure-index", action="store_true",
                        help="Create recommended index for better performance")
    args = parser.parse_args()

    prefix = args.prefix or f"sam_export_safe_{date.today().isoformat()}"
    send_date = date.today().isoformat()
    max_rows = args.max_rows if args.max_rows > 0 else float("inf")
    rows_per_file = args.rows_per_file

    os.makedirs(args.output_dir, exist_ok=True)
    progress_file = os.path.join(args.output_dir, f"{prefix}.progress")

    # Read checkpoint if resuming
    resume_from_drop = None
    resume_from_pk = None
    part_num = 1
    total_written = 0
    rows_in_current_file = 0

    if args.resume:
        resume_from_drop, resume_from_pk, part_num, total_written, rows_in_current_file = read_progress(progress_file)
        if resume_from_drop:
            print(f"Resuming from checkpoint:")
            print(f"  Drop: {resume_from_drop}")
            print(f"  Last PK: {resume_from_pk}")
            print(f"  Part: {part_num:03d}")
            print(f"  Total written: {total_written:,}")
            print(f"  Rows in current part: {rows_in_current_file:,}")
        else:
            print("No valid checkpoint found, starting fresh.")

    print("Connecting to SQL Server...")
    conn = get_mssql_connection()

    if args.ensure_index:
        ensure_index(conn)

    print("Fetching Drop_Name values...")
    drops = fetch_distinct_drops(conn, args.unsent_only, resume_from_drop)
    print(f"  Found {len(drops)} drops to process.")

    if not drops:
        print("Nothing to export.")
        conn.close()
        return

    start_time = time.time()
    total_drops = len(drops)

    # Open output file
    file_handle, writer, filepath, existing_rows = open_output_file(
        args.output_dir, prefix, part_num, is_resume=(args.resume and resume_from_drop)
    )
    rows_in_current_file = existing_rows

    try:
        for drop_idx, drop_name in enumerate(drops, 1):
            if total_written >= max_rows:
                print(f"\nReached max-rows limit ({int(max_rows):,}). Stopping.")
                break

            drop_start = time.time()
            last_pk = resume_from_pk if resume_from_pk is not None else 0
            rows_written_this_drop = 0
            pks_to_update = []
            drop_send_date = fetch_marketing_send_date(conn, drop_name)

            print(f"\n[{drop_idx}/{total_drops}] Processing drop: {drop_name}")

            # Process this drop in batches
            while True:
                if total_written >= max_rows:
                    break

                # Fetch batch
                batch_start = time.time()
                rows = fetch_rows_batch(conn, drop_name, last_pk, args.unsent_only, BATCH_SIZE)
                
                if not rows:
                    # No more rows in this drop
                    break

                # Process batch
                for row in rows:
                    if total_written >= max_rows:
                        break

                    # Format data
                    first_name = parse_first_name(row.get("Client", ""))
                    address = (row.get("Address", "") or "").upper()
                    debt_load = format_debt_load(row.get("Debt_Amount", ""))

                    phones = {}
                    for col in ["phone1", "phone2", "phone3", "phone4", "phone5"]:
                        phones[col] = (row.get(col, "") or "").strip()

                    # Rotate to new file if needed
                    if rows_in_current_file >= rows_per_file:
                        file_handle.close()
                        part_num += 1
                        rows_in_current_file = 0
                        file_handle, writer, filepath, _ = open_output_file(
                            args.output_dir, prefix, part_num, is_resume=False
                        )

                    # Write row
                    writer.writerow({
                        "First name": first_name,
                        "address": address,
                        "debt load": debt_load,
                        "phone1": phones["phone1"],
                        "phone2": phones["phone2"],
                        "phone3": phones["phone3"],
                        "phone4": phones["phone4"],
                        "phone5": phones["phone5"],
                        "send date": drop_send_date,
                    })
                    total_written += 1
                    rows_in_current_file += 1
                    rows_written_this_drop += 1

                    # Collect PK for update
                    pks_to_update.append(row["PK"])
                    last_pk = row["PK"]

                # Update send dates for this batch
                if args.update_send_date and pks_to_update:
                    update_send_dates_batch(conn, pks_to_update, sms_mark_date)
                    pks_to_update = []

                # Flush file handle
                file_handle.flush()

                # Save checkpoint after each batch
                write_progress(progress_file, drop_name, last_pk, part_num, total_written, rows_in_current_file)

                batch_elapsed = time.time() - batch_start
                print(f"  Batch: {len(rows):,} rows in {batch_elapsed:.1f}s | "
                      f"Total: {total_written:,} | Last PK: {last_pk}")

            # Clear resume PK after first drop
            resume_from_pk = None

            drop_elapsed = time.time() - drop_start
            print(f"  Completed drop {drop_name}: {rows_written_this_drop:,} rows ({drop_elapsed:.1f}s)")

    finally:
        file_handle.close()

    elapsed = time.time() - start_time
    print(f"\n{'='*60}")
    print(f"Export completed successfully!")
    print(f"  Total time: {elapsed:.1f}s ({elapsed/60:.1f}m)")
    print(f"  Output directory: {args.output_dir}")
    print(f"  Files created: {prefix}_001.csv ... {prefix}_{part_num:03d}.csv")
    print(f"  Total rows exported: {total_written:,}")
    if args.update_send_date:
        print(f"  sms_send_date updated to: {sms_mark_date}")
    else:
        print("  sms_send_date was NOT updated")
    print(f"{'='*60}")

    # Clean up progress file on successful completion
    if os.path.exists(progress_file):
        os.remove(progress_file)
        print("Progress file removed (export completed successfully)")

    conn.close()


if __name__ == "__main__":
    main()
