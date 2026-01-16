#!/bin/bash

# 配置路径
LOG_DIR="/opt/squid/var/log"
TXT_DIR="/usr/nginx/html/transproxy_admin/public/fileData/threat"
##YESTERDAY_DATE=$(date -d "yesterday" +"%Y%m%d")  # 格式: 20250216
YESTERDAY_DATE=$(date  +"%Y%m%d")  # 格式: 20250216
##YESTERDAY_DATE=$(date +"%Y%m%d")  # 格式: 20250216
#YESTERDAY_DATE=20250729
INPUT_FILE="$LOG_DIR/access-tianji-blacklist.log-$YESTERDAY_DATE.gz"
BLACKLIST_FILES=(
    "$TXT_DIR/botnet.txt"
    "$TXT_DIR/c2.txt"
    "$TXT_DIR/gambling.txt"
    "$TXT_DIR/malicious_website.txt"
    "$TXT_DIR/phishing.txt"
    "$TXT_DIR/porn.txt"
    "$TXT_DIR/ransomware.txt"
)

OUTPUT_JSON="$TXT_DIR/blacklist_summary.json"
TEMP_STATS="/opt/squid_shell/daily_blacklist_statistics.txt"

# 初始化JSON文件
if [ ! -s "$OUTPUT_JSON" ]; then
    echo "{}" > "$OUTPUT_JSON"
fi
RESULT=$(cat "$OUTPUT_JSON")

# 获取双格式日期
YESTERDAY_LOG=$(date +"%d/%b/%Y")   # 日志格式 17/Jul/2025
YESTERDAY_JSON=$(date +"%Y-%m-%d") # JSON键格式 2025-07-17
##YESTERDAY_LOG=$(date -d "-1 day" +"%d/%b/%Y")   # 日志格式 17/Jul/2025
##YESTERDAY_JSON=$(date -d "-1 day" +"%Y-%m-%d") # JSON键格式 2025-07-17
##YESTERDAY_LOG=$(date -d "yesterday" +"%d/%b/%Y")   # 日志格式 17/Jul/2025
##YESTERDAY_JSON=$(date -d "yesterday" +"%Y-%m-%d") # JSON键格式 2025-07-17
#YESTERDAY_LOG="15/Aug/2025"   # 日志格式 17/Jul/2025
#YESTERDAY_JSON="2025-08-15" # JSON键格式 2025-07-17

# 步骤1：提取昨日日志（优化日期匹配逻辑）
> "$TEMP_STATS"  # 清空临时文件
zcat "$INPUT_FILE" | while IFS= read -r line; do
    log_date=$(echo "$line" | grep -oP '\[\K[^:]+')
    [[ "$log_date" != "$YESTERDAY_LOG" ]] && continue
    
    ip=$(awk '{print $1}' <<< "$line")
    status=$(awk '{print $9}' <<< "$line")
    #domain=$(grep -oP 'CONNECT \K[^:]+' <<< "$line")
    domain=$(grep -oP 'https?://\K[^/:]+' <<< "$line")
    echo "获取的IP：$ip  $status  $domain"
    
    [[ -n "$ip" && -n "$status" && -n "$domain" ]] && 
    echo "$ip $status $domain" >> "$TEMP_STATS"
done
echo "步骤1完成：提取日志保存至 $TEMP_STATS"


# 步骤2：黑名单分类（修复匹配逻辑）
while read -r ip status domain; do
    clean_domain=$(sed -r 's#^https?://##;s#/.*$##' <<< "$domain")
    # 优化域名提取逻辑
    domain_parts=$(tr -s '.' <<< "$clean_domain" | tr '.' '\n' | tac)
    main_domain=$(echo "$domain_parts" | sed -n '1p;2p' | tac | paste -sd'.')
    # 计算后缀域名（比如 www.hkjc.com → .hkjc.com）
    suffix=".$(echo "$clean_domain" | awk -F. '{print $(NF-1)"."$NF}')"
    
    matched=0
    for blacklist in "${BLACKLIST_FILES[@]}"; do
        # >>> 修改开始：逐级剥离域名进行匹配，支持黑名单以点开头的写法（如 .21.squid.hk）
        check_domain="$clean_domain"
        while [[ "$check_domain" == *.* ]]; do
            if grep -Fxq ".$check_domain" "$blacklist" || \
               grep -Fxq "$check_domain" "$blacklist"; then
               
               blacklist_name=$(basename "$blacklist" .txt)
               
               # 修复jq更新逻辑
               RESULT=$(jq --arg date "$YESTERDAY_JSON" \
                           --arg key "$blacklist_name" \
                           --arg ip "$ip" \
                           --arg status "$status" \
                           --arg domain "$clean_domain" \
                           '.[$date][$key] = ((.[$date][$key] // []) + [{
                               "ip": $ip,
                               "status": $status,
                               "domain": $domain,
                               "date": $date
                           }] | unique)' <<< "$RESULT")
               matched=1
               echo "匹配成功: $clean_domain → $blacklist_name (规则: .$check_domain)"
               break 2
            fi
            # 去掉最左边的一级再继续匹配
            check_domain="${check_domain#*.}"
        done
        # <<< 修改结束
    done
    
    [[ $matched -eq 0 ]] && echo "未匹配: $clean_domain ($ip)"
done < "$TEMP_STATS"

# 保存结果（增加调试输出）
echo "$RESULT" | jq . > "$OUTPUT_JSON"
echo "步骤2完成：JSON已保存至 $OUTPUT_JSON"
echo "生成记录数: $(jq ".[\"$YESTERDAY_JSON\"] | [.[]] | flatten | length" "$OUTPUT_JSON")"

# 清理（测试时可注释）
# rm -f "$TEMP_STATS"

