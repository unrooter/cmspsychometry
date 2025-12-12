<?php

use think\Env;
use think\Log;

/**
 * 导出excel信息
 * @param string  $titles    导出的表格标题
 * @param string  $keys      需要导出的键名
 * @param array   $data      需要导出的数据
 * @param string  $file_name 导出的文件名称
 */
function export_excel_office($titles = '', $keys = '', $data = [], $file_name = '导出文件' )
{
    $objPHPExcel = get_excel_obj($file_name);
    $y = 1;
    $s = 0;
    $titles_arr = str2arr($titles);
    foreach ($titles_arr as $k => $v) {
        $objPHPExcel->setActiveSheetIndex($s)->setCellValue(string_from_column_index($k). $y, $v);
    }
    $keys_arr = str2arr($keys);

    foreach ($data as $k => $v){
        is_object($v) && $v = $v->toArray();
        foreach ($v as $kk => $vv){
            $num = array_search($kk, $keys_arr);
            false !== $num && $objPHPExcel->setActiveSheetIndex($s)->setCellValue(string_from_column_index($num) . ($y + $k + 1), $vv );
        }
    }

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

    $objWriter->save('php://output'); exit;
}

/**
 * 获取excel
 */
function get_excel_obj($file_name = '导出文件')
{
    set_time_limit(0);
    vendor('phpoffice/phpexcel/Classes/PHPExcel');
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
    header("Content-Type:application/force-download");
    header("Content-Type:application/vnd.ms-execl");
    header("Content-Type:application/octet-stream");
    header("Content-Type:application/download");
    header('Content-Disposition:attachment;filename='.iconv("utf-8", "gb2312", $file_name).'.xls');
    header("Content-Transfer-Encoding:binary");
    return new PHPExcel();
}

/**
 * 读取excel返回数据
 */
function get_excel_data($file_url = '', $start_row = 1, $start_col = 0)
{
    vendor('phpoffice/phpexcel/Classes/PHPExcel');
    $objPHPExcel        = PHPExcel_IOFactory::load($file_url);
    $objWorksheet       = $objPHPExcel->getActiveSheet();
    $highestRow         = $objWorksheet->getHighestDataRow();
    $highestColumn      = $objWorksheet->getHighestDataColumn();
    $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
    $excel_data = [];
    for ($row = $start_row; $row <= $highestRow; $row++){
        for ($col = $start_col; $col < $highestColumnIndex; $col++){
            $excel_data[$row][] =(string)$objWorksheet->getCellByColumnAndRow($col, $row)->getValue();
        }
    }

    return $excel_data;
}

/**
 * 字符串转换为数组，主要用于把分隔符调整到第二个参数
 * @param  string $str  要分割的字符串
 * @param  string $glue 分割符
 * @return array
 */
function str2arr($str, $glue = ',')
{

    return explode($glue, $str);
}

/**
 * 数字转字母
 */
function  string_from_column_index($pColumnIndex = 0)
{
    static $_indexCache = [];
    if (!isset($_indexCache[$pColumnIndex])) {
        if ($pColumnIndex < 26) {
            $_indexCache[$pColumnIndex] = chr(65 + $pColumnIndex);
        } elseif ($pColumnIndex < 702) {
            $_indexCache[$pColumnIndex] = chr(64 + ($pColumnIndex / 26)).chr(65 + $pColumnIndex % 26);
        } else {
            $_indexCache[$pColumnIndex] = chr(64 + (($pColumnIndex - 26) / 676 )).chr(65 + ((($pColumnIndex - 26) % 676) / 26 )).  chr( 65 + $pColumnIndex % 26);
        }
    }
    return $_indexCache[$pColumnIndex];
}


