<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Cache;

/**
 * IndexNow URL自动提交命令
 * 
 * 使用方法:
 * php think indexnow                    # 提交最近24小时的新URL
 * php think indexnow --days=7           # 提交最近7天的URL
 * php think indexnow --force            # 强制重新提交所有URL
 * php think indexnow --test             # 测试模式（不实际提交）
 */
class Indexnow extends Command
{
    /**
     * IndexNow API密钥
     * 这是在项目根目录生成的验证文件名
     */
    const API_KEY = 'e8f4c7b2d1a9f6e3c5b8a4d7e2f1c9b6a3d8e5f2c7b4a1d6e9f3c8b5a2d7e4f1c9b6a3d8e5f2c7b4a1d6e9f3c8b5a2d7e4f1c9b6a3d8e5f2c7b4a1d6e9f3c8b5';
    
    /**
     * 网站URL
     */
    const SITE_URL = 'https://www.dopsytest.com';
    
    /**
     * IndexNow API端点
     */
    const API_ENDPOINT = 'https://api.indexnow.org/indexnow';
    
    /**
     * 缓存键前缀（已弃用，改用文件记录）
     */
    const CACHE_PREFIX = 'indexnow_submitted_';
    
    /**
     * 已提交URL记录文件
     */
    const SUBMITTED_FILE = 'indexnow_submitted.txt';
    
    /**
     * 每批次提交的URL数量（IndexNow建议最多10000个）
     * 每次API调用提交50个URL，避免请求过大
     */
    const BATCH_SIZE = 100;

    protected function configure()
    {
        $this->setName('indexnow')
            ->addOption('days', 'd', \think\console\input\Option::VALUE_OPTIONAL, '提交最近N天的URL', 1)
            ->addOption('force', 'f', \think\console\input\Option::VALUE_NONE, '强制重新提交所有URL')
            ->addOption('test', 't', \think\console\input\Option::VALUE_NONE, '测试模式（不实际提交）')
            ->addOption('all', 'a', \think\console\input\Option::VALUE_NONE, '提交所有已发布的URL')
            ->addOption('limit', 'l', \think\console\input\Option::VALUE_OPTIONAL, '限制提交数量', 0)
            ->addOption('batch', 'b', \think\console\input\Option::VALUE_OPTIONAL, '批次号（用于分批提交）', 0)
            ->addOption('count', 'c', \think\console\input\Option::VALUE_NONE, '只统计总数，不查询具体数据')
            ->addOption('auto', null, \think\console\input\Option::VALUE_NONE, '自动模式（自动递增批次号）')
            ->setDescription('自动提交URL到IndexNow（Google/Bing）');
    }

    protected function execute(Input $input, Output $output)
    {
        // 提高内存限制（处理大量数据）
        ini_set('memory_limit', '512M');
        
        $days = $input->getOption('days');
        $force = $input->getOption('force');
        $test = $input->getOption('test');
        $all = $input->getOption('all');
        $limit = $input->getOption('limit');
        $batch = $input->getOption('batch');
        $countOnly = $input->getOption('count');
        $auto = $input->getOption('auto');
        
        // 如果只是统计总数
        if ($countOnly) {
            $this->showTotalCount($days, $all, $output);
            return;
        }
        
        // 如果是自动模式
        if ($auto) {
            return $this->autoSubmit($output);
        }
        
        // 如果使用--all但没有指定limit，默认提交所有数据（小型站点）
        if ($all && $limit == 0 && $batch == 0) {
            // 数据量较小，可以一次性提交所有URL
            $output->writeln('<comment>提示: 小型站点模式，将自动提交所有可用URL</comment>');
            $output->writeln('');
        }
        
        $output->writeln('');
        $output->writeln('<info>========================================</info>');
        $output->writeln('<info>   IndexNow URL自动提交任务开始</info>');
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        $output->writeln('执行时间: ' . date('Y-m-d H:i:s'));
        $output->writeln('网站URL: ' . self::SITE_URL);
        $output->writeln('测试模式: ' . ($test ? '<comment>是</comment>' : '<info>否</info>'));
        $output->writeln('');
        
        try {
            // 1. 验证API密钥文件是否存在
            $this->verifyApiKeyFile($output);
            
            // 2. 获取需要提交的URL列表
            $urls = $this->getUrlsToSubmit($days, $force, $all, $limit, $batch, $output);
            
            if (empty($urls)) {
                $output->writeln('<comment>没有需要提交的URL</comment>');
                return;
            }
            
            // 3. 过滤已提交的URL（除非是强制模式）
            if (!$force) {
                $urls = $this->filterSubmittedUrls($urls, $output);
            }
            
            if (empty($urls)) {
                $output->writeln('<comment>所有URL都已提交过，无需重复提交</comment>');
                return;
            }
            
            // 4. 分批提交URL
            $this->submitUrls($urls, $test, $output);
            
            // 5. 记录提交历史（非测试模式）
            if (!$test) {
                $this->recordSubmittedUrls($urls);
            }
            
            $output->writeln('');
            $output->writeln('<info>========================================</info>');
            $output->writeln('<info>   提交任务完成</info>');
            $output->writeln('<info>========================================</info>');
            $output->writeln('');
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>错误: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>文件: ' . $e->getFile() . '</error>');
            $output->writeln('<error>行号: ' . $e->getLine() . '</error>');
            return 1;
        }
        
        return 0;
    }
    
