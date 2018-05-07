<?php
/**
 * Created by PhpStorm.
 * User: kky
 * Date: 2018/5/7
 * Time: 16:40
 */


// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

//功能：计算两个时间戳之间相差的日时分秒,相差要大于24小时的签到功能
//$begin_time  开始时间戳
//$end_time 结束时间戳
function timediff($begin_time,$end_time)
{
    if($begin_time < $end_time){
        $starttime = $begin_time;
        $endtime = $end_time;
    }else{
        $starttime = $end_time;
        $endtime = $begin_time;
    }

    //计算天数
    $timediff = $endtime-$starttime;
    $days = intval($timediff/86400);
    //计算小时数
    $remain = $timediff%86400;
    $hours = intval($remain/3600);
    //计算分钟数
    $remain = $remain%3600;
    $mins = intval($remain/60);
    //计算秒数
    $secs = $remain%60;
    $res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
    return $res;
}


//功能：不在同一天的签到功能
//$begin_time  开始时间戳
//$end_time 结束时间戳
function daydiff($begin_time,$end_time)
{
    if($begin_time < $end_time){
        $starttime = $begin_time;
        $endtime = $end_time;
    }else{
        $starttime = $end_time;
        $endtime = $begin_time;
    }

    $start_day = intval($starttime/86400);
    $end_day = intval($endtime/86400);

    if($start_day==$end_day){
        $res=['day'=>0];
        return $res;
    } elseif ($end_day-$start_day==1){
        $res=['day'=>1];
        return $res;
    } else{
        $res=['day'=>999];
        return $res;
    }
}


function wordFilter($forbid, $article)
{
    foreach ($forbid as $v) {
        if (stristr($article, $v)!==FALSE) {
            return 1;
        }
    }

    return 0;
}

function sign($param)
{

    $str = '';
    ksort($param);
//    dump($param);
    foreach ($param as $key => $value) {
        $str = $str . $key . "=" . $value;
    }
    $str = $str . "key=" . "daxuewuyoulorisongdaxuewuyoulori";

    return strtoupper(md5($str));
}

function signWX($param)
{

    $str = '';
    ksort($param);
//    dump($param);
    foreach ($param as $key => $value) {
        $str = $str . $key . "=" . $value."&";
    }
    $str = $str . "key=" . "daxuewuyoulorisongdaxuewuyoulori";

    return strtoupper(md5($str));
}

/**
 * 发起一个post请求到指定接口
 *
 * @param string $api 请求的接口
 * @param array $params post参数
 * @param int $timeout 超时时间
 * @return string 请求结果
 */
function postRequest( $api, array $params = array(), $timeout = 30 ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $api );
    // 以返回的形式接收信息
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    // 设置为POST方式
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params ) );
    // 不验证https证书
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        'Accept: application/json',
    ) );
    // 发送数据
    $response = curl_exec( $ch );
    // 不要忘记释放资源
    curl_close( $ch );
    return $response;
}

//发送post请求file_get_content，返回数组
function send_post($url, $post_data)
{

    //        echo $post_data;
    $postdata = http_build_query($post_data);
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $postdata,
            'timeout' => 15 * 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    //echo "result:".$result."<br />";
    $output_array = json_decode($result, true);
    return $output_array;
}


//发送post请求curl,返回数组
function sendRequestPost($url, $param)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // post数据
    curl_setopt($ch, CURLOPT_POST, 1);
    // post的变量
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    $output = curl_exec($ch);
    curl_close($ch);
    //打印获得的数据
//        print_r($output);
    $output_array = json_decode($output, true);
    return $output_array;

}

//发送get请求curl,返回数组
function sendRequestGet($url)
{

    //初始化
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);//设置为0、1控制是否返回请求头信息
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);//这个是重点。
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    $wx_access_token = curl_exec($curl);
    curl_close($curl);
    $wx_json = json_decode($wx_access_token, TRUE);
    return $wx_json;

}


function checkSmsResponse($response)
{
    switch ($response)
    {
        case 200:
            return '验证成功';
            break;
        case 405:
            return 'AppKey为空';
            break;
        case 406:
            return 'AppKey无效';
            break;
        case 456:
            return '国家代码或手机号码为空';
            break;
        case 457:
            return '手机号码格式错误';
            break;
        case 466:
            return '请求校验的验证码为空';
            break;
        case 467:
            return '请求校验验证码频繁（5分钟内同一个appkey的同一个号码最多只能校验三次）';
            break;
        case 468:
            return '验证码错误';
            break;
        case 474:
            return '没有打开服务端验证开关';
            break;
    }
}

function convertToOwnCode($response)
{
    switch ($response) {
        case 466:
            return '1';
            break;
        case 468:
            return '1';
            break;
        case 457:
            return '1';
            break;
        case 467:
            return '1';
            break;
        case 456:
            return '1';
            break;
    }
}

  function getNonceStr($length = 32)
  {
      $chars = "qweryuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJLZXCVBNM";
      $str ="";
      for ( $i = 0; $i < $length; $i++ )  {
          $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
      }
      return $str;
  }
function downloadImage($url, $filename, $path, $type = 0)
{
    if ($url == '') {
        return false;
    }
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
            $ext = 'jpg';
        }
        $filename = $filename . "." . $ext;
    }

    //下载文件流
    if ($type) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $img = curl_exec($ch);
        curl_close($ch);
    } else {
        ob_start();
        readfile($url);

        $img = ob_get_contents();
        ob_end_clean();
    }
    //保存文件
