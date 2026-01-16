#!/bin/bash
# 日志目录和文件
LOG_DIR="/opt/squid/var/log/squid"
ACCESS_LOG="${LOG_DIR}/access.log"
CACHE_LOG="${LOG_DIR}/cache.log"

#系统告警日志文件
SYSTEM_ALERT_LOG="/var/log/messages"

# 日志服务器配置
SYSLOG_SERVERS=("11.185.1.33" "11.185.1.57")
SYSLOG_PORT="514"
# 资源监控阈值（百分比）
THRESHOLD=2

# 日志函数
log_message() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$message"


# 将告警信息追到日志文件
 if [[ "$1" == ALERT:* ]]; then
	echo "$message" >> /var/log/monitor/alert.log
   fi
}

# 发送日志到远程服务器
send_to_remote() {
    local tag=$1
    local message=$2
    for SERVER in "${SYSLOG_SERVERS[@]}"; do
        logger -n "$SERVER" -P "$SYSLOG_PORT" -t "$tag" "$message"
    done
}
# 监控CPU使用率
monitor_cpu() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2 + $4}')
    if (( $(echo "$cpu_usage > $THRESHOLD" | bc -l) )); then
        local message="CPU usage is $cpu_usage% (threshold: $THRESHOLD%)"
        log_message "ALERT: $message"
        send_to_remote "system_monitor" "$message"
    fi
}
# 监控内存使用率
monitor_memory() {
    local total_mem=$(free -m | awk '/Mem:/ {print $2}')
    local used_mem=$(free -m | awk '/Mem:/ {print $3}')
    local mem_usage=$(echo "scale=2; $used_mem / $total_mem * 100" | bc)
    if (( $(echo "$mem_usage > $THRESHOLD" | bc -l) )); then
        local message="Memory usage is $mem_usage% (threshold: $THRESHOLD%)"
        log_message "ALERT: $message"
        send_to_remote "system_monitor" "$message"
    fi
}
# 监控磁盘使用率
monitor_disk() {
    local disks=("/usr/" "/opt/")
    for disk in "${disks[@]}"; do
        local disk_usage=$(df -h "$disk" | awk 'NR==2 {print $5}' | sed 's/%//')
        if (( $(echo "$disk_usage > $THRESHOLD" | bc -l) )); then
            local message="Disk usage on $disk is $disk_usage% (threshold: $THRESHOLD%)"
            log_message "ALERT: $message"
            send_to_remote "system_monitor" "$message"
        fi
    done
}
# 上传 access log
if [ -f "$ACCESS_LOG" ]; then
    while IFS= read -r line; do
        for SERVER in "${SYSLOG_SERVERS[@]}"; do
            logger -n "$SERVER" -P "$SYSLOG_PORT" -t poxappprd41a_squid_access "$line"
        done
    done < "$ACCESS_LOG"
fi
# 上传 cache log
if [ -f "$CACHE_LOG" ]; then
    while IFS= read -r line; do
        for SERVER in "${SYSLOG_SERVERS[@]}"; do
            logger -n "$SERVER" -P "$SYSLOG_PORT" -t poxappprd41a_squid_cache "$line"
        done
    done < "$CACHE_LOG"
fi
# 上传系统告警日志
if [ -f "$SYSTEM_ALERT_LOG" ]; then
 while IFS= read line; do
     for SERVER in  "${SYSLOG_SERVER[@]}"; do
         logger -n "$SERVER" -P "$SYSLOG_PORT" -t poxappprd41a_system_alert "$line"
     done
 done < "$SYSTEM_ALERT_LOG"
fi 
# 执行资源监控
monitor_cpu
monitor_memory
monitor_disk 
