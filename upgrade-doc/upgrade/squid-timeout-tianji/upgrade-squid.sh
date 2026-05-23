#!/bin/bash

### 0. export variable

export BACKUP_DIR=/opt/download/backup/squid-timeout-tianji
export UPGRADE_DIR=/opt/download/itp-upgrade/squid-timeout-tianji

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. squid.conf upgrade

cp /opt/squid/etc/squid.conf $BACKUP_DIR/
\cp -f $UPGRADE_DIR/squid/squid.conf /opt/squid/etc/squid.conf
chown squid.squid /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse

