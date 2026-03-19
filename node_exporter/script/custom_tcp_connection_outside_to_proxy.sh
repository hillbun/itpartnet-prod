#!/bin/bash
COLLECTOR_PATH='/opt/node_exporter/textfile_collector'
COLLECTOR_FILE='custom_tcp_connection_outside_to_proxy.prom'
count=$(/usr/sbin/ss -ant | grep -v "LISTEN" | awk 'NR>1 && $4 ~ /:8080$/ {print $5}' | sed -E 's/\[.*:([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\].*/\1/;t; s/:.*//' |wc -l)
echo "custom_tcp_connection_outside_to_proxy $count" > $COLLECTOR_PATH/$COLLECTOR_FILE.$$
mv $COLLECTOR_PATH/$COLLECTOR_FILE.$$ $COLLECTOR_PATH/$COLLECTOR_FILE
