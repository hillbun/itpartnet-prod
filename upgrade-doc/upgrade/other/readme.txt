生产环境变更申请清单：
1. 41a,41b,41c,51a,51b,51c(已完成：51c): 修复python_prod_logs日志文件logrotate执行失败问题

51c

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs


2. 41a,41b,41c,51a,51b,51c: 添加天际友盟清理定时任务（参考52a： 30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh）

30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh

chown logreader.logreader /opt/squid_shell/cleanup_iocs.sh


3. 52a,52b,52e： 删除/etc/logrotate.d/squid, /etc/logrotate.d/nginx, /etc/logrotate.d/python_prod_logs 重复冲突配置项

rm -rf /etc/logrotate.d/squid
rm -rf /etc/logrotate.d/nginx
rm -rf /etc/logrotate.d/python_prod_logs

4. 41a,41b,41c,51a,51b,51c,52a,52b,52e squid cache.log 日志文件级别调整

5. node_exporter调整 (已完成：51c)


41a 天际友盟归档文件清理脚本定时任务已更新