<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\RadiusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;
use phpseclib3\Net\SSH2;
use SplFileObject;
use Illuminate\Support\Str;

class ThreatController extends Controller
{
    private $sortList = '/fileData/threat/threat-sort.txt';
    private $threatV = '/fileData/threat/threat_version.txt';
    private $fileData = '/fileData/threat/';
    private $setAlert = '/fileData/threat/set-alert.txt';
    private $ipReport = '/fileData/threat/week_top_ip_10.json';
    private $urlReport = '/fileData/threat/week_top_url_10.json';
    private $logReport = '/fileData/threat/logstatus_7days.json';
    private $upFile = '/uploadFile/';
    private $pypath = '/usr/nginx/html/threat/archive/';
    private $blackSummary = '/fileData/threat/blacklist_summary.json';
    private $porn_str = '';
    private $gambling_str = '';
    private $phishing_str = '';
    private $ransomware_str = '';
    private $botnet_str = '';
    private $c2_str = '';
    private $malicious_website_str = '';

    public function index(Request $request)
    {
        $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
        return response()->json(['code' => 200, 'data' => $sortData]);
    }

    public function upThreat()
    {
        ini_set('memory_limit', '15G');
        set_time_limit(0);
        $skip_web = env('SKIP_THREAT_WEB', '');  //这些域名不添加到天际友盟
        if (!empty($skip_web)) {
            $skip_web = explode(',', $skip_web);
        }

        $fileName = $this->pypath . "IOCS_" . date("Y-m-d", time()) . "_1.csv";
        $rowLimit = 1000; // 每次处理的行数
        // 打开 CSV 文件
        $fileHandle = fopen($fileName, 'r');
        $rowCount = 0;
        $batchData = [];
        while (($row = fgetcsv($fileHandle)) !== false) {
            // 处理每一行数据
            $batchData[] = $row;
            $rowCount++;
            // 如果达到批处理行数限制，处理这批数据
            if ($rowCount >= $rowLimit) {
                $this->processBatch($batchData, $skip_web);
                $batchData = [];
                $rowCount = 0;
            }
        }
        // 处理剩余的数据
        if (!empty($batchData)) {
            $this->processBatch($batchData, $skip_web);
        }
        fclose($fileHandle);
        $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
        $ThreatData = json_decode(file_get_contents(public_path() . $this->threatV), true);
        $total = 0;
        foreach ($sortData as $ks => $sort) {
            $type = $sort['name'];
            $path =  $this->fileData . $sort['name'] . '.txt';
            //exec("sudo /bin/chmod 777  $path");
            if ($type == 'porn' && !empty($this->porn_str)) {
                $pNum =  count(array_unique(explode("\n", $this->porn_str))) - 1;
                $this->porn_str = join("\n", array_unique(explode("\n", $this->porn_str)));
                $sortData[$ks]['num'] = $pNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->porn_str);
            } elseif ($type == 'gambling' && !empty($this->gambling_str)) {
                $gNum =  count(array_unique(explode("\n", $this->gambling_str))) - 1;
                $this->gambling_str = join("\n", array_unique(explode("\n", $this->gambling_str)));
                $sortData[$ks]['num'] = $gNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->gambling_str);
            } elseif ($type == 'phishing' && !empty($this->phishing_str)) {
                $phNum =  count(array_unique(explode("\n", $this->phishing_str))) - 1;
                $this->phishing_str = join("\n", array_unique(explode("\n", $this->phishing_str)));
                $sortData[$ks]['num'] = $phNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->phishing_str);
            } elseif ($type == 'ransomware' && !empty($this->ransomware_str)) {
                $sNum =  count(array_unique(explode("\n", $this->ransomware_str))) - 1;
                $this->ransomware_str = join("\n", array_unique(explode("\n", $this->ransomware_str)));
                $sortData[$ks]['num'] = $sNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->ransomware_str);
            } elseif ($type == 'botnet' && !empty($this->botnet_str)) {
                $bNum =  count(array_unique(explode("\n", $this->botnet_str))) - 1;
                $this->botnet_str = join("\n", array_unique(explode("\n", $this->botnet_str)));
                $sortData[$ks]['num'] = $bNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->botnet_str);
            } elseif ($type == 'c2' && !empty($this->c2_str)) {
                $c2Num =  count(array_unique(explode("\n", $this->c2_str))) - 1;
                $this->c2_str = join("\n", array_unique(explode("\n", $this->c2_str)));
                $sortData[$ks]['num'] = $c2Num;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->c2_str);
            } elseif ($type == 'malicious_website' && !empty($this->malicious_website_str)) {
                $maNum =  count(array_unique(explode("\n", $this->malicious_website_str))) - 1;
                $this->malicious_website_str = join("\n", array_unique(explode("\n", $this->malicious_website_str)));
                $sortData[$ks]['num'] = $maNum;
                file_put_contents(public_path() . $this->fileData . $sort['name'] . '.txt', $this->malicious_website_str);
            }
            $total += $sortData[$ks]['num'];
            $sortData[$ks]['upTime'] = date("Y-m-d H:i:s", time());
        }
        $ThreatData['num'] =  $total;
        $ThreatData['version'] =  "v" . date("Y-m-d", time());
        $ThreatData['up_time'] =  date("Y-m-d H:i:s", time());
        file_put_contents(public_path() . $this->sortList, json_encode($sortData, true));
        file_put_contents(public_path() . $this->threatV, json_encode($ThreatData, true));

	/*
        $command = "sudo /opt/squid/sbin/squid -k reconfigure 2>&1 ";
        $output = [];
        $return = 0;
        exec($command, $output, $return);
	*/
        return response()->json(['code' => 200, 'message' => 'success']);
    }

    public function processBatch($data, $skip_web)
    {
        foreach ($data as $tmp) {
            if ($tmp[1] == 'category') {
                continue;
            }
            $type = $tmp[1];

            $_temp = $tmp[0];

            if (!empty($skip_web)) {
                if (in_array($_temp, $skip_web)) {
                    continue;
                }
            }

            //$tmp[0] = preg_replace('/^www\./i', '', $tmp[0]);
            $tmp[0] = "." . $tmp[0];

            if ($type == 'porn') {
                $this->porn_str .= $tmp[0] . "\n";
            } elseif ($type == 'gambling') {
                $this->gambling_str .= $tmp[0] . "\n";
            } elseif ($type == 'phishing') {
                $this->phishing_str .= $tmp[0] . "\n";
            } elseif ($type == 'ransomware' || $type == 'downloader' || $type == 'malware' || $type == 'proxy' || $type == 'spam' || $type == 'digiccy') {
                $this->ransomware_str .= $tmp[0] . "\n";
            } elseif ($type == 'botnet') {
                $this->botnet_str .= $tmp[0] . "\n";
            } elseif ($type == 'c2') {
                $this->c2_str .= $tmp[0] . "\n";
            } elseif ($type == 'malicious_website') {
                $this->malicious_website_str .= $tmp[0] . "\n";
            }
        }
    }

    public function ipReport(Request $request)
    {
        $ipReport = json_decode(file_get_contents(public_path() . $this->ipReport), true) ?? [];
        $return = [];
        $return['iplist'] = $ipReport['iplist'];
        $return['name'] = $ipReport['data'];
        $return['info'] = [];
        if (!empty($return['iplist'][0])) {
            foreach ($return['iplist'] as $k => $v) {
                foreach ($return['name'] as $ks => $vs) {
                    $num = $ipReport[trim($vs, "\n")][$k];
                    $return['info'][$v][] = $num;
                }
            }
        }
        return response()->json(['code' => 200, 'data' => $return]);
    }

    public function urlReport(Request $request)
    {
        $urlReport = json_decode(file_get_contents(public_path() . $this->urlReport), true) ?? [];
        $return = [];
        $return['iplist'] = $urlReport['iplist'];
        $return['name'] = $urlReport['data'];
        $return['info'] = [];
        if (!empty($return['iplist'][0])) {
            foreach ($return['iplist'] as $k => $v) {
                foreach ($return['name'] as $ks => $vs) {
                    $num = $urlReport[trim($vs, "\n")][$k];
                    $return['info'][$v][] = $num;
                }
            }
        }
        return response()->json(['code' => 200, 'data' => $return]);
    }

    public function logReport()
    {
        $logReport = json_decode(file_get_contents(public_path() . $this->logReport), true);
        $params = ['403' => "403 Forbidden", "407" => "407 Proxy Authentication Required", "502" => "502 Bad Gateway", "504" => "504 Gateway Timeout"];
        $return = [];
        $return['date'] = array_keys($logReport);
        $return['name'] = array_keys(array_values($logReport)[0]);
        $return['info'] = [];
        foreach ($return['date'] as $k => $v) {
            foreach ($return['name'] as $ks => $vs) {
                $return['info'][$vs][] = $logReport[$v][$vs];
            }
        }
        foreach ($return['info'] as $kb => $vb) {
            $return['info'][$params[$kb]] = $vb;
            unset($return['info'][$kb]);
        }
        $return['name'] = array_values($params);
        return response()->json(['code' => 200, 'data' => $return]);
    }

    public function upStatus(Request $request)
    {
        $id = trim($request->input('id', ''));
        $status = trim($request->input('status', ''));
        $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
        $txtName = '';
        foreach ($sortData as $k => $v) {
            if ($v['id'] == $id) {
                $sortData[$k]['upTime'] = date("Y-m-d H:i:s", time());
                $sortData[$k]['status'] = $status;
                $txtName = $v['name'];
            }
        }
        file_put_contents(public_path() . $this->sortList, json_encode($sortData, true));
        if ($status == 1) {
            //开启
            $fileDir = public_path() . $this->fileData . $txtName . '.txt';
            $bakFileDir = public_path() . $this->fileData . $txtName . '.bak.txt';
            unlink($fileDir);
            rename($bakFileDir, $fileDir);
        } else {
            $fileDir = public_path() . $this->fileData . $txtName . '.txt';
            $bakFileDir = public_path() . $this->fileData . $txtName . '.bak.txt';
            copy($fileDir, $bakFileDir);
            $permission = 0777;
            file_put_contents($fileDir, '');
            chmod($fileDir, $permission);
        }
        return response()->json(['code' => 200, 'message' => 'success']);
    }

    public function addData(Request $request)
    {
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
            $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
            if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $target_file)) {
                // 载入Excel文件
                $inputFileName = $target_file;
                chmod($inputFileName, 0777);
                $reader = IOFactory::createReader('Xls'); // 用于读取XLS格式的文件
                $reader->setReadDataOnly(true); // 只读取数据，不读取格式等其他信息
                $spreadsheet = $reader->load($inputFileName);
                $sheetCount = $spreadsheet->getSheetCount();
                // 遍历所有sheet
                for ($i = 0; $i < $sheetCount; $i++) {
                    $sheet = $spreadsheet->getSheet($i);
                    $highestRow = $sheet->getHighestRow(); // 总行数
                    $highestColumn = $sheet->getHighestColumn(); // 总列数
                    $rowData = [];
                    $type = '';
                    $strData = '';
                    for ($row = 2; $row <= $highestRow; $row++) {
                        for ($col = 'A'; $col !== $highestColumn; $col++) {
                            $cellValue = $sheet->getCell($col . $row)->getValue();
                            $type = $sheet->getCell('B' . 2)->getValue();
                            if ($type == 'c2') $type = 'pornographic';
                            $rowData[] = $cellValue;
                            $strData .= $cellValue . "\n";
                        }
                    }
                    $fileDir = public_path() . $this->fileData . $type . '.txt';
                    $permission = 0666;
                    if (!file_exists($fileDir)) {
                        file_put_contents($fileDir, '');
                    }
                    chmod($fileDir, $permission);
                    $nums = count($rowData);
                    file_put_contents($fileDir, $strData);
                    //更新类型条数
                    $txtName = '';
                    foreach ($sortData as $k => $v) {
                        if ($v['name'] == $type) {
                            $sortData[$k]['num'] = $nums;
                        }
                    }
                }
                file_put_contents(public_path() . $this->sortList, json_encode($sortData, true));
            }
        }
        return response()->json(['message' => 'Modified successfully', 'code' => 200]);
    }

    public function report(Request $request)
    {
        $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
        $nameArr = array_column($sortData, 'name');

        $return = file_get_contents(public_path() . $this->blackSummary);
        $info = json_decode($return, true);
        $exInfo = array_keys($info);
        //dd($info);

        //dd($info['2024-12-06_00']["system_usage"]['cpu_usage_percent']);
        $dates = [];
        $currentDate = date('Y-m-d');
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($currentDate . " -{$i} days"));
            $dates[] = $date;
        }
        $dates = array_reverse($dates);
        $return = [];
        foreach ($nameArr as $ks => $vs) {
            foreach ($dates as $k => $v) {
                if (in_array($v, $exInfo)) {
                    if (in_array($vs, array_keys($info[$v]))) {
                        $return[$vs][] = count($info[$v][$vs]);
                    } else {
                        $return[$vs][] = 0;
                    }
                    //  $return[$vs][] = $info[$v][];
                } else {
                    $return[$vs][] = 0;
                }
            }
        }
        $returns['dates'] =  $dates;
        $returns['names'] =  $nameArr;
        $returns['info'] =  $return;
        return response()->json(['data' => $returns, 'message' => 'success', 'code' => 200]);
    }

    public function alert(Request $request)
    {

        $return = file_get_contents(public_path() . $this->blackSummary);
        $alert = json_decode(file_get_contents(public_path() . $this->setAlert), true);
        $info = json_decode($return, true);
        //dd($info);
        $return = [];
        $i = 0;
        foreach ($info as $kay => $val) {
            foreach ($val as $ka => $va) {
                foreach ($va as $k => $v) {
                    $return[$i]['ip'] = $v['ip'];
                    $return[$i]['domain'] = $v['domain'];
                    $return[$i]['date'] = $v['date'];
                    $return[$i]['name'] = $ka;
                    $return[$i]['num'] = count($va);
                    if (count($va) < $alert[0]['num']) {
                        $return[$i]['serious'] = $alert[0]['name'];
                    } elseif (count($va) > $alert[2]['num']) {
                        $return[$i]['serious'] = $alert[2]['name'];
                    } else {
                        $return[$i]['serious'] = $alert[1]['name'];
                    }
                    $i++;
                }
            }
        }
        //dd($info['2024-12-06_00']["system_usage"]['cpu_usage_percent']);
        return response()->json(['data' => $return, 'message' => 'success', 'code' => 200]);
    }

    public function searchAlert(Request $request)
    {
        $params = $request->all();


        $page = !empty($params['page']) ? (int)$params['page'] : 1;
        $limit = !empty($params['limit']) ? (int)$params['limit'] : 10;
        $count = 0;
        $start = 0;
        $skip  = true;
        if ($page > 1) $start = ($page - 1) * $limit;

        $search_arr = [];
        foreach ($params as $key => $value) {
            if ($key == 'page') continue;
            if ($key == 'limit') continue;
            if (!empty($value) || $value === '0') $search_arr[trim($key)] = trim($value);
        }

        $return = file_get_contents(public_path() . $this->blackSummary) ?? '';
        $alert = json_decode(file_get_contents(public_path() . $this->setAlert), true);
        $info = json_decode($return, true);
        //dd($info);
        $return = [];
        $i = 0;
        foreach ($info as $kay => $val) {
            foreach ($val as $ka => $va) {
                foreach ($va as $k => $v) {
                    $return[$i]['ip'] = $v['ip'];
                    $return[$i]['domain'] = $v['domain'];
                    $return[$i]['date'] = $v['date'];
                    $return[$i]['name'] = $ka;
                    $return[$i]['num'] = count($va);
                    if (count($va) < $alert[0]['num']) {
                        $return[$i]['serious'] = $alert[0]['name'];
                    } elseif (count($va) > $alert[2]['num']) {
                        $return[$i]['serious'] = $alert[2]['name'];
                    } else {
                        $return[$i]['serious'] = $alert[1]['name'];
                    }
                    $i++;
                }
            }
        }
        // $seen = [];                 // 用来记录已经出现过的组合
        // $data = [];

        // foreach ($return as $item) {
        //     // 用竖线拼接成唯一键
        //     $key = $item['ip'] . '|' . $item['name'] . '|' . $item['domain'];
        //     if (!isset($seen[$key])) {
        //         $seen[$key] = true;
        //         $data[]     = $item;
        //     }
        // }

        if (!empty($return)) {
            $return = array_reverse($return);
            if (!empty($search_arr)) {
                $skip  = false;
                $return = @$this->filterUsers($return, $search_arr);
            }

            $return = array_slice($return, $start, $limit);
        }

        return response()->json(['data' => $return, 'is_all' => $skip, 'message' => 'successfully', 'count' => count($return), 'code' => 200]);
    }

    // public function searchData(Request $request)
    // {
    //     $params = $request->all();

    //     $page = !empty($params['page']) ? (int)$params['page'] : 1;
    //     $limit = !empty($params['limit']) ? (int)$params['limit'] : 10;

    //     $sort = trim($request->input('sort', ''));
    //     $domain = trim($request->input('domain', ''));

    //     $count = 0;
    //     $start = 0;
    //     $skip = true;
    //     if ($page > 1) $start = ($page - 1) * $limit;

    //     $search_arr = [];
    //     foreach ($params as $key => $value) {
    //         if ($key == 'page') continue;
    //         if ($key == 'limit') continue;
    //         if (!empty($value) || $value === '0') $search_arr[trim($key)] = trim($value);
    //     }

    //     $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
    //     $data = [];
    //     foreach ($sortData as $key => $val) {
    //         if ($sort == $val['name']) {
    //             $temp = file_get_contents(public_path() . $this->fileData . $val['name'] . ".txt");
    //             $tempArr[$val['name']] = explode("\n", trim($temp));
    //             $data = $tempArr;
    //         }
    //     }
    //     $retrun = [];
    //     foreach ($data as $ks => $item) {
    //         foreach ($item as $k => $v) {
    //             $retrun[] = ['sort' => $ks, 'domain' => $v];
    //         }
    //     }
    //     if (!empty($retrun)) {

    //         if (!empty($search_arr)) {
    //             $skip = false;
    //             $retrun = @$this->filterUsers($retrun, $search_arr);
    //         }

    //         $count = count($retrun) + 1;

    //         $retrun = array_slice($retrun, $start, $limit);
    //     }

    //     return response()->json(['message' => 'successfully', 'code' => 200, 'data' => $retrun, 'is_all' => $skip, 'count' => $count]);
    // }


    function searchData(Request $request)
    {
        $params = $request->all();

        $page = !empty($params['page']) ? (int)$params['page'] : 1;
        $limit = !empty($params['limit']) ? (int)$params['limit'] : 10;
        $sort = trim($request->input('sort', ''));

        $logPath  =  $request->input('path');
        $offset  = ($page - 1) * $limit;
        $keyword =  trim($request->input('domain') ?? '');

        $sortData = json_decode(file_get_contents(public_path() . $this->sortList), true);
        $fileName = '';
        $total = 0;
        foreach ($sortData as $key => $val) {
            if ($sort == $val['name']) {
                $fileName =  $val['name'];
                $total = $val['num'];
                $logPath  =  public_path() . $this->fileData . $val['name'] . ".txt";
                break;
            }
        }

        // 文件不存在直接结束
        if (!file_exists($logPath)) {
            return response()->json(['message' => 'successfully', 'code' => 200, 'data' => [], 'is_all' => true, 'count' => 0]);
        }

        $lines = [];
        $return = [];
        // ---------- 2. 普通分页读取 + 关键字过滤 ----------
        // 1. 关键词存在 → 先拿全部匹配行号
        $is_all = true;
        if (!empty($sort) || !empty($keyword)) {
            $is_all = false;
        }
        if ($keyword !== '') {
            // 一次性扫盘，只记录行号
            $file = new SplFileObject($logPath);
            $allMatched = [];          // 元素：行号
            foreach ($file as $lineNo => $line) {
                if (trim($line) !== '' && stripos($line, $keyword) !== false) {
                    $allMatched[] = $lineNo;
                }
            }
            // 2. 根据页码取子集
            $total   = count($allMatched);
            $totalPg = $limit <= 0 ? 1 : (int)ceil($total / $limit);
            $page    = max(1, min($page, $totalPg ?: 1));
            $offset  = ($page - 1) * $limit;

            $lines = [];
            for ($i = $offset; $i < $offset + $limit && $i < $total; $i++) {
                $file->seek($allMatched[$i]);
                $lines[] = trim($file->current());
            }
            foreach ($lines as $k => $v) {
                $return[] = ['sort' => $fileName, 'domain' => $v];
            }
            return response()->json(['message' => 'successfully', 'code' => 200, 'data' => $return, 'is_all' => $is_all, 'count' => $total]);
            //return [$lines, $total, $totalPg];
        } else {
            // 3. 关键词为空 → 保持原“边读边停”逻辑
            $lines = [];
            $file  = new SplFileObject($logPath);
            $file->seek($offset);
            // 外部传进来的起始行号/偏移
            while (!$file->eof() && count($lines) < $limit) {
                $line = trim($file->current());
                if ($line !== '') {
                    $lines[] = $line;
                }
                $file->next();
            }

            foreach ($lines as $k => $v) {
                $return[] = ['sort' => $fileName, 'domain' => $v];
            }
            return response()->json(['message' => 'successfully', 'code' => 200, 'data' => $return, 'is_all' => $is_all, 'count' => $total]);
            //return [$lines, null, null];   // 总条数、总页数未知（可再扫一次补）
        }
    }

    public function getAlert(Request $request)
    {

        $return = file_get_contents(public_path() . $this->setAlert);
        $info = json_decode($return, true);
        return response()->json(['data' => $info, 'message' => 'success', 'code' => 200]);
    }

    public function setAlert(Request $request)
    {

        $num = trim($request->input('num', ''));
        $num = explode(',', $num);
        $return = file_get_contents(public_path() . $this->setAlert);
        $info = json_decode($return, true);
        $info[0]['num'] = $num[0];
        $info[1]['num'] = $num[1];
        $info[2]['num'] = $num[2];

        file_put_contents(public_path() . $this->setAlert, json_encode($info, true));
        return response()->json(['data' => $info, 'message' => 'Modified successfully', 'code' => 200]);
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
}
