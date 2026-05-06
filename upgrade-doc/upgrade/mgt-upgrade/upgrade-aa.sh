#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260423/squid-timeout-tianji
export UPGRADE_DIR=/home/logreader/upgrade/20260423/squid-timeout-tianji

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. IndexController.php upgrade

cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php $BACKUP_DIR/
\cp -f $UPGRADE_DIR/transproxy/IndexController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chmod 664 /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php

