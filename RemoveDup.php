<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Writer;
use App\Http\Controllers\Api\ThreatController;

class RemoveDup extends Command
{
    protected $signature = 'ioc:remove-dup';
    private $pypath = '/usr/local/nginx/html/threat/archive/';

    protected $description = '去掉子域，只保留父域';

    public function handle()
    {
        ini_set('memory_limit', '5G');
        set_time_limit(0);

        $fileName1 = $this->pypath . "IOCS_" . date("Y-m-d", time()) . ".csv";
        $fileName2 = $this->pypath . "IOCS_" . date("Y-m-d", time()) . "_1.csv";
        
        if (!file_exists($fileName2)) {
            touch($fileName2);
        }
        
        exec("sudo /bin/chmod 644  $fileName1");
        exec("sudo /bin/chmod 644  $fileName2");

        $in  = $fileName1;
        $out = $fileName2;
        
        $this->processLargeCsv($in, $out);
        
        $threat = new ThreatController();
        $threat->upThreat();

        $this->info('✅ 完成！输出：' . basename($out));
        return 0;
    }
    
    /**
     * 处理大CSV文件的优化版本
     */
    private function processLargeCsv($inputFile, $outputFile)
    {
        // 方法1：分块处理，使用临时文件排序
        $this->processWithExternalSort($inputFile, $outputFile);
        
        // 或者使用方法2：哈希表去重（如果数据可以放入内存）
        // $this->processWithHashMap($inputFile, $outputFile);
    }
    
