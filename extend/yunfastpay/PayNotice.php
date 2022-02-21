<?php
namespace yunfastpay;

use think\Db;
use think\Exception;
use think\Log;
use yunfastpay\utils\Des3Utils;

/**
 * 这个程序实现“异步回调”接口调用示例
 * 支付结果异步回调，交易发起方广东快付，请提供回调链接给快付技术。
 * 机构号，秘钥，请求地址，请在KFRequestUtil.php修改。
 * 2018-05-18
 */
class PayNotice {
    /*
     * secretKey是3des加密秘钥，测试时请替换成自己的测试秘钥，
     * 生产需要换生产秘钥，如果没有请联系广东快付的技术获取。
     * 这里可以使用机构秘钥或者商户秘钥。使用机构秘钥加密类型ORG，
     * 使用商户秘钥加密类型MCHT
     */

    public static function notify(){
        $config_params = config('yunfastpay');
        try {
            $msg = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
            if (empty($msg)) {
                # 如果没有数据，直接返回失败
                Log::write('快付数据有误');
                return json_encode(['respCode' => '0000', 'respMsg' => '成功']);
            }

            $reqStr = Des3Utils::decrypt($msg, $config_params['secret']);

            $respData = json_decode($reqStr, true);
            if ($config_params['debug_mode'] === true) {
                Log::write(json_encode($respData));
            }
            $res = \app\common\model\GoodsMainOrders::where(['gmo_code' => $respData["outOrderId"]])->find();
            if ($res) {
                $flag = \app\common\model\YunfastpayLog::addItem($res['gmo_id'], $res['gmo_user_id'], $respData, 3);
                if ($flag === false) {
                    return json_encode(['respCode' => '0000', 'respMsg' => '成功']);
                }
            }
            if("100" ==  $respData["transStatus"]){
                /*交易成功*/
                /*这里建议做一下金额核对。*/
                if ($res) {
                    $total_amount = $res['gmo_pay_num'];
                    $transAmt = ceil($respData['transAmt']);
                    if (ceil($total_amount) != $transAmt) {
                        Log::write('快付金额不正确');
                        return json_encode(['respCode' => '0000', 'respMsg' => '成功']);
                    }

                    // 更新商品订单状态
                    $gmo_status = \app\common\model\GoodsMainOrders::STATUS_PAID;//已付款
                    $gmo_auto_sure_time = 0;
                    if ($res['gmo_express_type'] == 3) {
                        //自提区，支付成功后，订单状态改成已发货
                        $gmo_status = \app\common\model\GoodsMainOrders::STATUS_SHIPPED;//已发货
                        $auto_sure_time = \app\common\model\ShopConfig::get_value('auto_sure_time', 10);
                        $gmo_auto_sure_time = time() + $auto_sure_time * 86400;
                    }
                    $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $res['gmo_id']])->update(['gmo_status' => $gmo_status, 'gmo_pay_time' => time(), 'gmo_auto_sure_time' => $gmo_auto_sure_time]);
                    if ($flag === false) {
                        Log::write('快付更新商品订单状态失败');
                    }
                }
            }else if("102" == $respData["transStatus"]){ 
                /*交易失败*/

            } else {
                /*未知结果，这种情况不会有。*/
            }
        } catch (Exception $e) {

        }
        return json_encode(['respCode' => '0000', 'respMsg' => '成功']);
        /*  一定要记得返回成功，否则，会继续通知，持续半小时。  */
    }
}
