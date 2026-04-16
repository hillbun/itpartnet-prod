upgrade steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260413/tianji-party
export UPGRADE_DIR=/home/logreader/upgrade/20260413/tianji-party

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. check status

cd /opt/nginx/html/transproxy_admin/public/fileData/threat
grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt

### 3. transproxy php upgrade

cp /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php $BACKUP_DIR/
cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php $BACKUP_DIR/

\cp -f $UPGRADE_DIR/transproxy/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
\cp -f $UPGRADE_DIR/transproxy/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php

### 4. verify

/usr/bin/python3 /opt/nginx/html/threat/IOCsApi_csv.py

cd /opt/nginx/html/transproxy_admin/public/fileData/threat
grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt


### 5. cleanup script

cp $UPGRADE_DIR/squid_shell/cleanup_iocs.sh /opt/squid_shell/cleanup_iocs.sh

### 6. cleanup crontab

### 0 23 * * * bash /usr/bin/python3 /opt/nginx/html/threat/IOCsApi_csv.py ....
### after add this crontab
### 30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh
###


/opt/squid/sbin/squid -k parse

------

rollback steps:

### 1. transproxy php

cp -af $BACKUP_DIR/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
cp -af $BACKUP_DIR/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php


### 2. verify

/usr/bin/python3 /usr/nginx/html/threat/IOCsApi_csv.py

cd /opt/nginx/html/transproxy_admin/public/fileData/threat
grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt



------

upgrade notes:

1. transproxy php file

transproxy/RemoveDup.php
transproxy/ThreatController.php

2. squid.conf

squid/squid.conf

---


13/04 23:00 DC6 squid log format update, threat intelligence update

poxappprd41a  192.168.52.236	DC6
poxappprd41b  192.168.52.238	DC6
poxappprd41c  192.168.52.97		DC6



15/04 23:00 DC7 squid log format update, threat intelligence update

poxappprd51a  192.168.152.101	DC7
poxappprd51b  192.168.152.102	DC7
poxappprd51c  192.168.152.103	DC7
poxappprd52a  192.168.152.120 	DC7 !!!!!!!!!!!!!!!!!
poxappprd52b  192.168.152.121 	DC7





------------

poxappprd42a  192.168.50.159 	DC6
poxappprd42b  192.168.50.160 	DC6
poxappprd42c  192.168.50.161	DC6
poxappprd42d  192.168.50.162 	DC6
poxappprd42e  192.168.50.163 	DC6
poxappprd52c  192.168.152.122	DC7
poxappprd52d  192.168.152.123 	DC7



poxappprd52e  192.168.152.124 	DC7
