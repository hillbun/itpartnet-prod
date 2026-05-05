生产环境变更申请清单：
1. 41a,41b,41c,51a,51b,51c(已完成：51c): 修复python_prod_logs日志文件logrotate执行失败问题

ls -al /etc/logrotate.d/python_prod_logs

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs

已完成：51c
失败：41a,41b,41c,51a,51b

2. /opt/squid_shell/resource_monitor_script.sh
需完成：41a,41b,41c,51a,51b,51c

3. squid cache.log 日志文件级别调整
需完成：所有

4. logrotage squid 取消reload script, 增加 crontab systemctl reload squid， 时间错开
需完成：41a,41b,41c,51a,51b,51c

5.


6. python_prod_logs logrotate crontab file confirm
-f /root/python_prod_logs
52a, 52b, 52e


7. 52a jobs  kill monitor 取消监控脚本
需完成：51a, 52a, 52e


8. 上线时间和公网IP确认
poxappprd52c
poxappprd52d


---
/etc/rsyslog.d/oprox_log.conf


if $programname == 'rsyslogd-pstats' then {
    action(type="omfile"
    file="/opt/squid/var/log/rsyslog-stats.log"
    fileOwner="squid"
    fileGroup="squid"
    )
	
systemctl restart rsyslog


more /opt/logrotate/etc/squid

/opt/squid/var/log/access.log  
/opt/squid/var/log/cache.log  
/opt/squid/var/log/access-tianji-blacklist.log  
/opt/squid/var/log/rsyslog-stats.log {  
    su squid squid  
    daily  
    dateext  
    dateformat -%Y%m%d  
    rotate 7  
    compress  
    copytruncate  
    missingok  
    ifempty  
    nodelaycompress  
}


----
poxappprd52c绑定变更（proxyxyvmcprd51a（192.168.153.195）-> poxappprd52c（192.168.152.122）					2026年5月份会议测试（公网：42.200.29.213）
poxappprd52d绑定变更（proxy11（192.168.150.230）-> poxappprd52d（192.168.152.123）					直接切换IP，无需查LOG通知（跟daniel确认下是不是可以直接切？）（公网：42.200.29.223）


