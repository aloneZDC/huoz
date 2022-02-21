<?php

namespace Alipay;

use app\common\model\GoodsMainOrders;
use app\common\model\WechatOrder;
use think\Log;

class Alipay
{
    // 支付宝支付
    static function wapPay($member_id, $order_number)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        if (empty($member_id) || empty($order_number)) return $r;

        // 获取订单信息
        $GoodOrder = GoodsMainOrders::getGoodOrder($member_id, $order_number);
        if (empty($GoodOrder)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        $OrderRecord = [
            'member_id' => $member_id,
            'gmo_id' => $GoodOrder['order_id'],
            'out_trade_no' => WechatOrder::GetRandOrderSn(),
            'total_fee' => keepPoint($GoodOrder['total_price'] * 100, 0),
            'trade_type' => 'wapPay',
            'order_status' => 1,
            'add_time' => time(),
        ];
        WechatOrder::create($OrderRecord);

        require_once EXTEND_PATH . '/Alipay/wappay/service/AlipayTradeService.php';
        require_once EXTEND_PATH . '/Alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php';

        //构造参数
        $config = config('alipay');
        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($config['app_name']);
        $payRequestBuilder->setSubject($config['app_name']);
        $payRequestBuilder->setTotalAmount($GoodOrder['total_price']);
        $payRequestBuilder->setOutTradeNo($OrderRecord['out_trade_no']);

        //电脑网站支付请求
        $aop = new \AlipayTradeService($config);
        $response = $aop->wapPay($payRequestBuilder, $config['return_url'], $config['notify_url']);
        return $response;
    }

    // 支付结果通知
    static function AliPayNotify($input)
    {
        require_once EXTEND_PATH . '/Alipay/wappay/service/AlipayTradeService.php';
        try {
//            $config = config('alipay');
//            $alipaySevice = new \AlipayTradeService($config);
//            $result = $alipaySevice->check($input);

            $WechatOrder = WechatOrder::where(['out_trade_no' => $input['out_trade_no'], 'pay_status' => 0])->find();
            if (empty($WechatOrder)) {
                Log::write('验签失败', 'ALIPAY_THORW');
                return 'fail';
            }

            // 支付状态：0未支付 1支付成功 2支付失败
            $pay_status = 2;

            // 更新商品订单状态结果
            $ResultOrder = ['error_info' => '支付失败'];
            // 支付状态
            if ($input['trade_status'] == 'TRADE_SUCCESS') {
                $total_amount = keepPoint($input['total_amount'] * 100, 0);
                if ($total_amount != $WechatOrder['total_fee']) {
                    Log::write('金额不正确', 'ALIPAY_THORW');
                    return 'fail';
                }

                if (empty($WechatOrder['transaction_id'])
                    && $WechatOrder['pay_status'] == 0) {

                    // 更新商品订单状态
                    $ResultOrder = GoodsMainOrders::wx_pay_order($WechatOrder['member_id'], $WechatOrder['gmo_id'], $input['trade_no'], 'zfbpay');
                    if ($ResultOrder['code'] == SUCCESS) {
                        $pay_status = 1;
                    }
                }

                // 更改支付订单状态
                $WechatOrder = WechatOrder::where(['out_trade_no' => $input['out_trade_no'], 'pay_status' => 0])->update([
                    'cash_fee' => $input['total_amount'],
                    'transaction_id' => $input['trade_no'],
                    'pay_status' => $pay_status,
                    'time_end' => time(),
                    'callback' => json_encode($input),
                    'order_result' => json_encode($ResultOrder)
                ]);
                if (!$WechatOrder) {
                    Log::write('更改支付订单状态失败', 'ALIPAY_THORW');
                    return 'fail';
                }

                return 'success';
            }
        } catch (\Exception $e) {
            Log::write($e->getMessage(), 'ALIPAY_THORW');
            return 'fail';
        }
    }
}