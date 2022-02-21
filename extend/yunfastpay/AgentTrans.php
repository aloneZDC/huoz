<?php
namespace yunfastpay;

use think\Db;
use think\Exception;
use yunfastpay\utils\KFRequestUtil;

/**
 * 这个程序实现“代付”接口调用示例
 * 部分通道不支持延迟分账，联调需要与广东快付确认。
 * 机构号，秘钥，请求地址，请在KFRequestUtil.php修改。
 * 2018-05-18
 */
class AgentTrans {

    public function test(){
        $config_params = config('yunfastpay');
        try {
        	
            /*
            * orgCd是机构号，测试时请替换成自己的测试机构号，
            * 生产需要换生产机构号，如果没有请联系广东快付的技术获取。
            */

            static $orgCd = "201901176661391";
            /*
             * 商户号，测试时请替换成自己的测试商户号，
             * 生产需要换生产商户，如果没有请联系广东快付的技术获取。
             */
            $mchtCd = $config_params['mchtCd'];

            //交易码，代付固定：TRANS0108
            $reqData["trscode"] = "TRANS0108";
            //商户编号
            $reqData["orgCd"] = $config_params['orgCd'];;
            //商户编号
            $reqData["mchtCd"] = $mchtCd;
            //外部订单号
            $reqData["outOrderId"] = "4a1fd372b63f4ae38043cf7718263a0f";
            //产品编号，固定：tran
            $reqData["proCd"] = "agent";
            //费率通道，固定1
            $reqData["chanelType"] = "1";
            //代付金额（单位：元）
            $reqData["transAmt"] = "1";
            //账户类型  1：对私,2：对公
            $reqData["acctType"] = "1";
            //收款卡号
            $reqData["settleAcct"] = "6225052154515454501";
            //收款户名
            $reqData["settleAcctNm"] = "张三";
            //收款支行号
            $reqData["settleBankNo"] = "102100024248";
            //收款支行名
            $reqData["settleBankNm"] = "中国工商银行北京新中街支行";
            
            /* 发送 */
            $respStr = KFRequestUtil::req($reqData);
            $respData = json_decode($respStr, true);
            if("0000" == $respData["respCode"]){
                /* 交易正常 */
                echo $respData["respMsg"] . "<br/>";
                if("100" == $respData["transStatus"]){
                    /*交易成功*/
                }else if("102" ==  $respData["transStatus"]){
                    /*交易失败*/
                } else {
                    /*交易状态未知，请调查询接口获取最终状态*/
                }
            } else {
                /* 交易异常 */
                echo $respData["respMsg"] . "<br/>";
                /*交易状态未知，请调查询接口获取最终状态*/
            }
        } catch (Exception $e){
            print_r($e);
        }
    }
}

$agentTrans = new AgentTrans();
$agentTrans->test();
