#!/usr/bin/env python3
import sys
import redis
import json
import threading
import datetime

log_file = "/opt/py_prod/log/group.log"
log_lock = threading.Lock()

def log_request(username, result):
    timestamp = datetime.datetime.now().isoformat()
    message = f"[{timestamp}] [GROUP-CHECK] user={username} result={result}\n"
    with log_lock:
        with open(log_file, "a") as f:
            f.write(message)

# Redis连接池
redis_pool = redis.ConnectionPool(host='127.0.0.1', port=6379,db=1, decode_responses=True,password='StrongP@@ssw0rd123!')
r = redis.Redis(connection_pool=redis_pool)

# 主处理循环
for line in sys.stdin:
    try:
        line = line.strip()
        if not line or ' ' not in line:
            log_request(username, "invalid_input")
            print("ERR message=invalid_input")
            sys.stdout.flush()
            continue

        username, _ = line.split(None, 1)
        username = username.split("@")[0]

        # 获取用户所属的 AD 组列表
        user_key = f"acl:user:{username}"
        user_groups_raw = r.get(user_key)
        if not user_groups_raw:
            log_request(username, "no_user_groups")
            print("ERR message=no_user_groups")
            sys.stdout.flush()
            continue

        user_groups = json.loads(user_groups_raw)

        # 获取 Redis 中 group 白名单
        group_key = "acl:group:group"
        group_list_raw = r.hget(group_key, "groups")
        if not group_list_raw:
            log_request(username, "no_group_list")
            print("ERR message=no_group_list")
            sys.stdout.flush()
            continue

        allowed_groups = json.loads(group_list_raw)

        # 检查用户是否属于任何允许的组
        if any(group in allowed_groups for group in user_groups):
            log_request(username, f"allow:{user_groups}")
            print("OK")
        else:
            log_request(username, f"deny:{user_groups}")
            print("ERR message=group_denied")

    except Exception as e:
        log_request(username if 'username' in locals() else "?", f"exception:{str(e)}")
        print(f"ERR message=exception:{str(e)}")
    finally:
        sys.stdout.flush()
