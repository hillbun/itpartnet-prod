upgrade steps:

#### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack
export UPGRADE_DIR=/home/logreader/upgrade/20260422/conntrack

### 1. create backup dir

mkdir -p $BACKUP_DIR
cp /etc/sysctl.conf $BACKUP_DIR/sysctl.conf

### 2. sysctl.conf upgrade

\cp $UPGRADE_DIR/sysctl/sysctl.conf /etc/sysctl.conf

sysctl -p

------
verify



------

rollback steps:


### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack

### 1. transproxy php

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf

sysctl -p



------
verify



------

upgrade notes:

1. /etc/sysctl.conf

add this line:

net.netfilter.nf_conntrack_max = 4194304





