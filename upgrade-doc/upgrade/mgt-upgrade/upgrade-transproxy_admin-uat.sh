#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/root/upgrade/20260425/mgt-upgrade

### 1. create backup dir

mkdir -p $BACKUP_DIR
mkdir -p $BACKUP_DIR/transproxy_admin

cp /opt/nginx/html/transproxy_admin/routes/api.php $BACKUP_DIR/transproxy_admin/api.php
cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php $BACKUP_DIR/transproxy_admin/


/usr/local/nginx/html/transproxy_admin/routes/api.php
/usr/local/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php

### 2. transproxy_admin upgrade

cp $UPGRADE_DIR/transproxy_admin/api.php /opt/nginx/html/transproxy_admin/routes/api.php
cp $UPGRADE_DIR/transproxy_admin/IndexController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php


#####
##### curl -k https://127.0.0.1:8000/api/version_list
##### curl -k https://127.0.0.1:8000/api/get_connection_status
#####


