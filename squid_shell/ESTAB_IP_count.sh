#!/bin/bash

echo -e "connect state count:"
ss -ant | awk 'NR>1 {print $1}' | sort | uniq -c | sort -rn

echo -e "IP count:"
ss -ant | awk 'NR>1 && $5!="*.*" {split($5,a,":"); print a[1]}' | sort | uniq -c | sort -rn | head -n 10

