<?php

namespace WeChat;

use app\common\model\GoodsMainOrders;
use app\common\model\WechatOrder;
use app\common\model\WechatRefund;
use app\common\model\WechatTransfer;
use think\Log;

class WeChatPay
{
    /**
     * JSAPI支付
     * @param int $member_id 用户ID
     * @param string $openid 微信用户 openid
     * @param array $order_number 订单号
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function JsApiPay($member_id, $openid, $order_number)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        if (empty($member_id) || empty($openid) || empty($order_number)) return $r;

        // 获取订单信息
        $GoodOrder = GoodsMainOrders::getGoodOrder($member_id, $order_number);
        if (empty($GoodOrder)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 组装请求参数
        $config = config('wechat');
        $expand = [
            'appid' => $config['app_id'],
            'trade_type' => 'JSAPI',
            'openid' => $openid
        ];
        $responseData = self::UnifiedOrder($GoodOrder, $expand);

        // 判断预付下单结果
        if ($responseData['code'] == ERROR1) {
            $r['message'] = $responseData['message'];
            return $r;
        }

        // 组装前端需要的数据
        $time = time();
        settype($time, "string");        //jsapi支付界面,时间戳必须为字符串格式
        $resdata = [
            'appId' => strval($responseData['appid']),
            'nonceStr' => strval($responseData['nonce_str']),
            'package' => 'prepay_id=' . strval($responseData['prepay_id']),
            'signType' => 'MD5',
            'timeStamp' => $time
        ];
        $resdata['paySign'] = self::MakeSign($resdata, $config['mch_key']);

        return ['code' => SUCCESS, 'message' => lang('wechat_order_success'), 'result' => $resdata];
    }

    /**
     * H5支付
     * @param int $member_id 用户ID
     * @param array $order_number 订单号
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function MWebPay($member_id, $order_number)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        if (empty($member_id) || empty($order_number)) return $r;

        // 查询是否已经下单
        $end_time = time() - 5 * 60; // 有效期5分钟
        $wechat_order = WechatOrder::where([
            'member_id' => $member_id,
            'gmo_id' => implode(',', $order_number),
            'order_status' => 1,
            'add_time' => ['gt', $end_time],
            'trade_type' => 'MWEB'
        ])->find();
        if ($wechat_order) {
            // 还原数据
            $error_info = json_decode($wechat_order['error_info'], true);
            $result = [
                'trade_type' => $error_info['trade_type'],
                'prepay_id' => $error_info['prepay_id'],
                'mweb_url' => $error_info['mweb_url'],
            ];
            return ['code' => SUCCESS, 'message' => lang('wechat_order_success'), 'result' => $result];
        }

        // 获取订单信息
        $GoodOrder = GoodsMainOrders::getGoodOrder($member_id, $order_number);
        if (empty($GoodOrder)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 组装请求参数
        $config = config('wechat');
        $expand = [
            'appid' => $config['app_id'],
            'trade_type' => 'MWEB',
            'scene_info' => json_encode($config['h5_info'])
        ];
        $responseData = self::UnifiedOrder($GoodOrder, $expand);

        // 判断下单结果
        if ($responseData['code'] == ERROR1) {
            $r['message'] = $responseData['message'];
            return $r;
        }

        // 组装前端需要的数据
        $result = [
            'trade_type' => $responseData['result']['trade_type'],
            'prepay_id' => $responseData['result']['prepay_id'],
            'mweb_url' => $responseData['result']['mweb_url'],
        ];
        return ['code' => SUCCESS, 'message' => lang('wechat_order_success'), 'result' => $result];
    }

    /**
     * APP支付
     * @param int $member_id 用户ID
     * @param array $order_number 订单号
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function AppPay($member_id, $order_number)
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

        // 组装请求参数
        $config = config('wechat');
        $expand = [
            'appid' => $config['open_id'],
            'trade_type' => 'APP',
        ];
        $responseData = self::UnifiedOrder($GoodOrder, $expand);

        // 判断下单结果
        if ($responseData['code'] == ERROR1) {
            $r['message'] = $responseData['message'];
            return $r;
        }

        // 组装前端需要的数据
        $resultData = [
            'appid' => $config['open_id'],
            'partnerid' => $config['mch_id'],
            'prepayid' => $responseData['prepayid'],
            'package' => 'Sign=WXPay',
            'noncestr' => self::GetNonceStr(),
            'timestamp' => time(),
        ];
        $resultData['paySign'] = self::MakeSign($resultData, $config['mch_key']);

        return ['code' => SUCCESS, 'message' => lang('wechat_order_success'), 'result' => $resultData];
    }

    /**
     * 支付结果通知
     * @param string $xmlData xml数据
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function WeChatPayNotify($xmlData)
    {
        $resultData = self::XmlToArray($xmlData);

        // 判断支付状态
        if ($resultData['return_code'] == 'FAIL'
            || $resultData['result_code'] == 'FAIL') {
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 验证订单数据
        $WxPayOrder = WechatOrder::where(['out_trade_no' => $resultData['out_trade_no'], 'pay_status' => 0])->find();
        if (empty($WxPayOrder)) {
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 支付状态：0未支付 1支付成功 2支付失败
        $pay_status = 2;

        // 更新商品订单状态结果
        $ResultOrder = ['error_info' => '支付失败'];

        // 1:验证签名，2：订单金额是否与商户侧的订单金额一致
        if ($resultData['sign'] == self::NotifyCheckSign($resultData)
            && $resultData['total_fee'] == $WxPayOrder['total_fee']) {

            // TODO 系统业务处理(判断该通知是否已经处理过，如果没有处理过再进行处理，如果处理过直接返回结果成功)
            if (empty($WxPayOrder['transaction_id'])
                && $WxPayOrder['pay_status'] == 0) {

                // 更新商品订单状态
                $ResultOrder = GoodsMainOrders::wx_pay_order($WxPayOrder['member_id'], $WxPayOrder['gmo_id'], $resultData['transaction_id']);
                if ($ResultOrder['code'] == SUCCESS) {
                    $pay_status = 1;
                }
            }
        }

        // 更改支付订单状态
        $WechatOrder = WechatOrder::where('out_trade_no', $resultData['out_trade_no'])->update(['cash_fee' => $resultData['cash_fee'], 'transaction_id' => $resultData['transaction_id'], 'pay_status' => $pay_status, 'time_end' => time(), 'callback' => json_encode($resultData), 'order_result' => json_encode($ResultOrder)]);
        if (!$WechatOrder) {
            Log::write('微信支付回调|更新订单失败|支付订单号|' . $WxPayOrder['out_trade_no']);
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        return self::ArrayToXml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
    }

    /**
     * 查询订单
     * @param string $out_trade_no 订单号
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function OrderQuery($out_trade_no)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        if (empty($out_trade_no)) return $r;

        // 组装参数
        $config = config('wechat');
        $queryParams = [
            'appid' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'out_trade_no' => $out_trade_no,
            'nonce_str' => self::GetNonceStr(),
        ];

        // 生成签名
        $queryParams['sign'] = self::MakeSign($queryParams, $config['mch_key']);

        // 发送请求
        $xmlData = self::ArrayToXml($queryParams);
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $result = self::CurlPostSsl($url, $xmlData);

        // 处理返回的结果
        $responseData = self::XmlToArray($result);
        if ($responseData['return_code'] == 'FAIL') {
            $r['message'] = $responseData['return_msg'];
            return $r;
        }
        if ($responseData['return_code'] == 'SUCCESS'
            && $responseData['result_code'] == 'FAIL') {
            $r['message'] = $responseData['err_code_des'];
            return $r;
        }
        return ['code' => SUCCESS, 'result' => $responseData];
    }

    /**
     * 微信申请退款
     * @param int $member_id 用户ID
     * @param int $gmo_id 订单号
     * @param string $transaction_id 微信订单号
     * @param float $money 金额，单位元
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function WeChatRefund($member_id, $gmo_id, $transaction_id, $money = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        if (empty($member_id) || empty($gmo_id) || empty($transaction_id)) return $r;

        $WechatRefund = WechatRefund::where(['member_id' => $member_id, 'gmo_id' => $gmo_id, 'transaction_id' => $transaction_id, 'status' => 1])->find();
        if ($WechatRefund) {
            return ['code' => SUCCESS, 'message' => lang('wecaht_refund_success'), 'result' => null];
        }

        // 获取支付订单
        $WxpayOrder = WechatOrder::where(['member_id' => $member_id, 'transaction_id' => $transaction_id, 'pay_status' => 1])->find();
        if (empty($WxpayOrder)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 判断商品订单是否在支付订单中
        $goodsArr = explode(',', $WxpayOrder['gmo_id']);
        if (!in_array($gmo_id, $goodsArr)) {
            $r['message'] = lang('wechat_no_order_number');
            return $r;
        }

        // 单位换算，元换分
        $money = $money * 100;

        // 如果只有一个订单，退全部付款金额，单位分
        if (count($goodsArr) == 1) {
            $money = $WxpayOrder['total_fee'];
        }

        // 查询商品订单
        $GoodsMainOrders = GoodsMainOrders::where(['gmo_user_id' => $member_id, 'gmo_id' => $gmo_id])->find();
        if (empty($GoodsMainOrders)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 判断退款金额,订单的单位元
        if (($GoodsMainOrders['gmo_pay_num'] * 100) != $money) {
            $r['message'] = lang('wechat_refund_amount_error');
            return $r;
        }

        // 判断实际退款，单位分
        if ($money > $WxpayOrder['total_fee']) {
            $r['message'] = lang('wechat_refund_amount_error');
            return $r;
        }

        $WxpayOrder['gmo_id'] = $GoodsMainOrders['gmo_id']; // 商品订单ID
        return self::RefundExecute($WxpayOrder, $money);
    }

    /**
     * 退款结果通知
     * @param string $xmlData xml数据
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function RefundNotify($xmlData)
    {
        $xmlData = self::XmlToArray($xmlData);

        // 判断通知状态
        if ($xmlData['return_code'] == 'FAIL') {
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 解密结果信息
        $config = config('wechat');
        $ReqXmlData = openssl_decrypt(base64_decode($xmlData['req_info']), 'AES-256-ECB', MD5($config['mch_key']), OPENSSL_RAW_DATA, '');
        $resultData = self::XmlToArray($ReqXmlData);

        // 判断状态
        if ($resultData['refund_status'] != 'SUCCESS') {
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 验证数据
        $RefundOrder = WechatRefund::where(['out_refund_no' => $resultData['out_refund_no'], 'transaction_id' => $resultData['transaction_id'], 'refund_status' => 0])->find();
        if (empty($RefundOrder)) {
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 更新商品订单
        $refund_status = 2; // 退款状态：0未处理 1退款成功 2退款失败
        $order_result = ['error_info' => '退款失败']; // 更新商品订单状态结果
        if ($RefundOrder['refund_fee'] == $resultData['settlement_refund_fee']) {
            $order_result = GoodsMainOrders::wx_refund_order($RefundOrder['member_id'], $RefundOrder['gmo_id'], $RefundOrder['total_fee'] / 100, $RefundOrder['refund_fee'] / 100, $resultData['refund_id']);
            if ($order_result['code'] == SUCCESS) {
                $refund_status = 1;
            }
        }

        // 更新退款订单
        $WechatRefund = WechatRefund::where(['out_refund_no' => $resultData['out_refund_no'], 'transaction_id' => $resultData['transaction_id'], 'refund_status' => 0])->update(['refund_id' => $resultData['refund_id'], 'refund_status' => $refund_status, 'success_time' => time(), 'callback' => json_encode($resultData), 'order_result' => json_encode($order_result)]);
        if (!$WechatRefund) {
            Log::write('微信退款回调|更新订单失败|退款订单号|' . $RefundOrder['out_refund_no']);
            return self::ArrayToXml(['return_code' => 'FAIL']);
        }

        // 返回参数
        return self::ArrayToXml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
    }

    /**
     * 查询退款
     * @param string $out_refund_no 商户退款单号
     * @return array
     */
    public static function RefundQuery($out_refund_no)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        if (empty($out_refund_no)) return $r;

