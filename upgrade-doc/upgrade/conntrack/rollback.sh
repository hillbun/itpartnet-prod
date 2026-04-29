#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260508/conntrack

### 1. transproxy php

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf

sysctl -p
sysctl -w net.netfilter.nf_conntrack_max=262144

