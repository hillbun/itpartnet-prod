#!/bin/bash

LOG_FILE="/opt/squid/var/log/access.log"

# 获取 5 分钟前的 Unix 时间戳
START_TS=$(date -d "5 minutes ago" +%s)

awk -v start_ts="$START_TS" '
BEGIN {
    print "["
    first = 1
    # 月份映射
    split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
    for (i=1; i<=12; i++) months[m[i]] = sprintf("%02d", i)
}
{
    # 1. 处理时间戳 (取第4列，例如 [09/Apr/2026:17:48:49)
    t_str = substr($4, 2)
    split(t_str, t_parts, "[\/:]")
    # 转换为 Unix 秒数
    cur_ts = mktime(t_parts[3] " " months[t_parts[2]] " " t_parts[1] " " t_parts[4] " " t_parts[5] " " t_parts[6])

    # 2. 检查是否在 5 分钟范围内
    if (cur_ts >= start_ts) {
        ip = $1
        user = $3
        # 根据你的 logformat，流量字段 %<st 在第 10 列
        bytes = $10 
        
        # 3. 累加数据
        key = ip SUBSEP user
        count[key]++
        sum_bytes[key] += bytes
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        u_ip = parts[1]
        u_name = (parts[2] == "-" ? "anonymous" : parts[2])
        
        if (!first) print ","
        
        s_bytes = sum_bytes[key]
        s_kb = s_bytes / 1024
        s_mb = s_kb / 1024

        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"total_bytes\": %d,\n    \"size_mb\": %.4f\n  }", 
                u_ip, u_name, count[key], s_bytes, s_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"