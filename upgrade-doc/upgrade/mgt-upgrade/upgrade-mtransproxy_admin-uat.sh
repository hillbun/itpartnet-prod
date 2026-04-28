#!/bin/bash

### 0. export variable

export BACKUP_DIR=/app1/n5/software/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/app1/n5/software/upgrade/20260425/mgt-upgrade

### 1. backup mtransproxy_admin

mkdir -p $BACKUP_DIR
mkdir -p $BACKUP_DIR/mtransproxy_admin

cp /usr/nginx/html/mtransproxy_admin/routes/api.php $BACKUP_DIR/mtransproxy_admin/api.php

### 2. mtransproxy_admin upgrade

\cp $UPGRADE_DIR/mtransproxy_admin/api.php /usr/nginx/html/mtransproxy_admin/routes/api.php
cp $UPGRADE_DIR/mtransproxy_admin/ConnectionstatusController.php /app1/n5/nginx/html/mtransproxy_admin/app/Http/Controllers/Api/ConnectionstatusController.php

