/opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php
/opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php

/usr/bin/python3 /usr/nginx/html/threat/IOCsApi_csv.py


cd /opt/nginx/html/transproxy_admin/public/fileData/threat


grep "\.co$" *.txt
grep -E "^\.[a-z]{2,}$" *.txt


mkdir -p /root/backup/20260324
cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php /root/backup/20260324/ThreatController.php
cp /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php /root/backup/20260324/RemoveDup.php


mkdir -p /root/upgrade/tianji/
cd /root/upgrade/tianji/
scp ubuntu@xxxx:/home/ubuntu/upgrade/tianji/ThreatController.php  /root/upgrade/tianji/
scp ubuntu@xxxx:/home/ubuntu/upgrade/tianji/RemoveDup.php  /root/upgrade/tianji/

scp -P 80 ubuntu@x.x.x.x:/home/ubuntu/upgrade/tianji/ThreatController.php  /root/upgrade/tianji/
scp -P 80 ubuntu@xxxx:/home/ubuntu/upgrade/tianji/RemoveDup.php  /root/upgrade/tianji/

\cp /root/upgrade/tianji/ThreatController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/ThreatController.php
\cp /root/upgrade/tianji/RemoveDup.php /opt/nginx/html/transproxy_admin/app/Console/Commands/RemoveDup.php


