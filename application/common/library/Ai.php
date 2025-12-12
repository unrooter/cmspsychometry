<?php

namespace app\common\library;

use think\Db;
use think\Exception;

class Ai
{
    const API_KEY = "3fIghQ3Ig9RIgkGQAODZw6cp";
    const SECRET_KEY = "VtfMa6mOgvx8l55x0aVcnDRLm9M1N2ng";
    public function getseo($content='')
    {
        ini_set('memory_limit', '4086M');
        $txt = "I'm an AI language model and I can assist you with SEO optimization for your website. Please provide me with the content you'd like me to summarize into SEO format. Remember to include the title, keywords (up to 10 separated by commas), and description (up to 500 words).";
        return $this->processArticle($txt,strip_tags($content));
    }
    public function getseok($content='')
    {
        ini_set('memory_limit', '4086M');
        $txt = "你是资深站长，帮助我优化网站的SEO。帮我把给你的内容总结总结出关键词（最多10个，用逗号分隔）。以下是我给你的内容：";
        return $this->processArticlek($txt,strip_tags($content));
    }
    public function getseozh($content='')
    {
        ini_set('memory_limit', '4086M');
        $txt = "你是资深站长，帮助我优化网站的SEO。帮我把给你的内容总结成SEO格式的内容。请记住包括标题、关键词（最多10个，用逗号分隔）和描述（最多500字）。以下是我给你的内容：";
        return $this->processArticle($txt,strip_tags($content));
    }
    public function processArticlek($txt='', $content='', $recursionCount = 0) {
        try {
            $messages[0]['role'] = "user";
            $messages[0]['content'] = $txt.$content;
            $con = $this->getarticle($messages);
            $con_arr = json_decode($con, true);
            $text = $con_arr['result'];
            preg_match("/(?:关键词|Keywords)[:：](.*?)(?:\||\n|$)/us", $text, $keywords_matches);
            $keywords = isset($keywords_matches[1]) ? trim($keywords_matches[1]) : '';
            if($keywords){
                $updata = [];
                $updata['keywords'] = mb_substr($keywords, 0, 250);
                return $updata;
            } else {
                if ($recursionCount < 3) { // 设置递归次数限制为5次
                    return $this->processArticle($txt, $content, $recursionCount + 1);
                } else {
                    // 如果递归次数超过限制，返回默认值或错误处理
                    return [
                        'keywords' => '默认关键词',
                    ];
                }
            }
        } catch (Exception $e) {
            // 捕获异常并重新尝试执行
            return $this->processArticlek($txt, $content, $recursionCount);
        }
    }
    public function processArticle($txt='', $content='', $recursionCount = 0) {
        try {
            $messages[0]['role'] = "user";
            $messages[0]['content'] = $txt.$content;
            $con = $this->getarticle($messages);
            $con_arr = json_decode($con, true);
            $text = $con_arr['result'];
            preg_match("/(?:标题|Title)[:：](.*?)(?:\||\n|$)/us", $text, $title_matches);
            preg_match("/(?:关键词|Keywords)[:：](.*?)(?:\||\n|$)/us", $text, $keywords_matches);
            preg_match("/(?:描述|Description)[:：](.*?)(?:\|$|\n|$)/us", $text, $description_matches);

            $title = isset($title_matches[1]) ? trim($title_matches[1]) : '';
            $keywords = isset($keywords_matches[1]) ? trim($keywords_matches[1]) : '';
            $description = isset($description_matches[1]) ? trim($description_matches[1]) : '';
            if(empty($description)){
                $description = mb_substr($content, 0, 200);
            }

            if($title && $keywords && $description){
                $updata = [];
                $updata['seotitle'] = $title;
                $updata['keywords'] = mb_substr($keywords, 0, 250);
                $updata['description'] = mb_substr($description, 0, 500);
                return $updata;
            } else {
                if ($recursionCount < 3) { // 设置递归次数限制为5次
                    return $this->processArticle($txt, $content, $recursionCount + 1);
                } else {
                    // 如果递归次数超过限制，返回默认值或错误处理
                    return [
                        'seotitle' => '默认标题',
                        'keywords' => '默认关键词',
                        'description' => '默认描述'
                    ];
                }
            }
        } catch (Exception $e) {
            // 捕获异常并重新尝试执行
            return $this->processArticle($txt, $content, $recursionCount);
        }
    }

    public function extractContent($text, $delimiter1, $delimiter2, $isEnd = false) {
        $start = 0;
        $end = 0;

        if (strpos($text, $delimiter1) !== false) {
            $start = strpos($text, $delimiter1) + strlen($delimiter1);
            $end = $isEnd ? strlen($text) : strpos($text, "\n", $start);
        }

        if (strpos($text, $delimiter2) !== false) {
            $start = strpos($text, $delimiter2) + strlen($delimiter2);
            $end = $isEnd ? strlen($text) : strpos($text, "\n", $start);
        }

        if ($start > 0) {
            return substr($text, $start, $end - $start);
        }

        return '';
    }
    public function getarticle($messages)
    {
        $curl = curl_init();
        $curlPost['system'] = '';
        $curlPost['messages'] = $messages;
//
//        $url = "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/ai_apaas?access_token={$this->getAccessToken()}";
        $url = "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/ai_apaas?access_token={$this->getAccessToken()}";
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode($curlPost),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
//        $log_data['url'] = $url;
//        $log_data['post_data'] = json_encode($curlPost);
//        $log_data['create_time'] = time();
//        $log_data['response'] = $response;
//        $log_data['aid'] = $aid;
//        $log_data['type'] = $type;
//        $log_res = Db::table("fa_bdai_log")->insert($log_data);
        return $response;
    }
    /**
     * 使用 AK，SK 生成鉴权签名（Access Token）
     * @return string 鉴权签名信息（Access Token）
     */
    private function getAccessToken(){
        $curl = curl_init();
        $postData = array(
            'grant_type' => 'client_credentials',
            'client_id' => self::API_KEY,
            'client_secret' => self::SECRET_KEY
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://aip.baidubce.com/oauth/2.0/token',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($postData)
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $rtn = json_decode($response);
        return $rtn->access_token;
    }
    public function deepseek($messages='',$token='')
    {
        $post_data = [];
        $post_data['messages'] = $messages;
        $post_data['model'] = 'deepseek-chat';
        $post_data['frequency_penalty'] = 0;
        $post_data['max_tokens'] = 2048;
        $post_data['presence_penalty'] = 0;
        $post_data['stream'] = false;
        $post_data['temperature'] = 1;
        $post_data['top_p'] = 1;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.deepseek.com/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>json_encode($post_data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$token
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}