#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260423/squid-timeout-tianji

### 1. IndexController.php rollback

\cp -f $BACKUP_DIR/IndexController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chmod 664 /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php

