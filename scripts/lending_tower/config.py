"""
Configuration for Lending Tower data pipeline.
All sensitive values loaded from environment variables.
"""
import os
from pathlib import Path
from dotenv import load_dotenv

CURRENT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = CURRENT_DIR.parents[1]

load_dotenv(PROJECT_ROOT / ".env")
load_dotenv(CURRENT_DIR / ".env", override=True)

# SQL Server (external - TblMailers, TblContacts, TblEnrollment)
MSSQL_HOST = os.getenv("LT_MSSQL_HOST") or os.getenv("CMD_DB_HOST", "")
MSSQL_PORT = int(os.getenv("LT_MSSQL_PORT") or os.getenv("CMD_DB_PORT", "1433"))
MSSQL_USER = os.getenv("LT_MSSQL_USER") or os.getenv("CMD_DB_USERNAME", "")
MSSQL_PASSWORD = os.getenv("LT_MSSQL_PASSWORD") or os.getenv("CMD_DB_PASSWORD", "")
MSSQL_DATABASE = os.getenv("LT_MSSQL_DATABASE") or os.getenv("CMD_DB_DATABASE", "")

# RDS MySQL (AWS - lending_tower)
MYSQL_HOST = os.getenv("LT_MYSQL_HOST", "lending-tower-db.cjkd8ljs7x9n.us-east-2.rds.amazonaws.com")
MYSQL_PORT = int(os.getenv("LT_MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("LT_MYSQL_USER", "admin")
MYSQL_PASSWORD = os.getenv("LT_MYSQL_PASSWORD", "")
MYSQL_DATABASE = os.getenv("LT_MYSQL_DATABASE", "lending_tower")

# AWS (Athena / S3)
AWS_REGION = os.getenv("LT_AWS_REGION") or os.getenv("AWS_DEFAULT_REGION", "us-east-2")
ATHENA_DATABASE = os.getenv("LT_ATHENA_DATABASE", "tu-identity-graph-crawler")
ATHENA_RESULTS_BUCKET = os.getenv("LT_ATHENA_RESULTS_BUCKET", "s3://517693899832-athena-results/")
AWS_ACCESS_KEY_ID = os.getenv("LT_AWS_ACCESS_KEY_ID", "")
AWS_SECRET_ACCESS_KEY = os.getenv("LT_AWS_SECRET_ACCESS_KEY", "")
AWS_SESSION_TOKEN = os.getenv("LT_AWS_SESSION_TOKEN", "")

# Exclusion settings
CONTACT_EXCLUSION_MONTHS = int(os.getenv("LT_CONTACT_EXCLUSION_MONTHS", "6"))
DEFAULT_SMS_DATE = "2024-01-01"
