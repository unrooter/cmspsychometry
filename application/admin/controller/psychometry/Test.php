<?php

namespace app\admin\controller\psychometry;

use app\common\controller\Backend;

/**
 * 心理测试管理
 *
 * @icon fa fa-heart
 */
class Test extends Backend
{
    /**
     * Test模型对象
     * @var \app\admin\model\psychometry\Test
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\psychometry\Test;
        $this->view->assign("testTypeList", $this->model->getTestTypeList());
        $this->view->assign("showTypeList", $this->model->getShowTypeList());
    }

    public function index()
    {
        $this->dataLimit = false;
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
                // 处理JSON字段
                if (isset($row['test_config'])) {
                    $row['test_config'] = json_decode($row['test_config'], true);
                }
                if (isset($row['scoring_rules'])) {
                    $row['scoring_rules'] = json_decode($row['scoring_rules'], true);
                }
                if (isset($row['result_rules'])) {
                    $row['result_rules'] = json_decode($row['result_rules'], true);
                }
                
                // 添加题目数量统计
                $questionCount = \think\Db::table('fa_psychometry_question')
                    ->where('test_id', $row['id'])
                    ->where('status', 1)
                    ->count();
                $row['question_count'] = $questionCount;
                
                // 添加解析数量统计
                $answerCount = \think\Db::table('fa_psychometry_answer')
                    ->where('test_id', $row['id'])
                    ->where('status', 1)
                    ->count();
                $row['answer_count'] = $answerCount;
                
                // 添加多语言信息
                $multilangLangs = \think\Db::table('fa_psychometry_test_content')
                    ->where('test_id', $row['id'])
                    ->column('lang');
                $row['multilang'] = implode(',', $multilangLangs);
                
                // 添加中文测试标题
                $chineseTitle = \think\Db::table('fa_psychometry_test_content')
                    ->where('test_id', $row['id'])
                    ->where('lang', 'zh')
                    ->value('title');
                
                if ($chineseTitle) {
                    $row['test_title_zh'] = $chineseTitle;
                } else {
                    $row['test_title_zh'] = '暂无中文标题';
                }
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                
                // 处理JSON字段
                if (isset($params['test_config']) && is_array($params['test_config'])) {
                    $params['test_config'] = json_encode($params['test_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['scoring_rules']) && is_array($params['scoring_rules'])) {
                    $params['scoring_rules'] = json_encode($params['scoring_rules'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['result_rules']) && is_array($params['result_rules'])) {
                    $params['result_rules'] = json_encode($params['result_rules'], JSON_UNESCAPED_UNICODE);
                }

                $result = false;
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                } catch (ValidateException $e) {
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    $this->error($e->getMessage());
                } catch (Exception $e) {
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
                
                // 处理JSON字段
                if (isset($params['test_config']) && is_array($params['test_config'])) {
                    $params['test_config'] = json_encode($params['test_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['scoring_rules']) && is_array($params['scoring_rules'])) {
                    $params['scoring_rules'] = json_encode($params['scoring_rules'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['result_rules']) && is_array($params['result_rules'])) {
                    $params['result_rules'] = json_encode($params['result_rules'], JSON_UNESCAPED_UNICODE);
                }

                $result = false;
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                } catch (ValidateException $e) {
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    $this->error($e->getMessage());
                } catch (Exception $e) {
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

        // 处理JSON字段用于显示 - 保持原始JSON字符串格式用于编辑
        if (isset($row['test_config']) && is_string($row['test_config'])) {
            // 如果是字符串，尝试解码为数组用于显示
            $decoded = json_decode($row['test_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['test_config'] = $decoded;
            }
        }
        if (isset($row['scoring_rules']) && is_string($row['scoring_rules'])) {
            $decoded = json_decode($row['scoring_rules'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['scoring_rules'] = $decoded;
            }
        }
        if (isset($row['result_rules']) && is_string($row['result_rules'])) {
            $decoded = json_decode($row['result_rules'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['result_rules'] = $decoded;
            }
        }

        // 设置默认值
        if (!isset($row['test_config']) || !is_array($row['test_config'])) {
            $row['test_config'] = [];
        }
        $row['test_config'] = array_merge([
            'duration' => 0,
            'question_timeout' => 0,
            'allow_back' => 1,
            'random_questions' => 0,
            'show_progress' => 1,
            'instructions' => ''
        ], $row['test_config']);

        if (!isset($row['scoring_rules']) || !is_array($row['scoring_rules'])) {
            $row['scoring_rules'] = [];
        }
        $row['scoring_rules'] = array_merge([
            'method' => 'sum',
            'min_score' => 0,
            'max_score' => 100,
            'pass_score' => 60,
            'show_score' => 1
        ], $row['scoring_rules']);

        if (!isset($row['result_rules']) || !is_array($row['result_rules'])) {
            $row['result_rules'] = [];
        }
        $row['result_rules'] = array_merge([
            'type' => 'type',
            'title' => '',
            'description' => '',
            'show_suggestions' => 1,
            'allow_share' => 1
        ], $row['result_rules']);

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 多语言管理
     */
    public function multilang()
    {
        $ids = $this->request->param('ids');
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        // 获取多语言内容
        $multilangContents = \think\Db::table('fa_psychometry_test_content')
            ->where('test_id', $ids)
            ->select();
        
        $multilangData = [];
        foreach ($multilangContents as $content) {
            $multilangData[$content['lang']] = $content;
        }
        
        // 可用语言列表
        $availableLangs = [
            ['key' => 'zh', 'name' => '中文'],
            ['key' => 'en', 'name' => 'English']
        ];
        
        $this->view->assign('row', $row);
        $this->view->assign('multilangContents', $multilangData);
        $this->view->assign('availableLangs', $availableLangs);
        
        return $this->view->fetch();
    }

