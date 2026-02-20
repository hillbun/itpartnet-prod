#!/usr/bin/env python3
"""
读取web保存的JSON，渲染Jinja2模板，安全写入密码文件，
验证配置，灰度reload
"""
import json
import os
import stat
import subprocess
import tempfile
import pwd
import grp
from jinja2 import Template

JSON_FILE = "/usr/nginx/html/transproxy_admin/public/fileData/ladps_conn.json"
TEMPLATE = "/opt/squid/etc/ldap_auth.conf.j2"
OUTPUT_SNIP = "/opt/squid/etc/ldap_auth.conf"
PW_FILE = "/opt/squid/etc/ldap_password"

# 动态获取Squid用户和组的UID/GID
try:
    squid_user = pwd.getpwnam("squid")
    SQUID_UID = squid_user.pw_uid
    SQUID_GID = squid_user.pw_gid
except KeyError:
    # 如果"squid"用户不存在，回退到默认值
    SQUID_UID = 981
    SQUID_GID = 981

def write_atomic(content: str, target_file: str, chmod_mode: int = 0o600) -> None:
    """原子写入文件，避免跨设备问题"""
    target_dir = os.path.dirname(target_file)
    # 确保目标目录存在
    os.makedirs(target_dir, exist_ok=True, mode=0o755)
    
    # 在目标目录创建临时文件
    with tempfile.NamedTemporaryFile(
        mode="w",
        dir=target_dir,
        delete=False,
        prefix=".tmp_",
        suffix=os.path.basename(target_file)
    ) as tmp:
        tmp.write(content)
        tmp.flush()
        # 设置权限
        os.fchmod(tmp.fileno(), chmod_mode)
    
    try:
        # 跨设备安全替换
        os.replace(tmp.name, target_file)
    except OSError as e:
        # 替换失败时清理临时文件
        os.unlink(tmp.name)
        raise e

def main():
    try:
        # 1. 读取JSON配置
        with open(JSON_FILE) as f:
            json_data = json.load(f)
            active_record = None
            
            # 查找状态为"1"的有效记录
            for record in json_data.values():
                status_val = record.get("status")
                if str(status_val).strip() == "1":
                    active_record = record
                    break
            
            if not active_record:
                raise ValueError("❌ 未找到status=1的有效记录")
        
        # 根据servertype设置过滤属性[1,8](@ref)
        if active_record["servertype"] == "3":
            filter_attr = "sAMAccountName=%s"
        elif active_record["servertype"] == "4":
            filter_attr = "userPrincipalName=%s"
        else:
            raise ValueError(f"❌ 不支持的servertype值: {active_record['servertype']}")
        
        # 添加到模板渲染数据中
        active_record["filter_attr"] = filter_attr

        # 2. 写入密码文件（原子操作）
        write_atomic(active_record["password"] + "\n", PW_FILE)
        # 修改密码文件的所有者为squid
        os.chown(PW_FILE, SQUID_UID, SQUID_GID)

        # 3. 渲染模板
        with open(TEMPLATE) as tpl_file:
            tpl = Template(tpl_file.read())
        rendered = tpl.render(**active_record)
        
        # 4. 写入配置文件（原子操作）
        write_atomic(rendered, OUTPUT_SNIP, chmod_mode=0o644)
        # 修改配置文件的所有者为squid
        os.chown(OUTPUT_SNIP, SQUID_UID, SQUID_GID)

        # 5. 语法检查 & 灰度重载[7](@ref)
        subprocess.run(["sudo", "/opt/squid/sbin/squid", "-k", "parse"], check=True)
        subprocess.run(["sudo", "/opt/squid/sbin/squid", "-k", "reconfigure"], check=True)

        print("✅ 配置更新成功！")

    except Exception as e:
        print(f"❌ 错误发生: {str(e)}")
        # 建议用户检查日志
        print("请检查Squid日志: /var/log/squid/cache.log")
        raise

if __name__ == "__main__":
    main()
