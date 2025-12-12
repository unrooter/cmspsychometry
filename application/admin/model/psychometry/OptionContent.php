<?php

namespace app\admin\model\psychometry;

use think\Model;

class OptionContent extends Model
{
    // 表名
    protected $name = 'psychometry_option_content';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'lang_text'
    ];

    public function getLangTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lang']) ? $data['lang'] : '');
        $list = $this->getLangList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getLangList()
    {
        return [
            'zh' => __('中文'),
            'en' => __('英文'),
            'ja' => __('日文'),
            'ko' => __('韩文')
        ];
    }

    public function question()
    {
        return $this->belongsTo('Question', 'question_id', 'id');
    }
}