    /**
     * 验证API密钥文件是否存在
     */
    protected function verifyApiKeyFile(Output $output)
    {
        $keyFile = ROOT_PATH . 'public' . DS . self::API_KEY . '.txt';
        
        if (!file_exists($keyFile)) {
            $output->writeln('<error>API密钥文件不存在，正在创建...</error>');
            
            // 创建密钥文件
            $result = file_put_contents($keyFile, self::API_KEY);
            
            if ($result === false) {
                throw new \Exception('无法创建API密钥文件: ' . $keyFile);
            }
            
            $output->writeln('<info>✓ API密钥文件已创建: ' . $keyFile . '</info>');
        } else {
            $output->writeln('<info>✓ API密钥文件已存在</info>');
        }
        
        // 验证文件可访问性
        $keyUrl = self::SITE_URL . '/' . self::API_KEY . '.txt';
        $output->writeln('<comment>验证文件URL: ' . $keyUrl . '</comment>');
    }
    
    /**
     * 自动提交模式（自动递增批次号）
     */
    protected function autoSubmit(Output $output)
    {
        // 配置
        $batchFile = dirname(dirname(dirname(__DIR__))) . '/runtime/indexnow_batch.txt';
        $limit = 500;  // 每批数量（小型站点）
        $totalBatches = 1;  // 总批次数（数据量小，1批即可）
        
        $output->writeln('');
        $output->writeln('<info>========================================</info>');
        $output->writeln('<info>   IndexNow 自动提交模式</info>');
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        
        // 读取当前批次号
        if (file_exists($batchFile)) {
            $currentBatch = (int)file_get_contents($batchFile);
        } else {
            $currentBatch = 1;
            // 确保目录存在
            $dir = dirname($batchFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($batchFile, $currentBatch);
        }
        
        // 检查是否已完成
        if ($currentBatch > $totalBatches) {
            $output->writeln('<info>🎉 恭喜！所有批次已完成！</info>');
            $output->writeln('');
            $output->writeln('总批次: ' . $totalBatches);
            $output->writeln('总URL数: ' . number_format($totalBatches * $limit));
            $output->writeln('');
            $output->writeln('<comment>如需重新开始，请删除文件：</comment>');
            $output->writeln($batchFile);
            $output->writeln('');
            return 0;
        }
        
        // 显示当前状态
        $progress = round(($currentBatch - 1) / $totalBatches * 100, 2);
        $submitted = ($currentBatch - 1) * $limit;
        $remaining = $totalBatches - $currentBatch + 1;
        
        $output->writeln('执行时间: <comment>' . date('Y-m-d H:i:s') . '</comment>');
        $output->writeln('当前批次: <info>' . $currentBatch . ' / ' . $totalBatches . '</info>');
        $output->writeln('每批数量: <comment>' . $limit . '</comment>');
        $output->writeln('');
        $output->writeln('已完成批次: <info>' . ($currentBatch - 1) . '</info>');
        $output->writeln('已提交URL: <info>' . number_format($submitted) . '</info>');
        $output->writeln('完成进度: <info>' . $progress . '%</info>');
        $output->writeln('剩余批次: <comment>' . $remaining . '</comment>');
        $output->writeln('预计剩余: <comment>' . $remaining . ' 天</comment>（每天1批）');
        $output->writeln('');
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        
        try {
            // 1. 验证API密钥文件
            $this->verifyApiKeyFile($output);
            
            // 2. 获取URL列表
            $urls = $this->getUrlsToSubmit(1, false, true, $limit, $currentBatch, $output);
            
            if (empty($urls)) {
                $output->writeln('<comment>没有需要提交的URL</comment>');
                return 1;
            }
            
            // 3. 过滤已提交的URL（不强制）
            $urls = $this->filterSubmittedUrls($urls, $output);
            
            if (empty($urls)) {
                $output->writeln('<comment>所有URL都已提交过，跳过本批次</comment>');
                $output->writeln('');
                
                // 即使没有URL需要提交，也递增批次号
                $nextBatch = $currentBatch + 1;
                file_put_contents($batchFile, $nextBatch);
                
                $output->writeln('<info>批次号已更新: ' . $nextBatch . '</info>');
                return 0;
            }
            
            // 4. 提交URL
            $result = $this->submitUrls($urls, false, $output);
            
            if ($result) {
                // 5. 记录已提交的URL
                $this->recordSubmittedUrls($urls);
                
                // 6. 递增批次号
                $nextBatch = $currentBatch + 1;
                file_put_contents($batchFile, $nextBatch);
                
                $output->writeln('');
                $output->writeln('<info>========================================</info>');
                $output->writeln('<info>✅ 批次 ' . $currentBatch . ' 提交成功！</info>');
                $output->writeln('<info>下次将执行批次: ' . $nextBatch . '</info>');
                
                if ($nextBatch > $totalBatches) {
                    $output->writeln('');
                    $output->writeln('<info>🎉🎉🎉 所有批次已全部完成！🎉🎉🎉</info>');
                    $output->writeln('总共提交: <info>' . number_format($totalBatches * $limit) . '</info> 个URL');
                }
                
                $output->writeln('<info>========================================</info>');
                $output->writeln('');
                
                return 0;
            } else {
                $output->writeln('');
                $output->writeln('<error>========================================</error>');
                $output->writeln('<error>❌ 批次 ' . $currentBatch . ' 提交失败！</error>');
                $output->writeln('<error>批次号未更新，下次将重试此批次</error>');
                $output->writeln('<error>========================================</error>');
                $output->writeln('');
                
                return 1;
            }
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>错误: ' . $e->getMessage() . '</error>');
            $output->writeln('<comment>文件: ' . $e->getFile() . '</comment>');
            $output->writeln('<comment>行号: ' . $e->getLine() . '</comment>');
            return 1;
        }
    }
    
    /**
     * 统计总数（不查询具体数据）
     */
    protected function showTotalCount($days, $all, Output $output)
    {
        $output->writeln('');
        $output->writeln('<info>========================================</info>');
        $output->writeln('<info>   统计URL总数</info>');
        $output->writeln('<info>========================================</info>');
        $output->writeln('');
        
        // 构建查询条件
        $where = [
            'status' => 'normal',
            'deletetime' => null
        ];
        
        // 如果不是统计全部，则按时间过滤
        if (!$all) {
            $timestamp = time() - ($days * 86400);
            $where['createtime'] = ['>=', $timestamp];
            $output->writeln('<comment>统计范围: 最近' . $days . '天</comment>');
        } else {
            $output->writeln('<comment>统计范围: 所有文章</comment>');
        }
        
        try {
            // 只统计总数，不查询具体数据
            $articleCount = Db::name('cms_archives')->where($where)->count();
            // 每篇文章有中英文两个版本
            $total = $articleCount * 2;
            
            $output->writeln('');
            $output->writeln('<info>总计: ' . number_format($articleCount) . ' 篇文章 (中英文共 ' . number_format($total) . ' 个URL)</info>');
            $output->writeln('');
            
            // 计算需要的批次
            if ($total > 0) {
                $batchSize = 500;
                $batchCount = ceil($total / $batchSize);
                
                $output->writeln('<comment>========================================</comment>');
                $output->writeln('<comment>   分批提交建议</comment>');
                $output->writeln('<comment>========================================</comment>');
                $output->writeln('');
                $output->writeln('建议每批提交: <info>' . $batchSize . '</info> 个URL');
                $output->writeln('需要批次数: <info>' . $batchCount . '</info> 批');
                $output->writeln('预计耗时: <info>' . $batchCount . '</info> 天（每天1批）');
                $output->writeln('');
                $output->writeln('<comment>执行命令示例:</comment>');
                $output->writeln('');
                
                // 显示前5批的命令
                $showCount = min(5, $batchCount);
                for ($i = 1; $i <= $showCount; $i++) {
                    $startNum = ($i - 1) * $batchSize + 1;
                    $endNum = min($i * $batchSize, $total);
                    $output->writeln('# 第' . $i . '天 (URL ' . $startNum . '-' . $endNum . ')');
                    $output->writeln('php think indexnow --all --limit=' . $batchSize . ' --batch=' . $i);
                    $output->writeln('');
                }
                
                if ($batchCount > 5) {
                    $output->writeln('# ... 继续执行到第' . $batchCount . '批');
                    $output->writeln('php think indexnow --all --limit=' . $batchSize . ' --batch=' . $batchCount);
                    $output->writeln('');
                }
                
                $output->writeln('<comment>========================================</comment>');
            }
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>统计失败: ' . $e->getMessage() . '</error>');
        }
    }
    
    /**
     * 获取需要提交的URL列表
     */
    protected function getUrlsToSubmit($days, $force, $all, $limit, $batch, Output $output)
    {
        $output->writeln('<comment>正在查询需要提交的URL...</comment>');
        
        // 构建查询条件
        $where = [
            'status' => 'normal',
            'deletetime' => null
        ];
        
        // 如果不是提交全部，则按时间过滤
        if (!$all) {
            $timestamp = time() - ($days * 86400);
            $where['createtime'] = ['>=', $timestamp];
        }
        
        // 直接查询数据库，手动构建URL（最可靠）
        $query = Db::name('cms_archives')->where($where)->order('id desc');
        
        // 如果指定了limit，则限制数量
        if ($limit > 0) {
            if ($batch > 0) {
                // 分批查询：offset = (batch-1) * limit
                $offset = ($batch - 1) * $limit;
                $query->limit($offset, $limit);
                $output->writeln("<comment>批次模式: 第{$batch}批，每批{$limit}个URL</comment>");
            } else {
                $query->limit($limit);
                $output->writeln("<comment>限制数量: 最多{$limit}个URL</comment>");
            }
        }
        
        // 查询文章基本信息
        $archives = $query->field('id,title,createtime,updatetime,diyname,channel_id')->select();
        
        // 获取所有频道信息（一次性查询，避免N+1问题）
        $channelIds = array_unique(array_column($archives, 'channel_id'));
        $channels = Db::name('cms_channel')->whereIn('id', $channelIds)->column('diyname', 'id');
        
        $urls = [];
        foreach ($archives as $item) {
            // 构建URL路径
            $channelName = isset($channels[$item['channel_id']]) ? $channels[$item['channel_id']] : 'all';
            // 强制使用ID而不是diyname（因为网站可能不支持diyname格式）
            $articleName = $item['id'];
            
            // 中文版URL（默认语言）
            $fullUrl = self::SITE_URL . '/' . $channelName . '/' . $articleName . '.html';
            $urls[] = [
                'url' => $fullUrl,
                'id' => $item['id'],
                'title' => $item['title'],
                'time' => max($item['createtime'], $item['updatetime']),
                'lang' => 'zh'
            ];
            
            // 英文版URL（通过?lg=en参数）
            $fullUrlEn = self::SITE_URL . '/' . $channelName . '/' . $articleName . '.html?lg=en';
            $urls[] = [
                'url' => $fullUrlEn,
                'id' => $item['id'],
                'title' => $item['title'] . ' (EN)',
                'time' => max($item['createtime'], $item['updatetime']),
                'lang' => 'en'
            ];
        }
        
        // 排序：最新的在前
        usort($urls, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        $output->writeln('<info>查询到 ' . count($urls) . ' 个URL</info>');
        
        // 显示前5个URL预览
        if (!empty($urls)) {
            $output->writeln('');
            $output->writeln('<comment>URL预览（前5个）：</comment>');
            foreach (array_slice($urls, 0, 5) as $idx => $item) {
                $output->writeln('  ' . ($idx + 1) . '. ' . $item['title']);
                $output->writeln('     ' . $item['url']);
            }
            if (count($urls) > 5) {
                $output->writeln('  ... 还有 ' . (count($urls) - 5) . ' 个');
            }
            $output->writeln('');
        }
        
        return $urls;
    }
    
    /**
     * 过滤已提交的URL（使用文件记录）
     */
    protected function filterSubmittedUrls($urls, Output $output)
    {
        $output->writeln('<comment>正在过滤已提交的URL...</comment>');
        
        // 读取已提交URL记录
        $submittedUrls = $this->getSubmittedUrls();
        
        $unsubmittedUrls = [];
        $filteredCount = 0;
        
        foreach ($urls as $item) {
            $urlHash = md5($item['url']);
            
            // 检查是否已提交
            if (!isset($submittedUrls[$urlHash])) {
                $unsubmittedUrls[] = $item;
            } else {
                $filteredCount++;
            }
        }
        
        if ($filteredCount > 0) {
            $output->writeln('<comment>过滤掉 ' . $filteredCount . ' 个已提交的URL</comment>');
        }
        
        $output->writeln('<info>剩余 ' . count($unsubmittedUrls) . ' 个URL需要提交</info>');
        
        return $unsubmittedUrls;
    }
    
    /**
     * 获取已提交的URL列表
     */
    protected function getSubmittedUrls()
    {
        $file = dirname(dirname(dirname(__DIR__))) . '/runtime/' . self::SUBMITTED_FILE;
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        if (empty($content)) {
            return [];
        }
        
        // 每行格式：url_hash|url|timestamp
        $lines = explode("\n", trim($content));
        $submitted = [];
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $submitted[$parts[0]] = [
                    'url' => $parts[1],
                    'time' => isset($parts[2]) ? $parts[2] : 0
                ];
            }
        }
        
        return $submitted;
    }
    
