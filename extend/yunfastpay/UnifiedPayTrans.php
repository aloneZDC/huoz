<?php
namespace yunfastpay;

use think\Db;
use think\Exception;
use think\Log;

/**
 * 这个程序实现“公众号支付/小程序支付/生活号支付/H5支付/APP支付”接口调用示例
 * H5支付/APP支付，通过APP跳转至小程序再发起小程序支付实现。
 * 机构号，秘钥，请求地址，请在KFRequestUtil.php修改。
 * 2018-05-18
 */
class UnifiedPayTrans {

    public function wapPay($gmo_id, $transAmt, $browser, $outOrderId){
        try {
            $config_params = config('yunfastpay');
            /*
             * 商户号，测试时请替换成自己的测试商户号，
             * 生产需要换生产商户，如果没有请联系广东快付的技术获取。
             */
            $mchtCd = $config_params['mchtCd'];

            //交易码，固定：TRANS1119
            $reqData["trscode"] = 'TRANS1119';
            //机构号
            $reqData["orgCd"] = $config_params['orgCd'];
            //商户编号
            $reqData["mchtCd"] = $mchtCd;
            //外部订单号
            $reqData["outOrderId"] = $outOrderId; // 此处仅做示例,故以时间戳为值
            //交易金额, 单位：元
            $random_num = \app\common\model\RocketGoods::get_random($transAmt);//获取随机扣除数量（分）
            $reqData["transAmt"] = sprintf('%.2f', $transAmt - $random_num);
            //产品编号，统一下单：tran
            $reqData["proCd"] = "tran";
            //费率通道，公众号支付：1，生活号支付：1，小程序支付：7，H5支付/APP支付：7
            $reqData["chanelType"] = "2";
            $res = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
            if ($res) {
                $order = \app\common\model\GoodsOrders::where(['go_main_id' => $gmo_id])->find();
                //订单标题，非必填
                $reqData["outOrderTitle"] = $order['go_title'] . $outOrderId . "下单";
                //订单描述，非必填
                $reqData["outOrderDesc"] = "购买" . $order['go_title'];
            } else {
                //订单标题，非必填
                $reqData["outOrderTitle"] = $outOrderId . "下单";
                //订单描述，非必填
                $reqData["outOrderDesc"] = "购物";
            }
            //浏览器，区分消费者支付方式：支付宝：alipay，微信：wxpay
            $reqData["browser"] = $browser;
            //收银台回调地址，用户支付完成后显示的页面。
            $reqData["frontUrl"] = $config_params['frontUrl'];
            //订单有效时间,YYYYMMDDHHMMSS，默认1小时
            $expireTime = time() + 3600;
            $reqData["expireTime"] = date('YmdHis', $expireTime);
            //是否分账，0：不分账，1：分账。为空时默认分帐。
            $reqData["isSplitBill"] = "0";
            //微信小程序APPID
            $appid = \app\common\model\RocketConfig::getValue('wx_appid');
            if (empty($appid)) {
                $appid = $config_params['appid'];
            }
            $reqData["appid"] = $appid;

            /*isSplitBill为1时必填，isSplitBill为0时，需要填写。*/
            $itemsList = [];
            /*分账角色Item*/
            $splitBillItem = [];
            //是否子商户，固定1
//            $splitBillItem["item1"] = "1";
//            /*
//             * 分账角色，服务商:SERVICE_PROVIDER，门店: STORE，员工:STAFF，店主:STORE_OWNER
//             * 合作伙伴:PARTNER,总部:HEADQUARTER,品牌方:BRAND,分销商:DISTRIBUTOR,用户:USER,供应商:SUPPLIER
//             */
//            $splitBillItem["item2"] = "SERVICE_PROVIDER";
//            //分账接收方,接收方商户号。
//            $splitBillItem["item3"] = "MCHT100012134";
//            //手续费承担方，只能有一方承担,是	0：否，1：是，部分通道不支持,可以先问下业务。
//            $splitBillItem["item4"] =  "1";
//            //分账ID类型，固定02，userId：00，loginName：01，商户id：02，个人微信号：03
//            $splitBillItem["item5"] = "02";
//            //分账描述
//            $splitBillItem["item9"] = "平台分佣";
//            //分账金额，单位(元)
//            $splitBillItem["item10"] = "0.03";
//            //分账者省代码，可空
//            $splitBillItem["item11"] = "";
//            //分账者市代码，可空
//            $splitBillItem["item12"] = "";
//            //分账者区代码，可空
//            $splitBillItem["item13"] = "";
            /*每一个分账，添加一个分账角色，分账总金额*/
            $itemsList[] = $splitBillItem;
            //分账列表
            $reqData["itemsList"] = $itemsList;
            $res = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
            if ($res) {
                $flag = \app\common\model\YunfastpayLog::addItem($gmo_id, $res['gmo_user_id'], $reqData, 1);
                if ($flag === false) {
                    return false;
                }
            }

            /* 发送 */
            $respStr = \yunfastpay\utils\KFRequestUtil::req($reqData);
            $respData = json_decode($respStr, true);
            if("0000" == $respData["respCode"]){
                if ($res) {
                    $flag = \app\common\model\YunfastpayLog::addItem($gmo_id, $res['gmo_user_id'], $respData, 2);
                    if ($flag === false) {
                        return false;
                    }
                }
                /* 交易正常 */
                return $respData['qrData'];
//                if("100" == $respData["code"]){
//                    /*交易成功*/
//
//                }else if("102" ==  $respData["code"]){
//                    /*交易失败*/
//                    throw new Exception($respData["respMsg"]);
//                } else {
//                    /*交易状态未知，请调查询接口获取最终状态*/
//                    throw new Exception($respData["respMsg"]);
//                }
            } else {
                /* 交易异常 */
                /*交易状态未知，请调查询接口获取最终状态*/
                throw new Exception($respData["respMsg"]);
            }
        } catch (Exception $e){
            Log::write($e->getMessage());
            return false;
        }
    }
}


