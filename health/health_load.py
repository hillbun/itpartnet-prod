#!/usr/bin/env python3
from http.server import BaseHTTPRequestHandler, HTTPServer
import subprocess
import time
import os

INTERFACE = "team0"   # ← 如果是 bond0 / ens192，改这里

def get_load():
    """
    根据参考方式计算Load值：5分钟负载平均值 * 10000 / CPU核心数
    """
    # 获取5分钟负载平均值[2](@ref)
    with open("/proc/loadavg") as f:
        load_avg_5min = float(f.read().split()[1])  # 第二个值是5分钟负载
    
    # 获取CPU核心数[1](@ref)
    with open("/proc/cpuinfo") as f:
        cpu_count = len([line for line in f.readlines() if line.startswith("processor")])
    
    # 计算Load值：5分钟负载 * 10000 / 核心数
    load_value = int(load_avg_5min * 10000 / cpu_count)
    return load_value

def get_conns():
    """
    统计连接数：USER_TO_PROXY + PROXY_TO_OUTSIDE
    使用您提供的ss命令进行统计
    """
    try:
        # user -> proxy (目标端口为8080)[8](@ref)
        user_to_proxy_cmd = "ss -ant | awk '$1==\"ESTAB\" && $4 ~ /:8080$/ {count++} END {print count+0}'"
        user_to_proxy = int(subprocess.getoutput(user_to_proxy_cmd))
        
        # proxy -> outside (非8080端口, 非127.0.0.1, 目标端口为80或443)
        proxy_to_outside_cmd = "ss -ant | awk '$1==\"ESTAB\" && $4 !~ /:8080$/ && $5 !~ /:8080$/ && $5 !~ /^127\\.0\\.0\\.1:/ && $5 {count++} END {print count+0}'"
        proxy_to_outside = int(subprocess.getoutput(proxy_to_outside_cmd))
        
        return user_to_proxy + proxy_to_outside
    except Exception as e:
        # 如果统计失败，返回-1表示错误
        return -1

def get_mbps(interface):
    """
    计算网络带宽使用率(Mbps)
    保持原有的实现方式[9](@ref)
    """
    def read_bytes():
        with open("/proc/net/dev") as f:
            for line in f:
                if interface in line:
                    data = line.split()
                    rx = int(data[1])  # 接收字节数
                    tx = int(data[9])  # 发送字节数
                    return rx + tx
        return 0

    b1 = read_bytes()
    time.sleep(1)
    b2 = read_bytes()

    # 计算带宽：(字节差 * 8) / (1024 * 1024) 转换为Mbps
    mbps = (b2 - b1) * 8 / 1024 / 1024
    return round(mbps, 2)

class HealthHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path != "/health.load":
            self.send_response(404)
            self.end_headers()
            return

        load = get_load()
        conns = get_conns()
        mbps = get_mbps(INTERFACE)

        body = f"Load={load}\nConns={conns}\nMbps={mbps}\n"

        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.end_headers()
        self.wfile.write(body.encode())
    
    def log_message(self, format, *args):
        # 可选：简化日志输出
        pass

if __name__ == "__main__":
    print(f"启动健康监控服务器，接口: {INTERFACE}，端口: 8083")
    print("访问 http://<服务器IP>:8083/health.load 查看监控数据")
    HTTPServer(("0.0.0.0", 8083), HealthHandler).serve_forever()
