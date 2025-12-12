<?php

namespace app\api\controller;

use app\admin\model\cms\ProfileWork;
use app\admin\model\personality\Profile;
use app\admin\model\personality\Profilecj;
use app\common\controller\Api;

/**
 */
class Cms extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 更新作品
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/Cms/updateWork)
     * @ApiHeaders  (name=cat_id, type=string, required=true, description="cat_id")
     */
    public function updateWork()
    {
        $post = file_get_contents('php://input');
        if(empty($post)){
            $this->error(__('no data1'));
        }
        $post_data = json_decode($post,true);

        if(empty($post_data) || count($post_data) <= 0){
            $this->error(__('no data2'));
        }
        $v_ids = [];
        foreach($post_data as $k => $v){
            array_push($v_ids,$v['id']);
            $has_info = ProfileWork::where(['id'=>$v['id']])->find();
            $av = $v;
            $av['version'] = 2;
            $av['r_id'] = $v['id'];
            $av['cat_icon'] = $v['subcategory_image_url'];
            $av['status'] = 1;
            if($has_info){
                unset($av['id']);
                ProfileWork::where(['id'=>$v['id']])->update($av);
            }else{
                ProfileWork::insert($av);
            }
        }
        $this->success(__('成功'),$v_ids);
    }

    /**
     * 更新人物资料
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/Cms/updateProfile)
     * @ApiHeaders  (name=cat_id, type=string, required=true, description="cat_id")
     */
    public function updateProfile()
    {
        $post = file_get_contents('php://input');
        if(empty($post)){
            $this->error(__('no data1'));
        }
        $post_data = json_decode($post,true);

        if(empty($post_data) || count($post_data) <= 0){
            $this->error(__('no data2'));
        }

        $v_ids = [];
        foreach($post_data as $k => $v){
            array_push($v_ids,$v['id']);
            $has_info = Profile::where(['id'=>$v['id']])->find();
            $av = $v;
            $av['update_time'] = time();
            if($has_info){
                unset($av['id']);
                if($has_info['mbti_profile'] != $v['mbti_profile']){
                    Profile::where(['id'=>$v['id']])->update($av);
                }
            }else{
                Profile::insert($av);
            }
        }
        $this->success(__('成功'),$v_ids);
    }

}
