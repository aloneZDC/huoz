<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/6/7
 * Time: 18:35
 */

namespace message;

function get_data($url){
    return file_get_contents($url);
}

class Eth
{
    private $KEYCODE = "QIXS9EvR5h2L";//签名
    public $url = "http://172.18.134.75:8000/";//服务器地址
    public $pwd = "HHGdhxdl1998#Eth";//创建钱包密码
    private $heyue = "";//
    private $ethurl = "https://api.ethplorer.io/";
    private $wei = 1000000000000000000;
    private $apikey = "S7DW2W13SIY1D2IDRUHQSMM2HCRAUTW9FZ";


    public function __construct($rpc_url = null, $port_number = null)
    {
        if ($rpc_url && $port_number) $this->url = "http://" . $rpc_url . ":" . $port_number;


    }

    /**
     * 网络请求
     * @param $url                  请求地址
     * @param null $data 发送参数
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
     * 生成签名
     * @param $args //要发送的参数
     * @param $key //keycode
     * @return string
     */
    private function createSign($args, $key = '')
    {
        $signPars = ""; //初始化
        ksort($args); //键名升序排序
        $key = empty($key) ? $this->KEYCODE : $key;
        foreach ($args as $k => $v) {
            if (!isset($v) || strtolower($k) == "sign") {
                continue;
            }
            $signPars .= $k . "=" . $v . "&";
        }
        $signPars .= "key=" . $key; //签名的参数和key秘钥连接
        $sign = md5($signPars); //md5加密
        $sign = strtoupper($sign); //转为大写
        return $sign; //最终的签名
    }

    /**
     * 创建钱包地址
     * 返回格式
     * 'code' => int 10000
     * 'message' => string 'success' (length=7)
     * 'result' =>
     * 'jsonrpc' => string '2.0' (length=3)
     * 'id' => int 0
     * 'result' => string '0x7e094a0e95faf5147276be2711b143cca8108371' (length=42)
     * @return mixed
     */
    function personal_newAccount($pwd = null)
    {
        $data['method'] = "personal_newAccount";
        $data['passphrase'] = !empty($pwd) ? $pwd : $this->pwd;
        $data['sign'] = $this->createSign($data);
        return $this->curl($data);
    }

