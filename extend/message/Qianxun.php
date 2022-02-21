<?php
namespace message;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 短信宝发送接口
 */
class Qianxun extends Sms {
    protected $keyword = 's_qx';

    public function __construct($setting) {
        parent::__construct($setting);
    }

    //发送短信 短信宝发送短信验证码
    public function send($phone, $code, $type,$country_code='86') {
        if(!$this->setting) return false;

        $smsapi = "http://47.99.227.82:8081/api/sms/send";
        $content = "【".SMS_NAME."】您的验证码为{$code}，请不要泄漏给他人。";//要发送的短信内容

        $moduleId = $this->getModuleByType($type);
        if($country_code=='86') {
            $text = $this->sendCode($code,$moduleId);
        } else {
            $phone = $country_code.$phone;
            $text = $this->sendCodeEn($code,$moduleId);
        }
        $con = empty($text) ? $content : $text;

        $ts = $this->getMillisecond();
        $sign = md5($this->setting['appid'].$ts.$this->setting['token']);
        $paramStr = "userid=".$this->setting['appid']."&ts=".$ts."&sign=".$sign."&mobile=".$phone."&msgcontent=".urlencode($con)."&sendtime=&extnum=";

        $result = $this->http_post_json($smsapi, $paramStr);
        if ($result['code']==0) {
            if(!empty($result['msg'])) {
                return [
                    'send_status' => true,
                    'third_id' => $result['msg'],
                ];
            }
            return true;
        } else {
            return $result['msg'];
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
            '700' =>'【' . SMS_NAME . '】您充值的'.sprintf('%.4f', $params[1]).'  '.$params[0].'已经到帐。',
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

    /*
    * @param $jsonStr 发送的json字符串
    * @return array
    */
    function http_post_json($url, $paramStr)
    {
        $status = -1;
        $message = "not response";
        $curl_response = '';

        try{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                    'Content-Length: ' . strlen($paramStr)
                )
            );
            $response = $curl_response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if(!empty($response)) {
                $response = json_decode($response,true);
                if(!isset($response['code']) || $response['code']!=0) {
                    $message = isset($response['msg']) ? $response['msg'] : 'response error';
                    Log::write("千寻 response：".$message);
                } else {
                    $status = 0;
                    $message = (isset($response['data']) && isset($response['data']['taskid'])) ? $response['data']['taskid'] : '';
                }
            } else {
                Log::write("千寻: not response");
            }
        } catch (Exception $e) {
            $message = "exception catch";
            Log::write("千寻：".$e->getMessage());
        }
        return [
            'code' => $status,
            'msg' => $message,
            'response' => $curl_response,
        ];
    }

    //获取余额
    function getBalance() {
        $smsapi = "http://47.99.227.82:8081/api/sms/balance";
        $ts = $this->getMillisecond();
        $sign = md5($this->setting['appid'].$ts.$this->setting['token']);
        $paramStr = "userid=".$this->setting['appid']."&ts=".$ts."&sign=".$sign;

        $result = $this->http_post_json($smsapi, $paramStr);
        if ($result['response']) {
            $response = json_decode($result['response'],true);
            if((isset($response['data']) && isset($response['data']['balance']))) {
                return $response['data']['balance'];
            }
        }
        return false;
    }

    function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }
}
