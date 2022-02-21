<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/7/18
 * Time: 18:04
 */
namespace message;
class Xrp
{
    private $sign = "xrp20181210";

    function _curl($url, $data = null, $json = false, $method = 'POST', $timeout = 30)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
        $ch = curl_init();
        $fields = $data;
        $headers = array();
        if ($json && is_array($data)) {
            $fields = json_encode($data);
            $headers = array(
                "Content-Type: application/json",
                'Content-Length: ' . strlen($fields),
            );
        }

        $opt = array(
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        );

        if ($ssl) {
            $opt[CURLOPT_SSL_VERIFYHOST] = 1;
            $opt[CURLOPT_SSL_VERIFYPEER] = FALSE;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * 瑞波币转帐
     * @param $url                      服务器的请求url
     * @param $amount                   转帐数量
     * @param $src_address              转帐地址
     * @param $src_secret               转帐私钥
     * @param $des_address              接收地址
     * @param $des_tag                  接收地址的标签
     * @param null $src_tag             转账地址传过去的备注标签（非必传，一般用于提币定位）
     * @return mixed
     * Created by Red.
     * Date: 2018/12/27 19:42
     */
    function sendTrans($url, $amount, $src_address, $src_secret, $des_address, $des_tag, $src_tag = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        if (!empty($url) && !empty($amount) && !empty($src_address) && !empty($src_secret) && !empty($des_address) && !empty($des_tag)) {
            $server['act'] = "trans";
            $server['amount'] = $amount;
            $server['src_address'] = $src_address;
            $server['src_secret'] = $src_secret;
            $server['des_address'] = $des_address;
            $server['des_tag'] = $des_tag;
            $server['sign'] = $this->sign;
            if (!empty($src_tag)) $server['src_tag'] = $src_tag;
            $result = json_decode($this->_curl($url, $server, true), true);
            if ($result['code'] == 1) {
                $r['code'] = SUCCESS;
                $r['message'] = "转账提交成功，交易是否成功请去区块浏览器查询";
            } else {
                $r['message'] = $result['msg'];
            }
        }
        return $r;
    }


    /**
     * 根据地址获取余额数量
     * @param $address          地址
     * @return int
     * Created by Red.
     * Date: 2018/12/28 8:58
     */
    function getBalance($address)
    {
        if (!empty($address)) {
            $xrpResult = json_decode(get_data("https://data.ripple.com/v2/accounts/" . $address . "/balances"), true);
            if ($xrpResult['result'] == "success" && isset($xrpResult['balances']) && !empty($xrpResult['balances'])) {
                foreach ($xrpResult['balances'] as $v) {
                    if ($v['currency'] == "XRP") {
                        return $v['value'];
                    }
                }
            }
        }
        return 0;

    }

}