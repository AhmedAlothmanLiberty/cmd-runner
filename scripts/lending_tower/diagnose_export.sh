#!/bin/bash
#===============================================================================
# diagnose_export.sh - Production-safe diagnostic for export_sam_report.py
#
# Usage:
#   ./diagnose_export.sh              # Dry run (default, safe)
#   DRY_RUN=false ./diagnose_export.sh # Enable kill actions (DANGEROUS)
#
# Author: Production Troubleshooter
# Safety: Does NOT kill processes unless DRY_RUN=false
#===============================================================================

set -euo pipefail

# Configuration
DRY_RUN="${DRY_RUN:-true}"
STALE_THRESHOLD_HOURS="${STALE_THRESHOLD_HOURS:-6}"
EXPORT_DIR="${EXPORT_DIR:-/var/www/cmd-runner/EE}"
SCRIPT_NAME="export_sam_report.py"
LOG_FILE="/var/www/cmd-runner/logs/diagnose_export_$(date +%Y%m%d_%H%M%S).log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

#-------------------------------------------------------------------------------
# Logging
#-------------------------------------------------------------------------------
mkdir -p "$(dirname "$LOG_FILE")"

log() {
    local level="$1"
    shift
    local msg="$*"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "[$timestamp] [$level] $msg" | tee -a "$LOG_FILE"
}

log_info()  { log "INFO" "$*"; }
log_warn()  { log "${YELLOW}WARN${NC}" "$*"; }
log_error() { log "${RED}ERROR${NC}" "$*"; }
log_ok()    { log "${GREEN}OK${NC}" "$*"; }

#-------------------------------------------------------------------------------
# Header
#-------------------------------------------------------------------------------
echo ""
echo "=============================================================="
echo " Export Process Diagnostic Tool"
echo " $(date)"
echo " Log: $LOG_FILE"
echo " DRY_RUN: $DRY_RUN"
echo "=============================================================="
echo ""

if [[ "$DRY_RUN" == "false" ]]; then
    log_warn "DRY_RUN=false - DESTRUCTIVE ACTIONS ENABLED"
    echo ""
    read -p "Are you sure you want to proceed? (yes/no): " confirm
    if [[ "$confirm" != "yes" ]]; then
        log_info "Aborted by user"
        exit 0
    fi
fi

#-------------------------------------------------------------------------------
# 1. Find all export_sam_report.py processes
#-------------------------------------------------------------------------------
log_info "=== STEP 1: Finding $SCRIPT_NAME processes ==="

mapfile -t PIDS < <(pgrep -f "$SCRIPT_NAME" 2>/dev/null || true)

