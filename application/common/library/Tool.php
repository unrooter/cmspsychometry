<?php

namespace app\common\library;

use app\admin\model\user\Answer;
use think\Db;
use think\Exception;

class Tool
{
    public function saveImage($img_url='',$dir_name='aimg',$aid=0)
    {
        $dir_name = '/'.$dir_name.'/';
        if (!is_dir(ROOT_PATH  . 'public'.$dir_name)){
            @mkdir('public'.$dir_name, 0755, true);
        }
        $original_arr = explode('/',$img_url);
        if($aid > 0){
            $filename = $aid.'_'.$original_arr[count($original_arr)-1];
        }else{
            $filename = $original_arr[count($original_arr)-1];
        }
        $key = $dir_name.$filename;

        $img_res = $this->checkImage($img_url,$key);
        if($img_res){
            return $key;
        }else{
            return false;
        }
    }
    /**
     * @ApiInternal
     */
    public function checkImage($sourceUrl,$key)
    {
        if (file_exists(ROOT_PATH . 'public' . $key)) {
            return true;
        }

        if (empty($sourceUrl)) {
            return false;
        }

        $quality = 85;
        try {
            $imageInfo = getimagesize($sourceUrl);
        } catch (Exception $e) {
            return false; // 无法获取图片信息
        }
        if ($imageInfo === false) {
            return false; // 无法获取图片信息
        }

        $imageType = $imageInfo[2];

        $img = null;
        switch ($imageType) {
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($sourceUrl);
                break;
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($sourceUrl);
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($sourceUrl);
                break;
            case IMAGETYPE_BMP:
                $img = imagecreatefrombmp($sourceUrl);
                break;
            default:
                return false; // 不支持该格式的图片
        }

        list($width, $height) = $imageInfo;
        $maxWidth = 1024;
        $maxHeight = 1024;
        if ($width <= $maxWidth && $height <= $maxHeight) {
            switch ($imageType) {
                case IMAGETYPE_PNG:
                    $img_res = imagepng($img, ROOT_PATH . 'public' . $key, 9);
                    break;
                case IMAGETYPE_JPEG:
                    $img_res = imagejpeg($img, ROOT_PATH . 'public' . $key, $quality);
                    break;
                case IMAGETYPE_GIF:
                    $img_res = imagegif($img, ROOT_PATH . 'public' . $key);
                    break;
                case IMAGETYPE_BMP:
                    $img_res = imagewbmp($img, ROOT_PATH . 'public' . $key);
                    break;
                default:
                    $img_res = false; // 不支持该格式的图片
                    break;
            }
        } else {
            if ($width > $height) {
                $newWidth = $maxWidth;
                $newHeight = intval($height / ($width / $maxWidth));
            } else {
                $newWidth = intval($width / ($height / $maxHeight));
                $newHeight = $maxHeight;
            }
            $tmpImg = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($tmpImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            switch ($imageType) {
                case IMAGETYPE_PNG:
                    $img_res = imagepng($tmpImg, ROOT_PATH . 'public' . $key, 9);
                    break;
                case IMAGETYPE_JPEG:
                    $img_res = imagejpeg($tmpImg, ROOT_PATH . 'public' . $key, $quality);
                    break;
                case IMAGETYPE_GIF:
                    $img_res = imagegif($tmpImg, ROOT_PATH . 'public' . $key);
                    break;
                case IMAGETYPE_BMP:
                    $img_res = imagewbmp($tmpImg, ROOT_PATH . 'public' . $key);
                    break;
                default:
                    $img_res = false; // 不支持该格式的图片
                    break;
            }
            imagedestroy($tmpImg);
        }

        imagedestroy($img);
        return $img_res;
    }
    public function checkAuth($user = [],$a_info = [])
    {
        $ip = request()->ip();
        $check_data = [
            'pay_type' => 0,
            'is_need_code' => 1,
            'price' => $a_info['price'],
            'three_user_id' => $user ? $user['id'] : 0,
            'code' => 1,
            'msg' => 'ok'
        ];
//        if (empty($user)) {
//            $this->handleGuestUser($check_data, $a_info, $ip);
//        } else {
//            $this->handleLoggedInUser($check_data, $a_info, $user, $ip);
//        }
        return $check_data;
    }

    private function handleGuestUser(&$check_data, $a_info, $ip)
    {
        if ($a_info['price'] > 0) {
            $check_data['code'] = 4;
            $check_data['msg'] = '该测试需要付费，请先登录～';
        } else {
            $t_count = Answer::where(['ip' => $ip])->whereTime('create_time', 'today')->count();
            if ($t_count >= 10) {
                $check_data['code'] = 3;
                $check_data['msg'] = '超出免费次数，请登录增加测试次数或明日再来';
            } else {
                $today_count = Answer::where(['ip' => $ip, 'aid' => $a_info['id'], 'pay_type' => ['>', 0]])->whereTime('create_time', 'today')->count();
                if ($today_count > 0) {
                    $check_data['code'] = 0;
                    $check_data['msg'] = '今日已经做过该测试了，不能重复';
                } else {
                    $check_data['pay_type'] = 9;
                }
            }
        }
    }
    private function handleLoggedInUser(&$check_data, $a_info, $user, $ip)
    {
        if ($user['vip'] == 0) {
            $this->handleLevelZeroUser($check_data, $a_info, $user, $ip);
        } else {
            $this->handleLevelNonZeroUser($check_data, $a_info, $user);
        }
    }
    private function handleLevelZeroUser(&$check_data, $a_info, $user, $ip)
    {
        if ($a_info['price'] > 0) {
            $check_data['msg'] = '该测试需要付费查看结果';
        } else {
            $t_count1 = Answer::where(['ip' => $ip])->whereTime('create_time', 'today')->count();
            $t_count2 = Answer::where(['user_id' => $user['id']])->whereTime('create_time', 'today')->count();
            if ($t_count1 + $t_count2 >= 20) {
                $check_data['code'] = 3;
                $check_data['msg'] = '超出免费次数，请升级会员增加测试次数';
            } else {
                $today_count = Answer::where(['user_id' => $user['id'], 'aid' => $a_info['id'], 'pay_type' => ['>', 0]])->whereTime('create_time', 'today')->count();
                if ($today_count > 0) {
                    $check_data['code'] = 0;
                    $check_data['msg'] = '今日已经做过该测试了，不能重复';
                } else {
                    $check_data['pay_type'] = 9;
                }
            }
        }
    }
    private function handleLevelNonZeroUser(&$check_data, $a_info, $user)
    {
        if ($a_info['price'] > 0) {
            $month_count = Answer::where(['user_id' => $user['id'], 'aid' => $a_info['id'], 'pay_type' => ['>', 0]])
                ->whereBetween('create_time', [strtotime('-30 days'), time()])
                ->count();
            if ($month_count > 0) {
                $check_data['code'] = 2;
                $check_data['msg'] = '近30天已做过该题';
            } else {
                $check_data['pay_type'] = 8;
            }
        } else {
            $today_count = Answer::where(['user_id' => $user['id'], 'aid' => $a_info['id'], 'pay_type' => ['>', 0]])->whereTime('create_time', 'today')->count();
            if ($today_count > 0) {
                $check_data['code'] = 0;
                $check_data['msg'] = '今日已经做过该测试了，不能重复';
            } else {
                $check_data['pay_type'] = 9;
            }
        }
    }
}