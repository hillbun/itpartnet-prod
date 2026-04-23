#!/bin/bash

### 0. export variable

export BACKUP_DIR=/app1/pox/home/poxadm/backup/20260425/mgt-upgrade

### 1. rollback mtransproxy_admin-dist

rm -rf /usr/nginx/html/dist
tar xvf $BACKUP_DIR/mtransproxy_admin-dist/dist.tar -C /usr/nginx/html/

