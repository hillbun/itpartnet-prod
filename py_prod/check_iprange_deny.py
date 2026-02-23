#!/usr/bin/env python3
import sys, redis, json, threading, datetime, os, ipaddress

LOG_DIR = "/opt/py_prod/log/"
os.makedirs(LOG_DIR, exist_ok=True)
LOG_FILE = f"{LOG_DIR}iprange_deny.log"
log_lock = threading.Lock()

def log_request(ip_range, domain, result, debug_info=""):
    timestamp = datetime.datetime.now().isoformat()
    message = f"[{timestamp}] [IPRANGE-DENY] range={ip_range} domain={domain} result={result} {debug_info}\n"
    with log_lock:
        with open(LOG_FILE, "a") as f:
            f.write(message)

redis_pool = redis.ConnectionPool(host='127.0.0.1', port=6379, password='StrongP@@ssw0rd123!', decode_responses=True)
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
    """匹配域名trie树"""
    parts = hostname.lower().rstrip('.').split('.')[::-1]
    node = trie
    path = []

    for seg in parts:
        if "__end__" in node:
            path.append("泛域名或部分匹配成功")
            return True, path
        if seg in node:
            node = node[seg]
            path.append(f"匹配段:{seg}")
        else:
            return False, path

    if "__end__" in node:
        path.append("完整域名匹配成功")
        return True, path
    return False, path

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
                return True, [f"IP {hostname} 在范围 {rule} 内"]
        return False, ["IP地址不在允许范围内"]
    
    # 处理域名
    trie = build_trie(allow_rules)
    return match_trie(trie, hostname)

if __name__ == '__main__':
    # 预加载所有IP范围键
    iprange_keys = [key for key in r.keys("acl:iprange:*")]
    
    for line in sys.stdin:
        try:
            line = line.strip()
            if not line or len(line.split()) < 2:
                print("ERR")
                continue
                
            src_ip, hostname, port = line.split()[:3]
            matched_range = None
            
            # 查找匹配的IP范围
            for key in iprange_keys:
                ip_range = key.split(':')[-1]
                if ip_in_range(src_ip, ip_range):
                    matched_range = ip_range
                    break
            #if matched_range:
            #    log_request("yes", f"{matched_range}", "matching_range_ip")
                    
            if not matched_range:
                log_request("N/A", f"{matched_range}", "no_matching_range")
                print("ERR")
                continue
                
            redis_key = f"acl:iprange:{matched_range}"
            rules_data = r.hget(redis_key, "serializedData")
            
            if not rules_data:
                log_request(matched_range, f"{hostname}", "no_rules")
                print("ERR")
                continue
                
            try:
                parsed = json.loads(rules_data)
                deny_rules = parsed.get("deny", [])
            except Exception as e:
                log_request(matched_range, f"{hostname}", f"json_error:{str(e)}")
                print("ERR")
                continue
                
            if not deny_rules:
                print("ERR")
                continue
                
            # trie = build_trie(deny_rules)
            # matched = match_trie(trie, hostname)
            matched, match_path = check_access(hostname, deny_rules)
            
            if matched:
                #debug_info = " | ".join(match_path)
                #log_request(matched_range, f"{hostname}", "deny_matched", debug_info)
                log_request(matched, f"{hostname}", "deny_matched")
                print("OK")
            else:
                #log_request(matched, f"hostname:{hostname}-----trie:{trie}", "no_matched")
                print("ERR")
                
        except Exception as e:
            log_request("?", "?", f"exception:{str(e)}")
            print("ERR")
        finally:
            sys.stdout.flush()
