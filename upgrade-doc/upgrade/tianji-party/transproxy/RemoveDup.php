<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Writer;
use App\Http\Controllers\Api\ThreatController;

class RemoveDup extends Command
{
    protected $signature = 'ioc:remove-dup';
    private $pypath = '/usr/nginx/html/threat/archive/';
    protected $description = '去掉子域，只保留父域';

    public function handle()
    {
        ini_set('memory_limit', '15G');
        set_time_limit(0);

        $fileName1 = $this->pypath . "IOCS_" . date("Y-m-d", time()) . ".csv";
        $fileName2 = $this->pypath . "IOCS_" . date("Y-m-d", time()) . "_1.csv";
    	if (!file_exists($fileName2)) {          // 不存在
    		touch($fileName2);                   // 创建空文件
    	}
    	exec("sudo /bin/chmod 644  $fileName1");
        exec("sudo /bin/chmod 644  $fileName2");

        $in  = $fileName1;
        $out =  $fileName2;
        // echo $in . '----------------------' . $out;
        /* 1. 读 + 生成排序键 */
        $reader = Reader::createFromPath($in, 'r');
        $reader->setHeaderOffset(0);
        $records = [];
        foreach ($reader->getRecords() as $row) {

	    //Handle URLs with top-level domain names ending in .co, as well as the special domain name www.co
            if(trim($row['value']) == 'www.co'){
                $domain = trim($row['value']);
            }else{
                $domain = preg_replace('/^(www\.)+/i', '', trim($row['value']));
            }

            if($domain == 'co') continue;

            $dots   = substr_count($domain, '.');
            $records[] = [
                'key'    => sprintf('%02d%05d%s', $dots, strlen($domain), $domain),
                'domain' => $domain,
                'row'    => $row
            ];
        }

        /* 2. 排序 */
        usort($records, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });

        /* 3. 线性过滤 */
        $keep   = [];          // 已保留的父域
        $written = [];         // 已写盘的父域
        $writer = Writer::createFromPath($out, 'w');
        $writer->insertOne(['value', 'category', 'score']);

        foreach ($records as $rec) {
            $domain = $rec['domain'];
            for ($i = 0; ($i = strpos($domain, '.', $i + 1)) !== false;) {
                if (isset($keep[substr($domain, $i + 1)])) continue 2;
            }
            // 父域已写盘？直接跳过
            if (isset($written[$domain])) continue;

            $keep[$domain]    = true;
            $written[$domain] = true;      // 标记已写
            $writer->insertOne([$domain, $rec['row']['category'], $rec['row']['score']]);
        }

        unset($records);

        $threat = new ThreatController();
        $threat->upThreat();

        $this->info('✅ 完成！输出：' . basename($out));
        return 0;
    }
}