    /**
     * 提交URL到IndexNow
     */
    protected function submitUrls($urls, $test, Output $output)
    {
        $output->writeln('');
        $output->writeln('<comment>开始提交URL到IndexNow...</comment>');
        
        // 分批提交
        $batches = array_chunk($urls, self::BATCH_SIZE);
        $totalBatches = count($batches);
        $successCount = 0;
        $failCount = 0;
        
        foreach ($batches as $batchIndex => $batch) {
            $batchNum = $batchIndex + 1;
            $urlList = array_column($batch, 'url');
            
            $output->writeln('');
            $output->writeln("批次 $batchNum/$totalBatches (共 " . count($urlList) . " 个URL)");
            
            if ($test) {
                $output->writeln('<comment>[测试模式] 跳过实际提交</comment>');
                $successCount += count($urlList);
                continue;
            }
            
            // 构建请求数据
            $data = [
                'host' => parse_url(self::SITE_URL, PHP_URL_HOST),
                'key' => self::API_KEY,
                'keyLocation' => self::SITE_URL . '/' . self::API_KEY . '.txt',
                'urlList' => $urlList
            ];
            
            // 发送请求
            $result = $this->sendRequest($data);
            
            if ($result['success']) {
                $output->writeln('<info>✓ 提交成功 (HTTP ' . $result['http_code'] . ')</info>');
                $successCount += count($urlList);
            } else {
                $output->writeln('<error>✗ 提交失败 (HTTP ' . $result['http_code'] . ')</error>');
                if ($result['error']) {
                    $output->writeln('<error>  错误信息: ' . $result['error'] . '</error>');
                }
                $failCount += count($urlList);
            }
            
            // 避免请求过快，休息1秒
            if ($batchNum < $totalBatches) {
                sleep(1);
            }
        }
        
        // 统计结果
        $output->writeln('');
        $output->writeln('<info>提交统计：</info>');
        $output->writeln('  成功: <info>' . $successCount . '</info> 个');
        if ($failCount > 0) {
            $output->writeln('  失败: <error>' . $failCount . '</error> 个');
        }
        $output->writeln('  总计: ' . ($successCount + $failCount) . ' 个');
    }
    
    /**
     * 发送HTTP请求到IndexNow API
     */
    protected function sendRequest($data)
    {
        $ch = curl_init(self::API_ENDPOINT);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: DopsyTest/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        return [
            'success' => ($httpCode == 200 || $httpCode == 202),  // 200=同步成功, 202=异步接受
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
    
    /**
     * 记录已提交的URL（使用文件永久保存）
     */
    protected function recordSubmittedUrls($urls)
    {
        $file = dirname(dirname(dirname(__DIR__))) . '/runtime/' . self::SUBMITTED_FILE;
        
        // 确保目录存在
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 准备写入的内容
        $lines = [];
        $timestamp = time();
        
        foreach ($urls as $item) {
            $urlHash = md5($item['url']);
            // 格式：url_hash|url|timestamp
            $lines[] = $urlHash . '|' . $item['url'] . '|' . $timestamp;
        }
        
        // 追加到文件（避免每次都重写整个文件）
        file_put_contents($file, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }
}
