<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/6/7
 * Time: 18:35
 */

namespace message;



class Eos
{
    private $KEYCODE = "EOS20190223";//签名
    private $url = "";//签名

    public function __construct($rpc_url = null, $port_number = null)
    {
        if(!empty($rpc_url))$this->url = "http://" . $rpc_url;
        if(!empty($port_number))$this->url=$this->url.":".$port_number;
        $this->url=$this->url."/eos-api.php";


    }

    /**
     * 网络请求
     *
     * @param null $data 发送参数
     * @param $url                  请求地址
     * @param bool $json 是否json数据格式请求
     * @param string $method 请求方法，默认post请求
     * @param int $timeout 超时时间
     * @param array $header hearder头
     * @return mixed
     */
    private function curl($data = null, $url = null, $json = false, $method = 'POST', $timeout = 30, $header = [])
    {
        $url = empty($url) ? $this->url : $url;
        $ssl = substr(trim($url), 0, 8) == "https://" ? true : false;
        $ch = curl_init();
        $fields = $data;
        $headers = [];
        if ($json && is_array($data)) {
            $fields = json_encode($data);
            $headers = [
                "Content-Type: application/json",
                'Content-Length: ' . strlen($fields),
            ];
        }
        if (!empty($header)) {
            $headers = array_merge($header, $headers);
        }

        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($ssl) {
            $opt[CURLOPT_SSL_VERIFYHOST] = 1;
            $opt[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return @json_decode($result, true);
    }

    /**
     * 获取帐号的余额
     * @param $account      帐号
     * @return mixed
     *  'code' => string '10000' (length=5)
        'message' => string '获取余额成功' (length=18)
        'result' => string '8.9910' (length=6)
     * Created by Red.
     * Date: 2019/2/26 15:50
     */
   function getbalance($account){
        $r['code']=ERROR1;
        $r['message']="参数错误";
        $r['result']=[];
        if(!empty($account)){
            $data['act']="getbalance";
            $data['account']=$account;
            $data['sign']=$this->KEYCODE;
            $result=$this->curl($data);
            if(isset($result['code'])&&$result['code']==1){
                $balance=explode(" EOS",$result['core_liquid_balance']);
                $r['code']=SUCCESS;
                $r['message']="获取余额成功";
                $r['result']=$balance[0];
            }else{
                $r['message']=@$result['msg'];
            }
        }
        return $r;

    }

    /**
     * EOS转账
     * @param $from_address             转出的钱包地址
     * @param $to_address               接收的钱包地址
     * @param $money                    转出数量
     * @param string $memo              接收的标签
     * @return mixed
     * 返回数据如：
     *  'code' => string '10000' (length=5)
        'message' => string '转账成功' (length=12)
        'result' => string '6854a298d5090485e9eeefd765db4247c82ffc47663088419e147ab8323db708' (length=64)
     * Created by Red.
     * Date: 2019/2/26 16:18
     */
    function transfer($from_address,$to_address,$money,$memo=""){
        $r['code']=ERROR1;
        $r['message']="参数错误";
        $r['result']=[];
        if(!empty($from_address)&&!empty($to_address)&&$money>0){
            $data['act']="trans";
            $data['quantity']=$money;
            $data['from']=$from_address;
            $data['to']=$to_address;
            $data['memo']=$memo;
            $data['sign']=$this->KEYCODE;
            $result=$this->curl($data);
            if(isset($result['code'])&&$result['code']==1){
                $r['code']=SUCCESS;
                $r['message']="转账成功";
                $r['result']=$result['tx'];
            }else{
                $r['message']=@$result['msg'];
            }
        }
        return $r;
    }

}