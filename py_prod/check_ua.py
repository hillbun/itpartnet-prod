#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys, threading, datetime, os, queue
from urllib.parse import unquote
import redis

# ===== 日志 =====
LOG_DIR = "/opt/py_prod/log/"
os.makedirs(LOG_DIR, exist_ok=True)
LOG_FILE = os.path.join(LOG_DIR, "ua_chrome.log")
LOG_QUEUE = queue.Queue(maxsize=10000)

def log_writer():
    while True:
        line = LOG_QUEUE.get()
        if line is None:
            break
        try:
            with open(LOG_FILE, "a", buffering=1, encoding="utf-8") as f:
                f.write(line)
        finally:
            LOG_QUEUE.task_done()

def log_line(ua_raw, ua_dec, dst_raw, ua_ok, domain_ok, result, info=""):
    ts = datetime.datetime.now().isoformat()
    msg = (f"[{ts}] [UA-DST-REDIS] result={result} ua_ok={str(ua_ok).lower()} "
           f"domain_ok={str(domain_ok).lower()} ua_raw={repr(ua_raw)} "
           f"ua_dec={repr(ua_dec)} dst_raw={repr(dst_raw)} {info}\n")
    try:
        LOG_QUEUE.put_nowait(msg)
    except queue.Full:
        pass

threading.Thread(target=log_writer, daemon=True).start()

# ===== Redis 连接与 key =====
#REDIS_HOST = os.environ.get("REDIS_HOST", "127.0.0.1")
#REDIS_PORT = int(os.environ.get("REDIS_PORT", "6379"))
#REDIS_PASSWORD = os.environ.get("REDIS_PASSWORD", "StrongP@@ssw0rd123!")
UA_SET_KEY = os.environ.get("REDIS_UA_SUBSTR_SET", "acl:pop_ua_whitelist")           # 存 “需要包含”的 UA 子串，如 "Chrome/"
DOMAIN_SET_KEY = os.environ.get("REDIS_DOMAIN_SET", "acl:pop_domain_whitelist")   # 存 原样域名字符串，如 "www.scmp.com" 或 "www.scmp.com:443"

try:
    redis_pool = redis.ConnectionPool(
        host='127.0.0.1',
        port=6379,
        password='StrongP@@ssw0rd123!',
        decode_responses=True,
        max_connections=1000,
        socket_timeout=2,
        socket_connect_timeout=2,
        db=1,
        retry_on_timeout=True
    )
    r = redis.Redis(connection_pool=redis_pool)
except Exception as e:
    log_line("", "", "", False, False, "ERR-1", f"redis_init_error={e}")
    sys.exit(1)


if __name__ == "__main__":
    for line in sys.stdin:
        try:
            raw = line.strip()
            parts = raw.split()
            dst_tok = parts[0] if len(parts) >= 2 else "No_domain"
            ua_tok  = parts[1] if len(parts) >= 1 else "No_ua"
            #log_line("", "parts", "", False, False, f"ERR-0", f"----{dst_tok}---{ua_tok}----")

            # 1) 先做域名等值匹配（%DST 原样，不改写）
            try:
                redis_domain=r.sismember(DOMAIN_SET_KEY, dst_tok)
                domain_ok = bool(r.sismember(DOMAIN_SET_KEY, dst_tok))
            except Exception as e:
                log_line(ua_tok, "", dst_tok, False, False, f"ERR-2", f"redis_sismember_error={e}")
                print("ERR") #; sys.stdout.flush(); continue
                continue

            if not domain_ok:
                # 域名没命中：直接返回 ERR（节省性能）
                #log_line(ua_tok, "", dst_tok, False, False, f"ERR-3 {redis_domain} {DOMAIN_SET_KEY} {dst_tok}", "domain_miss")
                log_line(ua_tok, "", dst_tok, False, False, f"ERR-3", "domain_miss")
                print("ERR") #; sys.stdout.flush(); continue
                continue

            # 2) 命中域名后再判断 UA：UA 中需包含 Redis 集合里的任一子串（如 "Chrome/"）
            ua_dec = unquote(ua_tok) if ua_tok else ""
            try:
                ua_subs = r.smembers(UA_SET_KEY)   # 可维护多种：Chrome/、Edg/、Firefox/ 等
            except Exception as e:
                log_line(ua_tok, ua_dec, dst_tok, False, True, "ERR-4", f"redis_smembers_error={e}")
                print("ERR") #; sys.stdout.flush(); continue
                continue

            ua_ok = any(s and (s in ua_dec) for s in ua_subs)

            result = "OK" if (ua_ok and domain_ok) else "ERR"
            log_line("", ua_dec, dst_tok, ua_ok, domain_ok, f"{result}-5")
            print(result)
        except Exception as e:
            log_line("e", "e", "e","e", "e", f"exception={e} ")
            #log_line(ua_tok if 'ua_tok' in locals() else "", ua_dec if 'ua_dec' in locals() else "",
            #         dst_tok if 'dst_tok' in locals() else "", False, False, "ERR", f"exception={e}")
            print("ERR")
        finally:
            sys.stdout.flush()
