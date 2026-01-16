#!/bin/bash

# 目标 JSON 文件
OUTPUT_FILE="/usr/nginx/html/transproxy_admin/public/fileData/threat/netstat_status.json"

# 获取当前日期+小时 (YYYY-MM-DD_HH)
CURRENT_HOUR=$(date '+%Y-%m-%d_%H')

# 统计 TCP 连接状态
declare -A CONNECTION_COUNTS
for STATE in ESTABLISHED TIME_WAIT CLOSE_WAIT LISTEN SYN_SENT SYN_RECV FIN_WAIT1 FIN_WAIT2 CLOSING LAST_ACK UNKNOWN; do
    CONNECTION_COUNTS[$STATE]=$(netstat -tan | awk -v state="$STATE" '$6 == state {count++} END {print count+0}')
done

# 生成当前小时的数据 JSON (保证 JSON 格式正确)
NEW_DATA=$(jq -n --arg hour "$CURRENT_HOUR" \
    --argjson closing "${CONNECTION_COUNTS[CLOSING]}" \
    --argjson time_wait "${CONNECTION_COUNTS[TIME_WAIT]}" \
    --argjson last_ack "${CONNECTION_COUNTS[LAST_ACK]}" \
    --argjson syn_sent "${CONNECTION_COUNTS[SYN_SENT]}" \
    --argjson close_wait "${CONNECTION_COUNTS[CLOSE_WAIT]}" \
    --argjson syn_recv "${CONNECTION_COUNTS[SYN_RECV]}" \
    --argjson established "${CONNECTION_COUNTS[ESTABLISHED]}" \
    --argjson fin_wait2 "${CONNECTION_COUNTS[FIN_WAIT2]}" \
    --argjson listen "${CONNECTION_COUNTS[LISTEN]}" \
    --argjson fin_wait1 "${CONNECTION_COUNTS[FIN_WAIT1]}" \
    --argjson unknown "${CONNECTION_COUNTS[UNKNOWN]}" \
    '{
        ($hour): {
            "CLOSING": $closing,
            "TIME_WAIT": $time_wait,
            "LAST_ACK": $last_ack,
            "SYN_SENT": $syn_sent,
            "CLOSE_WAIT": $close_wait,
            "SYN_RECV": $syn_recv,
            "ESTABLISHED": $established,
            "FIN_WAIT2": $fin_wait2,
            "LISTEN": $listen,
            "FIN_WAIT1": $fin_wait1,
            "UNKNOWN": $unknown
        }
    }')

# 确保 JSON 文件存在
if [[ ! -f "$OUTPUT_FILE" ]]; then
    echo "{}" > "$OUTPUT_FILE"
fi

# **合并新数据**
UPDATED_JSON=$(jq --argjson newData "$NEW_DATA" '. * $newData' "$OUTPUT_FILE")

# **删除超过 3 天前的数据**
THREE_DAYS_AGO=$(date -d "-3 days" '+%Y-%m-%d_%H')

UPDATED_JSON=$(echo "$UPDATED_JSON" | jq --arg cutoff "$THREE_DAYS_AGO" '
    with_entries(select(.key >= $cutoff))
')

# **确保最终 JSON 不是空**
if [[ -n "$UPDATED_JSON" ]]; then
    echo "$UPDATED_JSON" > "$OUTPUT_FILE"
else
    echo "{}" > "$OUTPUT_FILE"
fi

# **输出结果**
echo "系统状态统计完成，已写入：$OUTPUT_FILE"
cat "$OUTPUT_FILE"

