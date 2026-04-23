<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\RadiusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RedisModel\RagentController;
use App\Http\Controllers\RedisModel\RcomputerController;
use App\Http\Controllers\RedisModel\RipRangeController;
use Illuminate\Support\Facades\Redis;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use phpseclib3\Net\SSH2;
use Ramsey\Uuid\Generator\RandomGeneratorFactory;
use App\Http\Controllers\RedisModel\RmanagerController;
use App\Http\Controllers\RedisModel\RcategoryController;
use App\Http\Controllers\RedisModel\RfilterController;
use App\Http\Controllers\RedisModel\RstrategyController;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Services\AdminLogService;
use App\Http\Controllers\Services\HelperService;

class IndexController extends Controller
{
    private $user_login = '/fileData/user.txt';
    private $threatV = '/fileData/threat/threat_version.txt';
    private $ipwhitelist = '/fileData/ipwhitelist.txt';
    private $ipblacklist = '/fileData/ipblacklist.txt';
    private $dest_ip_whitelist = '/fileData/dest_ip_whitelist.txt';
    private $dest_ip_blacklist = '/fileData/dest_ip_blacklist.txt';
    private $upFile = '/uploadFile/';
    private $urlwhitelist = '/fileData/urlwhitelist.txt';
    private $urlblacklist = '/fileData/urlblacklist.txt';
    private $squidServerlist = '/fileData/squid_server.txt';
    private $useragentlist = '/fileData/useragentlist.txt';
    private $urlsetlist = '/fileData/urlset.txt';
    private $popuplist = '/fileData/popuplist.txt';
    private $sysJson = '/fileData/threat/netstat_status.json';
    private $squid_addr = '/opt/squid/etc/squid.conf';
    private $released_version_txt = '/fileData/released_version.txt';
    protected $logService;
    protected $helperService;

    public function __construct(AdminLogService $logService, HelperService $helperService)
    {
        $this->logService = $logService;
        $this->helperService = $helperService;
    }

    public function logout(Request $request)
    {
        $params = $request->all();

        $message = trim($params['message'] ?? '');
        $this->logService->logAction(
            Session::all()['user_name'],
            "login",
            "",
            "logout",
            "Logout success,operate the computer:" . gethostbyname(gethostname()) . " " . $message
        );
        $request->session()->forget('token');
        $request->session()->invalidate();  // 使当前 Session 失效
        $request->session()->regenerateToken();  // 刷新 CSRF 令牌
        return response()->json(['code' => 200]);
    }

    public function do_login(Request $request)
    {

        $allowedDomains  = [env('FRONT_END_ADDR', 'tp.opp.ha.org.hk')];
        $host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) ?? '';
        //var_dump($allowedDomains,$host);die;
        if (!in_array(strtolower($host), $allowedDomains, true)) {
            return response()->json(['code' => -1, 'message' => 'Invalid Host header']);
	}

        $username = trim($request->input('username', ''));
        $password = trim($request->input('password', ''));


        if (empty($username) || empty($password)) {
            return response()->json(['code' => -1, 'message' => 'Account or password error']);
        }

        $redis_user = new RmanagerController();
        $search_arr = [];
        $search_arr['username'] = $username;
        $user_data = $redis_user->verifyAccountAndPassword($search_arr, 1);
        if (empty($user_data['data'])) return response()->json(['code' => -1, 'message' => 'Account or password error']);
        $user_info = $user_data['data'][0];
        //账户状态：1正常、0停用
        if ($user_info['status'] == 0) {
            $this->logService->logAction(
                $username,
                "login",
                "",
                "login",
                "Account has been disabled,operate the computer:" . gethostbyname(gethostname())
            );
            return response()->json(['code' => -1, 'message' => 'This account has been disabled, please contact the administrator']);
        }
        // 当天密码输入5次错误，禁用账户
        if ($user_info['password'] != $password) {

            $password_error_count = 0;
            if (isset($user_info['password_error_count']) && isset($user_info['password_error_date']) && $user_info['password_error_date'] == date('Y-m-d')) {
                $password_error_count = $user_info['password_error_count'];
            }
            $password_error_count++;
            $error_info = "the account will be disabled";
            if ($password_error_count >= 5) {
                $error_info = "the account have been disabled,please contact the administrator";
                $user_info['status'] = 0;
            }
            $user_info['password_error_count'] = $password_error_count;
            $user_info['password_error_date'] = date('Y-m-d');
            $redis_user->update($user_info['id'], $user_info);
            $this->logService->logAction(
                $username,
                "login",
                "",
                "login",
                "Number of password errors:{$password_error_count},operate the computer:" . gethostbyname(gethostname())
            );

            return response()->json(['code' => -1, 'message' => "Account or password error"]);
        }
        $request->session()->invalidate();
        $request->session()->push('token', $this->generateToken());
        Session::put('user_id', $user_info['id']);
        Session::put('user_name', $user_info['username']);
        Session::put('login_time', time());
        Session::put('user_role', strtolower($user_info['role']));
        $session_info = Session::all() ?? [];

        $this->logService->logAction(
            $username,
            "login",
            "",
            "login",
            "Login success,operate the computer:" . gethostbyname(gethostname())
        );

