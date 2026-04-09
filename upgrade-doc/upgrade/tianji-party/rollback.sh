#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260413/tianji-party

### 1. transproxy php rollback

\cp -f $BACKUP_DIR/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
\cp -f $BACKUP_DIR/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php

chmod 664 /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php
chmod 664 /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php


