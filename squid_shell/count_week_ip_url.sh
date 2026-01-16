#!/bin/bash

# 配置路径
INPUT_JSON="/usr/nginx/html/transproxy_admin/public/fileData/threat/blacklist_summary.json"
WEEKLY_IP_OUTPUT="/usr/nginx/html/transproxy_admin/public/fileData/threat/week_top_ip_10.json"
WEEKLY_URL_OUTPUT="/usr/nginx/html/transproxy_admin/public/fileData/threat/week_top_url_10.json"

# 检查输入文件是否存在
if [ ! -f "$INPUT_JSON" ]; then
    echo "错误: 输入文件 $INPUT_JSON 不存在！" >&2
    exit 1
fi

# 获取一周的日期列表
start_date=$(date -d "last sunday -6 days" "+%Y-%m-%d")
end_date=$(date -d "last sunday" "+%Y-%m-%d")

# 初始化统计变量
declare -A ip_count
declare -A url_count
declare -a week_dates=()

# 生成日期列表
current_date="$start_date"
while [ "$current_date" != "$(date -d "$end_date +1 day" "+%Y-%m-%d")" ]; do
    week_dates+=("$current_date")
    current_date=$(date -d "$current_date +1 day" "+%Y-%m-%d")
done

# 遍历 JSON 数据，统计 IP 和 URL 出现次数
for date in "${week_dates[@]}"; do
    ips=$(jq -r --arg date "$date" '.[$date] | to_entries[] | .value[] | .ip' "$INPUT_JSON" 2>/dev/null)
    urls=$(jq -r --arg date "$date" '.[$date] | to_entries[] | .value[] | .domain' "$INPUT_JSON" 2>/dev/null)
    
    # 累计 IP 统计
    for ip in $ips; do
        ip_count["$ip"]=$((ip_count["$ip"] + 1))
    done

    # 累计 URL 统计
    for url in $urls; do
        url_count["$url"]=$((url_count["$url"] + 1))
    done
done

# 获取前 10 个 IP 和 URL
top_ips=($(for ip in "${!ip_count[@]}"; do echo "$ip ${ip_count[$ip]}"; done | sort -k2 -nr | head -n 10 | awk '{print $1}'))
top_urls=($(for url in "${!url_count[@]}"; do echo "$url ${url_count[$url]}"; done | sort -k2 -nr | head -n 10 | awk '{print $1}'))

# 准备 JSON 输出
generate_weekly_data() {
    local -n top_list=$1
    local -n count_map=$2
    local -n dates=$3

    local result="{}"
    result=$(echo "$result" | jq --argjson data "$(echo "${dates[@]}" | jq -R -s 'split(" ")')" '. += {data: $data}')

    result=$(echo "$result" | jq --argjson iplist "$(printf '%s\n' "${top_list[@]}" | jq -R -s 'split("\n")[:-1]')" '. += {iplist: $iplist}')

    for date in "${dates[@]}"; do
        local daily_counts=()
        for ip in "${top_list[@]}"; do
            daily_counts+=(${count_map["$ip,$date"]:-0})
        done
        result=$(echo "$result" | jq --argjson counts "$(echo "${daily_counts[@]}" | jq -s '.')" --arg date "$date" '. += {($date): $counts}')
    done
    echo "$result"
}

# 按日期统计 IP 和 URL 的每天出现次数
for date in "${week_dates[@]}"; do
    ips=$(jq -r --arg date "$date" '.[$date] | to_entries[] | .value[] | .ip' "$INPUT_JSON" 2>/dev/null)
    urls=$(jq -r --arg date "$date" '.[$date] | to_entries[] | .value[] | .domain' "$INPUT_JSON" 2>/dev/null)
    
    for ip in "${top_ips[@]}"; do
        ip_count["$ip,$date"]=0
    done
    for url in "${top_urls[@]}"; do
        url_count["$url,$date"]=0
    done
    
    for ip in $ips; do
        if [[ " ${top_ips[*]} " =~ " $ip " ]]; then
            ip_count["$ip,$date"]=$((ip_count["$ip,$date"] + 1))
        fi
    done

    for url in $urls; do
        if [[ " ${top_urls[*]} " =~ " $url " ]]; then
            url_count["$url,$date"]=$((url_count["$url,$date"] + 1))
        fi
    done
done

# 生成 JSON 文件
ip_json=$(generate_weekly_data top_ips ip_count week_dates)
url_json=$(generate_weekly_data top_urls url_count week_dates)

echo "$ip_json" > "$WEEKLY_IP_OUTPUT"
echo "$url_json" > "$WEEKLY_URL_OUTPUT"

echo "统计完成，结果已保存到:"
echo "IP统计: $WEEKLY_IP_OUTPUT"
echo "URL统计: $WEEKLY_URL_OUTPUT"