if [[ ${#PIDS[@]} -eq 0 ]]; then
    log_info "No $SCRIPT_NAME processes found"
else
    log_info "Found ${#PIDS[@]} process(es)"
fi

#-------------------------------------------------------------------------------
# 2. Detailed process analysis
#-------------------------------------------------------------------------------
log_info "=== STEP 2: Process Details ==="

declare -A STALE_PIDS

for pid in "${PIDS[@]}"; do
    echo ""
    log_info "--- PID: $pid ---"
    
    # Basic process info
    if ps -p "$pid" -o pid,ppid,user,stat,%cpu,%mem,etime,cmd --no-headers 2>/dev/null; then
        # Get elapsed time in seconds
        etime=$(ps -p "$pid" -o etime= 2>/dev/null | tr -d ' ')
        
        # Parse elapsed time (handles DD-HH:MM:SS, HH:MM:SS, MM:SS formats)
        if [[ "$etime" =~ ^([0-9]+)-([0-9]+):([0-9]+):([0-9]+)$ ]]; then
            days="${BASH_REMATCH[1]}"
            hours="${BASH_REMATCH[2]}"
            mins="${BASH_REMATCH[3]}"
            secs="${BASH_REMATCH[4]}"
            total_secs=$((days*86400 + hours*3600 + mins*60 + secs))
        elif [[ "$etime" =~ ^([0-9]+):([0-9]+):([0-9]+)$ ]]; then
            hours="${BASH_REMATCH[1]}"
            mins="${BASH_REMATCH[2]}"
            secs="${BASH_REMATCH[3]}"
            total_secs=$((hours*3600 + mins*60 + secs))
        elif [[ "$etime" =~ ^([0-9]+):([0-9]+)$ ]]; then
            mins="${BASH_REMATCH[1]}"
            secs="${BASH_REMATCH[2]}"
            total_secs=$((mins*60 + secs))
        else
            total_secs=0
        fi
        
        hours_running=$((total_secs / 3600))
        
        # Check CPU usage
        cpu=$(ps -p "$pid" -o %cpu= 2>/dev/null | tr -d ' ')
        
        # Stale detection: running > threshold hours AND cpu < 1%
        if [[ "$hours_running" -ge "$STALE_THRESHOLD_HOURS" ]] && (( $(echo "$cpu < 1.0" | bc -l) )); then
            log_warn "STALE DETECTED: PID $pid running ${hours_running}h with ${cpu}% CPU"
            STALE_PIDS[$pid]=1
        fi
        
        # Get full command line
        log_info "Full cmdline:"
        cat /proc/"$pid"/cmdline 2>/dev/null | tr '\0' ' ' | fold -w 100
        echo ""
        
        # Check for worker flags
        cmdline=$(cat /proc/"$pid"/cmdline 2>/dev/null | tr '\0' ' ')
        if [[ "$cmdline" =~ --worker-id ]]; then
            worker_id=$(echo "$cmdline" | grep -oP '(?<=--worker-id )\d+' || echo "?")
            total_workers=$(echo "$cmdline" | grep -oP '(?<=--total-workers )\d+' || echo "?")
            log_info "Worker mode: $worker_id / $total_workers"
        else
            log_warn "Non-worker mode (processes ALL drops)"
        fi
        
        # Check output file
        output_file=$(lsof -p "$pid" 2>/dev/null | grep -E '\.csv$' | awk '{print $NF}' | head -1)
        if [[ -n "$output_file" ]]; then
            if [[ -f "$output_file" ]]; then
                size=$(stat -c%s "$output_file" 2>/dev/null || echo 0)
                size_mb=$((size / 1024 / 1024))
                rows=$(($(wc -l < "$output_file") - 1))
                log_info "Output: $output_file (${size_mb}MB, ${rows} rows)"
            else
                log_warn "Output file not found: $output_file"
            fi
        else
            log_warn "No CSV file handle detected"
        fi
        
        # Check SQL connection
        sql_conn=$(lsof -p "$pid" 2>/dev/null | grep -E ':1433|:ms-sql' | head -1 || true)
        if [[ -n "$sql_conn" ]]; then
            log_ok "SQL connection: ESTABLISHED"
            echo "  $sql_conn"
        else
            log_warn "SQL connection: NOT FOUND"
        fi
        
    else
        log_error "Process $pid no longer exists"
    fi
done

#-------------------------------------------------------------------------------
# 3. Check all export files
#-------------------------------------------------------------------------------
log_info ""
log_info "=== STEP 3: Export Files in $EXPORT_DIR ==="

if [[ -d "$EXPORT_DIR" ]]; then
    total_rows=0
    total_size=0
    
    for f in "$EXPORT_DIR"/sam_export*.csv; do
        if [[ -f "$f" ]]; then
            size=$(stat -c%s "$f" 2>/dev/null || echo 0)
            size_mb=$((size / 1024 / 1024))
            rows=$(($(wc -l < "$f") - 1))
            mtime=$(stat -c%y "$f" 2>/dev/null | cut -d. -f1)
            total_rows=$((total_rows + rows))
            total_size=$((total_size + size))
            printf "  %-35s %8d rows  %6d MB  %s\n" "$(basename "$f")" "$rows" "$size_mb" "$mtime"
        fi
    done
    
    echo ""
    log_info "TOTAL: $total_rows rows, $((total_size / 1024 / 1024)) MB"
else
    log_warn "Export directory not found: $EXPORT_DIR"
fi

#-------------------------------------------------------------------------------
# 4. Check for lock contention (if sqlcmd available)
#-------------------------------------------------------------------------------
log_info ""
log_info "=== STEP 4: SQL Server Lock Check (if accessible) ==="
log_info "Run this query on SQL Server to check for blocking:"
echo ""
cat << 'EOF'
SELECT 
    r.session_id,
    r.blocking_session_id,
    r.wait_type,
    r.wait_time / 1000.0 AS wait_seconds,
    t.text AS query_text
FROM sys.dm_exec_requests r
CROSS APPLY sys.dm_exec_sql_text(r.sql_handle) t
WHERE r.database_id = DB_ID('YourDatabaseName')
  AND r.wait_time > 0
ORDER BY r.wait_time DESC;
EOF
echo ""

#-------------------------------------------------------------------------------
# 5. Stale process handling
#-------------------------------------------------------------------------------
if [[ ${#STALE_PIDS[@]} -gt 0 ]]; then
    log_info ""
    log_info "=== STEP 5: Stale Process Handling ==="
    log_warn "Found ${#STALE_PIDS[@]} stale process(es): ${!STALE_PIDS[*]}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY_RUN=true - Would send SIGTERM to: ${!STALE_PIDS[*]}"
        log_info "To actually kill, run: DRY_RUN=false $0"
    else
        for pid in "${!STALE_PIDS[@]}"; do
            log_warn "Sending SIGTERM to PID $pid..."
            kill -TERM "$pid" 2>/dev/null || log_error "Failed to send SIGTERM to $pid"
        done
        log_info "Waiting 30 seconds for graceful shutdown..."
        sleep 30
        for pid in "${!STALE_PIDS[@]}"; do
            if ps -p "$pid" > /dev/null 2>&1; then
                log_warn "PID $pid still alive, sending SIGKILL..."
                kill -9 "$pid" 2>/dev/null || true
            else
                log_ok "PID $pid terminated gracefully"
            fi
        done
    fi
else
    log_ok "No stale processes detected"
fi

#-------------------------------------------------------------------------------
# 6. Summary and Recommendations
#-------------------------------------------------------------------------------
log_info ""
log_info "=== SUMMARY ==="
log_info "Active processes: ${#PIDS[@]}"
log_info "Stale processes:  ${#STALE_PIDS[@]}"
log_info "Log file: $LOG_FILE"

echo ""
echo "=============================================================="
echo " RECOMMENDATIONS"
echo "=============================================================="
echo ""
echo "1. Check database state:"
echo "   cd /var/www/cmd-runner/scripts/lending_tower"
echo "   source /var/www/cmd-runner/.venv/bin/activate"
echo "   python -c \"from config import *; import pymssql; c=pymssql.connect(MSSQL_HOST,MSSQL_USER,MSSQL_PASSWORD,MSSQL_DATABASE,port=MSSQL_PORT).cursor(); c.execute('SELECT COUNT(*) FROM dbo.TblMailersUnique WHERE phone1 IS NOT NULL AND sms_send_date IS NULL'); print(f'Unsent: {c.fetchone()[0]:,}')\""
echo ""
echo "2. If processes are stuck, kill them gracefully:"
echo "   DRY_RUN=false $0"
echo ""
echo "3. For safe recovery, run SINGLE process (no workers):"
echo "   python export_sam_report.py --unsent-only --update-send-date --output /var/www/cmd-runner/EE/sam_export_recovery.csv"
echo ""
echo "=============================================================="
