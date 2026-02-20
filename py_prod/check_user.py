#!/usr/bin/env python3
import sys
import redis
import threading
import datetime

log_file = "/opt/py_prod/log/user.log"
log_lock = threading.Lock()

def log_request(username, result):
    timestamp = datetime.datetime.now().isoformat()
    message = f"[{timestamp}] [USER-CHECK] user={username} result={result}\n"
    with log_lock:
        with open(log_file, "a") as f:
            f.write(message)

# Redis连接池
redis_pool = redis.ConnectionPool(host='127.0.0.1', port=6379, password='StrongP@@ssw0rd123!', db=1, decode_responses=True)
r = redis.Redis(connection_pool=redis_pool)

# 主处理循环
for line in sys.stdin:
    try:
        line = line.strip()
        if not line or ' ' not in line:
            log_request("?", "invalid_input")
            print("ERR message=invalid_input")
            sys.stdout.flush()
            continue

        username, _ = line.split(None, 1)
        username = username.split("@")[0]

        # 获取用户权限标签（直接读取字符串）
        user_key = f"acl:user:{username}"
        user_tag = r.get(user_key)  # 返回字符串，如 "allow" 或 "deny"

        # 检查逻辑（直接操作字符串）
        if user_tag is None:
            log_request(username, "no_user_tags")
            print("ERR message=no_user_tags")
        elif user_tag == "deny":
            log_request(username, f"deny:{user_tag}")
            print("ERR message=user_denied")
        elif user_tag == "allow":
            log_request(username, f"allow:{user_tag}")
            print("OK")
        else:  # 处理非预期标签（如拼写错误）
            log_request(username, f"invalid_tag:{user_tag}")
            print("ERR message=invalid_tag")

    except Exception as e:
        log_request(username if 'username' in locals() else "?", f"exception:{str(e)}")
        print(f"ERR message=exception:{str(e)}")
    finally:
        sys.stdout.flush()
