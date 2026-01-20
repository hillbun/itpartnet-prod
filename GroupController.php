<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Services\AdminLogService;
use App\Http\Controllers\Services\HelperService;
use App\Http\Controllers\RedisModel\RstrategyController;
use App\Http\Controllers\RedisModel\RfilterController;

class GroupController extends Controller
{
    private $grouplist = '/fileData/grouplist.txt';
    private $ladpsgroup = '/fileData/ladpsgroup.txt';
    private $kbsgroup = '/fileData/kbsgroup.txt';
    protected $logService;
    protected $helperService;
    private $ladps_conn = '/fileData/ladps_conn.json';
    private $ladps = '';
    private $whole_user;
    private $user_dn = [];

    public function __construct(AdminLogService $logService, HelperService $helperService)
    {
        $this->logService = $logService;
        $this->helperService = $helperService;
        $alllinks = json_decode(file_get_contents(public_path() . $this->ladps_conn), true) ?? [];
        //获取status为1的$this->ladps
        foreach ($alllinks as $k => $v) {
            if ($v['status'] == 1) {
                $this->ladps = $v;
                break;
            }
        }
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

        $data = json_decode(file_get_contents(public_path() . $this->grouplist), true) ?? [];

        // 倒序
        $data = array_reverse($data);

        if (!empty($data)) {

            $count = count($data);

            $data = array_slice($data, $start, $limit);
        }

        return response()->json(['message' => 'Success', 'code' => 200, 'data' => $data, 'is_all' => $skip, 'count' => $count]);
    }

