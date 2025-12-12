<?php

namespace app\admin\controller\cms;

use app\common\controller\Backend;
use addons\cms\model\ArchivesContent;

/**
 * 文章多语言内容管理
 *
 * @icon fa fa-language
 */
class ArchivesContent extends Backend
{
    /**
     * ArchivesContent模型对象
     * @var \addons\cms\model\ArchivesContent
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \addons\cms\model\ArchivesContent;
        $this->view->assign("langList", $this->model->getLangList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['archives'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['archives'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id', 'archives_id', 'lang', 'title', 'content', 'sub_title', 'question', 'seo_title', 'keywords', 'description', 'created_at', 'updated_at']);
                $row->visible(['archives']);
                $row->getRelation('archives')->visible(['id', 'title']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 批量创建多语言版本
     */
    public function batchCreate()
    {
        if ($this->request->isPost()) {
            $archives_id = $this->request->post('archives_id');
            $langs = $this->request->post('langs/a');
            
            if (!$archives_id || !$langs) {
                $this->error('参数错误');
            }
            
            // 获取原文章信息
            $archives = \addons\cms\model\Archives::get($archives_id);
            if (!$archives) {
                $this->error('文章不存在');
            }
            
            $successCount = 0;
            foreach ($langs as $lang) {
                // 检查是否已存在该语言版本
                $exists = ArchivesContent::where('archives_id', $archives_id)
                    ->where('lang', $lang)
                    ->find();
                    
                if ($exists) {
                    continue; // 已存在，跳过
                }
                
                // 创建新语言版本
                $data = [
                    'archives_id' => $archives_id,
                    'lang' => $lang,
                    'title' => '[待翻译] ' . $archives->title,
                    'content' => $archives->content,
                    'sub_title' => '[待翻译] ' . $archives->sub_title,
                    'question' => '[待翻译] ' . $archives->question,
                    'seo_title' => '[待翻译] ' . $archives->seotitle,
                    'keywords' => $archives->keywords,
                    'description' => '[待翻译] ' . $archives->description,
                ];
                
                ArchivesContent::create($data);
                $successCount++;
            }
            
            $this->success("成功创建 {$successCount} 个语言版本");
        }
        
        return $this->view->fetch();
    }
}
