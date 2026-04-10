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
# 如果小于 5 (包含 0 或非数字转换后的 0)，则设为 5
if [ "$MINUTES" -lt 5 ]; then
    MINUTES=5
# 如果大于 60，则设为 60
elif [ "$MINUTES" -gt 60 ]; then
    MINUTES=60
fi

# 日志路径
LOG_FILE="/opt/squid/var/log/access.log"

# 获取 $MINUTES 分钟前的 Unix 时间戳
START_TS=$(date -d "$MINUTES minutes ago" +%s)

awk -v start_ts="$START_TS" '
BEGIN {
    print "["
    first = 1
    # 建立月份映射表
    split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
    for (i=1; i<=12; i++) months[m[i]] = sprintf("%02d", i)
}
{
    # 1. 提取并转换时间戳
    # 针对 [%tl] 格式，例如 [09/Apr/2026:17:48:49
    t_str = substr($4, 2)
    split(t_str, t_parts, "[/:]")
    
    # 组合成 mktime 格式: "YYYY MM DD HH MM SS"
    cur_ts = mktime(t_parts[3] " " months[t_parts[2]] " " t_parts[1] " " t_parts[4] " " t_parts[5] " " t_parts[6])

    # 2. 时间窗口过滤
    if (cur_ts >= start_ts) {
        ip = $1
        user = $3
        # 根据 HAsquidlog 格式，%<st 流量位于第 10 列
        bytes = $10 
        
        # 使用 IP 和 用户名 作为联合键
        key = ip SUBSEP user
        count[key]++
        sum_bytes[key] += bytes
    }
}
END {
    for (key in count) {
        split(key, parts, SUBSEP)
        u_ip = parts[1]
        u_user = parts[2] # 保持原样，不进行转换

        if (!first) print ","
        
        s_bytes = sum_bytes[key]
        s_mb = s_bytes / 1024 / 1024

        printf "  {\n    \"ip\": \"%s\",\n    \"user\": \"%s\",\n    \"request_count\": %d,\n    \"total_bytes\": %d,\n    \"size_mb\": %.4f\n  }", 
                u_ip, u_user, count[key], s_bytes, s_mb
        first = 0
    }
    print "\n]"
}' "$LOG_FILE"