        // 组装参数
        $config = config('wechat');
        $queryParams = [
            'appid' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'nonce_str' => self::GetNonceStr(),
            'out_refund_no' => $out_refund_no,
        ];

        // 生成签名
        $queryParams['sign'] = self::MakeSign($queryParams, $config['mch_key']);

        // 发送请求
        $xmlData = self::ArrayToXml($queryParams);
        $url = 'https://api.mch.weixin.qq.com/pay/refundquery';
        $result = self::CurlPostSsl($url, $xmlData);

        // 处理返回的结果
        $responseData = self::XmlToArray($result);
        if ($responseData['return_code'] == 'FAIL') {
            $r['message'] = $responseData['return_msg'];
            return $r;
        }
        if ($responseData['return_code'] == 'SUCCESS'
            && $responseData['result_code'] == 'FAIL') {
            $r['message'] = $responseData['err_code_des'];
            return $r;
        }
        return ['code' => SUCCESS, 'result' => $responseData];
    }

    /**
     * 企业微信付款
     * @param string $order_number 订单号
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function WeChatTransfers($order_number)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 获取订单信息
        $transferOrder = WechatTransfer::getGoodOrder($order_number);
        if (empty($transferOrder)) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 组装参数
        $config = config('wechat');
        $transferParams = [
            'mch_appid' => $config['app_id'],
            'mchid' => $config['mch_id'],
            'nonce_str' => self::GetNonceStr(),
            'partner_trade_no' => $transferOrder['partner_trade_no'],
            'openid' => $transferOrder['wechatbind']['openid'],
            'check_name' => $transferOrder['wechatbind']['check_name'], // 校验用户姓名选项 NO_CHECK-不校验真实姓名 FORCE_CHECK-强校验真实姓名
            're_user_name' => $transferOrder['wechatbind']['re_user_name'], // 如果check_name设置为FORCE_CHECK，则必填用户真实姓名
            'amount' => $transferOrder['amount'],
            'desc' => $transferOrder['desc'],
        ];

        // 生成签名
        $transferParams['sign'] = self::MakeSign($transferParams, $config['mch_key']);

        // 发送请求
        $xmlData = self::ArrayToXml($transferParams);
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $result = self::CurlPostSsl($url, $xmlData);

        // 处理返回的结果
        $responseData = self::XmlToArray($result);
        $responseData['pay_status'] = 1;
        if ($responseData['return_code'] == 'FAIL') {
            $responseData['pay_status'] = 2;
            $r['message'] = $responseData['return_msg'];
        }
        if ($responseData['return_code'] == 'SUCCESS'
            && $responseData['result_code'] == 'FAIL') {
            $responseData['pay_status'] = 2;
            $r['message'] = $responseData['err_code_des'];
        }

        // 保存请求信息
        $WechatTransfer = WechatTransfer::UpdateOrderRecord($responseData);
        if (!$WechatTransfer) {
            Log::write('企业微信付款|更新订单失败|订单号|' . $order_number);
        }

        // 失败返回错误
        if ($responseData['pay_status'] == 2) return $r;

        $r['code'] = SUCCESS;
        $r['result'] = $responseData;
        return $r;
    }

    /**
     * 统一下单
     * @param array $goods 商品信息
     * @param array $expand 扩展参数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function UnifiedOrder($goods, $expand)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 组装参数
        $config = config('wechat');
        $unifiedOrder = [
            'mch_id' => $config['mch_id'],
            'notify_url' => $config['domain'] . $config['unified_notify_url'],
            'total_fee' => $goods['total_price'] * 100, // TODO: 测试暂时去掉
//            'total_fee' => 1,
            'nonce_str' => self::GetNonceStr(),
            'out_trade_no' => WechatOrder::GetRandOrderSn(),
            'spbill_create_ip' => request()->ip(),
            'attach' => $goods['order_id'], // 附加数据
        ];
//        $unifiedOrder['body'] = $config['app_name'] . '订单号：' . $unifiedOrder['out_trade_no'];
        $unifiedOrder['body'] = $config['app_name'];

        // 扩展
        if (!empty($expand)) {
            $unifiedOrder = array_merge($unifiedOrder, $expand);
        }

        // 生成签名
        $unifiedOrder['sign'] = self::MakeSign($unifiedOrder, $config['mch_key']);

        // 发送请求
        $xmlData = self::ArrayToXml($unifiedOrder);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result = self::CurlPostSsl($url, $xmlData);

        // 处理返回的结果
        $responseData = self::XmlToArray($result);
        $otherData['member_id'] = $goods['member_id'];
        $otherData['order_status'] = 1;
        if ($responseData['return_code'] == 'FAIL') {
            $otherData['order_status'] = 2;
            $responseData['message'] = $responseData['return_msg'];
            $r['message'] = $responseData['message'];
        }
        if ($responseData['return_code'] == 'SUCCESS'
            && $responseData['result_code'] == 'FAIL') {
            $otherData['order_status'] = 2;
            $responseData['message'] = $responseData['err_code'] . ':' . $responseData['err_code_des'];
            $r['message'] = $responseData['message'];
        }

        // 保存请求信息
        $WechatOrder = WechatOrder::SaveOrderRecord($unifiedOrder, $responseData, $otherData);
        if (!$WechatOrder) {
            Log::write('微信支付预下单|数据保存失败|订单信息|' . json_encode($unifiedOrder));
        }

        // 失败返回错误
        if ($otherData['order_status'] == 2) return $r;

        return ['code' => SUCCESS, 'message' => lang('wechat_order_success'), 'result' => $responseData];
    }

    /**
     * 退款处理
     * @param array $WechatOrder 微信订单
     * @param int $refund_fee 退款金额，单位元
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function RefundExecute($WechatOrder, $refund_fee)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 退款参数
        $config = config('wechat');
        $refundOrder = [
            'appid' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'nonce_str' => self::GetNonceStr(),
            'transaction_id' => $WechatOrder['transaction_id'],
            'out_refund_no' => WechatRefund::GetRandOrderSn(),
            'total_fee' => $WechatOrder['total_fee'],
            'refund_fee' => $refund_fee,
            'notify_url' => $config['domain'] . $config['refund_notify_url']
        ];
        $refundOrder['sign'] = self::MakeSign($refundOrder, $config['mch_key']);

        //请求数据,进行退款
        $xmlData = self::ArrayToXml($refundOrder);
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        $res = self::CurlPostSsl($url, $xmlData, true);
        if (!$res) {
            $r['message'] = 'Can\'t connect the server';
            return $r;
        }

        // 处理请求结果
        $responseData = self::XmlToArray($res);
        $responseData['status'] = 1;
        if (strval($responseData['return_code']) == 'FAIL') {
            $responseData['status'] = 2;
            $r['message'] = strval($responseData['return_msg']);
        }
        if (!empty($responseData['result_code'])
            && strval($responseData['result_code']) == 'FAIL') {
            $responseData['status'] = 2;
            $r['message'] = strval($responseData['err_code_des']);
        }

        // 保存请求信息
        $WechatRefund = WechatRefund::SaveOrderRecord($refundOrder, $WechatOrder, $responseData);
        if (!$WechatRefund) {
            Log::write('微信退款预下单|数据保存失败|订单信息|' . json_encode($refundOrder));
        }

        // 失败返回错误
        if ($responseData['status'] == 2) return $r;

        return ['code' => SUCCESS, 'message' => lang('wecaht_refund_success'), 'result' => null];
    }

    /**
     * 数组转xml
     * @param array $array 数据
     * @return string
     */
    private static function ArrayToXml($array)
    {
        $xml = "<xml>";
        foreach ($array as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<{$key}>{$val}</{$key}>";
            } else
                $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * xml转换为数组
     * @param string $xmlData xml数据
     * @return mixed
     */
    private static function XmlToArray($xmlData)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xmlData, 'SimpleXMLElement', LIBXML_NOCDATA);
        $resultArray = json_decode(json_encode($xmlstring, true), true);
        return $resultArray;
    }

    /**
     * 产生随机字符串，不长于32位
     * @param int $length 长度
     * @return string
     */
    private static function GetNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 生成签名
     * @param array $params 参数
     * @param string $key 商户密钥
     * @return string
     */
    private static function MakeSign($params, $key)
    {
        $String = self::ToUrlParams($params);
        return strtoupper(md5("{$String}&key={$key}"));
    }

    /**
     * 异步通知验签
     * @param array $params 参数
     * @return string
     */
    private static function NotifyCheckSign($params)
    {
        unset($params['sign']);
        $config = config('wechat');
        return self::MakeSign($params, $config['mch_key']);
    }

    /**
     * 格式化参数，将数组转成uri字符串
     * @param array $params 参数
     * @param bool $urlencode url解码
     * @return bool|string
     */
    private static function ToUrlParams($params, $urlencode = false)
    {
        $buff = "";
        ksort($params);
        foreach ($params as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= "&{$k }={$v }";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 1);
        }
        return $reqPar;
    }

    /**
     * 统一下单，发送请求
     * @param string $url 地址
     * @param string $xmlData 参数
     * @param false $useCert 证书
     * @param array $header 头信息
     * @param int $second 执行时间
     * @return bool|string
     */
    private static function CurlPostSsl($url, $xmlData, $useCert = false, $header = [], $second = 30)
    {
        // 设置头信息
        $header[] = "Content-type: text/xml";

        $curl = curl_init();// 初始化curl
        curl_setopt($curl, CURLOPT_TIMEOUT, $second);// 设置执行最长秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_URL, $url);// 抓取指定网页
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        // 商户证书
        if ($useCert == true) {
            $config = config('wechat');
            curl_setopt($curl, CURLOPT_SSLCERTTYPE, 'PEM');// 证书类型
            curl_setopt($curl, CURLOPT_SSLCERT, $config['ssl_cert']);// 证书位置
            curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');// CURLOPT_SSLKEY 中规定的私钥的加密类型
            curl_setopt($curl, CURLOPT_SSLKEY, $config['ssl_key']);// 证书位置
            // curl_setopt($curl, CURLOPT_CAINFO, 'PEM');
            // curl_setopt($curl, CURLOPT_CAINFO, $config['ssl_rootca']);
        }

        // 设置头部
        if (count($header) >= 1) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($curl, CURLOPT_POST, 1);// post提交方式
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlData);// 全部数据使用HTTP协议中的"POST"操作来发送

        $data = curl_exec($curl);// 执行回话
        if ($data) {
            curl_close($curl);
            return $data;
        } else {
            // $error = curl_errno($curl);
            // echo "call faild, errorCode:$error\n";
            curl_close($curl);
            return false;
        }
    }

}
