<?php

namespace app\api\controller;

use addons\cms\model\Archives;
use app\admin\model\user\Answer;
use app\common\controller\Api;
use think\Cache;
use think\Db;
use function fast\e;

/**
 */
class Sync extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    public function syncseo()
    {
        $data = Db::table("fa_cms_archives")->where(['product_id'=>['>',0]])->select();
        $url = "http://api.nbmgkj.com/api/sync/upseo";
        foreach ($data as $k=>$v){
            if(empty($v['seotitle'])){
                $seotitle = $v['title'];
                $up_data = [];
                $up_data['seotitle'] = $v['title'];
                Db::table("fa_cms_archives")->where(['id'=>$v['id']])->update($up_data);
            }else{
                $seotitle = $v['seotitle'];
            }
            $post_data = [];
            $post_data['product_id'] = $v['product_id'];
            $post_data['seotitle'] = $seotitle;
            $post_data['keywords'] = $v['keywords'];
            $post_data['description'] = $v['description'];
            $res = ihttps_post($url,json_encode($post_data));
            var_dump($res);
        }
    }

    public function addarticle()
    {
        $json = file_get_contents('php://input');
        $post_data = json_decode($json, true);
        $channel_id = $post_data['channel_id'];
        $model_id = $post_data['model_id'];
        $add_data['channel_id'] = $channel_id;
        $add_data['title'] = $post_data['title'];
        $add_data['model_id'] = $model_id;
        $add_data['createtime'] = time();
        $add_data['updatetime'] = time();
        $add_data['publishtime'] = time();
        $add_data['status'] = 'normal';
        $add_data['image'] = $post_data['image'];
        $add_data['description'] = $post_data['seodescription'];
        $add_data['question'] = $post_data['question'];
        $add_data['sub_title'] = $post_data['sub_title'];
        $add_data['question_num'] = $post_data['question_num'];
        $add_data['seotitle'] = $post_data['title'];
        $add_data['keywords'] = $post_data['seokeywords'];
        $add_data['lang'] = $post_data['lang'];
        $add_data['r_table'] = $post_data['r_table'];
        $add_data['r_id'] = $post_data['id'];
        $add_data['product_id'] = $post_data['product_id'];
        $has = Db::table("fa_cms_archives")->where(['r_id' => $post_data['id'],'r_table' => $post_data['r_table']])->find();
        if (empty($has)) {
            $a_id = Db::table("fa_cms_archives")->insertGetId($add_data);
        }else{
            Db::table("fa_cms_archives")->where(['id'=>$has['id']])->update($add_data);
            $a_id = $has['id'];
        }
        if($model_id == 1){
            $addon_table = "fa_cms_addonnews";
        }elseif($model_id == 2){
            $addon_table = "fa_cms_addonproduct";
        }
        $has_addon = Db::table($addon_table)->where(['id' => $a_id])->find();
        $add_data2 = [];
        $add_data2['content'] = trim($post_data['content']);
        if (empty($has_addon)) {
            $add_data2['id'] = $a_id;
            $res = Db::table($addon_table)->insertGetId($add_data2);
        }else{
            $res = Db::table($addon_table)->where(['id' => $a_id])->update($add_data2);
        }
        $r_data['a_id'] = $a_id;
        //清除内容缓存
        rmdirs(CACHE_PATH, false);
        Cache::clear();

        $this->success('请求成功',$r_data);
    }
    public function getchannel()
    {
        $channel_data = Db::table("fa_cms_channel")->where(['model_id'=>['>',0],'type'=>['<>','link']])->select();
        exit(json_encode($channel_data,JSON_UNESCAPED_UNICODE));
    }
}
