upgrade steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260423/squid-timeout-tianji
export UPGRADE_DIR=/home/logreader/upgrade/20260423/squid-timeout-tianji

### 1. create backup dir

mkdir -p $BACKUP_DIR

### 2. IndexController.php upgrade

cp /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php $BACKUP_DIR/
\cp -f $UPGRADE_DIR/transproxy/IndexController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chmod 664 /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php




------------
rollback steps:

### 0. export variable

export BACKUP_DIR=/root/backup/20260423/squid-timeout-tianji

### 1. IndexController.php rollback

\cp -f $BACKUP_DIR/IndexController.php /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chmod 664 /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php
chown nginx.www-group /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php



------------


upgrade notes:

1. /opt/nginx/html/transproxy_admin/app/Http/Controllers/Api/IndexController.php


code comments:


	/*
        $python_file_addr="\/opt\/py_prod\/";
        // 检测squid调用的外部py程序配置参数以及py程序
        $filePath = $this->squid_addr;
        // 读取文件，每行作为数组元素（保留换行符）
        // FILE_IGNORE_NEW_LINES 可选：去除每行末尾的换行符
        $squidConfig = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $pyAcl = array_filter($squidConfig, function($line) {
            return preg_match('/^\s*external_acl_type/', $line);
        });
        $pyAcl = array_values(array_filter($pyAcl));

        //1、检测 check_ip_allow.py
        // external_acl_type check_ip_allow_check ttl=0 negative_ttl=0 s  children-max=100 s %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_allow.py
        $check_ip_allow=false;
        //2、检测 check_ip_deny.py
        // external_acl_type check_ip_deny_check ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_prod/check_ip_deny.py
        $check_ip_deny=false;
        //3、检测 check_iprange_allow.py
        // external_acl_type check_iprange_allow_check ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_allow.py
        $check_iprange_allow=false;
        //4、检测 check_iprange_deny.py
        // external_acl_type check_iprange_deny_check  ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_prod/check_iprange_deny.py
        $check_iprange_deny=false;
        //5、检测 check_special_user_allow.py
        // external_acl_type special_user_allow_check ttl=0 negative_ttl=0  children-max=100  %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_allow.py
        $check_special_user_allow=false;
        //6、检测 check_special_user_deny.py
        // external_acl_type special_user_deny_check ttl=0 negative_ttl=0   children-max=100  %LOGIN %DST /usr/bin/python3 /opt/py_prod/check_special_user_deny.py
        $check_special_user_deny=false;
        //7、检测 check_user.py
        // external_acl_type check_user ttl=0 negative_ttl=0  children-max=100  %LOGIN %URI /usr/bin/python3 /opt/py_prod/check_user.py
        $check_user=false;
        foreach ($pyAcl as $key => $value) {
            if(
                preg_match('/^external_acl_type\s+check_ip_allow_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_ip_allow\.py\s*$/', $value) === 1 && 
                $this->validateSquidRule($value) === true
            ) {
                $check_ip_allow=true;          
            }

            if(
                preg_match('/^external_acl_type\s+check_ip_deny_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_ip_deny\.py\s*$/', $value) === 1 && 
                $this->validateSquidRule($value) === true 
            ) {
                $check_ip_deny=true;  
            }

            if(
                preg_match('/^external_acl_type\s+check_iprange_allow_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_iprange_allow\.py\s*$/', $value) === 1 && 
                $this->validateSquidRule($value) === true 
            ) {
                $check_iprange_allow=true;  
            }

            if(
                preg_match('/^external_acl_type\s+check_iprange_deny_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_iprange_deny\.py\s*$/', $value) === 1 && 
                $this->validateSquidRule($value) === true 
            ) {
                $check_iprange_deny=true;  
            }

            if(
                preg_match('/^external_acl_type\s+special_user_allow_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_special_user_allow\.py\s*$/', $value) === 1 && 
                $this->validateSquidSpecialUserRule($value) === true 
            ) {
                $check_special_user_allow=true;  
            }

            if(
                preg_match('/^external_acl_type\s+special_user_deny_check\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_special_user_deny\.py\s*$/', $value) === 1 && 
                $this->validateSquidSpecialUserRule($value) === true 
            ) {
                $check_special_user_deny=true;  
            }

            if(
                preg_match('/^external_acl_type\s+check_user\s+/', $value) === 1 && 
                preg_match('/\s+\/usr\/bin\/python3\s+'.$python_file_addr.'check_user\.py\s*$/', $value) === 1 && 
                $this->validateSquidUserRule($value) === true 
            ) {
                $check_user=true;  
            }
        }

        if($check_ip_allow===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_ip_allow.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_ip_allow.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }

        if($check_ip_deny===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_ip_deny.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_ip_deny.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }

        if($check_iprange_allow===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_iprange_allow.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_iprange_allow.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }


        if($check_iprange_deny===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_iprange_deny.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_iprange_deny.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }

        if($check_special_user_allow===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_special_user_allow.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_special_user_allow.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }

        if($check_special_user_deny===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_special_user_deny.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_special_user_deny.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }

        if($check_user===false) {
            $this->remark_squid_reload_failed_log('There is an error in configuring the check_user.py parameter in opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "There is an error in configuring the check_user.py parameter in opp conf", 
                    'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
	}
	*/


