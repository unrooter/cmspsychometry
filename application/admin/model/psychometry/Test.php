<?php

namespace app\admin\model\psychometry;

use think\Model;

class Test extends Model
{
    // 表名
    protected $name = 'psychometry_test';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'test_type_text',
        'show_type_text'
    ];

    public function getTestTypeList()
    {
        return [
            'mbti' => __('MBTI'),
            'score' => __('分数型'),
            'dimension' => __('维度型'),
            'custom' => __('自定义'),
            'nine_type' => __('九型人格'),
            'multiple_type' => __('多类型')
        ];
    }

    public function getTestTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['test_type']) ? $data['test_type'] : '');
        $list = $this->getTestTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getShowTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['show_type']) ? $data['show_type'] : '');
        $list = $this->getShowTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getShowTypeList()
    {
        return [
            'none' => __('不显示'),
            'immediate' => __('立即显示'),
            'delay' => __('延迟显示')
        ];
    }

    public function archives()
    {
        return $this->hasOne('\app\admin\model\cms\Archives', 'id', 'archives_id')->setEagerlyType(0)->joinType('LEFT');
    }

    public function questions()
    {
        return $this->hasMany('Question', 'test_id', 'id');
    }

    public function answers()
    {
        return $this->hasMany('Answer', 'test_id', 'id');
    }

    public function testContent()
    {
        return $this->hasMany('TestContent', 'test_id', 'id');
    }
}