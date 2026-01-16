#!/bin/bash

# FTP配置信息
FTP_SERVER="160.93.34.26"
FTP_USER="n2syslogadmin"
FTP_PASS="1qsx6tfc!"  # 使用您手动测试成功的密码
REMOTE_DIR="/logs/FTP/INET_Proxy/poxappprd52a"

# 本地文件配置
LOCAL_DIR="/opt/squid/var/log"

# 获取昨天的日志文件名（格式：access.log-YYYYMMDD.gz）
##yesterday=$(date -d "-2 day" +%Y%m%d)
yesterday=$(date -d "yesterday" +%Y%m%d)
FILENAME="access.log-${yesterday}.gz"
LOCAL_FILE="${LOCAL_DIR}/${FILENAME}"

# 日志文件
LOG_FILE="/opt/squid/var/log/ftp_upload.log"

# 函数：记录日志
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# 开始执行
log_message "=== 开始FTP上传任务 ==="

# 检查本地文件是否存在
if [ ! -f "$LOCAL_FILE" ]; then
    log_message "错误: 文件 $LOCAL_FILE 不存在"
    echo "当前目录文件列表:"
    ls -la "$LOCAL_DIR"/access.log* >> "$LOG_FILE" 2>&1
    exit 1
fi

# 检查文件是否可读
if [ ! -r "$LOCAL_FILE" ]; then
    log_message "错误: 文件 $LOCAL_FILE 不可读"
    exit 1
fi

log_message "找到文件: $LOCAL_FILE"
log_message "文件大小: $(du -h "$LOCAL_FILE" | cut -f1)"

# 切换到文件所在目录
cd "$LOCAL_DIR" || {
    log_message "错误: 无法切换到目录 $LOCAL_DIR"
    exit 1
}

# 执行FTP上传
log_message "开始FTP上传到 $FTP_SERVER:$REMOTE_DIR"

ftp -n -i "$FTP_SERVER" << EOF >> "$LOG_FILE" 2>&1
user $FTP_USER $FTP_PASS
cd $REMOTE_DIR
binary
put $FILENAME
quit
EOF

# 检查FTP执行结果
if [ $? -eq 0 ]; then
    log_message "✓ FTP上传成功完成"
    log_message "=== 任务执行完成 ==="
else
    log_message "✗ FTP上传失败"
    log_message "=== 任务执行失败 ==="
    exit 1
fi
