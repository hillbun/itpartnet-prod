生产环境变更申请清单：
1. 41a,41b,41c,51a,51b,51c(已完成：51c): 修复python_prod_logs日志文件logrotate执行失败问题

ls -al /etc/logrotate.d/python_prod_logs

-rw-r----- root root /etc/logrotate.d/python_prod_logs

chmod 640 /etc/logrotate.d/python_prod_logs

已完成：51c
失败：41a,41b,41c,51a,51b

3 /opt/squid_shell/resource_monitor_script.sh
需完成：41a,41b,41c,51a,51b,51c

4. squid cache.log 日志文件级别调整
需完成：所有

5. logrotage squid 取消reload script, 增加 crontab systemctl reload squid， 时间错开
需完成：41a,41b,41c,51a,51b,51c


6. 52a jobs  kill monitor
需完成：52a


ps -ef --forest | grep -B 5 sleep

nohup bash -c 'while true; do timestamp=$(date "+%Y-%m-%d %H:%M:%S"); result=$(curl -s -o /dev/null -w "%{http_code} %{time_total} " --proxy http://n5-testing:P%40ssw0rd1234@192.168.50.163:8080 --connect-timeout 1 --max-time 3 https://www.hk01.com/ 2>/dev/null || echo "FAIL"); echo "$timestamp $result" >> /tmp/42e-curl.txt; sleep 1; done' > /dev/null 2>&1 &
ps -ef | grep "while true"





