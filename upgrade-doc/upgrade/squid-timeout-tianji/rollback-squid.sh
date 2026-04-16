#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260423/squid-timeout-tianji

### 1. squid.conf rollback

\cp -f $BACKUP_DIR/squid.conf /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse
