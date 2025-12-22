import sys
import pandas as pd

csv_path = sys.argv[1]
parquet_path = sys.argv[2]

df = pd.read_csv(csv_path)
df.to_parquet(parquet_path, engine="pyarrow", compression="snappy", index=False)
print("OK")
