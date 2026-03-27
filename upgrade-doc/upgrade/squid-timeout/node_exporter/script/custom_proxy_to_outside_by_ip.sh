#!/bin/bash
COLLECTOR_PATH='/opt/node_exporter/textfile_collector'
COLLECTOR_FILE='custom_proxy_to_outside_by_ip.prom'
result=$(/usr/sbin/ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 !~ /127.0.0.1/ && $4 !~ /:8080$/ && match($4, /:([0-9]+)$/, a) && a[1] > 1024 && $5 != "*:*" {print $5}' | sort | uniq -c | sort -n | awk '{printf "custom_proxy_to_outside_by_ip{client_ip=\"%s\"} %d\n", $2, $1}')
echo "$result" > $COLLECTOR_PATH/$COLLECTOR_FILE.$$
mv $COLLECTOR_PATH/$COLLECTOR_FILE.$$ $COLLECTOR_PATH/$COLLECTOR_FILE
