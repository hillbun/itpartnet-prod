生产环境变更申请清单：
1. 41a,41b,41c,51a,51b,51c(已完成：51c): 修复python_prod_logs日志文件logrotate执行失败问题

ls -al /etc/logrotate.d/python_prod_logs

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs

已完成：51c 

2. 添加天际友盟清理定时任务（参考52a： 30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh）

30 23 * * * bash /opt/squid_shell/cleanup_iocs.sh

chown logreader.logreader /opt/squid_shell/cleanup_iocs.sh

需完成：41a,41b,41c,51a,51b,51c: 
已完成：41a

3. 删除/etc/logrotate.d/squid, /etc/logrotate.d/nginx, /etc/logrotate.d/python_prod_logs 重复冲突配置项

ls -l /etc/logrotate.d/squid /etc/logrotate.d/nginx /etc/logrotate.d/python_prod_logs

rm -rf /etc/logrotate.d/squid
rm -rf /etc/logrotate.d/nginx
rm -rf /etc/logrotate.d/python_prod_logs

需完成：52a,52b,52e

4. 41a,41b,41c,51a,51b,51c,52a,52b,52e squid cache.log 日志文件级别调整

5. node_exporter调整

已完成：51c

6. 52a jobs  kill monitor