//        try {
    $fp2 = fopen('./public/static/' . $path . '/' . $filename, 'w');
    fwrite($fp2, $img);
    fclose($fp2);
    return $filename;
    /*} catch (\think\Exception $e) {
        //TODO 异常处理
        return false;
    }*/
}


    function mexplode($str)
    {
        $find = "/[\x{4e00}-\x{9fa5}]/u";
        preg_match_all($find, $str, $m);
        return $m[0];
    }

    function hasstring($source, $target)
    {
        preg_match_all("/$target/sim", $source, $strResult, PREG_PATTERN_ORDER);
        return !empty($strResult[0]);
    }

/**
 * 删除目录及目录下所有文件或删除指定文件
 * @param str $path 待删除目录路径
 * @param int $delDir 是否删除目录，1或true删除目录，0或false则只删除文件保留目录（包含子目录）
 * @return bool 返回删除状态
 */
    function delDirAndFile($path, $delDir = FALSE)
    {
        $handle = opendir($path);
        if ($handle) {
            while (false !== ($item = readdir($handle))) {
                if ($item != "." && $item != "..")
                    is_dir("$path/$item") ? delDirAndFile("$path/$item", $delDir) : unlink("$path/$item");
            }
            closedir($handle);
            if ($delDir)
                return rmdir($path);
        } else {
            if (file_exists($path)) {
                return unlink($path);
            } else {
                return FALSE;
            }
        }
    }


    //获取某目录下的所有文件夹名并排序
    function getfilename($dir)
    {
        $i = 0;
//        $dir = ROOT_PATH . "public/uploads";
        if ($dir_handle = @opendir($dir)) {
            while ($filename = readdir($dir_handle)) {
                if ($filename != "." && $filename != "..") {
                    $subFile = $dir . DIRECTORY_SEPARATOR . $filename; //要将源目录及子文件相连
                    if (is_dir($subFile)) {
                        $map[] = basename($subFile);
                        $i = $i + 1;
                    }
                }
            }
        }
        closedir($dir_handle);
        if(!empty($map)){
            sort($map);
            return $map;
        }
        else{
            return $map=0;
        }

    }

    //获取某目录下的所有文件名
    function getimagename($dir)
    {
        $handle = opendir($dir);
        $i = 0;
        while ($file = readdir($handle)) {
            if (($file != ".") and ($file != "..")) {
                //                $temp = explode(".", $file);
                $map[$i] = $file;
                $i = $i + 1;
            }
        }
        closedir($handle);
        if (!empty($map)) {
            return $map;
        } else
            return $map = 0;

    }

/** 删除所有空目录
 * @param String $path 目录路径
 */
    function rm_empty_dir($path){
        if(is_dir($path) && ($handle = opendir($path))!==false){
            while(($file=readdir($handle))!==false){// 遍历文件夹
                if($file!='.' && $file!='..'){
                    $curfile = $path.'/'.$file;// 当前目录
                    if(is_dir($curfile)){// 目录
                        rm_empty_dir($curfile);// 如果是目录则继续遍历
                        if(count(scandir($curfile))==2){//目录为空,=2是因为.和..存在
                            rmdir($curfile);// 删除空目录
                        }
                    }
                }
            }
            closedir($handle);
        }
    }



/**
 * 需求：2018年寒宣二维码处理
 * 功能：php多种方式完美实现下载远程图片保存到本地
 * 参数：文件url,保存文件名称，使用的下载方式
 * 当保存文件名称为空时则使用远程文件原来的名称
 * @param string $url 请求图片的链接
 * @param string $filename 保存的文件名
 * @param int $type 保存图片的类型 0为curl,适用于静态图片,其他为缓冲缓存,适用于动态图片
 * @return string $filename 返回保存的文件名
 */
 function downloadcodeimage($url, $filename, $type = 0)
 {
     if ($url == '') {
         return false;
     }
     $ext = pathinfo($filename, PATHINFO_EXTENSION);
     if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
         $ext = pathinfo($url, PATHINFO_EXTENSION);
         if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
             $ext = 'jpg';
         }
         $filename = $filename . "." . $ext;
     }

     //下载文件流
     if ($type) {
         $ch = curl_init();
         $timeout = 5;
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
         $img = curl_exec($ch);
         curl_close($ch);
     } else {
         ob_start();
         readfile($url);
         $img = ob_get_contents();
         ob_end_clean();
     }

     //保存文件

     $fp2 = fopen('./public/teamcode/' . $filename, 'w');
     fwrite($fp2, $img);
     fclose($fp2);
     return $filename;

 }

  function getDistance($lat1,$lng1,$lat2,$lng2){
      //将角度转为狐度
      $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
      $radLat2 = deg2rad($lat2);
      $radLng1 = deg2rad($lng1);
      $radLng2 = deg2rad($lng2);
      $a = $radLat1 - $radLat2;
      $b = $radLng1 - $radLng2;
      $s = 2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6371;
      return round($s,1);
  }


function downloadwxImage($url, $filename, $type = 0)
{
    if ($url == '') {
        return false;
    }
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif', 'tiff'])) {
            $ext = 'jpg';
        }
        $filename = $filename . "." . $ext;
    }

    //下载文件流
    if ($type) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $img = curl_exec($ch);
        curl_close($ch);
    } else {
        ob_start();
        readfile($url);
        $img = ob_get_contents();
        ob_end_clean();
    }

    //保存文件
//        try {
    $fp2 = fopen('./public/uploads/' . $filename, 'w');
    fwrite($fp2, $img);
    fclose($fp2);
    return $filename;
    /*} catch (\think\Exception $e) {
        //TODO 异常处理
        return false;
    }*/
}






