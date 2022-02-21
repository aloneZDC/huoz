<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/7/18
 * Time: 18:04
 */
namespace message;

class Btc
{
    /**
     * 获取新的一个钱包地址
     * @return unknown
     */
    function qianbao_new_address($uid, $currency)
    {
        $r['code']=ERROR1;
        $r['message']="参数错误";
        $r['result']=[];
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $address = $bitcoin->getnewaddress(strval($uid));
        if($address){
            $r['code']=SUCCESS;
            $r['message']="创建地址成功";
            $r['result']=$address;
        }else{
            $r['code']=ERROR2;
            $r['message']=$bitcoin->error;
        }
        return $r;
    }

    /**
     * 查询钱包余额
     * @param array $currency 要查询的服务器配置信息
     * @return float  剩余的余额
     */
    function get_qianbao_balance($currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $money = $bitcoin->getbalance();
        return $money>0?$money:0;
    }

    /**
     * 检测地址是否是有效地址
     *
     * @return boolean 如果成功返回个true
     * @return boolean 如果失败返回个false；
     * @param string $url btc地址
     * @param array $currency 服务器配置的
     */

    function check_qianbao_address($url, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $address = $bitcoin->validateaddress($url);
        if ($address['isvalid']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * BTC提币方法
     * @param unknown $url 钱包地址
     * @param unknown $money 提广告资产数量
     */
    /**BTC提币方法
     * @param $from_address     转出地址
     * @param $pwd              密码
     * @param $to_address       接收地址
     * @param $money            转账数量
     * @param $currency         服务器配置信息
     * @return string           交易编号hash
     * Created by Red.
     * Date: 2018/7/26 9:00
     */
    function qianbao_tibi($pwd,$to_address, $money, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $bitcoin->walletlock();//强制上锁
        $bitcoin->walletpassphrase($pwd, 60);
        $id = $bitcoin->sendtoaddress($to_address, $money);
        $bitcoin->walletlock();
        return $id;
    }


    /**
     *  根据交易哈希id查看交易记录
     * 待网络节点确认中返回false，成功返回交易数据
     * @param $tid              交易哈希id
     * @param $currency         服务器配置信息
     * @return mixed
     * Created by Red.
     * Date: 2018/7/26 9:24
     */
    function chakan_tibi_jilu($tid, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $result = $bitcoin->getinfo();
        $list = $bitcoin->gettransaction($tid);
        return $list;
    }

    /**
     * BTC查询某人的交易记录(旧版BTC可用，新版本不可用)
     * @param int $user 用户uid
     * @param int $count 从第几个开始查找
     *
     *
        'account' => string '5005' (length=4)
        'address' => string '3E8mtwiZZPQvGEZK9An4yp1tWgvUTPH7No'
        'category' => string 'receive' (length=7) (receive表示接收，send表示发送)
        'amount' => float 1.0E-5
        'label' => string '5005' (length=4)
        'vout' => int 0
        'confirmations' => int 919
        'blockhash' => string '0000000000000000002a8954c80cdcffc00e5dc1e815b6f6a04484e2633d5fbc' (length=64)
        'blockindex' => int 692
        'blocktime' => int 1532519725
        'txid' => string '2f476258c9ceb32b84edc15b4ab6f3a53ad1a96cd4101dc47c14d831c1a23fc9' (length=64)
        'walletconflicts' =>
        array (size=0)
        empty
        'time' => int 1532519578
        'timereceived' => int 1532519578
        'bip125-replaceable' => string 'no' (length=2)
     * @return $list  返回此用户的交易列表
     */
    function trade_qianbao($uid, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $result = $bitcoin->getinfo();
        $list = $bitcoin->listtransactions(strval($uid), 50,0);
        return $list;
    }

    /**根据BTC钱包址获取该地址的转帐记录(新版BTC服务器,旧版用trade_qianbao方法)
     * @param $address                  地址
     * @param $currency                 币服务器配置信息
     * @return array|null
     *
     * 'address' => string '379tpW3fwsBXh1AP5ZjDmbLK3x93hkTXZR' (length=34)
    'category' => string 'receive' (length=7)  (receive表示接收，send表示发送)
    'amount' => float 0.01447                   (receive会是正数，send会是负数)
    'label' => string '1' (length=1)
    'vout' => int 5
    'confirmations' => int 951
    'blockhash' => string '0000000000000000001839e169c9d6736893426996cc1162f5ea8495217bd9cd' (length=64)
    'blockindex' => int 107
    'blocktime' => int 1539419365
    'txid' => string 'f019264c43f6a7aa0f9dbd379cfd097a86a53920f6037e926efc02c8615c4cb6' (length=64)
    'walletconflicts' =>
    array (size=0)
    empty
    'time' => int 1539419308
    'timereceived' => int 1539419308
    'bip125-replaceable' => string 'no' (length=2)
     * Created by Red.
     * Date: 2018/10/20 14:16
     */
    function trade_by_address($address,$currency){
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $list = $bitcoin->listtransactions("*");
        if(!empty($list)){
            $array=[];
            foreach ($list as $value){
                if($value['address']==$address){
                   $array[]=$value;
                }
            }
            return $array;
        }
        return null;
    }

    /**USDT创建一个钱包地址
     * @param $uid              用户uid
     * @param $currency         配置信息数组
     * @return mixed
     * Created by Red.
     * Date: 2018/7/24 14:47
     */
    function omni_qianbao_new_address($uid, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $address = $bitcoin->getnewaddress(strval($uid));
        return $address;
    }

    /**
     * 查询某个地址帐号的USDT转帐记录
     * @param $address          钱包地址
     * @param $currency         配置信息数组
     * @return mixed
     *
     *    'txid' => string '42392966f6ff14b48d18b9b638c35dac7bb7db5bb5d14fab3c38391014d0ac05' (length=64)
            'fee' => string '0.00001535' (length=10)
            'sendingaddress' => string '14g42KnXkRFfR8cUtS5tReprjeNvGQKW9v' (length=34)
            'referenceaddress' => string '14r9FgrbXfseDcYaE2GuDQjbLuTY4ehZZ7' (length=34)
            'ismine' => boolean true
            'version' => int 0
            'type_int' => int 0
            'type' => string 'Simple Send' (length=11)
            'propertyid' => int 31
            'divisible' => boolean true
            'amount' => string '0.00100000' (length=10)
            'valid' => boolean true     (说明：转账成功为true,待节点确认中无此字段)
            'blockhash' => string '00000000000000000027c17df43ee9b6bdd284b42390e706a52aad18b7a5812c' (说明：转账成功有些字段，待节点确认中无此字段)
            'blocktime' => int 1533028729   (说明：转账成功有此字段,待节点确认中无此字段)
            'positioninblock' => int 1095   (说明：转账成功有此字段,待节点确认中无此字段)
            'block' => int 534515           (说明：转账成功有此字段,待节点确认中无此字段)
            'confirmations' => int 1        (说明：节点确认数：转账成功不为零,待节点确认中为0)
     * Created by Red.
     * Date: 2018/7/24 14:59
     */
    function omni_trade_qianbao($address, $currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $list = $bitcoin->omni_listtransactions($address, 100000);
        return $list;
    }


    /**
     * 提usdt的方法
     * @param string    $from_address   转账地址
     * @param string    $pwd            转账密码
     * @param string    $to_address     钱包地址
     * @param int $money 提广告资产数量
     * @return string   交易id
     */
     function usdt_qianbao_tibi($from_address,$pwd,$to_address, $money, $currency)
    {
        if(!empty($from_address)&&!empty($pwd)&&!empty($to_address)&&!empty($money)&&!empty($currency)){
            $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
//            $bitcoin->walletlock();//强制上锁
            $bitcoin->walletpassphrase($pwd, 60);
            $id = $bitcoin->omni_send($from_address, $to_address, 31, strval($money));
//            $bitcoin->walletlock();
            return $id;
        }
        return null;

    }
    /**
     * USDT转帐接口，手续费从指定的地址扣除
     * @param $fromaddress              发送方地址
     * @param $pwd                      解锁密码
     * @param $toaddress                接收方地址
     * @param $amount                   数量
     * @param $feeaddress               扣除手续费的地址(必须是本主机上的)
     * @param $currency                 服务器参数配置
     * @return null
     * Created by Red.
     * Date: 2018/10/23 16:49
     */
    function omni_funded_send($fromaddress,$pwd,$toaddress,$amount,$feeaddress,$currency){
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        if(!empty($fromaddress)&&!empty($toaddress)&&$amount>0&&!empty($feeaddress)&&!empty($currency)){
            $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
            $bitcoin->walletpassphrase($pwd, 60);
            sleep(1);
            $id = $bitcoin->omni_funded_send($fromaddress, $toaddress, 31, strval($amount),$feeaddress);
            if($id){
                $r['code']=SUCCESS;
                $r['message']="转账成功";
                $r['result']=$id;
            }else{
               $r['message']=$bitcoin->error;
            }
        }
        return $r;
    }

    /**
     * 查询地址上USDT的余额
     * @param $address          钱包地址
     * @param $currency         配置信息数组
     * @return float
     * Created by Red.
     * Date: 2018/7/26 20:33
     */
    function omni_getbalance($address,$currency){
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $r=$bitcoin->omni_getbalance($address,31);
        $money=isset($r['balance'])?$r['balance']:0;
       return $money;
    }


    /**
     * 查询某个用户的BTC钱包余额
     * @param $uid              用户uid
     * @param $currency         要查询的服务器配置信息
     * @return float              地址的余额
     * Created by Red.
     * Date: 2018/8/4 11:35
     */
    function get_balance($uid,$currency)
    {
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $money = $bitcoin->getbalance(strval($uid));
        return $money>0?$money:0;
    }
    /**
     * 根据钱包地址获取地址的BTC余额(该地址必须是在该服务器上的)
     * @param $address      地址
     * @param $currency     服务器
     * @return int
     * Created by Red.
     * Date: 2018/9/10 18:04
     */
    function get_balance_by_address($address,$currency){
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $list=$bitcoin->listaddressgroupings();
        if(!empty($list)){
            foreach ($list as $k=>$value){
                foreach ((array)$value as $kk=>$vv){
                    if($vv[0]==$address){
                        return $vv[1];
                    }
                }
            }
        }
        return 0;

    }
    /*根据BTC的地址查询BTC在服务器的account(旧版BTC适用)
    * @param $address          地址
    * @param $currency         要查询的服务器配置信息
    * @return mixed
    * Created by Red.
    * Date: 2018/9/5 14:29
    */
    function get_account_by_address($address,$currency){
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $account=$bitcoin->getaccount($address);
        return $account;
    }
    /**
     * 获取币服务的BTC和USDT版本等其它信息
     * @param $currency
     * @return mixed
     * Created by Red.
     * Date: 2018/10/27 16:21
     */
    function omni_getinfo($currency){
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $account=$bitcoin->getinfo();
        return $account;
    }

    /**
     * 设置找零方式的BTC转帐
     * @param $pwd                  钱包密码
     * @param $to_address           接收钱包
     * @param $change_address       找零地址钱包（必须在转帐服务器内的）
     * @param $money                转账数量
     * @param $currency             服务器配置
     * @return mixed                交易哈希
     * Created by Red.
     * Date: 2019/3/21 15:02
     */
    function btc_transfer($pwd, $to_address, $change_address, $money, $currency)
    {
        $debug = false;
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        // 创建交易
        $hex = $bitcoin->createrawtransaction(array(), array($to_address => $money));
        if ($debug) var_dump($hex);
        // 设置找零地址
        $hex = $bitcoin->fundrawtransaction($hex, array("changeAddress" => $change_address));
        if ($debug) var_dump($hex);
        // 解锁钱包
        $bitcoin->walletpassphrase($pwd, 60);
        // 签名
        $hex = $bitcoin->signrawtransactionwithwallet($hex['hex']);
        if ($debug) var_dump($hex);
        // 广播
        $hash_id = $bitcoin->sendrawtransaction($hex['hex']);
        return $hash_id;
    }

}