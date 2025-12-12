<?php

namespace app\admin\model\cms;

use think\Model;

class Addonprofile extends Model
{
    // 表名
    protected $name = 'cms_addonprofile';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    // 追加属性
    protected $append = [
        'full_image'
    ];
    protected static $config = [];

    public function getFullImageAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['image']) ? cdnurl($data['image'],true) : '');
        return $value;
    }
}
