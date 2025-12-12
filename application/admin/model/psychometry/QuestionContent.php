<?php

namespace app\admin\model\psychometry;

use think\Model;


class QuestionContent extends Model
{

    

    

    // 表名
    protected $name = 'psychometry_question_content';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'lang_text'
    ];
    

    
    public function getLangList()
    {
        return ['zh' => __('Zh'), 'en' => __('En'), 'ja' => __('Ja'), 'ko' => __('Ko')];
    }


    public function getLangTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lang']) ? $data['lang'] : '');
        $list = $this->getLangList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
