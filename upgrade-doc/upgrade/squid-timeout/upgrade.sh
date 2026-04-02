#!/bin/bash

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

\cp -f $UPGRADE_DIR/node_exporter/node_exporter.service /etc/systemd/system/node_exporter.service
systemctl daemon-reload
systemctl restart node_exporter

### 3. logreader crontab update

cp /opt/crontab/opp-crontab $BACKUP_DIR/opp-crontab

echo "" >> /opt/crontab/opp-crontab
echo "* * * * * /opt/node_exporter/script/custom_tcp_connection_outside_to_proxy.sh" >> /opt/crontab/opp-crontab
echo "* * * * * /opt/node_exporter/script/custom_tcp_connection_proxy_to_outside.sh" >> /opt/crontab/opp-crontab
echo "* * * * * /opt/node_exporter/script/custom_outside_to_proxy_by_ip.sh" >> /opt/crontab/opp-crontab
echo "* * * * * /opt/node_exporter/script/custom_proxy_to_outside_by_ip.sh" >> /opt/crontab/opp-crontab
echo "* * * * * /opt/node_exporter/script/custom_number_py_prod.sh" >> /opt/crontab/opp-crontab

crontab -u logreader /opt/crontab/opp-crontab
