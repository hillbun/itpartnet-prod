# 定義 ACL
acl apple_traffic dstdomain .apple.com
# 設置 DSCP 為 8 (即 ToS 32 或 0x20)
tcp_outgoing_tos 0x20 apple_traffic
clientside_tos 0x20 apple_traffic