        return response()->json(['code' => 200, 'username' => $username, 'token' => $request->session()->get('token')[0], 'user_info' => $session_info]);
    }

    public function generateToken($length = 32)
    {
        $randomBytes = random_bytes($length);
        return bin2hex($randomBytes);
    }

    public function isValidUrlChars($url)
    {
        // 允许的字符：字母、数字、以及 URL 特殊符号
        return preg_match('/^[a-zA-Z0-9\-\._~:\/\?#\[\]@!\$&\'\(\)\*\+,;=]+$/', $url);
    }

    public function checkAddressType($address)
    {
        // 检查是否为 IPv4 地址
        if (preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $address)) {
            return 'ipv4';
        }

        // 检查是否为域名（简化版，允许中文域名）
        if (preg_match('/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?:\.(?!-)[A-Za-z0-9-]{1,63})*(?:\.[A-Za-z]{2,})$/u', $address)) {
            return 'domain';
        }

        return 'invalid';
    }

    public function hasCharsAfterFirstSlash($str)
    {
        $pos = strpos($str, '/');
        return $pos !== false && $pos < strlen($str) - 1;
    }

    public function url_check($data)
    {

        foreach ($data as $key => $url_data) {

            // 同时替换多个模式
            $patterns = [
                '/https:\/+/',
                '/http:\/+/'
            ];

            $url_chuli = preg_replace($patterns, '', $url_data[0]);

            $url = explode('/', $url_chuli)[0];

            if ($this->isValidUrlChars($url) == 0) return ['message' => 'Invalid URL, on line ' . ($key + 2), 'code' => -1];

            // if ($this->hasCharsAfterFirstSlash($url) === true) return ['message' => 'Invalid URL, on line ' . ($key + 2), 'code' => -1];

            $url = preg_replace('/^www\./', '', $url);
            $url = preg_replace('/^\.+/', '', $url);
            // $url = preg_replace('/^\.+/', '.', $url);
            // $url = preg_replace('/\/+/', '', $url);
            /*
            $url_v = explode(':', $url)[0];

            $res = $this->checkAddressType($url_v);

            if ($res == 'invalid') return ['message' => 'Invalid URL, on line ' . ($key + 2), 'code' => -1];

            if ($res == 'domain') {
                $url = '.' . $url;
            }*/

            $url = '.' . $url;

            $data[$key][0] = $url;
        }

        // 去重處理
        $jsonKeys = [];
        $result = [];

        foreach ($data as $item) {
            $json = json_encode(trim($item[0]));
            if (!in_array($json, $jsonKeys)) {
                $jsonKeys[] = $json;
                $result[] = $item;
            }
        }
        return $result;
    }

    public function file_upload(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(600);

        $type = $request->input('type', '');

        if (empty($_FILES['fileUpload'])) {
            return response()->json(['message' => 'File empty', 'code' => -1]);
        }
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $target_dir = public_path() . $this->upFile; // 设置上传目录

            $target_file = $target_dir . mt_rand(0, 999999) . "_" . basename($_FILES["fileUpload"]["name"]);
            // 检查文件类型
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if ($imageFileType != "xls") {
                return response()->json(['message' => 'File type is not xls', 'code' => -1]);
            }
            if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $target_file)) {
                // 载入Excel文件
                $inputFileName = $target_file;
                $reader = IOFactory::createReader('Xls'); // 用于读取XLS格式的文件
                $reader->setReadDataOnly(true); // 只读取数据，不读取格式等其他信息
                $spreadsheet = $reader->load($inputFileName);
                $worksheet = $spreadsheet->getActiveSheet();

                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $data = [];
                for ($row = 2; $row <= $highestRow; $row++) {
                    // 遍历每一列
                    $rowData = [];
                    for ($col = 'A'; $col <= $highestColumn; ++$col) {
                        // 获取单元格的值
                        $cellValue = $worksheet->getCell($col . $row)->getValue();
                        // 输出单元格的值
                        $rowData[] = $cellValue;
                        // echo $cellValue . ' ';
                    }
                    $data[] = $rowData;
                }

                $log_flag = false;
                $details = [];
                if ($type == "ipWhiteList") {
                    $ipdata = json_decode(@file_get_contents(public_path() . $this->ipwhitelist), true) ?: [];
                    $data = $this->helperService->validateIpOrIpSegment($data, $ipdata);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $id = !empty($ipdata) ? end($ipdata)['id'] + 1 : 1;
                    $keys = 'ip';
                    // 提取需要查找的列
                    $ipStr = '';
                    $ipRangeStr = '';
                    $column = array_column($ipdata, $keys);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "ip" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            strpos($value[0], '-') === false ? $ipStr .= $value[0] . "\n" : $ipRangeStr .= $value[0] . "\n";
                            $ipdata[] = $new_arr;
                        } else {
                            foreach ($ipdata as $kk => $vv) {
                                $vv['remark'] = $vv['remark'] ?? '';
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['ip'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $ipdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $ipdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $ipdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $ipdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['ip'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->ipwhitelist, json_encode($ipdata, true));
                    !empty($ipStr) && file_put_contents(public_path() . '/fileData/ip-whitelist.txt', $ipStr, FILE_APPEND);
                    !empty($ipRangeStr) && file_put_contents(public_path() . '/fileData/ip-whitelist-range.txt', $ipRangeStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['ip', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "ip_authentication",
                            "source_ip/ips_whitelist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "ipBlackList") {
                    $ipdata = json_decode(@file_get_contents(public_path() . $this->ipblacklist), true) ?: [];
                    $data = $this->helperService->validateIpOrIpSegment($data, $ipdata);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $id = !empty($ipdata) ? end($ipdata)['id'] + 1 : 1;
                    $keys = 'ip';
                    // 提取需要查找的列
                    $ipStr = '';
                    $ipRangeStr = '';
                    $column = array_column($ipdata, $keys);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "ip" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            strpos($value[0], '-') === false ? $ipStr .= $value[0] . "\n" : $ipRangeStr .= $value[0] . "\n";
                            $ipdata[] = $new_arr;
                        } else {
                            foreach ($ipdata as $kk => $vv) {
                                $vv['remark'] = $vv['remark'] ?? '';
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['ip'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $ipdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $ipdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $ipdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $ipdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['ip'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->ipblacklist, json_encode($ipdata, true));
                    !empty($ipStr) && file_put_contents(public_path() . '/fileData/ip-blacklist.txt', $ipStr, FILE_APPEND);
                    !empty($ipRangeStr) && file_put_contents(public_path() . '/fileData/ip-blacklist-range.txt', $ipRangeStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['ip', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "ip_authentication",
                            "source_ip/ips_blacklist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "popUpList") {
                    $ipdata = json_decode(@file_get_contents(public_path() . $this->popuplist), true) ?: [];
                    $data = $this->helperService->validateIpOrIpSegment($data, $ipdata);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $id = !empty($ipdata) ? end($ipdata)['id'] + 1 : 1;
                    $keys = 'ip';
                    // 提取需要查找的列
                    $ipPopStr = '';
                    $column = array_column($ipdata, $keys);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "ip" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $ipPopStr .= $value[0] . "\n";
                            $ipdata[] = $new_arr;
                        } else {
                            foreach ($ipdata as $kk => $vv) {
                                $vv['remark'] = $vv['remark'] ?? '';
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['ip'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $ipdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $ipdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $ipdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $ipdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['ip'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->popuplist, json_encode($ipdata, true));
                    file_put_contents(public_path() . '/fileData/specify_ip_pop_ldaps.txt', $ipPopStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['ip', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "pop_up_list",
                            "",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "ipDestWhiteList") {
                    $ipdata = json_decode(@file_get_contents(public_path() . $this->dest_ip_whitelist), true) ?: [];
                    $data = $this->helperService->validateIpOrIpSegment($data, $ipdata);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $id = !empty($ipdata) ? end($ipdata)['id'] + 1 : 1;
                    // $keys = 'ip';
                    // 提取需要查找的列
                    $ipStr = '';
                    $ipRangeStr = '';
                    // $column = array_column($ipdata, $keys);
                    $column = array_map(function ($value) {
                        return $value['ip'];
                    }, $ipdata);
                    foreach ($data as $key => $value) {
                        $iprange_flag = false;
                        if (strpos($value[0], '-') !== false) {
                            $iprange_flag = true;
                        }
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "ip" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $iprange_flag === false && $ipStr .= $value[0] . "\n";
                            $iprange_flag === true && $ipRangeStr .= $value[0] . "\n";
                            $ipdata[] = $new_arr;
                        } else {
                            foreach ($ipdata as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['ip'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $ipdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $ipdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $ipdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $ipdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['ip'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->dest_ip_whitelist, json_encode($ipdata, true));
                    !empty($ipStr) && file_put_contents(public_path() . '/fileData/dest_ip_whitelist_squid.txt', $ipStr, FILE_APPEND);
                    !empty($ipRangeStr) && file_put_contents(public_path() . '/fileData/des-iprange-white.txt', $ipRangeStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['ip', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "ip_access_control",
                            "destination_ip/ips_whitelist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "ipDestBlackList") {
                    $ipdata = json_decode(@file_get_contents(public_path() . $this->dest_ip_blacklist), true) ?: [];
                    $data = $this->helperService->validateIpOrIpSegment($data, $ipdata);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $id = !empty($ipdata) ? end($ipdata)['id'] + 1 : 1;
                    // $keys = 'ip';
                    // 提取需要查找的列
                    $ipStr = '';
                    $ipRangeStr = '';
                    // $column = array_column($ipdata, $keys);
                    $column = array_map(function ($value) {
                        return $value['ip'];
                    }, $ipdata);
                    foreach ($data as $key => $value) {
                        $iprange_flag = false;
                        if (strpos($value[0], '-') !== false) {
                            $iprange_flag = true;
                        }
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "ip" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $iprange_flag === false && $ipStr .= $value[0] . "\n";
                            $iprange_flag === true && $ipRangeStr .= $value[0] . "\n";
                            $ipdata[] = $new_arr;
                        } else {
                            foreach ($ipdata as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['ip'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $ipdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $ipdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $ipdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $ipdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['ip'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->dest_ip_blacklist, json_encode($ipdata, true));
                    !empty($ipStr) && file_put_contents(public_path() . '/fileData/dest_ip_blacklist_squid.txt', $ipStr, FILE_APPEND);
                    !empty($ipRangeStr) && file_put_contents(public_path() . '/fileData/des-iprange-black.txt', $ipRangeStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['ip', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "ip_access_control",
                            "destination_ip/ips_blacklist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "urlWhiteList") {
                    $data = $this->url_check($data);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $urlwhitedata = json_decode(@file_get_contents(public_path() . $this->urlwhitelist), true) ?: [];
                    $id = !empty($urlwhitedata) ? end($urlwhitedata)['id'] + 1 : 1;
                    $import_data = [];
                    foreach ($data as $key => $value) {
                        $import_data[] = trim($value[0]);
                    }
                    // 检测Excel域名冲突
                    $res_check = $this->helperService->checkDomainConflict($import_data);
                    if (count($res_check) > 0) {
                        return response()->json(['message' => "Domain name conflict of the imported data: {$res_check[0][0]} and {$res_check[0][1]}", 'code' => -1]);
                    }
                    // 检测Excel域名是否存在包含关系
                    $r_check = $this->helperService->checkDomainContains($import_data);
                    if (count($r_check) > 0) {
                        return response()->json(['message' => "Domain name contains relationships of the imported data: " . implode(" || ", $r_check), 'code' => -1]);
                    }
                    if (!empty($urlwhitedata)) {
                        $multiplied = array_map(function ($value) {
                            return $value['url'];
                        }, $urlwhitedata);
                        foreach ($import_data as $k => $c_url) {
                            $multiplied_temp = $multiplied;
                            array_unshift($multiplied_temp, $c_url);
                            // 检测域名冲突
                            $res_check = $this->helperService->checkDomainConflict($multiplied_temp);
                            if (count($res_check) > 0) {
                                return response()->json(['message' => "There is a domain name conflict between imported data and database data: {$res_check[0][0]} and {$res_check[0][1]}, on line " . ($k + 2), 'code' => -1]);
                            }
                            // 检测域名是否存在包含关系
                            $r_check = $this->helperService->checkDomainContains($multiplied_temp);
                            if (count($r_check) > 0) {
                                return response()->json(['message' => "There is a domain name inclusion relationship between imported data and database data: " . implode(" || ", $r_check) . ", on line " . ($k + 2), 'code' => -1]);
                            }
                        }
                    }
                    // $keys = 'url';
                    $urlStr = '';
                    // 提取需要查找的列
                    // $column = array_column($urlwhitedata, $keys);
                    $column = array_map(function ($value) {
                        return $value['url'];
                    }, $urlwhitedata);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "url" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $urlStr .= $value[0] . "\n";
                            $urlwhitedata[] = $new_arr;
                        } else {
                            foreach ($urlwhitedata as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['url'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $urlwhitedata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $urlwhitedata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $urlwhitedata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $urlwhitedata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['url'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->urlwhitelist, json_encode($urlwhitedata, true));
                    !empty($urlStr) && file_put_contents(public_path() . '/fileData/url-whitelist.txt', $urlStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['url', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "url_access_control",
                            "url_whitelist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "urlBlackList") {
                    $data = $this->url_check($data);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $urlblackdata = json_decode(@file_get_contents(public_path() . $this->urlblacklist), true) ?: [];
                    $id = !empty($urlblackdata) ? end($urlblackdata)['id'] + 1 : 1;
                    $import_data = [];
                    foreach ($data as $key => $value) {
                        $import_data[] = trim($value[0]);
                    }
                    // 检测Excel域名冲突
                    $res_check = $this->helperService->checkDomainConflict($import_data);
                    if (count($res_check) > 0) {
                        return response()->json(['message' => "Domain name conflict of the imported data: {$res_check[0][0]} and {$res_check[0][1]}", 'code' => -1]);
                    }
                    // 检测Excel域名是否存在包含关系
                    $r_check = $this->helperService->checkDomainContains($import_data);
                    if (count($r_check) > 0) {
                        return response()->json(['message' => "Domain name contains relationships of the imported data: " . implode(" || ", $r_check), 'code' => -1]);
                    }
                    if (!empty($urlblackdata)) {
                        $multiplied = array_map(function ($value) {
                            return $value['url'];
                        }, $urlblackdata);
                        foreach ($import_data as $k => $c_url) {
                            $multiplied_temp = $multiplied;
                            array_unshift($multiplied_temp, $c_url);
                            // 检测域名冲突
                            $res_check = $this->helperService->checkDomainConflict($multiplied_temp);
                            if (count($res_check) > 0) {
                                return response()->json(['message' => "There is a domain name conflict between imported data and database data: {$res_check[0][0]} and {$res_check[0][1]}, on line " . ($k + 2), 'code' => -1]);
                            }
                            // 检测域名是否存在包含关系
                            $r_check = $this->helperService->checkDomainContains($multiplied_temp);
                            if (count($r_check) > 0) {
                                return response()->json(['message' => "There is a domain name inclusion relationship between imported data and database data: " . implode(" || ", $r_check) . ", on line " . ($k + 2), 'code' => -1]);
                            }
                        }
                    }
                    // $keys = 'url';
                    $urlStr = '';
                    // 提取需要查找的列
                    // $column = array_column($urlblackdata, $keys);
                    $column = array_map(function ($value) {
                        return $value['url'];
                    }, $urlblackdata);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "url" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $urlStr .= $value[0] . "\n";
                            $urlblackdata[] = $new_arr;
                        } else {
                            foreach ($urlblackdata as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['url'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $urlblackdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $urlblackdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $urlblackdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $urlblackdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['url'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->urlblacklist, json_encode($urlblackdata, true));
                    !empty($urlStr) && file_put_contents(public_path() . '/fileData/threat/url-blacklist.txt', $urlStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['url', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "url_access_control",
                            "url_blacklist",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "urlSetList") {
                    $data = $this->url_check($data);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $urlsetdata = json_decode(@file_get_contents(public_path() . $this->urlsetlist), true) ?: [];
                    $id = !empty($urlsetdata) ? end($urlsetdata)['id'] + 1 : 1;
                    $import_data = [];
                    foreach ($data as $key => $value) {
                        $import_data[] = trim($value[0]);
                    }
                    // 检测Excel域名冲突
                    $res_check = $this->helperService->checkDomainConflict($import_data);
                    if (count($res_check) > 0) {
                        return response()->json(['message' => "Domain name conflict of the imported data: {$res_check[0][0]} and {$res_check[0][1]}", 'code' => -1]);
                    }
                    // 检测Excel域名是否存在包含关系
                    $r_check = $this->helperService->checkDomainContains($import_data);
                    if (count($r_check) > 0) {
                        return response()->json(['message' => "Domain name contains relationships of the imported data: " . implode(" || ", $r_check), 'code' => -1]);
                    }
                    if (!empty($urlsetdata)) {
                        $multiplied = array_map(function ($value) {
                            return $value['url'];
                        }, $urlsetdata);
                        foreach ($import_data as $k => $c_url) {
                            $multiplied_temp = $multiplied;
                            array_unshift($multiplied_temp, $c_url);
                            // 检测域名冲突
                            $res_check = $this->helperService->checkDomainConflict($multiplied_temp);
                            if (count($res_check) > 0) {
                                return response()->json(['message' => "There is a domain name conflict between imported data and database data: {$res_check[0][0]} and {$res_check[0][1]}, on line " . ($k + 2), 'code' => -1]);
                            }

                            // 检测域名是否存在包含关系
                            $r_check = $this->helperService->checkDomainContains($multiplied_temp);
                            if (count($r_check) > 0) {
                                return response()->json(['message' => "There is a domain name inclusion relationship between imported data and database data: " . implode(" || ", $r_check) . ", on line " . ($k + 2), 'code' => -1]);
                            }
                        }
                    }
                    // $keys = 'url';
                    $urlStr = '';
                    // 提取需要查找的列
                    // $column = array_column($urlsetdata, $keys);
                    $column = array_map(function ($value) {
                        return $value['url'];
                    }, $urlsetdata);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "url" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $urlStr .= $value[0] . "\n";
                            $urlsetdata[] = $new_arr;
                        } else {
                            foreach ($urlsetdata as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['url'])) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $urlsetdata[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $urlsetdata[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $urlsetdata[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $urlsetdata[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['url'],  $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->urlsetlist, json_encode($urlsetdata, true));
                    !empty($urlStr) && file_put_contents(public_path() . '/fileData/url-set.txt', $urlStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag === true) {
                        $log_keys = ['url', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "url_access_control",
                            "url_unverified",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "userAgentList") {
                    $userAgentData = json_decode(@file_get_contents(public_path() . $this->useragentlist), true) ?: [];
                    $id = !empty($userAgentData) ? end($userAgentData)['id'] + 1 : 1;
                    $keys = 'user_agent';
                    $agentStr = '';
                    // 提取需要查找的列
                    $column = array_column($userAgentData, $keys);
                    $column = array_map('strtolower', $column);
                    foreach ($data as $key => $value) {
                        if (!in_array(strtolower(trim($value[0])), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "id" => $id,
                                "user_agent" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "remark" => trim($value[3]) ?? '',
                                "date_time" => date("Y-m-d H:i:s", time())
                            ];
                            $id++;
                            $agentStr .= $value[0] . "\n";
                            $userAgentData[] = $new_arr;
                        } else {
                            foreach ($userAgentData as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['remark'] = $vv['remark'] ?? '';
                                $details_flag = false;
                                if (strtolower(trim($value[0])) == strtolower(trim($vv['user_agent']))) {
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $userAgentData[$kk]['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $userAgentData[$kk]['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['remark'])) {
                                        $userAgentData[$kk]['remark'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    //记录操作日志
                                    if ($details_flag === true) {
                                        $userAgentData[$kk]['date_time'] = date("Y-m-d H:i:s", time());
                                        $details[] = [$vv['user_agent'], $vv['pc_name'], $vv['department'], $vv['remark'], $vv['date_time']];
                                    }
                                }
                            }
                        }
                    }
                    file_put_contents(public_path() . $this->useragentlist, json_encode($userAgentData, true));
                    file_put_contents(public_path() . '/fileData/user_agent_list.txt', $agentStr, FILE_APPEND);
                    if (count($details) > 0 || $log_flag == true) {
                        $log_keys = ['user_agent', 'pc_name', 'department', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "user_agent",
                            "",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "computerList") {
                    $data = $this->validateIp($data);
                    if (isset($data['code']) && $data['code'] == -1) return response()->json($data);
                    $redis = new RcomputerController();
                    $computerData = $redis->getAll();
                    $computerData = array_map(function ($item) {
                        return json_decode($item, true);
                    }, $computerData);
                    $keys = 'computer';
                    // 提取需要查找的列
                    $column = array_column($computerData, $keys);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "computer" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "desc" => trim($value[3]) ?? '',
                                'remake' => trim($value[4]) ?? '',
                                'belongto' =>  '',
                                "date" => date("Y-m-d H:i:s", time())
                            ];
                            $redis->add($new_arr['computer'], $new_arr);
                        } else {
                            foreach ($computerData as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['desc'] = $vv['desc'] ?? '';
                                $vv['remake'] = $vv['remake'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['computer'])) {
                                    $update_data = $vv;
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $update_data['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $update_data['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['desc'])) {
                                        $update_data['desc'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[4]) && trim($value[4]) != trim($vv['remake'])) {
                                        $update_data['remake'] = trim($value[4]);
                                        $details_flag = true;
                                    }
                                    if ($details_flag === true) {
                                        $update_data['date'] = date("Y-m-d H:i:s", time());
                                        $redis->update($vv['id'], $update_data);
                                        $details[] = [$vv['computer'], $vv['pc_name'], $vv['department'], $vv['desc'], $vv['remake'], $vv['date']];
                                    }
                                }
                            }
                        }
                    }
                    if (count($details) > 0 || $log_flag == true) {
                        $log_keys = ['computer', 'pc_name', 'department', 'description', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "clients",
                            "computer",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "agentList") {
                    $redis = new RagentController();
                    $agentData = $redis->getAll();
                    $agentData = array_map(function ($item) {
                        return json_decode($item, true);
                    }, $agentData);
                    $keys = 'agent';
                    // 提取需要查找的列
                    $column = array_column($agentData, $keys);
                    $column = array_map('strtolower', $column);
                    foreach ($data as $key => $value) {
                        if (!in_array(strtolower(trim($value[0])), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "agent" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "desc" => trim($value[3]) ?? '',
                                'remake' => trim($value[4]) ?? '',
                                'belongto' =>  '',
                                "date" => date("Y-m-d H:i:s", time())
                            ];
                            $redis->add($new_arr['agent'], $new_arr);
                        } else {
                            foreach ($agentData as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['desc'] = $vv['desc'] ?? '';
                                $vv['remake'] = $vv['remake'] ?? '';
                                $details_flag = false;
                                if (strtolower(trim($value[0])) == strtolower(trim($vv['agent']))) {
                                    $update_data = $vv;
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $update_data['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $update_data['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['desc'])) {
                                        $update_data['desc'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[4]) && trim($value[4]) != trim($vv['remake'])) {
                                        $update_data['remake'] = trim($value[4]);
                                        $details_flag = true;
                                    }
                                    if ($details_flag === true) {
                                        $update_data['date'] = date("Y-m-d H:i:s", time());
                                        $redis->update($vv['id'], $update_data);
                                        $details[] = [$vv['agent'], $vv['pc_name'], $vv['department'], $vv['desc'], $vv['remake'], $vv['date']];
                                    }
                                }
                            }
                        }
                    }
                    if (count($details) > 0 || $log_flag == true) {
                        $log_keys = ['agent', 'pc_name', 'department', 'description', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "clients",
                            "user_agent",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "iprangeList") {
                    $redis = new RipRangeController();
                    $return_data = $redis->checkIPsOverlap($data, 'import');
                    if (isset($return_data['code']) && $return_data['code'] == -1) return response()->json($return_data);
                    $iprangeData = $redis->getAll();
                    $iprangeData = array_map(function ($item) {
                        return json_decode($item, true);
                    }, $iprangeData);
                    $keys = 'iprange';
                    // 提取需要查找的列
                    $column = array_column($iprangeData, $keys);
                    foreach ($data as $key => $value) {
                        if (!in_array(trim($value[0]), $column)) {
                            $log_flag = true;
                            $new_arr = [
                                "iprange" => trim($value[0]),
                                "pc_name" => trim($value[1]) ?? '',
                                "department" => trim($value[2]) ?? '',
                                "desc" => trim($value[3]) ?? '',
                                'remake' => trim($value[4]) ?? '',
                                'belongto' =>  '',
                                "date" => date("Y-m-d H:i:s", time())
                            ];
                            $redis->add($new_arr['iprange'], $new_arr);
                        } else {
                            foreach ($iprangeData as $kk => $vv) {
                                $vv['pc_name'] = $vv['pc_name'] ?? '';
                                $vv['department'] = $vv['department'] ?? '';
                                $vv['desc'] = $vv['desc'] ?? '';
                                $vv['remake'] = $vv['remake'] ?? '';
                                $details_flag = false;
                                if (trim($value[0]) == trim($vv['iprange'])) {
                                    $update_data = $vv;
                                    if (!empty($value[1]) && trim($value[1]) != trim($vv['pc_name'])) {
                                        $update_data['pc_name'] = trim($value[1]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[2]) && trim($value[2]) != trim($vv['department'])) {
                                        $update_data['department'] = trim($value[2]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[3]) && trim($value[3]) != trim($vv['desc'])) {
                                        $update_data['desc'] = trim($value[3]);
                                        $details_flag = true;
                                    }
                                    if (!empty($value[4]) && trim($value[4]) != trim($vv['remake'])) {
                                        $update_data['remake'] = trim($value[4]);
                                        $details_flag = true;
                                    }
                                    if ($details_flag === true) {
                                        $update_data['date'] = date("Y-m-d H:i:s", time());
                                        $redis->update($vv['id'], $update_data);
                                        $details[] = [$vv['iprange'], $vv['pc_name'], $vv['department'], $vv['desc'], $vv['remake'], $vv['date']];
                                    }
                                }
                            }
                        }
                    }

                    if (count($details) > 0 || $log_flag == true) {
                        $log_keys = ['iprange', 'pc_name', 'department', 'description', 'remark', 'date'];
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "clients",
                            "networks",
                            "import",
                            $details,
                            $log_keys,
                            $inputFileName
                        );
                    }
                } elseif ($type == "userAgentList_category") {

                    $data_arr = [];
                    foreach ($data as $key => $value) {

                        $url = $value[1];

                        $port_temp = 443;

                        strpos($url, "http://") !== false && $port_temp = 80;
                        strpos($url, "https://") !== false && $port_temp = 443;

                        $patterns = [
                            '/https:\/+/',
                            '/http:\/+/'
                        ];


                        $url = preg_replace($patterns, '', $url);

                        $filter_arr = ['：', '：', ''];
                        $url = str_replace($filter_arr, ':', $url);


                        if (strpos($url, "/") !== false) {
                            $url_arr = explode('/', $url);
                            $url = $url_arr[0];
                        }


                        $url = preg_replace('/^www\./', '', $url);
                        $url = preg_replace('/^\.+/', '', $url);


                        $url_v = explode(':', $url)[0];
                        $res = $this->checkAddressType($url_v);


                        if ($res == 'domain') {
                            $url = '.' . $url;
                        }

                        $url = trim($url);

                        // $check_flag = false;

                        // $specialChars = ['\d', '|', '(', ')', '[', ']'];

                        // foreach ($specialChars as $char) {
                        //     if (strpos($url, $char) !== false) {
                        //         $check_flag = true;
                        //         break;
                        //     }
                        // }

                        // if($check_flag === true){

                        //     strpos($url,":") === false ? $port_t = $port_temp : $port_t = explode(':',$url)[1];
                        //     $check_url = explode(':',$url)[0];
                        //     $res_ips = $this->helperService->parseRegexIP($check_url);
                        //     if(is_array($res_ips)){
                        //         foreach ($res_ips as $kk => $vv) {
                        //             $data_arr[$value[0]][] = $vv. ":" . $port_t;
                        //         }
                        //     }else{

                        //         $data_arr[$value[0]][] = $res_ips . ":" . $port_t;; 
                        //     }

                        // }else{

                        strpos($url, ":") === false && $url = $url . ":" . $port_temp;

                        $data_arr[trim($value[0])][] = $url;

                        // }

                    }

                    // $total_num = 0;
                    // $filter_num = 0; 
                    foreach ($data_arr as $key => $value) {

                        // $total_num = $total_num + count($value);
                        // $filter_num = $filter_num + count(array_unique($value));

                        $data_arr[$key] = array_unique($value);
                    }

                    // var_dump($total_num,$filter_num);

                    $redis = new RcategoryController();
                    $redis_filter = new RfilterController();

                    foreach ($data_arr as $key => $value) {

                        $label_arr = array_values($value);

                        $data_top = [
                            'category' => $key,
                            'label' => $key,
                            'desc' => 'HA Category',
                            'parent_id' => intval(2),
                            'belongto' =>  '',
                            'date' => date('Y-m-d H:i:s'),
                        ];

                        $parent_id = $redis->add($data_top['category'], $data_top);

                        if (!$parent_id) {
                            $res = $redis->searchData(["category" => $key], 1);
                            $datas = $res['data'];
                            $parent_id = $datas[0]['id'];
                        }

                        $add_flag = true;
                        $category_str = "";
                        foreach ($label_arr as $k => $val) {
                            $data = [
                                'category' => $key . ':' . $val,
                                'label' => $val,
                                'desc' => 'HA Category',
                                'parent_id' => $parent_id,
                                'belongto' =>  '',
                                'date' => date('Y-m-d H:i:s'),
                            ];
                            if ($redis->add($data['category'], $data)) {
                            } else {
                                $add_flag = false;
                                $category_str = $val;
                                break;
                            }
                        }

                        if ($add_flag) {

                            $details = [
                                ['name' => 'category', 'orginData' => '', 'newData' => $data_top['category']],
                                ['name' => 'desc', 'orginData' => '', 'newData' => $data_top['desc']],
                                ['name' => 'destination', 'orginData' => '', 'newData' => $label_arr],
                                ['name' => 'date', 'orginData' => '', 'newData' => $data_top['date']]
                            ];
                            // $this->logService->logAction(
                            //     Session::all()['user_name'],
                            //     "category",
                            //     "",
                            //     "add",
                            //     $details
                            // );
                        }

                        $expandedkeys_temp = ['All Categories', 'User-Defined', $key];
                        $expandedkeys = array_merge($expandedkeys_temp, $label_arr);

                        $data_filter = [
                            'filter' => $key,
                            'desc' =>  'HA Filter',
                            'urllist' =>  $label_arr,
                            'expandedkeys' =>  $expandedkeys,
                            'status' =>  '2',
                            'belongto' => '',
                            'date' => date('Y-m-d H:m:s'),
                        ];


                        if ($redis_filter->add($data_filter['filter'], $data_filter)) {
                            /*
                            $details1 = [
                                ['name' => 'filter', 'orginData' => '', 'newData' => $data_filter['filter']],
                                ['name' => 'desc', 'orginData' => '', 'newData' => $data_filter['desc']],
                                ['name' => 'urllist', 'orginData' => '', 'newData' => $data_filter['urllist']],
                                ['name' => 'status', 'orginData' => '', 'newData' => $data_filter['status']],
                                ['name' => 'belongto', 'orginData' => '', 'newData' => $data_filter['belongto']],
                                ['name' => 'date', 'orginData' => '', 'newData' => $data_filter['date']]
                            ];
                            $this->logService->logAction(
                                Session::all()['user_name'],
                                "filter",
                                "",
                                "add",
                                $details1
                            );*/
                        }
                    }
                } elseif ($type == "userAgentList-deny") {

                    $data_check = $data;

                    // $total_policy_arr = array_column($data, '2');
                    // $total_policy_arr = array_unique($total_policy_arr);
                    // $total_policy_arr = array_values($total_policy_arr);

                    // $t_policy = count($data);


                    //原始数据：策略和filter映射关系
                    // $policy_filter_data = json_decode(file_get_contents(public_path() . '/fileData/policy_filter.txt'), true) ?: [];


                    /*
                    $policy_filter=[];
                    $data_arr_temp = [];
                    $id = !empty($policy_filter) ? end($policy_filter)['id'] + 1 : 1;

                    foreach ($data as $key => $value) {
                        $new_arr = [
                            "id" => $id,
                            "policy_name" => trim($value[1]),
                            "filter_name" => trim($value[7]),
                            "remark" => 'HA Filter',
                            "date_time" => date("Y-m-d H:i:s", time())
                        ];
                        $id++;
                        $data_arr_temp[] = $new_arr;
                    }

                    $res = file_put_contents(public_path() . '/fileData/policy_filter.txt', json_encode($data_arr_temp, true));
                    */

                    // $redis = new RcategoryController();

                    // 拿filter数据
                    $redis_filter = new RfilterController();
                    $filterData = $redis_filter->getAll();
                    $filterData = array_map(function ($item) {
                        return json_decode($item, true);
                    }, $filterData);



                    /*
                    foreach ($filterData as $key => $value) {
                        
                        $urllist = array_map('trim', $value['urllist']);
                        $expandedkeys = array_map('trim', $value['expandedkeys']);
                        $data = [
                            'filter' => trim($value['filter']),
                            'desc' =>  $value['desc'],
                            'belongto' =>  $value['belongto'],
                            'expandedkeys' =>  $expandedkeys,
                            'urllist' =>  $urllist,
                            'status' =>  $value['status'],
                            'date' => $value['date'],
                        ];

                        $res = $redis_filter->update($value['id'], $data);

                        dump($res);

                    }

                    dd(22);
                    */

                    $data_arr_mapping = [];
                    foreach ($data as $k => $val) {
                        foreach ($filterData as $kk => $vv) {
                            if ($vv['desc'] != 'HA Filter') continue;
                            if (trim($vv['filter']) == trim($val[1])) {
                                $data_arr_mapping[] = $val;
                                break;
                            }
                        }
                    }



                    //已导入的filter：策略和filter映射关系 
                    $data_arr = [];
                    foreach ($data_arr_mapping as $key => $value) {

                        $data_arr[trim($value[0]) . "|||" . trim($value[2])]['filter'][] = trim($value[1]);
                        $data_arr[trim($value[0]) . "|||" . trim($value[2])]['clients'][] = trim($value[3]);
                    }

                    // $is_arr = ['160.102.233.116'];
                    // $a = 0;
                    // foreach ($data_arr as $key => $value) {
                    //     // dd($value['clients']);
                    //     // if($a == 10){
                    //         $intersect = array_intersect($is_arr,array_values($value['clients']));
                    //         if(!empty($intersect)){
                    //             foreach ($intersect as $k => $va) {
                    //                 echo "已建立policy,无法再创建policy的clients：{$va}, policy name: {$key}"."<br/>";
                    //             }
                    //         }

                    //         $diff = array_diff(array_values($value['clients']), $intersect);

                    //         if(empty($diff)) continue;

                    //     // }
                    //     $is_arr = array_merge($is_arr,array_values($value['clients']));
                    //     // $a++;
                    // }

                    // dd($data_arr);

                    // foreach ($data_arr as $key => $value) {
                    //     dd($key);
                    //     $data_arr[trim($value[0])."|||".trim($value[2])]['filter'][] = trim($value[1]);
                    //     $data_arr[trim($value[0])."|||".trim($value[2])]['clients'][] = trim($value[3]);
                    // }




                    // 整理policy数据
                    // $policy_arr = [];
                    // foreach ($data as $kkk => $vvv) {

                    //     if(trim($vvv[1]) == 'User' || trim($vvv[1]) == 'Group') continue;

                    //     $policy_arr[$vvv[2]."|||".$vvv[1]]['filter'][] = $vvv[0];

                    // }

                    $redis_ip = new RcomputerController();
                    $redis_ips = new RipRangeController();

                    // 查询已存在的ip/ips
                    $policy_arr_filter = [];
                    foreach ($data_arr as $ke => $ips) {
                        $ke_arr = explode('|||', $ke);
                        $_key = $ke_arr[0];
                        $_flag = $ke_arr[1];

                        $redis_check = $redis_ip;
                        $_flag == 'Network' && $redis_check = $redis_ips;

                        $policy_arr_filter[$ke]['filter'] = array_unique($ips['filter']);

                        foreach ($ips['clients'] as $k_str => $ip_str) {
                            if ($redis_check->exists($ip_str) === true) {
                                $policy_arr_filter[$ke]['clients'][] = $ip_str;
                            } else {
                                echo "未导入的clients： {$ip_str}" . "<br/>";
                            }
                        }
                    }

                    // dd($policy_arr_filter);

                    // $policy_arr_filter1 = [];
                    // $clinet_values_arr = [];
                    // foreach ($policy_arr_filter as $ky => $values) {
                    //     $ke_arr = explode('|||',$ky);
                    //     $_key = $ke_arr[0];
                    //     $_flag = $ke_arr[1];
                    //     foreach ($data_arr as $kky => $filters) {
                    //         if(trim($_key) == trim($kky)) {
                    //             $policy_arr_filter1[$ky][] = $values;
                    //             $policy_arr_filter1[$ky][] = $filters;
                    //             $clinet_values_arr = array_merge($clinet_values_arr,$values);
                    //         }
                    //     }

                    // }

                    // dd($data_check);
                    $redisRange = new RipRangeController();
                    $redisComputer = new RcomputerController();
                    $redis = new RstrategyController();
                    $total = 0;
                    $total_policy = 0;
                    $w_policy = [];
                    $w_policy1 = [];
                    $is_arr = [];
                    foreach ($policy_arr_filter as $policy_str => $clients_filters) {

                        $policy_arr = explode('|||', $policy_str);
                        $_strategy = $strategy = trim($policy_arr[0]);
                        $type_flag = trim($policy_arr[1]);

                        if (!isset($clients_filters['clients']) || empty($clients_filters['clients'])) {
                            echo "数据库中找不到clients，无法创建policy name: {$strategy}" . "<br/>";
                            continue;
                        }

                        $type = 4;
                        $type_flag == 'Network' && $type = 3;

                        $rescouce_content = array_values($clients_filters['clients']);
                        $filter_content = array_values($clients_filters['filter']);

                        $desc = "HA Policy";


                        $intersect = array_intersect($is_arr, $rescouce_content);

                        if (!empty($intersect)) {
                            foreach ($intersect as $k => $va) {
                                echo "已建立policy,无法再创建policy的clients：{$va}, policy name: {$strategy}" . "<br/>";
                            }
                        }

                        $diff = array_diff($rescouce_content, $intersect);

                        if (empty($diff)) continue;

                        $diff = array_values($diff);

                        $is_arr = array_merge($is_arr, $diff);

                        $redis->exists($strategy) === true && $strategy = $strategy . rand(100, 999);

                        //policy  type 1 表示user类型的  2 ad类型的  3 network类型   4 computer类型
                        $data = [
                            'strategy' => $strategy,
                            'desc' =>  $desc,
                            'status' =>  2,
                            'type' =>  $type,
                            'rescouce_content' => $diff,
                            'filter_content' => $filter_content,
                            'date' => date('Y-m-d H:m:s'),
                        ];


                        if ($id = $redis->add($data['strategy'], $data)) {
                            if ($data['type'] == 1) {
                                //绑定到user里面
                                $userinfo = json_decode(Redis::get('user_content'), true);
                                $userNameArr = array_column($userinfo, 'name');
                                foreach ($data['rescouce_content'] as $ks => $vs) {
                                    if (in_array($vs, $userNameArr)) {  //该用户在user里面则绑定belongto
                                        $key = array_search($vs, $userNameArr);
                                        $userinfo[$key]['belongto'] = $data['strategy'];
                                    }
                                }
                                Redis::set('user_content', json_encode($userinfo, true));
                            } elseif ($data['type'] == 2) {
                                //绑定到ad里面
                                $adinfo = json_decode(Redis::get('ad_content'), true);
                                $adNameArr = array_column($adinfo, 'name');
                                foreach ($data['rescouce_content'] as $ks => $vs) {
                                    if (in_array($vs, $adNameArr)) {  //该用户在user里面则绑定belongto
                                        $key = array_search($vs, $adNameArr);
                                        $adinfo[$key]['belongto'] = $data['strategy'];
                                    }
                                }
                                Redis::set('ad_content', json_encode($adinfo, true));
                            } elseif ($data['type'] == 3) {
                                //绑定到iprange里面
                                // $redisRange = new RipRangeController();
                                $redisRange->upByName($data['rescouce_content'], $data['strategy']);
                            } elseif ($data['type'] == 4) {
                                //绑定到computer里面
                                // $redisComputer = new RcomputerController();
                                $redisComputer->upByName($data['rescouce_content'], $data['strategy']);
                            } else {
                                //绑定到useragent里面
                                $redisAgent = new RagentController();
                                $redisAgent->upByName($data['rescouce_content'], $data['strategy']);
                            }
                            //记录操作日志
                            $details = [
                                ['name' => 'strategy', 'orginData' => '', 'newData' => $data['strategy']],
                                ['name' => 'desc', 'orginData' => '', 'newData' => $data['desc']],
                                ['name' => 'status', 'orginData' => '', 'newData' => $data['status']],
                                ['name' => 'type', 'orginData' => '', 'newData' => $data['type']],
                                ['name' => 'content', 'orginData' => '', 'newData' => $data['rescouce_content']],
                                ['name' => 'filter_name', 'orginData' => '', 'newData' => $data['filter_content']],
                                ['name' => 'date', 'orginData' => '', 'newData' => $data['date']]
                            ];

                            // $this->logService->logAction(
                            //     Session::all()['user_name'],
                            //     "policy",
                            //     "",
                            //     "add",
                            //     $details
                            // );

                            echo 'Success';
                            echo '<br/>';

                            // 激活
                            $ress = $this->changeStatus($id);

                            echo 'Activation successful';
                            echo '<br/>';

                            $w_policy[] = $strategy;
                            $w_policy1[] = $_strategy;
                            $total++;
                            // $total_policy = $total_policy + count($data['rescouce_content']);

                        } else {
                            echo 'strategy exists';
                        }
                    }

                    echo '<br/>';
                    echo '<br/>';
                    echo '总共策略条数：' . $total;
                    echo '<br/>';
                    echo '<br/>';
                    echo 'Clients录入条数: ' . count($is_arr);
                    echo '<br/>';

                    /*
                    $w_policy1 = array_unique($w_policy1);
                    $total_policy_arr_diff = array_diff($total_policy_arr, $w_policy1);

                    echo '<br/>';
                    echo '<pre/>';
                    print_r($clinet_values_arr);
                    echo '<br/>';
                    echo '已录入策略数: '.$total;
                    echo '<br/>';
                    echo '<br/>';
                    echo '录入策略名称: ';
                    echo '<pre/>';
                    print_r($w_policy);
                    echo '<br/>';
                    echo '去重：';
                    echo '<pre/>';
                    print_r($w_policy1);
                    echo '<br/>';
                    echo '<br/>';
                    echo '未录入策略名称（已去重）: ';
                    echo '<pre/>';
                    print_r($total_policy_arr_diff);
                    echo '<br/>';
                    echo '<br/>';
                    echo 'Client对照表总共策略条数（注：一个策略包含多条记录）：'.$t_policy;
                    echo '<br/>';
                    echo '<br/>';
                    echo 'Client对照表已录入策略条数（注：一个策略包含多条记录）: '.$total_policy;
                    echo '<br/>';
                    foreach ($data_check as $key => $value) {

                        if(in_array(trim($value[2]),$w_policy1)){
                            echo '是-->'.$value[0].'<br/>';
                        }else{
                            echo '否-->'.$value[0].'<br/>';
                        }
                    }*/
                }

                return response()->json(['message' => 'success', 'code' => 200]);
            } else {
                return response()->json(['message' => 'File upload file', 'code' => -1]);
            }
        }
    }

    public function changeStatus($id)
    {
        //status 1激活  0 未激活
        $status = 1;
        $redis = new RstrategyController();
        $redisdb1 = Redis::connection('db1');
        $strageData = $redis->get($id);
        $originType = $strageData['type'];
        if ($strageData['type'] == 1) {
            $users = $strageData['rescouce_content'];
        } elseif ($strageData['type'] == 2) {
            $ads = $strageData['rescouce_content'];
        } elseif ($strageData['type'] == 3) {
            $ranges = $strageData['rescouce_content'];
        } elseif ($strageData['type'] == 4) {
            $ips = $strageData['rescouce_content'];
        } else {
            $agents = $strageData['rescouce_content'];
        }
        $strageData['status'] = $status;
        if ($redis->update($id, $strageData)) {
            if ($status == 1) {
                //获取filter里面的数据
                $redis2 = new RfilterController();
                $allowData = [];
                $denlyData = [];
                foreach ($strageData['filter_content'] as $kkk => $vvv) {
                    $filterData = [];
                    $filterData = $redis2->getByName($vvv);

                    //filter status 1 block 2 permit
                    if ($filterData['status'] == 2) {
                        $allowData = array_merge($allowData, $filterData['urllist']);
                    } else {
                        $denlyData =  array_merge($denlyData, $filterData['urllist']);
                    }
                }
                $filterinfoData = [
                    'allow' => $allowData,
                    'deny' => $denlyData
                ];
                $serializedData = json_encode($filterinfoData, true);
                if ($originType == 1) {
                    foreach ($strageData['rescouce_content'] as $ks => $vs) {
                        //写入每个用户的key  acl:user:username  hset
                        $tAclKey = 'acl:user:' . $vs;
                        Redis::hset($tAclKey, 'serializedData', $serializedData);
                    }
                } elseif ($originType == 2) {
                    foreach ($strageData['rescouce_content'] as $ks => $vs) {
                        //写入每个用户的key  acl:ad:adname  hset
                        $tAclKey = 'acl:special:' . $vs;
                        $redisdb1->hset($tAclKey, 'serializedData', $serializedData);
                    }
                } elseif ($originType == 3) {
                    foreach ($strageData['rescouce_content'] as $ks => $vs) {
                        //写入每个ip的key  acl:iprange:iprange_address  hset
                        $tAclKey = 'acl:iprange:' . $vs;
                        Redis::hset($tAclKey, 'serializedData', $serializedData);
                    }
                } elseif ($originType == 4) {
                    //绑定到computer里面
                    foreach ($strageData['rescouce_content'] as $ks => $vs) {
                        //写入每个ip的key  acl:ip:ip_address  hset
                        $tAclKey = 'acl:ip:' . $vs;
                        Redis::hset($tAclKey, 'serializedData', $serializedData);
                    }
                } else {
                    foreach ($strageData['rescouce_content'] as $ks => $vs) {
                        //写入每个ip的key  acl:user_agent:user_agent  hset
                        $tAclKey = 'acl:user_agent:' . $vs;
                        Redis::hset($tAclKey, 'serializedData', $serializedData);
                    }
                }
            } else {
                if ($originType == 1) {
                    //删除原来的 acl:user:username , 将原来的user内容解绑
                    foreach ($users as $k => $user) {
                        $tKey = 'acl:user:' . $user;
                        Redis::del($tKey);
                    }
                } elseif ($originType == 2) {
                    //删除原来的 acl:ad:adname , 将原来的ad内容解绑
                    foreach ($ads as $k => $adname) {
                        $tKey = 'acl:special:' . $adname;
                        $redisdb1->del($tKey);
                    }
                } elseif ($originType == 3) {
                    //删除原来的 acl:iprange:range 
                    foreach ($ranges as $k => $range) {
                        $tKey = 'acl:iprange:' . $range;
                        Redis::del($tKey);
                    }
                } elseif ($originType == 4) {
                    //删除原来的 acl:ip:ip 
                    foreach ($ips as $k => $ip) {
                        $tKey = 'acl:ip:' . $ip;
                        Redis::del($tKey);
                    }
                } else {
                    //删除原来的 acl:user_agent:user_agent 
                    foreach ($agents as $k => $agent) {
                        $tKey = 'acl:user_agent:' . $agent;
                        Redis::del($tKey);
                    }
                }
            }
        }
        return true;
    }

    public function validateIp($data)
    {

        foreach ($data as $key => $value) {

            $check_ip = trim($value[0]);

            // 允许的字符：数字、点号（IPv4）、冒号（IPv6）、小写字母（IPv6）
            $allowedChars = '/[^0-9a-fA-F\.\:]/';
            if (preg_match($allowedChars, $check_ip)) {
                return ['message' => 'Invalid IP, on line ' . ($key + 2), 'code' => -1]; // 包含非法字符
            }

            // 检查是否为 IPv4 地址
            if (!filter_var($check_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['message' => 'Invalid IP, on line ' . ($key + 2), 'code' => -1];
            }
        }

        // 去重處理
        $jsonKeys = [];
        $result = [];
        foreach ($data as $item) {
            $json = json_encode(trim($item[0]));
            if (!in_array($json, $jsonKeys)) {
                $jsonKeys[] = $json;
                $result[] = $item;
            }
        }

        return $result;
    }


    public function sys_status(Request $request)
    {
        $return = file_get_contents(public_path() . "/../system_usage.json");
        $info = json_decode($return, true);
        $tcpData = file_get_contents(public_path() . "/fileData/threat/netstat_status.json") ?? [];
        $tcp = json_decode($tcpData, true);
        //dd($info['2024-12-06_00']["system_usage"]['cpu_usage_percent']);
        $dates = [];
        $today = date('Y-m-d');
        $dates[] = $today;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $dates[] = $yesterday;
        $lastyesterday = date('Y-m-d', strtotime('-2 day'));
        $dates[] = $lastyesterday;
        $data = range('00', '24');
	$return = [];
	//dd($info);
        foreach ($dates as $ks => $vs) {
            foreach ($data as $k => $v) {
                if ($v < 10) $v = "0" . $v;
                $times = $vs . "_" . $v;
                if (empty($info[$times])) {
                    $return[$vs]['cpu_usage_percent'][] = 0;
                } else {
                    $return[$vs]['cpu_usage_percent'][] = $info[$times]['system_usage']['cpu_usage_percent'];
                }
                if (empty($info[$times])) {
                    $return[$vs]['memory_usage_percent'][] = 0;
                } else {
                    $return[$vs]['memory_usage_percent'][] = $info[$times]['system_usage']['memory_usage_percent'];
                }
                if (empty($info[$times])) {
                    $return[$vs]['disk_usage_percent'][] = 0;
                } else {
                    $return[$vs]['disk_usage_percent'][] = $info[$times]['system_usage']['disk_usage_percent'];
		}
		if (empty($info[$times])) {
                    $return[$vs]['received_bytes'][] = 0;
		} else {
		    $return[$vs]['received_bytes'][] = round(($info[$times]['network_usage']['received_bytes_max']??0 / 1000000), 2);
                }
                if (empty($info[$times])) {
                    $return[$vs]['sent_bytes'][] = 0;
		} else {
		    $return[$vs]['sent_bytes'][] = round(($info[$times]['network_usage']['sent_bytes_max']??0 / 1000000), 2);
                }
                if (empty($tcp[$times])) {
                    $return[$vs]['User To Proxy'][] = 0;
                    $return[$vs]['Proxy To Outside'][] = 0;
                } else {
                    $return[$vs]['User To Proxy'][] = $tcp[$times]['ESTABLISHED'] ?? 0;
                    $return[$vs]['Proxy To Outside'][] = $tcp[$times]['ESTABLISHED2'] ?? 0;
                }
            }
        }
        return response()->json(['data' => $return, 'message' => 'success', 'code' => 200]);
    }

    public function squid_list(Request $request)
    {
        $Serverdata = json_decode(file_get_contents(public_path() . $this->squidServerlist), true) ?? [];
        $ssLink = [];
        $i = 0;
        foreach ($Serverdata as $k => $v) {
            $ssLink[$i]['id'] = $v['id'];
            $ssLink[$i]['ip'] = $v['ip'];
            $i++;
        }

        return response()->json(['data' => $ssLink, 'message' => 'success', 'code' => 200]);
    }

    public function threat_version(Request $request)
    {
        $threatdata = json_decode(file_get_contents(public_path() . $this->threatV), true) ?? [];
        return response()->json(['data' => $threatdata, 'message' => 'success', 'code' => 200]);
    }

    public function restart_squid(Request $request)
    {
        ob_flush();
        flush();
        $orginTime      =   Redis::get('restart_squid_failed_time') ? Redis::get('restart_squid_failed_time') : Redis::get('restart_squid_time');
        $orginReason    =   Redis::get('restart_squid_failed_reason') ? Redis::get('restart_squid_failed_reason') : 'Opp service reload successfully';
        $orginStatus    =   Redis::get('restart_squid_failed_reason') ? 'failure' : 'success';
        $command = "sudo /opt/squid/sbin/squid -k reconfigure 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        // squid原生配置错误
        if ($return !== 0) {

            $this->remark_squid_reload_failed_log('Opp service reload failed, pls check opp conf',$orginStatus,$orginReason,$orginTime);
            return response()->json(['message' => "Opp service reload failed, pls check opp conf", 
                'code' => -1,'reload_failed_time' => Redis::get('restart_squid_failed_time'),'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
        }
	/*
        $python_file_addr="\/opt\/py_test_new\/";
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
        // external_acl_type check_ip_allow_check ttl=0 negative_ttl=0 s  children-max=100 s %SRC %DST /usr/bin/python3 /opt/py_test_new/check_ip_allow.py
        $check_ip_allow=false;
        //2、检测 check_ip_deny.py
        // external_acl_type check_ip_deny_check ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_test_new/check_ip_deny.py
        $check_ip_deny=false;
        //3、检测 check_iprange_allow.py
        // external_acl_type check_iprange_allow_check ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_test_new/check_iprange_allow.py
        $check_iprange_allow=false;
        //4、检测 check_iprange_deny.py
        // external_acl_type check_iprange_deny_check  ttl=0 negative_ttl=0  children-max=100  %SRC %DST /usr/bin/python3 /opt/py_test_new/check_iprange_deny.py
        $check_iprange_deny=false;
        //5、检测 check_special_user_allow.py
        // external_acl_type special_user_allow_check ttl=0 negative_ttl=0  children-max=100  %LOGIN %DST /usr/bin/python3 /opt/py_test_new/check_special_user_allow.py
        $check_special_user_allow=false;
        //6、检测 check_special_user_deny.py
        // external_acl_type special_user_deny_check ttl=0 negative_ttl=0   children-max=100  %LOGIN %DST /usr/bin/python3 /opt/py_test_new/check_special_user_deny.py
        $check_special_user_deny=false;
        //7、检测 check_user.py
        // external_acl_type check_user ttl=0 negative_ttl=0  children-max=100  %LOGIN %URI /usr/bin/python3 /opt/py_test_new/check_user.py
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
        /*
        $python_file_addr_spec="/opt/py_test_new/";
        // 检测py文件语法是否错误
        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_ip_allow.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_ip_allow.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_ip_deny.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_ip_deny.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_iprange_allow.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_iprange_allow.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_iprange_deny.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_iprange_deny.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_special_user_allow.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_special_user_allow.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_special_user_deny.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_special_user_deny.py has a syntax error", 'code' => -1]);

        $command = "sudo /usr/bin/python3 {$python_file_addr_spec}check_user.py 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
        if ($return !== 0) return response()->json(['message' => "The program check_user.py has a syntax error", 'code' => -1]);
        */

        // 执行成功
        Redis::set('restart_squid_time', date("Y-m-d H:i:s"));
        Redis::set('restart_squid_failed_time', '');
        Redis::set('restart_squid_failed_reason', '');
        //记录操作日志
        $details = [
            ['name' => 'status', 'orginData' => $orginStatus, 'newData' => 'success'],
            ['name' => 'reason', 'orginData' => $orginReason, 'newData' => 'Opp service reload successfully'],
            ['name' => 'date', 'orginData' => $orginTime, 'newData' => Redis::get('restart_squid_time')]
        ];
        $this->logService->logAction(
            Session::all()['user_name'],
            "system status",
            "",
            "reload",
            $details
        );
        return response()->json(['message' => "Opp service reload successfully", 'code' => 200, 'reload_time' => Redis::get('restart_squid_time')]);
   
    }

    public function remark_squid_reload_failed_log($log, $orginStatus, $orginReason, $orginTime)
    {
        Redis::set('restart_squid_failed_time', date("Y-m-d H:i:s"));
        Redis::set('restart_squid_failed_reason', $log);
        //记录操作日志
        $details = [
            ['name' => 'status', 'orginData' => $orginStatus, 'newData' => 'failure'],
            ['name' => 'reason', 'orginData' => $orginReason, 'newData' => $log],
            ['name' => 'date', 'orginData' => $orginTime, 'newData' => Redis::get('restart_squid_failed_time')]
        ];
        $this->logService->logAction(
            Session::all()['user_name'],
            "system status",
            "",
            "reload",
            $details
        );
    }

    public function validateSquidRule($rule)
    {
        // 检查规则是否以 external_acl_type 开头
        if (!preg_match('/^external_acl_type\s+/', $rule)) {
            return false;
        }

        // 移除 external_acl_type 部分，专注于参数检查
        $params = trim(preg_replace('/^external_acl_type\s+\w+\s*/', '', $rule));

        // 定义允许的参数模式
        $allowedParams = [
            'ttl' => '\d+',
            'negative_ttl' => '\d+',
            'children-max' => '\d+'
        ];

        // 检查参数部分是否只包含允许的参数
        $paramPattern = '/^(' . implode('|', array_keys($allowedParams)) . ')=\d+$/';

        // 分割参数并检查每个参数
        $paramParts = preg_split('/\s+/', $params);

        foreach ($paramParts as $part) {
            // 跳过 %SRC 和 %DST 变量
            if ($part === '%SRC' || $part === '%DST') {
                continue;
            }

            // 检查是否是脚本路径（包含斜杠）
            if (strpos($part, '/') !== false) {
                continue;
            }

            // 检查参数格式是否正确
            if (!preg_match($paramPattern, $part)) {
                return false;
            }
        }

        return true;
    }


    public function validateSquidSpecialUserRule($rule)
    {
        // 检查规则是否以 external_acl_type 开头
        if (!preg_match('/^external_acl_type\s+/', $rule)) {
            return false;
        }

        // 移除 external_acl_type 部分，专注于参数检查
        $params = trim(preg_replace('/^external_acl_type\s+\w+\s*/', '', $rule));

        // 定义允许的参数模式
        $allowedParams = [
            'ttl' => '\d+',
            'negative_ttl' => '\d+',
            'children-max' => '\d+'
        ];

        // 检查参数部分是否只包含允许的参数
        $paramPattern = '/^(' . implode('|', array_keys($allowedParams)) . ')=\d+$/';

        // 分割参数并检查每个参数
        $paramParts = preg_split('/\s+/', $params);

        foreach ($paramParts as $part) {
            // 跳过 %LOGIN 和 %DST 变量
            if ($part === '%LOGIN' || $part === '%DST') {
                continue;
            }

            // 检查是否是脚本路径（包含斜杠）
            if (strpos($part, '/') !== false) {
                continue;
            }

            // 检查参数格式是否正确
            if (!preg_match($paramPattern, $part)) {
                return false;
            }
        }

        return true;
    }


    public function validateSquidUserRule($rule)
    {
        // 检查规则是否以 external_acl_type 开头
        if (!preg_match('/^external_acl_type\s+/', $rule)) {
            return false;
        }

        // 移除 external_acl_type 部分，专注于参数检查
        $params = trim(preg_replace('/^external_acl_type\s+\w+\s*/', '', $rule));

        // 定义允许的参数模式
        $allowedParams = [
            'ttl' => '\d+',
            'negative_ttl' => '\d+',
            'children-max' => '\d+'
        ];

        // 检查参数部分是否只包含允许的参数
        $paramPattern = '/^(' . implode('|', array_keys($allowedParams)) . ')=\d+$/';

        // 分割参数并检查每个参数
        $paramParts = preg_split('/\s+/', $params);

        foreach ($paramParts as $part) {
            // 跳过 %LOGIN 和 %URI 变量
            if ($part === '%LOGIN' || $part === '%URI') {
                continue;
            }

            // 检查是否是脚本路径（包含斜杠）
            if (strpos($part, '/') !== false) {
                continue;
            }

            // 检查参数格式是否正确
            if (!preg_match($paramPattern, $part)) {
                return false;
            }
        }

        return true;
    }

    public function getrestart_time(Request $request)
    {
        return response()->json(['message' => "last reload time", 'code' => 200, 'reload_time' => Redis::get('restart_squid_time'), 'ad_update_time' => Redis::get('ad_update_time'), 'reload_failed_time' => Redis::get('restart_squid_failed_time'), 'reload_failed_reason' => Redis::get('restart_squid_failed_reason')]);
    }

    public function bandwith_tcp()
    {
        $output = 0;
        $output2 = 0;
	//$cmd = "sudo ss -ant | awk '\$1==\"ESTAB\" && \$4 !~ /:8080\$/ && \$5 !~ /:8080\$/ && \$5 !~ /^127\\.0\\.0\\.1:/ && \$5 ~ /:443|:80/ {print}' | wc -l 2>&1";
	$cmd = "/usr/sbin/ss -ant | awk '\$1==\"ESTAB\" && \$4 !~ /:8080\$/ && \$5 !~ /:8080\$/ && \$5 !~ /^127\\.0\\.0\\.1:/ && \$5 ~ /:443|:80/ {print}' | wc -l 2>&1";
	exec($cmd,$o,$r);
	//exec('sudo ss -ant | awk \'$1=="ESTAB" && $4 ~ /:8080$/{print}\' | wc -l 2>&1',$o2,$r2);
	exec('/usr/sbin/ss -ant | awk \'$1=="ESTAB" && $4 ~ /:8080$/{print}\' | wc -l 2>&1',$o2,$r2);
        //$command2 = "sar -n DEV 1 1 | awk '$2 != \"IFACE\" && $2 != \"lo\" { sum = $5 + $6; if (sum > max) max = sum } END { printf \"%.2f\\n\", max * 8 / 1000 }'";
        $command2 = "/usr/bin/sar -n DEV 1 1 | awk '$2 != \"IFACE\" && $2 != \"lo\" { sum = $5 + $6; if (sum > max) max = sum } END { printf \"%.2f\\n\", max * 8 / 1000 }'";
        $output2 = shell_exec($command2);
        return response()->json(['data' => ['tcpnum' => $o2[0], 'tcpnum2'=>$o[0], 'bandwithnum' => $output2], 'message' => 'success', 'code' => 200]);
    }

    /**
     * 导出数据到 XLS 文件
     * @param array $headers 表头数据（一维数组）
     * @param array $data 表格内容（二维数组）
     * @param string $fileName 导出文件名（不带扩展名）
     * @param bool $output 是否直接输出到浏览器（否则返回文件路径）
     * @return string|bool 成功时返回文件路径（$output=false），或布尔值（$output=true）
     */
    public function one_click_export(Request $request)
    {

        ini_set('memory_limit', '256M');
        set_time_limit(600);

        try {

            // 创建新的 Spreadsheet 对象
            $spreadsheet = new Spreadsheet();
            $data_row = 1;

            /**********************源ip白名单**********************/
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Source IPs White List');
            $headers = ['No.', 'IPs', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $ipwhitelist = json_decode(file_get_contents(public_path() . '/fileData/ipwhitelist.txt'), true) ?? [];
            if (!empty($ipwhitelist)) {
                $ipwhitelist = array_values($ipwhitelist);
                foreach ($ipwhitelist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['ip'];
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet1, $headers, $export_arr, $data_row);
            /**********************源ip白名单**********************/


            /**********************源ip黑名单**********************/
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Source IPs Black List');
            $headers = ['No.', 'IPs', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $ipblacklist = json_decode(file_get_contents(public_path() . '/fileData/ipblacklist.txt'), true) ?? [];
            if (!empty($ipblacklist)) {
                $ipblacklist = array_values($ipblacklist);
                foreach ($ipblacklist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['ip'];
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet2, $headers, $export_arr, $data_row);
            /**********************源ip黑名单**********************/

            /**********************user agent名单**********************/
            $sheet3 = $spreadsheet->createSheet();
            $sheet3->setTitle('User Agent List');
            $headers = ['No.', 'User Agent', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $useragentlist = json_decode(file_get_contents(public_path() . '/fileData/useragentlist.txt'), true) ?? [];
            if (!empty($useragentlist)) {
                $useragentlist = array_values($useragentlist);
                foreach ($useragentlist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['user_agent'];
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet3, $headers, $export_arr, $data_row);
            /**********************user agent名单**********************/

            /**********************目的ip白名单**********************/
            $sheet4 = $spreadsheet->createSheet();
            $sheet4->setTitle('Destination IPs White List');
            $headers = ['No.', 'IPs', 'Port', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $dest_ip_whitelist = json_decode(file_get_contents(public_path() . '/fileData/dest_ip_whitelist.txt'), true) ?? [];
            if (!empty($dest_ip_whitelist)) {
                $dest_ip_whitelist = array_values($dest_ip_whitelist);
                foreach ($dest_ip_whitelist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['ip'];
                    $export_arr[$key][] = $value['port_name'] ?? '';
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet4, $headers, $export_arr, $data_row);
            /**********************目的ip白名单**********************/

            /**********************目的ip黑名单**********************/
            $sheet5 = $spreadsheet->createSheet();
            $sheet5->setTitle('Destination IPs Black List');
            $headers = ['No.', 'IPs', 'Port', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $dest_ip_blacklist = json_decode(file_get_contents(public_path() . '/fileData/dest_ip_blacklist.txt'), true) ?? [];
            if (!empty($dest_ip_blacklist)) {
                $dest_ip_blacklist = array_values($dest_ip_blacklist);
                foreach ($dest_ip_blacklist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['ip'];
                    $export_arr[$key][] = $value['port_name'] ?? '';
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet5, $headers, $export_arr, $data_row);
            /**********************目的ip黑名单**********************/


            /**********************Free url名单**********************/
            $sheet6 = $spreadsheet->createSheet();
            $sheet6->setTitle('Free Url List');
            $headers = ['No.', 'URL', 'Port', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $urlset = json_decode(file_get_contents(public_path() . '/fileData/urlset.txt'), true) ?? [];
            if (!empty($urlset)) {
                $urlset = array_values($urlset);
                foreach ($urlset as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['url'];
                    $export_arr[$key][] = $value['port_name'] ?? '';
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet6, $headers, $export_arr, $data_row);
            /**********************Free url名单**********************/

            /**********************url白名单**********************/
            $sheet7 = $spreadsheet->createSheet();
            $sheet7->setTitle('Destination Url White List');
            $headers = ['No.', 'URL', 'Port','Combination Verification', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $urlwhitelist = json_decode(file_get_contents(public_path() . '/fileData/urlwhitelist.txt'), true) ?? [];
            if (!empty($urlwhitelist)) {
                $urlwhitelist = array_values($urlwhitelist);
                foreach ($urlwhitelist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['url'];
                    $export_arr[$key][] = $value['port_name'] ?? '';
                    if(!isset($value['combination_verification'])) {
                        $export_arr[$key][] = 'No';
                    }else{
                        $export_arr[$key][] = $value['combination_verification'] == 1 ? 'Yes' : 'No'; 
                    }
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet7, $headers, $export_arr, $data_row);
            /**********************url白名单**********************/


            /**********************url黑名单**********************/
            $sheet8 = $spreadsheet->createSheet();
            $sheet8->setTitle('Destination Url Black List');
            $headers = ['No.', 'URL', 'Port', 'Device', 'Department', 'Date', 'Remark'];
            $export_arr = [];
            $urlblacklist = json_decode(file_get_contents(public_path() . '/fileData/urlblacklist.txt'), true) ?? [];
            if (!empty($urlblacklist)) {
                $urlblacklist = array_values($urlblacklist);
                foreach ($urlblacklist as $key => $value) {
                    $export_arr[$key][] = $key + 1;
                    $export_arr[$key][] = $value['url'];
                    $export_arr[$key][] = $value['port_name'] ?? '';
                    $export_arr[$key][] = $value['pc_name'] ?? '';
                    $export_arr[$key][] = $value['department'] ?? '';
                    $export_arr[$key][] = $value['date_time'];
                    $export_arr[$key][] = $value['remark'] ?? '';
                }
            }
            $this->inputDataToXls($sheet8, $headers, $export_arr, $data_row);
            /**********************url黑名单**********************/


            /**********************精细化策略名单**********************/
            $sheet9 = $spreadsheet->createSheet();
            $sheet9->setTitle('Special Policy List');
            $headers = ['No.', 'Policy', 'Filters', 'Allow Or Deny', 'Filters Details', 'Clients', 'Type', 'Remark', 'Date'];
            $export_arr = [];
            $redis_filter = new RfilterController();          
            $redis = new RstrategyController();
            $iprangeData = $redis->getAll();
            $iprangeData = array_map(function ($item) {
                return json_decode($item, true);
            }, $iprangeData);
            if (!empty($iprangeData)) {
                $iprangeData = array_values($iprangeData);
                $index = 0;
                foreach ($iprangeData as $k => $val) {
                    $strategy = $val['strategy'] ?? '';
                    $desc = $val['desc'] ?? '';
                    $client_content = '';
                    // if (!empty($val['filter_content'])) $filter_content = implode(',', $val['filter_content']);
                    if (!empty($val['rescouce_content'])) $client_content = implode(', ', $val['rescouce_content']);

                    if ($val['type'] == 4) {
                        $type = 'Computer';
                    } elseif ($val['type'] == 3) {
                        $type = 'Networks';
                    } elseif ($val['type'] == 2) {
                        $type = 'Group';
                    } else {
                        $type = 'User';
                    }
                    // foreach ($val['rescouce_content'] as $kk => $client) {
                    foreach ($val['filter_content'] as $kk => $filter) {
                
                        $redis_filter_data_res = $redis_filter->searchData(['filter'=>trim($filter)], 1);
                        //$redis_filter_data_res = $redis_filter->searchData(['filter'=>'20250804'], 1);
                        if(empty($redis_filter_data_res['data'])) continue;
                        $redis_filter_data = $redis_filter_data_res['data'][0];
                        $filter_type = $redis_filter_data['status'] == '2' ? 'Allow' : 'Deny' ;
                        $filter_detail = implode(', ', $redis_filter_data['urllist']);

                        $export_arr[$index][] = $index + 1;
                        $export_arr[$index][] = $strategy;
                        $export_arr[$index][] = $filter;
                        $export_arr[$index][] = $filter_type;
                        $export_arr[$index][] = $filter_detail;
                        $export_arr[$index][] = $client_content;
                        $export_arr[$index][] = $type;
                        $export_arr[$index][] = $desc;
                        $export_arr[$index][] = $val['date'];
                        $index++;
                    }
                }
	    }
            $this->inputDataToXls($sheet9, $headers, $export_arr, $data_row);
            /**********************精细化策略名单**********************/


            // 创建 IOFactory 实例
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            // 导出文件名
	    $fileName = 'policy_data_' . time();
            //$output为true直接输出到浏览器, 为false保存到服务器
            $output = false;

            if ($output) {
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . $fileName . '.xls"');
                header('Cache-Control: max-age=0');
                $writer->save('php://output');
                return response()->json(['message' => 'Export success', 'code' => 200]);
            } else {
                $dir = public_path()  . '/policy_export/';
                if (!is_dir($dir)) {
                    // 创建目录并设置权限为777
                    mkdir($dir, 0777, true);
                }
                $filePath = $dir . $fileName . '.xls';
                $writer->save($filePath);
                //$export_addr  = env('BACK_END_ADDR', 'https://transproxy.swd.ha.org.hk:8000');
                //$downloadUrl = $export_addr . '/fileData/export/' . $fileName . '.xls';
                $downloadUrl = '/policy_export/' . $fileName . '.xls';
                return response()->json(['message' => 'Export success', 'code' => 200, 'fileName' => $fileName . '.xls', 'downloadUrl' => $downloadUrl]);
            }
        } catch (\Exception $e) {
            // 错误处理
            error_log('导出 XLS 失败: ' . $e->getMessage());
            return response()->json(['message' => 'Export fail', 'code' => -1]);
        } finally {
            // 释放内存
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        exit;
    }

    function inputDataToXls($sheet, array $headers, array $data, $data_row)
    {
        // 设置表头
        if (!empty($headers)) {
            foreach ($headers as $colIndex => $header) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $data_row, $header);
            }
        }

        $data_row = $data_row + 1;
        // 设置数据
        if (!empty($data)) {
            foreach ($data as $rowIndex => $rowData) {
                foreach ($rowData as $colIndex => $cellValue) {
                    $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + $data_row, $cellValue);
                }
            }
        }

        // 自动调整列宽
        foreach (range('A', $sheet->getHighestDataColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function autoLoginoutLog(Request $request)
    {
        $redisdb1 = Redis::connection('db1');
        $now = date('Y-m-d H:i:s');
        $cursor = null;
        $redisdb1->setOption(4, 1);
        // 扫描所有在线用户 Key
        while ($keys = $redisdb1->scan($cursor, 'userLoginStatus:*')) {
            foreach ($keys as $key) {
                $ttl = $redisdb1->ttl($key);
                if ($ttl < 0) {                 // 已过期
                    $data = $redisdb1->hGetAll($key);
                    if ($data) {
                        // 写登出日志
                        $this->logService->logAction(
                            Session::all()['user_name'],
                            "login",
                            "",
                            "logout",
                            "Logout success,operate the computer:" . $data['login_computer']
                        );
                        // 可选：删除该键
                        $redisdb1->del($key);
                    }
                }
            }
        }
    }
    public function released_version(Request $request)
    {
        // 获取当前下发的版本号
        $uat_current_version = '';
        $production_current_version = '';
        if(file_exists(public_path().$this->released_version_txt)){
            $tmpData = array_values(array_filter(explode("\n", file_get_contents(public_path() . $this->released_version_txt))));
            if(!empty($tmpData)){
                $version_data = array_map(function ($item) {
                    return json_decode($item, true);
                }, $tmpData);
                // 倒序
                $version_data = array_reverse($version_data);
                foreach ($version_data as $k => $val) {
                    if($this->hasString($version_data,'released_version_type','PRODUCTION') === false) break;
                    if($val['released_version_type'] == 'PRODUCTION') {
                        $production_current_version = $val['released_version'];
                        break;
                    }
                }
            }
        }
        return response()->json(['message' => 'Success','code' => 200,'production_current_version' => $production_current_version]);
    }

    public function hasString($array, $colume, $index)
    {
	$adminNames = array_column($array, $colume);
    	return in_array($index, $adminNames);
    }


    public function get_connection_status_data(Request $request) {
 	set_time_limit(0);
	ini_set('memory_limit', '-1');
	$_params = $request->input();
	if(empty($_params['server_time'])){
	  exit("PARAM_ERROR");
	}
	$server_time = escapeshellarg($_params['server_time']);

	//$filename = "data_".uniqid().".json";
	$filename = "data_connection_status.json";
	$save_path = public_path()."/".$filename;
	//$result = file_get_contents(public_path() . '/fileData/connection_status_test.txt');
	//file_put_contents($save_path, $result);

	//$server_time = 300;
	$command = "/opt/squid_shell/stat.sh ".$server_time." > ".$save_path;
	// 执行，输出直接写入文件
	exec($command, $output, $return);
	if ($return !== 0) {
    	  exit("EXEC_FAILED");
	}	
	// 返回文件名
	echo $filename;
	exit;
    }
}
