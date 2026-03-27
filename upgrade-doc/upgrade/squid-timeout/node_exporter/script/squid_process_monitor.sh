#!/bin/bash
# 脚本路径: /opt/node_exporter/script/squid_process_monitor.sh
# 输出文件: /opt/node_exporter/textfile_collector/squid_process.prom

# 定义输出文件
OUTPUT_FILE="/opt/node_exporter/textfile_collector/squid_process.prom"
TMP_FILE="${OUTPUT_FILE}.$$"

# 确保输出目录存在
mkdir -p /opt/node_exporter/textfile_collector

# 清理旧临时文件（超过5分钟）
find /opt/node_exporter/textfile_collector -name "squid_process.prom.*" -mmin +5 -delete 2>/dev/null

# 生成指标头
cat > "$TMP_FILE" << 'EOF'
# HELP squid_process_count Number of squid processes by type
# TYPE squid_process_count gauge
EOF

# 执行你的命令并处理输出
ps aux | grep squid | grep -E "python|kerberos_auth" | awk '{print $NF}' | sort | uniq -c | while read count process; do
    # 清理进程名（替换特殊字符为下划线）
        process_clean=$(echo "$process" | sed 's/[^a-zA-Z0-9_]/_/g')
	    
	    # 如果是空进程名，使用unknown
	        [ -z "$process_clean" ] && process_clean="unknown"
		    
		    # 写入指标
		        echo "squid_process_count{process=\"$process_clean\"} $count" >> "$TMP_FILE"
		done

		# 如果没有找到进程，也要写入0值
		if [ ! -s "$TMP_FILE" ] || ! grep -q "squid_process_count{" "$TMP_FILE"; then
			    echo "squid_process_count{process=\"none\"} 0" >> "$TMP_FILE"
		    fi

		    # 原子替换文件
		    mv "$TMP_FILE" "$OUTPUT_FILE"

		    # 确保文件可读
		    chmod 644 "$OUTPUT_FILE"
