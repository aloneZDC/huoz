<?php
namespace message;

use think\Log;

/**
* 创蓝短信发送接口
*/
class Chuanglan extends Sms {
    protected $keyword = 's_lan';
    //参数的配置 请登录zz.253.com 获取以下API信息 ↓↓↓↓↓↓↓
    const API_SEND_URL='http://smssh1.253.com/msg/send/json'; //创蓝发送短信接口URL

    const API_VARIABLE_URL = 'http://smssh1.253.com/msg/variable/json';//创蓝变量短信接口URL

    const API_BALANCE_QUERY_URL= 'http://smssh1.253.com/msg/balance/json';//创蓝短信余额查询接口URL

    const API_INTERNATIONAL_URL= 'http://intapi.253.com/send/json';//创蓝发送国际短信接口URL

    public $API_ACCOUNT= ''; // 创蓝API账号

    public $API_PASSWORD= '';// 创蓝API密码

    public function __construct($setting) {
        parent::__construct($setting);
    }

    //发送短信 创蓝发送短信验证码
    public function send($phone, $code, $type,$country_code='86') {
        if(!$this->setting) return false;
        $this->API_ACCOUNT=$this->setting['account'];
        $this->API_PASSWORD=$this->setting['token'];
        $content = "【".SMS_NAME."】您的验证码为{$code}，请不要泄漏给他人。";//要发送的短信内容
        $moduleId = $this->getModuleByType($type);
        if($country_code=='86') {
            $text = $this->sendCode($code,$moduleId);
        } else {
            $text = $this->sendCodeEn($code,$moduleId);
        }
        $con = empty($text) ? $content : $text;
        $params=$phone;
        $par = explode("::", $code);
        if(!isset($par[1])) $par[1] = '';
        if(isset($par[0])&&!empty($par[0])){
            $params.=",".$par[0];
        }
        if(isset($par[1])&&!empty($par[1])){
            $params.=",".$par[1];
        }
//        if($country_code=='86') {
//            //返回响应格式说明
//            //code	        string	提交响应状态码，返回“0”表示提交成功（详细参考提交响应状态码）	"code":"0"
//            //failNum	    string	失败条数	"failNum":"0"
//            //successNum	string	成功条数	"successNum":"1"
//            //msgId	        string	消息id	"msgId":"18052416023122210"
//            //time	        string	响应时间	"time":"20180524160231"
//            //errorMsg	    string	状态码说明（成功返回空）	"errorMsg":""
//            $result = json_decode($this->sendVariableSMS($con,$params),true);
//        } else {
            //code	   string	0代表发送成功，其他code代表出错，详细见"返回值说明"页面
            //error	   string	提交成功返回空，或者相应错误信息描述
            //msgid	   string	消息id
            $result = @json_decode($this->sendInternationalSMS($con,$country_code.$phone),true);
//        }
        if (isset($result['code']) && $result['code'] == 0) {
            return true;
        } else {
//            if($country_code=='86') {
//                return isset($result['errorMsg'])?$result['errorMsg']:"";
//            } else {
                return isset($result['error'])?$result['error']:"";
//            }
        }
    }

    /**
     *发送的模板
     * @param $code 验证码
     * @param $moduleId 模板id
     */
//    function  sendCode($code,$moduleId){
//        $params = explode("::", $code);
//        if(!isset($params[1])) $params[1] = '';
//
//        $array = [
//            '100' =>'【' . SMS_NAME . '】您的验证码为{$var}，请不要泄漏给他人。',
//            '200' =>'【' . SMS_NAME . '】您修改安全密码的验证码为{$var}，请不要泄漏给他人。',
//            '300' =>'【' . SMS_NAME . '】您提币的操作验证码为{$var}，请不要泄漏给他人。',
//            '400' =>'【' . SMS_NAME . '】您找回密码操作的验证码为{$var}，请不要泄漏给他人。',
//            '500' =>'【' . SMS_NAME . '】您正在进行会员互转操作的验证码为{$var}，请不要泄漏给他人。',
//            '600' =>'【' . SMS_NAME . '】您正在进行修改手机号码操作的验证码为{$var}，请不要泄漏给他人。',
//            '700' =>'【' . SMS_NAME . '】您充值的{$var}:{$var}已经到帐，可以进行交易了。',
//            '800' =>'【' . SMS_NAME . '】订单号{$var}在时间{$var}已下单并标记已付款,请登录到我的广告中查看并确认。',
//            '900' =>'【' . SMS_NAME . '】您正在进行的绑定手机操作的验证码为{$var}，请不要泄漏给他人。',
//            '1000' => '【' . SMS_NAME . '】订单号{$var}在时间{$var}被标记已放行,请登录到我的订单中查看并确认。',
//            '1100' => '【' . SMS_NAME . '】有新订单向您出售, 订单号:{$var},请登录平台查看并完成付款操作。',
//        ];
//        return $array[$moduleId];
//    }
    /**
     *发送的模板
     * @param $code 验证码
     * @param $moduleId 模板id
     */
    function  sendCode($code,$moduleId){
        $params = explode("::", $code);
        if(!isset($params[1])) $params[1] = '';

        $array = [
            '100' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '200' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '300' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '400' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '500' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '600' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '700' =>'【' . SMS_NAME . '】您充值的'.$params[0].':'.$params[1].'已经到帐。',
            '800' =>'【' . SMS_NAME . '】订单号'.$params[0].'在'.$params[1].'已付款,请到广告中查看。',
            '900' =>'【' . SMS_NAME . '】您的验证码为'.$params[0].'，如非本人，请勿操作。',
            '1000'=>'【' . SMS_NAME . '】订单号'.$params[0].'在'.$params[1].'已放行,请到订单中查看。',
            '1100'=>'【' . SMS_NAME . '】有新订单向您出售,订单号:'.$params[0].',请完成付款。',
        ];
        return $array[$moduleId];
    }

