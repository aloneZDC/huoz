<?php
namespace xingl;

use think\Db;
use think\Exception;
use think\Log;

class RpcClient
{
    private $config;
    private $ts;
    private $url_map;

    public function __construct($cfg, $ts=null) {
        $this->config = $cfg;
        $this->ts = $ts;
    }

    //请求接口
    public function call($action, $parameters) {
        $system_params = $this->get_system_params($parameters);
        $request_url = $this->get_request_url($action);
        $result = $this->post($request_url, $parameters, $system_params);
        return $result;
    }

    //获取接口地址
    public function get_request_url($action) {
        $this->url_map = $this->config['pro_url'] . $action;
        return $this->url_map;
    }

    //获取系统参数
    public function get_system_params($params) {
        # 默认系统参数
        $system_params = array(
            'appKey' => $this->config['appKey'],
            'randomNumber' => randNum(15),
            //'skuId' => 1410800728046895105,//示例
            'timeStamp' => time() * 1000,
            'version' => "1.0",
        );
        if ($params) {//body参数加入header
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $system_params[$key] = json_encode($value);
                } else {
                    $system_params[$key] = str_replace('"','', $value);
                }
            }
        }

        return $this->generate_signature($system_params);
    }

    //计算验签
    public function generate_signature($system_params) {
        $sign_str = '';
        ksort($system_params);
        foreach($system_params as $key=>$value) {
            $sign_str.=$key.'='.strval($value) . '&';
        }
        //md5Hex('appKey=1395984999469318145&randomNumber=465654555656544&skuId=4543645454452&timeStamp=1545804554075&version=1.0&270c449611614f4f92a8b36433793fdc')
        $sign_str.=$this->config['appSecret'];
        if($this->config['debug_mode']) Log::write('计算sign源串'.$sign_str);
        $system_params['sign'] = md5($sign_str);

        $result = [];
        foreach ($system_params as $k => $v) {
            $result[] = $k . ':' . $v;
        }
        $result[] = 'Content-Type: application/json;charset=UTF-8';

        return $result;
    }

    //发送请求
    public function post($url, $data, $url_params) {
        try{
            $post_data = json_encode($data);
            if($this->config['debug_mode']) {
                Log::write($url);
                Log::write(json_encode($url_params));
                Log::write($post_data);
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            //忽略证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $url_params);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                print curl_error($ch);
            }
            curl_close($ch);
            return json_decode($result,true);
        }catch(Exception $e){
            return $e->getMessage();
        }
    }

}
?>