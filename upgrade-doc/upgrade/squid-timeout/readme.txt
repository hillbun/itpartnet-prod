upgrade steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260409/squid-timeout
export UPGRADE_DIR=/home/logreader/upgrade/20260409/squid-timeout

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. node_exporter config update

mkdir -p /opt/node_exporter
mkdir -p /opt/node_exporter/textfile_collector
cp -r $UPGRADE_DIR/node_exporter/script /opt/node_exporter/
chown -R logreader.logreader /opt/node_exporter

cp /etc/systemd/system/node_exporter.service $BACKUP_DIR/node_exporter.service

cp -f $UPGRADE_DIR/node_exporter/node_exporter.service /etc/systemd/system/node_exporter.service
systemctl restart node_exporter

### 3. logreader crontab update

cp /opt/crontab/opp-crontab $BACKUP_DIR/opp-crontab
cp -f $UPGRADE_DIR/crontab/opp-crontab /opt/crontab/opp-crontab
crontab -u logreader /opt/crontab/opp-crontab
crontab -u logreader -l

### 4. squid config update

cp /opt/squid/etc/squid.conf $BACKUP_DIR/squid.conf
cp $UPGRADE_DIR/squid/squid.conf /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse


------
verify monitor dashboard
wait at 00:00 crontab run systemctl reload squid
verify timeout status

tail -f /tmp/51a-1-curl.txt
tail -f /tmp/51a-1-ps.txt
------


rollback steps:

### 1. squid config update

cp -f $BACKUP_DIR/squid.conf /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse


### 2. logreader crontab update

cp -f $BACKUP_DIR/opp-crontab /opt/crontab/opp-crontab
crontab -u logreader /opt/crontab/opp-crontab
crontab -u logreader -l

### 3. node_exporter config update

cp -f $BACKUP_DIR/node_exporter.service /etc/systemd/system/node_exporter.service
systemctl restart node_exporter

rm -rf /opt/node_exporter


------


upgrade notes:


1. /opt/node_exporter/script

node_exporter scripts

2. /etc/systemd/system/node_exporter.service

old:

ExecStart=/usr/local/node_exporter/node_exporter --web.listen-address=:9100

new:
ExecStart=/usr/local/node_exporter/node_exporter --web.listen-address=:9100 --collector.textfile.directory=/opt/node_exporter/textfile_collector


3. /opt/squid/etc/squid.conf

old:
external_acl_type check_ip_allow_check ttl=604800 negative_ttl=360 children-max=100 %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_allow.py
external_acl_type check_ip_deny_check  ttl=604800 negative_ttl=360 children-max=100 %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_deny.py
external_acl_type check_iprange_allow_check  ttl=604800 negative_ttl=360 children-max=100 %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_allow.py
external_acl_type check_iprange_deny_check   ttl=604800 negative_ttl=360 children-max=100 %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_deny.py
external_acl_type ua_dump ttl=604800 negative_ttl=360 children-max=100 %DST %{User-Agent}>h /usr/bin/python3 /opt/py_prod/check_ua.py
external_acl_type special_user_allow_check  ttl=604800 negative_ttl=360 children-max=100 %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_allow.py
external_acl_type special_user_deny_check  ttl=604800 negative_ttl=360 children-max=100 %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_deny.py
external_acl_type check_user ttl=604800 negative_ttl=360 children-max=100 %LOGIN %URI /usr/bin/python3 /opt/py_prod/check_user.py

new:
external_acl_type check_ip_allow_check ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_allow.py
external_acl_type check_ip_deny_check  ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_deny.py
external_acl_type check_iprange_allow_check  ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_allow.py
external_acl_type check_iprange_deny_check   ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_deny.py
external_acl_type ua_dump ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %DST %{User-Agent}>h /usr/bin/python3 /opt/py_prod/check_ua.py
external_acl_type special_user_allow_check  ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_allow.py
external_acl_type special_user_deny_check  ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_deny.py
external_acl_type check_user ttl=604800 negative_ttl=360 children-startup=2 children-idle=2 %LOGIN %URI /usr/bin/python3 /opt/py_prod/check_user.py



52a, 52b, 52c
root crontab
root jobs
