<?php

namespace addons\cms\controller;

use addons\cms\model\Autolink;
use think\Config;

/**
 * 跳转控制器
 * Class Go
 * @package addons\cms\controller
 */
class Go extends Base
{
    protected $noNeedLogin = ['*'];

    public function index()
    {
        $url = $this->request->get("url", "", 'trim,xss_clean');
        $id = $this->request->get("id/d", "0");
        if ($id) {
            $autolink = Autolink::get($id);
            if ($autolink) {
                $autolink->setInc("views");
                $this->redirect($autolink['url']);
            }
        }

        Config::set('cms.title', '跳转提示');
        return $this->view->fetch("/outlink", ['url' => $url]);
    }

}
