#!/bin/bash

# 配置文件路径
LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的时间戳（格式匹配 [09/Apr/2026:17:48:49）
START_TIME=$(date -d "5 minutes ago" +"%d/%b/%Y:%H:%M:%S")

awk -v start="$START_TIME" '
BEGIN {
    print "["
    first = 1
}
{
    # 提取时间戳，去掉 [ 符号
    log_time = substr($4, 2)
    
    if (log_time >= start) {
        ip = $1
        user = $3
        # %<st 对应日志的第 7 列
        bytes = $7 
        
        # 以 IP 和 用户名为联合键进行累加
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
        
        # 换算单位，方便阅读
        size_kb = total_bytes[key] / 1024
        size_mb = total_bytes[key] / 1024 / 1024
        
        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"bytes_sent\": %d,\n    \"size_kb\": %.2f,\n    \"size_mb\": %.4f\n  }", 
                ip, (user=="-" ? "anonymous" : user), count[key], total_bytes[key], size_kb, size_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"