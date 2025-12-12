<?php

namespace app\admin\model;

use think\Model;


class Plan extends Model
{

    // 表名
    protected $name = 'plan';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'pre_send_time_text'
    ];
    

    



    public function getPreSendTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pre_send_time']) ? $data['pre_send_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPreSendTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
