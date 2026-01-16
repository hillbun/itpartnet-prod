#!/bin/bash
set -euo pipefail

# 自动识别默认接口（或者手工传参）
IFACE="${1:-$(/usr/sbin/ip -o route get 1.1.1.1 | awk '{for(i=1;i<=NF;i++) if($i=="dev"){print $(i+1); exit}}')}"

FILE="/var/log/sa/sa$(date -d '1 hour ago' +%d)"
S=$(date -d '1 hour ago' +%H):00:00
E=$(date -d '1 hour ago' +%H):59:59
HOUR_KEY=$(date -d '1 hour ago' +%F_%H)

read RX_avg TX_avg RX_max TX_max <<<$(LANG=C sar -n DEV -f "$FILE" -s "$S" -e "$E" \
| awk -v IF="$IFACE" '
  $0 ~ /^Average:/ {next}
  $0 ~ /^[0-9]/ && $2==IF {
    rxB = $(NF-6) * 1024    # rxkB/s → Bytes/s
    txB = $(NF-5) * 1024    # txkB/s → Bytes/s
    sumrx += rxB; sumtx += txB; n++
    if (rxB > maxrx) maxrx = rxB
    if (txB > maxtx) maxtx = txB
  }
  END {
    if (n==0) { print 0,0,0,0; exit }
    print int(sumrx/n), int(sumtx/n), int(maxrx), int(maxtx)
  }')

# 用 jq 直接更新 JSON
jq --arg k "$HOUR_KEY" \
   --argjson rx "$RX_avg" \
   --argjson tx "$TX_avg" \
   --argjson rxmax "$RX_max" \
   --argjson txmax "$TX_max" '
  .[$k].network_usage.received_bytes = $rx
  | .[$k].network_usage.sent_bytes = $tx
  | .[$k].network_usage.received_bytes_max = $rxmax
  | .[$k].network_usage.sent_bytes_max = $txmax
' /usr/nginx/html/transproxy_admin/system_usage.json > /tmp/usage.json.tmp && \cp -f  /tmp/usage.json.tmp /usr/nginx/html/transproxy_admin/system_usage.json