    /**
     * 获取钱包的余额(ETH)
     * @param $address      钱包地址
     * @return mixed
     *
     * 'code' => int 10000
     * 'message' => string 'success'
     * 'result' =>
     * array (size=3)
     * 'jsonrpc' => string '2.0'
     * 'id' => int 0
     * 'result' =>
     * 'hex' => string '0x135be2c816159d'
     * 'number' => float 0.0054490541351295
     */
    function eth_getBalance($address)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        if (isValidAddress($address)) {
            $data['method'] = "eth_getBalance";
            $data['address'] = $address;
            $data['sign'] = $this->createSign($data);
            return $this->curl($data);
        }
        return $r;

    }

    /**
     * 获取钱包代币的余额
     * @param $address      钱包地址
     * @return mixed
     *
     * 'code' => int 10000
     * 'message' => string 'success'
     * 'result' => int 100
     */
    function token_getBalance($address, $token_address)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        if (isValidAddress($address) && !empty($token_address)) {
            $data['method'] = "token_getBalance";
            $data['agreement'] = "token";
            $data['token_address'] = $token_address;
            $data['address'] = $address;
            $data['sign'] = $this->createSign($data);
            $re = $this->curl($data);
            $re['result'] = $re['result'] / pow(10, 8);
            return $re;
        }
        return $r;

    }

    /**
     * 代币发送交易
     * @param $from_address         转账人地址
     * @param $to_address           接收人地址
     * @param $token_address        合约地址
     * @param $money                转账金额(转账数量)
     * @param $pwd                  以太坊的转账密码
     * @return mixed
     *
     * 'code' => int 10000
     * 'message' => string 'success'
     * 'result' =>
     * 'result' => string '0xb50356d3dda253aa87bc036a05608888d8f26447d44e3b0c0e4e823cc12721b4'
     *
     */
    function token_sendTransaction($from_address, $to_address, $token_address, $money, $gasPrice, $gas, $pwd)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        $money = floatval($money);
        if (isValidAddress($from_address) && isValidAddress($to_address) && !empty($token_address) && $money > 0 && !empty($gasPrice) && !empty($gas) && !empty($pwd)) {
            $data['method'] = "token_sendTransaction";
            $data['agreement'] = "token";
            $data['token_address'] = $token_address;
            $data['from'] = $from_address;
            $data['to'] = $to_address;
            $data['value'] = floatval($money);
            $data['passphrase'] = $pwd;
            $data['gasPrice'] = $gasPrice;
            $data['gas'] = $gas;
            $data['sign'] = $this->createSign($data);
            return $this->curl($data);
        }
        return $r;

    }

    /**
     * eth发送交易
     * @param $from_address     转账方地址
     * @param $to_address       接收方地址
     * @param $money            转账数量
     * @param $pwd              密码
     * @param $gasPrice         gasPrice
     * @param $gas              gas
     * @return mixed
     *
     * 'code' => int 10000
     * 'message' => string 'success' (length=7)
     * 'result' =>
     * 'jsonrpc' => string '2.0' (length=3)
     * 'id' => int 0
     * 'result' => string '0xdf9c148d84bddf7032dedb465d0cdb29a7dc4497e9c536c6a7836bbba4b7a8e7' (length=66)
     */
    function personal_sendTransaction($from_address, $to_address, $money, $pwd, $gasPrice, $gas)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        $money = floatval($money);
        if (isValidAddress($from_address) && isValidAddress($to_address) && $money > 0 && !empty($pwd) && $gasPrice > 0 && $gas > 0) {
            $data['method'] = "personal_sendTransaction";
            $data['from'] = $from_address;
            $data['to'] = $to_address;
            $data['value'] = floatval($money);
            $data['passphrase'] = $pwd;
            $data['gasPrice'] = $gasPrice;
            $data['gas'] = $gas;
            $data['sign'] = $this->createSign($data);
            return $this->curl($data);
        }
        return $r;
    }

    /**获取转账预估手续费
     * @param $from_address     转账人地址
     * @param $to_address       接收人地址
     * @param $money            转账金额(转账数量)
     * @return mixed        特别注意：本接口为综合性接口，会返回gas，gasPrice和通过gas与gasPrice最终计算得到的手续费三个值
     *
     *          {
     * "code":10000,
     * "message":"success",
     * "result":{
     * "gas":{
     * "hex":"0xcf0c",
     * "number":53004
     * },
     * "gasPrice":{
     * "hex":"0x77359400",
     * "number":2000000000
     * },
     * "fee":0.000106008
     * }
     * }
     */
    function token_getTxUseFee($from_address, $to_address, $token_address, $money)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        $money = floatval($money);
        if (isValidAddress($from_address) && isValidAddress($to_address) && !empty($to_address) && $money > 0) {

            $gasResult = $this->token_estimateGas($from_address, $to_address, $token_address, $money);
            if (!empty($gasResult) && $gasResult['code'] == SUCCESS) {
                $gas = $gasResult['result']['number'];
                $gaspriceResult = $this->eth_gasPrice();
                if (!empty($gaspriceResult) && $gaspriceResult['code'] == SUCCESS) {
                    $gasprice = $gaspriceResult['result']['result']["number"];
                    //为了转账稳定性，gasPrice不小于10 Gwei
//                   if ($gasprice < 10000000000) {
//                       $gasprice = 10000000000;
//                   }
                    $array['gas']["number"] = $gas;
                    $array['gasPrice']["number"] = $gasprice;
                    $array['fee'] = $fee = floatval(($gas * $gasprice) / $this->wei);
                    $r['code'] = SUCCESS;
                    $r['message'] = "获取手续费成功";
                    $r['result'] = $array;
                    return $r;
                } else {
                    $r['message'] = "获取gasPrice出错";
                }
            } else {
                $r['message'] = "获取gas出错";
            }
//            $data['method']="token_getTxUseFee";
//            $data['agreement']="token";
//            $data['token_address']=$token_address;
//            $data['from']=$from_address;
//            $data['to']=$to_address;
//            $data['value']=floatval($money);
//            $data['sign']=$this->createSign($data);
//            return $this->curl($data);
        }
        return $r;

    }

    /**
     *获取预估gas
     * @param $from_address            转账地址
     * @param $to_address              接收地址
     * @param $token_address           合约地址
     * @param $money                    金额
     * @return mixed
     */
    function token_estimateGas($from_address, $to_address, $token_address, $money)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        if (isValidAddress($from_address) && isValidAddress($to_address) && !empty($token_address) && $money > 0) {
            $data['method'] = "token_estimateGas";
            $data['agreement'] = "token";
            $data['token_address'] = $token_address;
            $data['from'] = $from_address;
            $data['to'] = $to_address;
            $data['value'] = floatval($money);
            $data['sign'] = $this->createSign($data);
            return $this->curl($data);
        }
        return $r;
    }

    /**
     * 获取以太坊Token的充值记录 [递归]
     * @param string $address 查询的地址
     * @param string $token_address 合约地址
     * @param string $timestamp 时间戳
     * @param array $result 结果集
     * @param int $index
     * @param array $tx_array 以太坊的交易编号(hash)
     * 'hash' => string '0x9ccf6f515d9298b3a5b6ad55018229a2ac0e7e060ce582f9e3611f69b9bd84f6' (length=66)
     * 'timestamp' => int 1532590681
     * 'value' => float 20000
     * 'success' => boolean true
     * 'from' => string '0x6e83b3ed6ed80f9a471579bb7374596eeb7d44d6' (length=42)
     * 'to' => string '0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b' (length=42)
     * @return array
     */
    function getTokenHistory($address = '', $token_address = '', $timestamp = '', $result = [], $index = 0, $tx_array = [])
    {
        if (!isValidAddress($address)) return $result;
        $token_address = !empty($token_address) ? $token_address : $this->heyue;
        //每页最多取10条，这里取3页，通过每页最后一个数组元素的时间戳取下一页数据。
        $page = 10;
        if ($index < $page) {
            $url = $this->ethurl . "getAddressHistory/" . $address . "?apiKey=freekey&token=" . $token_address . "&type=transfer";
            if (!empty($timestamp)) $url .= "&timestamp=" . $timestamp;
            $data = $this->curl(null, $url, false, 'get');
            if (!empty($data['operations'])) {
                $length = count($data['operations']);
                foreach ($data['operations'] as $key => $val) {
                    if (strtolower($val['to']) == strtolower($address) && !in_array($val['transactionHash'], $tx_array)) {
                        $tx_info = $this->getTxInfo($val['transactionHash']);
                        $val['success'] = $tx_info['success'];
                        $tx_array[] = $val['transactionHash'];

                        $result[] = [
                            'hash' => $val['transactionHash'],
                            'timestamp' => $val['timestamp'],
                            'value' => $val['value'] / pow(10, intval($val['tokenInfo']['decimals'])),
                            'success' => $val['success'],
                            'from' => $val['from'],
                            'to' => $val['to'],
                        ];
                    }
                }

                $index++;

                if ($length === 10) {
                    $timestamp = @$data['operations'][9]['timestamp'];
                    return $this->getTokenHistory($address, $token_address, $timestamp, $result, $index, $tx_array);
                }
            }
        }

        return $result;
    }


    /**
     * 获取以太坊和Token交易信息
     * 转帐以太坊和转账以太坊代币返回数据格式不一样！！
     * 以太坊代币返回格式：
     * 'hash' => string '0xeb1c805af68e085fd2a533ebc88b1cbff8210b9fbca8daff5be3e4fcd7a5e101' (length=66)
     * 'timestamp' => int 1533090373
     * 'blockNumber' => int 6066577
     * 'confirmations' => int 1980
     * 'success' => boolean true
     * 'from' => string '0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b' (length=42)
     * 'to' => string '0xbe0b8fe07b72dbb1dbb5a25c6206cd6d8d7e3dfc' (length=42)
     * 'value' => int 0
     * 'input' => string '0xa9059cbb00000000000000000000000040df5086f5a47c30f0d65cfd9ab13b623716de3500000000000000000000000000000000000000000000000000000006dac2c000' (length=138)
     * 'gasLimit' => int 60000
     * 'gasUsed' => int 52012
     * 'logs' =>
     * array (size=0)
     * empty
     * 'operations' =>
     * array (size=1)
     * 0 =>
     * array (size=11)
     * 'timestamp' => int 1533090373
     * 'transactionHash' => string '0xeb1c805af68e085fd2a533ebc88b1cbff8210b9fbca8daff5be3e4fcd7a5e101' (length=66)
     * 'value' => string '29440000000' (length=11)
     * 'intValue' => float 29440000000
     * 'type' => string 'transfer' (length=8)
     * 'isEth' => boolean false
     * 'priority' => int 131
     * 'from' => string '0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b' (length=42)
     * 'to' => string '0x40df5086f5a47c30f0d65cfd9ab13b623716de35' (length=42)
     * 'addresses' =>
     * array (size=2)
     * 0 => string '0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b' (length=42)
     * 1 => string '0x40df5086f5a47c30f0d65cfd9ab13b623716de35' (length=42)
     * 'tokenInfo' =>
     * array (size=10)
     * 'address' => string '0xbe0b8fe07b72dbb1dbb5a25c6206cd6d8d7e3dfc' (length=42)
     * 'name' => string 'BCB Candy' (length=9)
     * 'decimals' => string '8' (length=1)
     * 'symbol' => string 'BCBcy' (length=5)
     * 'totalSupply' => string '170000000000000000' (length=18)
     * 'owner' => string '0x' (length=2)
     * 'lastUpdated' => int 1533090414
     * 'issuancesCount' => int 0
     * 'holdersCount' => int 145
     * 'price' => boolean false
     * 以太坊返回数据：
     * 'hash' => string '0xe230ba6a8c48f4c4d50b06fb02b1956eb06356d6d45123077a4b1dba2395d188' (length=66)
     * 'timestamp' => int 1532743334
     * 'blockNumber' => int 6042612
     * 'confirmations' => int 25945
     * 'success' => boolean true
     * 'from' => string '0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b' (length=42)
     * 'to' => string '0x71574b54ae651cb1833ca241e27b6d84d18f0480' (length=42)
     * 'value' => float 1.0E-5
     * 'input' => string '0x' (length=2)
     * 'gasLimit' => int 21000
     * 'gasUsed' => int 21000
     * 'logs' =>
     * array (size=0)
     * empty
     *
     * @param $tx_hash      交易编号
     * @return mixed
     */
    function getTxInfo($tx_hash)
    {
        $url = $this->ethurl . "getTxInfo/" . $tx_hash . "?apiKey=freekey";
        return $this->curl(null, $url, false, 'get');
    }

    /**
     * 获取转账ETH的gasPrice
     *（ETH和Token是一样的）
     * 'code' => int 10000
     * 'message' => string 'success' (length=7)
     * 'result' =>
     * 'jsonrpc' => string '2.0' (length=3)
     * 'id' => int 0
     * 'result' =>
     * 'hex' => string '0x12a05f201' (length=11)
     * 'number' => string '5000000001' (length=10)
     *
     * @return mixed
     */
    function eth_gasPrice()
    {
        $data['method'] = "eth_gasPrice";
        $data['sign'] = $this->createSign($data);
        return $this->curl($data);
    }

    /**
     * 计算ETH转账的手续费
     *
     * 'code' => string '10000' (length=5)
     * 'message' => string '获取成功' (length=12)
     * 'result' =>
     * 'gas' => int 21000
     * 'gasPrice' => string '1900000000' (length=10)
     * 'fees' => float 3.99E-5
     * @return mixed
     */
    function eth_fees()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        $result = $this->eth_gasPrice();
        if (!empty($result) && $result['code'] == SUCCESS) {
            $r['result']['gas'] = 21000;
            $r['result']['gasPrice'] = $result['result']['result']['number'];
            $r['result']['fees'] = (21000 * $result['result']['result']['number']) / $this->wei;
            $r['code'] = SUCCESS;
            $r['message'] = "获取成功";
        }
        return $r;
    }

    function eth_getTransactionReceipt($address)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];
        if (!empty($address)) {
            $data['method'] = "eth_getTransactionReceipt";
            $data['transaction_address'] = $address;
            $data['sign'] = $this->createSign($data);
            return $this->curl($data);
        }
        return $r;
    }

    /**
     * 获取地址的余额
     *
     * 'eth' => float 0.0009965130932103
     * 'TTH' => float 86
     * 'BCBcy' => float 2553.08
     * 'BCBtu' => float 1.5
     * @param $address
     * @return null
     */
    function getBalance($address)
    {
        $url = $this->ethurl . "getAddressInfo/" . $address . "?apiKey=freekey";
        $r = $this->curl(null, $url, false, 'get');
        if (!empty($r) && isset($r['address'])) {
            $array['eth'] = $r['ETH']['balance'];
            if (isset($r['tokens'])) {
                foreach ($r['tokens'] as $k => $vo) {
                    if ($vo['balance'] > 0) {
                        $array[$vo['tokenInfo']['symbol']] = $vo['balance'] / pow(10, intval($vo['tokenInfo']['decimals']));
                    } else {
                        $array[$vo['tokenInfo']['symbol']] = 0;
                    }
                }
            }
            return $array;
        } else {
            return null;
        }
    }

    /*
     * 获取ETH的转账记录
     *
     * @param $address          地址
     *没有数据返回[]
     * 返回数组
     *  "timestamp":1529894644,
        "from":"0x6e83b3ed6ed80f9a471579bb7374596eeb7d44d6",
        "to":"0xb8fe56c76eb15e9b6f6d37cb1931fe3c513b881b",
        "hash":"0x460036ba5b4eb38055a7b94e7289470311c01d04c701d2563ccd9365b7620b43",
        "value":0.09,
        "input":"0x",
        "success":true
     * @return mixed
     * Created by Red.
     * Date: 2018/7/26 15:40
     * 注意：2018/08/09   此接口有些记录查询缺失，改用getEthTransactionsList代替，返回的数据格式差不多，详情查看接口
     */
    function getEthTransactions($address)
    {
        $url = $this->ethurl . "getAddressTransactions/" . $address . "?apiKey=freekey&limit=50";
        return $this->curl(null, $url, false, 'get');
    }

    /**
     * 获取ETH的转账记录
     * @param $address
     * @param string $sort
     * @return bool|mixed|string
     * Created by Red.
     * Date: 2018/8/8 19:04
     *
     *参考来源：https://etherscan.io/apis#accounts
     *api返回数据格式,调用些方法返回字段不变，只是处理了一下value的值
     * 'status' => string '1' (length=1)
     * 'message' => string 'OK' (length=2)
     * 'result' =>
     * array (size=1)
     * 0 =>
     * array (size=18)
     * 'blockNumber' => string '6102089' (length=7)
     * 'timeStamp' => string '1533609065' (length=10)
     * 'hash' => string '0x45c2402e8a5a6cc949a5b3ae4e4580bc4f98f095505c90b63920ba70b796a3b1' (length=66)
     * 'nonce' => string '1' (length=1)
     * 'blockHash' => string '0x95dd10837605c3d5a4d8ce0f04635ab2313633b7a45ec219e96caf0b91bcd9fa' (length=66)
     * 'transactionIndex' => string '96' (length=2)
     * 'from' => string '0x54d2cf2bd78ee429789954775fcaa38890ba2581' (length=42)
     * 'to' => string '0xac5fe2a7433b6415692d6c8cb3b9fcd72b9b4d6a' (length=42)
     * 'value' => string '999823600000000000' (length=18)
     * 'gas' => string '21000' (length=5)
     * 'gasPrice' => string '8400000000' (length=10)
     * 'isError' => string '0' (length=1)
     * 'txreceipt_status' => string '1' (length=1)
     * 'input' => string '0x' (length=2)
     * 'contractAddress' => string '' (length=0)
     * 'cumulativeGasUsed' => string '7798740' (length=7)
     * 'gasUsed' => string '21000' (length=5)
     * 'confirmations' => string '12245' (length=5)
     */
    function getEthTransactionsList($address, $sort = "desc")
    {

        $url = "http://api.etherscan.io/api?module=account&action=txlist&address=" . $address . "&startblock=0&endblock=99999999&sort=" . $sort . "&apikey=" . $this->apikey;
        $result = json_decode(get_data($url), true);
        if ($result['status'] == "1") {
            $list = $result['result'];
            $listResult = array();
            if (!empty($list)) {
                foreach ($list as $k => &$value) {
                    //返回来是以位为单位，要除以1000000000000000000;
                    $value['value'] = $value['value'] / $this->wei;
                    if ($value['isError'] == 0) {
                        $listResult[$k] = $value;
                    }

                }
                return $listResult;
            }
        }
        return null;
    }

}