#!/bin/bash

# 1. 生成 60 分钟前的时间戳（格式：日/月/年:时:分）
# 注意：这里只精确到分钟，方便字符串比较
START_TIME=$(date -d "60 minutes ago" +"%d/%b/%Y:%H:%M")

# 2. 使用 awk 进行健壮性处理
# 逻辑：遍历每一列，找到以 [ 开头的列，然后进行比较
awk -v start="$START_TIME" '
{
    for(i=1; i<=NF; i++) {
        if($i ~ /^\[[0-9]{2}\/[A-Z][a-z]{2}\/[0-9]{4}/) {
            if($i > "["start) {
                print $0
                next
            }
        }
    }
}' /opt/squid/var/log/access.log > /tmp/access_stat.log