<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Services\AdminLogService;
use App\Http\Controllers\Services\HelperService;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ConnectionstatusController extends Controller
{

    private $connection_status_path = '/mangerTxt/connection_status.txt';
    private $serverlist = '/mangerTxt/serverlist.txt';
    protected $logService;
    protected $helperService;
 
    public function __construct(AdminLogService $logService, HelperService $helperService)
    {
        $this->logService = $logService;
        $this->helperService = $helperService;
    }

    public function index(Request $request)
    {
        $params = $request->all();
        $page = !empty($params['page']) ? (int)$params['page'] : 1;
        $limit = !empty($params['limit']) ? (int)$params['limit'] : 10;
        $count = 0;
        $start = 0;
        $skip = true;
        if ($page > 1) $start = ($page - 1) * $limit;

        $search_arr = [];
        foreach ($params as $key => $value) {
            if ($key == 'page') continue;
            if ($key == 'limit') continue;
            if (!empty($value) || $value === '0') $search_arr[trim($key)] = trim($value);
        }

        $data = json_decode(@file_get_contents(public_path() . $this->connection_status_path), true) ?? [];

        if (!empty($data)) {

            if(!empty($params['sort_by']) && !empty($params['sort_order'])){
                $sort = array_column($data, $params['sort_by']);
                if($params['sort_order'] == 'asc'){
                    array_multisort($sort, SORT_ASC, $data);
                }else{
                    array_multisort($sort, SORT_DESC, $data);
                }
            }

            if (!empty($search_arr)) {
                $skip = false;
                $data = @$this->filterUsers($data, $search_arr);
            }


            $count = count($data);
            // 统计去重后的user数量
            // $user_count = count(array_unique(array_column($data, 'user')));
            $user_count = count(array_filter(array_unique(array_column($data, 'user')), function($val) {
                return trim($val) !== '-';
            }));

            $data = array_slice($data, $start, $limit);
        }

        return response()->json(['message' => 'Success', 'code' => 200, 'data' => $data, 'is_all' => $skip, 'count' => $count, 'user_count' => $user_count ?? 0]);
    }

    public function filterUsers($data, $conditions)
    {
        $results = array_filter($data, function ($item) use ($conditions) {
            foreach ($conditions as $key => $value) {
                // 检查字段是否存在，如果不存在则跳过
                if (!isset($item[$key])) {
                    continue;
                }
                // 确保 $value 是字符串
                $value = strval($value);
                // 模糊匹配
                if (stripos($item[$key], $value) === false) {
                    return false;
                }
            }
            return true;
        });
        return $results;
    }


    public function connection_status_data(Request $request)
    {   
        set_time_limit(600);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 600);

        $params = $request->input();
        if(empty($params['server_ip']) || empty($params['server_time'])){
            return response()->json(['message' => 'Params error', 'code' => 9001]);
        }

        $server_ip = trim($params['server_ip']);
        $server_time = trim($params['server_time']);

        try {
            // 1. 请求 A 接口，只拿文件名（极小数据）
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://'.$server_ip.':8000/api/get_connection_status');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'server_time' => $server_time
            ]));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $filename = trim(curl_exec($ch));
            curl_close($ch);

            if (!$filename || $filename === 'PARAM_ERROR' || $filename === 'EXEC_FAILED') {
                return response()->json(['message' => 'A 接口执行失败', 'code' => 9001]);
            }

            // 2. 直接下载文件（流式，不爆内存）
            $fileUrl = 'https://'.$server_ip.':8000/'.$filename;

            $tmpFile = tempnam(sys_get_temp_dir(), 'data');
            $fp = fopen($tmpFile, 'w');

            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $fileUrl);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch2, CURLOPT_FILE, $fp);
            curl_exec($ch2);
            curl_close($ch2);
            fclose($fp);

            // 3. 读取内容
            $result = file_get_contents($tmpFile);
            @unlink($tmpFile);

            // 4. 解析 + 排序
            $return_data = json_decode($result, true) ?? [];

            // 超快排序
            $sort = array_column($return_data, 'request_count');
            array_multisort($sort, SORT_DESC, $return_data);

            // 加ID
            foreach ($return_data as $key => &$item) {
                $item['id'] = $key + 1;
            }

            // 写入最终文件
            $sorted_json = json_encode($return_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents(public_path() . $this->connection_status_path, $sorted_json);

            return response()->json(['message' => 'Success', 'code' => 200]);

        } catch (Throwable $e) {
            return response()->json(['message' => 'Error:'.$e->getMessage(), 'code' => 9001]);
        }
    }

    // csv
    public function export(Request $request)
    {
        ini_set('memory_limit', '1G');
        set_time_limit(600);

        $params = $request->all();
        if (empty($params['server']) || empty($params['time_period'])) {
            return response()->json(['message' => 'Params is empty.', 'code' => 9001]);
        }

        $time_period = "Last " . $params['time_period'] . " Minute";
        $server = '';
        $serverlist = json_decode(@file_get_contents(public_path() . $this->serverlist), true) ?? [];
        foreach ($serverlist as $key => $value) {
            if ($value['ip'] == $params['server']) {
                $server = $value['server_name'];
                break;
            }
        }

        // 读取数据
        $data = json_decode(@file_get_contents(public_path() . $this->connection_status_path), true) ?? [];
        $count = count($data);
	$user_count = count(array_filter(array_unique(array_column($data, 'user')), function($val) {
            return trim($val) !== '-';
        }));

        try {
            // 生成文件名
            $fileName = 'connection_status.csv';
            $dir = public_path() . '/connection_status_export/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $filePath = $dir . $fileName;

            // 打开文件句柄（流式写入，支持10万+数据）
            $fp = fopen($filePath, 'w');
            // 解决Excel打开CSV乱码问题
            fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 写入统计信息
            fputcsv($fp, ['Server', $server]);
            fputcsv($fp, ['Time Period', $time_period]);
            fputcsv($fp, ['User Count', $user_count]);
            fputcsv($fp, ['IP Count', $count]);
            fputcsv($fp, []); // 空行
            fputcsv($fp, []); // 空行

            // 表头
            $headers = ['No.', 'Source IP', 'Username', 'Connection', 'Download Size (MB)'];
            fputcsv($fp, $headers);

            // 循环写入数据（逐行写入，不占内存）
            foreach ($data as $value) {
                $row = [
                    $value['id'] ?? '',
                    $value['ip'] ?? '',
                    $value['user'] ?? '',
                    $value['request_count'] ?? '',
                    !empty($value['size_mb']) ? number_format($value['size_mb'], 2) : ''
                ];
                fputcsv($fp, $row);
            }

            fclose($fp);

            return response()->json([
                'message' => 'Export success',
                'code' => 200,
                'fileName' => $fileName,
                'downloadUrl' => '/connection_status_export/' . $fileName
            ]);

        } catch (\Exception $e) {
            error_log('Export CSV failed: ' . $e->getMessage());
            return response()->json(['message' => 'Export fail', 'code' => 9001]);
        }
    }
}
