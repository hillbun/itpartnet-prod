#!/usr/bin/env python3
"""
Kerberos配置生成脚本（精确匹配status="1"）
"""
import json
import os
import subprocess
import tempfile
import pwd
import grp
from jinja2 import Template

# 配置文件路径
JSON_FILE = "/usr/nginx/html/transproxy_admin/public/fileData/kbs_conn.json"
KRB5_TEMPLATE = "/etc/krb5.conf.j2"
KRB5_OUTPUT = "/etc/krb5.conf"
SQUID_TEMPLATE = "/opt/squid/etc/kerberos_auth.conf.j2"
SQUID_OUTPUT = "/opt/squid/etc/kerberos_auth.conf"

# 获取Squid用户信息
try:
    squid_user = pwd.getpwnam("squid")
    SQUID_UID = squid_user.pw_uid
    SQUID_GID = squid_user.pw_gid
except KeyError:
    SQUID_UID = 981
    SQUID_GID = 981

def write_atomic(content: str, target_file: str, chmod_mode: int = 0o600) -> None:
    """原子写入文件（防写入中断）"""
    target_dir = os.path.dirname(target_file)
    os.makedirs(target_dir, exist_ok=True, mode=0o755)
    with tempfile.NamedTemporaryFile(
        mode="w", dir=target_dir, delete=False, prefix=".tmp_", suffix=os.path.basename(target_file)
    ) as tmp:
        tmp.write(content)
        tmp.flush()
        os.fchmod(tmp.fileno(), chmod_mode)
    os.replace(tmp.name, target_file)

def main():
    try:
        # 1. 读取JSON并精确匹配status="1"
        with open(JSON_FILE) as f:
            records = json.load(f)
            active_record = None
            
            for record in records.values():
                # 关键逻辑：仅匹配字符串类型的"1" [1](@ref)
                status_val = record.get("status")
                if str(status_val).strip() == "1":
                    active_record = record
                    break  # 找到第一个即终止
            
            # 无有效记录时立即报错 [2](@ref)
            if not active_record:
                raise ValueError("❌ 配置错误：未找到status=1的有效记录")

        # 2. 准备模板渲染数据
        render_data = {
            'keytab_file': active_record["filepath"],
            'service_principal': active_record["servername"].split('@')[0],
            'realm': active_record["defaultrealm"],
            'kdc_server': active_record["adservername"],
            'domain': active_record["defaultrealm"].lower()  # realm转小写 [3](@ref)
        }

        # 3. 生成krb5.conf
        with open(KRB5_TEMPLATE) as tpl_file:
            tpl = Template(tpl_file.read())
        write_atomic(tpl.render(**render_data), KRB5_OUTPUT, 0o644)

        # 4. 生成Squid认证配置
        with open(SQUID_TEMPLATE) as tpl_file:
            tpl = Template(tpl_file.read())
        write_atomic(tpl.render(**render_data), SQUID_OUTPUT, 0o644)
        os.chown(SQUID_OUTPUT, SQUID_UID, SQUID_GID)  # 设置Squid用户权限

        # 5. 重载Squid服务
        subprocess.run(["sudo", "/opt/squid/sbin/squid", "-k", "parse"], check=True)
        subprocess.run(["sudo", "/opt/squid/sbin/squid", "-k", "reconfigure"], check=True)
        print("✅ Kerberos配置已生效")

    except Exception as e:
        print(f"❌ 执行失败：{str(e)}")
        raise SystemExit(1)

if __name__ == "__main__":
    main()
