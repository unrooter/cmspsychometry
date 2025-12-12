<?php

namespace app\admin\model\psychometry;

use think\Model;
use traits\model\SoftDelete;

class Analyse extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'psychometry_analyse';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];
    

    







}
