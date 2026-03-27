#!/bin/bash
COLLECTOR_PATH='/opt/node_exporter/textfile_collector'
COLLECTOR_FILE='custom_number_py_prod.prom'
count=$(ps axu |grep "/opt/py_prod" |wc -l)
echo "custom_number_py_prod $count" > $COLLECTOR_PATH/$COLLECTOR_FILE.$$
mv $COLLECTOR_PATH/$COLLECTOR_FILE.$$ $COLLECTOR_PATH/$COLLECTOR_FILE
