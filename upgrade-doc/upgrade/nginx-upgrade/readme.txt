https://www.cve.org/CVERecord?id=CVE-2026-42945

https://github.com/openresty/openresty/issues/1119


nginx -T 2>/dev/null | grep -E 'rewrite.*\$[0-9]'

https://nginx.org/en/download.html
https://nginx.org/download/nginx-1.30.1.tar.gz


NGINX 曝严重远程代码执行漏洞，潜伏代码库 18 年，全球数亿服务器面临风险

2026 年 5 月 13 日，安全研究机构 depthfirst 与 F5 联合披露了 NGINX 中一个编号为 CVE-2026-42945 的严重漏洞，CVSS v4.0 评分达 9.2。该漏洞属于堆缓冲区溢出类型，存在于 ngx_http_rewrite_module 模块中，最早可追溯至 2008 年引入的代码逻辑，在代码库中潜伏长达 18 年。

漏洞根源在于脚本引擎两遍执行流程中的状态不一致：
当 rewrite 指令的替换字符串中含有问号时，引擎内部标志位 is_args 会被置为 1 且不会重置。第一遍（长度计算）使用全零初始化的子引擎，以未转义长度分配内存；第二遍（数据拷贝）则在主引擎上执行，对 URI 中的特殊字符进行转义扩展，每个字符可由 1 字节膨胀至 3 字节，实际写入量超出已分配缓冲区，造成堆溢出。攻击者无需身份认证，仅需向含有特定 rewrite 配置模式的服务器发送精心构造的 HTTP 请求，即可触发该漏洞。进一步利用时，攻击者可通过控制连接时序实施堆内存布局操纵，并借助 POST 请求体注入任意二进制载荷，最终实现对 NGINX Worker 进程的远程代码执行。

受影响版本覆盖范围极广：
NGINX Open Source 0.6.27 至 1.30.0 全系，NGINX Plus R32 至 R36，以及 NGINX Instance Manager、F5 WAF for NGINX、NGINX App Protect WAF/DoS、NGINX Gateway Fabric、NGINX Ingress Controller 等多个企业级产品均受影响。
由于上述产品被大量部署于云原生 Kubernetes 集群的 Ingress 层及 API 网关场景，实际暴露面涉及全球数以亿计的生产服务器。

已发布修复版本：NGINX Open Source 用户应升级至 1.31.0 或 1.30.1，NGINX Plus 用户应升级至 R36 P4 或 R32 P6，升级后需重启服务使补丁生效。对于暂时无法升级的用户，可通过将配置中所有未命名捕获组（$1、$2）替换为命名捕获组的方式规避触发条件。排查方法为：确认已安装的 NGINX 版本区间，并在配置文件中检索同时含有未命名捕获组与问号替换字符串的 rewrite 指令。




---
https://github.com/openresty/headers-more-nginx-module/archive/refs/tags/v0.34.tar.gz


https://nginx.org/download/nginx-1.30.1.tar.gz


./configure --user=nginx --group=nginx --prefix=/opt/nginx \
--with-cc-opt=-O2 \
--with-http_ssl_module \
--with-http_v2_module \
--with-http_sub_module \
--with-http_stub_status_module \
--with-threads \
--with-compat \
--add-module=../headers-more-nginx-module-0.34 \
--sbin-path=/opt/nginx/sbin/nginx \
--conf-path=/opt/nginx/conf/nginx.conf \
--with-stream \
--without-pcre2 \
--with-stream_ssl_module \
--with-stream_ssl_preread_module



/opt/nginx/sbin/nginx -V


			
			