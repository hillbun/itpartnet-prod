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


---
more /proc/net/nf_conntrack  |grep -v '127.0.0.1'
ipv4     2 tcp      6 431998 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=59564 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=59564 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431993 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=49040 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=49040 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431992 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=33340 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=33340 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431984 ESTABLISHED src=10.2.3.94 dst=10.2.3.162 sport=51579 dport=8080 src=10.2.3.162 dst=10.2.3.94 sport=8080 dport=51579 [ASSURED] mark=0 zone=0 use=2
ipv4     2 icmp     1 20 src=10.2.3.76 dst=10.2.3.162 type=8 code=0 id=7734 src=10.2.3.162 dst=10.2.3.76 type=0 code=0 id=7734 mark=0 zone=0 use=2
ipv4     2 tcp      6 431993 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=38248 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=38248 [ASSURED] mark=0 zone=0 use=2
ipv4     2 udp      17 5 src=0.0.0.0 dst=255.255.255.255 sport=68 dport=67 [UNREPLIED] src=255.255.255.255 dst=0.0.0.0 sport=67 dport=68 mark=0 zone=0 use=2
ipv4     2 tcp      6 431994 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=48622 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=48622 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431989 ESTABLISHED src=192.168.40.62 dst=10.2.3.162 sport=42048 dport=9100 src=10.2.3.162 dst=192.168.40.62 sport=9100 dport=42048 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431996 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=44856 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=44856 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 299 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=43746 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=43746 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431998 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=34766 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=34766 [ASSURED] mark=0 zone=0 use=2
ipv4     2 tcp      6 431995 ESTABLISHED src=192.168.1.189 dst=192.168.1.162 sport=51284 dport=22 src=192.168.1.162 dst=192.168.1.189 sport=22 dport=51284 [ASSURED] mark=0 zone=0 use=2
ipv4     2 udp      17 27 src=192.168.1.2 dst=255.255.255.255 sport=55330 dport=4680 [UNREPLIED] src=255.255.255.255 dst=192.168.1.2 sport=4680 dport=55330 mark=0 zone=0 use=2
ipv4     2 icmp     1 20 src=10.2.3.76 dst=10.2.3.162 type=8 code=0 id=7728 src=10.2.3.162 dst=10.2.3.76 type=0 code=0 id=7728 mark=0 zone=0 use=2
ipv4     2 tcp      6 431849 ESTABLISHED src=10.2.3.162 dst=74.125.204.188 sport=17228 dport=5228 src=74.125.204.188 dst=10.2.3.162 sport=5228 dport=17228 [ASSURED] mark=0 zone





字段含义（从左到右）：

字段	示例值	解释
协议族名	ipv4	IPv4
协议族数值	2	AF_INET 在内核中的宏定义值（2）
传输层协议名	tcp	TCP 协议
传输层协议号	6	TCP 的 IP 协议号（6）
剩余生存时间（秒）	431998	此条目再过这么多秒就会超时删除（如果无新数据包刷新）
连接状态	ESTABLISHED	TCP 连接状态（ESTABLISHED 表示已建立）
原始方向元组	src=192.168.1.189 dst=192.168.1.162 sport=59564 dport=22	发起方 IP/端口 → 目标 IP/端口
回复方向元组	src=192.168.1.162 dst=192.168.1.189 sport=22 dport=59564	预期的回复包方向（源/目的与原始方向互换）
标志	[ASSURED]	连接已双向通信（见下文）
防火墙标记	mark=0	未设置 netfilter 标记
zone	zone=0	conntrack zone，用于虚拟化隔离
引用计数	use=2	有 2 个地方正在使用此条目
