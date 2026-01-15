import os
import sys
import pandas as pd

csv_path = sys.argv[1]
parquet_path = sys.argv[2]

encoding_override = os.getenv("EE_CSV_ENCODING")
encodings = [encoding_override] if encoding_override else [
    "utf-8",
    "utf-8-sig",
    "cp1252",
    "latin1",
]

df = None
last_error = None
for encoding in encodings:
    if not encoding:
        continue
    try:
        df = pd.read_csv(csv_path, encoding=encoding)
        break
    except UnicodeDecodeError as exc:
        last_error = exc

if df is None and last_error is not None:
    raise last_error

df.to_parquet(parquet_path, engine="pyarrow", compression="snappy", index=False)
print("OK")
