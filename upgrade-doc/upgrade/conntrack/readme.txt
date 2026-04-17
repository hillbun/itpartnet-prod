upgrade steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack
export UPGRADE_DIR=/home/logreader/upgrade/20260422/conntrack

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. sysctl.conf upgrade

\cp /etc/sysctl.conf $BACKUP_DIR/sysctl.conf
sysctl -p

------
verify



------

rollback steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack

### 1. transproxy php

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf


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




