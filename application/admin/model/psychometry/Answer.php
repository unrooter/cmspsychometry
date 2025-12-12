<?php

namespace app\admin\model\psychometry;

use think\Model;

class Answer extends Model
{
    // 表名
    protected $name = 'psychometry_answer';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'result_type_text'
    ];

    public function getResultTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['result_type']) ? $data['result_type'] : '');
        $list = $this->getResultTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getResultTypeList()
    {
        return [
            'type' => __('类型'),
            'score' => __('分数'),
            'dimension' => __('维度'),
            'custom' => __('自定义')
        ];
    }

    public function test()
    {
        return $this->hasOne('Test', 'id', 'test_id')->setEagerlyType(0)->joinType('LEFT');
    }

    public function answerContent()
    {
        return $this->hasMany('AnswerContent', 'answer_id', 'id');
    }
}