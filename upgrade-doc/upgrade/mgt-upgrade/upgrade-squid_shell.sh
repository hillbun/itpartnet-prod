#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260425/mgt-upgrade
export UPGRADE_DIR=/opt/download/upgrade/20260425/mgt-upgrade


### 2.stat.sh upgrade

cp $UPGRADE_DIR/squid_shell/stat.sh /opt/squid_shell/


