#连接建立超时
connect_timeout 45 seconds
forward_timeout 45 seconds

#client_ip_max_connections 10

#单个客户最大并发连接数
acl client_maxconn maxconn 1000
http_access deny client_maxconn

#客户端空闲超时
client_lifetime 150 seconds

#服务器非活动连接超时
read_timeout 150 seconds
#read_timeout 5 minutes

#服务器请求总超时
request_timeout 180 seconds

#client_idle_pconn_timeout 5 minutes



net.ipv4.tcp_fin_timeout = 15