upgrade steps:

### 0. export variable

export $BACKUP_DIR=/root/backup/20260323/squid-timeout
export $UPGRADE_DIR=/home/logreader/upgrade/squid-timeout

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. node_exporter config update


------


rollback steps:

### 1. squid config update

cp -f $BACKUP_DIR/squid.conf /opt/squid/etc/squid.conf
/opt/squid/sbin/squid -k parse


### 2. logreader crontab update

cp -f $BACKUP_DIR/opp-crontab /opt/crontab/opp-crontab
crontab -u logreader /opt/crontab/opp-crontab
crontab -u logreader -l

### 3. node_exporter config update

cp -f $BACKUP_DIR/node_exporter.service /etc/systemd/system/node_exporter.service
systemctl restart node_exporter

rm -rf /opt/node_exporter


------


upgrade notes:

1. 