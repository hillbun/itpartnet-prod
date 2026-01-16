#!/bin/bash

# 配置路径
INPUT_FILE="/root/deny_logs/matched.logs"        # 实时监控生成的日志文件
BLACKLIST_FILES=(
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/pornographic.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/digital.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/gambling.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/malware.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/phishing.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/ransomware.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/spam.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/other.txt"
    "/usr/nginx/html/transproxy_admin/public/fileData/threat/url-blacklist.txt"
)

OUTPUT_JSON="/usr/nginx/html/transproxy_admin/public/fileData/threat/blacklist_summary.json"     # 最终生成的 JSON 文件
TEMP_STATS="/root/deny_logs/daily_blacklist_statistics.txt" # 中间统计文件

# 初始化 JSON 数据
if [ ! -s "$OUTPUT_JSON" ]; then
    # 如果 JSON 文件不存在或为空，初始化为 "{}"
    echo "{}" > "$OUTPUT_JSON"
fi
RESULT=$(cat "$OUTPUT_JSON")

# 获取昨天的日期
#YESTERDAY=$(date -d "yesterday" +"%Y-%m-%d")
YESTERDAY=$(date  "+%Y-%m-%d")
#YESTERDAY="2024-12-19"

# 步骤 1: 提取昨天的日志记录，仅保留 $3 (IP地址)、$4 (状态码)、$7 (域名)
awk -v yesterday="$YESTERDAY" '
{
    # 转换时间戳为日期
    cmd = "date -d @"$1" +\"%Y-%m-%d\"";
    cmd | getline log_date;
    close(cmd);

    # 如果日期是昨天，提取需要的字段
    if (log_date == yesterday) {
        print $3, $4, $7;
    }
}' "$INPUT_FILE" > "$TEMP_STATS"

echo "步骤 1: 提取昨天的日志记录完成，结果保存在 $TEMP_STATS"

# 步骤 2: 遍历提取的域名，根据黑名单文件分类
while read -r domain; do
    # 转换域名，去掉 http:// 或 https://
    IP=$(echo "$domain" | awk '{print $1}')
    STATUS=$(echo "$domain" | awk '{print $2}')
    url=$(echo "$domain" | awk '{print $3}' | sed -r 's#https?://([^/]+)/?.*#\1#g')

    # 提取主域名部分
    dot_count=$(echo "$url" | grep -o '\.' | wc -l)
    if [ "$dot_count" -ge 2 ]; then
        # 包含两个及以上的 '.'，提取最后两部分
        main_domain=$(echo "$url" | awk -F'.' '{print $(NF-1)"."$NF}')
    else
        # 只有一个 '.'，直接使用域名
        main_domain="$url"
    fi

    # 遍历黑名单文件匹配
    for blacklist in "${BLACKLIST_FILES[@]}"; do
        if grep -Fq "$main_domain" "$blacklist"; then
            # 提取黑名单文件名作为 JSON 键
	    blacklist_name=$(basename "$blacklist" .txt)
            # 更新 JSON 数据，加入 IP、STATUS、域名和日期
            RESULT=$(printf '%s' "$RESULT" | jq --arg date "$YESTERDAY" --arg key "$blacklist_name" \
                --arg domain "$url" --arg ip "$IP" --arg status "$STATUS" \
                '.[$date][$key] += [{"ip": $ip, "status": $status, "domain": $domain, "date": $date}] | .[$date][$key] |= unique')
            break
        fi
    done
done < "$TEMP_STATS"  # 避免管道，确保变量作用域正确

# 保存 JSON 数据到文件
echo "$RESULT" > "$OUTPUT_JSON"
echo "步骤 2: 域名分类完成，结果已保存到 $OUTPUT_JSON"

# 清理中间文件
rm -f "$TEMP_STATS"