    public function search(Request $request)
    {
        $params = $request->all();
        $keyword = $request->input('keyword', '');
        $page = !empty($params['page']) ? (int)$params['page'] : 1;
        $limit = !empty($params['limit']) ? (int)$params['limit'] : 10;
        $count = 0;
        $start = 0;
        if ($page > 1) $start = ($page - 1) * $limit;
        if (empty($keyword)) {
            return response()->json(['message' => 'Success', 'code' => 200, 'data' => ['VZONE_EWS', 'VZONE_EWS_UAT'], 'count' => 2]);
        } else {
            $ldap_host = $this->ladps['hostname'];
            $ldap_port = $this->ladps['port'];
            $ldap_dn   = $this->ladps['ladpdn'];
            $ldap_pwd  = $this->ladps['password'];
            $return    = [];

            $ldap_conn = ldap_connect($ldap_host, $ldap_port) or die('无法连接到LDAP服务器');
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3) or die('Unable to set LDAP protocol version');
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

            if ($ldap_bind = ldap_bind($ldap_conn, $ldap_dn, $ldap_pwd)) {

                $filter = "(&(objectClass=group)(cn=*$keyword*))";
                if (empty($keyword)) {
                    $filter = "(&(objectClass=group)(cn=*))";
                }

                $attr    = ['cn'];
                $base_dn =  $this->ladps['basedn'];
                $result  = ldap_search($ldap_conn, $base_dn, $filter, $attr) or die('无法执行搜索');
                $entries = ldap_get_entries($ldap_conn, $result);

                for ($i = 0; $i < $entries['count']; $i++) {
                    $return[] = $entries[$i]['cn'][0];
                }
                if (!empty($return)) {
                    $count = count($return);
                    $return = array_slice($return, $start, $limit);
                }
            }
            ldap_unbind($ldap_conn);
            return response()->json(['message' => 'Success', 'code' => 200, 'data' => $return, 'count' => $count]);
        }
    }

    public function upDirinfo()
    {
	
        ini_set('memory_limit', '5G');
        set_time_limit(0);
        $host = $this->ladps['hostname'];
        $port = $this->ladps['port'];
        $user = $this->ladps['ladpdn'];
        $pass = $this->ladps['password'];
        $baseDn = $this->ladps['basedn'];

        // ============ 基础配置 全部保留 无修改 ============
        $retryTimes = 3;        // 失败重试次数，足够应对随机连接失败
        $connectTimeout = 10;   // 连接超时时间（秒），内网最优值
        $readTimeout = 30;      // 读写超时时间（秒），适配大量数据查询
        // ============ 循环测试配置 ============
        $testLoopNum = 20;      // 自定义连续测试次数，可改50/100次压测
        $successNum = 0;        // 成功次数统计
        $failNum = 0;           // 失败次数统计
        $startTime = microtime(true);

        // ============ 核心：循环测试LDAP连续连接 ============
        echo "开始循环测试LDAP连接，总次数：{$testLoopNum} 次，单次重试{$retryTimes}次 \r\n";
        for ($loop = 1; $loop <= $testLoopNum; $loop++) {
            $ldapConn = false;
            $bindSuccess = false;
            $currentRetry = 0;
            $loopErrorMsg = '';

            // 单次连接的重试逻辑 - 核心逻辑不变
            while ($currentRetry < $retryTimes && !$bindSuccess) {
                // 创建LDAP连接
                $ldapConn = ldap_connect($host, $port);
                if (!$ldapConn) {
                    $currentRetry++;
                    usleep(500000); // 休眠500毫秒，避免高频请求触发防火墙限流
                    continue;
                }

                // ============ PHP7.4 有效且必须的 LDAP核心稳定配置 全部保留 ============
                ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3); // ★必须配置，无则必失败
                ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);        // ★关闭引用，查询更稳定
                ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $connectTimeout); // ★解决90%的超时抖动
                ldap_set_option($ldapConn, LDAP_OPT_TIMELIMIT, $readTimeout);         // ★查询超时保护

                // 绑定账号密码，屏蔽原生警告
                $bindSuccess = @ldap_bind($ldapConn, $user, $pass);
                if (!$bindSuccess) {
                    $currentRetry++;
                    $loopErrorMsg = $ldapConn ? ldap_error($ldapConn) : 'LDAP服务器连接创建失败';
                    @ldap_unbind($ldapConn); // 释放失败连接，防止内存泄漏
                    usleep(500000);
                    continue;
                }
            }

            // ============ 单次连接结果判断 ============
            if ($bindSuccess && $ldapConn) {
                $successNum++;
                echo "【第{$loop}次】✅ LDAP连接&绑定成功 \r\n";
                @ldap_unbind($ldapConn); // 仅需unbind即可，自动关闭连接，PHP7.4最优写法
            } else {
                $failNum++;
                echo "【第{$loop}次】❌ LDAP连接失败，错误信息：{$loopErrorMsg} \r\n";
                if($ldapConn) @ldap_unbind($ldapConn); // 容错释放，杜绝资源警告
            }
            // 模拟真实请求间隔，可删除
            usleep(300000);
        }

        // ============ 测试完成-统计结果 ============
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        echo "====================================================\r\n";
        echo "✅ LDAP连续连接测试完成 | PHP7.4 无报错版 \r\n";
        echo "总测试次数：{$testLoopNum} 次 \r\n";
        echo "成功次数：{$successNum} 次 \r\n";
        echo "失败次数：{$failNum} 次 \r\n";
        echo "总耗时：{$totalTime} 秒 \r\n";
        echo "成功率：" . round(($successNum/$testLoopNum)*100, 2) . "% \r\n";
        echo "====================================================\r\n";

        // 失败时终止程序（按需开启）
        if ($failNum > 0) {
            die("⚠️ LDAP连续连接异常，共失败{$failNum}次，请排查网络或LDAP服务！");
        }
        exit;
	
        ini_set('memory_limit', '5G');
        set_time_limit(0);
        $host = $this->ladps['hostname'];
        $port = $this->ladps['port'];
        $user    = $this->ladps['ladpdn'];
        $pass  = $this->ladps['password'];
        $baseDn =  $this->ladps['basedn'];

        /* ---------- 1. 连接 & 绑定 ---------- */
        // ============ 新增：核心配置 - LDAP超时参数 + 重连次数 ============
        $retryTimes = 3; // 失败重试次数，建议3次，足够应对随机失败
        $connectTimeout = 10; // 连接超时时间（秒），内网建议5-10秒
        $readTimeout = 30; // 读写超时时间（秒），查询大量数据建议长一点

        $ldap = false;
        $bindSuccess = false;
        $currentRetry = 0;

        // ============ 新增：失败自动重连逻辑 ============
        while ($currentRetry < $retryTimes && !$bindSuccess) {
            // 1. 创建LDAP连接
            $ldap = ldap_connect($host, $port);
            if (!$ldap) {
                $currentRetry++;
                usleep(500000); // 失败后休眠500毫秒再重试，避免高频请求
                continue;
            }

            // 2. 配置LDAP核心参数（保留你的原有配置）
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            
            // ============ 新增：配置超时时间（重中之重！解决90%的超时抖动问题） ============
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, $connectTimeout);
            ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, $readTimeout);

            // 3. 绑定LDAP账号密码
            $bindSuccess = @ldap_bind($ldap, $user, $pass); // @屏蔽错误提示，自己控制重试逻辑
            if (!$bindSuccess) {
                $currentRetry++;
                ldap_close($ldap); // 关闭失败的连接，释放资源
                usleep(500000);
                continue;
            }
        }

        // ============ 最终判断：重试完还是失败，抛出异常 ============
        if (!$bindSuccess || !$ldap) {
            $error = ldap_error($ldap); // 获取具体的LDAP错误信息
            ldap_close($ldap);
            die("LDAP连接绑定失败，重试{$retryTimes}次均失败，错误信息：{$error}");
        }


        /* ---------- 2. 分页拉取全部节点 ---------- */
        $filter = '(|(objectClass=user)(objectClass=group)(objectClass=top))';
        $pageSize = 1000;
        $cookie   = '';

        $nodes = [];
        do {
            $controls = [[
                'oid'   => LDAP_CONTROL_PAGEDRESULTS,
                'value' => ['size' => $pageSize, 'cookie' => $cookie]
            ]];

            $sr = ldap_search($ldap, $baseDn, $filter, ['dn', 'sAMAccountName', 'objectclass'], 0, -1, -1, LDAP_DEREF_NEVER, $controls);
            $entries = ldap_get_entries($ldap, $sr);

            for ($i = 0; $i < $entries['count']; $i++) {
                $dn   = $entries[$i]['dn'];
                $cn   = $entries[$i]['samaccountname'][0] ?? '';

                $classes = array_map('strtolower', $entries[$i]['objectclass']);

                if (in_array('group', $classes) || in_array('groupofnames', $classes)) {
                    $type = 'group';
                } elseif (in_array('person', $classes) || in_array('user', $classes) || in_array('inetorgperson', $classes)) {
                    $type = 'user';
                } else {
                    $type = 'directory';
                }
                $nodes[] = ['dn' => $dn, 'label' => $cn, 'type' => $type];
            }

            ldap_parse_result($ldap, $sr, $errcode, $matcheddn, $errmsg, $referrals, $serverControls);
            $cookie = $serverControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while (!empty($cookie));
        //dd($nodes, $baseDn);
        $tree = $this->buildTreeFromNodes($nodes, $baseDn);
        $tree = $this->upAdinfo($ldap, $tree);
        // LDAP操作全部完成后执行
        ldap_unbind($ldap); // 解绑账号

        Redis::set('tree_adinfo_content', json_encode($tree, true));

        $redisdb1 = Redis::connection('db1');
        ///////////////////数据同步////////////////////////////////////
        //删除历史 allow_group与deny_group对应的用户
        $keysArr = $redisdb1->keys('acl:user:*');
        if (!empty($keysArr)) {
            foreach ($keysArr as $vss) {
                $redisdb1->del($vss);
            }
        }

        $adusers = json_decode(Redis::get('ad_user_content'), true);
        //////////////////添加新的allow_group下对应的用户/////////////////
        $allow = 'acl:group:group';
        if ($redisdb1->exists($allow)) {
            $return_allow = $redisdb1->hgetall($allow);
            $allowArr = json_decode($return_allow['groups'], true);
            if (!empty($allowArr)) {
                $allowUsers = [];
                foreach ($allowArr as $k => $allows) {
                    $type = array_values($allows)[1];
                    $dir = array_keys($allows)[0];
                    $flag = $this->pathAllExists($tree, $dir);
                    if (!$flag) {
                        unset($allowArr[$k]);
                        continue;
                    }
                    if ($type == 'directory') {
                        $users = [];
                        $users = $this->helperService->findUsersUnderPath([$tree], $dir);
                        $allowUsers = array_merge($allowUsers, $users);
                    } elseif ($type == 'group') {
                        $allowUsers = array_merge($allowUsers, $adusers[substr(strrchr($dir, '/'), 1)]);
                    } else {
                        $allowUsers[] = substr(strrchr($dir, '/'), 1);
                    }
                }

                if (!empty($allowUsers)) {
                    $allowUsers = array_unique($allowUsers);
                    foreach ($allowUsers as $alluser) {
                        $redisdb1->set("acl:user:" . $alluser, 'allow');
                        if (preg_match('/[A-Z]/', $alluser)) {
                            $alluser = strtolower($alluser);
                            $redisdb1->set("acl:user:" . $alluser, 'allow');
                        } else {
                            $alluser = strtoupper($alluser);
                            $redisdb1->set("acl:user:" . $alluser, 'allow');
                        }
                    }
                }
            }
            $redisdb1->hset($allow, 'groups', json_encode($allowArr, true));
        }
        ////////////////////////////////////////////////////////////////

        $keysArrss = Redis::keys('Hospitals:*');
        if (!empty($keysArrss)) {
            foreach ($keysArrss as $vss) {
                Redis::del($vss);
            }
        }
        if (!empty(Session::all()['user_name'])) {
            $userName = Session::all()['user_name'];
            $details = 'Manual synchronization adinfo';
        } else {
            $userName = 'system-auto';
            $details = 'System automatic synchronization adinfo';
        }
        $this->logService->logAction(
            $userName,
            "sync_ad",
            "",
            "edit",
            $details
        );

        $this->processDirectoryTree([$tree]);
        Redis::set('ad_update_time', date("Y-m-d H:i:s"));
        ////////////////////////////////////////////////////////////////
        return response()->json(['message' => 'Success', 'code' => 200]);
    }


    function processDirectoryTree(array $tree): void
    {
        foreach ($tree as $node) {
            // 当前节点的 Redis 键
            $currentKey = 'Hospitals:' . md5($node['dn']);

            // 准备当前节点数据（只包含直接子节点的基本信息）
            $currentNodeData = [
                'label' => $node['label'],
                'dn' => $node['dn'],
                'type' => $node['type'],
                'children' => []
            ];

            // 处理直接子节点
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $childKey = 'Hospitals:' . md5($child['dn']);

                    // 只存储子节点的基本信息，不递归存储
                    if ($child['type'] != 'user') {
                        if (!empty($child['children'])) {
                            $currentNodeData['children'][] = [
                                'label' => $child['label'],
                                'dn' => $child['dn'],
                                'type' => $child['type'],
                                'children' => [[]],
                                '_key' => $childKey // 存储子节点的 Redis 键引用
                            ];
                        } else {
                            $currentNodeData['children'][] = [
                                'label' => $child['label'],
                                'dn' => $child['dn'],
                                'type' => $child['type'],
                                '_key' => $childKey // 存储子节点的 Redis 键引用
                            ];
                        }
                    } else {
                        $currentNodeData['children'][] = [
                            'label' => $child['label'],
                            'dn' => $child['dn'],
                            'type' => $child['type'],
                            '_key' => $childKey // 存储子节点的 Redis 键引用
                        ];
                    }
                    // 递归处理子节点（会存储子节点的数据）
                    $this->processDirectoryTree([$child], $currentKey);
                }
            }
            if ($node['type'] == 'user') {
                continue;
            }
            // 将当前节点数据存储到 Redis
            Redis::set($currentKey, json_encode($currentNodeData));
        }
    }


    /**
     * 判断 label 路径是否完整存在
     * @param array  $tree  传入 $data[0] 即 Hospitals 根节点
     * @param string $path  "Hospitals/HAHO/Users/ODC"
     * @return bool
     */
    function pathAllExists(array $tree, string $path): bool
    {
        // ["Hospitals","HAHO","Users","ODC"]
        $labels = explode('/', $path);

        // 当前节点集合；先放根
        $nodes = [$tree];

        foreach ($labels as $label) {
            $found = null;
            foreach ($nodes as $node) {
                if (($node['label'] ?? '') === $label) {
                    $found = $node;
                    break;
                }
            }
            if ($found === null) {
                return false;          // 中间节点缺失
            }
            $nodes = $found['children'] ?? []; // 继续下一层

        }
        return true;                   // 全部节点都存在
    }

    public function buildTreeFromNodes(array $flat, string $rootDn): array
    {
        // 只保留以指定根开头的节点
        $flat = array_filter($flat, function ($n) use ($rootDn) {
            return stripos($n['dn'], $rootDn) !== false;
        });

        // 按层级升序
        usort($flat, function ($a, $b) {
            return substr_count($a['dn'], ',') - substr_count($b['dn'], ',');
        });

        // 映射表
        $tree = [$rootDn => ['label' => explode('=', explode(',', $rootDn)[0])[1], 'dn' => $rootDn, 'type' => 'directory', 'children' => []]];

        foreach ($flat as $node) {
            $dn    = $node['dn'];
            $label = $node['label'] ?: explode('=', explode(',', $dn)[0])[1]; // 空 label 用 CN/OU 值
            $type  = $node['type'];

            $item = [
                'label'    => $label,
                'dn'       => $dn,
                'type'     => $type,
                'children' => []
            ];

            $parentDn = preg_replace('/^[^,]+,\s*/', '', $dn);
            $parentDn = ($parentDn === $dn) ? '' : $parentDn;

            if (isset($tree[$parentDn])) {
                $tree[$parentDn]['children'][] = &$item;
                $tree[$dn] = &$item;
            }
            unset($item);
        }

        return $tree[$rootDn] ?? [];
    }

    public function upAdinfo($ldap, $tree)
    {

        $baseDn =  $this->ladps['basedn'];
        /* ---------- 2. 分页拉取全部节点 ---------- */
        $filter = "(objectClass=group)";
        //$attributes = array("cn", "member");
        $attributes = array("cn", "member");
        $pageSize = 1000;
        $cookie   = '';

        $adinfo = [];
        do {
            $controls = [[
                'oid'   => LDAP_CONTROL_PAGEDRESULTS,
                'value' => ['size' => $pageSize, 'cookie' => $cookie]
            ]];

            $sr  = ldap_search($ldap, $baseDn, $filter, $attributes, 0, -1, -1, LDAP_DEREF_NEVER, $controls) or die('无法执行搜索');
            $entries = ldap_get_entries($ldap, $sr);

            for ($i = 0; $i < $entries['count']; $i++) {
                $group_cn = $entries[$i]["cn"][0];
                $group_dn = $entries[$i]["dn"];

                // 用 Range 控制获取所有成员
                $allMembers = $this->getAllMembers($ldap, $group_dn);

                foreach ($allMembers as $member) {
                    $matches = $this->dn2login($ldap, $member);
                    $this->user_dn[$matches] = $member;
                    $adinfo[$group_cn][] = $matches;
                    // if (preg_match('/^CN=([^,]+)/', $member, $matches)) {
                    //    $adinfo[$group_cn][] = $matches[1];
                    //    $this->user_dn[$matches[1]] = $member;
                    //}
                }
            }
            ldap_parse_result($ldap, $sr, $errcode, $matcheddn, $errmsg, $referrals, $serverControls);
            $cookie = $serverControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while (!empty($cookie));
        Redis::set("ad_user_content", json_encode($adinfo, true)); ////组跟人的关系
        $treeData = $this->mergeGroupMembersEnhanced($tree, $adinfo, $ldap);
        return $treeData;
    }

    function dn2login($ldap, string $userDn): ?string
    {
        $sr = ldap_read($ldap, $userDn, '(objectClass=*)', ['sAMAccountName']);
        if (!$sr) return null;

        $entry = ldap_first_entry($ldap, $sr);
        if (!$entry) return null;
        $name = ldap_get_values($ldap, $entry, 'sAMAccountName');
        return $name[0] ?? null;
    }

    function mergeGroupMembersEnhanced(array $tree, array $groupMembers, $ldap): array
    {
        $this->getUserOu($ldap);
        $userOU = $this->user_dn;
        $iterator = function (&$node) use (&$iterator, $groupMembers, $userOU) {
            if ($node['type'] === 'group' && isset($groupMembers[$node['label']])) {
                // 如果需要将members也放入children：
                foreach ($groupMembers[$node['label']] as $member) {
                    $node['children'][] = [
                        'label' => $member,
                        'type' => 'user',
                        'dn' => $userOU[$member],
                        'children' => []
                    ];
                }
            }

            if (!empty($node['children'])) {
                foreach ($node['children'] as &$child) {
                    $iterator($child);
                }
            }
        };

        $mergedTree = $tree;
        $iterator($mergedTree);
        return $mergedTree;
    }

    function getAllMembers($ldap, $groupDn)
    {
        $members = [];
        $start = 0;
        do {
            $attr = "member;range={$start}-*";
            $sr = ldap_read($ldap, $groupDn, "(objectClass=*)", [$attr]);

            if (!$sr) break;

            $entry = ldap_first_entry($ldap, $sr);
            if (!$entry) break;

            // 获取实际的属性名（可能包含范围信息）
            $ber = null;
            $attrName = ldap_first_attribute($ldap, $entry, $ber);

            if (!$attrName) break;

            // 获取成员值
            $values = ldap_get_values_len($ldap, $entry, $attrName);
            if ($values && $values['count'] > 0) {
                for ($i = 0; $i < $values['count']; $i++) {
                    $members[] = $values[$i];
                }
            }

            // 检查是否还有更多数据
            if (strpos($attrName, ';range=') === false) {
                break; // 没有范围信息，可能是最后一批
            }

            // 解析范围信息，确定下一批的起始位置
            if (preg_match('/;range=(\d+)-(\*|\d+)/', $attrName, $matches)) {
                if ($matches[2] === '*') {
                    // 这是最后一批
                    break;
                } else {
                    $start = (int)$matches[2] + 1;
                }
            } else {
                break;
            }
        } while (true);

        return $members;
    }

    public function getUserOu($ldap)
    {
        $baseDn =  $this->ladps['basedn'];
        // 1) 用 ldap_explode_dn 拆成 RDN 数组
        $parts = ldap_explode_dn($baseDn, 0);          // ['CN=tim', 'OU=HAHO', ... , 'DC=squid', 'DC=hk']
        array_shift($parts);                       // 去掉 count 元素

        // 2) 过滤出 DC=xxx 的片段
        $dcParts = array_filter($parts, function ($rdn) {
            return stripos($rdn, 'DC=') === 0;
        });
        // 3) 重新拼成新的 DN
        $baseDn = implode(',', $dcParts); // DC=squid,DC=hk
        $pageSize = 1000;
        $cookie   = '';
        $map   = [];   // 用户名 => [目录路径1, 目录路径2, ...]

        // 2) 用户过滤器（AD/OpenLDAP 通用）
        $filter = '(&(objectClass=user)(objectCategory=person))';

        // 如果是 OpenLDAP，改用：
        // $filter = '(&(objectClass=inetOrgPerson))';
        do {
            $controls = [[
                'oid'   => LDAP_CONTROL_PAGEDRESULTS,
                'value' => ['size' => $pageSize, 'cookie' => $cookie]
            ]];

            $sr = ldap_search($ldap, $baseDn, $filter, ['dn', 'samaccountname', 'uid', 'cn'], 0, -1, -1, LDAP_DEREF_NEVER, $controls);

            $entries = ldap_get_entries($ldap, $sr);

            for ($i = 0; $i < $entries['count']; $i++) {
                // 优先用 sAMAccountName，其次是 uid，最后是 cn
                $key = $entries[$i]['samaccountname'][0]
                    ?? $entries[$i]['uid'][0]
                    ?? $entries[$i]['cn'][0]
                    ?? null;

                if ($key) {
                    $this->user_dn[$key] = $entries[$i]['dn'];
                }
            }

            // 3.3 分页控制
            ldap_parse_result($ldap, $sr, $errcode, $matcheddn, $errmsg, $referrals, $serverControls);
            $cookie = $serverControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while (!empty($cookie));
    }

    public function add(Request $request)
    {
        $name = trim($request->input('name', ''));
        $data = json_decode(file_get_contents(public_path() . $this->grouplist), true) ?? [];
        $id = !empty($data) ? end($data)['id'] + 1 : 1;
        if (!empty($data)) {
            // 提取需要查找的列
            $column = array_column($data, 'name');
            // 使用in_array来查找内容
            $isFound = in_array($name, $column);
            if (!$isFound) {
                $new_arr = [
                    "id" => $id,
                    "name" => $name,
                    "ladps" => 0,
                    "kbs" => 0,
                    "date_time" => date("Y-m-d H:i:s", time())
                ];
            }
        } else {
            $new_arr = [
                "id" => $id,
                "name" => $name,
                "ladps" => 0,
                "kbs" => 0,
                "date_time" => date("Y-m-d H:i:s", time())
            ];
        }
        if (!empty($new_arr)) $data[] = $new_arr;
        file_put_contents(public_path() . $this->grouplist, json_encode($data, true));

        //记录操作日志
        $details = [
            ['name' => 'name', 'orginData' => '', 'newData' => $new_arr['name']],
            ['name' => 'ladps', 'orginData' => '', 'newData' => $new_arr['ladps']],
            ['name' => 'kbs', 'orginData' => '', 'newData' => $new_arr['kbs']],
            ['name' => 'date', 'orginData' => '', 'newData' => $new_arr['date_time']],
        ];
        $this->logService->logAction(
            Session::all()['user_name'],
            "group",
            "search_group",
            "add",
            $details
        );
        return response()->json(['message' => 'Success', 'code' => 200]);
    }

    public function del(Request $request)
    {
        $id = trim($request->input('id', ''));
        $data = json_decode(file_get_contents(public_path() . $this->grouplist), true) ?? [];
        foreach ($data as $k => $v) {
            if ($id == $v['id']) {
                $name = $v['name'];
                unset($data[$k]);
            }
        }
        file_put_contents(public_path() . $this->grouplist, json_encode($data, true));

        //配置更新
        $param1 = file_get_contents(public_path() . $this->ladpsgroup) ?? "";
        $param2 = file_get_contents(public_path() . $this->kbsgroup) ?? "";
        if (!empty($param1)) {
            $tmpArr = explode(":", $param1);
            foreach ($tmpArr as $k => $v) {
                if ($name == $v) {
                    $orginData = $v;
                    unset($tmpArr[$k]);
                }
            }
            $param1 = join(":", $tmpArr);
        }
        if (!empty($param2)) {
            $tmpArr2 = explode(":", $param2);
            foreach ($tmpArr2 as $k => $v) {
                if ($name == $v) {
                    $orginData = $v;
                    unset($tmpArr2[$k]);
                }
            }
            $param2 = join(":", $tmpArr2);
        }
        file_put_contents(public_path() . $this->ladpsgroup, trim($param1, ":"));
        file_put_contents(public_path() . $this->kbsgroup, trim($param2, ":"));

        //记录操作日志
        $details = [
            ['name' => 'name', 'orginData' => $orginData['name'], 'newData' => ''],
            ['name' => 'ladps', 'orginData' => $orginData['ladps'], 'newData' => ''],
            ['name' => 'kbs', 'orginData' => $orginData['kbs'], 'newData' => ''],
            ['name' => 'date', 'orginData' => $orginData['date_time'], 'newData' => ''],
        ];
        $this->logService->logAction(
            Session::all()['user_name'],
            "group",
            "management_group",
            "del",
            $details
        );
        ///调用python3
        $dirpy =  public_path() . '/fileData/auto_squid_conf.py';
        exec("/usr/bin/python3  $dirpy");
        return response()->json(['message' => 'Success', 'code' => 200]);
    }

    public function edit(Request $request)
    {

        $name = $request->input('name', '');
        $ladps = trim($request->input('ladps', ''));
        $kbs = trim($request->input('kbs', ''));

        //更新list
        $data = json_decode(file_get_contents(public_path() . $this->grouplist), true) ?? [];
        foreach ($data as $k => $v) {
            if ($name == $v['name']) {
                $data[$k]['ladps'] = $ladps;
                $data[$k]['kbs'] = $kbs;
            }
        }
        file_put_contents(public_path() . $this->grouplist, json_encode($data, true));

        $param1 = file_get_contents(public_path() . $this->ladpsgroup) ?? "";
        $param2 = file_get_contents(public_path() . $this->kbsgroup) ?? "";
        $orginParam1 = $param1;
        $orginParam2 = $param2;

        //判断是否已经设置过
        $isSet = false;
        if (!empty($param1)) {
            $tmpArr = explode(":", $param1);
            foreach ($tmpArr as $k => $v) {
                if ($name == $v) {
                    $isSet = true;
                }
            }
        }


        if ($ladps) {
            if (!$isSet) {
                $param1 .= ":$name";
            }
        } else {
            if ($isSet) {
                if (!empty($param1)) {
                    $tmpArr = explode(":", $param1);
                    foreach ($tmpArr as $k => $v) {
                        if ($name == $v) {
                            unset($tmpArr[$k]);
                        }
                    }
                    $param1 = join(":", $tmpArr);
                }
            }
        }
        file_put_contents(public_path() . $this->ladpsgroup, trim($param1, ":"));


        //判断是否已经设置过
        $isSet2 = false;
        if (!empty($param2)) {
            $tmpArr2 = explode(":", $param2);
            foreach ($tmpArr2 as $kd => $vd) {
                if ($name == $vd) {
                    $isSet2 = true;
                }
            }
        }

        if ($kbs) {
            if (!$isSet2) {
                $param2 .= ":$name";
            }
        } else {
            if ($isSet) {
                if (!empty($param2)) {
                    $tmpArr2 = explode(":", $param2);
                    foreach ($tmpArr2 as $k => $v) {
                        if ($name == $v) {
                            unset($tmpArr2[$k]);
                        }
                    }
                    $param2 = join(":", $tmpArr2);
                }
            }
        }
        file_put_contents(public_path() . $this->kbsgroup, trim($param2, ":"));

        //记录操作日志
        if ($kbs) {
            $orginKbs = 0;
            if ($isSet) {
                $orginladps = 1;
            } else {
                $orginladps = 0;
            }
        }
        if ($ladps) {
            $orginladps = 0;
            if ($isSet2) {
                $orginKbs = 1;
            } else {
                $orginKbs = 0;
            }
        }
        $details = [
            ['name' => 'name', 'orginData' => $name, 'newData' => $name],
            ['name' => 'ladps', 'orginData' =>  $orginladps, 'newData' => $ladps],
            ['name' => 'kbs', 'orginData' => $orginKbs, 'newData' => $kbs],
        ];
        $this->logService->logAction(
            Session::all()['user_name'],
            "group",
            "management_group",
            "edit",
            $details
        );
        ///调用python3
        $dirpy = public_path() . '/fileData/auto_squid_conf.py';
        exec("/usr/bin/python3  $dirpy", $output, $return);

        return response()->json(['message' => "Success", 'code' => 200]);
    }
}
