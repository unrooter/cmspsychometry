<?php

namespace app\admin\controller\cms;

use addons\cms\library\FulltextSearch;
use app\admin\model\cms\Channel;
use app\admin\model\cms\ChannelAdmin;
use app\admin\model\cms\Modelx;
use app\common\controller\Backend;
use app\common\model\User;
use fast\Tree;
use think\Db;
use think\db\Query;
use think\Hook;

/**
 * 内容表
 *
 * @icon fa fa-file-text-o
 */
class Archives extends Backend
{

    /**
     * Archives模型对象
     */
    protected $model = null;
    protected $noNeedRight = ['get_fields_html', 'check_element_available', 'suggestion', 'copy', 'special', 'tags', 'move', 'flag'];
    protected $channelIds = [];
    protected $isSuperAdmin = false;
    protected $searchFields = 'id,title';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\cms\Archives;
        $config = get_addon_config('cms');
        if ($config['archivesdatalimit'] != 'all') {
            $this->dataLimit = $config['archivesdatalimit'];
        }

        //复制/加入专题/修改标签均检测编辑权限
        if (in_array($this->request->action(), ['copy', 'special', 'tags', 'move', 'flag']) && !$this->auth->check('cms/archives/edit')) {
            Hook::listen('admin_nopermission', $this);
            $this->error(__('You have no permission'), '');
        }

        //是否超级管理员
        $this->isSuperAdmin = $this->auth->isSuperAdmin();
        $channelList = [];
        $disabledIds = [];
        $all = collection(Channel::order("weigh desc,id desc")->select())->toArray();

        //允许的栏目
        $this->channelIds = $this->isSuperAdmin || !$config['channelallocate'] ? Channel::column('id') : ChannelAdmin::getAdminChanneIds();
        $parentChannelIds = Channel::where('id', 'in', $this->channelIds)->column('parent_id');
        $parentChannelIds = array_unique($parentChannelIds);
        $parentChannelList = \think\Db::name('cms_channel')->where('id', 'in', $parentChannelIds)->where('parent_id', '<>', 0)->field('id,parent_id,name')->select();
        $tree = Tree::instance()->init($all, 'parent_id');
        foreach ($parentChannelList as $index => $channel) {
            $parentChannelIds = array_merge($parentChannelIds, $tree->getParentsIds($channel['parent_id'], true));
        }
        $this->channelIds = array_merge($parentChannelIds, $this->channelIds);
        foreach ($all as $k => $v) {
            $state = ['opened' => true];
            if ($v['type'] == 'link') {
                $disabledIds[] = $v['id'];
            }
            if ($v['type'] == 'link') {
                $state['checkbox_disabled'] = true;
            }
            if (!$this->isSuperAdmin) {
                if (!in_array($v['id'], $parentChannelIds) && !in_array($v['id'], $this->channelIds)) {
                    unset($all[$k]);
                    continue;
                }
            }
            $channelList[] = [
                'id'     => $v['id'],
                'parent' => $v['parent_id'] ? $v['parent_id'] : '#',
                'text'   => __($v['name']),
                'type'   => $v['type'],
                'state'  => $state
            ];
        }
        $tree = Tree::instance()->init($all, 'parent_id');
        $channelOptions = $tree->getTree(0, "<option model='@model_id' value=@id @selected @disabled>@spacer@name</option>", '', $disabledIds);
        $secondChannelOptions = $tree->getTree(0, "<option model='@model_id' value=@id disabled>@spacer@name</option>", '', $disabledIds);
        $this->view->assign('channelOptions', $channelOptions);
        $this->view->assign('secondChannelOptions', $secondChannelOptions);
        $this->assignconfig('channelList', $channelList);
        $this->assignconfig('spiderRecord', intval($config['spiderrecord'] ?? 0));

        $this->assignconfig("flagList", $this->model->getFlagList());
        $this->view->assign("flagList", $this->model->getFlagList());
        $this->view->assign("statusList", $this->model->getStatusList());

