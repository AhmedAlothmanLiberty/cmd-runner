import csv as std_csv
import os
import sys

import pyarrow as pa
import pyarrow.csv as pa_csv
import pyarrow.parquet as pq

csv_path = sys.argv[1]
parquet_path = sys.argv[2]
parquet_tmp_path = parquet_path + ".tmp"

compression = os.getenv("EE_PARQUET_COMPRESSION", "snappy")

try:
    block_size = int(os.getenv("EE_PARQUET_BLOCK_SIZE_BYTES", "1048576"))
except ValueError:
    block_size = 1048576
if block_size <= 0:
    block_size = 1048576


def replace_tmp_file() -> None:
    if os.path.exists(parquet_tmp_path):
        os.replace(parquet_tmp_path, parquet_path)


def remove_tmp_file() -> None:
    if os.path.exists(parquet_tmp_path):
        os.remove(parquet_tmp_path)


def build_invalid_row_handler(mode: str | None):
    if mode == "skip":
        return lambda row: "skip"

    if mode == "warn":
        def handler(row):
            print(f"WARN skipped invalid row {row.number}: {row.text[:200]}", file=sys.stderr)
            return "skip"

        return handler

    return None


def sniff_delimiter(path: str, encoding: str) -> str:
    override = os.getenv("EE_CSV_DELIMITER")
    if override:
        return override

    with open(path, "rb") as handle:
        sample = handle.read(65536)

    if not sample:
        return ","

    try:
        text = sample.decode(encoding, errors="ignore")
        dialect = std_csv.Sniffer().sniff(text, delimiters=",;\t|")
        return dialect.delimiter
    except std_csv.Error:
        return ","


def normalize_header_name(name: str, index: int) -> str:
    return name if name != "" else f"Unnamed: {index}"


def make_unique_column_names(names: list[str]) -> list[str]:
    counts = {}
    unique_names = []

    for index, name in enumerate(names):
        base_name = normalize_header_name(name, index)
        duplicate_index = counts.get(base_name, 0)

        if duplicate_index == 0:
            unique_name = base_name
        else:
            unique_name = f"{base_name}.{duplicate_index}"

        counts[base_name] = duplicate_index + 1
        unique_names.append(unique_name)

    return unique_names


def read_column_names(path: str, encoding: str, delimiter: str) -> list[str]:
    with open(path, "r", encoding=encoding, newline="") as handle:
        reader = std_csv.reader(handle, delimiter=delimiter)
        header = next(reader, [])

    return make_unique_column_names(header)


def build_csv_options(encoding: str):
    on_bad_lines = os.getenv("EE_CSV_ON_BAD_LINES")
    if on_bad_lines not in {None, "error", "warn", "skip"}:
        on_bad_lines = None

    delimiter = sniff_delimiter(csv_path, encoding)
    column_names = read_column_names(csv_path, encoding, delimiter)

    read_options = pa_csv.ReadOptions(
        use_threads=False,
        block_size=block_size,
        skip_rows=1,
        column_names=column_names,
        encoding=encoding,
    )
    parse_options = pa_csv.ParseOptions(
        delimiter=delimiter,
        invalid_row_handler=build_invalid_row_handler(on_bad_lines),
    )
    convert_options = pa_csv.ConvertOptions(
        check_utf8=False,
        strings_can_be_null=True,
        quoted_strings_can_be_null=True,
    )

    return read_options, parse_options, convert_options


def write_csv_stream_to_parquet(encoding: str) -> int:
    read_options, parse_options, convert_options = build_csv_options(encoding)
    reader = pa_csv.open_csv(
        csv_path,
        read_options=read_options,
        parse_options=parse_options,
        convert_options=convert_options,
    )

    writer = None
    rows = 0
    success = False

    try:
        for batch in reader:
            if writer is None:
                writer = pq.ParquetWriter(parquet_tmp_path, batch.schema, compression=compression)
            writer.write_batch(batch)
            rows += batch.num_rows

        if writer is None:
            empty_table = pa_csv.read_csv(
                csv_path,
                read_options=read_options,
                parse_options=parse_options,
                convert_options=convert_options,
            )
            pq.write_table(empty_table, parquet_tmp_path, compression=compression)
            rows = empty_table.num_rows

        success = True
        return rows
    finally:
        if writer is not None:
            writer.close()

        if success:
            replace_tmp_file()
        else:
            remove_tmp_file()


def write_excel_to_parquet() -> int:
    import pandas as pd

    excel_engine = os.getenv("EE_EXCEL_ENGINE")

    try:
        dataframe = pd.read_excel(csv_path, engine=excel_engine) if excel_engine else pd.read_excel(csv_path)
    except Exception as exc:
        raise RuntimeError(
            "Excel read failed. Convert the file to CSV before upload, or install the required engine."
        ) from exc

    table = pa.Table.from_pandas(dataframe, preserve_index=False)
    pq.write_table(table, parquet_tmp_path, compression=compression)
    replace_tmp_file()
    return len(dataframe.index)


def convert_to_parquet() -> int:
    ext = os.path.splitext(csv_path)[1].lower()

    if ext in {".xlsx", ".xls"}:
        return write_excel_to_parquet()

    encoding_override = os.getenv("EE_CSV_ENCODING")
    encodings = [encoding_override] if encoding_override else [
        "utf-8",
        "utf-8-sig",
        "cp1252",
        "latin1",
    ]

    last_error = None

    for encoding in encodings:
        if not encoding:
            continue

        try:
            return write_csv_stream_to_parquet(encoding)
        except (UnicodeDecodeError, pa.ArrowInvalid, pa.ArrowTypeError) as exc:
            last_error = exc

    if last_error is not None:
        raise last_error

    raise RuntimeError("CSV conversion failed before reading any data.")


rows = convert_to_parquet()
print(f"OK rows={rows}")
