<?php
namespace yunfastpay;

use think\Db;
use think\Exception;
/**
 * 这个程序实现“退款”接口调用示例
 * 机构号，秘钥，请求地址，请在KFRequestUtil.php修改。
 * 2018-05-18
 */
class RefundTrans {

    public function test(){
        try {
            /*
             * 商户号，测试时请替换成自己的测试商户号，
             * 生产需要换生产商户，如果没有请联系广东快付的技术获取。
             */
            $mchtCd = "MCHT100011937";

            //交易码，退款固定：TRANS0107
            $reqData["trscode"] = "TRANS0107";
            //商户编号
            $reqData["mchtCd"] = $mchtCd;
            //原消费的外部订单号
            $reqData["oglOrdId"] = "4a1fd372b63f4ae38043cf7718263a0f";
            
            //原消费的交易日期
            $reqData["oglOrdDate"] = "20200810";
            //退款金额（单位:元）
            $reqData["transAmt"] = "10";

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

$refundTrans = new RefundTrans();
$refundTrans->test();