        $this->assignconfig('cms', ['archiveseditmode' => $config['archiveseditmode']]);
    }
    public function cj($work_id=0)
    {
        $has_info = Db::table("fa_cms_work_log")->where(['work_id'=>$work_id])->find();
        if($has_info){
            $this->error('已采集或采集中');
        }
        $add_data['work_id'] = $work_id;
        $add_data['create_time'] = time();
        $res = Db::table("fa_cms_work_log")->insert($add_data);
        $this->success('操作成功');
    }
    /**
     * 查看文章详情（包括翻译、测试、题目等完整信息）
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // 可用语言列表
        $availableLangs = ['zh' => '中文', 'en' => 'English'];
        
        // 1. 获取文章多语言内容
        $multilangContents = \addons\cms\model\ArchivesContent::where('archives_id', $row['id'])
            ->select();
        
        $multilangData = [];
        $archivesLangStats = [];
        foreach ($availableLangs as $lang => $langName) {
            $content = null;
            foreach ($multilangContents as $item) {
                if ($item['lang'] == $lang) {
                    $content = $item->toArray();
                    break;
                }
            }
            $multilangData[$lang] = $content ?: [];
            $archivesLangStats[$lang] = !empty($content) && !empty($content['title']);
        }
        
        // 2. 获取关联的测试信息
        $testInfo = \think\Db::name('psychometry_test')
            ->where('archives_id', $row['id'])
            ->find();
        
        $testDetails = [];
        $statistics = [
            'test_exists' => false,
            'question_count' => 0,
            'option_count' => 0,
            'answer_count' => 0,
            'test_lang_stats' => [],
            'question_lang_stats' => [],
            'answer_lang_stats' => []
        ];
        
        if ($testInfo) {
            $statistics['test_exists'] = true;
            
            // 2.1 获取测试的多语言内容
            $testContentsRaw = \think\Db::table('fa_psychometry_test_content')
                ->where('test_id', $testInfo['id'])
                ->select();
            
            $testMultilangData = [];
            foreach ($availableLangs as $lang => $langName) {
                $content = null;
                foreach ($testContentsRaw as $item) {
                    $itemArray = (array)$item;
                    if ($itemArray['lang'] == $lang) {
                        $content = $itemArray;
                        break;
                    }
                }
                $testMultilangData[$lang] = $content ?: [];
                $statistics['test_lang_stats'][$lang] = !empty($content) && !empty($content['title']);
            }
            
            // 2.2 获取题目及其多语言内容
            $questionsRaw = \think\Db::name('psychometry_question')
                ->where('test_id', $testInfo['id'])
                ->order('sort_order', 'asc')
                ->select();
            
            $questions = [];
            foreach ($questionsRaw as $question) {
                $questionArray = (array)$question;
                $statistics['question_count']++;
                
                // 获取题目多语言内容
                $questionContentsRaw = \think\Db::table('fa_psychometry_question_content')
                    ->where('question_id', $questionArray['id'])
                    ->select();
                
                $qMultilangData = [];
                $qLangStats = [];
                foreach ($availableLangs as $lang => $langName) {
                    $content = null;
                    foreach ($questionContentsRaw as $item) {
                        $itemArray = (array)$item;
                        if ($itemArray['lang'] == $lang) {
                            $content = $itemArray;
                            break;
                        }
                    }
                    $qMultilangData[$lang] = $content ?: [];
                    $qLangStats[$lang] = !empty($content) && !empty($content['question_text']);
                }
                $questionArray['multilang'] = $qMultilangData;
                $questionArray['lang_stats'] = $qLangStats;
                
                // 获取选项多语言内容（选项直接存储在option_content表中）
                $optionContentsRaw = \think\Db::table('fa_psychometry_option_content')
                    ->where('question_id', $questionArray['id'])
                    ->select();
                
                // 按 option_key 分组选项
                $optionsByKey = [];
                foreach ($optionContentsRaw as $item) {
                    $itemArray = (array)$item;
                    $optionKey = $itemArray['option_key'];
                    if (!isset($optionsByKey[$optionKey])) {
                        $optionsByKey[$optionKey] = [
                            'option_key' => $optionKey,
                            'question_id' => $itemArray['question_id'],
                            'multilang' => []
                        ];
                    }
                    $optionsByKey[$optionKey]['multilang'][$itemArray['lang']] = [
                        'content' => $itemArray['option_text'],
                        'intro' => $itemArray['option_description'] ?? '',
                        'media' => $itemArray['option_media'] ?? ''
                    ];
                }
                
                // 转换为数组并统计
                $options = [];
                foreach ($optionsByKey as $optionKey => $optionData) {
                    $statistics['option_count']++;
                    // 为每种语言补充空数据
                    foreach ($availableLangs as $lang => $langName) {
                        if (!isset($optionData['multilang'][$lang])) {
                            $optionData['multilang'][$lang] = [];
                        }
                    }
                    $options[] = $optionData;
                }
                $questionArray['options'] = $options;
                $questions[] = $questionArray;
            }
            
            // 2.3 获取答案及其多语言内容
            $answersRaw = \think\Db::table('fa_psychometry_answer')
                ->where('test_id', $testInfo['id'])
                ->order('sort_order', 'asc')
                ->select();
            
            $answers = [];
            foreach ($answersRaw as $answer) {
                $answerArray = (array)$answer;
                $statistics['answer_count']++;
                
                $answerContentsRaw = \think\Db::table('fa_psychometry_answer_content')
                    ->where('answer_id', $answerArray['id'])
                    ->select();
                
                $ansMultilangData = [];
                $ansLangStats = [];
                foreach ($availableLangs as $lang => $langName) {
                    $content = null;
                    foreach ($answerContentsRaw as $item) {
                        $itemArray = (array)$item;
                        if ($itemArray['lang'] == $lang) {
                            $content = $itemArray;
                            break;
                        }
                    }
                    $ansMultilangData[$lang] = $content ?: [];
                    $ansLangStats[$lang] = !empty($content) && !empty($content['title']);
                }
                $answerArray['multilang'] = $ansMultilangData;
                $answerArray['lang_stats'] = $ansLangStats;
                $answers[] = $answerArray;
            }
            
            $testDetails = [
                'test' => $testInfo,
                'multilang' => $testMultilangData,
                'questions' => $questions,
                'answers' => $answers
            ];
        }
        
        $this->view->assign('row', $row);
        $this->view->assign('multilangContents', $multilangData);
        $this->view->assign('testDetails', $testDetails);
        $this->view->assign('availableLangs', $availableLangs);
        $this->view->assign('statistics', $statistics);
        $this->view->assign('archivesLangStats', $archivesLangStats);
        
        return $this->view->fetch();
    }

    /**
     * 查看
     */
    public function index($rid=0,$type=0)
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $this->relationSearch = true;
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            if (!$this->auth->isSuperAdmin()) {
                $this->model->where('channel_id', 'in', $this->channelIds);
            }
            $where2 = [];
            if($rid > 0){
                if($type == 1){
                    $where2['work_id'] = $rid;
                }elseif($type == 2){
                    $where2['voice_id'] = $rid;
                }elseif($type == 3){
                    $where2['author_id'] = $rid;
                }
            }
            // 统计总数（不需要连表内容表，避免性能损耗）
            $total = $this->model
                ->with('Channel')
                ->where($where)
                ->where($where2)
                ->order($sort, $order)
                ->count();
            if (!$this->auth->isSuperAdmin()) {
                $this->model->where('channel_id', 'in', $this->channelIds);
            }
            // 列表联表中文标题
            $list = $this->model
                ->alias('archives')
                ->with(['Channel'])
                ->join('fa_cms_archives_content ac', 'ac.archives_id = archives.id AND ac.lang = "zh"', 'LEFT')
                ->where($where)
                ->where($where2)
                ->order($sort, $order)
                ->field("archives.*, COALESCE(NULLIF(ac.title,''), archives.title) AS title")
                ->limit($offset, $limit)
                ->select();
            
            // 获取多语言内容信息
            $archivesIds = [];
            foreach ($list as $item) {
                $archivesIds[] = $item['id'];
            }
            $multilangContents = [];
            if (!empty($archivesIds)) {
                $multilangContents = \addons\cms\model\ArchivesContent::where('archives_id', 'in', $archivesIds)
                    ->group('archives_id')
                    ->column('GROUP_CONCAT(lang) as langs', 'archives_id');
            }
            
            // 获取关联的测试题信息
            $testInfo = [];
            if (!empty($archivesIds)) {
                $testInfo = \think\Db::name('psychometry_test')
                    ->where('archives_id', 'in', $archivesIds)
                    ->column('id,test_type,question_count,status', 'archives_id');
            }
            
            // 为每个文章添加多语言信息和测试题信息
            foreach ($list as $item) {
                $item->multilang_langs = $multilangContents[$item['id']] ?? '';
                $item->multilang_count = count(explode(',', $multilangContents[$item['id']] ?? ''));
                
                // 添加关联的测试题信息
                if (isset($testInfo[$item['id']])) {
                    $test = $testInfo[$item['id']];
                    $item->test_id = $test['id'];
                    $item->test_type = $test['test_type'];
                    $item->question_count = $test['question_count'];
                    $item->test_status = $test['status'] ? '启用' : '禁用';
                } else {
                    $item->test_id = '';
                    $item->test_type = '';
                    $item->question_count = '';
                    $item->test_status = '';
                }
            }
            
            addtion($list, [
                [
                    'field'   => 'channel_ids',
                    'display' => 'channel_ids',
                    'model'   => Channel::class,
                ],
            ]);
            \app\admin\model\cms\SpiderLog::render($list, 'archives');
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }

        $modelList = \app\admin\model\cms\Modelx::all();
        $specialList = \app\admin\model\cms\Special::where('status', 'normal')->select();
        $this->view->assign('modelList', $modelList);
        $this->view->assign('specialList', $specialList);
        return $this->view->fetch();
    }

    /**
     * 副表内容
     */
    public function content($model_id = null)
    {
        $model = \app\admin\model\cms\Modelx::get($model_id);
        if (!$model) {
            $this->error('未找到对应模型');
        }
        $fieldsList = \app\admin\model\cms\Fields::where('source', 'model')->where('source_id', $model['id'])->where('type', '<>', 'text')->select();

        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $fields = [];
            foreach ($fieldsList as $index => $item) {
                $fields[] = "addon." . $item['name'];
            }
            $filter = $this->request->request('filter');
            $op = $this->request->request('op');
            if ($filter && $op) {
                $filterArr = json_decode($filter, true);
                $opArr = json_decode($op, true);
                foreach ($filterArr as $index => $item) {
                    if (in_array("addon." . $index, $fields)) {
                        $filterArr["addon." . $index] = $item;
                        $opArr["addon." . $index] = $opArr[$index];
                        unset($filterArr[$index], $opArr[$index]);
                    }
                }
                $this->request->get(['filter' => json_encode($filterArr), 'op' => json_encode($opArr)]);
            }

            $this->searchFields = "archives.id,archives.title";
            $this->relationSearch = true;
            $table = $this->model->getTable();
            list($where, $sort, $order, $offset, $limit, $page, $alias) = $this->buildparams();
            $sort = 'archives.id';
            $isSuperAdmin = $this->isSuperAdmin;
            $channelIds = $this->channelIds;
            $customWhere = function ($query) use ($isSuperAdmin, $channelIds, $model_id) {
                if (!$isSuperAdmin) {
                    $query->where('archives.channel_id', 'in', $channelIds);
                }
                if ($model_id) {
                    $query->where('archives.model_id', $model_id);
                }
            };

            $list = $this->model
                ->alias($alias)
                ->alias('archives')
                ->join('cms_channel channel', 'channel.id=archives.channel_id', 'LEFT')
                ->join($model['table'] . ' addon', 'addon.id=archives.id', 'LEFT')
                ->field('archives.*,channel.name as channel_name,addon.id as aid' . ($fields ? ',' . implode(',', $fields) : ''))
                ->where($customWhere)
                ->whereNull('deletetime')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        $fields = [];
        foreach ($fieldsList as $index => $item) {
            $fields[] = ['field' => $item['name'], 'title' => $item['title'], 'type' => $item['type'], 'content' => $item['content_list']];
        }
        $this->assignconfig('fields', $fields);
        $this->view->assign('fieldsList', $fieldsList);
        $this->view->assign('model', $model);
        $this->assignconfig('model_id', $model_id);
        $modelList = \app\admin\model\cms\Modelx::all();
        $this->view->assign('modelList', $modelList);
        return $this->view->fetch();
    }

    /**
     * 编辑
     *
     * @param mixed $ids
     * @return string
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
        if (!$this->isSuperAdmin && !in_array($row['channel_id'], $this->channelIds)) {
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {
            // 处理多语言内容保存
            $multilangData = $this->request->post('multilang/a', []);
            if (!empty($multilangData)) {
                foreach ($multilangData as $lang => $data) {
                    if (empty($data['title'])) continue; // 跳过空标题
                    
                    // 检查是否已存在
                    $exists = \addons\cms\model\ArchivesContent::where('archives_id', $row['id'])
                        ->where('lang', $lang)
                        ->find();
                    
                    if ($exists) {
                        // 只更新传递的字段，避免覆盖未传递的字段
                        $updateData = [];
                        if (isset($data['title'])) $updateData['title'] = $data['title'];
                        if (isset($data['content'])) $updateData['content'] = $data['content'];
                        if (isset($data['sub_title'])) $updateData['sub_title'] = $data['sub_title'];
                        if (isset($data['question'])) $updateData['question'] = $data['question'];
                        if (isset($data['seo_title'])) $updateData['seo_title'] = $data['seo_title'];
                        if (isset($data['keywords'])) $updateData['keywords'] = $data['keywords'];
                        if (isset($data['description'])) $updateData['description'] = $data['description'];
                        
                        if (!empty($updateData)) {
                            $updateData['updated_at'] = date('Y-m-d H:i:s');
                            $exists->save($updateData);
                        }
                    } else {
                        // 新建记录时，使用默认值
                        $contentData = [
                            'archives_id' => $row['id'],
                            'lang' => $lang,
                            'title' => $data['title'] ?? '',
                            'content' => $data['content'] ?? '',
                            'sub_title' => $data['sub_title'] ?? '',
                            'question' => $data['question'] ?? '',
                            'seo_title' => $data['seo_title'] ?? '',
                            'keywords' => $data['keywords'] ?? '',
                            'description' => $data['description'] ?? '',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                        \addons\cms\model\ArchivesContent::create($contentData);
                    }
                }
            }
            
            // 处理普通字段保存（从主表字段）
            $params = $this->request->post("row/a");
            if (is_array($params)) {
                // 从多语言数据同步到主表字段（用于显示）
                // 只同步title字段，其他多语言字段(content/sub_title/question等)只存在于fa_cms_archives_content表
                if (isset($multilangData['zh'])) {
                    $zhData = $multilangData['zh'];
                    if (isset($zhData['title'])) $params['title'] = $zhData['title'];
                }
                
                $row->save($params);
            }
            
            $this->success();
        }
        $channel = Channel::get($row['channel_id']);
        if (!$channel) {
            $this->error(__('No specified channel found'));
        }
        $model = \app\admin\model\cms\Modelx::get($channel['model_id']);
        if (!$model) {
            $this->error(__('No specified model found'));
        }
        $addon = db($model['table'])->where('id', $row['id'])->find();
        if ($addon) {
            $row->setData($addon);
        }

        // 获取多语言内容
        $multilangContents = \addons\cms\model\ArchivesContent::where('archives_id', $row['id'])
            ->order('lang')
            ->select();
        
        // 按语言分组
        $multilangData = [];
        foreach ($multilangContents as $content) {
            $multilangData[$content['lang']] = $content;
        }
        
        $this->view->assign('multilangContents', $multilangData);
        $this->view->assign('availableLangs', ['zh' => '中文', 'en' => 'English']);

        $disabledIds = [];
        $all = collection(Channel::order("weigh desc,id desc")->select())->toArray();
        foreach ($all as $k => $v) {
            if ($v['type'] == 'link' || $v['model_id'] != $channel['model_id']) {
                $disabledIds[] = $v['id'];
            }
        }
        $disabledIds = array_diff($disabledIds, [$row['channel_id']]);
        $tree = Tree::instance()->init($all, 'parent_id');
        $channelOptions = $tree->getTree(0, "<option model='@model_id' value=@id @selected @disabled>@spacer@name</option>", $row['channel_id'], $disabledIds);
        $secondChannelOptions = $tree->getTree(0, "<option model='@model_id' value=@id @selected @disabled>@spacer@name</option>", explode(',', $row['channel_ids']), $disabledIds);
        $this->view->assign('channelOptions', $channelOptions);
        $this->view->assign('secondChannelOptions', $secondChannelOptions);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 多语言内容管理
     */
    public function multilang($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        // 获取多语言内容
        $multilangContents = \addons\cms\model\ArchivesContent::where('archives_id', $row['id'])
            ->order('lang')
            ->select();
        
        // 调试：输出原始查询结果
        \think\Log::info('Raw multilang contents for article ' . $row['id'] . ': ' . json_encode($multilangContents));
        
        // 按语言分组
        $multilangData = [];
        foreach ($multilangContents as $content) {
            $multilangData[$content['lang']] = is_array($content) ? $content : $content->toArray();
        }
        
        // 调试：输出分组后的数据
        \think\Log::info('Grouped multilang data for article ' . $row['id'] . ': ' . json_encode($multilangData));
        
        // 重新组织数据，确保volist能正确获取键值
        $availableLangs = [];
        $availableLangs[] = ['key' => 'zh', 'name' => '中文'];
        $availableLangs[] = ['key' => 'en', 'name' => 'English'];
        
        $this->view->assign('row', $row);
        $this->view->assign('multilangContents', $multilangData);
        $this->view->assign('availableLangs', $availableLangs);
        
        // 调试：输出最终传递给模板的数据
        \think\Log::info('Final template data - multilangContents: ' . json_encode($multilangData));
        \think\Log::info('Final template data - availableLangs: ' . json_encode(['zh' => '中文', 'en' => 'English']));
        
        return $this->view->fetch();
    }

    /**
     * 删除
     * @param mixed $ids
     */
    public function del($ids = "")
    {
        parent::del($ids);
    }

    /**
     * 销毁
     * @param string $ids
     */
    public function destroy($ids = "")
    {
        \app\admin\model\cms\Archives::event('after_delete', function ($row) {
            //删除副表
            $channel = Channel::get($row->channel_id);
            if ($channel) {
                $model = Modelx::get($channel['model_id']);
                if ($model) {
                    db($model['table'])->where("id", $row['id'])->delete();
                }
            }
        });
        parent::destroy($ids);
    }

    /**
     * 还原
     * @param mixed $ids
     */
    public function restore($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->model->where($pk, 'in', $ids);
        }
        $config = get_addon_config('cms');
        $list = $this->model->onlyTrashed()->select();
        if ($list) {
            $ids = [];
            $refreshIds = [];
            foreach ($list as $index => $item) {
                if ($item['status'] == 'normal') {
                    User::score($config['score']['postarchives'], $item['user_id'], '发布文章');
                }
                $ids[] = $item['id'];
                $refreshIds = array_merge([$item['channel_id']], explode(',', $item['channel_ids']));
                $refreshIds = array_filter(array_unique($refreshIds));
            }
            $this->model->where('id', 'in', $ids);
            $this->model->restore('1=1');
            Channel::refreshItems($refreshIds);
            $this->success();
        }
        $this->error(__('No rows were updated'));
    }

    /**
     * 移动
     * @param string $ids
     */
    public function move($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        if ($ids) {
            if (!$this->request->isPost()) {
                $this->error(__("Invalid parameters"));
            }
            $channel_id = $this->request->post('channel_id');
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $this->model->where($pk, 'in', $ids);
            $channel = Channel::get($channel_id);
            if ($channel && $channel['type'] === 'list') {
                $channelNums = \app\admin\model\cms\Archives::
                with('channel')
                    ->where('archives.' . $pk, 'in', $ids)
                    ->where('channel_id', '<>', $channel['id'])
                    ->field('channel_id,COUNT(*) AS nums')
                    ->group('channel_id')
                    ->select();
                $result = $this->model
                    ->where('model_id', '=', $channel['model_id'])
                    ->where('channel_id', '<>', $channel['id'])
                    ->update(['channel_id' => $channel_id]);
                if ($result) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            } else {
                $this->error(__('No rows were updated'));
            }
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
    }

    /**
     * 复制选择行
     * @param string $ids
     */
    public function copy($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $archivesList = $this->model->where('id', 'in', $ids)->select();
            foreach ($archivesList as $index => $item) {
                try {
                    $model = Modelx::get($item['model_id']);
                    $addon = \think\Db::name($model['table'])->find($item['id']);
                    $data = $item->toArray();
                    $data = array_merge($data, $addon ?? []);
                    $data['title'] = $data['title'] . "_copy";
                    $data['status'] = 'hidden';
                    unset($data['id']);
                    \app\admin\model\cms\Archives::create($data, true);
                } catch (\Exception $e) {
                    //
                }
            }
            $this->success();
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
    }

    /**
     * 加入专题
     * @param string $ids
     */
    public function special($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        if ($ids) {
            $special_id = $this->request->post('special_id');
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $special = \app\admin\model\cms\Special::get($special_id);
            if ($special) {
                $archivesList = $this->model->where($pk, 'in', $ids)->select();
                foreach ($archivesList as $index => $item) {
                    $special_ids = explode(',', $item['special_ids']);
                    if (!in_array($special['id'], $special_ids)) {
                        $special_ids[] = $special['id'];
                        $item->save(['special_ids' => implode(',', array_unique(array_filter($special_ids)))]);
                    }
                }
                $this->success();
            } else {
                $this->error(__('No rows were updated'));
            }
        }
        $this->error(__('Please select at least one row'));
    }

    /**
     * 加入标签
     * @param string $ids
     */
    public function tags($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        if ($ids) {
            $tags = $this->request->post('tags');
            $newTagsArr = array_filter(explode(',', $tags));
            if ($newTagsArr) {
                $pk = $this->model->getPk();
                $adminIds = $this->getDataLimitAdminIds();
                if (is_array($adminIds)) {
                    $this->model->where($this->dataLimitField, 'in', $adminIds);
                }
                $archivesList = $this->model->where($pk, 'in', $ids)->select();
                foreach ($archivesList as $index => $item) {
                    $tagsArr = explode(',', $item['tags']);
                    $tagsArr = array_merge($tagsArr, $newTagsArr);
                    $item->save(['tags' => implode(',', array_unique(array_filter($tagsArr)))]);
                }
                $this->success();
            } else {
                $this->error(__('标签数据不能为空'));
            }
        }
        $this->error(__('Please select at least one row'));
    }

    /**
     * 修改标志
     * @param string $ids
     */
    public function flag($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        if ($ids) {
            $type = $this->request->post('type');
            $flag = $this->request->post('flag');
            $changeFlagArr = array_filter(explode(',', $flag));
            if ($changeFlagArr) {
                $pk = $this->model->getPk();
                $adminIds = $this->getDataLimitAdminIds();
                if (is_array($adminIds)) {
                    $this->model->where($this->dataLimitField, 'in', $adminIds);
                }
                $archivesList = $this->model->where($pk, 'in', $ids)->select();
                foreach ($archivesList as $index => $item) {
                    $flagArr = explode(',', $item['flag']);
                    if ($type == 'add') {
                        $flagArr = array_merge($flagArr, $changeFlagArr);
                    } else {
                        $flagArr = array_diff($flagArr, $changeFlagArr);
                    }
                    $item->save(['flag' => implode(',', array_unique(array_filter($flagArr)))]);
                }
                $this->success();
            } else {
                $this->error(__('标志数据不能为空'));
            }
        }
        $this->error(__('Please select at least one row'));
    }

    /**
     * 获取栏目列表
     * @internal
     */
    public function get_fields_html()
    {
        $this->view->engine->layout(false);
        $channel_id = $this->request->post('channel_id');
        $archives_id = $this->request->post('archives_id');
        $channel = Channel::get($channel_id, 'model');
        if ($channel) {
            $model_id = $channel['model_id'];
            $values = [];
            if ($archives_id) {
                $values = db($channel['model']['table'])->where('id', $archives_id)->find();

                //优先从栏目获取模型ID，再从文档获取
                $archives = \app\admin\model\cms\Archives::get($archives_id);
                $model_id = $archives ? $archives['model_id'] : $model_id;
            }

            $fields = \addons\cms\library\Service::getCustomFields('model', $model_id, $values);

            $model = Modelx::get($model_id);

            $setting = $model['setting'];
            $publishfields = isset($setting['publishfields']) ? $setting['publishfields'] : [];
            $titlelist = isset($setting['titlelist']) ? $setting['titlelist'] : [];

            $this->view->assign('channel', $channel);
            $this->view->assign('fields', $fields);
            $this->view->assign('values', $values);
            $this->success('', null, ['html' => $this->view->fetch('cms/common/fields'), 'publishfields' => $publishfields, 'titlelist' => $titlelist]);
        } else {
            $this->error(__('Please select channel'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 检测元素是否可用
     * @internal
     */
    public function check_element_available()
    {
        $id = $this->request->request('id');
        $name = $this->request->request('name');
        $value = $this->request->request('value');
        $name = substr($name, 4, -1);
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if ($id) {
            $this->model->where('id', '<>', $id);
        }
        $exist = $this->model->where($name, $value)->find();
        if ($exist) {
            $this->error(__('The data already exist'));
        } else {
            $this->success();
        }
    }

    /**
     * 搜索建议
     * @internal
     */
    public function suggestion()
    {
        $config = get_addon_config('cms');
        $q = trim($this->request->request("q"));
        $id = trim($this->request->request("id/d"));
        $list = [];
        if ($config['searchtype'] == 'xunsearch') {
            $result = FulltextSearch::search($q, 1, 10);
        } else {
            $result = $this->model->where("title|keywords|description", "like", "%{$q}%")->where('id', '<>', $id)->limit(10)->order("id", "desc")->select();
            foreach ($result as $index => $item) {
                $item['image'] = $item['image'] ? $item['image'] : '/assets/addons/cms/img/noimage.png';
                $list[] = ['id' => $item['id'], 'url' => $item['fullurl'], 'image' => cdnurl($item['image']), 'title' => $item['title'], 'create_date' => datetime($item['createtime']), 'status' => $item['status'], 'status_text' => $item['status_text'], 'deletetime' => $item['deletetime']];
            }
        }
        return json($list);
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
                    ->where('archives_id', 'in', $ids)
                    ->where('lang', 'zh')
                    ->column('title', 'archives_id');
                
                // 更新结果中的标题
                foreach ($result['list'] as &$item) {
                    if (isset($titles[$item['id']])) {
                        $item['title'] = $titles[$item['id']];
                    } elseif (isset($item['name'])) {
                        $item['title'] = $item['name'];
                    }
                }
            }
        }
        
        // 重新设置响应数据
        $response->data($result);
        
        return $response;
    }
}
