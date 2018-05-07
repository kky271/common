<?php
/**
 * Created by PhpStorm.
 * User: lorisong
 * Date: 16/9/7
 * Time: 上午11:42
 */

namespace app\api\controller;

use app\api\model\BannerModel;
use app\api\model\CollegeModel;
use app\api\model\DialogModel;
use app\api\model\MenuModel;
use app\api\model\NewsModel;
use app\api\model\UserModel;
use app\api\model\Volunteer2018;

use think\Controller;
use think\Db;
use think\Image;
use think\Request;

//secret:58bab760c50e897ca4e3d619c573dc39

define("URL_HEAD", "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxa0cfc6de291d1c83&redirect_uri=");
define("URL_TAIL", "&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect");

//header('Content-type: text/html;charset=UTF-8');

class CmsController extends Controller
{

    //微信端相关接口
//    发送GET请求
    private function sendRequestGet($url)
    {

        //初始化
        $ch = curl_init();

        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        //执行并获取HTML文档内容
        $output = curl_exec($ch);
        //释放curl句柄
        curl_close($ch);
        //打印获得的数据
//        print_r($output);
        $output_array = json_decode($output, true);
        return $output_array;


    }

    // send_get
    public function send_get($url)
    {
        return file_get_contents($url);
    }

    // send_post
    public function send_post($url, $post_data)
    {

//        echo $post_data;
        //$postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $post_data,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        //echo "result:".$result."<br />";
        return $result;
    }

//    发送POST请求
    private function sendRequestPost($url, $param)
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

//    获取access_token
    public function getAccessToken()
    {

        $appid = 'wxa0cfc6de291d1c83';
        $secret = '58bab760c50e897ca4e3d619c573dc39';
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;

        $now = time();
        $lastTime = Db::table('wx_access')->where('id', 1)->value('timestamp');
        if (!$lastTime) {
            //没有获取过
            $data = $this->sendRequestGet($url);
//            if (!$data["errcode"] ){
            $sql_data = ['access_token' => $data['access_token'], 'timestamp' => $now];
            Db::table("wx_access")->insert($sql_data);
            return $data['access_token'];
//            }else{
//                echo $data['errcode'];
//                return 'error';
//            }
        } else {

            if ($now > $lastTime + 1000) {
                //获取过已过期
                $data = $this->sendRequestGet($url);
//                if (!$data["errcode"] ){
                $sql_data = ['access_token' => $data['access_token'], 'timestamp' => $now];
                Db::table("wx_access")->where('id', '1')->update($sql_data);
                return $data['access_token'];
//                }else{
//                    echo $data['errcode'];
//                    return 'error';
//                }
            } else {
                //获取过未过期
                return $access_token = Db::table('wx_access')->where('id', 1)->value('access_token');
            }
        }

    }

    //评价后发送审核消息模板
    public function checkSuccess($dialogid, $url)
    {
        $getAccessToken = $this->getAccessToken();
        $openid = DialogModel::where("id", $dialogid)->value("answerer_openid");


        $data = [
            "first" => ["value" => "您好，审核结果如下"],
            "keyword1" => ["value" => $dialogid],
            "keyword2" => ["value" => '您回答的问题已通审核,回答所得的咨询费已转入您个人的余额当中,可去个人中心查看余额并提款', 'color' => '#173177'],
            "remark" => ["value" => "感谢你积极的回复", 'color' => '#FF2D21']
        ];

        $param = [
            //接收者openid
            'touser' => $openid,
            //模板号
            'template_id' => '-Qi9YYEu21a0AER5jqHqGLw4knf02dBXxOswcNiv2ss',
            'url' => $url,
            'data' => $data
        ];

        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->send_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $getAccessToken, $json_string);

    }

    //其他用户给5角后 分别向志愿者以及今朝的提问用户发送推送消息
    public function checkAnswer($dialogid, $nickname, $answerer_money, $url, $type, $sinNum)
    {
        $getAccessToken = $this->getAccessToken();

        //找到付款人的昵称
        $touser = DialogModel::where('id', $dialogid)->value('answerer_openid');

        $data = [
            "first" => ["value" => "您好，您回答的问题已被人查看,收到一笔奖金", 'color' => '#173177'],

            //收款金额
            "keyword1" => ["value" => $answerer_money . '元'],
            //付款人昵称
            "keyword2" => ["value" => $nickname],
            //付款方式
            "keyword3" => ["value" => $type],
            //收款时间
            "keyword4" => ["value" => date('Y年m月d日', time())],
            //交易单号
            "keyword5" => ["value" => $sinNum],
            "remark" => ["value" => "此金额已累计到个人账户当中\r\n请去个人中心查看", 'color' => '#FF2D21']

        ];


        $param = [
            //接收者openid
            'touser' => $touser,
            //模板号
            'template_id' => '-fvYCkr-akvEXqGGU9KWXPswPINsge0jCNvJeYp8fjg',
            //跳转链接
            'url' => $url,
            //传递的参数
            'data' => $data
        ];


        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->send_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $getAccessToken, $json_string);

    }


    //    获取access_token
    public function getJinzhaoAccessToken()
    {

        $appid = 'wxc4415ed7714d2750';
        $secret = '016397ede2bcd99a82a2410d49041002';
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;

        $now = time();
        $lastTime = Db::table('wx_access')->where('id', 2)->value('timestamp');
        if (!$lastTime) {
            //没有获取过
            $data = $this->sendRequestGet($url);
//            echo "getacc";
//            if (!$data["errcode"] ){
            $sql_data = ['access_token' => $data['access_token'], 'timestamp' => $now, 'id' => 2];
            Db::table("wx_access")->insert($sql_data);
            return $data['access_token'];
//            }else{
//                echo $data['errcode'];
//                return 'error';
//            }
        } else {

            if ($now > $lastTime + 1000) {
                //获取过已过期
                $data = $this->sendRequestGet($url);
//                if (!$data["errcode"] ){
                $sql_data = ['access_token' => $data['access_token'], 'timestamp' => $now];
                Db::table("wx_access")->where('id', '2')->update($sql_data);
                return $data['access_token'];
//                }else{
//                    echo $data['errcode'];
//                    return 'error';
//                }
            } else {
                //获取过未过期
                return $access_token = Db::table('wx_access')->where('id', 2)->value('access_token');
            }
        }

    }

