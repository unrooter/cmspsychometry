<?php

namespace app\admin\model\psychometry;

use think\Model;


class UserQuestion extends Model
{

    // 表名
    protected $name = 'psychometry_user_question';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'create_time_text'
    ];

    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function answer()
    {
        return $this->belongsTo('Answer', 'answer_id', 'id');
    }

    public function question()
    {
        return $this->belongsTo('Question', 'question_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id');
    }
}
