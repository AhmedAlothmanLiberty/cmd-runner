"""
Configuration for Lending Tower data pipeline.
All sensitive values loaded from environment variables.
"""
import os
from dotenv import load_dotenv

load_dotenv()

# SQL Server (external - TblMailers, TblContacts, TblEnrollment)
MSSQL_HOST = os.getenv("LT_MSSQL_HOST", "")
MSSQL_PORT = int(os.getenv("LT_MSSQL_PORT", "1433"))
MSSQL_USER = os.getenv("LT_MSSQL_USER", "")
MSSQL_PASSWORD = os.getenv("LT_MSSQL_PASSWORD", "")
MSSQL_DATABASE = os.getenv("LT_MSSQL_DATABASE", "")

# RDS MySQL (AWS - lending_tower)
MYSQL_HOST = os.getenv("LT_MYSQL_HOST", "lending-tower-db.cjkd8ljs7x9n.us-east-2.rds.amazonaws.com")
MYSQL_PORT = int(os.getenv("LT_MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("LT_MYSQL_USER", "admin")
MYSQL_PASSWORD = os.getenv("LT_MYSQL_PASSWORD", "")
MYSQL_DATABASE = os.getenv("LT_MYSQL_DATABASE", "lending_tower")

# AWS (Athena / S3)
AWS_REGION = os.getenv("LT_AWS_REGION", "us-east-2")
ATHENA_DATABASE = os.getenv("LT_ATHENA_DATABASE", "tu-identity-graph-crawler")
ATHENA_RESULTS_BUCKET = os.getenv("LT_ATHENA_RESULTS_BUCKET", "s3://517693899832-athena-results/")

# Exclusion settings
CONTACT_EXCLUSION_MONTHS = int(os.getenv("LT_CONTACT_EXCLUSION_MONTHS", "6"))
DEFAULT_SMS_DATE = "2024-01-01"
