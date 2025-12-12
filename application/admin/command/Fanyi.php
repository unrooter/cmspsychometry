<?php

namespace app\admin\command;

use app\admin\model\personality\Profile;
use app\api\controller\Index;
use app\common\library\Bdfy;
use app\common\library\Bdy;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Model;
use function GuzzleHttp\Psr7\str;

class Fanyi extends Command
{
    /**
     * 路径和文件名配置
     */
    protected $options = [];

    protected function configure()
    {
        $this->setName('fanyi')
            ->setDescription('fanyi');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '1024M');
//        $this->baidufanyi();
        $this->bdyfanyi();
    }
    public function bdyfanyi(){
        $profile_name_data = Db::table("fa_personality_profile")->where(['profile_name'=>''])->select();
        $config['bdy_client_id'] = 'YtgBb993qusgu2xcoo3Fs0Mh';
        $config['bdy_client_secret'] = 'eNsCI4K2lgaOHMb7rch5jNozoNI1qOGQ';
        $bdy = new Bdy($config);
        foreach ($profile_name_data as $k => $v){
            if(empty($v['mbti_profile'])){
                continue;
            }
            $promptfy = json_decode($bdy->translate($v['mbti_profile'],'en','zh'),true);
            var_dump($v['id']);
            $zh_prompt = '';
            foreach ($promptfy['result']['trans_result'] as $kt => $vt){
                $zh_prompt .= $vt['dst'];
            }
            var_dump($zh_prompt);
//            $wiki_description_zh = '';
//            if($v['wiki_description']){
//                $wiki_description_data = json_decode($bdy->translate($v['wiki_description'],'en','zh'),true);
//                foreach ($wiki_description_data['result']['trans_result'] as $kt2 => $vt2){
//                    $wiki_description_zh .= $vt2['dst'];
//                }
//                var_dump($wiki_description_zh);
//            }
            Db::table("fa_personality_profile")->where(['id'=>$v['id']])->update(['profile_name'=>$zh_prompt]);
        }
    }
    public function baidufanyi(){
        $profile_name_data = Db::table("fa_personality_profile")->where(['profile_name'=>''])->select();
        $bdfy = new Bdfy();
        foreach ($profile_name_data as $k => $v){
            $promptfy = $bdfy->translate($v['mbti_profile'],'en','zh');
            var_dump($promptfy);
            exit();
            $zh_prompt = '';
            var_dump($v['id']);
            foreach ($promptfy['trans_result'] as $kt => $vt){
                $zh_prompt .= $vt['dst'];
            }
            var_dump($zh_prompt);
            $wiki_description_zh = '';
            if($v['wiki_description']){
                $wiki_description_data = $bdfy->translate($v['wiki_description'],'en','zh');
                foreach ($wiki_description_data['trans_result'] as $kt2 => $vt2){
                    $wiki_description_zh .= $vt2['dst'];
                }
                var_dump($wiki_description_zh);
            }
            Db::table("fa_personality_profile")->where(['id'=>$v['id']])->update(['profile_name'=>$zh_prompt,'wiki_description_zh'=>$wiki_description_zh]);
        }
    }
}
