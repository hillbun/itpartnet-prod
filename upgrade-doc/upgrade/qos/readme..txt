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

acl qos_work_hours time MTWHF  9:00-18:00

acl qos_src_2 src "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_src_2.txt"
acl qos_dstdomain_2 dstdomain "/usr/local/nginx/html/transproxy_admin/public/fileData/qos_dstdomain_2.txt"

delay_class 1 2
#delay_parameters 1 1310720/1310720 655360/655360
delay_parameters 1 983040/983040 655360/655360

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

/usr/local/nginx/html/transproxy_admin/public/fileData/qos_src_2.txt
0.0.0.0/0

/usr/local/nginx/html/transproxy_admin/public/fileData/qos_dstdomain_2.txt
.01link.hk
.apple.com
.cdn-apple.com

