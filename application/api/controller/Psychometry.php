<?php

namespace app\api\controller;

use addons\cms\model\Archives;
use app\admin\model\user\Answer;
use app\common\controller\Api;
use app\common\library\Tool;

use function Yansongda\Supports\value;

/**
 * 心理测试
 */
class Psychometry extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 将 test_type 字符串转换为 back_type 数字（向后兼容）
     */
    private function testTypeToBackType($test_type)
    {
        $mapping = [
            'mbti' => 0,
            'score' => 1,
            'dimension' => 2,
            'custom' => 3,
            'nine_type' => 4,
            'multiple_type' => 5
        ];
        return $mapping[$test_type] ?? 3; // 默认返回 3 (custom)
    }

    /**
     * 获取兼容的语言代码
     * 支持URL参数 ?lg= 和API参数 lang
     * API返回zh-cn，但数据库存储的是zh
     */
    private function getCompatibleLang()
    {
        // 优先从API参数获取
        $lang = $this->request->post('lang', '');
        
        // 如果没有API参数，从URL参数获取
        if (empty($lang)) {
            $lang = $this->request->param('lg', '');
        }
        
        // 如果还没有，从Cookie获取
        if (empty($lang)) {
            $lang = cookie('frontend_language') ?: '';
        }
        
        // 如果都没有，使用系统默认语言
        if (empty($lang)) {
            $lang = $this->request->langset() ?: 'zh-cn';
        }

        // 语言代码映射
        $langMap = [
            'zh-cn' => 'zh',
            'zh' => 'zh',
            'en-us' => 'en',
            'en' => 'en',
            'ja-jp' => 'ja',
            'ja' => 'ja',
            'ko-kr' => 'ko',
            'ko' => 'ko'
        ];

        return isset($langMap[$lang]) ? $langMap[$lang] : 'zh';
    }

    /**
     * 获取多语言内容，支持回退到中文
     * @param string $table 表名
     * @param array $where 查询条件
     * @param string $lang 语言代码
     * @return array|null
     */
    private function getMultilangContent($table, $where, $lang)
    {
        // 先尝试获取指定语言的内容
        $content = \think\Db::table($table)
            ->where($where)
            ->where('lang', $lang)
            ->find();
        
        // 如果没有找到，且不是中文，则回退到中文
        if (!$content && $lang !== 'zh') {
            $content = \think\Db::table($table)
                ->where($where)
                ->where('lang', 'zh')
                ->find();
        }
        
        return $content;
    }

    /**
     * 智能获取测试多语言内容
     * 支持跨测试ID的内容匹配
     * @param int $test_id 测试ID
     * @param string $lang 语言代码
     * @return array|null
     */
    private function getSmartTestContent($test_id, $lang)
    {
        // 1. 先尝试获取指定测试ID和语言的内容
        $content = \think\Db::table('fa_psychometry_test_content')
            ->where('test_id', $test_id)
            ->where('lang', $lang)
            ->find();
        
        if ($content) {
            return $content;
        }
        
        // 2. 如果没有找到，尝试查找相同测试类型的其他测试ID的对应语言内容
        $test_info = \think\Db::table('fa_psychometry_test')
            ->where('id', $test_id)
            ->find();
        
        if ($test_info) {
            // 查找相同类型的其他测试
            $similar_tests = \think\Db::table('fa_psychometry_test')
                ->where('test_type', $test_info['test_type'])
                ->where('id', '<>', $test_id)
                ->column('id');
            
            if (!empty($similar_tests)) {
                $content = \think\Db::table('fa_psychometry_test_content')
                    ->where('test_id', 'in', $similar_tests)
                    ->where('lang', $lang)
                    ->find();
                
                if ($content) {
                    return $content;
                }
            }
        }
        
        // 3. 最后回退到中文内容
        $content = \think\Db::table('fa_psychometry_test_content')
            ->where('test_id', $test_id)
            ->where('lang', 'zh')
            ->find();
        
        return $content;
    }

    /**
     * 生成多语言SEO友好的URL
     * @param string $base_url 基础URL
     * @param string $lang 语言代码
     * @return string
     */
    private function generateMultilangUrl($base_url, $lang)
    {
        // 语言代码映射到URL前缀
        $langPrefixes = [
            'zh' => '',
            'en' => '/en',
            'ja' => '/ja',
            'ko' => '/ko'
        ];
        
        $prefix = isset($langPrefixes[$lang]) ? $langPrefixes[$lang] : '';
        
        // 如果URL已经包含语言前缀，替换它
        $pattern = '/^\/(en|ja|ko)\//';
        if (preg_match($pattern, $base_url)) {
            $base_url = preg_replace($pattern, '/', $base_url);
        }
        
        return $prefix . $base_url;
    }

    /**
     * 生成多语言hreflang标签
     * @param string $current_url 当前URL
     * @param array $available_langs 可用语言列表
     * @return array
     */
    private function generateHreflangTags($current_url, $available_langs = ['zh', 'en', 'ja', 'ko'])
    {
        $hreflang_tags = [];
        
        foreach ($available_langs as $lang) {
            $lang_url = $this->generateMultilangUrl($current_url, $lang);
            $hreflang_tags[] = [
                'lang' => $lang,
                'url' => $lang_url
            ];
        }
        
        return $hreflang_tags;
    }
    /**
     * 获取测试信息
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/psychometry/getTestInfo)
     * @ApiParams  (name=aid, type=string, required=true, description="当前文章id")
     * @ApiParams  (name=lang, type=string, required=false, description="语言代码，默认zh")
     */
    public function getTestInfo()
    {
        $aid = $this->request->post('aid', 0);
        $lang = $this->getCompatibleLang(); // 使用统一的语言获取方法
        
        $a_info = Archives::get($aid);
        if (!$a_info) {
            $this->error(__('文章不存在'));
        }
        
        $api_id = $a_info['product_id'];

        // 获取测试基本信息
        $test = \think\Db::table('fa_psychometry_test')
            ->where('archives_id', $api_id)
            ->find();

        if (!$test) {
            $this->error(__('测试不存在'));
        }
        
        $test_id = $test['id'];

        // 获取测试多语言内容 - 使用智能匹配
        $test_content = $this->getSmartTestContent($test['id'], $lang);

        $result = [
            'test_id' => $test['id'],
            'test_type' => $test['test_type'],
            'back_type' => $this->testTypeToBackType($test['test_type']), // 从 test_type 转换
            'show_type' => $test['show_type'],
            'question_count' => $test['question_count'],
            'base_score' => $test['base_score'],
            'test_config' => json_decode($test['test_config'], true),
            'scoring_rules' => json_decode($test['scoring_rules'], true),
            'result_rules' => json_decode($test['result_rules'], true),
            'status' => $test['status'],
            'sort_order' => $test['sort_order'],
            'title' => $test_content['title'] ?? '',
            'description' => $test_content['description'] ?? '',
            'intro' => $test_content['intro'] ?? '',
            'keywords' => $test_content['keywords'] ?? '',
            'seo_title' => $test_content['seo_title'] ?? ''
        ];

        $this->success(__('获取测试信息成功'), $result);
    }

    /**
     * 获取试题选项
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/psychometry/getQuestions)
     * @ApiParams  (name=token, type=string, required=true, description="请求的token")
     * @ApiParams  (name=aid, type=string, required=true, description="当前文章id")
     * @ApiParams  (name=lang, type=string, required=false, description="语言代码，默认zh")
     */
    public function getQuestions()
    {
        $aid = $this->request->post('aid', 0);
        $lang = $this->getCompatibleLang(); // 使用统一的语言获取方法
        
        $a_info = Archives::get($aid);
        if (!$a_info) {
            $this->error(__('文章不存在'));
        }

        // 获取测试ID
        $test = \think\Db::table('fa_psychometry_test')
            ->where('archives_id', $aid)
            ->find();

        if (!$test) {
            $this->error(__('测试不存在'));
        }

        // 根据测试的返回类型判断
        if ($test['back_type'] == 3) {
            // 答案类型题目 - 直接返回答案选项（选择阶段不返回答案解析）
            $answers = \think\Db::table('fa_psychometry_answer')
                ->alias('pa')
                ->join('fa_psychometry_answer_content pac', 'pa.id = pac.answer_id')
                ->where('pa.test_id', $test['id'])
                ->where('pa.status', 1)
                ->where('pac.lang', $lang)
                ->field('pa.id,pac.title,pac.cover,pa.result_key as mark')
                ->select();
            
            $r_data['question_data'] = $answers;
            $r_data['exam_info'] = $test;
            $r_data['count'] = count($answers);
            $this->success('操作成功', $r_data);
        } else {
            // 普通题目 - 返回题目和选项
            $questions = \think\Db::table('fa_psychometry_question')
                ->alias('pq')
                ->join('fa_psychometry_question_content pqc', 'pq.id = pqc.question_id')
                ->where('pq.test_id', $test['id'])
                ->where('pq.status', 1)
                ->where('pqc.lang', $lang)
                ->order('pq.sort_order asc')
                ->field('pq.id,pqc.question_text as question')
                ->select();

            $new_data = [];
            foreach ($questions as $k => $v) {
                // 从option_content表获取用户看到的选项文本
                $options = \think\Db::table('fa_psychometry_option_content')
                    ->where('question_id', $v['id'])
                    ->where('lang', $lang)
                    ->field('option_key as `key`,option_text as value')
                    ->order('option_key asc')
                    ->select();

                $selectdata = [];
                foreach ($options as $option) {
                    $selectdata[] = [
                        'key' => $option['key'],
                        'value' => $option['value']
                    ];
                }

                $new_data[$k]['id'] = $v['id'];
                $new_data[$k]['question'] = $v['question'];
                $new_data[$k]['selectdata'] = $selectdata;
            }
            $r_data['question_data'] = $new_data;
            $r_data['exam_info'] = $test;
            $r_data['count'] = count($new_data);
            $this->success('操作成功', $r_data);
        }
    }

    /**
     * 提交测试结果获取答案
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/psychometry/getExamResult)
     * @ApiParams  (name=token, type=string, required=true, description="请求的token")
     * @ApiParams  (name=aid, type=string, required=true, description="当前文章id")
     * @ApiParams  (name=answer_mark, type=string, required=true, description="简单试题答案")
     * @ApiParams  (name=answer_data, type=string, required=true, description="复杂试题答案")
     */
    public function getExamResult()
    {
        header('Access-Control-Allow-Origin:*');
        $ip = request()->ip();
        $aid = $this->request->post('aid', 0);
        $a_info = Archives::get($aid);
        if (!$a_info) {
            $this->error(__('试题不存在'));
        }

        // 去掉强制登录验证，允许游客访问
        $user = $this->auth->getUser();
        $user_id = $user ? $user['id'] : 0; // 游客用户ID为0

        $answer_mark = htmlspecialchars_decode($this->request->post('answer_mark', ''));
        $answer_json = htmlspecialchars_decode($this->request->post('answer_data', ''));
        if(empty($answer_mark) && empty($answer_json)){
            $this->error('请选择选项');
        }

        // 直接处理测试结果，不调用外部API
        $test = \think\Db::table('fa_psychometry_test')
            ->where('archives_id', $aid)
            ->find();

        if (!$test) {
            $this->error(__('测试不存在'));
        }
        
        $test_id = $test['id'];

        // 根据测试类型处理结果
        $result_data = $this->processTestResult($test, $answer_json, $answer_mark);
        
        // 生成唯一的结果ID
        $unique_id = $this->generateUniqueId();
        
        // 保存答题记录
        $update_data = [
            'answer_data' => $answer_json,
            'out_trade_no' => $unique_id,
            'stat_data' => json_encode($result_data['stat_data'], JSON_UNESCAPED_UNICODE),
            'answer_info' => json_encode($result_data['answer_info'], JSON_UNESCAPED_UNICODE),
            'answer_back' => json_encode($result_data['answer_back'], JSON_UNESCAPED_UNICODE),
            'show_type' => $test['show_type'] ?? '',
            'max_score' => $result_data['max_score'],
            'result' => $result_data['result'],
            'test_id' => $test_id,
            'aid' => $aid,
            'user_id' => $user_id,
            'price' => 0,
            'ip' => $ip,
            'pay_type' => 0,
            'paytime' => 0,
            'create_time' => time(),
            'back_type' => $this->testTypeToBackType($test['test_type']), // 从 test_type 转换
            'three_user_id' => '',
            'status' => 1
        ];
        
        $a_res = Answer::create($update_data);

        if ($a_res) {
            $r_data = [
                'answer_id' => $unique_id, // 使用生成的唯一ID而不是数据库ID
                'result' => $result_data['result'],
                'test_type' => $test['test_type'], // 添加测试类型信息
                'mbti_type' => $result_data['answer_info']['mbti_type'] ?? '', // MBTI类型
                'stat_data' => $result_data['stat_data'], // 统计数据
                'answer_info' => $result_data['answer_info'] // 答案详情
            ];
            
            // 记录日志用于调试
            \think\Log::write('提交结果数据：' . json_encode($r_data, JSON_UNESCAPED_UNICODE), 'info');
            
            $this->success(__('提交成功'), $r_data);
        } else {
            $this->error(__('提交失败'));
        }
    }

    /**
     * 处理测试结果
     */
    private function processTestResult($test, $answer_json, $answer_mark)
    {
        $test_type = $test['test_type'];
        $scoring_rules = json_decode($test['scoring_rules'], true);
        $result_rules = json_decode($test['result_rules'], true);
        
        // 解析答案数据
        $answers = json_decode($answer_json, true);
        if (!$answers) {
            $answers = [];
        }
        
        $result_data = [
            'stat_data' => [],
            'answer_info' => [],
            'answer_back' => [],
            'max_score' => 0,
            'result' => ''
        ];
        
        // 根据测试类型计算分数
        if ($test_type == 'mbti') {
            $result_data = $this->processMbtiResult($test, $answers, $scoring_rules, $result_rules);
        } elseif ($test_type == 'score') {
            $result_data = $this->processScoreResult($test, $answers, $scoring_rules, $result_rules);
        } elseif ($test_type == 'dimension') {
            $result_data = $this->processDimensionResult($test, $answers, $scoring_rules, $result_rules);
        } elseif ($test_type == 'custom') {
            $result_data = $this->processCustomResult($test, $answers, $scoring_rules, $result_rules);
        } elseif ($test_type == 'multiple_type') {
            $result_data = $this->processMultipleTypeResult($test, $answers, $scoring_rules, $result_rules);
        } elseif ($test_type == 'nine_type') {
            $result_data = $this->processNineTypeResult($test, $answers, $scoring_rules, $result_rules);
        } else {
            // 默认处理
            $result_data['result'] = '测试完成，感谢参与！';
            $result_data['max_score'] = count($answers);
        }
        
        return $result_data;
    }
    
    /**
     * 处理MBTI测试结果
     */
    private function processMbtiResult($test, $answers, $scoring_rules, $result_rules)
    {
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        $dimensions = ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'];
        $scores = [];
        
        // 初始化各维度分数
        foreach ($dimensions as $dim) {
            $scores[$dim] = 0;
        }
        
        // 计算各维度分数
        foreach ($answers as $answer) {
            // 前端发送的数据格式是 {"qid":1,"answer":"A"}
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key) {
                // 获取题目配置
                $question = \think\Db::table('fa_psychometry_question')
                    ->where('id', $question_id)
                    ->find();
                
                if ($question && $question['options_config']) {
                    $options = json_decode($question['options_config'], true);
                    foreach ($options as $option) {
                        if ($option['key'] == $answer_key) {
                            // 检查value是否是MBTI维度（E/I/S/N/T/F/J/P）
                            $value = $option['value'] ?? '';
                            if (in_array($value, $dimensions)) {
                                $scores[$value]++;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // 确定MBTI类型
        $mbti_type = '';
        $mbti_type .= ($scores['E'] > $scores['I']) ? 'E' : 'I';
        $mbti_type .= ($scores['S'] > $scores['N']) ? 'S' : 'N';
        $mbti_type .= ($scores['T'] > $scores['F']) ? 'T' : 'F';
        $mbti_type .= ($scores['J'] > $scores['P']) ? 'J' : 'P';
        
        // 获取MBTI类型的详细解析内容
        $mbti_content = \think\Db::table('fa_psychometry_answer')
            ->alias('pa')
            ->join('fa_psychometry_answer_content pac', 'pa.id = pac.answer_id')
            ->where('pa.test_id', $test['id'])
            ->where('pa.result_key', $mbti_type)
            ->where('pac.lang', $lang)
            ->field('pa.id,pa.result_key,pac.title,pac.content,pac.intro,pac.cover,pac.analysis,pac.suggestion')
            ->find();
        
        $answer_info = [
            'mbti_type' => $mbti_type,
            'type' => $mbti_type
        ];
        
        $answer_back = [
            'type' => $mbti_type
        ];
        
        // 如果有详细内容，添加到结果中
        if ($mbti_content) {
            $answer_info['mbti_content'] = $mbti_content;
        }
        
        return [
            'stat_data' => $scores,
            'answer_info' => $answer_info,
            'answer_back' => $answer_back,
            'max_score' => array_sum($scores),
            'result' => '您的MBTI类型是：' . $mbti_type
        ];
    }
    
    /**
     * 处理分数型测试结果
     */
    private function processScoreResult($test, $answers, $scoring_rules, $result_rules)
    {
        $total_score = 0;
        
        // 计算总分
        foreach ($answers as $answer) {
            // 前端发送的数据格式是 {"qid":1,"answer":"A"}
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key) {
                $question = \think\Db::table('fa_psychometry_question')
                    ->where('id', $question_id)
                    ->find();
                
                if ($question && $question['options_config']) {
                    $options = json_decode($question['options_config'], true);
                    foreach ($options as $option) {
                        if ($option['key'] == $answer_key) {
                            $total_score += $option['score'];
                            break;
                        }
                    }
                }
            }
        }
        
        return [
            'stat_data' => ['total_score' => $total_score],
            'answer_info' => ['score' => $total_score],
            'answer_back' => ['score' => $total_score],
            'max_score' => $total_score,
            'result' => '您的得分是：' . $total_score . '分'
        ];
    }
    
    /**
     * 处理维度型测试结果（支持 question_numbers 和 question_ids 配置）
     */
    private function processDimensionResult($test, $answers, $scoring_rules, $result_rules)
    {
        $total_score = 0;
        $dimension_scores = [];
        
        // 获取测试ID
        $test_id = $test['id'];
        
        // 获取维度配置（从 fa_psychometry_answer 表）
        $dimension_configs = \think\Db::table('fa_psychometry_answer')
            ->where('test_id', $test_id)
            ->where('result_type', 'dimension')
            ->where('status', 1)
            ->select();
        
        // 构建题号到维度的映射（用于 question_numbers 方式）
        $question_number_to_dimension = [];
        $dimension_max_scores = [];  // 记录每个维度的最大分数
        
        foreach ($dimension_configs as $config) {
            $conditions = json_decode($config['conditions'], true) ?: [];
            $result_key = $config['result_key'];
            
            foreach ($conditions as $condition) {
                // 检查是否有 dimension_questions 配置
                if (isset($condition['type']) && $condition['type'] === 'dimension_questions') {
                    // 使用新字段名 question_numbers，向后兼容旧字段名 question_ids
                    $question_numbers = $condition['question_numbers'] ?? $condition['question_ids'] ?? [];
                    
                    foreach ($question_numbers as $qnum) {
                        if (!isset($question_number_to_dimension[$qnum])) {
                            $question_number_to_dimension[$qnum] = [];
                        }
                        $question_number_to_dimension[$qnum][] = $result_key;
                    }
                }
            }
            
            // 初始化维度分数
            $dimension_scores[$result_key] = 0;
            
            // 从 result_config 获取最大分数
            $result_config = json_decode($config['result_config'], true) ?: [];
            if (isset($result_config['max_score'])) {
                $dimension_max_scores[$result_key] = $result_config['max_score'];
            }
        }
        
        // 判断使用哪种计算方式
        $use_question_number_method = !empty($question_number_to_dimension);
        
        // 计算分数
        foreach ($answers as $answer) {
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key) {
                $question = \think\Db::table('fa_psychometry_question')
                    ->where('id', $question_id)
                    ->find();
                
                if ($question && $question['options_config']) {
                    $options = json_decode($question['options_config'], true);
                    $question_sort_order = $question['sort_order'];  // 获取题号
                    
                    foreach ($options as $option) {
                        if ($option['key'] == $answer_key) {
                            // 优先使用 value 字段，否则使用 score
                            $score = isset($option['value']) && is_numeric($option['value']) 
                                ? $option['value'] 
                                : ($option['score'] ?? 1);
                            
                            $total_score += $score;
                            
                            if ($use_question_number_method) {
                                // 方式1：基于题号的维度计算（使用 question_numbers 配置）
                                if (isset($question_number_to_dimension[$question_sort_order])) {
                                    foreach ($question_number_to_dimension[$question_sort_order] as $dim_key) {
                                        $dimension_scores[$dim_key] += $score;
                                    }
                                }
                            } else {
                                // 方式2：基于选项的维度计算（使用选项中的 dimension 字段）
                                if (isset($option['dimension'])) {
                                    $dimension = $option['dimension'];
                                    if (!isset($dimension_scores[$dimension])) {
                                        $dimension_scores[$dimension] = 0;
                                    }
                                    $dimension_scores[$dimension] += $score;
                                }
                            }
                            
                            break;
                        }
                    }
                }
            }
        }
        
        // 如果没有维度分数，使用总分
        if (empty($dimension_scores) || array_sum($dimension_scores) == 0) {
            $dimension_scores = ['总分' => $total_score];
        }
        
        // 移除分数为0的维度
        $dimension_scores = array_filter($dimension_scores, function($score) {
            return $score > 0;
        });
        
        return [
            'stat_data' => $dimension_scores,
            'answer_info' => [
                'total_score' => $total_score, 
                'dimensions' => $dimension_scores,
                'dimension_max_scores' => $dimension_max_scores
            ],
            'answer_back' => [
                'total_score' => $total_score, 
                'dimensions' => $dimension_scores
            ],
            'max_score' => $total_score,
            'result' => '您的总分是：' . $total_score . '分'
        ];
    }
    
    /**
     * 处理自定义类型测试结果
     */
    private function processCustomResult($test, $answers, $scoring_rules, $result_rules)
    {
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        $selected_options = [];
        $total_score = 0;
        
        // 处理每个答案
        foreach ($answers as $answer) {
            $answer_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($answer_id && $answer_key) {
                // 对于back_type==3的测试，直接查询答案表
                // 先根据answer_key查找对应的答案记录
                $answer_info = \think\Db::table('fa_psychometry_answer')
                    ->alias('pa')
                    ->join('fa_psychometry_answer_content pac', 'pa.id = pac.answer_id')
                    ->where('pa.test_id', $test['id'])
                    ->where('pa.result_key', $answer_key)
                    ->where('pac.lang', $lang)
                    ->field('pa.id,pa.result_key,pac.title,pac.intro,pac.cover')
                    ->find();
                
                if ($answer_info) {
                    $selected_options[] = [
                        'answer_id' => $answer_info['id'],
                        'option_key' => $answer_info['result_key'],
                        'option_title' => $answer_info['title'],
                        'option_intro' => $answer_info['intro'],
                        'option_cover' => $answer_info['cover'],
                        'score' => 1
                    ];
                    $total_score += 1;
                }
            }
        }
        
        // 生成结果信息
        $result_info = [];
        if (!empty($selected_options)) {
            $result_info = [
                'selected_options' => $selected_options,
                'total_score' => $total_score,
                'option_count' => count($selected_options)
            ];
        }
        
        return [
            'stat_data' => [], // 自定义类型不需要统计分数
            'answer_info' => $result_info,
            'answer_back' => $result_info,
            'max_score' => $total_score,
            'result' => '测试完成，感谢参与！'
        ];
    }

    private function generateSign($post_data, $api_key)
    {
        return getsign($post_data, $api_key);
    }
    
    /**
     * 生成唯一的结果ID
     */
    private function generateUniqueId()
    {
        // 生成一个包含时间戳和随机数的唯一ID
        $timestamp = time();
        $random = mt_rand(100000, 999999);
        $unique_id = 'test_' . $timestamp . '_' . $random;
        
        // 确保ID唯一性
        $exists = \think\Db::table('fa_user_answer')
            ->where('out_trade_no', $unique_id)
            ->find();
            
        if ($exists) {
            // 如果ID已存在，递归生成新的ID
            return $this->generateUniqueId();
        }
        
        return $unique_id;
    }
    
    /**
     * 根据分数获取分析数据
     */
    private function getScoreAnalysis($test_id, $score)
    {
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        // 先尝试根据分数范围查找对应的答案分析
        $answer = \think\Db::table('fa_psychometry_answer')
            ->alias('pa')
            ->join('fa_psychometry_answer_content pac', 'pa.id = pac.answer_id')
            ->where('pa.test_id', $test_id)
            ->where('pa.status', 1)
            ->where('pac.lang', $lang)
            ->field('pa.id,pa.result_key,pa.result_config,pac.title,pac.intro,pac.content,pac.analysis,pac.suggestion,pac.cover')
            ->select();
        
        if (empty($answer)) {
            return null;
        }
        
        // 根据分数范围匹配最合适的分析
        foreach ($answer as $item) {
            $config = json_decode($item['result_config'], true);
            if ($config && isset($config['min_score']) && isset($config['max_score'])) {
                if ($score >= $config['min_score'] && $score <= $config['max_score']) {
                    return [
                        'title' => $item['title'],
                        'intro' => $item['intro'],
                        'content' => $item['content'],
                        'analysis' => $item['analysis'],
                        'suggestion' => $item['suggestion'],
                        'cover' => $item['cover']
                    ];
                }
            }
        }
        
        // 如果没有找到匹配的分数范围，返回第一个可用的分析
        $first_item = $answer[0];
        return [
            'title' => $first_item['title'],
            'intro' => $first_item['intro'],
            'content' => $first_item['content'],
            'analysis' => $first_item['analysis'],
            'suggestion' => $first_item['suggestion'],
            'cover' => $first_item['cover']
        ];
    }

    private function prepareUpdateData($data, $answer_json, $api_id, $aid, $check_data, $ip)
    {
        return [
            'answer_data' => $answer_json,
            'out_trade_no' => $data['out_trade_no'],
            'stat_data' => json_encode($data['stat_data'], JSON_UNESCAPED_UNICODE),
            'answer_info' => json_encode($data['answer_info'], JSON_UNESCAPED_UNICODE),
            'answer_back' => json_encode($data['answer_back'], JSON_UNESCAPED_UNICODE),
            'show_type' => $data['show_type'],
            'max_score' => $data['max_score'],
            'result' => $data['res_txt'],
            'aid' => $aid,
            'user_id' => $check_data['three_user_id'],
            'price' => $check_data['price'],
            'ip' => $ip,
            'pay_type' => $check_data['pay_type'] > 0 ? $check_data['pay_type'] : 0,
            'paytime' => $check_data['pay_type'] > 0 ? time() : 0,
            'create_time' => time()
        ];
    }
    public function getExamAuth(){
        header('Access-Control-Allow-Origin:*');
        $aid = $this->request->get('aid',0);
        $a_info = Archives::get($aid);
        if(empty($aid) || empty($a_info)){
            $check_data['code'] = 0;
            $check_data['msg'] = '测试题不存在';
            exit(json_encode($check_data,JSON_UNESCAPED_UNICODE));
        }
        $user = $this->auth->getUser();
        $tool = new Tool();
        $check_data = $tool->checkAuth($user,$a_info);
        exit(json_encode($check_data,JSON_UNESCAPED_UNICODE));
    }
    /**
     * 获取测试结果详情
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/psychometry/answerInfo)
     * @ApiParams  (name=answer_id, type=string, required=true, description="答题记录ID")
     */
    public function answerInfo()
    {
        header('Access-Control-Allow-Origin:*');
        $answer_id = $this->request->post('answer_id', 0) ?: $this->request->post('aid', 0);
        $lang = $this->getCompatibleLang(); // 使用统一的语言获取方法
        
        // 调试日志
        \think\Log::write('🔥🔥🔥 answerInfo方法被调用 | answer_id=' . $answer_id . ' | lang=' . $lang . ' | 当前时间=' . date('Y-m-d H:i:s'), 'info');
        
        if (!$answer_id) {
            $this->error(__('参数错误'));
        }
        
        // 获取答题记录 - 支持通过唯一ID或数据库ID查找
        $answer = \think\Db::table('fa_user_answer')
            ->where('out_trade_no', $answer_id)
            ->find();
            
        if (!$answer) {
            // 如果通过唯一ID没找到，尝试通过数据库ID查找（向后兼容）
            $answer = \think\Db::table('fa_user_answer')
                ->where('id', $answer_id)
                ->find();
        }
            
        if (!$answer) {
            $this->error(__('答题记录不存在'));
        }
        
        // 获取文章信息
        $a_info = Archives::get($answer['aid']);
        if (!$a_info) {
            $this->error(__('文章不存在'));
        }
        
        // 获取文章的多语言内容
        $multilangContent = $a_info->getMultilangContent($lang);
        if ($multilangContent) {
            // 设置临时语言，让访问器使用正确的语言
            $a_info->tempLang = $lang;
            $a_info->setAttr('title', $multilangContent['title']);
            $a_info->setAttr('sub_title', $multilangContent['sub_title']);
            $a_info->setAttr('content', $multilangContent['content']);
            $a_info->setAttr('question', $multilangContent['question']);
            $a_info->setAttr('description', $multilangContent['description']);
        }
        
        // 获取测试信息
        $test = \think\Db::table('fa_psychometry_test')
            ->where('id', $answer['test_id'])
            ->find();
            
        if (!$test) {
            $this->error(__('测试不存在'));
        }
        
        // 获取测试的多语言内容
        $testContent = \think\Db::table('fa_psychometry_test_content')
            ->where('test_id', $test['id'])
            ->where('lang', $lang)
            ->find();
        
        if ($testContent) {
            $test['title'] = $testContent['title'];
            $test['description'] = $testContent['description'];
            $test['intro'] = $testContent['intro'];
            $test['keywords'] = $testContent['keywords'];
            $test['seo_title'] = $testContent['seo_title'];
        }
        
        // 解析答题数据
        $stat_data = json_decode($answer['stat_data'], true) ?: [];
        $answer_info_db = json_decode($answer['answer_info'], true) ?: []; // 数据库缓存（保留用于向后兼容）
        $answer_back = json_decode($answer['answer_back'], true) ?: [];

        // 根据测试类型重新计算分析数据（不使用数据库缓存）
        $analyse = null;
        $answer_info = null; // 重新计算的answer_info
        
        if ($test['test_type'] == 'dimension') {
            // 维度类型测试 - 实时重新计算
            \think\Log::write('⭐⭐⭐ 维度测试：实时重新计算 | test_id=' . $test['id'] . ' | stat_data=' . json_encode($stat_data) . ' | lang=' . $lang, 'info');
            $analyse = $this->getDimensionAnalysis($test['id'], $stat_data, $lang);
            \think\Log::write('⭐⭐⭐ getDimensionAnalysis返回完成 | dimensions字段存在=' . (isset($analyse['dimensions']) ? 'YES' : 'NO') . ' | dimensions数量=' . (isset($analyse['dimensions']) ? count($analyse['dimensions']) : 0), 'info');
            
            // 构建answer_info（包含维度信息）
            $answer_info = [
                'dimensions' => $stat_data,
                'total_score' => array_sum($stat_data)
            ];
        } elseif (isset($answer_info_db['mbti_type']) && !empty($answer_info_db['mbti_type'])) {
            // MBTI测试
            $analyse = \think\Db::table('fa_psychometry_analyse')
                ->where('mark', $answer_info_db['mbti_type'])
                ->find();
            $answer_info = $answer_info_db;
        } elseif (isset($answer_info_db['custom_type']) && !empty($answer_info_db['custom_type'])) {
            // 自定义测试
            $analyse = \think\Db::table('fa_psychometry_analyse')
                ->where('mark', $answer_info_db['custom_type'])
                ->find();
            $answer_info = $answer_info_db;
        } elseif (isset($answer_info_db['type']) && !empty($answer_info_db['type'])) {
            // 其他类型测试
            $analyse = \think\Db::table('fa_psychometry_analyse')
                ->where('mark', $answer_info_db['type'])
                ->find();
            $answer_info = $answer_info_db;
        } elseif ($test['test_type'] == 'score') {
            // 分数类型测试 - 根据分数范围获取分析数据
            $score = $answer_info_db['score'] ?? $stat_data['total_score'] ?? 0;
            $analyse = $this->getScoreAnalysis($test['id'], $score);
            $answer_info = $answer_info_db;
        } elseif ($test['test_type'] == 'multiple_type') {
            // 多选类型测试 - 获取类型分析数据
            $analyse = $this->getMultipleTypeAnalysis($test['id'], $stat_data);
            $answer_info = $answer_info_db;
        } elseif ($test['test_type'] == 'nine_type') {
            // 九型人格测试 - 按multiple_type处理
            $analyse = $this->getNineTypeAnalysis($test['id'], $stat_data);
            $answer_info = $answer_info_db;
        } else {
            // 默认使用数据库缓存
            $answer_info = $answer_info_db;
        }
        
        // 对于自定义测试，重新从数据库获取选项内容，确保数据准确性
        if ($test['test_type'] == 'custom' && isset($answer_info['selected_options'])) {
            $updated_options = [];
            foreach ($answer_info['selected_options'] as $option) {
                if (isset($option['answer_id'])) {
                    // 重新查询选项内容
                    $option_content = \think\Db::table('fa_psychometry_answer')
                        ->alias('pa')
                        ->join('fa_psychometry_answer_content pac', 'pa.id = pac.answer_id')
                        ->where('pa.id', $option['answer_id'])
                        ->where('pac.lang', $lang)
                        ->field('pa.id,pa.result_key,pac.title,pac.intro,pac.cover,pac.content')
                        ->find();
                    
                    if ($option_content) {
                        $updated_options[] = [
                            'answer_id' => $option_content['id'],
                            'option_key' => $option_content['result_key'],
                            'option_title' => $option_content['title'],
                            'option_intro' => $option_content['intro'],
                            'option_cover' => $option_content['cover'],
                            'option_content' => $option_content['content'],
                            'score' => $option['score'] ?? 1
                        ];
                    }
                }
            }
            
            if (!empty($updated_options)) {
                $answer_info['selected_options'] = $updated_options;
                $answer_back['selected_options'] = $updated_options;
            }
        }

        $result_data = [
            'id' => $answer['id'],
            'answer_data' => $answer['answer_data'],
            'stat_data' => $stat_data,
            'answer_info' => $answer_info,
            'answer_back' => $answer_back,
            'show_type' => $answer['show_type'],
            'max_score' => $answer['max_score'],
            'result' => $answer['result'],
            'create_time' => $answer['create_time'],
            'a_info' => $a_info,
            'test_info' => $test,
            'analyse' => $analyse
        ];
        
        $this->success('操作成功', $result_data);
    }
    
    /**
     * 处理多维度测试数据（适配新表结构）
     * @param array $answer_data 用户答案数据
     * @param int $test_id 测试ID
     * @return array
     */
    public function getMultipleData($answer_data = [], $test_id = 0)
    {
        if (empty($answer_data) || !$test_id) {
            return ['stat_data' => [], 'res_txt' => ''];
        }
        
        // 获取测试信息
        $test = \think\Db::table('fa_psychometry_test')->where('id', $test_id)->find();
        if (!$test) {
            return ['stat_data' => [], 'res_txt' => ''];
        }
        
        // 获取所有题目数据
        $questions = \think\Db::table('fa_psychometry_question')
            ->where('test_id', $test_id)
            ->where('status', 1)
            ->order('sort_order ASC')
            ->select();
        
        if (empty($questions)) {
            return ['stat_data' => [], 'res_txt' => ''];
        }
        
        // 构建题目数据映射
        $question_data = [];
        foreach ($questions as $question) {
            $question_data[$question['id']] = $question;
        }
        
        // 计算各维度分数
        $stat_data = [];
        $total_score = 0;
        
        foreach ($answer_data as $answer) {
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key && isset($question_data[$question_id])) {
                $question = $question_data[$question_id];
                $options_config = json_decode($question['options_config'], true);
                
                if ($options_config) {
                    foreach ($options_config as $option) {
                        if ($option['key'] == $answer_key) {
                            $score = $option['score'] ?? 1;
                            $total_score += $score;
                            
                            // 按维度统计分数
                            if (isset($option['dimension'])) {
                                $dimension = $option['dimension'];
                                if (!isset($stat_data[$dimension])) {
                                    $stat_data[$dimension] = [
                                        'name' => $dimension,
                                        'mark' => $dimension,
                                        'score' => 0,
                                        'score_rate' => 0
                                    ];
                                }
                                $stat_data[$dimension]['score'] += $score;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // 计算各维度的最大可能分数
        $dimension_max_scores = [];
        foreach ($questions as $question) {
            $options_config = json_decode($question['options_config'], true);
            if ($options_config) {
                foreach ($options_config as $option) {
                    if (isset($option['dimension'])) {
                        $dimension = $option['dimension'];
                        $max_score = $option['score'] ?? 1;
                        if (!isset($dimension_max_scores[$dimension])) {
                            $dimension_max_scores[$dimension] = 0;
                        }
                        $dimension_max_scores[$dimension] += $max_score;
                    }
                }
            }
        }
        
        // 计算得分率
        foreach ($stat_data as $dimension => &$data) {
            if (isset($dimension_max_scores[$dimension]) && $dimension_max_scores[$dimension] > 0) {
                $data['score_rate'] = round(($data['score'] / $dimension_max_scores[$dimension]) * 100);
            }
        }
        
        // 如果没有维度数据，使用总分
        if (empty($stat_data)) {
            $stat_data = [
                '总分' => [
                    'name' => '总分',
                    'mark' => '总分',
                    'score' => $total_score,
                    'score_rate' => 100
                ]
            ];
        }
        
        return [
            'stat_data' => $stat_data,
            'res_txt' => ''
        ];
    }

    /**
     * 获取维度测试分析数据
     */
    private function getDimensionAnalysis($test_id, $stat_data, $lang = null)
    {
        // 调试：记录原始lang参数
        $original_lang = $lang;
        
        // 获取当前语言
        if (!$lang) {
            $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        }
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        // 调试日志
        \think\Log::write('getDimensionAnalysis START | 原始lang: ' . var_export($original_lang, true) . ' | 处理后lang: ' . $lang . ' | test_id: ' . $test_id, 'info');
        
        if (empty($stat_data)) {
            return null;
        }
        
        // 获取该测试的所有分析配置
        $answers = \think\Db::table('fa_psychometry_answer')
            ->where('test_id', $test_id)
            ->where('status', 1)
            ->select();
            
        if (empty($answers)) {
            return null;
        }
        
        // 构建维度分析内容和维度配置信息
        $analysis_content = '';
        $analysis_title = '';
        $dimension_config = [];
        $max_possible_score = 0;
        
        // 遍历各维度得分
        foreach ($stat_data as $dimension => $score) {
            if ($dimension === '总分') {
                continue; // 跳过总分
            }
            
            // dimension 就是 result_key (如 'R', 'I', 'A' 等)
            // 第一步：查询答案基础信息
            $answer_base = \think\Db::table('fa_psychometry_answer')
                ->where('test_id', $test_id)
                ->where('result_key', $dimension)
                ->field('id, result_key, conditions, result_config')
                ->find();
            
            if (!$answer_base) {
                continue;
            }
            
            // 第二步：查询对应语言的内容
            $answer_content = \think\Db::table('fa_psychometry_answer_content')
                ->where('answer_id', $answer_base['id'])
                ->where('lang', $lang)
                ->field('title, content, intro')
                ->find();
            
            // 调试：记录查询结果
            \think\Log::write('查询answer_id=' . $answer_base['id'] . ', lang=' . $lang . ' | 结果: ' . ($answer_content ? 'YES' : 'NO'), 'info');
            
            // 调试：检查查询结果
            if ($answer_content) {
                \think\Log::write('维度 ' . $dimension . ' | answer_id=' . $answer_base['id'] . ' | lang=' . $lang . ' | ✓查到内容 | title=' . $answer_content['title'] . ' | content长度=' . strlen($answer_content['content']), 'info');
            } else {
                \think\Log::write('维度 ' . $dimension . ' | answer_id=' . $answer_base['id'] . ' | lang=' . $lang . ' | ✗未查到内容', 'warning');
                
                // 进一步调试：查看该answer_id有哪些语言的数据
                $available_langs = \think\Db::table('fa_psychometry_answer_content')
                    ->where('answer_id', $answer_base['id'])
                    ->column('lang');
                \think\Log::write('该answer_id=' . $answer_base['id'] . '存在的语言: ' . implode(',', $available_langs), 'info');
            }
            
            // 合并数据
            if ($answer_content) {
                $answer_info = array_merge($answer_base, $answer_content);
            } else {
                $answer_info = array_merge($answer_base, ['title' => '', 'content' => '', 'intro' => '']);
                \think\Log::write('警告：维度 ' . $dimension . ' 没有查到对应语言(' . $lang . ')的内容', 'warning');
            }
                
            if ($answer_info) {
                $dimension_name = $answer_info['title'] ?: $dimension;
                
                // 从conditions中获取该维度的题目数（用于计算满分）
                $conditions = json_decode($answer_info['conditions'], true);
                $question_count = 0;
                if ($conditions) {
                    foreach ($conditions as $condition) {
                        if (isset($condition['type']) && $condition['type'] === 'dimension_questions' && isset($condition['question_ids'])) {
                            $question_count = count($condition['question_ids']);
                            break;
                        }
                    }
                }
                
                // 从result_config获取配置
                $result_config = json_decode($answer_info['result_config'], true);
                $dimension_max_score = $result_config['max_score'] ?? $question_count;
                
                if ($dimension_max_score > $max_possible_score) {
                    $max_possible_score = $dimension_max_score;
                }
                
                // 保存维度配置信息（包含详细内容）
                $dimension_config[$dimension] = [
                    'key' => $dimension,
                    'name' => $dimension_name,
                    'max_score' => $dimension_max_score,
                    'score' => $score,
                    'detail' => $answer_info['content'] ?: '' // 新增：维度的详细分析内容
                ];
                
                // 构建分析内容（使用完整维度名称）
                if (empty($analysis_title)) {
                    // 根据语言返回不同的标题
                    $analysis_title = ($lang == 'zh') ? '维度分析报告' : 'Dimension Analysis Report';
                }
                
                // 使用完整的维度名称而不是key
                $analysis_content .= '<h4>' . $dimension_name . ' (' . $score . '分)</h4>';
                if ($answer_info['content']) {
                    // content本身已经是HTML格式，直接使用
                    $analysis_content .= $answer_info['content'];
                } else {
                    // 通用分析也使用完整名称
                    $default_text = ($lang == 'zh') 
                        ? '您在' . $dimension_name . '维度得分为' . $score . '分。这个分数反映了您在该维度上的表现水平。'
                        : 'You scored ' . $score . ' points in ' . $dimension_name . ' dimension. This score reflects your performance level in this dimension.';
                    $analysis_content .= '<p>' . $default_text . '</p>';
                }
            } else {
                // 如果没有找到配置，使用默认值
                $dimension_config[$dimension] = [
                    'key' => $dimension,
                    'name' => $dimension,
                    'max_score' => 15, // 默认满分
                    'score' => $score
                ];
                
                $analysis_content .= '<h4>' . $dimension . ' (' . $score . '分)</h4>';
                $analysis_content .= '<p>您在' . $dimension . '维度得分为' . $score . '分。</p>';
            }
        }
        
        if (empty($analysis_content)) {
            return null;
        }
        
        // 多语言文本
        $intro_text = ($lang == 'zh') 
            ? '根据您的测试结果，我们为您分析了各个维度的表现'
            : 'Based on your test results, we have analyzed your performance in each dimension';
        
        $suggestion_title = ($lang == 'zh') ? '建议' : 'Suggestions';
        $suggestion_text = ($lang == 'zh')
            ? '测试结果仅供参考，如需专业指导，请咨询相关领域的专家。'
            : 'Test results are for reference only. For professional guidance, please consult experts in the relevant field.';
        
        return [
            'title' => $analysis_title ?: (($lang == 'zh') ? '维度分析报告' : 'Dimension Analysis Report'),
            'intro' => $intro_text,
            'content' => $analysis_content,
            'analysis' => $analysis_content,
            'suggestion' => '<h4>' . $suggestion_title . '</h4><p>' . $suggestion_text . '</p>',
            'dimensions' => $dimension_config, // 维度配置信息
            'max_possible_score' => $max_possible_score, // 最大可能分数
            'language' => $lang // 当前语言
        ];
    }

    /**
     * 处理多选类型测试结果
     */
    private function processMultipleTypeResult($test, $answers, $scoring_rules, $result_rules)
    {
        $test_id = $test['id'];
        $total_score = 0;
        $type_scores = [];
        
        // 计算各类型分数
        foreach ($answers as $answer) {
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key) {
                $question = \think\Db::table('fa_psychometry_question')
                    ->where('id', $question_id)
                    ->find();
                
                if ($question && $question['options_config']) {
                    $options = json_decode($question['options_config'], true);
                    foreach ($options as $option) {
                        if ($option['key'] == $answer_key) {
                            $score = $option['score'] ?? 1;
                            $total_score += $score;
                            
                            // 如果有类型信息，按类型统计
                            if (isset($option['type'])) {
                                $type = $option['type'];
                                if (!isset($type_scores[$type])) {
                                    $type_scores[$type] = 0;
                                }
                                $type_scores[$type] += $score;
                            } elseif (isset($option['value'])) {
                                // FPA性格色彩测试使用value字段表示类型
                                $type_value = $option['value'];
                                $type_mapping = [
                                    '1' => '1', // 红色
                                    '2' => '2', // 蓝色  
                                    '3' => '3', // 黄色
                                    '4' => '4'  // 绿色
                                ];
                                $type = $type_mapping[$type_value] ?? $type_value;
                                if (!isset($type_scores[$type])) {
                                    $type_scores[$type] = 0;
                                }
                                $type_scores[$type] += $score;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // 如果没有类型分数，使用总分
        if (empty($type_scores)) {
            $type_scores = ['总分' => $total_score];
        }
        
        // 获取分析内容
        $analyse = $this->getMultipleTypeAnalysis($test_id, $type_scores);
        
        return [
            'stat_data' => $type_scores,
            'answer_info' => $type_scores,
            'answer_back' => $type_scores,
            'max_score' => $total_score,
            'result' => '测试完成，感谢参与！',
            'analyse' => $analyse
        ];
    }
    
    /**
     * 处理九型人格测试结果
     */
    private function processNineTypeResult($test, $answers, $scoring_rules, $result_rules)
    {
        $test_id = $test['id'];
        $total_score = 0;
        $type_scores = [];
        
        // 计算各类型分数
        foreach ($answers as $answer) {
            $question_id = $answer['qid'] ?? $answer['question_id'] ?? 0;
            $answer_key = $answer['answer'] ?? '';
            
            if ($question_id && $answer_key) {
                $question = \think\Db::table('fa_psychometry_question')
                    ->where('id', $question_id)
                    ->find();
                
                if ($question && $question['options_config']) {
                    $options = json_decode($question['options_config'], true);
                    foreach ($options as $option) {
                        if ($option['key'] == $answer_key) {
                            $score = $option['score'] ?? 1;
                            $total_score += $score;
                            
                            // 九型人格测试使用value字段表示类型（如#1, #2等）
                            if (isset($option['value'])) {
                                $type_value = $option['value'];
                                // 去掉#号，只保留数字
                                $type = str_replace('#', '', $type_value);
                                if (!isset($type_scores[$type])) {
                                    $type_scores[$type] = 0;
                                }
                                $type_scores[$type] += $score;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // 如果没有类型分数，使用总分
        if (empty($type_scores)) {
            $type_scores = ['总分' => $total_score];
        }
        
        // 获取分析内容
        $analyse = $this->getNineTypeAnalysis($test_id, $type_scores);
        
        return [
            'stat_data' => $type_scores,
            'answer_info' => $type_scores,
            'answer_back' => $type_scores,
            'max_score' => $total_score,
            'result' => '测试完成，感谢参与！',
            'analyse' => $analyse
        ];
    }

    /**
     * 获取多选类型测试分析数据
     */
    private function getMultipleTypeAnalysis($test_id, $type_scores)
    {
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        if (empty($type_scores)) {
            return null;
        }
        
        // 获取该测试的所有分析配置
        $answers = \think\Db::table('fa_psychometry_answer')
            ->where('test_id', $test_id)
            ->where('status', 1)
            ->select();
            
        if (empty($answers)) {
            return null;
        }
        
        // 构建分析内容
        $analysis_content = '';
        $analysis_title = '';
        
        // 找到得分最高的类型
        $max_type = '';
        $max_score = 0;
        foreach ($type_scores as $type => $score) {
            if ($type !== '总分' && $score > $max_score) {
                $max_score = $score;
                $max_type = $type;
            }
        }
        
        // 根据最高分类型获取分析内容
        if ($max_type) {
            $answer_content = \think\Db::table('fa_psychometry_answer_content')
                ->alias('pac')
                ->join('fa_psychometry_answer pa', 'pac.answer_id = pa.id')
                ->where('pa.test_id', $test_id)
                ->where('pa.result_key', $max_type)
                ->where('pac.lang', $lang)
                ->field('pac.title, pac.content, pac.intro')
                ->find();
                
            if ($answer_content) {
                $analysis_title = $answer_content['title'] . '性格分析';
                $analysis_content = $answer_content['content'];
            }
        }
        
        if (empty($analysis_content)) {
            return null;
        }
        
        return [
            'title' => $analysis_title ?: '性格分析报告',
            'intro' => '根据您的测试结果，我们为您分析了您的性格特征',
            'content' => $analysis_content,
            'analysis' => $analysis_content,
            'suggestion' => '<h4>建议</h4><p>测试结果仅供参考，如需专业指导，请咨询相关领域的专家。</p>'
        ];
    }
    
    /**
     * 获取九型人格测试的分析内容
     * @param int $test_id 测试ID
     * @param array $type_scores 各类型分数
     * @return array|null
     */
    private function getNineTypeAnalysis($test_id, $type_scores)
    {
        // 获取当前语言
        $lang = cookie('frontend_language') ? cookie('frontend_language') : 'zh-cn';
        $lang = ($lang == 'en') ? 'en' : 'zh';
        
        if (empty($type_scores)) {
            return null;
        }
        
        // 获取该测试的所有分析配置
        $answers = \think\Db::table('fa_psychometry_answer')
            ->where('test_id', $test_id)
            ->where('status', 1)
            ->select();
            
        if (empty($answers)) {
            return null;
        }
        
        // 构建分析内容
        $analysis_content = '';
        $analysis_title = '';
        
        // 找到得分最高的类型
        $max_type = '';
        $max_score = 0;
        foreach ($type_scores as $type => $score) {
            if ($type !== '总分' && $score > $max_score) {
                $max_score = $score;
                $max_type = $type;
            }
        }
        
        // 根据最高分类型获取分析内容
        if ($max_type) {
            $answer_content = \think\Db::table('fa_psychometry_answer_content')
                ->alias('pac')
                ->join('fa_psychometry_answer pa', 'pac.answer_id = pa.id')
                ->where('pa.test_id', $test_id)
                ->where('pa.result_key', $max_type)
                ->where('pac.lang', $lang)
                ->field('pac.title, pac.content, pac.intro')
                ->find();
                
            if ($answer_content) {
                $analysis_title = $answer_content['title'] . '性格分析';
                $analysis_content = $answer_content['content'];
            }
        }
        
        if (empty($analysis_content)) {
            return null;
        }
        
        return [
            'title' => $analysis_title ?: '九型人格分析报告',
            'intro' => '根据您的测试结果，我们为您分析了您的九型人格特征',
            'content' => $analysis_content,
            'analysis' => $analysis_content,
            'suggestion' => '<h4>建议</h4><p>测试结果仅供参考，如需专业指导，请咨询相关领域的专家。</p>'
        ];
    }
}
