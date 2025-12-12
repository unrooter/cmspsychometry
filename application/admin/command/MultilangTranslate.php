<?php

namespace app\admin\command;

use app\common\library\Bdfy;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class MultilangTranslate extends Command
{
    protected function configure()
    {
        $this->setName('multilang:translate')
            ->setDescription('多语言翻译命令')
            ->addArgument('action', null, '操作类型: article|test|questions|answers|complete')
            ->addArgument('id', null, '文章ID或测试ID')
            ->addArgument('lang', null, '目标语言: en|ja|ko')
            ->addOption('force', 'f', null, '强制重新翻译，即使已存在翻译');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action') ?: 'complete';
        $id = $input->getArgument('id') ?: 12;
        $lang = $input->getArgument('lang') ?: 'en';
        $force = $input->getOption('force');
        
        $output->writeln("开始多语言翻译...");
        $output->writeln("操作: {$action}, ID: {$id}, 目标语言: {$lang}");
        if ($force) {
            $output->writeln("模式: 强制重新翻译");
        }
        
        $bdfy = new Bdfy();
        
        switch ($action) {
            case 'article':
                $this->translateArticle($id, $lang, $bdfy, $output, $force);
                break;
            case 'test':
                $this->translateTest($id, $lang, $bdfy, $output, $force);
                break;
            case 'questions':
                $this->translateTestQuestions($id, $lang, $bdfy, $output, $force);
                break;
            case 'answers':
                $this->translateTestAnswers($id, $lang, $bdfy, $output, $force);
                break;
            case 'complete':
                $this->translateComplete($id, $lang, $bdfy, $output, $force);
                break;
            default:
                $output->writeln("未知操作类型: {$action}");
                $output->writeln("可用操作: article, test, questions, answers, complete");
        }
    }
    
    /**
     * 翻译文章
     */
    private function translateArticle($articleId, $targetLang, $bdfy, $output, $force = false)
    {
        $output->writeln("=== 翻译文章 ID: {$articleId} ===");
        
        // 获取中文文章内容（从多语言表获取）
        $article = Db::table('fa_cms_archives_content')
            ->where('archives_id', $articleId)
            ->where('lang', 'zh')
            ->find();
        
        if (!$article) {
            // 如果多语言表中没有中文版本，尝试从原始表获取
            $article = Db::table('fa_cms_archives')
                ->alias('a')
                ->join('fa_cms_addonnews n', 'a.id = n.id', 'LEFT')
                ->join('fa_cms_addonproduct p', 'a.id = p.id', 'LEFT')
                ->where('a.id', $articleId)
                ->field('a.*, COALESCE(n.content, p.content, "") as content')
                ->find();
        }
        
        if (!$article) {
            $output->writeln("文章不存在");
            return false;
        }
        
        // 检查是否已存在该语言版本
        $exists = Db::table('fa_cms_archives_content')
            ->where('archives_id', $articleId)
            ->where('lang', $targetLang)
            ->find();
        
        $isUpdate = false;
        if ($exists) {
            $output->writeln("该语言版本已存在，检查缺失字段...");
            $isUpdate = true;
        }
        
        // 翻译各个字段（只翻译缺失的字段）
        $translatedData = [];
        
        // 检查并翻译标题
        if (!$isUpdate || empty($exists['title'])) {
            $output->writeln("正在翻译标题...");
            $translatedTitle = $this->translateText($article['title'], $targetLang, $bdfy);
            $translatedData['title'] = $translatedTitle;
        } else {
            $translatedData['title'] = $exists['title'];
        }
        
        // 检查并翻译内容
        if (!$isUpdate || empty($exists['content'])) {
            $output->writeln("正在翻译内容...");
            $translatedContent = $this->translateText($article['content'], $targetLang, $bdfy);
            $translatedData['content'] = $translatedContent;
        } else {
            $translatedData['content'] = $exists['content'];
        }
        
        // 检查并翻译副标题
        if (!$isUpdate || empty($exists['sub_title'])) {
            $output->writeln("正在翻译副标题...");
            $translatedSubTitle = $this->translateText($article['sub_title'] ?? '', $targetLang, $bdfy);
            $translatedData['sub_title'] = $translatedSubTitle;
        } else {
            $translatedData['sub_title'] = $exists['sub_title'];
        }
        
        // 检查并翻译问题
        if (!$isUpdate || empty($exists['question'])) {
            $output->writeln("正在翻译问题...");
            $translatedQuestion = $this->translateText($article['question'] ?? '', $targetLang, $bdfy);
            $translatedData['question'] = $translatedQuestion;
        } else {
            $translatedData['question'] = $exists['question'];
        }
        
        // 检查并翻译SEO标题
        if (!$isUpdate || empty($exists['seo_title'])) {
            $output->writeln("正在翻译SEO标题...");
            $translatedSeoTitle = $this->translateText($article['seo_title'] ?? '', $targetLang, $bdfy);
            $translatedData['seo_title'] = $translatedSeoTitle;
        } else {
            $translatedData['seo_title'] = $exists['seo_title'];
        }
        
        // 检查并翻译关键词
        if (!$isUpdate || empty($exists['keywords'])) {
            $output->writeln("正在翻译关键词...");
            $translatedKeywords = $this->translateText($article['keywords'] ?? '', $targetLang, $bdfy);
            $translatedData['keywords'] = $translatedKeywords;
        } else {
            $translatedData['keywords'] = $exists['keywords'];
        }
        
        // 检查并翻译描述
        if (!$isUpdate || empty($exists['description'])) {
            $output->writeln("正在翻译描述...");
            $translatedDescription = $this->translateText($article['description'] ?? '', $targetLang, $bdfy);
            $translatedData['description'] = $translatedDescription;
        } else {
            $translatedData['description'] = $exists['description'];
        }
        
        // 添加其他必要字段
        $translatedData['archives_id'] = $articleId;
        $translatedData['lang'] = $targetLang;
        $translatedData['updated_at'] = date('Y-m-d H:i:s');
        
        if ($isUpdate) {
            // 更新现有记录
            $translatedData['id'] = $exists['id'];
            $result = Db::table('fa_cms_archives_content')->update($translatedData);
            $output->writeln("✓ 文章翻译更新完成");
        } else {
            // 插入新记录
            $translatedData['created_at'] = date('Y-m-d H:i:s');
            $result = Db::table('fa_cms_archives_content')->insert($translatedData);
            $output->writeln("✓ 文章翻译完成");
        }
        
        if ($result) {
            $output->writeln("  原标题: {$article['title']}");
            $output->writeln("  翻译标题: {$translatedData['title']}");
            return true;
        } else {
            $output->writeln("✗ 文章翻译失败");
            return false;
        }
    }
    
    /**
     * 翻译测试
     */
    private function translateTest($testId, $targetLang, $bdfy, $output, $force = false)
    {
        $output->writeln("=== 翻译测试内容 ID: {$testId} ===");
        
        // 检查是否已存在该语言版本的测试内容
        $exists = Db::table('fa_psychometry_test_content')
            ->where('test_id', $testId)
            ->where('lang', $targetLang)
            ->find();
        
        $isUpdate = false;
        if ($exists) {
            $output->writeln("测试内容该语言版本已存在，检查缺失字段...");
            $isUpdate = true;
        }
        
        // 获取中文测试内容
        $testContent = Db::table('fa_psychometry_test_content')
            ->where('test_id', $testId)
            ->where('lang', 'zh')
            ->find();
        
        if (!$testContent) {
            $output->writeln("未找到中文测试内容");
            return false;
        }
        
        // 如果已存在且内容完整，跳过翻译
        if ($isUpdate && !empty($exists['title']) && !empty($exists['description']) && !empty($exists['intro'])) {
            $output->writeln("✓ 测试内容已存在且完整，跳过翻译");
            return true;
        }
        
        // 翻译测试内容（只翻译缺失的字段）
        $translatedData = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (!$isUpdate || empty($exists['title'])) {
            $output->writeln("正在翻译测试标题...");
            $translatedData['title'] = $this->translateText($testContent['title'], $targetLang, $bdfy);
        }
        
        if (!$isUpdate || empty($exists['description'])) {
            $output->writeln("正在翻译测试描述...");
            $translatedData['description'] = $this->translateText($testContent['description'], $targetLang, $bdfy);
        }
        
        if (!$isUpdate || empty($exists['intro'])) {
            $output->writeln("正在翻译测试介绍...");
            $translatedData['intro'] = $this->translateText($testContent['intro'], $targetLang, $bdfy);
        }
        
        if (!$isUpdate || empty($exists['seo_title'])) {
            $output->writeln("正在翻译SEO标题...");
            $translatedData['seo_title'] = $this->translateText($testContent['seo_title'], $targetLang, $bdfy);
        }
        
        if (!$isUpdate) {
            $translatedData['test_id'] = $testId;
            $translatedData['lang'] = $targetLang;
            $translatedData['keywords'] = $testContent['keywords'];
            $translatedData['created_at'] = date('Y-m-d H:i:s');
        }
        
        if ($isUpdate) {
            // 更新现有记录
            $result = Db::table('fa_psychometry_test_content')
                ->where('test_id', $testId)
                ->where('lang', $targetLang)
                ->update($translatedData);
            $output->writeln("✓ 测试内容更新完成");
        } else {
            // 插入新记录
            $result = Db::table('fa_psychometry_test_content')->insert($translatedData);
            $output->writeln("✓ 测试内容翻译完成");
        }
        
        if ($result) {
            $output->writeln("  原测试标题: {$testContent['title']}");
            return true;
        } else {
            $output->writeln("✗ 测试内容翻译失败");
            return false;
        }
    }
    
    /**
     * 翻译测试题目
     */
    private function translateTestQuestions($testId, $targetLang, $bdfy, $output, $force = false)
    {
        $output->writeln("=== 翻译测试题目 ID: {$testId} ===");
        
        // 获取测试的所有题目
        $questions = Db::table('fa_psychometry_question')
            ->where('test_id', $testId)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select();
        
        if (empty($questions)) {
            $output->writeln("未找到题目");
            return 0;
        }
        
        $output->writeln("找到 " . count($questions) . " 个题目");
        $translatedCount = 0;
        
        foreach ($questions as $index => $question) {
            $questionNum = $index + 1;
            $output->writeln("\n--- 题目 {$questionNum}/{" . count($questions) . "} (ID: {$question['id']}) ---");
            
            // 检查是否已存在该语言版本
            $exists = Db::table('fa_psychometry_question_content')
                ->where('question_id', $question['id'])
                ->where('lang', $targetLang)
                ->find();
            
            // 获取中文题目内容
            $questionContent = Db::table('fa_psychometry_question_content')
                ->where('question_id', $question['id'])
                ->where('lang', 'zh')
                ->find();
            
            if (!$questionContent) {
                $output->writeln("题目 {$question['id']} 没有中文内容，跳过");
                continue;
            }
            
            // 如果已存在且内容完整，跳过
            if ($exists && !empty($exists['question_text'])) {
                $output->writeln("✓ 题目 {$question['id']} 已翻译，跳过");
                
                // 但仍然翻译选项（如果是选择题）
                if (in_array($question['question_type'], ['single', 'multiple'])) {
                    $optionsTranslated = $this->translateQuestionOptions($question['id'], $targetLang, $bdfy, $output);
                    if ($optionsTranslated > 0) {
                        $output->writeln("✓ 翻译了 {$optionsTranslated} 个选项");
                    }
                }
                continue;
            }
            
            // 翻译题目文本
            $output->writeln("正在翻译题目文本...");
            $translatedText = $this->translateText($questionContent['question_text'], $targetLang, $bdfy);
            $output->writeln("原文: {$questionContent['question_text']}");
            $output->writeln("译文: {$translatedText}");
            
            // 翻译题目提示（如果有）
            $translatedHint = '';
            if (!empty($questionContent['question_hint'])) {
                $output->writeln("正在翻译题目提示...");
                $translatedHint = $this->translateText($questionContent['question_hint'], $targetLang, $bdfy);
            }
            
            // 准备数据
            $translatedData = [
                'question_id' => $question['id'],
                'lang' => $targetLang,
                'question_text' => $translatedText,
                'question_media' => $questionContent['question_media'],
                'question_hint' => $translatedHint,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($exists) {
                // 更新现有记录
                $result = Db::table('fa_psychometry_question_content')
                    ->where('question_id', $question['id'])
                    ->where('lang', $targetLang)
                    ->update($translatedData);
                $output->writeln("✓ 题目 {$question['id']} 翻译更新完成");
            } else {
                // 插入新记录
                $translatedData['created_at'] = date('Y-m-d H:i:s');
                $result = Db::table('fa_psychometry_question_content')->insert($translatedData);
                $output->writeln("✓ 题目 {$question['id']} 翻译完成");
            }
            
            if ($result) {
                $translatedCount++;
            }
            
            // 翻译选项（如果是选择题）
            if (in_array($question['question_type'], ['single', 'multiple'])) {
                $optionsTranslated = $this->translateQuestionOptions($question['id'], $targetLang, $bdfy, $output);
                $output->writeln("✓ 翻译了 {$optionsTranslated} 个选项");
            }
            
            // 防止请求过快
            usleep(500000); // 0.5秒
        }
        
        $output->writeln("\n✓ 共翻译了 {$translatedCount} 个题目");
        return $translatedCount;
    }
    
    /**
     * 翻译题目选项
     */
    private function translateQuestionOptions($questionId, $targetLang, $bdfy, $output)
    {
        // 获取中文选项
        $zhOptions = Db::table('fa_psychometry_option_content')
            ->where('question_id', $questionId)
            ->where('lang', 'zh')
            ->select();
        
        if (empty($zhOptions)) {
            return 0;
        }
        
        $translatedCount = 0;
        
        foreach ($zhOptions as $option) {
            // 检查是否已存在该语言版本
            $exists = Db::table('fa_psychometry_option_content')
                ->where('question_id', $questionId)
                ->where('option_key', $option['option_key'])
                ->where('lang', $targetLang)
                ->find();
            
            // 如果已存在且有内容，跳过
            if ($exists && !empty($exists['option_text'])) {
                continue;
            }
            
            // 翻译选项文本
            $translatedText = $this->translateText($option['option_text'], $targetLang, $bdfy);
            
            // 翻译选项描述（如果有）
            $translatedDescription = '';
            if (!empty($option['option_description'])) {
                $translatedDescription = $this->translateText($option['option_description'], $targetLang, $bdfy);
            }
            
            $optionData = [
                'question_id' => $questionId,
                'option_key' => $option['option_key'],
                'option_text' => $translatedText,
                'option_description' => $translatedDescription,
                'option_media' => $option['option_media'],
                'lang' => $targetLang,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($exists) {
                // 更新
                Db::table('fa_psychometry_option_content')
                    ->where('id', $exists['id'])
                    ->update($optionData);
            } else {
                // 插入
                $optionData['created_at'] = date('Y-m-d H:i:s');
                Db::table('fa_psychometry_option_content')->insert($optionData);
            }
            
            $translatedCount++;
            $output->writeln("选项 {$option['option_key']}: {$option['option_text']} -> {$translatedText}");
        }
        
        return $translatedCount;
    }
    
    /**
     * 翻译测试答案
     */
    private function translateTestAnswers($testId, $targetLang, $bdfy, $output, $force = false)
    {
        $output->writeln("=== 翻译测试答案 ===");
        
        // 获取测试的所有答案
        $answers = Db::table('fa_psychometry_answer')
            ->where('test_id', $testId)
            ->where('status', 1)
            ->select();
        
        $translatedCount = 0;
        
        foreach ($answers as $answer) {
            // 检查是否已存在该语言版本的答案内容
            $exists = Db::table('fa_psychometry_answer_content')
                ->where('answer_id', $answer['id'])
                ->where('lang', $targetLang)
                ->find();
            
            // 获取中文答案内容
            $answerContent = Db::table('fa_psychometry_answer_content')
                ->where('answer_id', $answer['id'])
                ->where('lang', 'zh')
                ->find();
            
            if (!$answerContent) {
                $output->writeln("答案 {$answer['id']} 没有中文内容，跳过");
                continue;
            }
            
            // 输出中文源数据信息
            $output->writeln("\n--- 答案 {$answer['id']} 中文源数据 ---");
            $output->writeln("  标题长度: " . mb_strlen($answerContent['title'] ?? '', 'UTF-8') . " 字符");
            $output->writeln("  内容长度: " . mb_strlen(strip_tags($answerContent['content'] ?? ''), 'UTF-8') . " 字符");
            $output->writeln("  简介长度: " . mb_strlen($answerContent['intro'] ?? '', 'UTF-8') . " 字符");
            
            // 检查是否需要翻译（包括intro字段）
            $needTranslateIntro = !empty($answerContent['intro']) && empty($exists['intro']);
            $needTranslateContent = !$exists || empty($exists['content']);
            $needTranslateTitle = !$exists || empty($exists['title']);
            
            if (!$force && $exists && !empty($exists['title']) && !empty($exists['content']) && !$needTranslateIntro) {
                $output->writeln("✓ 答案 {$answer['id']} 已翻译，跳过");
                continue;
            }
            
            $needTranslate = true;
            if (!$exists) {
                $output->writeln("答案 {$answer['id']} 该语言版本不存在，需要翻译");
            } else if ($force) {
                $output->writeln("答案 {$answer['id']} 强制重新翻译");
            } else if ($needTranslateIntro) {
                $output->writeln("答案 {$answer['id']} intro字段缺失，需要补充翻译");
            } else if ($needTranslateContent) {
                $output->writeln("答案 {$answer['id']} content字段缺失，需要补充翻译");
            } else if ($needTranslateTitle) {
                $output->writeln("答案 {$answer['id']} title字段缺失，需要补充翻译");
            } else {
                $output->writeln("答案 {$answer['id']} 内容不完整，需要翻译");
            }
            
            // 检查中文源数据是否为空
            if (empty($answerContent['content'])) {
                $output->writeln("  ⚠ 警告: 中文 content 字段为空，无法翻译！");
            }
            if (empty($answerContent['title'])) {
                $output->writeln("  ⚠ 警告: 中文 title 字段为空，无法翻译！");
            }
            
            // 翻译答案内容
            $output->writeln("正在翻译答案 {$answer['id']}...");
            $translatedContent = $this->translateText($answerContent['content'], $targetLang, $bdfy);
            $translatedTitle = $this->translateText($answerContent['title'], $targetLang, $bdfy);
            
            $output->writeln("  原标题: " . mb_substr($answerContent['title'], 0, 50));
            $output->writeln("  译标题: " . mb_substr($translatedTitle, 0, 50));
            $output->writeln("  原内容: " . mb_substr(strip_tags($answerContent['content']), 0, 80) . "...");
            $output->writeln("  译内容: " . mb_substr(strip_tags($translatedContent), 0, 80) . "...");
            
            // 翻译intro字段（如果有）
            $translatedIntro = '';
            if (!empty($answerContent['intro'])) {
                $translatedIntro = $this->translateText($answerContent['intro'], $targetLang, $bdfy);
            }
            
            if ($exists) {
                // 更新现有记录
                $updateData = [
                    'content' => $translatedContent,
                    'title' => $translatedTitle,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                if ($translatedIntro) {
                    $updateData['intro'] = $translatedIntro;
                }
                $result = Db::table('fa_psychometry_answer_content')
                    ->where('answer_id', $answer['id'])
                    ->where('lang', $targetLang)
                    ->update($updateData);
            } else {
                // 插入新记录
                $translatedData = [
                    'answer_id' => $answer['id'],
                    'lang' => $targetLang,
                    'content' => $translatedContent,
                    'title' => $translatedTitle,
                    'intro' => $translatedIntro,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $result = Db::table('fa_psychometry_answer_content')->insert($translatedData);
            }
            
            if ($result) {
                $translatedCount++;
                $output->writeln("  ✓ 答案 {$answer['id']} 翻译完成");
            } else {
                $output->writeln("  ✗ 答案 {$answer['id']} 翻译失败");
            }
        }
        
        $output->writeln("✓ 共翻译了 {$translatedCount} 个答案");
        return $translatedCount;
    }
    
    /**
     * 执行完整翻译流程
     */
    private function translateComplete($articleId, $targetLang, $bdfy, $output, $force = false)
    {
        $output->writeln("=== 开始完整翻译流程 ===");
        $output->writeln("文章ID: {$articleId}");
        $output->writeln("目标语言: {$targetLang}");
        $output->writeln("\n" . str_repeat("=", 60));
        
        $startTime = time();
        
        // 1. 翻译文章内容
        $output->writeln("\n步骤 1/4: 翻译文章内容");
        $articleResult = $this->translateArticle($articleId, $targetLang, $bdfy, $output, $force);
        if (!$articleResult) {
            $output->writeln("文章翻译失败，终止流程");
            return false;
        }
        
        // 2. 获取对应的测试ID
        $test = Db::table('fa_psychometry_test')
            ->where('archives_id', $articleId)
            ->find();
        
        if (!$test) {
            $output->writeln("未找到对应的测试，只翻译了文章内容");
            return true;
        }
        
        $output->writeln("找到对应测试 ID: {$test['id']}");
        
        // 3. 翻译测试内容
        $output->writeln("\n步骤 2/4: 翻译测试内容");
        $testResult = $this->translateTest($test['id'], $targetLang, $bdfy, $output, $force);
        
        // 4. 翻译测试题目和选项
        $output->writeln("\n步骤 3/4: 翻译测试题目和选项");
        $questionsResult = $this->translateTestQuestions($test['id'], $targetLang, $bdfy, $output, $force);
        
        // 5. 翻译测试答案解析
        $output->writeln("\n步骤 4/4: 翻译测试答案解析");
        $answersResult = $this->translateTestAnswers($test['id'], $targetLang, $bdfy, $output, $force);
        
        $endTime = time();
        $duration = $endTime - $startTime;
        
        // 翻译完成总结
        $output->writeln("\n" . str_repeat("=", 60));
        $output->writeln("=== 翻译完成总结 ===");
        $output->writeln(str_repeat("=", 60));
        $output->writeln("文章ID: {$articleId}");
        $output->writeln("测试ID: {$test['id']}");
        $output->writeln("目标语言: {$targetLang}");
        $output->writeln(str_repeat("-", 60));
        $output->writeln("文章内容翻译: " . ($articleResult ? "✓ 完成" : "✗ 失败"));
        $output->writeln("测试内容翻译: " . ($testResult ? "✓ 完成" : "✗ 失败"));
        $output->writeln("题目翻译: ✓ {$questionsResult} 个");
        $output->writeln("答案解析翻译: ✓ {$answersResult} 个");
        $output->writeln(str_repeat("-", 60));
        $output->writeln("总耗时: {$duration} 秒");
        $output->writeln(str_repeat("=", 60));
        
        return true;
    }
    
    /**
     * 翻译文本（智能处理HTML）
     */
    private function translateText($text, $targetLang, $bdfy)
    {
        if (empty($text)) {
            return $text;
        }
        
        // 检测是否包含HTML标签
        if (strip_tags($text) != $text) {
            return $this->translateHtmlText($text, $targetLang, $bdfy);
        }
        
        try {
            $result = $bdfy->translate($text, 'zh', $targetLang);
            
            if (isset($result['trans_result']) && !empty($result['trans_result'])) {
                return $result['trans_result'][0]['dst'];
            } else {
                echo "翻译失败: " . json_encode($result) . "\n";
                return $text;
            }
        } catch (Exception $e) {
            echo "翻译异常: " . $e->getMessage() . "\n";
            return $text;
        }
    }
    
    /**
     * 翻译HTML文本（提取纯文本翻译后还原为统一格式的HTML）
     */
    private function translateHtmlText($html, $targetLang, $bdfy)
    {
        // 提取纯文本内容（去掉所有HTML标签）
        $plainText = strip_tags($html);
        
        // 如果没有实际文本内容，直接返回
        if (empty(trim($plainText))) {
            return $html;
        }
        
        // 翻译纯文本
        try {
            $result = $bdfy->translate($plainText, 'zh', $targetLang);
            
            if (isset($result['trans_result']) && !empty($result['trans_result'])) {
                $translatedText = $result['trans_result'][0]['dst'];
                
                // 将翻译后的文本重新格式化为统一的HTML结构
                return $this->formatToStandardHtml($translatedText);
            } else {
                echo "翻译失败: " . json_encode($result) . "\n";
                return $html;
            }
        } catch (Exception $e) {
            echo "HTML翻译异常: " . $e->getMessage() . "\n";
            return $html;
        }
    }
    
    /**
     * 将文本格式化为统一的HTML结构（利于SEO）
     */
    private function formatToStandardHtml($text)
    {
        // 按段落分割（双换行或多个换行）
        $paragraphs = preg_split('/\n\s*\n/', trim($text));
        
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }
            
            // 检测是否是标题（通常标题较短且可能包含特殊标记）
            if (mb_strlen($paragraph, 'UTF-8') < 50 && 
                (preg_match('/^[一二三四五六七八九十\d]+[、\.]/', $paragraph) || 
                 preg_match('/^[\d]+\./', $paragraph))) {
                // 格式化为h3标题
                $html .= '<h3>' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
            } else {
                // 处理段落内的换行
                $lines = explode("\n", $paragraph);
                
                if (count($lines) > 1) {
                    // 多行内容，可能是列表
                    $html .= '<ul>' . "\n";
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line)) {
                            // 去掉列表标记
                            $line = preg_replace('/^[•\-\*]\s*/', '', $line);
                            $html .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
                        }
                    }
                    $html .= '</ul>' . "\n";
                } else {
                    // 单行段落
                    $html .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</p>' . "\n";
                }
            }
        }
        
        return $html;
    }
}
