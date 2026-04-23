#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/home/logreader/upgrade/20260425/mgt-upgrade

### 1. backup mtransproxy_dist

mkdir -p $BACKUP_DIR
tar cvf /home/logreader/backup/20260101/test/dist.tar -C /opt/nginx/html dist

### 2. upgrade mtransproxy_dist



