#!/bin/bash

# 1. 获取传入参数，若空则默认为 5
raw_input=${1:-5}

# 2. 校验是否为纯数字，不是则强制转为 0
if [[ "$raw_input" =~ ^[0-9]+$ ]]; then
    MINUTES=$((raw_input))
else
    MINUTES=0
fi

# 3. 区间限制逻辑
if [ "$MINUTES" -lt 5 ]; then
    MINUTES=5
elif [ "$MINUTES" -gt 60 ]; then
    MINUTES=60
fi

if [ "$MINUTES" -le 5 ]; then
    LINE_COUNT=250000
elif [ "$MINUTES" -le 10 ]; then
    LINE_COUNT=500000
elif [ "$MINUTES" -le 30 ]; then
    LINE_COUNT=1500000
elif [ "$MINUTES" -le 60 ]; then
    LINE_COUNT=3000000
else
    LINE_COUNT=5000000
fi

# 日志路径
LOG_FILE="/opt/squid/var/log/access.log"

# 生成 awk 可直接字符串比较的时间格式：DD/Mon/YYYY:HH:MM:SS
START_STR=$(date -d "$MINUTES minutes ago" +"%d/%b/%Y:%H:%M:%S")

awk -v start_str="$START_STR" '
BEGIN {
    print "["
    first = 1
}
{
    # 提取日志时间：[16/Apr/2026:00:05:18 → 16/Apr/2026:00:05:18
    t_str = substr($4, 2)
    
    # 正确时间过滤：只统计 >= 起始时间的日志
    if (t_str >= start_str) {
        key = $1 SUBSEP $3
        count[key]++
        sum_bytes[key] += $10
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        if (!first) print ","
        
        s_bytes = sum_bytes[key]
        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"total_bytes\": %d,\n    \"size_mb\": %.4f\n  }", 
               parts[1], parts[2], count[key], s_bytes, s_bytes/1048576
        first = 0
    }
    print "\n]"
}' <(tail -n "$LINE_COUNT" "$LOG_FILE")
