<?php
// ldapsearch -x -H ldaps://160.98.39.18:636 -D CN=n5-proxy-opp-52a,OU=Users,OU=N2,OU=ITIS,DC=corp,DC=ha,DC=org,DC=hk -w 'ziSJ144Watmj' -b DC=corp,DC=ha,DC=org,DC=hk
// 使用 SSL 连接
$ldapServer = "ldaps://160.98.39.18"; // 或使用端口 636
$ldapPort = 636;
$ldapUser = "CN=n5-proxy-opp-52a,OU=Users,OU=N2,OU=ITIS,DC=corp,DC=ha,DC=org,DC=hk";
$ldapPassword = "ziSJ144Watmj";

// 创建连接
$ldapConn = ldap_connect($ldapServer, $ldapPort);
if (!$ldapConn) {
    die("无法连接到 LDAP 服务器");
}

// 设置选项
ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);


// 绑定
if (ldap_bind($ldapConn, $ldapUser, $ldapPassword)) {
    echo "SSL LDAP 绑定成功！";
    ldap_unbind($ldapConn);
} else {
    echo "绑定失败：" . ldap_error($ldapConn);
}
?>