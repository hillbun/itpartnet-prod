upgrade steps:

### 0. export variable

export $BACKUP_DIR=/root/backup/20260413/tianji-party
export $UPGRADE_DIR=/home/logreader/upgrade/20260413/tianji-party

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. check status

cd /opt/nginx/html/transproxy_admin/public/fileData/threat
grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt

### 3. transproxy php upgrade

cp /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php $BACKUP_DIR/
cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php $BACKUP_DIR/

cp -af $UPGRADE_DIR/transproxy/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
cp -af $UPGRADE_DIR/transproxy/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php

### 4. verify

/usr/bin/python3 /usr/nginx/html/threat/IOCsApi_csv.py

cd /opt/nginx/html/transproxy_admin/public/fileData/threat
grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt


### 5. cleanup crontab

cp $UPGRADE_DIR/squid_shell/cleanup_iocs.sh /opt/squid_shell/cleanup_iocs.sh

### 6. crontab

### 0 23 * * * bash /usr/bin/python3 /opt/nginx/html/threat/IOCsApi_csv.py ....
### add this crontab
### 30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh
###


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

root crontab
root jobs