upgrade steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/home/logreader/upgrade/20260425/mgt-upgrade

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


1. mtransproxy_admin

add files:
/usr/local/nginx/html/mtransproxy_admin/public/mangerTxt/connection_status.txt
/usr/local/nginx/html/mtransproxy_admin/app/Http/Controllers/Api/ConnectionstatusController.php

modify files:
/usr/local/nginx/html/mtransproxy_admin/routes/api.php

2. mtransproxy_admin

modify files:
/usr/local/nginx/html/dist

3. transproxy_admin

modify files:
/usr/local/nginx/html/transproxy_admin/routes/api.php
/usr/local/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php

tar cvf /home/logreader/backup/20260101/test/dist.tar -C /opt/nginx/html dist
tar xvf /home/logreader/backup/20260101/test/dist.tar -C /home/logreader/backup/20260101/test --transform='s/dist/new-dist/'