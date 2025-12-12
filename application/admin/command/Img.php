<?php

namespace app\admin\command;

use app\admin\model\cms\Addonprofile;
use app\admin\model\personality\Profile;
use app\api\controller\Index;
use app\common\library\Bdfy;
use app\common\library\Bdy;
use app\common\library\Tool;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Model;
use function GuzzleHttp\Psr7\str;

class Img extends Command
{
    /**
     * 路径和文件名配置
     */
    protected $options = [];

    protected function configure()
    {
        $this->setName('img')
            ->setDescription('img');
    }

    public function fillcontentimg()
    {
        $data1 = Db::table("fa_cms_addonwork")->where(['content'=>['like','%upload%']])->select();
        if($data1){
            foreach ($data1 as $k1 => $v1) {
                var_dump('11=='.$v1['id']);
                $title = Db::table("fa_cms_archives")->where(['id'=>$v1['id']])->value('title');
                $new_content = $this->getsaveimg($v1['content'],$v1['id']);
                $new_content = str_replace('<p>本文'.$title.' 相关信息由白鸟acg整理发布','',$new_content);
                var_dump($new_content);
                Db::table("fa_cms_addonwork")->where(['id'=>$v1['id']])->update(['content' => $new_content,'status' => 2]);
            }
        }
        $data2 = Db::table("fa_cms_addonwork")->where(['work_info'=>['like','%upload%']])->select();
        if($data2){
            foreach ($data2 as $k2 => $v2) {
                var_dump('222=='.$v2['id']);
                $new_work_info = $this->getsaveimg($v2['work_info'],$v2['id']);
                Db::table("fa_cms_addonwork")->where(['id'=>$v2['id']])->update(['work_info' => $new_work_info,'status' => 3]);
            }
        }
        $data3 = Db::table("fa_cms_addonvoice")->where(['content'=>['like','%upload%']])->select();
        if($data3){
            foreach ($data3 as $k3 => $v3) {
                var_dump('333=='.$v3['id']);
                $new_content = $this->getsaveimg($v3['content'],$v3['id']);
                Db::table("fa_cms_addonvoice")->where(['id'=>$v3['id']])->update(['content' => $new_content,'status' => 3]);
            }
        }
        $data4 = Db::table("fa_cms_addonauthor")->where(['content'=>['like','%upload%']])->select();
        if($data4){
            foreach ($data4 as $k4 => $v4) {
                var_dump('444=='.$v4['id']);
                $new_content = $this->getsaveimg($v4['content'],$v4['id']);
                Db::table("fa_cms_addonauthor")->where(['id'=>$v4['id']])->update(['content' => $new_content,'status' => 3]);
            }
        }
        $data5 = Db::table("fa_cms_addonprofile")->where(['content'=>['like','%upload%']])->select();
        if($data5){
            foreach ($data5 as $k5 => $v5) {
                var_dump('555=='.$v5['id']);
                $new_content = $this->getsaveimg($v5['content'],$v5['id']);
                Db::table("fa_cms_addonprofile")->where(['id'=>$v5['id']])->update(['content' => $new_content]);
            }
        }
        $data6 = Db::table("fa_cms_addonprofile")->where(['comment'=>['like','%upload%']])->select();
        if($data6){
            foreach ($data6 as $k6 => $v6) {
                var_dump('666=='.$v6['id']);
                $new_comment = $this->getsaveimg($v6['comment'],$v6['id']);
                Db::table("fa_cms_addonprofile")->where(['id'=>$v6['id']])->update(['comment' => $new_comment]);
            }
        }
    }
    public function getsaveimg($con='',$aid=0)
    {
        $base_url =  'https://www.bnacg.com';
        $pattern1 = "/<img alt=.*?\/>/ism";
        $tool = new Tool();
        $dir_name = 'cimg';
        preg_match_all($pattern1, $con, $match1);
        if(count($match1[0]) > 0){
            foreach ($match1[0] as $km1 => $vm1){
                $atts2 = extract_attrib($vm1);
                $image = isset($atts2 ['src']) ? substr(substr($atts2 ['src'], 1), 0, -1) : '';
                var_dump($image);
                $image_save = $tool->saveImage($base_url.$image,$dir_name,$aid);
                $con = str_replace($image,$image_save,$con);
            }
        }
        return $con;
    }

    public function fillimg()
    {
        $data = Db::table("fa_cms_archives")->where(['a_status'=>0])->select();
        if(empty($data)){
            return false;
        }
        $tool = new Tool();
        $dir_name = 'aimg';
        foreach ($data as $k => $v) {
            var_dump($v['id']);
            var_dump($v['image']);
            $original_arr = explode('/',$v['image']);
            $filename = $original_arr[count($original_arr)-1];
//            if($filename == 'defaultpic.gif'){
//                Db::table("fa_cms_archives")->where(['id'=>$v['id']])->update(['image' => '','a_status' => 1]);
//                continue;
//            }
            $image = $tool->saveImage($v['image'],$dir_name,$v['id']);
            var_dump($image);
            if(Db::table("fa_cms_archives")->where(['id'=>$v['id']])->update(['image' => $image,'a_status' => 1])){
                if($image){
                    $add_img = [];
                    $add_img['img'] = $v['image'];
                    $add_img['filename'] = $image;
                    $add_img['a_id'] = $v['id'];
                    $add_img['r_id'] = $v['r_id'];
                    Db::table("fa_img_download")->insert($add_img);
                }
            }
        }
    }
    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '4086M');
//        $this->fillimg();
        $this->fillcontentimg();
    }
}
