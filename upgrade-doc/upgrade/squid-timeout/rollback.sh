#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260409/squid-timeout

### 1. squid config update

\cp -f $BACKUP_DIR/squid.conf /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse

### 2. logreader crontab update

\cp -f $BACKUP_DIR/opp-crontab /opt/crontab/opp-crontab
crontab -u logreader /opt/crontab/opp-crontab
crontab -u logreader -l

### 3. node_exporter config update

cp -f $BACKUP_DIR/node_exporter.service /etc/systemd/system/node_exporter.service
systemctl daemon-reload
systemctl restart node_exporter

rm -rf /opt/node_exporter
