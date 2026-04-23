<?php
// routes/api.php
// Route::get('posts', 'Api\PostController@index');
// Route::post('posts', 'Api\PostController@store');
// 其他前台API路由...

// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\IndexController;
use App\Http\Controllers\Api\IpwhitelistController;
use App\Http\Controllers\Api\UrlwhitelistController;
use App\Http\Controllers\Api\UrlblacklistController;
use App\Http\Controllers\Api\UrlsetController;
use App\Http\Controllers\Api\ThreatController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LogsController;
use App\Http\Controllers\Api\IpblacklistController;
use App\Http\Controllers\Api\IpRangeController;
use App\Http\Controllers\Api\ComputerController;
use App\Http\Controllers\Api\IpcustomerController;
use App\Http\Controllers\Api\UrlcustomerController;
use App\Http\Controllers\Api\UserDefinedController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\StrategyController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\IpSegmentByAdController;
use App\Http\Controllers\Api\IpSegmentController;
use App\Http\Controllers\Api\DestipwhitelistController;
use App\Http\Controllers\Api\DestipblacklistController;
use App\Http\Controllers\Api\UseragentController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\LadpsController;
use App\Http\Controllers\Api\KbsController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\AclController;
use App\Http\Controllers\Api\PortDefinedController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\FileStreamController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\Api\PopuplistController;

Route::get('/manager_del', [ManagerController::class, 'del']);

Route::post('/login', [IndexController::class, 'do_login']);
Route::get('/threat_up', [ThreatController::class, 'upThreat']);
Route::get('/upredis_ad', [GroupController::class, 'upAdinfo']);
Route::get('/upredis_dir', [GroupController::class, 'upDirinfo']);
Route::get('/upredis_userdn', [GroupController::class, 'getUserOu']);
Route::get('/manager_add_temp', [ManagerController::class, 'add_temp']);
Route::get('/upUser', [ManagerController::class, 'upUser']);
Route::get('/category_add_temp', [CategoryController::class, 'add_temp']);
Route::get('/update_data', [DestipwhitelistController::class, 'update_data']);
Route::get('/update_data_black', [DestipblacklistController::class, 'update_data_black']);
Route::get('/logs_del', [LogsController::class, 'delHistory']);
Route::get('/manager_unlock', [ManagerController::class, 'unlock']);//->middleware('throttle:5,1440'); // 5 次/1440 分钟（24小时）admin解锁api
Route::get('/file_stream2', [FileStreamController::class, 'streamFile2']);
Route::get('/version_list', [VersionController::class, 'index']);
Route::get('/released_version', [IndexController::class, 'released_version']);
Route::post('/get_connection_status', [IndexController::class, 'get_connection_status_data']);


