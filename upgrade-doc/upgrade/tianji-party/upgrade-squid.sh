#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260413/tianji-party
export UPGRADE_DIR=/home/logreader/upgrade/20260413/tianji-party

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. squid.conf

cp /opt/squid/etc/squid.conf $BACKUP_DIR/
\cp -f $UPGRADE_DIR/squid/squid.conf /opt/squid/etc/squid.conf
chown squid.squid /opt/squid/etc/squid.conf
