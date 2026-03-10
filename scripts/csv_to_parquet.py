import os
import sys
import pandas as pd
import pyarrow as pa
import pyarrow.parquet as pq
from pandas.errors import ParserError

csv_path = sys.argv[1]
parquet_path = sys.argv[2]
parquet_tmp_path = parquet_path + ".tmp"

compression = os.getenv("EE_PARQUET_COMPRESSION", "snappy")

try:
    chunk_rows = int(os.getenv("EE_PARQUET_CHUNK_ROWS", "50000"))
except ValueError:
    chunk_rows = 50000
if chunk_rows <= 0:
    chunk_rows = 50000


def write_df_to_parquet(df: pd.DataFrame) -> int:
    table = pa.Table.from_pandas(df, preserve_index=False)
    pq.write_table(table, parquet_tmp_path, compression=compression)
    os.replace(parquet_tmp_path, parquet_path)
    return len(df.index)


def write_csv_stream_to_parquet(encoding: str, delimiter: str | None, on_bad_lines: str | None, **overrides) -> int:
    read_options = {}
    if delimiter:
        read_options["sep"] = delimiter
    if on_bad_lines and "on_bad_lines" not in overrides:
        read_options["on_bad_lines"] = on_bad_lines
    read_options.update(overrides)

    writer = None
    total_rows = 0
    successful = False
    reader = pd.read_csv(
        csv_path,
        encoding=encoding,
        chunksize=chunk_rows,
        **read_options,
    )

    try:
        for chunk in reader:
            table = pa.Table.from_pandas(chunk, preserve_index=False)
            if writer is None:
                writer = pq.ParquetWriter(parquet_tmp_path, table.schema, compression=compression)
            writer.write_table(table)
            total_rows += len(chunk.index)

        if writer is None:
            # Build an empty parquet with schema when CSV has headers but no rows.
            empty_df = pd.read_csv(csv_path, encoding=encoding, nrows=0, **read_options)
            return write_df_to_parquet(empty_df)

        successful = True
        return total_rows
    finally:
        if writer is not None:
            writer.close()
        if successful and os.path.exists(parquet_tmp_path):
            os.replace(parquet_tmp_path, parquet_path)
        elif not successful and os.path.exists(parquet_tmp_path):
            os.remove(parquet_tmp_path)

ext = os.path.splitext(csv_path)[1].lower()

if ext in {".xlsx", ".xls"}:
    excel_engine = os.getenv("EE_EXCEL_ENGINE")
    try:
        df = pd.read_excel(csv_path, engine=excel_engine) if excel_engine else pd.read_excel(csv_path)
    except Exception as exc:
        raise RuntimeError(
            "Excel read failed. Install the required engine (openpyxl/xlrd) or set EE_EXCEL_ENGINE."
        ) from exc
    rows = write_df_to_parquet(df)
else:
    encoding_override = os.getenv("EE_CSV_ENCODING")
    encodings = [encoding_override] if encoding_override else [
        "utf-8",
        "utf-8-sig",
        "cp1252",
        "latin1",
    ]
    delimiter = os.getenv("EE_CSV_DELIMITER")
    on_bad_lines = os.getenv("EE_CSV_ON_BAD_LINES")
    if on_bad_lines not in {None, "error", "warn", "skip"}:
        on_bad_lines = None

    last_error = None
    rows = None
    for encoding in encodings:
        if not encoding:
            continue
        try:
            rows = write_csv_stream_to_parquet(encoding, delimiter, on_bad_lines)
            break
        except UnicodeDecodeError as exc:
            last_error = exc
        except ParserError as exc:
            last_error = exc
            fallback = {"engine": "python"}
            if not delimiter:
                fallback["sep"] = None
            try:
                rows = write_csv_stream_to_parquet(encoding, delimiter, on_bad_lines, **fallback)
                break
            except Exception as exc2:
                last_error = exc2

    if rows is None and last_error is not None:
        raise last_error

print(f"OK rows={rows}")
