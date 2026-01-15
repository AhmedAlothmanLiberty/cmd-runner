import os
import sys
import pandas as pd
from pandas.errors import ParserError

csv_path = sys.argv[1]
parquet_path = sys.argv[2]

ext = os.path.splitext(csv_path)[1].lower()

if ext in {".xlsx", ".xls"}:
    excel_engine = os.getenv("EE_EXCEL_ENGINE")
    try:
        df = pd.read_excel(csv_path, engine=excel_engine) if excel_engine else pd.read_excel(csv_path)
    except Exception as exc:
        raise RuntimeError(
            "Excel read failed. Install the required engine (openpyxl/xlrd) or set EE_EXCEL_ENGINE."
        ) from exc
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

    def read_csv_with(encoding, **overrides):
        options = {}
        if delimiter:
            options["sep"] = delimiter
        if on_bad_lines and "on_bad_lines" not in overrides:
            options["on_bad_lines"] = on_bad_lines
        options.update(overrides)
        return pd.read_csv(csv_path, encoding=encoding, **options)

    df = None
    last_error = None
    for encoding in encodings:
        if not encoding:
            continue
        try:
            df = read_csv_with(encoding)
            break
        except UnicodeDecodeError as exc:
            last_error = exc
        except ParserError as exc:
            last_error = exc
            fallback = {"engine": "python"}
            if not delimiter:
                fallback["sep"] = None
            try:
                df = read_csv_with(encoding, **fallback)
                break
            except Exception as exc2:
                last_error = exc2

    if df is None and last_error is not None:
        raise last_error

df.to_parquet(parquet_path, engine="pyarrow", compression="snappy", index=False)
print("OK")
