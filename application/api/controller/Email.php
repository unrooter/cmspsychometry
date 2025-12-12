<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Validate;

/**
 * 邮件接口
 */
class Email extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    //给未来的自己一封信
    public function sendEmail()
    {
        $content = $this->request->post('content','');
        $pre_send_time = $this->request->post('pre_send_time',0);
        $from_name = $this->request->post('from_name','');
        $from_email = $this->request->post('from_email','');
        $to_name = $this->request->post('to_name','');
        $to_email = $this->request->post('to_email','');
        $is_public = $this->request->post('is_public',0);

        if(empty($content)){
            $this->error('请填写邮件内容');
        }
        if(empty($pre_send_time)){
            $this->error('请选择发送时间');
        }
        if(empty($from_name) || empty($from_email)){
            $this->error('请填写完整您的信息');
        }
        if(empty($to_name) || empty($to_email)){
            $this->error('请填写完整收信人信息');
        }
        $add_data['content'] = $content;
        $add_data['pre_send_time'] = $pre_send_time;
        $add_data['from_name'] = $from_name;
        $add_data['from_email'] = $from_email;
        $add_data['to_name'] = $to_name;
        $add_data['to_email'] = $to_email;
        $add_data['is_public'] = $is_public;
        $modelEmail = new \app\admin\model\Email();
        if($modelEmail->insert($add_data)){
            $this->success('提交成功');
        }else{
            $this->error('提交失败');
        }
    }

    //给未来的计划一个询问
    public function sendPlan()
    {
        $title = $this->request->post('title','');
        $theme = $this->request->post('theme','');
        $to_name = $this->request->post('to_name','');
        $to_email = $this->request->post('to_email','');
        $pre_send_time = $this->request->post('pre_send_time',0);
        if(empty($title)){
            $this->error('请填写问题');
        }
        if(empty($theme)){
            $this->error('请选择主题');
        }
        if(empty($pre_send_time)){
            $this->error('请选择时间');
        }
        if(empty($to_name) || empty($to_email)){
            $this->error('请填写完整收信人信息');
        }
        $add_data['title'] = $title;
        $add_data['theme'] = $theme;
        $add_data['to_name'] = $to_name;
        $add_data['to_email'] = $to_email;
        $add_data['pre_send_time'] = $pre_send_time;
        $modelPlan = new \app\admin\model\Plan();
        if($modelPlan->insert($add_data)){
            $this->success('提交成功');
        }else{
            $this->error('提交失败');
        }
    }
}
