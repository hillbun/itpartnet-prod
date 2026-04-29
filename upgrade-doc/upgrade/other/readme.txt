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

6. python_prod_logs logrotate crontab confirm
-f /root/xxxxxx
52a, 52b, 52e

7. 52a jobs  kill monitor
需完成：52a


ps -ef --forest | grep -B 5 sleep

nohup bash -c 'while true; do timestamp=$(date "+%Y-%m-%d %H:%M:%S"); result=$(curl -s -o /dev/null -w "%{http_code} %{time_total} " --proxy http://n5-testing:P%40ssw0rd1234@192.168.50.163:8080 --connect-timeout 1 --max-time 3 https://www.hk01.com/ 2>/dev/null || echo "FAIL"); echo "$timestamp $result" >> /tmp/42e-curl.txt; sleep 1; done' > /dev/null 2>&1 &

ps -ef | grep "while true"

4194304

Dear Leo,
 
Please set email alert and highlighted in color for below trigger. Thank you.
 
No. of user connection:
Red > 53000, Orange > 50000, Yellow > 47000
 
Conntrack:

4194304
Red => net.netfilter.nf_conntrack_count > 3774873 (90%)
Orange => net.netfilter.nf_conntrack_count > 3565158 (85%)
Yellow => net.netfilter.nf_conntrack_count > 3355443 (80%) 


262144
Red => net.netfilter.nf_conntrack_count > 235930 (90%)
Orange => net.netfilter.nf_conntrack_count > 222822 (85%)
Yellow => net.netfilter.nf_conntrack_count > 209715 (80%) 

 
CPU, RAM, Disk Usage: Red > 90%, Orange >85%, Yellow > 80%
Bandwidth: Red > 900Mb/s, Orange > 800Mb/s, yellow > 700Mb/s
Connections: Red > 53000, Orange > 50000, Yellow > 47000
Conntrack: Red > 3774873, Orange > 3565158, Yellow > 3355443
 
Server Status:
Down => Red
 
Disk Usage:
Red > 90%, Orange >85%, Yellow > 80%



rgb(224, 47, 68)    rgb(255, 179, 87) rgb(255, 238, 82)

CPU, RAM, Disk Usage: Red > 90%, Orange >85%, Yellow > 80%<br />
Bandwidth: Red > 900Mb/s, Orange > 800Mb/s, yellow > 700Mb/s<br />
Connections: Red > 53000, Orange > 50000, Yellow > 47000<br />
Conntrack: Red > 3774873, Orange > 3565158, Yellow > 3355443



<div style="display: flex; gap: 10px;">
  <div style="background: rgb(224, 47, 68); width: 100px; height: 50px; display: flex; align-items: center; justify-content: center; color: white;">
    > 90%
  </div>
  <div style="background: rgb(255, 179, 87); width: 100px; height: 50px; display: flex; align-items: center; justify-content: center;">
    > 85%
  </div>
  <div style="background: rgb(255, 238, 82); width: 100px; height: 50px; display: flex; align-items: center; justify-content: center;">
    > 80%
  </div>
</div>