import requests

def fetch_baidu():
    # 目标 URL
    url = "https://www.baidu.com"
    
    try:
        # 发送 GET 请求
        response = requests.get(url)
        
        # 检查请求是否成功 (状态码 200)
        response.raise_for_status()
        
        # 设置正确的编码（百度有时需要手动设置，否则中文可能乱码）
        response.encoding = response.apparent_encoding
        
        print(f"请求成功！状态码: {response.status_code}")
        print("-" * 30)
        # 打印网页内容（只截取前500字以免刷屏）
        print(response.text[:500])
        
    except requests.exceptions.RequestException as e:
        print(f"请求出错: {e}")

if __name__ == "__main__":
    fetch_baidu()