import csv
import io
import json
import os
import sys
import time
import requests
import subprocess, shlex
from requests.packages.urllib3.exceptions import InsecureRequestWarning

# 禁用安全请求警告
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
from aliyun_api_gateway_sign_py3.com.aliyun.api.gateway.sdk import client
from aliyun_api_gateway_sign_py3.com.aliyun.api.gateway.sdk.http import request
from aliyun_api_gateway_sign_py3.com.aliyun.api.gateway.sdk.common import constant


"""
API具体参数细节请参考天际友盟redqueen API官方文档
在运行前请安装以下库文件
pip3.8 install aliyun-api-gateway-sign-py3
pip3.8 install requests 
"""

# 获取系统日期
datestamp = time.strftime("%Y-%m-%d",time.localtime(time.time()))
DATE = datestamp
# 修改此处可选择要输出的字段
#header = ['geo', 'value', 'type','category','src_ports','dst_ports','tag','score','malicious','source_ref','timestamp']
header = ['value','category','score']
# 设定存放iocs的csv文件名及相对路径‘
IOCS_CSVNAME = "/usr/nginx/html/threat/archive/IOCS_"+DATE+".csv"


APP_KEY = "24634572"
APP_SECRET = "812d078fe8143e529599f9d6689a6046"
HOST = "https://api.tj-un.com"
url = "/v1/iocs"
PAGENUM = "1"


cli = client.DefaultClient(app_key=APP_KEY, app_secret=APP_SECRET)


# token，用户身份标识，必填，请填写
# type，返回数据的类型，可选填，可选值为：feed_ipv4、feed_domain、feed_url、feed_email，
#     可以进行多类型选择，类型之间用","分割（如：feed_ipv4,feed_domain），当此参数为空时返回全部类型数据
# page，页码，可选填，为空时表示请求首页数据，后续根据返回值的"nextpage"字段值填写参数即可
# score_from，可选填，获取数据的信誉值区间最低值
# score_to，可选填，获取数据的信誉值区间最高值
# limit，可选填，按照时间由近到远输出用户指定条目的数据
# SSF(Strict_score_filter)是否严格控制分数筛选(1：是；0：否)

bodyMap = {}
bodyMap["token"] = "23e68d0e-10ec-4afb-b701-0b264eb4dac3"
bodyMap["type"] = ""
bodyMap["page"] = ""
bodyMap["score_from"] = "0"
bodyMap["score_to"] = "100"
bodyMap["limit"] = "1000"
SSF = "1"

# 代理配置
#---proxy_flag----为1开启代理，为0关闭代理；开启代理后续设定对应的IP地址与端口号；
proxy_flag = 0
proxies = {}
proxies["host"] = "127.0.0.1"
proxies["port"] = "8080"


def json_csv(data, filename):
    """ 将iocs的JSON数据转换为CSV """
    global header
    # 解决Windows下部分乱码问题
    """将iocs的JSON数据转换为CSV."""
    # 解决Windows下部分乱码问题
    if not os.path.exists(filename):
        with io.open(filename, "wb") as csvfile:
            csvfile.write(b"\xef\xbb\xbf")

    with io.open(filename, 'a', encoding='utf-8', newline='') as f:
        # 如果header为空，初始化默认字段
        if header is None:
            header = ['geo', 'value', 'type', 'src_ports', 'dst_ports']

        dw = csv.DictWriter(f, fieldnames=header)

        # 如果是第一页数据，则写入头部（列名）
        if PAGENUM == "1":
            dw.writeheader()

        for row in data:
            base_row = {key: row.get(key, '') for key in header if key in row}
            if 'reputation' in row:
                for rep in row['reputation']:
                    temp_row = base_row.copy()
                    temp_row.update({key: rep.get(key, '') for key in header if key in rep})
                    if SSF == "1" and bodyMap and "score_from" in bodyMap and bodyMap["score_from"]:
                        if float(temp_row.get('score', 0.1)) > float(bodyMap["score_from"]):
                            dw.writerow(temp_row)
                    else:
                        dw.writerow(temp_row)
            else:
                dw.writerow(base_row)

    return 0
def apires(PAGENUM):

    bodyMap["page"] = PAGENUM
    req_post = request.Request(host=HOST, protocol=constant.HTTPS, url=url, method="POST", time_out=30)
    req_post.set_body(bodyMap)
    req_post.set_content_type(constant.CONTENT_TYPE_FORM)
    #代理
    if proxy_flag == 1:
        if proxies["host"] == "" or proxies["port"] == "":
            print("已开启代理，请填写对应IP、端口")
            sys.exit()
        else:
            res = cli.execute_pro(req_post, proxies)
    else:
        res = cli.execute(req_post)

    try:
        j = json.loads(res[2].decode('utf-8'))
        assert j['response_status']['code'] == 1
        json_csv(j["response_data"][0]['labels'],IOCS_CSVNAME)

    # except ValueError:
    #     if len(res):
    #         print(("Response: {}".format(res)))
    #         print("API请求失败，请检查config参数")
    #         sys.exit(0)
    #     else:
    #         print("云端无响应")
    #     return 0
    except Exception as e:
        print(("获取数据异常:{}".format(e)))
        if str(e)=="'NoneType' object has no attribute 'decode'":
            print("请求失败，数据返回为空")
        else:
            print(("Response: {}".format(j)))
        return 0
    return j["nextpage"]

def IOCSApi():
        """ 测试IOCS接口"""

        print("--- 开始获取IOCs ---")
        retry = 50
        global PAGENUM
        try:
            nextpage = apires(PAGENUM)
            if nextpage == 0:
                print("无响应，5秒后再次尝试")
                nextpage = PAGENUM
                retry = retry - 1

            while nextpage and retry > 0:
                PAGENUM = nextpage
                print(("Next Page is {}".format(nextpage)))
                nextpage = apires(PAGENUM)
                if nextpage == 0:
                    print("无响应，5秒后再次尝试")
                    nextpage = PAGENUM
                    retry = retry - 1
            else:
                if nextpage == "":
                   print("调用后端api同步数据")
                   cmd = "sudo php artisan ioc:remove-dup"
                   subprocess.run(shlex.split(cmd), cwd="/usr/nginx/html/transproxy_admin", check=True)
                  # response = requests.get("https://127.0.0.1:8000/api/threat_up", verify=False)
                   print("调试信息：响应内容：success")
                else:
                    if retry == 0:
                        print("重试耗尽，任务被迫结束")
                    else:
                        print("如果重试多次仍出现这样的提示，请联系support@tj-un.com解决")

        except Exception as e:
            print(e)
            raise
            return 0
        except KeyboardInterrupt:
            print("\nUser Termined!")

if __name__ == "__main__":
    IOCSApi()
