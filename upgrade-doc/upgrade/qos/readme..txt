#1.1####################################################################################################################################################
#全局放行端口设置
#acl SSL_ports port 443
#acl Safe_ports port 80          # http
#acl Safe_ports port 443         # https
#acl Safe_ports port 563
#acl Safe_ports port 1025-65535  # unregistered ports
include /opt/squid/etc/global_ssl_port.inc
include /opt/squid/etc/global_port.inc
acl CONNECT method CONNECT
http_access deny !Safe_ports
http_access deny CONNECT !SSL_ports

# QOS POLOCY
delay_pools 1

###  file 读取测试 
acl qos_work_hours time MTWHF  9:00-18:00

acl qos_src_2 src "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_src_2.txt"
acl qos_dstdomain_2 dstdomain "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_dstdomain_2.txt"

delay_class 1 2
#delay_parameters 1 1310720/1310720 655360/655360
###  file 读取测试
delay_parameters 1 983040/983040 655360/655360

###1,2,3,....n
delay_access 1 allow qos_src_2 qos_dstdomain_2 qos_work_hours
delay_access 1 deny all


#free url
#1.1 目的ip地址白名单
acl dst_ip_alone dst "/usr/nginx/html/transproxy_admin/public/fileData/dst_ip_alone.txt"
http_access allow dst_ip_alone


#1.2 目的域名白名单
acl free-domain-white dstdomain "/usr/nginx/html/transproxy_admin/public/fileData/url-set.txt"
http_access allow free-domain-white

#1.3 DST Domain port white
#include /opt/squid/etc/allow_freedomain.inc


#来自于51a配置（参照那边写死加上去的）
acl allowed_2 dst  23.89.0.0/16 62.109.192.0/18 64.68.96.0/19 66.114.160.0/20 66.163.32.0/19 69.26.160.0/19 114.29.192.0/19 150.253.128.0/17 170.72.0.0/16 170.133.128.0/18

---------

5. 匹配规则
/usr/local/nginx/html/transproxy_admin/public/fileData/qos_rule.inc
delay_access 1 allow qos_src qos_dstdomain qos_work_hours


-----------







OPP QOS可配置项

在指定时间(3)对源地址IP(1)访问目标域名(2)的流量带宽限速(4)。

1. 源地址IP
/usr/local/nginx/html/transproxy_admin/public/fileData/qos_src.txt
10.2.3.207

10.2.3.208/30

2. 目标域名
/usr/local/nginx/html/transproxy_admin/public/fileData/qos_dstdomain.txt
*.koddos.net
mirror.01link.hk

3. 时间
/usr/local/nginx/html/transproxy_admin/public/fileData/qos.inc
acl qos_work_hours time MTWHF 9:00-18:00

周一至周日星期代码
MTWHFSU

4. 速率
/usr/local/nginx/html/transproxy_admin/public/fileData/qos.inc
delay_parameters 1 983040/983040 655360/655360

总带宽（Byte/s）983040     
单连接带宽（Byte/s）655360



acl qos_src src "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_src.txt"
acl qos_dstdomain dstdomain "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_dstdomain.txt"

delay_pools 1

include /usr/local/nginx/html/transproxy_admin/public/fileData/qos.inc
delay_access 1 allow qos_src qos_dstdomain qos_work_hours
delay_access 1 deny all


/usr/local/nginx/html/transproxy_admin/public/fileData/qos.inc
acl qos_work_hours time MTWHF 9:00-18:00
delay_parameters 1 983040/983040 655360/655360



---

/usr/nginx/html/transproxy_admin/public/fileData/url-set.txt
.koddos.net
mirror.01link.hk
mirror.freedif.org

curl -x http://10.2.3.162:8080 https://mirror-hk.koddos.net/ubuntu-releases/24.04/ubuntu-24.04.4-live-server-amd64.iso --output ubuntu-24.04.4-live-server-amd64.iso
curl -x http://10.2.3.162:8080 https://mirror.01link.hk/ubuntu-releases/focal/ubuntu-20.04.6-live-server-amd64.iso --output ubuntu-20.04.6-live-server-amd64.iso
curl -x http://10.2.3.162:8080 https://mirror.freedif.org/ubuntu-releases/26.04/ubuntu-26.04-live-server-amd64.iso --output ubuntu-26.04-live-server-amd64.iso

curl -x http://10.2.3.162:8080 https://www.baidu.com/


81.2 MB/s


curl -x http://10.2.3.162:8080 ftp://ftp.gnu.org


Bytes/s


1000 Mb/s
1000/8 MB/s






以 1000000/1000000 （单个 IP）为例：

前一个数字（1000000 字节/秒）- 恢复速率 (Restore Rate)：
这是长期的稳定限速值。当水桶里的“积蓄”用完后，Squid 每秒只往桶里放 1,000,000 字节的数据。也就是说，单个 IP 的长期持续下载速度被限制在约 1 MB/s（约 8 Mbps）。

后一个数字（1000000 字节）- 最大容量 (Max Capacity)：
这是水桶能装的最大流量，决定了突发传输（Burst）的额度。当用户一段时间没有下载，桶装满时，他突然发起一个请求，最开始的 1,000,000 字节（1 MB）可以无限制地以网络最高速度“瞬间飙完”。桶空了之后，速度就会降回上面定义的 1 MB/s。

3. 这条配置在生产中的实际运行效果
结合两组参数，这条命令实现了一个“双层限速控制”：

共享总上限：通过这个 Squid 代理池（池1）下载的所有用户，总下载速度绝对不会超过 2 MB/s（约 16 Mbps）。

单兵不垄断：即使总带宽有空闲，任何一个单独的 IP 长期下载速度最多也只能跑到 1 MB/s（约 8 Mbps）。

并发时的动态挤压：

如果只有 1 个 用户在全速下载，他能跑到他的上限 1 MB/s，此时整个池还剩 1 MB/s 闲置。

如果有 2 个 用户同时全速下载，他们每人可以分到 1 MB/s，正好把总池子的 2 MB/s 撑满。

如果有 4 个 用户同时全速下载，由于总池子被卡死在 2 MB/s，他们四个人将平摊总带宽，每人只能分到约 0.5 MB/s 的速度。