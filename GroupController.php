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

        /* ---------- 1. 连接 & 绑定 ---------- */
        // 创建连接
        $ldap = ldap_connect($host, $port);
        if (!$ldap) {
            die("无法连接到 LDAP 服务器");
        }

        // 设置选项
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        // 绑定
        if (ldap_bind($ldap, $user, $pass)) {
            // echo "LDAP 绑定成功！";
            // ldap_unbind($ldap);
        } else {
            die("绑定失败：" . ldap_error($ldap));
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

        $tree = $this->buildTreeFromNodes($nodes, $baseDn);
        $tree = $this->upAdinfo($ldap, $tree);
        ldap_unbind($ldap);

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
