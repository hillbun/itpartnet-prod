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
    # 获取当前crontab中是否已存在清理任务
    local cron_exists=$(crontab -l 2>/dev/null | grep -c "find /var/crash -maxdepth 1 -name 'squid.*.core.*' -mtime 0 -print -delete")

    for disk in "${disks[@]}"; do
        # 获取磁盘使用率
        local disk_usage=$(df -h "$disk" 2>/dev/null | awk 'NR==2 {print $5}' | sed 's/%//')
        
        # 检查是否成功获取到磁盘使用率
        if [ -z "$disk_usage" ]; then
            continue  # 跳过无效的磁盘挂载点
        fi

        # 对 /var/crash 分区的特殊处理
        if [ "$disk" = "/var/crash" ]; then
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" ] && [ "$disk_usage" -lt "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Waring"
                # 确保在警告级别时，清理任务不被激活
                if [ "$cron_exists" -ne 0 ]; then
                    (crontab -l 2>/dev/null | grep -v "find /var/crash -maxdepth 1 -name 'squid.*.core.*' -mtime +1 -print -delete") | crontab -
                    log_alert "Crontab cleanup task for /var/crash has been REMOVED (usage below alert threshold)."
                fi
            elif [ "$disk_usage" -ge "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_ALERT_THRESHOLD%) Alert - Managing crontab task"
                # 当使用率超过警报阈值时，添加清理任务到crontab
                if [ "$cron_exists" -eq 0 ]; then
                    (crontab -l 2>/dev/null; echo "0 1 * * * /usr/bin/find /var/crash -maxdepth 1 -name 'squid.*.core.*' -mtime +1 -print -delete") | crontab -
                    log_alert "Crontab cleanup task for /var/crash has been ADDED (will run daily at 01:00)."
                else
                    log_alert "Crontab cleanup task for /var/crash is already active."
                fi
            else
                # 如果使用率正常（低于50%），确保清理任务被移除
                if [ "$cron_exists" -ne 0 ]; then
                    (crontab -l 2>/dev/null | grep -v "find /var/crash -maxdepth 1 -name 'squid.*.core.*' -mtime +1 -print -delete") | crontab -
                    log_alert "Disk usage on $disk is normal ($disk_usage%). Crontab cleanup task for /var/crash has been REMOVED."
                fi
            fi

        # 对 /opt/ 分区的特殊处理（保留您原有的逻辑）
        elif [ "$disk" = "/opt/" ]; then
            if [ "$disk_usage" -ge "$DISK_THRESHOLD" ] && [ "$disk_usage" -lt "$DISK_ALERT_THRESHOLD" ]; then
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_THRESHOLD%) Waring"
            elif [ "$disk_usage" -ge "$DISK_ALERT_THRESHOLD" ]; then
                echo "要有权限清空或删除文件clean cache.log echo > /opt/squid/var/log/cache.log"
                echo "" > /opt/squid/var/log/cache.log
                find /opt/squid/var/log/ -name "cache.log-20*" -delete
                log_alert "Disk usage on $disk is $disk_usage% (threshold: $DISK_ALERT_THRESHOLD%) Alert - Cleaned cache logs"
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
