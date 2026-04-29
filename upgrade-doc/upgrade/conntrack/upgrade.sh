#!/bin/bash

#### 0. export variable

export BACKUP_DIR=/root/backup/20260508/conntrack
export UPGRADE_DIR=/home/logreader/upgrade/20260508/conntrack

### 1. create backup dir

mkdir -p $BACKUP_DIR
cp /etc/sysctl.conf $BACKUP_DIR/sysctl.conf

### 2. sysctl.conf upgrade

\cp $UPGRADE_DIR/sysctl/sysctl.conf /etc/sysctl.conf

sysctl -p

echo 524288 > /sys/module/nf_conntrack/parameters/hashsize
echo "options nf_conntrack hashsize=524288" > /etc/modprobe.d/nf_conntrack_hash.conf

