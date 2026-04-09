#!/bin/bash

LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的 Unix 时间戳
START_TS=$(date -d "5 minutes ago" +%s)

awk -v start_ts="$START_TS" '
BEGIN {
    print "["
    first = 1
    # 月份对应表
    split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
    for (i=1; i<=12; i++) months[m[i]] = sprintf("%02d", i)
}
{
    # 针对你的 logformat，时间戳在第 4 列
    tstr = substr($4, 2)
    split(tstr, a, "[\/:]")
    current_ts = mktime(a[3] " " months[a[2]] " " a[1] " " a[4] " " a[5] " " a[6])

    if (current_ts >= start_ts) {
        ip = $1
        user = $3
        
        # --- 重点修改区 ---
        # 根据你的格式，%<st 实际上在第 10 列
        # 如果用户名 $3 后面有多个字段，建议直接用倒数的方式更安全
        # 在你的日志条目中，从左往右数，200 之后的数字是流量
        bytes = $10 
        # ------------------
        
        count[ip, user]++
        total_bytes[ip, user] += bytes
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        if (!first) print ","
        
        size_kb = total_bytes[key] / 1024
        size_mb = size_kb / 1024
        
        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"bytes_sent\": %d,\n    \"size_kb\": %.2f,\n    \"size_mb\": %.4f\n  }", 
                parts[1], (parts[2]=="-" ? "anonymous" : parts[2]), count[key], total_bytes[key], size_kb, size_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"