#!/bin/bash

LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的 Unix 时间戳
START_TS=$(date -d "5 minutes ago" +%s)

awk -v start_ts="$START_TS" '
BEGIN {
    print "["
    first = 1
    split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
    for (i=1; i<=12; i++) months[m[i]] = sprintf("%02d", i)
}
{
    # 修正点：将 [\/:] 改为 [/:]，消除警告
    t_str = substr($4, 2)
    split(t_str, t_parts, "[/:]")
    
    # 确保时间戳转换正常
    cur_ts = mktime(t_parts[3] " " months[t_parts[2]] " " t_parts[1] " " t_parts[4] " " t_parts[5] " " t_parts[6])

    if (cur_ts >= start_ts) {
        ip = $1
        user = $3
        # 对应你的 HAsquidlog 格式中第 10 列的 %<st
        bytes = $10 
        
        key = ip SUBSEP user
        count[key]++
        sum_bytes[key] += bytes
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        if (!first) print ","
        
        s_bytes = sum_bytes[key]
        s_mb = s_bytes / 1024 / 1024

        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"total_bytes\": %d,\n    \"size_mb\": %.4f\n  }", 
                parts[1], (parts[2] == "-" ? "anonymous" : parts[2]), count[key], s_bytes, s_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"
