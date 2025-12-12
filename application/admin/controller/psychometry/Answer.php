<?php

namespace app\admin\controller\psychometry;

use app\common\controller\Backend;
/**
 * 测试答案管理
 *
 * @icon fa fa-check-circle
 */
class Answer extends Backend
{
    /**
     * Answer模型对象
     * @var \app\admin\model\psychometry\Answer
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\psychometry\Answer;
    }

    public function index($test_id = 0)
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $where2 = [];
            if ($test_id > 0) {
                $where2['test_id'] = $test_id;
            }
            $list = $this->model
                ->with(['test'])
                ->where($where)
                ->where($where2)
                ->order($sort, $order)
                ->paginate($limit);
            $rows = $list->items();
            
            // 为每行数据添加多语言信息和统计
            foreach ($rows as $row) {
                // 获取多语言内容信息
                $multilangLangs = \think\Db::table('fa_psychometry_answer_content')
                    ->where('answer_id', $row['id'])
                    ->column('lang');
                $row['multilang'] = implode(',', $multilangLangs);
                
                // 添加统计信息
                $row['content_count'] = count($multilangLangs);
                
                // 获取答案标题（中文）
                try {
                    $answerTitle = \think\Db::table('fa_psychometry_answer_content')
                        ->where('answer_id', $row['id'])
                        ->where('lang', 'zh')
                        ->value('title');
                    $row['answer_title'] = $answerTitle ?: '未设置标题';
                } catch (\Exception $e) {
                    $row['answer_title'] = '未设置标题';
                }
                
                // 获取测试信息（从多语言内容表获取标题）
                try {
                    $testTitle = \think\Db::table('fa_psychometry_test_content')
                        ->where('test_id', $row['test_id'])
                        ->where('lang', 'zh')
                        ->value('title');
                    $row['test_title'] = $testTitle ?: '未知测试';
                } catch (\Exception $e) {
                    $row['test_title'] = '未知测试';
                }
                
                // 获取结果类型显示
                $resultTypeMap = [
                    'type' => '类型结果',
                    'score' => '分数结果', 
                    'dimension' => '维度结果',
                    'custom' => '自定义结果'
                ];
                $row['result_type_text'] = $resultTypeMap[$row['result_type']] ?? '未知类型';
                
                // 获取结果配置信息
                if ($row['result_config']) {
                    $config = is_string($row['result_config']) ? json_decode($row['result_config'], true) : $row['result_config'];
                    $row['result_config_text'] = $config ? json_encode($config, JSON_UNESCAPED_UNICODE) : '';
                } else {
                    $row['result_config_text'] = '';
                }
            }
            
            $result = array("total" => $list->total(), "rows" => $rows);
            return json($result);
        }

        $this->view->assign("test_id", $test_id);
        $this->view->assign("question_id", $test_id); // 为了兼容模板中的question_id变量
        return parent::index();
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
        $multilangContents = \think\Db::table('fa_psychometry_answer_content')
            ->where('answer_id', $ids)
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
        $answerId = $this->request->post('answer_id');
        $multilangData = $this->request->post('multilang/a');
        
        if (empty($answerId) || empty($multilangData)) {
            $this->error('参数错误');
        }
        
        try {
            foreach ($multilangData as $lang => $data) {
                if (empty($data['title'])) {
                    continue; // 跳过没有标题的语言版本
                }
                
                // 检查是否已存在
                $exists = \think\Db::table('fa_psychometry_answer_content')
                    ->where('answer_id', $answerId)
                    ->where('lang', $lang)
                    ->find();
                
                $contentData = [
                    'answer_id' => $answerId,
                    'lang' => $lang,
                    'title' => $data['title'],
                    'content' => $data['content'] ?? '',
                    'intro' => $data['intro'] ?? '',
                    'cover' => $data['cover'] ?? '',
                    'analysis' => $data['analysis'] ?? '',
                    'suggestion' => $data['suggestion'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($exists) {
                    // 更新
                    \think\Db::table('fa_psychometry_answer_content')
                        ->where('answer_id', $answerId)
                        ->where('lang', $lang)
                        ->update($contentData);
                } else {
                    // 新增
                    $contentData['created_at'] = date('Y-m-d H:i:s');
                    \think\Db::table('fa_psychometry_answer_content')->insert($contentData);
                }
            }
            
            $this->success('保存成功');
        } catch (\Exception $e) {
            $this->error('保存失败：' . $e->getMessage());
        }
    }

    public function add($test_id = 0)
    {
        $this->view->assign("test_id", $test_id);
        $this->view->assign("question_id", $test_id); // 为了兼容模板中的question_id变量
        
        // 如果指定了测试ID，获取测试类型
        if ($test_id > 0) {
            $test = \think\Db::table('fa_psychometry_test')->where('id', $test_id)->find();
            $testType = $test['test_type'] ?? 'score';
            $this->view->assign('test_type', $testType);
        }
        
        return parent::add();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        // 如果是POST请求，调用父类的edit方法进行保存
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $this->request->filter(['strip_tags', 'trim']);
                $this->token();
            }
            return parent::edit($ids);
        }
        
        // 处理JSON字段用于显示
        if (isset($row['result_config']) && is_string($row['result_config'])) {
            $decoded = json_decode($row['result_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['result_config'] = $decoded;
            }
        }
        
        // 调试：输出result_config结构
        // \think\Log::write('result_config: ' . json_encode($row['result_config'], JSON_UNESCAPED_UNICODE), 'debug');
        
        // 确保 result_config 是一个数组，并设置默认值
        $resultConfig = $row['result_config'] ?? [];
        if (!is_array($resultConfig)) {
            $resultConfig = [];
        }
        
        // 获取测试类型，以便推荐合适的匹配方式
        $test = \think\Db::table('fa_psychometry_test')->where('id', $row['test_id'])->find();
        $testType = $test['test_type'] ?? 'score';
        
        // 根据测试类型设置默认的 match_type
        if (!isset($resultConfig['match_type'])) {
            switch ($testType) {
                case 'mbti':
                    $resultConfig['match_type'] = 'dimension';
                    break;
                case 'score':
                case 'custom':
                    $resultConfig['match_type'] = 'score';
                    break;
                case 'dimension':
                    $resultConfig['match_type'] = 'dimension';
                    break;
                case 'nine_type':
                case 'multiple_type':
                    $resultConfig['match_type'] = 'option';
                    break;
                default:
                    $resultConfig['match_type'] = 'score';
            }
        }
        
        // 将修改后的配置赋值回 row
        $row['result_config'] = $resultConfig;
        
        // 传递给视图
        $this->view->assign('test_type', $testType);
        
        if (isset($row['conditions']) && is_string($row['conditions'])) {
            $decoded = json_decode($row['conditions'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['conditions'] = $decoded;
            }
        }
        
        // 确保 conditions 是一个数组
        if (!isset($row['conditions']) || !is_array($row['conditions'])) {
            $row['conditions'] = [];
        }
        
        // 获取答案标题（中文）
        $answerTitle = \think\Db::table('fa_psychometry_answer_content')
            ->where('answer_id', $row['id'])
            ->where('lang', 'zh')
            ->value('title');
        $row['answer_title'] = $answerTitle ?: '未设置标题';
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
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
                    ->join('fa_psychometry_answer ans', 'ans.test_id = t.id')
                    ->where('ans.id', 'in', $ids)
                    ->where('ac.lang', 'zh')
                    ->column('ac.title', 'ans.id');
                
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