    /**
     * 保存多语言内容
     */
    public function multilang_save()
    {
        $testId = $this->request->post('test_id');
        $multilangData = $this->request->post('multilang/a');
        
        if (empty($testId) || empty($multilangData)) {
            $this->error('参数错误');
        }
        
        try {
            foreach ($multilangData as $lang => $data) {
                if (empty($data['title'])) {
                    continue; // 跳过没有标题的语言版本
                }
                
                // 检查是否已存在
                $exists = \think\Db::table('fa_psychometry_test_content')
                    ->where('test_id', $testId)
                    ->where('lang', $lang)
                    ->find();
                
                $contentData = [
                    'test_id' => $testId,
                    'lang' => $lang,
                    'title' => $data['title'],
                    'sub_title' => $data['sub_title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'seo_title' => $data['seo_title'] ?? '',
                    'keywords' => $data['keywords'] ?? '',
                    'seo_description' => $data['seo_description'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($exists) {
                    // 更新
                    \think\Db::table('fa_psychometry_test_content')
                        ->where('test_id', $testId)
                        ->where('lang', $lang)
                        ->update($contentData);
                } else {
                    // 新增
                    $contentData['created_at'] = date('Y-m-d H:i:s');
                    \think\Db::table('fa_psychometry_test_content')->insert($contentData);
                }
            }
            
            $this->success('保存成功');
        } catch (\Exception $e) {
            $this->error('保存失败：' . $e->getMessage());
        }
    }
    
    public function selectpage()
    {
        // 先调用父类的selectpage方法获取基础数据
        $response = parent::selectpage();
        
        // 获取当前请求的数据
        $result = $response->getData();
        
        // 如果有数据，需要重新处理标题字段，关联archives_content表获取中文标题
        if (!empty($result['list'])) {
            $ids = array_column($result['list'], 'id');
            if (!empty($ids)) {
                // 查询archives_content表获取中文标题
                $titles = \think\Db::name('cms_archives_content')
                    ->alias('ac')
                    ->join('fa_cms_archives a', 'a.id = ac.archives_id')
                    ->join('fa_psychometry_test t', 't.archives_id = a.id')
                    ->where('t.id', 'in', $ids)
                    ->where('ac.lang', 'zh')
                    ->column('ac.title', 't.id');
                
                // 更新结果中的标题
                foreach ($result['list'] as &$item) {
                    if (isset($titles[$item['id']])) {
                        $item['archives_title'] = $titles[$item['id']];
                        $item['title'] = $titles[$item['id']]; // 兼容性
                    }
                }
            }
        }
        
        // 重新设置响应数据
        $response->data($result);
        
        return $response;
    }
}