<?php

namespace app\index\controller\cms;

use app\common\controller\Frontend;

/**
 * 我的消费订单
 */
class Order extends Frontend
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
        $user_id = $this->auth->id;
        $orderList = \addons\cms\model\Order::with(['archives'])->where('user_id', $user_id)
            ->where('status', 'paid')
            ->order('id', 'desc')
            ->paginate(10, null);

        $this->view->assign('config', array_merge($this->view->config, ['jsname' => '']));
        $this->view->assign('orderList', $orderList);
        $this->view->assign('title', '我的消费订单');
        return $this->view->fetch();
    }

}
