#!/bin/bash

### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack

### 1. transproxy php

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf

sysctl -p

