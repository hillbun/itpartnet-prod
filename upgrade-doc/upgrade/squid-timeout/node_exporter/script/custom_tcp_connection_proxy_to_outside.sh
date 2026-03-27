#!/bin/bash
COLLECTOR_PATH='/opt/node_exporter/textfile_collector'
COLLECTOR_FILE='custom_tcp_connection_proxy_to_outside.prom'
count=$(/usr/sbin/ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 !~ /127.0.0.1/ && $4 !~ /:8080$/ && match($4, /:([0-9]+)$/, a) && a[1] > 1024 && $5 != "*:*" {print $5}' |wc -l)
echo "custom_tcp_connection_proxy_to_outside $count" > $COLLECTOR_PATH/$COLLECTOR_FILE.$$
mv $COLLECTOR_PATH/$COLLECTOR_FILE.$$ $COLLECTOR_PATH/$COLLECTOR_FILE