    /**发送的模板-英文
     * @param $code 验证码
     * @param $moduleId 模板id
     */
    function  sendCodeEn($code,$moduleId){
        $params = explode("::", $code);
        if(!isset($params[1])) $params[1] = '';

        $array = [
            '100' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '200' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '300' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '400' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '500' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '600' =>'【' . SMS_NAME . '】Your code is ' . $code . '.',
            '700' =>'【' . SMS_NAME . '】Your recharged '.$params[0].$params[1].' has arrived.',
            '800' =>'【' . SMS_NAME . '】Your order '.$params[0].' has been paid in '.$params[1].', please check it.',
            '900' =>'【' . SMS_NAME . '】Your code is '.$code.'.',
            '1000'=>'【' . SMS_NAME . '】Your order '.$params[0].' has been released in '.$params[1].', please check it.',
            '1100'=>'【' . SMS_NAME . '】A new order for you, please pay it '.$code.'.',
        ];
        return $array[$moduleId];
    }

    /**
     * 发送短信
     *
     * @param string $mobile 		手机号码
     * @param string $msg 			短信内容
     * @param string $needstatus 	是否需要状态报告
     */
    public function sendSMS( $mobile, $msg, $needstatus = 'true') {
        //创蓝接口参数
        $postArr = array (
            'account'  =>  $this->API_ACCOUNT,
            'password' => $this->API_PASSWORD,
            'msg' => urlencode($msg),
            'phone' => $mobile,
            'report' => $needstatus,
        );
        $result = $this->curlPost( self::API_SEND_URL, $postArr);
        return $result;
    }

    /**
     * 发送变量短信
     *
     * @param string $msg 			短信内容
     * @param string $params 	最多不能超过1000个参数组
     */
    public function sendVariableSMS( $msg, $params) {

        //创蓝接口参数
        $postArr = array (
            'account'  =>  $this->API_ACCOUNT,
            'password' => $this->API_PASSWORD,
            'msg' => $msg,
            'params' => $params,
            'report' => 'true'
        );

        $result = $this->curlPost( self::API_VARIABLE_URL, $postArr);
        return $result;
    }

    /**
     * 发送国际短信
     * 返回数据格式如下：
    code	string	0代表发送成功，其他code代表出错，详细见"返回值说明"页面
    error	string	提交成功返回空，或者相应错误信息描述
    msgid	string	消息id
     * @param $msg
     * @param $mobile
     * Create by: Red
     * Date: 2019/9/4 16:37
     */
    public function sendInternationalSMS($msg,$mobile){
        //创蓝接口参数
        $postArr = array (
            'account'  =>  $this->API_ACCOUNT,
            'password' => $this->API_PASSWORD,
            'msg' => $msg,
            'mobile' => $mobile,
        );
        $result = $this->curlPost( self::API_INTERNATIONAL_URL, $postArr);
        return $result;
    }

    /**
     * 查询额度
     *
     *  查询地址
     */
    public function queryBalance() {

        //查询参数
        $postArr = array (
            'account'  =>  $this->API_ACCOUNT,
            'password' => $this->API_PASSWORD,
        );
        $result = $this->curlPost(self::API_BALANCE_QUERY_URL, $postArr);
        return $result;
    }

    /**
     * 通过CURL发送HTTP请求
     * @param string $url  //请求URL
     * @param array $postFields //请求参数
     * @return mixed
     *
     */
    private function curlPost($url,$postFields){
        $postFields = json_encode($postFields);
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
            )
        );
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,60);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec ( $ch );
        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close ( $ch );
        return $result;
    }

}
