#!/bin/bash

# 日志路径
LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的时间戳（用于逻辑匹配）
START_TIME=$(date -d "5 minutes ago" +"%d/%b/%Y:%H:%M:%S")

# 使用 awk 提取数据并直接输出 JSON 格式
awk -v start="$START_TIME" '
BEGIN {
    # 打印 JSON 数组开始符号
    print "["
    first = 1
}
{
    # 提取时间戳 [09/Apr/2026:17:48:49
    log_time = substr($4, 2)
    
    if (log_time >= start) {
        ip = $1
        user = $3
        bytes = $7
        duration_ms = $8
        
        # 累加数据
        count[ip, user]++
        total_bytes[ip, user] += bytes
        total_ms[ip, user] += duration_ms
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        ip = parts[1]
        user = parts[2]
        
        # 计算速率 KB/s
        if (total_ms[key] > 0) {
            rate = (total_bytes[key] / 1024) / (total_ms[key] / 1000)
        } else {
            rate = 0
        }

        # 处理 JSON 逗号逻辑
        if (!first) {
            print ","
        }
        
        # 格式化为 JSON 对象
        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"access_count\": %d,\n    \"download_rate_kbps\": %.2f\n  }", 
                ip, (user=="-" ? "anonymous" : user), count[key], rate
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"