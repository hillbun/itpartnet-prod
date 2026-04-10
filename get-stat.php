<?php
// 定义脚本路径（建议使用绝对路径）
$scriptPath = "/tmp/stat.sh"; 
$minutes = 15;

// 执行脚本并获取输出内容
// 注意：escapeshellarg 是为了防止命令注入攻击
$command = "bash " . escapeshellarg($scriptPath) . " " . escapeshellarg($minutes);
$output = shell_exec($command);

if ($output === null) {
    echo json_encode(["error" => "NO DATA"]);
} else {
    // 告知浏览器返回的是 JSON 格式
    header('Content-Type: application/json; charset=utf-8');
    echo $output;
}
?>