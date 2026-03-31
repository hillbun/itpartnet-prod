#!/bin/bash

ARCHIVE_DIR="/opt/nginx/html/threat/archive"
LOG_FILE="/opt/squid_shell/logs/iocs_cleanup.log"

echo "==== $(date '+%F %T') Cleanup start ====" >> "$LOG_FILE"

find "$ARCHIVE_DIR" -type f -name "IOCS_*.csv" -mtime +7 -print -delete >> "$LOG_FILE" 2>&1

echo "==== $(date '+%F %T') Cleanup end ====" >> "$LOG_FILE"
echo >> "$LOG_FILE"
