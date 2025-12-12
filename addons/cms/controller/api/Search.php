<?php

namespace addons\cms\controller\api;

use addons\cms\library\FulltextSearch;
use addons\cms\library\Service;
use addons\cms\model\Archives;
use addons\cms\model\SearchLog;
use think\Config;
use think\Session;

/**
 * 搜索控制器
 */
class Search extends Base
{
    protected $noNeedLogin = ['index'];

    public function index()
    {
        $config = get_addon_config('cms');

        $search = $this->request->request("search", $this->request->request("q", ""));
        $search = mb_substr($search, 0, 100);
        if ($search) {
            $log = SearchLog::getByKeywords($search);
            if ($log) {
                $log->setInc("nums");
            } else {
                SearchLog::create(['keywords' => $search, 'nums' => 1]);
            }
        }
        $filterList = [];
        $orderList = [];

        $orderby = $this->request->get('orderby', '');
        $orderway = $this->request->get('orderway', '', 'strtolower');
        $params = ['q' => $search];
        if ($orderby) {
            $params['orderby'] = $orderby;
        }
        if ($orderway) {
            $params['orderway'] = $orderway;
        }

        //默认排序字段
        $orders = [
            ['name' => 'default', 'field' => 'weigh', 'title' => __('Default')],
            ['name' => 'views', 'field' => 'views', 'title' => __('Views')],
            ['name' => 'id', 'field' => 'id', 'title' => __('Post date')],
        ];

        //获取排序列表
        list($orderList, $orderby, $orderway) = Service::getOrderList($orderby, $orderway, $orders, $params);

        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $currentLang = ($lang == 'en') ? 'en' : 'zh';
        
        // 先获取有对应语言版本的文章ID
        $availableArchivesIds = \addons\cms\model\ArchivesContent::where('lang', $currentLang)
            ->column('archives_id');
        
        $pageList = Archives
            ::where('status', 'normal')
            ->where('id', 'in', $availableArchivesIds) // 只显示有对应语言版本的文章
            ->where(function ($query) use ($search) {
                $keywordArr = explode(' ', $search);
                foreach ($keywordArr as $index => $item) {
                    $query->where('title', 'like', '%' . $item . '%');
                }
            })
            ->whereNull('deletetime')
            ->order($orderby, $orderway)
            ->paginate(10, $config['pagemode'] == 'simple');

        // 获取当前语言并处理多语言内容
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $currentLang = ($lang == 'en') ? 'en' : 'zh';
        
        // 批量获取多语言内容
        $archivesIds = $pageList->column('id');
        $multilangContents = [];
        if (!empty($archivesIds)) {
            $multilangContents = \addons\cms\model\ArchivesContent::where('archives_id', 'in', $archivesIds)
                ->where('lang', $currentLang)
                ->column('title,sub_title,content,seo_title,keywords,description', 'archives_id');
        }
        
        foreach ($pageList as $item) {
            // 使用多语言内容替换原始内容
            if (isset($multilangContents[$item['id']])) {
                $multilangContent = $multilangContents[$item['id']];
                if (!empty($multilangContent['title'])) {
                    $item['title'] = $multilangContent['title'];
                }
                if (!empty($multilangContent['sub_title'])) {
                    $item['sub_title'] = $multilangContent['sub_title'];
                }
                if (!empty($multilangContent['content'])) {
                    $item['content'] = $multilangContent['content'];
                }
                if (!empty($multilangContent['seo_title'])) {
                    $item['seotitle'] = $multilangContent['seo_title'];
                }
                if (!empty($multilangContent['keywords'])) {
                    $item['keywords'] = $multilangContent['keywords'];
                }
                if (!empty($multilangContent['description'])) {
                    $item['description'] = $multilangContent['description'];
                }
            }
            
            $item->append(['images_list']);
            $item->id = $config['archiveshashids'] ? $item->eid : $item->id;
        }

        $pageList->appends(array_filter($params));

        Config::set('cms.title', __("Search for %s", $search));
        $this->success('', [
            'filterList' => $filterList,
            'orderList'  => $orderList,
            'pageList'   => $pageList
        ]);
    }

}
