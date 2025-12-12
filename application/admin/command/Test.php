<?php

namespace app\admin\command;

use addons\cms\model\Archives;
use app\admin\model\personality\Profile;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use function GuzzleHttp\Psr7\str;

class Test extends Command
{
    /**
     * 路径和文件名配置
     */
    protected $options = [];

    protected function configure()
    {
        $this->setName('test')
            ->setDescription('test');
    }
    protected function execute(Input $input, Output $output)
    {
        $this->fillprice();
    }
    public function fillprice(){
        $data = Archives::select();
        foreach ($data as $k=>$v){
            if($v['question_num'] > 1 && $v['question_num'] < 20){
                $price = 4.9;
            }elseif($v['question_num'] >= 2 && $v['question_num'] < 50){
                $price = 9.9;
            }elseif($v['question_num'] >= 50 && $v['question_num'] < 100){
                $price = 12.9;
            }elseif($v['question_num'] >= 100){
                $price = 19.9;
            }else{
                $price = 0;
            }
            Archives::where(['id'=>$v['id']])->update(['price'=>$price]);
        }
    }
}