    /**
     * 方法1：使用外部排序（适合超大文件）
     */
    private function processWithExternalSort($inputFile, $outputFile)
    {
        $tempDir = storage_path('temp/');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // 第一步：读取并处理数据，写入临时文件（分块）
        $chunkSize = 50000; // 每5万条处理一次
        $chunkFiles = [];
        
        $reader = Reader::createFromPath($inputFile, 'r');
        $reader->setHeaderOffset(0);
        
        $chunkIndex = 0;
        $currentChunk = [];
        
        foreach ($reader->getRecords() as $row) {
            $domain = preg_replace('/^www\.+/i', '', trim($row['value']));
            $dots   = substr_count($domain, '.');
            $sortKey = sprintf('%02d%05d%s', $dots, strlen($domain), $domain);
            
            $currentChunk[] = [
                'key'    => $sortKey,
                'domain' => $domain,
                'row'    => $row
            ];
            
            // 达到分块大小，写入临时文件
            if (count($currentChunk) >= $chunkSize) {
                $this->sortAndSaveChunk($currentChunk, $tempDir, $chunkIndex);
                $chunkFiles[] = $tempDir . "chunk_{$chunkIndex}.tmp";
                $chunkIndex++;
                $currentChunk = [];
                gc_collect_cycles(); // 强制垃圾回收
            }
        }
        
        // 处理最后一批数据
        if (!empty($currentChunk)) {
            $this->sortAndSaveChunk($currentChunk, $tempDir, $chunkIndex);
            $chunkFiles[] = $tempDir . "chunk_{$chunkIndex}.tmp";
        }
        
        // 第二步：多路归并排序
        $this->mergeSortedChunks($chunkFiles, $outputFile);
        
        // 第三步：清理临时文件
        foreach ($chunkFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * 对分块数据进行排序并保存到临时文件
     */
    private function sortAndSaveChunk(&$chunk, $tempDir, $chunkIndex)
    {
        // 排序
        usort($chunk, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });
        
        // 保存到临时文件
        $tempFile = $tempDir . "chunk_{$chunkIndex}.tmp";
        $fp = fopen($tempFile, 'w');
        
        foreach ($chunk as $item) {
            fwrite($fp, serialize($item) . PHP_EOL);
        }
        
        fclose($fp);
        unset($chunk); // 释放内存
    }
    
    /**
     * 多路归并排序
     */
    private function mergeSortedChunks($chunkFiles, $outputFile)
    {
        $writer = Writer::createFromPath($outputFile, 'w');
        $writer->insertOne(['value', 'category', 'score']);
        
        // 打开所有临时文件
        $fileHandles = [];
        $currentLines = [];
        
        foreach ($chunkFiles as $index => $file) {
            $fileHandles[$index] = fopen($file, 'r');
            $line = fgets($fileHandles[$index]);
            if ($line !== false) {
                $currentLines[$index] = unserialize(trim($line));
            }
        }
        
        $keep = []; // 已保留的父域
        
        // 归并排序并去重
        while (!empty($currentLines)) {
            // 找到最小的key
            $minKey = null;
            $minIndex = null;
            
            foreach ($currentLines as $index => $data) {
                if ($minKey === null || strcmp($data['key'], $minKey) < 0) {
                    $minKey = $data['key'];
                    $minIndex = $index;
                }
            }
            
            $data = $currentLines[$minIndex];
            $domain = $data['domain'];
            
            // 检查是否是子域
            $isSubdomain = false;
            for ($i = 0; ($i = strpos($domain, '.', $i + 1)) !== false;) {
                $parent = substr($domain, $i + 1);
                if (isset($keep[$parent])) {
                    $isSubdomain = true;
                    break;
                }
            }
            
            // 如果不是子域且未保留过，则写入
            if (!$isSubdomain && !isset($keep[$domain])) {
                $keep[$domain] = true;
                $writer->insertOne([
                    $domain, 
                    $data['row']['category'], 
                    $data['row']['score']
                ]);
            }
            
            // 读取下一行
            $nextLine = fgets($fileHandles[$minIndex]);
            if ($nextLine !== false) {
                $currentLines[$minIndex] = unserialize(trim($nextLine));
            } else {
                fclose($fileHandles[$minIndex]);
                unset($currentLines[$minIndex]);
                unset($fileHandles[$minIndex]);
            }
        }
    }
    
    /**
     * 方法2：使用哈希表去重（如果数据可以放入内存）
     * 这个方法更简单，但需要足够内存存储所有域名
     */
    private function processWithHashMap($inputFile, $outputFile)
    {
        $reader = Reader::createFromPath($inputFile, 'r');
        $reader->setHeaderOffset(0);
        
        $writer = Writer::createFromPath($outputFile, 'w');
        $writer->insertOne(['value', 'category', 'score']);
        
        // 使用数组存储按点数分组的域名
        $domainsByDots = [];
        
        foreach ($reader->getRecords() as $row) {
            $domain = preg_replace('/^www\.+/i', '', trim($row['value']));
            $dots = substr_count($domain, '.');
            
            if (!isset($domainsByDots[$dots])) {
                $domainsByDots[$dots] = [];
            }
            
            $domainsByDots[$dots][$domain] = $row;
        }
        
        // 按点数从小到大处理（点数少的可能是父域）
        ksort($domainsByDots);
        
        $keep = []; // 已保留的域名
        
        foreach ($domainsByDots as $dots => $domains) {
            // 对当前点数的域名按长度排序
            uksort($domains, function ($a, $b) {
                return strlen($a) - strlen($b) ?: strcmp($a, $b);
            });
            
            foreach ($domains as $domain => $row) {
                // 检查是否是子域
                $isSubdomain = false;
                for ($i = 0; ($i = strpos($domain, '.', $i + 1)) !== false;) {
                    $parent = substr($domain, $i + 1);
                    if (isset($keep[$parent])) {
                        $isSubdomain = true;
                        break;
                    }
                }
                
                if (!$isSubdomain && !isset($keep[$domain])) {
                    $keep[$domain] = true;
                    $writer->insertOne([
                        $domain, 
                        $row['category'], 
                        $row['score']
                    ]);
                }
            }
            
            // 处理完当前点数后，释放内存
            unset($domainsByDots[$dots]);
            gc_collect_cycles();
        }
    }
}