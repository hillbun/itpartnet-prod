#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260508/conntrack

### 1. transproxy php

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf

sysctl -p
sysctl -w net.netfilter.nf_conntrack_max=262144

echo 65536 > /sys/module/nf_conntrack/parameters/hashsize
echo "options nf_conntrack hashsize=65536" > /etc/modprobe.d/nf_conntrack_hash.conf

