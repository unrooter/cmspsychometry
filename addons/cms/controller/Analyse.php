<?php

namespace addons\cms\controller;

use addons\cms\library\Service;
use addons\cms\model\Archives;
use app\admin\model\user\Answer;
use think\Config;
use think\Db;

/**
 * CMS单页控制器
 * @package addons\cms\controller
 */
class Analyse extends Base
{
    public function index()
    {
        $aid = $this->request->param('aid');
        $answer_info = Answer::where(['id'=>$aid])->find();
        $analyse_info = Db::name("psychometry_analyse")->where(['mark'=>$answer_info['result']])->find();

        $this->view->assign("analyse_info", $analyse_info);
        return $this->view->fetch('/analyse');
    }
}
