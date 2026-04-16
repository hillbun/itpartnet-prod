生产环境变更申请清单：
1. 41a,41b,41c,51a,51b,51c(已完成：51c): 修复python_prod_logs日志文件logrotate执行失败问题

ls -al /etc/logrotate.d/python_prod_logs

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs

已完成：51c
失败：41a,41b,41c,51a,51b

2. 添加天际友盟清理定时任务（参考52a： 30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh）

30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh

chown logreader.logreader /opt/squid_shell/cleanup_iocs.sh

需检查：51a,51b,51c


3 /opt/squid_shell/resource_monitor_script.sh
需完成：41a,41b,41c,51a,51b,51c

4. squid cache.log 日志文件级别调整
需完成：所有

5. logrotage squid 取消reload script, 增加 crontab systemctl reload squid， 时间错开
需完成：41a,41b,41c,51a,51b,51c


6. 52a jobs  kill monitor

---






