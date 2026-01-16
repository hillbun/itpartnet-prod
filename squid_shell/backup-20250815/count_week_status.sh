#!/bin/bash

# Squid 日志目录
LOG_DIR="/opt/squid/var/log"

# 目标 JSON 文件
OUTPUT_FILE="/usr/nginx/html/transproxy_admin/public/fileData/threat/logstatus_7days.json"

# 获取昨天的日期（用于日志文件）
#YESTERDAY_DATE=$(date -d "yesterday" '+%Y%m%d')  # 格式: 20250216
YESTERDAY_DATE=$(date '+%Y%m%d')  # 格式: 20250216
#YESTERDAY_DATE=20250722
YESTERDAY_LOG="$LOG_DIR/access.log-$YESTERDAY_DATE.gz"
echo "日志文件: $YESTEDAY_LOG"

# 获取 7 天前的日期（用于删除 JSON 旧数据）
SEVEN_DAYS_AGO=$(date -d "7 days ago" '+%Y-%m-%d')
echo "删除7天前的日志: $SEVEN_DAYS_AGO"


# 格式化 JSON 日期
FORMATTED_YESTERDAY=$(date -d "yesterday" '+%Y-%m-%d')
yesterday=$(date -d 'yesterday' '+%d/%b/%Y')
#FORMATTED_YESTERDAY="2025-07-22"
#yesterday="21/Jul/2025"

# 需要统计的异常状态码
ERROR_CODES=("403" "407" "502" "504")

# 如果日志文件不存在，则退出
if [[ ! -f "$YESTERDAY_LOG" ]]; then
    echo "日志文件 $YESTERDAY_LOG 不存在，跳过统计"
    exit 1
fi

# **检查 JSON 文件是否为空或损坏**
if [ ! -f "$OUTPUT_FILE" ] || [ ! -s "$OUTPUT_FILE" ] || ! jq -e . "$OUTPUT_FILE" >/dev/null 2>&1; then
    echo "{ \"$FORMATTED_YESTERDAY\": { \"403\": 0, \"407\": 0, \"502\": 0, \"504\": 0 } }" > "$OUTPUT_FILE"
fi

# **读取现有 JSON 数据**
EXISTING_JSON=$(cat "$OUTPUT_FILE")

# 提取昨天的日志（解压后处理）
#LOGS=$(zcat "$YESTERDAY_LOG" | grep "\[$(date -d 'yesterday' '+%d/%b/%Y')")
LOGS=$(zcat "$YESTERDAY_LOG" | grep "\[$yesterday")

# 统计错误状态码
declare -A STATUS_COUNTS
for CODE in "${ERROR_CODES[@]}"; do
    STATUS_COUNTS[$CODE]=$(echo "$LOGS" | awk -v code="$CODE" '{if ($9 == code) count++} END {print count+0}')
done

# **构造 JSON 结构**
NEW_ENTRY="{"
COUNT=0
for CODE in "${ERROR_CODES[@]}"; do
    if [[ "$COUNT" -gt 0 ]]; then
        NEW_ENTRY+=", "
    fi
    NEW_ENTRY+="\"$CODE\": ${STATUS_COUNTS[$CODE]}"
    ((COUNT++))
done
NEW_ENTRY+="}"

# **追加 JSON 数据**
UPDATED_JSON=$(echo "$EXISTING_JSON" | jq --arg date "$FORMATTED_YESTERDAY" --argjson newEntry "$NEW_ENTRY" '. + {($date): $newEntry}')

# **删除 7 天前的数据**
UPDATED_JSON=$(echo "$UPDATED_JSON" | jq --arg oldDate "$SEVEN_DAYS_AGO" 'del(.[$oldDate])')

# **保存更新后的 JSON 数据**
echo "$UPDATED_JSON" > "$OUTPUT_FILE"

# **打印日志，方便调试**
echo "统计完成，结果已追加到：$OUTPUT_FILE"
cat "$OUTPUT_FILE"

