<?php

namespace app\admin\model\psychometry;

use think\Model;

class Question extends Model
{
    // 表名
    protected $name = 'psychometry_question';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp';

    // 定义时间戳字段名
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'question_type_text'
    ];

    public function getQuestionTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['question_type']) ? $data['question_type'] : '');
        $list = $this->getQuestionTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getQuestionTypeList()
    {
        return [
            'single' => __('单选题'),
            'multiple' => __('多选题'),
            'text' => __('文本题'),
            'image' => __('图片题'),
            'video' => __('视频题'),
            'sort' => __('排序题'),
            'matrix' => __('矩阵题'),
            'slider' => __('滑块题')
        ];
    }

    public function test()
    {
        return $this->hasOne('Test', 'id', 'test_id')->setEagerlyType(0)->joinType('LEFT');
    }

    public function questionContent()
    {
        return $this->hasMany('QuestionContent', 'question_id', 'id');
    }

    public function optionContent()
    {
        return $this->hasMany('OptionContent', 'question_id', 'id');
    }
}