#!/usr/bin/env python3
import sys
import redis
import json
import threading
import datetime
import ipaddress

# 日志配置
log_file = "/opt/py_prod/log/special_user_deny.log"
log_lock = threading.Lock()

def log_request(username, domain, result):
    timestamp = datetime.datetime.now().isoformat()
    message = f"[{timestamp}] [GROUP-CHECK] user={username} domain={domain} result={result}\n"
    with log_lock:
        with open(log_file, "a") as f:
            f.write(message)

# Redis连接池（高并发优化）
redis_pool = redis.ConnectionPool(host='127.0.0.1', port=6379, password='StrongP@@ssw0rd123!', db=1, decode_responses=True)
r = redis.Redis(connection_pool=redis_pool)


def is_ip_address(hostname):
    """快速判断是否为IP地址"""
    try:
        ipaddress.ip_address(hostname)
        return True
    except ValueError:
        return False

def build_trie(rules):
    """构建域名trie树，只处理域名规则"""
    trie = {}
    domain_rules = [rule for rule in rules if not (is_ip_range_rule(rule) or is_ip_address(rule))]
    
    for rule in domain_rules:
        domain = rule.strip('.').lower()
        # 泛域名处理
        segments = domain.lstrip('.').split('.')[::-1] if rule.startswith('.') else domain.split('.')[::-1]
        node = trie
        for seg in segments:
            node = node.setdefault(seg, {})
        node["__end__"] = True
    return trie

def is_ip_range_rule(rule):
    """判断规则是否为IP范围规则"""
    if '-' in rule:
        # 检查是否是有效的IP范围
        parts = rule.split('-')
        if len(parts) == 2:
            try:
                # 尝试解析为IP地址
                ipaddress.ip_address(parts[0])
                ipaddress.ip_address(parts[1])
                return True
            except ValueError:
                # 不是有效的IP范围，可能是包含"-"的域名
                return False
    return False

def ip_in_range(ip, ip_range):
    """检查IP是否在范围内"""
    try:
        start, end = ip_range.split('-')
        ip_obj = ipaddress.ip_address(ip)
        return (ipaddress.ip_address(start) <= ip_obj <= ipaddress.ip_address(end))
    except:
        return False


def match_trie(trie, hostname):
    # parts = hostname.lower().split('.')[::-1]
    parts = hostname.lower().rstrip('.').split('.')[::-1]
    node = trie
    for seg in parts:
        if "__end__" in node:
            return True
        if seg not in node:
            return False
        node = node[seg]
    return "__end__" in node

def check_access(hostname, allow_rules):
    """检查主机名或IP是否在允许规则中"""
    
    # 快速处理IP地址
    if is_ip_address(hostname):
        # 检查IP范围规则
        ip_rules = [rule for rule in allow_rules if is_ip_range_rule(rule) or is_ip_address(rule)]
        for rule in ip_rules:
            if is_ip_address(rule):
                rule_range = f"{rule}-{rule}"  # 创建起始和结束相同的IP范围
            else:
                rule_range = rule
            
            if ip_in_range(hostname, rule_range):
                return True
        return False
    
    # 处理域名
    trie = build_trie(allow_rules)
    return match_trie(trie, hostname)

# 主循环处理（每行一个请求）
for line in sys.stdin:
    try:
        line = line.strip()
        if not line:
            print("ERR message=empty_input")
            sys.stdout.flush()
            continue

        parts = line.split()
        if len(parts) < 3:
            log_request("?", "?", "invalid_param_count")
            print("ERR message=invalid_param_count")
            sys.stdout.flush()
            continue

        username_raw, domain_source, port = parts[:3]
        username = username_raw.split('@')[0]
        hostname = domain_source.lower()
        domain = f"{hostname}"
        log_request(username, domain, f"test--------{port}")

        # 检查用户是否是特殊用户
        user_key = f"acl:special:{username}"
        if not r.exists(user_key):
            log_request(username, domain, "no_special_group_matched_skip")
            print("OK message=no_special_group_matched")
            sys.stdout.flush()
            continue

        # 获取用户的特殊策略
        policy_raw = r.hget(user_key, "serializedData")
        if not policy_raw:
            log_request(username, domain, "no_policy_data")
            print("OK message=no_policy_match")
            sys.stdout.flush()
            continue

        try:
            policy = json.loads(policy_raw)
            deny_list = policy.get("deny", [])
            log_request(username, domain, f"deny_by:{username}")
            # trie = build_trie(deny_list)
            # if match_trie(trie, hostname):
            if check_access(hostname, deny_list):
                log_request(username, domain, f"deny_by:{username}")
                print("ERR")
                sys.stdout.flush()
                continue
            else:
                log_request(username, domain, "no_policy_match")
                print("OK message=no_policy_match")
        except Exception as e:
            log_request(username, domain, f"json_policy_error:{str(e)}")
            print("ERR message=json_policy_error")

    except Exception as e:
        log_request(username if 'username' in locals() else "?", domain if 'domain' in locals() else "?", f"exception:{str(e)}")
        print("ERR message=internal_exception")
    finally:
        sys.stdout.flush()