//    创建菜单
    public function setMenu()
    {

        $accessToken = $this->getAccessToken();
        if ($accessToken != 'error') {

            $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $accessToken;
            $param = ["button" => [
//                ["type"=>"view","name"=>"志愿者系统","url"=>URL_HEAD."http://xuegengwang.com/wx_daxuewuyou/page/announce.html".URL_TAIL],
                ["name" => "志愿者系统", "sub_button" => [
                    ["type" => "view", "name" => "志愿者报名", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou_2017/src/html/terms2.html" . URL_TAIL],
                    ["type" => "view", "name" => "线下报名认证", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou_2017/src/html/auth.html" . URL_TAIL],
                    ["type" => "click", "name" => "注册查询", "key" => "queryRegistration"],

//                    ["type" => "view", "name" => "首页", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/announce.html" . URL_TAIL],
//                    ["type" => "view", "name" => "合作", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/cooperation.html" . URL_TAIL],
//                    ["type" => "view", "name" => "队伍", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/team.html" . URL_TAIL],
                ]
                ],
                ["type" => "view", "name" => "首页", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou_2017/src/html/notice.html" . URL_TAIL],

//                ["name" => "寒宣大赛", "sub_button" => [
//                    ["type" => "view", "name" => "比赛说明", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/comp_intro.html" . URL_TAIL],
//                    ["type" => "view", "name" => "排行榜", "url" => URL_HEAD . "http://xuegengwang.com/jinzhao/page/competition.html" . URL_TAIL],
//                    ["type" => "view", "name" => "已回答问题", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/dialoglist.html?type=answered" . URL_TAIL],
//                    ["type" => "view", "name" => "未回答问题", "url" => URL_HEAD . "http://xuegengwang.com/wx_daxuewuyou/page/dialoglist.html?type=noanswer" . URL_TAIL],
//                ]
//                ],

                ["name" => "联系我们", "sub_button" => [
                    ["type" => "click", "name" => "QQ群", "key" => "QQ"],
//                    ["type"=>"view","name"=>"首页","url"=>URL_HEAD."http://xuegengwang.com/wx_daxuewuyou/page/announce.html".URL_TAIL],
//                    ["type"=>"view","name"=>"合作","url"=>URL_HEAD."http://xuegengwang.com/wx_daxuewuyou/page/cooperation.html".URL_TAIL],
                ]
                ],
//                ["name"=>"个人信息","sub_button"=>[
//                    ["type"=>"view","name"=>"个人信息","url"=>URL_HEAD."http://xuegengwang.com/wx_daxuewuyou/page/personalinfo.html".URL_TAIL],
//                    ["type"=>"view","name"=>"测试","url"=>URL_HEAD."http://xuegengwang.com/wx_daxuewuyou/page/announce.html".URL_TAIL]
//                ]
//                ],

            ]];

//            echo json_encode($param);
            $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

            echo $json_string;
            dump($data = $this->sendRequestPost($url, $json_string));
        }
    }

//    public function addWXUser($openid)
//    {
//
//        if (!empty($openid)) {
//
//            UserModel::where('openid', '=', $openid)->select();
//
//            $sql = "select * from think_user where openid = " . "'" . $openid . "'";
//            $result = DB::query($sql);
//
//
////            $result = UserModel::where('openid','=',$openid)->select();
//
//            if (count($result) == 0) {
//
//                $accessToken = $this->getAccessToken();
//                $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $accessToken . "&openid=" . $openid . "&lang=zh_CN";
//                $datauser = $this->sendRequestGet($url);
//
//                if (isset($datauser["error_code"])) {
//
//                    \log::getInstance()->LogMessage($datauser, Log::INFO, "data257");
//
//                    $now = time();
//                    $appid = 'wxa0cfc6de291d1c83';
//                    $secret = '58bab760c50e897ca4e3d619c573dc39';
//                    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
//                    $data_getopen = $this->sendRequestGet($url);
//
//                    \log::getInstance()->LogMessage($data_getopen, Log::INFO, "data_getopen265");
//
//                    $sql_data = ['access_token' => $data_getopen['access_token'], 'timestamp' => $now];
//                    Db::table("wx_access")->where('id', '1')->update($sql_data);
//                    $accessToken = $data_getopen['access_token'];
//
//                    \log::getInstance()->LogMessage($accessToken, Log::INFO, "accesstoken");
//
//                    $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $accessToken . "&openid=" . $openid . "&lang=zh_CN";
//                    $datauser = $this->sendRequestGet($url);
//
//                    \log::getInstance()->LogMessage($datauser, Log::INFO, "datauser274");
//                }
//
//
//                $userModel = new UserModel;
//                $userModel->save($datauser);
//
//                //未关注的时候
//                $userID = UserModel::where('openid', $openid)->value('id');
//                return json_encode(['userid' => $userID]);
//
////                echo json_encode($datauser);
//            } else {
//
//                $accessToken = $this->getAccessToken();
//                $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $accessToken . "&openid=" . $openid . "&lang=zh_CN";
//                $datauser = $this->sendRequestGet($url);
//                UserModel::where('openid', $openid)->update($datauser);
//                //已关注的时候
//                $userID = UserModel::where('openid', $openid)->value('id');
//                return json_encode(['userid' => $userID]);
//            }
//        }
//    }

# 添加用户
    public function addWXUser($openid)  //志愿者新表
    {
        $userID = Volunteer2018::where('openid', $openid)->value('id');

        if (!empty($openid)) {

            $UserId = db('volunteer2018')->where('openid', $openid)->value('id');

            $accessToken = $this->getAccessToken();

            if ($UserId === null) {

                $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $accessToken . "&openid=" . $openid . "&lang=zh_CN";

                $datauser = $this->sendRequestGet($url);

                $Volunteer2018_Model = new Volunteer2018;

                $Volunteer2018_Model->save($datauser);

                //未关注的时候

                return json_encode(['userid' => $userID]);

            } else {    //找不到就返回id

                $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $accessToken . "&openid=" . $openid . "&lang=zh_CN";

                $datauser = $this->sendRequestGet($url);

                Volunteer2018::where('openid', $openid)->update($datauser);

                return json_encode(['userid' => $userID]);

            }
        }
    }

    public function web_addWXUser()
    {
        $this->addWXUser(input('openid'));
    }

    public function getCodeWithTeamID($id)
    {

        $accesstoken = $this->getJinzhaoAccessToken();

        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $accesstoken;

        $param = ["action_name" => "QR_LIMIT_SCENE", "action_info" => ["scene" => ["scene_id" => $id]]];

        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $data = $this->sendRequestPost($url, $json_string);

        return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . urlencode($data["ticket"]);
    }

    //关注带参数二维码
    public function students_activity()
    {
        $time = time();

        //活动号
        $activity_num = input('activity_num');

        //当前用户id openid 昵称 头像
        $id = input('userid');
        $openid = UserModel::where('id', $id)->value('openid');
        $nickname = UserModel::where('id', $id)->value('nickname');
        $headimgurl = UserModel::where('id', $id)->value('headimgurl');
        //获取当前用户的活动号码
        $total_sceneid = UserModel::where("openid", $openid)->value('total_sceneid');

        //获取用户的二维码图片 以及上传的时间
        $qrcode_time = UserModel::where("openid", $openid)->value('qrcode_time');

        //被扫描的用户
        $scene_id = input('scene_id');
        $openided = UserModel::where('id', $scene_id)->value('openid');
        $scene_nickname = UserModel::where('id', $scene_id)->value('nickname');

        //原有被扫码的数量
        $scan_num = UserModel::where("openid", $openided)->value('scan_num');

        $accesstoken = $this->getAccessToken();


        //如果活动号码不为空
        if ($total_sceneid) {
            $arr = explode(",", $total_sceneid);

            //获取二维码图片的创建时间 如果为空 就是没创建完
            $qrcode_time = UserModel::where("openid", $openid)->value('qrcode_time');

            //如果当前的活动号码不在数组里面
            if (!in_array($activity_num, $arr) || $qrcode_time == 0) {
                return;
            }
        }


        // 判断活动id是否已经在total_sceneid里面
        //以及给被扫码人发送消息
        if ($id == $scene_id) {
            $text = '不能扫自己的二维码';
            $this->sendmessage_text($openided, $text);
            return;
        } else {
            //获取当前用户的活动号码
            $total_sceneid = UserModel::where("openid", $openid)->value('total_sceneid');

            //不为空，拆分，添加，再合并
            if ($total_sceneid) {
                $arr = explode(",", $total_sceneid);
                //如果数组里面没有此活动号码那么就添加进去 并且扫码数量加1
                if (!in_array($activity_num, $arr)) {
                    $total_sceneid = $total_sceneid . ',' . $activity_num;

                    db('user')->where("openid", $openid)->update(['total_sceneid' => $total_sceneid]);

                    //扫描人数加一
                    UserModel::where("openid", $openided)->update(['scan_num' => $scan_num + 1]);

                    $num = 3 - UserModel::where("openid", $openided)->value('scan_num');

                    //当前用户发送消息
                    $text = "你已扫描" . $scene_nickname . "分享下方图片到朋友圈或者微信群，至少三人扫码关注后，即可免费领取大礼包及参与师兄师姐线上培训课程\r\n下方二维码正在生成请稍后.............";
                    $this->sendmessage_text($openid, $text);

                    if ($num > 0) {
                        //以及给被扫码人发送消息
                        $text = $nickname . '成功扫描您的二维码，距离完成目标只差' . $num . '个好友的距离，快让更多好友助攻你吧!';
                        $this->sendmessage_text($openided, $text);
                    } else {
                        if ($num == 0) {
                            //以及给被扫码人发送消息
                            $text = '您已成功获取听课资格,请扫下方二维码加群听课';
                            $this->sendmessage_text($openided, $text);

                            //发送三天的二维码
                            //向当前用户发送图片
                            $path = './public/static/teacher/教师统招线上培训.jpg';
                            $size = filesize($path);
                            $this->sendmessage_image($openided, $path, $size);
                            return;
                        }
                    }

                } else {//否则
                    $text = '您已扫描' . $scene_nickname . '的二维码,本次扫码失败(每人只可扫描一次)。分享下方图片到朋友圈或者微信群，至少三人扫码关注后，即可免费听取线上培训课程';
                    $this->sendmessage_text($openid, $text);

                    //发送三天的二维码
                    //向当前用户发送图片
                    $path = './public/static/teacher/' . $id . '.jpg';
                    $size = filesize($path);
                    $this->sendmessage_image($openid, $path, $size);
                    return;
                }
            } else {
                //如果为空那么就不用拆分 直接添加活动号码
                db('user')->where("openid", $openid)->update(['total_sceneid' => $activity_num]);

                //扫描人数加一

                UserModel::where("openid", $openided)->update(['scan_num' => $scan_num + 1]);

                $num = 3 - UserModel::where("openid", $openided)->value('scan_num');
                //当前用户发送消息
                $text = "你已扫描" . $scene_nickname . "分享下方图片到朋友圈或者微信群，至少三人扫码关注后，即可免费领取大礼包及参与师兄师姐线上培训课程\r\n下方二维码正在生成请";
                $this->sendmessage_text($openid, $text);

                if ($num > 0) {
                    //以及给被扫码人发送消息
                    $text = $nickname . '成功扫描您的二维码，距离完成目标只差' . $num . '个好友的距离，快让更多好友助攻你吧!';
                    $this->sendmessage_text($openided, $text);
                } else {
                    if ($num == 0) {
                        //以及给被扫码人发送消息
                        $text = '您已成功获取听课资格,请扫下方二维码加群听课';
                        $this->sendmessage_text($openided, $text);

                        //发送三天的二维码
                        //向当前用户发送图片
                        $path = './public/static/teacher/教师统招线上培训.jpg';
                        $size = filesize($path);
                        $this->sendmessage_image($openided, $path, $size);
                        return;
                    }

                }
            }
        }

        //如果是为空或者大于20天 那么就新生成一张二维码
        if ($qrcode_time == 0 || time() > $qrcode_time + 2000000) {
            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $accesstoken;

            //10为活动号码
            $param = ["expire_seconds" => "2592000", "action_name" => "QR_SCENE", "action_info" => ["scene" => ["scene_id" => $activity_num . $id]]];

            $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

            $data = $this->sendRequestPost($url, $json_string);

            //获取到三十天的二维码
            $imgurl = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . urlencode($data["ticket"]);

            //二维码图片
            //下载获取到的二维码
            $qrcodename = self::downloadImage($imgurl, 'qrcode' . $id . '.jpg');
            //下载二维码图片
            $path_Image = \think\Image::open('./public/static/teacher/' . $qrcodename);
            //压缩二维码图片
            $path_Image->thumb(220, 220)->save('./public/static/teacher/' . $qrcodename);

            //用户的头像
            //下载用户头像
            $headimgname = self::downloadImage($headimgurl, 'head' . $id . '.png');
            //下载二维码图片
            $headimg = \think\Image::open('./public/static/teacher/' . $headimgname);
            //压缩二维码图片
            $headimg->thumb(140, 140)->radius(70, 255, 252, 220)->save('./public/static/teacher/' . $headimgname);

            //活动二维码图片 (需要打上二维码的水印)
            $bgImage = \think\Image::open('./public/static/teacher/二维码扫码页.jpg');

            //活动二维码图片添加水印并保存
            $bgImage->water('./public/static/teacher/' . $headimgname, [56, 1163])->water('./public/static/teacher/' . $qrcodename, [492, 1100])->save('./public/static/teacher/' . $id . '.jpg');

            //删除图片 头像和 二维码
            unlink('./public/static/teacher/' . $qrcodename);
            unlink('./public/static/teacher/' . $headimgname);

            //二维码存到数据库的时间
            UserModel::where("openid", $openid)->update(['qrcode_time' => $time]);

        }
        //发送三天的二维码
        //向当前用户发送图片
        $path = './public/static/teacher/' . $id . '.jpg';
        $size = filesize($path);
        $this->sendmessage_image($openid, $path, $size);
        return;
    }

    //发送客服图片消息
    public function sendmessage_image($openid, $path, $size)
    {
        $access_token = $this->getAccessToken();
        $file_info = array(
            'filename' => $path,   //国片相对于网站根目录的路径
            'content-type' => 'image/jpg',   //文件类型
            'filelength' => $size          //图文大小
        );
        $picture = $this->add_material($file_info);

        $data = ["touser" => $openid, "msgtype" => "image", "image" => ["media_id" => $picture]];
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->send_post($url, $json);


    }

    public function sendmessage_cms()
    {
        $accesstoken = $this->getAccessToken();
        $data = ["touser" => input('openid'), "msgtype" => "text", "text" => ["content" => input('text')]];
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $accesstoken;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->send_post($url, $json);

    }


    //发送客服文本消息
    public function sendmessage_text($openid, $text)
    {
        $accesstoken = $this->getAccessToken();
        $data = ["touser" => $openid, "msgtype" => "text", "text" => ["content" => $text]];
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $accesstoken;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->send_post($url, $json);

    }

    //发送 升级邀请函(免费自愿者升级为付费自愿者)
    public function sendInvitation()
    {
        $getAccessToken = $this->getAccessToken();

        $url_head = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxa0cfc6de291d1c83&redirect_uri=http://xuegengwang.com/wx_daxuewuyou/";
        $url_tail = "&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect";

        //模板参数
        $data = [
            "first" => ["value" => "尊敬的用户，您已获得回答付费咨询的权限。", 'color' => '#FF2D21'],
            "keyword1" => ["value" => '今朝小助手', 'color' => '#173177'],
            "keyword2" => ["value" => '回答付费咨询', 'color' => '#173177'],
            "remark" => ["value" => "点击查看详情，补充个人信息即可成为资深志愿者。", 'color' => '#FF2D21']
        ];

        $param = [
            //接收者openid
            'touser' => input('openid'),
            //模板号
            'template_id' => '9-YQNnebfS0rS82ssjo_kLdCahwgf1bUBQtrodih9gs',
            'url' => $url_head . 'page/Invitation_letter.html' . $url_tail,
            'data' => $data
        ];

        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->send_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $getAccessToken, $json_string);

        //state变成正在邀请的状态
        UserModel::where('openid', input('openid'))->update(['state' => 1]);
    }

    //获取素材
    function add_material($file_info)
    {

        $access_token = $this->getAccessToken();

        $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=$access_token&type=image";
        $ch1 = curl_init();
        $timeout = 5;

        $data = array("media" => new \CURLFile(realpath($file_info['filename'])));

//        $data = json_encode($data,JSON_UNESCAPED_UNICODE);

        dump($data);
        dump($url);
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_POST, 1);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch1);
        curl_close($ch1);

        $result = json_decode($result, true);
        var_dump($result);
        //var_dump($result);
        return $result['media_id'];
    }

    # 为用户打微信tagid标签
    public function makeTag()
    {
        /**
         * 先打便签
         * https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?access_token=ACCESS_TOKEN
         */
        $array = input('openidArray');
        $tagid = input('collegeid');
        $accessToken = $this->getAccessToken();

        $url = "https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?access_token=" . $accessToken;

        $param = [
            'openid_list' => $array,
            'tagid' => $tagid
        ];

        $jsonstring = json_encode($param, JSON_UNESCAPED_UNICODE);
        $request = sendRequestPost($url, $jsonstring);

        return json($request);

    }


    # 群发图文消息
    # 发送群发图文消息前必须将用户打标签
    # 缺点每个用户每个月只能接受4次群发推送 所以最好用模版消息
    public function sendMessagAll()
    {
        /**
         * @微信群发接口 根据OpenID列表群发 https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=ACCESS_TOKEN
         * {
         * "touser":[
         * "OPENID1",
         * "OPENID2"
         * ],
         * "mpnews":{
         * "media_id":"123dsdajkasd231jhksad"
         * },
         * "msgtype":"mpnews"，
         * "send_ignore_reprint":0
         * }
         *  参数名      必填        说明
         * touser        是    填写图文消息的接收者，一串OpenID列表，OpenID最少2个，最多10000个
         * mpnews        是    用于设定即将发送的图文消息
         * media_id        是    用于群发的消息的media_id
         * msgtype        是    群发的消息类型，图文消息为mpnews，文本消息为text，语音为voice，音乐为music，图片为image，视频为video，卡券为wxcard
         * title        否    消息的标题
         * description    否    消息的描述
         * send_ignore_reprint    是 图文消息被判定为转载时，是否继续群发。1为继续群发（转载），0为停止群发。该参数默认为0。
         */

        $array = json_decode(input('openidArray'), true);
        $media_id = '4U9SI0BseXhvzLEPnjh6glCmIFnaQtvvQF2nQasWlFA';
        $msgtype = input('type');
        $title = input('title');
        $description = input('description');
        $send_ignore_reprint = 0;
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=' . $access_token;
        $param = [
            'touser' => $array,
            'mpnews' => [
                'media_id' => $media_id,
                'title' => $title,
                'description' => $description
            ],
            'msgtype' => $msgtype,
            'send_ignore_reprint' => $send_ignore_reprint
        ];
        $jsonstring = json_encode($param, JSON_UNESCAPED_UNICODE);

        $request = sendRequestPost($url, $jsonstring);
        return json($request);
    }

    # 群发文本
    public function sendMessagAll_text()
    {
        $array_2017 = db('user')->where('college_id', 1)->field('openid')->select();
        $array_2018 = db('volunteer2018')->where('college_id', 1)->field('openid')->select();

        $allArray = array_merge($array_2017, $array_2018);

        $array = [];

        foreach ($allArray as $k => $v) {
            array_push($array, $v['openid']);
        }

        $content = input('content');

        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=' . $access_token;
        $param = [
            'touser' => $array,
            'msgtype' => 'text',
            'text' => [
                "content" => $content
            ]
        ];
        $jsonstring = json_encode($param, JSON_UNESCAPED_UNICODE);

        $request = sendRequestPost($url, $jsonstring);
        return json($request);
    }

    public function ask($title, $touser, $fromuser, $dialogid, $price, $type = 'web')
    {

        $access_token = $this->getAccessToken();
        $requesturl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;

        $template_id = 'ev2yzibcqNEqZ7YFFc6v6UFTPDWSOOo7eGz-zT5U0-o';
        $url = "http://xuegengwang.com/wx_daxuewuyou/page/answer.html?dialogid=" . $dialogid . "&price=" . $price . "&type=" . $type;
        $data = ["first" => ["value" => "您收到一条咨询留言", "color" => "#173177"],
            "user" => ["value" => $fromuser, "color" => "#173177"],
            "ask" => ["value" => $title, "color" => "#173177"],
            "remark" => ["value" => "回复问题请点击此处", "color" => "#173177"]
        ];

//        $data = {"first":{"value":"您收到一条咨询留言","color":"#173177"},"user":{"value":"测试用户","color":"#173177"},
//             "ask":{"value":"我想问一下","color":"#173177"},"remark":{"value":"回复问题请点击此处","color":"#173177"}};

        $param = ["touser" => $touser, "template_id" => $template_id, "url" => $url, "data" => $data];
//                $i=0;
//        $string='';
//        foreach ($param as $k =>$v) {
//            if ($i==0){
//                $string= $k.'='.$v."\n";
//            }
//                else{
//                $string =$string.'&'.$k.'='.$v."\n";
//                }
//                $i++;
//        }
//        db('dialog')->where('id',1396)->update(['answer'=> strval($string)]);
        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);


        $this->sendRequestPost($requesturl, $json_string);

    }

    public static function downloadImage($url, $filename, $type = 0)
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
        $fp2 = fopen('./public/static/teacher/' . $filename, 'w');
        fwrite($fp2, $img);
        fclose($fp2);
        return $filename;
        /*} catch (\think\Exception $e) {
            //TODO 异常处理
            return false;
        }*/
    }


    # wx长链接转加密的短链接
    public function shorturl($longurl = '')
    {
        $getAccessToken = $this->getAccessToken();

        if ($longurl === '') {
            $longurl = input('longurl');
        }

        $param = [
            'action' => 'long2short',
            'long_url' => $longurl
        ];
        $url = "https://api.weixin.qq.com/cgi-bin/shorturl?access_token=" . $getAccessToken;
        $jsonstring = json_encode($param, JSON_UNESCAPED_UNICODE);

        $request = sendRequestPost($url, $jsonstring);
        return json($request);
    }

    # 各大高校管理人员 发送通知公告后 需要调用的接口
    public function sendNoticeMessage()
    {
        $getAccessToken = $this->getAccessToken();

        $college_id=input('college_id');
        $type=input('type');
        $collegename = db('college')->where('id',$college_id)->value('school_name');
        //发送公告对象：0：跟进干事；1：队长；2：队员；3：跟进干事和队长；4：跟进干事和队员；5：队长和队员；6：全部；

//        $sendmsgopenid=[
//            '0'=>'oUoIwxL0yCIJzLTUMWkqjva-Lp3o',
//            '1'=>'oUoIwxEUAtdFNtys-jTSnM7Uy77k'
//        ];

        if ($type == 0) {
            $sendmsgopenid = db('director')->where('college_id', $college_id)->column('openid');
        } elseif ($type == 1) {
            $sendmsgopenid = db('volunteer2018')->where('college_id', $college_id)->where('iscaptain', 1)->where('isdelete', 2)->column('openid');
        } elseif ($type == 2) {
            $sendmsgopenid = db('volunteer2018')->where('college_id', $college_id)->where('iscaptain', 0)->where('isdelete', 2)->column('openid');
        } elseif ($type == 3) {
            $sendmsgopenid1 = db('director')->where('college_id', $college_id)->column('openid');
            $sendmsgopenid2 = db('volunteer2018')->where('college_id', $college_id)->where('iscaptain', 1)->where('isdelete', 2)->column('openid');
            $sendmsgopenid = $sendmsgopenid1 + $sendmsgopenid2;
        } elseif ($type == 4) {
            $sendmsgopenid1 = db('director')->where('college_id', $college_id)->column('openid');
            $sendmsgopenid2 = db('volunteer2018')->where('college_id', $college_id)->where('iscaptain', 0)->where('isdelete', 2)->column('openid');
            $sendmsgopenid = $sendmsgopenid1 + $sendmsgopenid2;
        } elseif ($type == 5) {
            $sendmsgopenid = db('volunteer2018')->where('college_id', $college_id)->where('isdelete', 2)->column('openid');
        } elseif ($type == 6) {
            $sendmsgopenid1 = db('director')->where('college_id', $college_id)->column('openid');
            $sendmsgopenid2 = db('volunteer2018')->where('college_id', $college_id)->where('isdelete', 2)->column('openid');
            $sendmsgopenid = $sendmsgopenid1 + $sendmsgopenid2;
        }
        //$sendmsgopenid=db('volunteer2018')->where('college_id',$college_id)->where('register',1)->where('isdelete',2)->column('openid');

        //dump($sendmsgopenid);exit;

        if(!empty($sendmsgopenid)){
            foreach ($sendmsgopenid as $k =>$v){
                $text = "<a href='https://w.url.cn/s/AWJObhe'>您好, $collegename 管理员发布了一条公告,请点解链接查看。</a> ";
                $data = ["touser" => $sendmsgopenid[$k], "msgtype" => "text", "text" => ["content" => $text]];
                $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $getAccessToken;
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                $this->send_post($url, $json);
            }
        }
    }

    # 查询注册情况
    public function queryRegistration()
    {
//        $getAccessToken = $this->getAccessToken();

        $openid = input('openid');
        db('wxtext')->where('id', 2)->update(['test' => $openid]);
        $params = db('volunteer2018')->where('openid', $openid)->field('realname,college,department,tel,qq,wx,email,grade,school,school_prov,school_dist,school_city,major,captain,question1,question2,question3,question4,question5,register')->find();

        $college = db('college')->where('school_name', $params['college'])->value('questionlist');

        $questionList = explode(';', $college);

        if ($params['captain'] == 0) {
            $params['captain'] = '否';
        } else {
            $params['captain'] = '是';
        }
        if ($params['register'] == 1) {
            $text = '您已经注册成功,所填写的注册信息如下:' .
                "\r\n" . '姓名:' . $params['realname'] .
                "\r\n" . '学校:' . $params['college'] .
                "\r\n" . '手机号码:' . $params['tel'] .
                "\r\n" . 'QQ号码:' . $params['qq'] .
                "\r\n" . '微信:' . $params['wx'] .
                "\r\n" . '邮箱:' . $params['email'] .
                "\r\n" . '年级:' . $params['grade'] .
                "\r\n" . '目标高中省份:' . $params['school_prov'] .
                "\r\n" . '目标高中市区:' . $params['school_city'] .
                "\r\n" . '目标高中县区:' . $params['school_dist'] .
                "\r\n" . '学院:' . $params['department'] .
                "\r\n" . '专业:' . $params['major'] .
                "\r\n" . '目标高中:' . $params['school'] .
                "\r\n" . '是否申请做队长:' . $params['captain'];

            if ($params['college'] != '广东外语外贸大学') {
                $text .= "\r\n如需修改信息请添加我们的QQ群:466783183";
            }
            $this->sendmessage_text($openid, $text);
            $text = '';
            for ($i = 0; $i < count($questionList); $i++) {
                $text .= "\r\n" . $questionList[$i] . "\r\n答案:" . $params['question' . ($i + 1)];
            }

            if (strlen($text) >= 364) {
                $text = '问题的回答信息过长,如需查询,请加QQ群联系管理人员';
            }

            return $this->sendmessage_text($openid, $text);
        } else {
            $text = '暂无您的信息,请注册后再进行查询';
            return $this->sendmessage_text($openid, $text);

        }

    }

    # 调剂模版(发送申请)    给队长发送
    public function adjustApplication($params = [])
    {
        $getAccessToken = $this->getAccessToken();
        if ($params == []) {
            $realname = input('realname');
            $leaderopenid = input('leaderopenid');
        } else {
            $realname = $params['realname'];
            $leaderopenid = $params['leaderopenid'];
        }

        $time = time();

        $url = URL_HEAD . 'http://xuegengwang.com/wx_daxuewuyou_2017/src/html/adjust.html' . URL_TAIL;

        $data = [
            "first" => ["value" => "申请加入队伍"],
            "keyword1" => ["value" => $realname],
            "keyword2" => ["value" => date('Y年m月d日', $time), 'color' => '#173177'],
            "remark" => ["value" => "审核用户（点击进入审核页面）", 'color' => '#FF2D21']
        ];

        $param = [
            //接收者openid
            'touser' => $leaderopenid,
            //模板号
            'template_id' => 'ulvAHfsUgVNs2gMHZbVR5muK5cxety8s0LcUnxVgWDQ',
            'url' => $url,
            'data' => $data
        ];

        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->send_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $getAccessToken, $json_string);

    }

    # 调剂模版(成功入队消息) 给申请者发送
    public function adjustAudit($params)
    {
        $getAccessToken = $this->getAccessToken();

        $openid = $params['openid'];
        $type = $params['type'];
        $applyname = $params['applyname'];
        $leadername = $params['leadername'];
        $wx = $params['wx'];
        $tel = $params['tel'];
        $teamname = $params['teamname'];

        # 1:成功 0:失败

        if ($type === '1') {
            $type = '加入' . $teamname . '队伍成功';
        } else {
            $type = '很遗憾,您已经落选' . $teamname . '。';
        }

        //$url = URL_HEAD . 'http://xuegengwang.com/wx_daxuewuyou_2017/src/html/adjust_application.html' . URL_TAIL;

        $data = [
            "first" => ["value" => "申请加入队伍审核结果"],
            "keyword1" => ["value" => date('Y年m月d日', time()), 'color' => '#173177'],
            "keyword2" => ["value" => $applyname . '同学，' . $type],
            "remark" => ["value" => "如有疑问,请点击此信息进入页面,联系队长。" . "\r\n" . "队长信息如下:" .
                "\r\n" . '姓名:' . $leadername .
                "\r\n" . '联系方式:' . $tel .
                "\r\n" . '微信:' . $wx, 'color' => '#FF2D21']
        ];

        $param = [
            //接收者openid
            'touser' => $openid,
            //模板号
            'template_id' => 'ZjZLf9-g03y9_uauRpkQCx-REVEfww3E7gZAUUC8AEg',
            'data' => $data
        ];

        $json_string = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->send_post('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $getAccessToken, $json_string);
    }


}