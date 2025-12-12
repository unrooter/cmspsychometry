<?php

namespace app\common\library;

class Bdy
{
    /**
     * 配置信息
     * @var array
     */
    private $config = [];

    public function __construct($options = [])
    {
        $this->config = $options;
    }

    public function getAccessToken()
    {
        $bdy_access_token = \think\Cookie::get('bdy_access_token');
        if($bdy_access_token){
            return $bdy_access_token;
        }
        $url = 'https://aip.baidubce.com/oauth/2.0/token';
        $post_data['grant_type']       = 'client_credentials';
        $post_data['client_id']      = $this->config['bdy_client_id'];
        $post_data['client_secret'] = $this->config['bdy_client_secret'];
        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $res = $this->request_post($url, $post_data);

        $res_arr = json_decode($res, true);

        if (isset($res_arr['error'])) {
            return false;
        }else{
            \think\Cookie::set('bdy_access_token', $res_arr['access_token'],$res_arr['expires_in']);
            return $res_arr['access_token'];
        }
    }
    public function translate($msg='',$from="en",$to="zh")
    {
        $bdy_access_token = $this->getAccessToken();
        $url = "https://aip.baidubce.com/rpc/2.0/mt/texttrans/v1?access_token=".$bdy_access_token;
        $bodys = array(
            'from' => $from,
            'to' => $to,
            'q' => $msg
        );
        $res = $this->run_post($url, $bodys);
        return $res;
    }
    /**
     *
     */
    public function checkContent($msg='')
    {
        $bdy_access_token = $this->getAccessToken();
        $url = 'https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined?access_token=' . $bdy_access_token;
        $bodys = array(
            'text' => $msg
        );
        $res = $this->request_post($url, $bodys);
        return $res;
    }
    public function checkImage($img_base64='')
    {

        $bdy_access_token = $this->getAccessToken();
        $url = 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined?access_token=' . $bdy_access_token;
        $bodys = array(
            'image' => $img_base64
        );
        $res = $this->request_post($url, $bodys);
        return $res;
    }

    /**
     * 发起http post请求(REST API), 并获取REST请求的结果
     * @param string $url
     * @param string $param
     * @return - http response body if succeeds, else false.
     */
    public function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        // 初始化curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $postUrl);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // post提交方式
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        // 运行curl
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }
    public function run_post($url = '', $param = '') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>json_encode($param,JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),

        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}