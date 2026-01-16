#!/bin/bash

# 日志目录 (与rsyslog 配置一致)
ALERT_LOG="/opt/squid_shell/resource_monitor_script.log"
#ALERT_LOG="/tmp/resource_monitor_script.log"

# 资源监控阈值（百分比）
CPU_THRESHOLD=50
MEM_THRESHOLD=50
DISK_THRESHOLD=50
DISK_ALERT_THRESHOLD=80

# 日志函数(仅本地记录告警，转发由 rsyslog 负责)
log_alert() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$message"  >> "$ALERT_LOG"    #写入本地告警文件，供rsyslog转发
    echo "$message" #可选: 终端输出,方便调试
}
# 监控CPU使用率
monitor_cpu() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2 + $4}')
    if (( $(echo "$cpu_usage > $CPU_THRESHOLD" | bc -l) )); then
        log_alert "CPU usage is $cpu_usage% (threshold: $CPU_THRESHOLD%)"
    fi
}
# 监控内存使用率
monitor_memory() {
    local total_mem=$(free -m | awk '/Mem:/ {print $2}')
    local used_mem=$(free -m | awk '/Mem:/ {print $3}')
    local mem_usage=$(echo "scale=2; $used_mem / $total_mem * 100" | bc)
    if (( $(echo "$mem_usage > $MEM_THRESHOLD" | bc -l) )); then
        log_alert "Memory usage is $mem_usage% (threshold: $MEM_THRESHOLD%)"
    fi
}
# 监控磁盘使用率
monitor_disk() {
    local disks=("/usr/" "/opt/" "/")
    for disk in "${disks[@]}"; do
        #获取磁盘使用率
        local disk_usage=$(df -h "$disk" | awk 'NR==2 {print $5}' | sed 's/%//')
        if [ $disk == "/opt/" ];then
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" -a "$disk_usage" -lt "$DISK_ALERT_THRESHOLD" ];then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Waring"
            elif [ "$disk_usage" -ge "$DISK_ALERT_THRESHOLD" ];then
                echo "要有权限清空或删除文件clean cache.log echo > /opt/squid/var/log/cache.log"
                echo "" > /opt/squid/var/log/cache.log
                find /opt/squid/var/log/  -name "cache.log-20*" -delete
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Alert"
            fi
        fi

        if [ $disk != "/opt/" ];then
             if [ "$disk_usage" -ge "$DISK_THRESHOLD" ];then
                 log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_ALERT_THRESHOLD%)"
             fi
        fi
    done
}
# 执行资源监控
monitor_cpu
monitor_memory
monitor_disk

