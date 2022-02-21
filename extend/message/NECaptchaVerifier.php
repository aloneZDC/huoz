<?php
namespace message;

define("YIDUN_CAPTCHA_API_TIMEOUT", 5);

/**
 * 易盾验证码二次校验SDK
 * @author yangweiqiang
 */
class NECaptchaVerifier {
    private $captchaId="393c58cccfa44537b90c773fcbcb43cb";
    private $secretId="4c9f74820520d03e8c3be92aec148240";
    private $secretKey="34dd116b77674d1e07e9184f0a306dd8";
    private $url="http://c.dun.163yun.com/api/v2/verify";


    /**
     * 发起二次校验请求
     * @param $validate 二次校验数据
     * @param $user 用户信息
     */
    public function verify($validate) {
        $params = array();
        $params["captchaId"] = $this->captchaId;
        $params["validate"] = $validate;
        $params["user"] = "";
        // 公共参数
        $params["secretId"] = $this->secretId;
        $params["version"] = 'v2';
        $params["timestamp"] = $this->msectime();// time in milliseconds
        $params["nonce"] = $this->msectime().sprintf("%d", rand()); // random int
        $params["signature"] = $this->gen_signature($this->secretKey, $params);
        $result = $this->send_http_request($params);
        return array_key_exists('result', $result) ? $result['result'] : false;
    }

    /**
     * 计算参数签名
     * @param $secret_key 密钥对key
     * @param $params 请求参数
     */
    private function sign($secret_key, $params){
        ksort($params); // 参数排序
        $buff="";
        foreach($params as $key=>$value){
            $buff .=$key;
            $buff .=$value;
        }
        $buff .= $secret_key;
        return md5($buff);
    }
    //生成13位毫秒时间戳
   private function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    }

    /**
     * 生成签名信息
     * $secretKey 产品私钥
     * $params 接口请求参数，不包括signature参数
     */
    private function gen_signature($secretKey,$params){
        ksort($params);
        $buff="";
        foreach($params as $key=>$value){
            $buff .=$key;
            $buff .=$value;
        }
        $buff .= $secretKey;
        return md5(mb_convert_encoding($buff, "utf8", "auto"));
    }

    /**
     * 发送http请求
     * @param $params 请求参数
     */
    private function send_http_request($params){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, YIDUN_CAPTCHA_API_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, YIDUN_CAPTCHA_API_TIMEOUT);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        /*
         * Returns TRUE on success or FALSE on failure. 
         * However, if the CURLOPT_RETURNTRANSFER option is set, it will return the result on success, FALSE on failure.
         */
        $result = curl_exec($ch);
        if(curl_errno($ch)){
            $msg = curl_error($ch);
            curl_close($ch);
            return array("error"=>500, "msg"=>$msg, "result"=>false);
        }else{
            curl_close($ch);
            return json_decode($result, true);  
        }
    }
}

?>