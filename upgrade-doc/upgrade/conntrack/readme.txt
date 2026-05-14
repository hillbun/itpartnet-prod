https://hateams.ha.org.hk/workgroups/group/13446/disk/path/Open-Platform_Proxy_Solution/upgrade-procedure/conntrack/


---

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

sysctl net.netfilter.nf_conntrack_max
4194304
sysctl net.netfilter.nf_conntrack_count
?????

------

rollback steps:


### 0. export variable

export BACKUP_DIR=/root/backup/20260422/conntrack

### 1. sysctl.conf rollback

\cp $BACKUP_DIR/sysctl.conf /etc/sysctl.conf

sysctl -p
sysctl -w net.netfilter.nf_conntrack_max=262144



------
verify
	  
	  
1.sysctl net.netfilter.nf_conntrack_max 
2.sysctl net.netfilter.nf_conntrack_count
3.cat /sys/module/nf_conntrack/parameters/hashsize
4.dmesg -T | tail -n 1000
5.dmesg -T |grep "table full"
6.ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 ~ /:8080$/ {print $1}' | sort | uniq -c
7.ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 !~ /127.0.0.1/ && $4 !~ /:8080$/ && match($4, /:([0-9]+)$/, a) && a[1] > 1024 && $5 != "*:*" {print $1}' | sort | uniq -c
8.cat /proc/net/nf_conntrack | awk '{print $3}' | sort | uniq -c | sort -nr
9.grep "^ipv4 .* tcp" /proc/net/nf_conntrack | awk '{print $6}' | sort | uniq -c | sort -nr
10.cat /proc/net/nf_conntrack | grep -o 'src=[0-9.]*' | cut -d= -f2 | sort | uniq -c | sort -nr
11.cat /proc/net/nf_conntrack | grep -o 'dst=[0-9.]*' | cut -d= -f2 | sort | uniq -c | sort -nr
12.cat /proc/net/nf_conntrack | grep -v "127.0.0.1" | awk '{if($0 ~ /\[ASSURED\]/) a="[ASSURED]"; else a="[UN-ASSURED]"; print $3, $4, $5, a}' | sort | uniq -c | sort -nr

13.awk -F'[][]' '$2 <= "30/Apr/2026:11:00:00"' /opt/squid/var/log/access.log | awk '{print $9}' | sort | uniq -c
14.awk -F'[][]' '$2 >= "30/Apr/2026:11:00:00"' /opt/squid/var/log/access.log | awk '{print $9}' | sort | uniq -c
15.awk '{print $9}' /opt/squid/var/log/access.log | sort | uniq -c | sort -nr



sysctl net.netfilter.nf_conntrack_tcp_timeout_established

------

upgrade notes:

1. /etc/sysctl.conf

add this line:

net.netfilter.nf_conntrack_max = 4194304





