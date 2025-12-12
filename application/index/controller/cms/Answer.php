<?php

namespace app\index\controller\cms;

use app\admin\model\cms\Channel;
use app\common\controller\Frontend;

/**
 * 我的消费订单
 */
class Answer extends Frontend
{
    protected $layout = 'layout_cms';
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];
    public function _initialize()
    {
        parent::_initialize();
        $configcms = get_addon_config('cms');
        $this->view->assign('configcms', $configcms);
        $this->view->assign('__CHANNEL__', null);
        $this->view->assign('isWechat', strpos($this->request->server('HTTP_USER_AGENT'), 'MicroMessenger') !== false);
    }
    /**
     * 我的消费订单
     */
    public function index()
    {
        $channel_ids  = Channel::where(['parent_id'=>['in',[2,27]]])->column('id');
        $user_id = $this->auth->id;
        $answerList = \app\admin\model\user\Answer::alias('ua')
            ->join('cms_archives ca', 'ca.id=ua.aid','left')
            ->join('fa_psychometry_test pt', 'pt.archives_id=ca.id','left')
            ->where(['ua.user_id'=>$user_id,'ca.channel_id'=>['in',$channel_ids]])
            ->order('ua.id', 'desc')
            ->field('ua.id as user_answer_id,ua.aid as product_id,ua.pay_type,ua.paytime,ua.create_time as test_time,ca.id as article_id,ca.tags,ca.title,ca.image,ca.channel_id,ca.price,ca.diyname,pt.test_type')
            ->paginate(10, null);
        
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $currentLang = ($lang == 'en') ? 'en' : 'zh';
        
        // 为每条记录获取多语言标题和生成结果链接
        foreach ($answerList as &$item) {
            if ($item['article_id']) {
                $archives = \addons\cms\model\Archives::get($item['article_id']);
                if ($archives) {
                    $multilangContent = $archives->getMultilangContent($currentLang);
                    if ($multilangContent && !empty($multilangContent['title'])) {
                        $item['title'] = $multilangContent['title'];
                    } elseif (empty($item['title'])) {
                        // 如果多语言标题也没有，使用文章标题
                        $item['title'] = $archives->title;
                    }
                }
            }
            // 根据测试类型生成结果页面URL
            $testType = $item['test_type'] ?? 'score';
            $resultPages = [
                'mbti' => '/p/results_mbti.html',
                'score' => '/p/results_score.html',
                'dimension' => '/p/results_dimension.html',
                'custom' => '/p/results_custom.html',
                'nine_type' => '/p/results_nine_type.html',
                'multiple_type' => '/p/results_multiple.html'
            ];
            $item['result_url'] = ($resultPages[$testType] ?? '/p/results_score.html') . '?answer_id=' . $item['user_answer_id'];
        }

        $this->view->assign('answerList', $answerList);
        return $this->view->fetch();
    }
    public function fun()
    {
        $channel_ids  = Channel::where(['parent_id'=>25])->column('id');
        $user_id = $this->auth->id;
        $answerList = \app\admin\model\user\Answer::alias('ua')
            ->join('cms_archives ca', 'ca.id=ua.aid','left')
            ->join('fa_psychometry_test pt', 'pt.archives_id=ca.id','left')
            ->where(['ua.user_id'=>$user_id,'ca.channel_id'=>['in',$channel_ids]])
            ->order('ua.id', 'desc')
            ->field('ua.id as user_answer_id,ua.aid as product_id,ua.pay_type,ua.paytime,ua.create_time as test_time,ca.id as article_id,ca.tags,ca.title,ca.image,ca.channel_id,ca.price,ca.diyname,pt.test_type')
            ->paginate(10, null);
        
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $currentLang = ($lang == 'en') ? 'en' : 'zh';
        
        // 为每条记录获取多语言标题和生成结果链接
        foreach ($answerList as &$item) {
            if ($item['article_id']) {
                $archives = \addons\cms\model\Archives::get($item['article_id']);
                if ($archives) {
                    $multilangContent = $archives->getMultilangContent($currentLang);
                    if ($multilangContent && !empty($multilangContent['title'])) {
                        $item['title'] = $multilangContent['title'];
                    } elseif (empty($item['title'])) {
                        // 如果多语言标题也没有，使用文章标题
                        $item['title'] = $archives->title;
                    }
                }
            }
            // 根据测试类型生成结果页面URL
            $testType = $item['test_type'] ?? 'score';
            $resultPages = [
                'mbti' => '/p/results_mbti.html',
                'score' => '/p/results_score.html',
                'dimension' => '/p/results_dimension.html',
                'custom' => '/p/results_custom.html',
                'nine_type' => '/p/results_nine_type.html',
                'multiple_type' => '/p/results_multiple.html'
            ];
            $item['result_url'] = ($resultPages[$testType] ?? '/p/results_score.html') . '?answer_id=' . $item['user_answer_id'];
        }

        $this->view->assign('answerList', $answerList);
        return $this->view->fetch();
    }
}
