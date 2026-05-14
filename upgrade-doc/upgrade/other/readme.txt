---

1. 52a, 52b, 52e

ls -l /opt/squid/var/log/

/etc/rsyslog.d/oproxy_log.conf

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

2. 52a, 52b, 52e

ls -al /opt/nginx/var/log/

crontab -l

cat /opt/crontab/opp-crontab

/root/logrotate-squid
/root/logrotate-python_prod_logs
/root/logrotate-nginx

3. 41a,41b,41c,51a,51b,51c

ls -l /opt/squid/var/log/
ls -l /opt/py_prod/log/

ls -al /etc/logrotate.d/python_prod_logs

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs

crontab -l |grep python_prod_logs


已完成：51c


crontab -l |grep cleanup_iocs.sh

ls -al /opt/nginx/html/threat/archive/

