<?php
namespace message;
use think\Db;
use think\Exception;
use think\Log;
class Communication extends Sms {
    protected $keyword = 's_tong';

    //企信通短信配置
    private $send_url = "http://120.25.105.164:8888/sms.aspx";
    private $action ="send";
    private $sendTime = null;
    protected $setting = [];

    public function __construct($setting) {
        parent::__construct($setting);
    }

    public function send($phone, $code, $type,$country_code='86') {
        $content = "【" . SMS_NAME . "】您的验证码为" . $code . "，请不要泄漏给他人。";//要发送的短信内容
        $moduleId = $this->getModuleByType($type);

        if($country_code=='86') {
            $text = $this->sendCode($code,$moduleId);
        } else {
            $text = $this->sendCodeEn($code,$moduleId);
        }
        $con = empty($text) ? $content : $text;
        Log::write("SMS s_tong Info:".$con, 'INFO');
        $result = $this->sendSmsQxt($phone, $con);
        
        Log::write("SMS s_tong Result:".$result, 'INFO');
        
        if (strstr($result, "success")) return true;

        return false;
    }


    public  function sendSmsQxt($mobile,$content){
        $data['userid'] = $this->setting['appid'];
        $data['account'] = $this->setting['account'];
        $data['password'] = $this->setting['token'];
        $data['action'] = $this->action;
        $data['mobile'] = $mobile;
        $data['content'] = $content;
        $data['sendTime'] = $this->sendTime;
        //var_dump($data);exit;
        return $this->send_http($this->send_url,$data);
    }

    private function send_http($url,$postdata=false){
        try{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if($postdata){//post请求
                curl_setopt($ch, CURLOPT_POST, true );
                curl_setopt($ch, CURLOPT_POSTFIELDS,$postdata);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $data = curl_exec($ch);
            curl_close($ch);
        } catch(Exception $e){
            $data = $e->getMessage();
        }
        return $data;
    }

    /**发送的模板
     * @param $code 验证码
     * @param $moduleId 模板id
     */
    function sendCode($code, $moduleId)
    {
        $params = explode("::", $code);
        if(!isset($params[1])) $params[1] = '';
        $array = [
            '100' => "【".SMS_NAME."】 您的验证码为" . $code . "，请不要泄漏给他人。",
            '200' => "【".SMS_NAME."】您修改安全密码的验证码为" . $code . "，请不要泄漏给他人。",
            '300' => "【".SMS_NAME."】您提币的操作验证码为" . $code . "，请不要泄漏给他人。",
            '400' => "【".SMS_NAME."】您找回密码操作的验证码为" . $code . "，请不要泄漏给他人。",
            '500' => "【".SMS_NAME."】您正在进行会员互转操作的验证码为" . $code . "，请不要泄漏给他人。",
            '600' => "【".SMS_NAME."】您正在进行修改手机号码操作的验证码为" . $code . "，请不要泄漏给他人。",
            '700' => "【" . SMS_NAME . "】您充值的".$params[0].$params[1]."已经到帐，可以进行交易了。",
            '800' => "【" . SMS_NAME . "】订单号".$params[0]."在时间".$params[1]."已下单并标记已付款,请登录到我的广告中查看并确认",
            '900' =>"【" . SMS_NAME . "】您正在进行的绑定手机操作的验证码为".$code."，请不要泄漏给他人",
            '1000' => "【" . SMS_NAME . "】订单号".$params[0]."在时间".$params[1]."被标记已放行,请登录到我的订单中查看并确认",
            '1100' => "【" . SMS_NAME . "】有新订单向您出售, 订单号:".$code.",请登录平台查看并完成付款操作.",
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
            '100' =>"【" . SMS_NAME . "】Your verification code is " . $code . ",Please don't leak to others.",
            '200' =>"【" . SMS_NAME . "】The verification code for your security password is " . $code . ",Please don't leak to others.",
            '300' =>"【" . SMS_NAME . "】The operation verification code for your coin is " . $code . ",Please don't leak to others.",
            '400' =>"【" . SMS_NAME . "】The verification code for your password recovery operation is " . $code . ",Please don't leak to others.",
            '500' =>"【" . SMS_NAME . "】The verification code for your member interaction is " . $code . ",Please don't leak to others.",
            '600' =>"【" . SMS_NAME . "】The verification code you are working on to modify your mobile number is " . $code . ",Please don't leak to others.",
            '700' =>"【" . SMS_NAME . "】Your recharged ".$params[0].$params[1]." has arrived and can be traded.",
            '800' =>"【" . SMS_NAME . "】Order number ".$params[0]." has placed the order in time ".$params[1]." and marked the payment，Please login to my advertisement and check it",
            '900' =>"【" . SMS_NAME . "】The verification code for bind phone is ".$code.",Please don't leak to others.",
            '1000'=>"【" . SMS_NAME . "】Order number ".$params[0]." has been marked in time ".$params[1]." and released. Please login to my order and check it.",
            '1100' => "【" . SMS_NAME . "】We have a new order for you. The order number is:".$code.",Please log on the platform to view and complete the payment operation.",
        ];
        return $array[$moduleId];
    }
}