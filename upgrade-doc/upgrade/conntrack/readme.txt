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

before upgrade

sysctl net.netfilter.nf_conntrack_max
262144
sysctl net.netfilter.nf_conntrack_count
?????

cat /sys/module/nf_conntrack/parameters/hashsize
65536

----
after upgrade

----

awk '{print $9}' /opt/squid/var/log/access.log | sort | uniq -c
     52 0
   3243 200
    331 206
      3 302
    112 304
     17 401
   2788 403
    101 407
      3 500
    190 503


awk -F'[][]' '$2 >= "24/Apr/2026:03:27:00" && $2 <= "24/Apr/2026:03:32:00"' /opt/squid/var/log/access.log

10.2.3.185 - - [24/Apr/2026:03:27:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63357 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:27:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4883 63359 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:27:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.203 - - [24/Apr/2026:03:27:01 +0800] "CONNECT clientservices.googleapis.com:443 HTTP/1.1" 200 3087 61691 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.203 - - [24/Apr/2026:03:27:04 +0800] "CONNECT main.vscode-cdn.net:443 HTTP/1.1" 200 79513 61690 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.95 - - [24/Apr/2026:03:27:04 +0800] "CONNECT push.services.mozilla.com:443 HTTP/1.1" 200 900 50347 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.95 - - [24/Apr/2026:03:27:11 +0800] "CONNECT push.services.mozilla.com:443 HTTP/1.1" 200 39 57765 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0" TCP_TUNNEL:HIER_DIRECT 200
10.2.12.59 - - [24/Apr/2026:03:27:25 +0800] "GET http://c.pki.goog/r/gsr1.crl HTTP/1.1" 403 3952 60592 "-" "Microsoft-CryptoAPI/10.0" TCP_DENIED:HIER_NONE 403
10.2.12.59 - - [24/Apr/2026:03:27:25 +0800] "GET http://c.pki.goog/r/r4.crl HTTP/1.1" 403 3946 60593 "-" "Microsoft-CryptoAPI/10.0" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:28:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:28:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63357 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:28:01 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4883 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:29:02 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:29:02 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:29:02 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4883 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.12.59 - - [24/Apr/2026:03:29:34 +0800] "CONNECT main.vscode-cdn.net:443 HTTP/1.1" 200 79512 49520 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.115.0 Chrome/142.0.7444.265 Electron/39.8.5 Safari/537.36" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.95 - - [24/Apr/2026:03:29:35 +0800] "CONNECT mtalk.google.com:5228 HTTP/1.1" 200 7105 57763 "-" "-" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.94 - - [24/Apr/2026:03:29:36 +0800] "CONNECT mtalk.google.com:5228 HTTP/1.1" 200 7105 50786 "-" "-" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.95 - - [24/Apr/2026:03:29:52 +0800] "CONNECT push.services.mozilla.com:443 HTTP/1.1" 200 4119 57766 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.95 - - [24/Apr/2026:03:30:01 +0800] "CONNECT push.services.mozilla.com:443 HTTP/1.1" 200 978 57764 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.185 - - [24/Apr/2026:03:30:03 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:30:03 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4883 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:30:03 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63367 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.12.59 - - [24/Apr/2026:03:30:14 +0800] "CONNECT update.code.visualstudio.com:443 HTTP/1.1" 200 10374 65142 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.115.0 Chrome/142.0.7444.265 Electron/39.8.5 Safari/537.36" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.203 - - [24/Apr/2026:03:30:55 +0800] "CONNECT safebrowsing.googleapis.com:443 HTTP/1.1" 200 7720 61693 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36" TCP_TUNNEL:HIER_DIRECT 200
10.2.3.185 - - [24/Apr/2026:03:31:04 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63358 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:31:04 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4882 63367 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.3.185 - - [24/Apr/2026:03:31:04 +0800] "POST http://10.2.3.110:5601/api/console/proxy? HTTP/1.1" 403 4883 63367 "http://10.2.3.110:5601/app/dev_tools" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36" TCP_DENIED:HIER_NONE 403
10.2.12.59 - - [24/Apr/2026:03:31:22 +0800] "CONNECT default.exp-tas.com:443 HTTP/1.1" 403 3979 56164 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.115.0 Chrome/142.0.7444.265 Electron/39.8.5 Safari/537.36" TCP_DENIED:HIER_NONE 403

awk -F'[][]' '$2 >= "24/Apr/2026:03:27:00" && $2 <= "24/Apr/2026:03:32:00"' /opt/squid/var/log/access.log | awk '{print $9}' | sort | uniq -c
     11 200
     18 403
	 

awk -F'[][]' '$2 >= "24/Apr/2026:03:27:00"' /opt/squid/var/log/access.log | awk '{print $9}' | sort | uniq -c


awk -F'[][]' '$2 <= "24/Apr/2026:03:32:00"' /opt/squid/var/log/access.log | awk '{print $9}' | sort | uniq -c


awk -F'[][]' '$2 >= "24/Apr/2026:04:27:00" && $2 <= "24/Apr/2026:04:32:00"' /opt/squid/var/log/access.log |wc -l
awk -F'[][]' '$2 >= "24/Apr/2026:03:27:00" && $2 <= "24/Apr/2026:03:32:00" {print $9}' /opt/squid/var/log/access.log | sort | uniq -c

------

upgrade notes:

1. /etc/sysctl.conf

add this line:

net.netfilter.nf_conntrack_max = 4194304





