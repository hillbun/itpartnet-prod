#!/bin/bash
# 日志目录 (与rsyslog 配置一致)
ALERT_LOG="/opt/squid_shell/resource_monitor_script.log"
# 资源监控阈值（百分比）
CPU_THRESHOLD=50
MEM_THRESHOLD=50
DISK_THRESHOLD=50
DISK_ALERT_THRESHOLD=80 # /var/crash 的警报阈值设定为80%
# 日志函数
log_alert() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "$message" >> "$ALERT_LOG"
    echo "$message" # 终端输出,方便调试
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
# 监控磁盘使用率 (重点修改部分)
monitor_disk() {
    local disks=("/usr/" "/opt/" "/" "/var/crash") # 将 /var 替换为 /var/crash
    for disk in "${disks[@]}"; do
        # 获取磁盘使用率
        local disk_usage=$(df -h "$disk" 2>/dev/null | awk 'NR==2 {print $5}' | sed 's/%//')
        # 检查是否成功获取到磁盘使用率
        if [ -z "$disk_usage" ]; then
            continue # 跳过无效的磁盘挂载点
        fi
        # 对 /var/crash 分区的特殊处理
        if [ "$disk" = "/var/crash" ]; then
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" ] && [ "$disk_usage" -lt "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Warning"
            elif [ "$disk_usage" -ge "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_ALERT_THRESHOLD%) Alert - Cleaning core files"
                # 直接执行清理命令（删除超过3天的 squid core dump 文件）
                find /var/crash -maxdepth 1 -name 'squid.*.core.*' -mtime +3 -print -delete
            fi
        # 对 /opt/ 分区的特殊处理
        elif [ "$disk" = "/opt/" ]; then
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" ] && [ "$disk_usage" -lt "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Warning"
            elif [ "$disk_usage" -ge "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_ALERT_THRESHOLD%) Alert - Cleaning cache logs"
                # 清空 cache.log 并删除历史日志
                echo  > /opt/squid/var/log/cache.log
                #find /opt/squid/var/log/ -name "cache.log-20*" -delete
            fi
        # 对其他分区（"/usr/", "/"）的通用处理
        else
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%)"
            fi
        fi
    done
}
# 执行资源监控
monitor_cpu
monitor_memory
monitor_disk
