<?php
namespace message;
use think\Db;
use think\Exception;
use message\Sms;
/**
* 短信宝发送接口
*/
class Smsbao extends Sms {
    protected $keyword = 's_bao';

    public function __construct($setting) {
        parent::__construct($setting);
    }

    //发送短信 短信宝发送短信验证码
    public function send($phone, $code, $type,$country_code='86') {
        if(!$this->setting) return false;

        $smsapi = "http://api.smsbao.com/";
        $pass = md5($this->setting['account']); //短信平台密码
        $key = 'sms_time_'.$phone;
        $content = "【".SMS_NAME."】您的验证码为{$code}，请不要泄漏给他人。";//要发送的短信内容

        $moduleId = $this->getModuleByType($type);
        if($country_code=='86') {
            $text = $this->sendCode($code,$moduleId);
        } else {
            $text = $this->sendCodeEn($code,$moduleId);
        }
        $con = empty($text) ? $content : $text;

        $user = $this->setting['appid'];
        $sendurl = $smsapi . "sms?u={$user}&p={$pass}&m={$phone}&c=" . urlencode($con);
        $result = @_curl($sendurl, null, false, 'GET',5);
        if (intval($result) == 0 && $result !== false) {
            return true;
        } else {
            return $this->getStatusStr($result);
        }
    }

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

    private function getStatusStr($status) {
        $statusStr = array(
            "0" => lang('lan_SMS_sending_success'),//"短信发送成功",
            "-1" => lang('lan_orders_send_failure'),//"参数不全",
            "-2" => lang('lan_orders_send_failure'),//"服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => lang('lan_orders_send_failure'),//"密码错误",
            "40" => lang('lan_orders_send_failure'),//"账号不存在",
            "41" => lang('lan_orders_send_failure'),//"短信余额不足",
            "42" => lang('lan_orders_send_failure'),//"帐户已过期",
            "43" => lang('lan_orders_send_failure'),//"IP地址限制",
            "50" => lang('lan_orders_send_failure'),//"内容含有敏感词",
            "51" => lang('The_phone_number_is_not_correct'),//"手机号码不正确",
            "100" => lang('SMS_operation_later'),//"获取验证码频率太频繁了"
        );
        return isset($statusStr[$status]) ? $statusStr[$status].$status : '';
    }
}
