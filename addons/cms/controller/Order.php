<?php

namespace addons\cms\controller;

use addons\cms\library\OrderException;
use addons\cms\model\Archives;
use app\admin\model\user\Answer;
use think\Exception;

/**
 * 订单控制器
 * Class Order
 * @package addons\cms\controller
 */
class Order extends Base
{

    /**
     * 创建订单并发起支付请求
     */
    public function submit()
    {
        $config = get_addon_config('cms');
        //是否需要登录后才可以支付
        if ($config['ispaylogin'] && !$this->auth->isLogin()) {
            $this->error("请登录后再进行操作!", "index/user/login");
        }
        $ua_id = $this->request->request('ua_id',0);
        $paytype = $this->request->request('paytype');
        $answer_info = Answer::get($ua_id);
        if (!$answer_info) {
            $this->error('未找到指定查询解析');
        }
        $archives = Archives::get($answer_info['aid']);

        try {
            $response = \addons\cms\library\Order::submit($ua_id, $paytype ? $paytype : $config['defaultpaytype']);
        } catch (OrderException $e) {
            if ($e->getCode() == 1) {
                $this->success($e->getMessage(), $archives->url);
            } else {
                $this->error($e->getMessage(), $archives->url);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), $archives->url);
        }

        return $response;
    }

    /**
     * 企业支付通知和回调
     */
    public function epay()
    {
        $type = $this->request->param('type');
        $paytype = $this->request->param('paytype');
        if ($type == 'notify') {
            $pay = \addons\epay\library\Service::checkNotify($paytype);
            if (!$pay) {
                echo '签名错误';
                return;
            }
            $data = $pay->verify();
            try {
                $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
                \addons\cms\library\Order::settle($data['out_trade_no'], $payamount);
            } catch (Exception $e) {
            }
            echo $pay->success();
        } else {
            $pay = \addons\epay\library\Service::checkReturn($paytype);
            if (!$pay) {
                $this->error('签名错误');
            }
            if ($pay === true) {
                //微信支付
                $data = ['out_trade_no' => $this->request->param('orderid')];
            } else {
                $data = $pay->verify();
            }

            $order = \addons\cms\model\Order::getByOrderid($data['out_trade_no']);
            if (!$order->archives) {
                $this->error('未找到文档信息!');
            }
            //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
            $this->redirect($order->archives->url);
            //$this->success("恭喜你！支付成功!", $order->archives->url);
        }
        return;
    }
}
