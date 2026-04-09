#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260413/tianji-party


### 1. squid.conf rollback


\cp -f $BACKUP_DIR/squid.conf /opt/squid/etc/squid.conf
chown squid.squid /opt/squid/etc/squid.conf

