<?php
namespace yunfastpay;

use think\Db;
use think\Exception;
use yunfastpay\utils\KFRequestUtil;

/**
 * 这个程序实现“条码支付/刷脸支付”接口调用示例
 * 机构号，秘钥，请求地址，请在KFRequestUtil.php修改。
 * 2018-05-18
 */
class MicroPayTrans {

    public function test(){
        try {
            /*
             * 商户号，测试时请替换成自己的测试商户号，
             * 生产需要换生产商户，如果没有请联系广东快付的技术获取。
             */
            $mchtCd = "MCHT100011937";

            //交易码，条码支付/刷脸支付固定：TRANS0106
            $reqData["trscode"] = "TRANS0106";
            //商户编号
            $reqData["mchtCd"] = $mchtCd;
            //外部订单号
            $reqData["outOrderId"] = time(); // 此处仅做示例,故以时间戳为值
            //交易金额, 单位：元
            $reqData["transAmt"] = "0.1";
            //产品编号，条码聚合：tran，支付宝扫码：aliUnScanPay，支付宝刷脸：aliFacePay，微信刷脸：facePay，微信扫码：wxUnScanPay
            $reqData["proCd"] = "tran";
            //费率通道，公众号支付：1，生活号支付：1，条码：5，刷脸：6，小程序支付：7，H5支付/APP支付：7
            $reqData["chanelType"] = "1";
            //用户的付款码
            $reqData["qrData"] = "282258618861175155";
            //订单标题，非必填
            $reqData["outOrderTitle"] = "下单";
            //订单描述，非必填
            $reqData["outOrderDesc"] = "购物";
            //浏览器，区分消费者支付方式：支付宝：alipay，微信：wxpay
            $reqData["browser"] = "wxpay";
            //收银台回调地址，用户支付完成后显示的页面。
            $reqData["frontUrl"] = "https://cash.yunfastpay.com/html/#/paysucc";
            //订单有效时间,YYYYMMDDHHMMSS，默认1小时
            $reqData["expireTime"] = "20180807171001";
            //是否分账，0：不分账，1：分账。为空时默认分帐。
            $reqData["isSplitBill"] = "1";

            /*isSplitBill为1时必填，isSplitBill为0时，需要填写。*/
            $itemsList = [];
            /*分账角色Item*/
            $splitBillItem = [];
            //是否子商户，固定1
            $splitBillItem["item1"] = "1";
            /*
             * 分账角色，服务商:SERVICE_PROVIDER，门店: STORE，员工:STAFF，店主:STORE_OWNER
             * 合作伙伴:PARTNER,总部:HEADQUARTER,品牌方:BRAND,分销商:DISTRIBUTOR,用户:USER,供应商:SUPPLIER
             */
            $splitBillItem["item2"] = "SERVICE_PROVIDER";
            //分账接收方,接收方商户号。
            $splitBillItem["item3"] = "MCHT100012134";
            //手续费承担方，只能有一方承担,是	0：否，1：是，部分通道不支持,可以先问下业务。
            $splitBillItem["item4"] =  "1";
            //分账ID类型，固定02，userId：00，loginName：01，商户id：02，个人微信号：03
            $splitBillItem["item5"] = "02";
            //分账描述
            $splitBillItem["item9"] = "平台分佣";
            //分账金额，单位(元)
            $splitBillItem["item10"] = "0.03";
            //分账者省代码，可空
            $splitBillItem["item11"] = "";
            //分账者市代码，可空
            $splitBillItem["item12"] = "";
            //分账者区代码，可空
            $splitBillItem["item13"] = "";
            /*每一个分账，添加一个分账角色，分账总金额*/
            $itemsList[] = $splitBillItem;
            //分账列表
            $reqData["itemsList"] = $itemsList;

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

$microPayTrans = new MicroPayTrans();
$microPayTrans->test();