<?php

namespace app\admin\command;

use addons\cms\model\Archives;
use app\admin\model\cms\Channel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Sitemap extends Command
{
    /**
     * 路径和文件名配置
     */
    protected $options = [];
    public $site_hosts = ['dopsytest.com'=>'dopsytest','www.dopsytest.com'=>'wwwdopsytest'];
    
    // 支持的语言
    protected $languages = ['zh-cn', 'en'];

    protected function configure()
    {
        $this->setName('sitemap')
            ->setDescription('生成多语言 Sitemap')
            ->addOption('type', 't', \think\console\input\Option::VALUE_OPTIONAL, '生成类型：all(全部)|xml(仅XML)|push(推送)', 'all')
            ->addOption('domain', 'd', \think\console\input\Option::VALUE_OPTIONAL, '指定域名：dopsytest|wwwdopsytest|all', 'all')
            ->addOption('ping', 'p', \think\console\input\Option::VALUE_NONE, '生成后通知搜索引擎');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getOption('type');
        $domain = $input->getOption('domain');
        $ping = $input->hasOption('ping');
        
        $output->writeln('<info>====================================</info>');
        $output->writeln('<info>  多语言 Sitemap 生成工具</info>');
        $output->writeln('<info>====================================</info>');
        $output->writeln('');
        
        try {
            // 生成 sitemap
            if ($type === 'all' || $type === 'xml') {
                $this->createMultilingualSitemapXml($input, $output, $domain);
            }
            
            // 推送到搜索引擎
            if ($type === 'all' || $type === 'push' || $ping) {
                $output->writeln('');
                $output->writeln('<comment>推送到搜索引擎...</comment>');
                // $this->bingpush($output);
                // $this->baidupush($output);
                $output->writeln('<info>如需自动推送，请取消代码中的注释</info>');
            }
            
            $output->writeln('');
            $output->writeln('<info>✅ 所有任务完成！</info>');
            
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>❌ 错误: ' . $e->getMessage() . '</error>');
            return 1;
        }
        
        return 0;
    }
    /**
     * 生成多语言 Sitemap XML
     * 为每个内容生成中文和英文版本（如果有翻译）
     */
    public function createMultilingualSitemapXml(Input $input, Output $output, $domainFilter = 'all'){
        ini_set('memory_limit', '4086M');
        $output->writeln('<comment>开始生成多语言 Sitemap...</comment>');
        $output->writeln('');
        
        $diyname = Channel::column('id,diyname');
        
        // 查询所有有英文翻译的文章
        $archivesWithEn = Db::table('fa_cms_archives_content')
            ->where('lang', 'en')
            ->column('archives_id');
        
        $output->writeln('<info>📊 统计信息：</info>');
        $output->writeln('   - 有英文翻译的文章: <comment>' . count($archivesWithEn) . '</comment> 篇');
        
        // 查询所有正常状态的文章（包含更新时间）
        $wherel = ['status' => 'normal'];
        $ars = Archives::field('id,channel_id,updatetime,publishtime')->where($wherel)->order('id asc')->select();
        
        $output->writeln('   - 文章总数: <comment>' . count($ars) . '</comment> 篇');
        
        // 过滤域名
        $hosts = $this->site_hosts;
        if ($domainFilter !== 'all') {
            $hosts = array_filter($hosts, function($name) use ($domainFilter) {
                return $name === $domainFilter;
            });
            if (empty($hosts)) {
                throw new \Exception("域名 '{$domainFilter}' 不存在");
            }
        }
        
        $output->writeln('   - 处理域名数: <comment>' . count($hosts) . '</comment> 个');
        $output->writeln('');
        
        foreach ($hosts as $site_host => $site_name){
            $urls = [];
            $baseUrl = 'https://'.$site_host.'/';
            
            // 添加首页（最高优先级）
            $urls[] = [
                'loc' => $baseUrl,
                'lang' => 'zh',
                'lastmod' => date('c'),
                'changefreq' => 'daily',
                'priority' => '1.0',
                'article_id' => 'home'
            ];
            $urls[] = [
                'loc' => $baseUrl . '?lg=en',
                'lang' => 'en',
                'lastmod' => date('c'),
                'changefreq' => 'daily',
                'priority' => '1.0',
                'article_id' => 'home'
            ];
            
            // 添加频道页（高优先级）
            $channels = Channel::where('status', 'normal')->select();
            foreach ($channels as $channel) {
                if ($channel['diyname']) {
                    // 中文频道页
                    $urls[] = [
                        'loc' => $baseUrl . $channel['diyname'],
                        'lang' => 'zh',
                        'lastmod' => date('c'),
                        'changefreq' => 'daily',
                        'priority' => '0.9',
                        'article_id' => 'channel_' . $channel['id']
                    ];
                    // 英文频道页
                    $urls[] = [
                        'loc' => $baseUrl . $channel['diyname'] . '?lg=en',
                        'lang' => 'en',
                        'lastmod' => date('c'),
                        'changefreq' => 'daily',
                        'priority' => '0.9',
                        'article_id' => 'channel_' . $channel['id']
                    ];
                }
            }
            
            // 添加文章页
            foreach($ars as $k => $v){
                if(isset($diyname[$v['channel_id']])){
                    $urlPath = $diyname[$v['channel_id']].'/'.$v['id'].'.html';
                    $lastmod = date('c', $v['updatetime'] ?: $v['publishtime']);
                    
                    // 添加中文版本（默认）
                    $urls[] = [
                        'loc' => $baseUrl . $urlPath,
                        'lang' => 'zh',
                        'lastmod' => $lastmod,
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                        'article_id' => $v['id']
                    ];
                    
                    // 如果有英文翻译，添加英文版本
                    if (in_array($v['id'], $archivesWithEn)) {
                        $urls[] = [
                            'loc' => $baseUrl . $urlPath . '?lg=en',
                            'lang' => 'en',
                            'lastmod' => $lastmod,
                            'changefreq' => 'weekly',
                            'priority' => '0.8',
                            'article_id' => $v['id']
                        ];
                    }
                }
            }
            
            $output->write('<info>🌐 域名: ' . $site_host . '</info>');
            $output->writeln(' (' . count($urls) . ' 个 URL)');
            
            // 分文件保存（每个文件最多 10000 个 URL）
            $num2 = 10000;
            $ic2 = ceil(count($urls)/$num2);
            
            for($i = 0; $i < $ic2; $i++){
                $si = $i*$num2;
                $site_data2 = array_slice($urls,$si,$num2);
                
                $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $xmlContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
                $xmlContent .= 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
                
                // 按文章分组，以便添加 hreflang 标签
                $groupedUrls = [];
                foreach($site_data2 as $item){
                    // 提取文章ID
                    preg_match('/\/(\d+)\.html/', $item['loc'], $matches);
                    $articleId = $matches[1] ?? null;
                    if ($articleId) {
                        if (!isset($groupedUrls[$articleId])) {
                            $groupedUrls[$articleId] = [];
                        }
                        $groupedUrls[$articleId][] = $item;
                    }
                }
                
                foreach($groupedUrls as $articleId => $versions){
                    // 为每个版本生成一个 URL 条目
                    foreach($versions as $version) {
                        $xmlContent .= "  <url>\n";
                        $xmlContent .= "    <loc>" . htmlspecialchars($version['loc']) . "</loc>\n";
                        
                        // 添加最后修改时间
                        if (isset($version['lastmod'])) {
                            $xmlContent .= "    <lastmod>{$version['lastmod']}</lastmod>\n";
                        }
                        
                        // 添加更新频率
                        $changefreq = $version['changefreq'] ?? 'weekly';
                        $xmlContent .= "    <changefreq>{$changefreq}</changefreq>\n";
                        
                        // 添加优先级
                        $priority = $version['priority'] ?? '0.8';
                        $xmlContent .= "    <priority>{$priority}</priority>\n";
                        
                        // 添加 hreflang 链接到所有语言版本
                        foreach($versions as $altVersion) {
                            $hreflang = $altVersion['lang'];
                            $xmlContent .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$hreflang}\" href=\"" . htmlspecialchars($altVersion['loc']) . "\"/>\n";
                        }
                        
                        // 设置 x-default 为中文版本
                        $defaultUrl = $versions[0]['loc']; // 中文版本总是第一个
                        $xmlContent .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . htmlspecialchars($defaultUrl) . "\"/>\n";
                        
                        $xmlContent .= "  </url>\n";
                    }
                }
                
                $xmlContent .= '</urlset>';
                
                $fileName = './public/sitemap-'.$site_name.'-'.$i.'.xml';
                file_put_contents($fileName, $xmlContent);
                $output->writeln('   ✓ ' . basename($fileName) . ' <comment>(' . count($site_data2) . ' URLs)</comment>');
            }
            
            // 生成 sitemap index 文件
            $this->createSitemapIndex($site_host, $site_name, $ic2, $output);
            $output->writeln('');
        }
        
        $output->writeln('<info>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</info>');
        $output->writeln('<info>✅ Sitemap 生成成功！</info>');
        $output->writeln('');
        $output->writeln('<comment>📤 提交到 Google Search Console:</comment>');
        $output->writeln('   https://www.dopsytest.com/sitemap-wwwdopsytest-index.xml');
        $output->writeln('<info>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</info>');
    }
    
    /**
     * 生成 Sitemap Index 文件
     */
    protected function createSitemapIndex($siteHost, $siteName, $fileCount, $output)
    {
        $baseUrl = 'https://'.$siteHost.'/';
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xmlContent .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        for($i = 0; $i < $fileCount; $i++){
            $xmlContent .= "  <sitemap>\n";
            $xmlContent .= "    <loc>{$baseUrl}sitemap-{$siteName}-{$i}.xml</loc>\n";
            $xmlContent .= "    <lastmod>" . date('c') . "</lastmod>\n";
            $xmlContent .= "  </sitemap>\n";
        }
        
        $xmlContent .= '</sitemapindex>';
        
        $fileName = './public/sitemap-'.$siteName.'-index.xml';
        file_put_contents($fileName, $xmlContent);
        $output->writeln('   ⭐ <info>' . basename($fileName) . '</info> <comment>(主索引)</comment>');
    }
}
