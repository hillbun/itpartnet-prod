#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260410/resource-monitor
export UPGRADE_DIR=/home/logreader/upgrade/20260410/resource-monitor

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. node_exporter config update

cp /opt/squid_shell/resource_monitor_script.sh $BACKUP_DIR/resource_monitor_script.sh

\cp -f $UPGRADE_DIR/squid_shell/resource_monitor_script.sh /opt/squid_shell/resource_monitor_script.sh
chown -R squid.squid /opt/squid_shell/resource_monitor_script.sh

