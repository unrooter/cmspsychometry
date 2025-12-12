<?php

namespace app\admin\controller\psychometry;

use app\common\controller\Backend;

/**
 * 用户答题记录
 *
 * @icon fa fa-user-check
 */
class UserQuestion extends Backend
{

    /**
     * UserQuestion模型对象
     * @var \app\admin\model\psychometry\UserQuestion
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\psychometry\UserQuestion;

    }
    public function index($question_id = 0)
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $where2 = [];
            if($question_id > 0){
                $where2['question_id'] = $question_id;
            }
            $list = $this->model
                ->with(['question','answer','user'])
                ->where($where)
                ->where($where2)
                ->order($sort, $order)
                ->paginate($limit);
            $rows = $list->items();
            $result = array("total" => $list->total(), "rows" => $rows);
            return json($result);
        }
        $this->view->assign("question_id", $question_id);
        return parent::index();
    }

}