Route::middleware(['login'])->group(function () {
    Route::get('/getDomain', [FileStreamController::class, 'get_domain']);
    Route::post('/file_stream', [FileStreamController::class, 'streamFile']);
    Route::post('/ad_search', [AdController::class, 'search_user']);     
    Route::post('/logout', [IndexController::class, 'logout']);
    Route::post('/file_upload', [IndexController::class, 'file_upload']);
    Route::post('/restart_squid', [IndexController::class, 'restart_squid']);
    Route::get('/sys_status', [IndexController::class, 'sys_status']);
    Route::get('/squid_list', [IndexController::class, 'squid_list']);
    Route::get('/bandwith_tcp', [IndexController::class, 'bandwith_tcp']);
    Route::get('/threat_v', [IndexController::class, 'threat_version']);
    Route::post('/getrestart_time', [IndexController::class, 'getrestart_time']);
    Route::post('/one_click_export', [IndexController::class, 'one_click_export']);

    Route::get('/threat_index', [ThreatController::class, 'index']);
    Route::get('/threat_upStatus', [ThreatController::class, 'upStatus']);
    Route::post('/threat_add', [ThreatController::class, 'addData']);
    Route::get('/threat_report', [ThreatController::class, 'report']);
    Route::get('/threat_alert', [ThreatController::class, 'alert']);
    Route::post('/threat_setAlert', [ThreatController::class, 'setAlert']);
    Route::get('/threat_getAlert', [ThreatController::class, 'getAlert']);
    Route::post('/threat_searchAlert', [ThreatController::class, 'searchAlert']);
    Route::post('/threat_searchData', [ThreatController::class, 'searchData']);
    Route::get('/threat_ipreport', [ThreatController::class, 'ipReport']);
    Route::get('/threat_urlreport', [ThreatController::class, 'urlReport']);
    Route::get('/threat_logReport', [ThreatController::class, 'logReport']);
    
    Route::post('/popup_list', [PopuplistController::class, 'index']);
    Route::post('/popup_add', [PopuplistController::class, 'add']);
    Route::post('/popup_edit', [PopuplistController::class, 'edit']);
    Route::post('/popup_del', [PopuplistController::class, 'del']);

    Route::get('/group_index', [GroupController::class, 'index']);
    Route::get('/group_search', [GroupController::class, 'search']);
    Route::get('/group_add', [GroupController::class, 'add']);
    Route::get('/group_edit', [GroupController::class, 'edit']);
    Route::get('/group_del', [GroupController::class, 'del']);

    Route::post('/ip_list', [IpwhitelistController::class, 'index']);
    Route::post('/ip_add', [IpwhitelistController::class, 'ip_add']);
    Route::post('/ip_edit', [IpwhitelistController::class, 'ip_edit']);
    Route::post('/ip_del', [IpwhitelistController::class, 'ip_del']);

    Route::post('/set_list', [UrlsetController::class, 'index']);
    Route::post('/set_add', [UrlsetController::class, 'url_add']);
    Route::post('/set_edit', [UrlsetController::class, 'url_edit']);
    Route::post('/set_del', [UrlsetController::class, 'url_del']);

    Route::post('/url_list', [UrlwhitelistController::class, 'index']);
    Route::post('/url_add', [UrlwhitelistController::class, 'url_add']);
    Route::post('/url_edit', [UrlwhitelistController::class, 'url_edit']);
    Route::post('/url_del', [UrlwhitelistController::class, 'url_del']);
    Route::post('/set_operate', [UrlwhitelistController::class, 'set_operate']);

    Route::post('/black_url_list', [UrlblacklistController::class, 'index']);
    Route::post('/black_url_add', [UrlblacklistController::class, 'url_add']);
    Route::post('/black_url_edit', [UrlblacklistController::class, 'url_edit']);
    Route::post('/black_url_del', [UrlblacklistController::class, 'url_del']);
    Route::get('/black_url_url', [UrlblacklistController::class, 'url_url']);

    Route::post('/logs_index', [LogsController::class, 'index']);
    Route::post('/logs_content', [LogsController::class, 'get_content']);
    Route::post('/logs_list', [LogsController::class, 'list']);
    Route::get('/logs_check', [LogsController::class, 'get_check']);
    Route::post('/logs_export', [LogsController::class, 'export']);

    Route::post('/ipblack_list', [IpblacklistController::class, 'index']);
    Route::post('/ipblack_add', [IpblacklistController::class, 'ip_add']);
    Route::post('/ipblack_edit', [IpblacklistController::class, 'ip_edit']);
    Route::post('/ipblack_del', [IpblacklistController::class, 'ip_del']);

    Route::post('/iprange_list', [IpRangeController::class, 'index']);
    Route::post('/iprange_add', [IpRangeController::class, 'add']);
    Route::post('/iprange_edit', [IpRangeController::class, 'edit']);
    Route::post('/iprange_del', [IpRangeController::class, 'del']);
    Route::post('/iprange_group', [IpRangeController::class, 'group']);

    Route::get('/agent_list', [AgentController::class, 'index']);
    Route::post('/agent_add', [AgentController::class, 'add']);
    Route::post('/agent_edit', [AgentController::class, 'edit']);
    Route::post('/agent_del', [AgentController::class, 'del']);

    Route::post('/computer_list', [ComputerController::class, 'index']);
    Route::post('/computer_add', [ComputerController::class, 'add']);
    Route::post('/computer_edit', [ComputerController::class, 'edit']);
    Route::post('/computer_del', [ComputerController::class, 'del']);
    Route::post('/computer_group', [ComputerController::class, 'group']);

    Route::get('/ipcustomer_list', [IpcustomerController::class, 'index']);
    Route::post('/ipcustomer_add', [IpcustomerController::class, 'add']);
    Route::post('/ipcustomer_edit', [IpcustomerController::class, 'edit']);
    Route::post('/ipcustomer_del', [IpcustomerController::class, 'del']);

    Route::get('/urlcustomer_list', [UrlcustomerController::class, 'index']);
    Route::post('/urlcustomer_add', [UrlcustomerController::class, 'add']);
    Route::post('/urlcustomer_edit', [UrlcustomerController::class, 'edit']);
    Route::post('/urlcustomer_del', [UrlcustomerController::class, 'del']);

    Route::get('/ad_list', [AdController::class, 'index']);
    Route::get('/ad_edit', [AdController::class, 'edit']);
    Route::get('/adallinfo', [AdController::class, 'adallinfo']);
    Route::post('/ad_set', [AdController::class, 'set']);
    Route::get('/ad_getdn', [AdController::class, 'getDn']);     
    Route::post('/ad_data', [AdController::class, 'get_data']);
    Route::post('/ad_operate', [AdController::class, 'operate']);

    Route::post('/filter_list', [FilterController::class, 'index']);
    Route::post('/filter_edit', [FilterController::class, 'edit']);
    Route::post('/filter_add', [FilterController::class, 'add']);
    Route::post('/filter_del', [FilterController::class, 'del']);

    Route::post('/strategy_list', [StrategyController::class, 'index']);
    Route::post('/strategy_edit', [StrategyController::class, 'edit']);
    Route::post('/strategy_add', [StrategyController::class, 'add']);
    Route::post('/strategy_del', [StrategyController::class, 'del']);
    Route::get('/strategy_network', [StrategyController::class, 'getNetWorkList']);
    Route::get('/strategy_user', [StrategyController::class, 'getUserList']);
    Route::get('/strategy_ad', [StrategyController::class, 'getAdList']);
    Route::get('/strategy_computer', [StrategyController::class, 'getComputerList']);
    Route::get('/strategy_filter', [StrategyController::class, 'getFilterList']);
    Route::get('/strategy_agent', [StrategyController::class, 'getAgentList']);
    Route::get('/strategy_status', [StrategyController::class, 'changeStatus']);

    Route::get('/userdefined_list', [UserDefinedController::class, 'index']);
    Route::post('/userdefined_edit', [UserDefinedController::class, 'edit']);
    Route::post('/userdefined_add', [UserDefinedController::class, 'add']);
    Route::post('/userdefined_del', [UserDefinedController::class, 'del']);

    Route::get('/category_list', [CategoryController::class, 'index']);
    Route::post('/category_add', [CategoryController::class, 'add']);
    Route::post('/category_edit', [CategoryController::class, 'edit']);
    Route::post('/category_del', [CategoryController::class, 'del']);
    Route::get('/category_data', [CategoryController::class, 'get_data']);
    Route::get('/category_exist', [CategoryController::class, 'isexist']);

    Route::get('/ip_segment_ad_list', [IpSegmentByAdController::class, 'index']);
    Route::post('/ip_segment_ad_add', [IpSegmentByAdController::class, 'ip_segment_add']);
    Route::post('/ip_segment_ad_edit', [IpSegmentByAdController::class, 'ip_segment_edit']);
    Route::post('/ip_segment_ad_del', [IpSegmentByAdController::class, 'ip_segment_del']);

    Route::get('/ip_segment_list', [IpSegmentController::class, 'index']);
    Route::post('/ip_segment_add', [IpSegmentController::class, 'ip_segment_add']);
    Route::post('/ip_segment_edit', [IpSegmentController::class, 'ip_segment_edit']);
    Route::post('/ip_segment_del', [IpSegmentController::class, 'ip_segment_del']);

    // destination ip white list
    Route::post('/dest_ip_list', [DestipwhitelistController::class, 'index']);
    Route::post('/dest_ip_add', [DestipwhitelistController::class, 'ip_add']);
    Route::post('/dest_ip_edit', [DestipwhitelistController::class, 'ip_edit']);
    Route::post('/dest_ip_del', [DestipwhitelistController::class, 'ip_del']);

    // destination ip black list
    Route::post('/dest_ipblack_list', [DestipblacklistController::class, 'index']);
    Route::post('/dest_ipblack_add', [DestipblacklistController::class, 'ip_add']);
    Route::post('/dest_ipblack_edit', [DestipblacklistController::class, 'ip_edit']);
    Route::post('/dest_ipblack_del', [DestipblacklistController::class, 'ip_del']);

    // user agent list
    Route::post('/useragent_list', [UseragentController::class, 'index']);
    Route::post('/useragent_add', [UseragentController::class, 'user_agent_add']);
    Route::post('/useragent_edit', [UseragentController::class, 'user_agent_edit']);
    Route::post('/useragent_del', [UseragentController::class, 'user_agent_del']);

    Route::post('/ladps_list', [LadpsController::class, 'index']);
    Route::post('/ladps_status', [LadpsController::class, 'checkStatus']);
    Route::post('/ladps_add', [LadpsController::class, 'add']);
    Route::post('/ladps_edit', [LadpsController::class, 'edit']);
    Route::post('/ladps_del', [LadpsController::class, 'del']);

    Route::post('/kbs_list', [KbsController::class, 'index']);
    Route::post('/kbs_status', [KbsController::class, 'checkStatus']);
    Route::post('/kbs_add', [KbsController::class, 'add']);
    Route::post('/kbs_edit', [KbsController::class, 'edit']);
    Route::post('/kbs_del', [KbsController::class, 'del']);
    Route::post('/kbs_check', [KbsController::class, 'check']);

    Route::post('/manager_list', [ManagerController::class, 'index']);
    Route::post('/manager_add', [ManagerController::class, 'add']);
    Route::post('/manager_edit', [ManagerController::class, 'edit']);
    Route::post('/manager_activation', [ManagerController::class, 'activation']);
    Route::get('/manager_roles', [ManagerController::class, 'roles']);

    // test
    Route::get('/whiteurl_global', [UrlwhitelistController::class, 'white_url_global']);

    Route::get('/acl_corss', [AclController::class, 'get_corss']);
    Route::get('/acl_acl', [AclController::class, 'searchAcl']);

    Route::post('/acl_search', [AclController::class, 'search']);
    Route::post('/port_list', [PortDefinedController::class, 'index']);
    Route::post('/port_add', [PortDefinedController::class, 'add']);
    Route::post('/port_del', [PortDefinedController::class, 'del']);
});
