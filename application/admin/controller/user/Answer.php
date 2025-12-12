<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 心理测试题
 *
 * @icon fa fa-circle-o
 */
class Answer extends Backend
{

    /**
     * Answer模型对象
     * @var \app\admin\model\user\Answer
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Answer;

    }

}
