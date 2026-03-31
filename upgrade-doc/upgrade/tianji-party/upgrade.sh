#!/bin/bash

export $BACKUP_DIR=/root/backup/20260413/tianji-party
export $UPGRADE_DIR=/home/logreader/upgrade/20260413/tianji-party

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 3. transproxy php upgrade

cp /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php $BACKUP_DIR/
cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php $BACKUP_DIR/

cp -af $UPGRADE_DIR/transproxy/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
cp -af $UPGRADE_DIR/transproxy/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php
