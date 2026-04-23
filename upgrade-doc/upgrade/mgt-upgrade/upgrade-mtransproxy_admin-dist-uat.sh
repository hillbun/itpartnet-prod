#!/bin/bash

### 0. export variable

export BACKUP_DIR=/app1/pox/home/poxadm/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/app1/pox/home/poxadm/upgrade/20260425/mgt-upgrade

### 1. backup mtransproxy_admin-dist

mkdir -p $BACKUP_DIR/mtransproxy_admin-dist

tar cvf $BACKUP_DIR/mtransproxy_admin-dist/dist.tar -C /usr/nginx/html/dist dist

### 2. upgrade mtransproxy_admin-dist

rm -rf /usr/nginx/html/dist

tar xvf $UPGRADE_DIR/mtransproxy_admin-dist/dist.tar -C /usr/nginx/html/

