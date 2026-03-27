#!/bin/bash
COLLECTOR_PATH='/opt/node_exporter/textfile_collector'
COLLECTOR_FILE='custom_outside_to_proxy_by_ip.prom'
result=$(/usr/sbin/ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 ~ /:8080$/ {print $5}' | sed -E 's/\[.*:([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\].*/\1/;t; s/:.*//' | sort | uniq -c | sort -n | awk '{printf "custom_outside_to_proxy_by_ip{client_ip=\"%s\"} %d\n", $2, $1}')
echo "$result" > $COLLECTOR_PATH/$COLLECTOR_FILE.$$
mv $COLLECTOR_PATH/$COLLECTOR_FILE.$$ $COLLECTOR_PATH/$COLLECTOR_FILE