if (!function_exists('sp_get_order_sn')) {
    /**
     * 获取惟一订单号
     * @return string
     */
    function sp_get_order_sn() {
        return date('YmdHis') . substr(implode(null, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
}
if (!function_exists('getRandom')) {
    function getRandom($param) {
        $str = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $key = "";
        for ($i = 0;$i < $param;$i++) {
            $key.= $str[mt_rand(0, 32)];
        }
        return $key;
    }
}
if (!function_exists('get_server_ip')) {
    /*
     * 获取到当前主机IP
     */
    function get_server_ip()
    {
        if (isset($_SERVER['SERVER_NAME'])) {
            return gethostbyname($_SERVER['SERVER_NAME']);
        } else {
            if (isset($_SERVER)) {
                if (isset($_SERVER['SERVER_ADDR'])) {
                    $server_ip = $_SERVER['SERVER_ADDR'];
                } elseif (isset($_SERVER['LOCAL_ADDR'])) {
                    $server_ip = $_SERVER['LOCAL_ADDR'];
                }
            } else {
                $server_ip = getenv('SERVER_ADDR');
            }
            return $server_ip ? $server_ip : '获取不到服务器IP';
        }
    }
}
if (!function_exists('getsign')) {
    function getsign($pars, $appkey) {
        ksort($pars, SORT_STRING);
        $string1 = '';
        foreach ($pars as $k => $v) {
            if($k != 'sign' && $k != 'answer_data' && $k != 'answer_mark'){
                $string1.= "{$k}={$v}&";
            }
        }
        $string1.= "key=" . $appkey;
        return (strtoupper(md5($string1)));
    }
}
if (!function_exists('array2xml')) {
    function array2xml($arr, $level = 1) {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . \app\api\library\array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }
}
if (!function_exists('xmlToArray')) {
    function xmlToArray($xml) {
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }
}

function http_get($url, $params = []) {
    $query_string = "";
    foreach ($params as $k => $v) {
        if (!empty($query_string)) {
            $query_string .= '&';
        }
        if (is_array($v)) {
            $query_string .= $k . '=' . implodeKV(':', '|', $v);
        } else {
            $query_string .= $k . '=' . $v;
        }
    }
    if (strlen($query_string) > 0) {
        $url .= ($url[strlen($url) - 1] == '?' ? '' : '?');
        $url .= $query_string;
    }
    //初始化
    $ch = curl_init();
    //设置选项，包括URL
    var_dump($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    //执行并获取HTML文档内容
    $output = curl_exec($ch);
    //释放curl句柄
    curl_close($ch);
    return $output;
}
if (!function_exists('getRandomNum')) {
    function getRandomNum($param) {
        $str = "0123456789";
        $key = "";
        for ($i = 0;$i < $param;$i++) {
            $key.= $str[mt_rand(0, 9)];
        }
        return sprintf("%0{$param}d", $key);
    }
}
if (!function_exists('ihttp_get')) {
    function ihttp_get($url,$timeout = 10){
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $file_contents = curl_exec($ch);
        curl_close($ch);
        return $file_contents;
    }
}
if (!function_exists('strexists')) {
    function strexists($string, $find) {
        return !(strpos($string, $find) === FALSE);
    }
}
if (!function_exists('ihttps_post')) {
    function ihttps_post($url, $data,$extra=[]) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        if (!empty($extra) && is_array($extra)) {
            $headers = array();
            foreach ($extra as $opt => $value) {
                if (strexists($opt, 'CURLOPT_')) {
                    curl_setopt($curl, constant($opt), $value);
                } elseif (is_numeric($opt)) {
                    curl_setopt($curl, $opt, $value);
                } else {
                    $headers[] = "{$opt}: {$value}";
                }
            }
            if (!empty($headers)) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            }
        }
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            return 'Errno' . curl_error($curl);
        }
        curl_close($curl);
        return $result;
    }
}
if (!function_exists('json_post')) {
    function json_post($url, $data = NULL,$xtoken=''){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if(!$data){
            return 'data is null';
        }
        if(is_array($data)){
            $data = json_encode($data);
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER,array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:' . strlen($data),
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'X-Token: '.$xtoken
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        if ($errorno) {
            return $errorno;
        }
        curl_close($curl);
        return $res;
    }
}
if (!function_exists('add_log')) {
    function add_log($msg,$fname='common',$type='info'){
        Log::init([
            'type' => 'File',
            'path' => Env::get('root_path') .'logs/'.$fname.'-'.date('Ym'),
        ]);
        Log::write($msg,$type);
        Log::close();
    }
}


/*写文件*/
if (!function_exists('common_log')) {
    function common_log($pay_type,$content){
        if(strpos($pay_type,'/')){
            $dir_arr = explode('/',$pay_type);
            $dir_name = $dir_arr[0];
            $dir = $_SERVER['DOCUMENT_ROOT']. '/common_log/'.$dir_name;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        if(is_array($content)){
            $content = json_encode($content,JSON_UNESCAPED_UNICODE);
        }
        $filename = $_SERVER['DOCUMENT_ROOT']. '/common_log/'.$pay_type.date('Y-m-d').'.txt';
        $Ts=fopen($filename,"a+");
        fputs($Ts,"执行日期："."\r\n".date('Y-m-d H:i:s',time()).  ' ' . "\n" .$content."\n");
        fclose($Ts);
    }
}

if (!function_exists('filterEmoji')) {
    function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }
}
function countWords1($str){

    $str=strip_tags($str);

    $str=str_replace(' ','',$str);//过滤空格

    $str=(mb_strlen($str, 'utf8') + strlen($str))/2;

    return $str;

}

/**
 * 返回两个时间的相距时间，*年*月*日*时*分*秒
 * @param int $one_time 时间戳一  大的时间戳
 * @param int $two_time 时间戳二  小的时间戳
 * @param int $return_type 默认值为0，0/不为0则拼接返回，1/*秒，2/*分*秒，3/*时*分*秒/，4/*日*时*分*秒，5/*月*日*时*分*秒，6/*年*月*日*时*分*秒
 * @param array $format_array 格式化字符，例，array('年', '月', '日', '时', '分', '秒')
 * @return String or false
 */
if (!function_exists('getRemainderTime')) {
    function getRemainderTime($a,$b)
    {
        //检查两个日期大小，默认前小后大，如果前大后小则交换位置以保证前小后大
        if(strtotime($a)>strtotime($b)) list($a,$b)=array($b,$a);
        $start  = strtotime($a);
        $stop   = strtotime($b);
        $extend = ($stop-$start)/86400;
        $result['extends'] = $extend;
        if($extend<7){                //如果小于7天直接返回天数
            $result['daily'] = $extend;
        }elseif($extend<=31){        //小于28天则返回周数，由于闰年2月满足了
            if($stop==strtotime($a.'+1 month')){
                $result['monthly'] = 1;
            }else{
                $w = floor($extend/7);
                $d = ($stop-strtotime($a.'+'.$w.' week'))/86400;
                $result['weekly']  = $w;
                $result['daily']   = $d;
            }
        }else{
            $y=    floor($extend/365);
            if($y>=1){                //如果超过一年
                $start = strtotime($a.'+'.$y.'year');
                $a     = date('Y-m-d',$start);
                //判断是否真的已经有了一年了，如果没有的话就开减
                if($start>$stop){
                    $a = date('Y-m-d',strtotime($a.'-1 month'));
                    $m =11;
                    $y--;
                }
                $extend = ($stop-strtotime($a))/86400;
            }
            if(isset($m)){
                $w = floor($extend/7);
                $d = $extend-$w*7;
            }else{
                $m = isset($m)?$m:round($extend/30);
                $stop>=strtotime($a.'+'.$m.'month')?$m:$m--;
                if($stop>=strtotime($a.'+'.$m.'month')){
                    $d=$w=($stop-strtotime($a.'+'.$m.'month'))/86400;
                    $w = floor($w/7);
                    $d = $d-$w*7;
                }
            }
            $result['yearly']  = $y;
            $result['monthly'] = $m;
            $result['weekly']  = $w;
            $result['daily']   = isset($d)?$d:null;
        }
        return array_filter($result);
    }
}
if (!function_exists('getTicketPassword')) {
    function getTicketPassword($o_num=0,$s_num=0) {
        $str1 = "23456789";
        $str2 = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        $key = "";
        $o_count = 0;
        $s_count = 0;
        $param = $o_num + $s_num;
        for ($i = 0;$i < $param;$i++) {
            if($o_count >= $o_num){
                $key.= $str2[mt_rand(0, 23)];
                $s_count++;
            }elseif($s_count >= $s_num){
                $key.= $str1[mt_rand(0, 7)];
                $o_count++;
            }else{
                $rand_num = rand(0,1);
                if($rand_num == 1){
                    $key.= $str1[mt_rand(0, 7)];
                    $o_count++;
                }else{
                    $key.= $str2[mt_rand(0, 23)];
                    $s_count++;
                }
            }
        }
        return $key;
    }
}