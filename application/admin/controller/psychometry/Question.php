<?php

namespace app\admin\controller\psychometry;

use app\common\controller\Backend;

/**
 * 测试题目管理
 *
 * @icon fa fa-question-circle
 */
class Question extends Backend
{
    /**
     * Question模型对象
     * @var \app\admin\model\psychometry\Question
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\psychometry\Question;
        $this->view->assign("questionTypeList", $this->model->getQuestionTypeList());
    }

    public function index($test_id = 0)
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
            $where2 = [];
            if ($test_id > 0) {
                $where2['test_id'] = $test_id;
            }
            
            $whereConditions = [];
            $bindParams = [];
            
            if ($test_id > 0) {
                $whereConditions[] = "q.test_id = :test_id";
                $bindParams['test_id'] = $test_id;
            }
            
            // 处理搜索条件
            if (is_array($where)) {
                if (isset($where['question'])) {
                    $whereConditions[] = "qc.question_text LIKE :question_text";
                    $bindParams['question_text'] = '%' . $where['question'] . '%';
                    unset($where['question']);
                }
                
                if (isset($where['test_title'])) {
                    $whereConditions[] = "tc.title LIKE :test_title";
                    $bindParams['test_title'] = '%' . $where['test_title'] . '%';
                    unset($where['test_title']);
                }
                
                foreach ($where as $key => $value) {
                    if (is_array($value)) {
                        $whereConditions[] = "q.{$key} IN (:" . $key . ")";
                        $bindParams[$key] = $value;
                    } else {
                        $whereConditions[] = "q.{$key} = :{$key}";
                        $bindParams[$key] = $value;
                    }
                }
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $allowedSortFields = ['id', 'test_id', 'question_type', 'sort_order', 'status', 'created_at', 'updated_at'];
            if (!in_array($sort, $allowedSortFields)) {
                $sort = 'id'; // 默认排序字段
            }
            
            $order = strtoupper($order);
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'DESC';
            }
            
            $sql = "SELECT q.*, qc.question_text, qc.question_media, qc.question_hint, tc.title as test_title
                    FROM fa_psychometry_question q 
                    LEFT JOIN fa_psychometry_question_content qc ON qc.question_id = q.id AND qc.lang = 'zh'
                    LEFT JOIN fa_psychometry_test_content tc ON tc.test_id = q.test_id AND tc.lang = 'zh'
                    {$whereClause}
                    ORDER BY q.{$sort} {$order}
                    LIMIT {$offset}, {$limit}";
            
            $countSql = "SELECT COUNT(*) as total 
                         FROM fa_psychometry_question q 
                         LEFT JOIN fa_psychometry_question_content qc ON qc.question_id = q.id AND qc.lang = 'zh'
                         {$whereClause}";
            
            // 执行查询
            $total = \think\Db::query($countSql, $bindParams)[0]['total'];
            $list = \think\Db::query($sql, $bindParams);

            foreach ($list as &$row) {
                // 处理JSON字段
                if (isset($row['question_config'])) {
                    $row['question_config'] = json_decode($row['question_config'], true);
                }
                if (isset($row['options_config'])) {
                    $row['options_config'] = json_decode($row['options_config'], true);
                }
                if (isset($row['scoring_rules'])) {
                    $row['scoring_rules'] = json_decode($row['scoring_rules'], true);
                }
                
                // 添加多语言信息
                $multilangLangs = \think\Db::table('fa_psychometry_question_content')
                    ->where('question_id', $row['id'])
                    ->column('lang');
                
                $row['multilang'] = implode(',', $multilangLangs);
                $row['content_count'] = count($multilangLangs);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        $this->view->assign("test_id", $test_id);
        return $this->view->fetch();
    }
    
    /**
     * 获取题目内容（多语言）
     */
    public function get_question_content()
    {
        $questionId = $this->request->param('question_id');
        if (empty($questionId)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        try {
            $contents = \think\Db::table('fa_psychometry_question_content')
                ->where('question_id', $questionId)
                ->select();
            
            $contentsByLang = [];
            foreach ($contents as $content) {
                $contentsByLang[$content['lang']] = $content;
            }
            
            return json(['code' => 1, 'msg' => '获取成功', 'data' => $contentsByLang]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '获取失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 保存题目内容（多语言）
     */
    public function save_question_content()
    {
        $questionId = $this->request->post('question_id');
        $contents = $this->request->post('contents/a');
        
        if (empty($questionId) || empty($contents)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        try {
            foreach ($contents as $lang => $data) {
                if (empty($data['question_text'])) {
                    continue;
                }
                
                $exists = \think\Db::table('fa_psychometry_question_content')
                    ->where('question_id', $questionId)
                    ->where('lang', $lang)
                    ->find();
                
                $contentData = [
                    'question_id' => $questionId,
                    'lang' => $lang,
                    'question_text' => $data['question_text'],
                    'question_media' => $data['question_media'] ?? '',
                    'question_hint' => $data['question_hint'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($exists) {
                    \think\Db::table('fa_psychometry_question_content')
                        ->where('question_id', $questionId)
                        ->where('lang', $lang)
                        ->update($contentData);
                } else {
                    $contentData['created_at'] = date('Y-m-d H:i:s');
                    \think\Db::table('fa_psychometry_question_content')->insert($contentData);
                }
            }
            
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取题目的选项数据
     */
    public function get_options()
    {
        $questionId = $this->request->param('question_id');
        if (empty($questionId)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        try {
            // 获取所有语言的选项
            $allOptions = \think\Db::table('fa_psychometry_option_content')
                ->where('question_id', $questionId)
                ->order('option_key', 'asc')
                ->select();
            
            // 获取评分规则
            $question = \think\Db::table('fa_psychometry_question')
                ->where('id', $questionId)
                ->find();
            
            $scoringRules = [];
            if ($question && !empty($question['scoring_rules'])) {
                $scoringRules = json_decode($question['scoring_rules'], true) ?: [];
            }
            
            // 按语言分组
            $optionsByLang = [];
            foreach ($allOptions as $option) {
                $lang = $option['lang'];
                if (!isset($optionsByLang[$lang])) {
                    $optionsByLang[$lang] = [];
                }
                
                // 查找对应的评分规则
                $targetDimension = '';
                $score = 1; // 默认分值
                
                foreach ($scoringRules as $rule) {
                    if (isset($rule['condition']) && strpos($rule['condition'], 'option_key = "' . $option['option_key'] . '"') !== false) {
                        $targetDimension = $rule['target'] ?? '';
                        $score = $rule['value'] ?? 1;
                        break;
                    }
                }
                
                $option['score'] = $score;
                $option['target_dimension'] = $targetDimension;
                
                $optionsByLang[$lang][] = $option;
            }
            
            return json(['code' => 1, 'msg' => '获取成功', 'data' => $optionsByLang]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '获取失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 保存题目的选项数据
     */
    public function save_options()
    {
        $questionId = $this->request->param('question_id');
        $options = $this->request->param('options');
        
        if (empty($questionId) || empty($options)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        try {
            // 删除现有选项
            \think\Db::table('fa_psychometry_option_content')
                ->where('question_id', $questionId)
                ->delete();
            
            // 保存新选项
            $insertData = [];
            foreach ($options as $option) {
                if (!empty($option['text']) && !empty($option['lang'])) {
                    $insertData[] = [
                        'question_id' => $questionId,
                        'option_key' => $option['key'],
                        'lang' => $option['lang'],
                        'option_text' => $option['text'],
                        'option_description' => $option['description'] ?? '',
                        'option_media' => $option['media'] ?? '',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            if (!empty($insertData)) {
                \think\Db::table('fa_psychometry_option_content')->insertAll($insertData);
            }
            
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 保存题目选项数据（内部方法）
     */
    private function saveQuestionOptions($questionId, $params)
    {
        try {
            // 删除现有选项
            \think\Db::table('fa_psychometry_option_content')
                ->where('question_id', $questionId)
                ->delete();
            
            // 从options_config中解析选项数据
            if (isset($params['options_config']) && !empty($params['options_config'])) {
                $optionsConfig = json_decode($params['options_config'], true);
                if (is_array($optionsConfig)) {
                    $insertData = [];
                    foreach ($optionsConfig as $option) {
                        if (!empty($option['value']) && !empty($option['lang'])) {
                            $insertData[] = [
                                'question_id' => $questionId,
                                'option_key' => $option['key'],
                                'lang' => $option['lang'],
                                'option_text' => $option['value'],
                                'option_description' => '',
                                'option_media' => '',
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                    
                    if (!empty($insertData)) {
                        \think\Db::table('fa_psychometry_option_content')->insertAll($insertData);
                    }
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不中断主流程
            \think\Log::error('保存题目选项失败：' . $e->getMessage());
        }
    }

    public function add($test_id = 0)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                
                // 处理JSON字段
                if (isset($params['question_config']) && is_array($params['question_config'])) {
                    $params['question_config'] = json_encode($params['question_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['options_config']) && is_array($params['options_config'])) {
                    $params['options_config'] = json_encode($params['options_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['scoring_rules']) && is_array($params['scoring_rules'])) {
                    $params['scoring_rules'] = json_encode($params['scoring_rules'], JSON_UNESCAPED_UNICODE);
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
                    
                    // 保存选项数据
                    if ($result !== false) {
                        $this->saveQuestionOptions($this->model->id, $params);
                    }
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
        $this->view->assign("test_id", $test_id);
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
                if (isset($params['question_config']) && is_array($params['question_config'])) {
                    $params['question_config'] = json_encode($params['question_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['options_config']) && is_array($params['options_config'])) {
                    $params['options_config'] = json_encode($params['options_config'], JSON_UNESCAPED_UNICODE);
                }
                if (isset($params['scoring_rules']) && is_array($params['scoring_rules'])) {
                    $params['scoring_rules'] = json_encode($params['scoring_rules'], JSON_UNESCAPED_UNICODE);
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
                    
                    // 保存选项数据
                    if ($result !== false) {
                        $this->saveQuestionOptions($ids, $params);
                    }
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

        // 处理JSON字段用于显示
        if (isset($row['question_config'])) {
            $row['question_config'] = json_decode($row['question_config'], true);
        }
        if (isset($row['options_config'])) {
            $row['options_config'] = json_decode($row['options_config'], true);
        }
        if (isset($row['scoring_rules'])) {
            $row['scoring_rules'] = json_decode($row['scoring_rules'], true);
        }

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    
    /**
     * 多语言管理页面
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
        $multilangContents = \think\Db::table('fa_psychometry_question_content')
            ->where('question_id', $ids)
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
        $questionId = $this->request->post('question_id');
        $multilangData = $this->request->post('multilang/a');
        
        if (empty($questionId) || empty($multilangData)) {
            $this->error('参数错误');
        }
        
        try {
            foreach ($multilangData as $lang => $data) {
                if (empty($data['question_text'])) {
                    continue; // 跳过没有题目内容的语言版本
                }
                
                // 检查是否已存在
                $exists = \think\Db::table('fa_psychometry_question_content')
                    ->where('question_id', $questionId)
                    ->where('lang', $lang)
                    ->find();
                
                $contentData = [
                    'question_id' => $questionId,
                    'lang' => $lang,
                    'question_text' => $data['question_text'],
                    'question_media' => $data['question_media'] ?? '',
                    'question_hint' => $data['question_hint'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($exists) {
                    // 更新
                    \think\Db::table('fa_psychometry_question_content')
                        ->where('question_id', $questionId)
                        ->where('lang', $lang)
                        ->update($contentData);
                } else {
                    // 新增
                    $contentData['created_at'] = date('Y-m-d H:i:s');
                    \think\Db::table('fa_psychometry_question_content')->insert($contentData);
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
        
        // 如果有数据，需要重新处理标题字段，关联test和archives表获取中文标题
        if (!empty($result['list'])) {
            $ids = array_column($result['list'], 'id');
            if (!empty($ids)) {
                // 查询archives_content表获取中文标题
                $titles = \think\Db::name('cms_archives_content')
                    ->alias('ac')
                    ->join('fa_cms_archives a', 'a.id = ac.archives_id')
                    ->join('fa_psychometry_test t', 't.archives_id = a.id')
                    ->join('fa_psychometry_question q', 'q.test_id = t.id')
                    ->where('q.id', 'in', $ids)
                    ->where('ac.lang', 'zh')
                    ->column('ac.title', 'q.id');
                
                // 更新结果中的标题
                foreach ($result['list'] as &$item) {
                    if (isset($titles[$item['id']])) {
                        $item['test_title'] = $titles[$item['id']];
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