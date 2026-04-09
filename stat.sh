#!/bin/bash

# 日志路径
LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的时间戳
START_TIME=$(date -d "5 minutes ago" +"%d/%b/%Y:%H:%M:%S")

awk -v start="$START_TIME" '
BEGIN {
    print "["
    first = 1
}
{
    # 提取时间戳 [09/Apr/2026:17:48:49
    log_time = substr($4, 2)
    
    if (log_time >= start) {
        ip = $1
        user = $3
        bytes = $7  # 对应格式中的 %<st
        
        # 按 IP 和 用户名 聚合
        count[ip, user]++
        total_bytes[ip, user] += bytes
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        ip = parts[1]
        user = parts[2]

        if (!first) print ","
        
        # 换算为 MB 保留两位小数
        size_mb = total_bytes[key] / 1024 / 1024
        
        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"access_count\": %d,\n    \"total_bytes\": %d,\n    \"total_size_mb\": %.2f\n  }", 
                ip, (user=="-" ? "anonymous" : user), count[key], total_bytes[key], size_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"