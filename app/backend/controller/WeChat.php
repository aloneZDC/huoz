<?php

namespace app\backend\controller;

use app\common\model\GoodsMainOrders;
use app\common\model\WechatOrder;
use app\common\model\WechatRefund;
use WeChat\WeChatPay;

class WeChat extends AdminQuick
{
    /**
     * 支付订单 - 列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function pay_list()
    {
        $where = [];
        $member_id = input('member_id');
        if (!empty($member_id)) $where['member_id'] = $member_id;
        $order_status = input('order_status');
        if (!empty($order_status)) $where['order_status'] = $order_status - 1;
        $pay_status = input('pay_status');
        if (!empty($pay_status)) $where['pay_status'] = $pay_status - 1;

        $order_status = [1 => '处理中', 2 => '下单成功', 3 => '下单失败'];
        $pay_status = [1 => '未支付', 2 => '支付成功', 3 => '支付失败'];
        $list = WechatOrder::where($where)->order('id', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        foreach ($list as &$value) {
            $value['order_status_name'] = $order_status[$value['order_status'] + 1]; // 订单状态
            $value['pay_status_name'] = $pay_status[$value['pay_status'] + 1]; // 支付状态
            $value['total_fee'] = keepPoint(floattostr($value['total_fee'] / 100)); // 订单金额
            $value['cash_fee'] = keepPoint(floattostr($value['cash_fee'] / 100)); // 支付金额
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'order_status', 'pay_status'));
    }

    /**
     * 支付预下单失败
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pay_pre_fail()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        $id = input('id');
        if (empty($id)) return $r;

        // 判断订单是否存在
        $WechatOrder = WechatOrder::where(['id' => $id, 'order_status' => 1, 'pay_status' => 2])->find();
        if (!$WechatOrder) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 查询微信订单
        $WeChatPay = WeChatPay::OrderQuery($WechatOrder['out_trade_no']);
        if ($WeChatPay['code'] == ERROR1) return $WeChatPay;
        $resultData = $WeChatPay['result'];

        if ($resultData['trade_state'] != 'SUCCESS') {
            $r['message'] = $resultData['trade_state_desc'];
            return $r;
        }

        $pay_status = 2;// 支付状态：0未支付 1支付成功 2支付失败
        $ResultOrder = ['error_info' => '支付失败'];// 更新商品订单状态结果
        if ($resultData['total_fee'] == $WechatOrder['total_fee']
            && $resultData['cash_fee'] == $WechatOrder['cash_fee']) {
            // 更新商品订单状态
            $ResultOrder = GoodsMainOrders::wx_pay_order($WechatOrder['member_id'], $WechatOrder['gmo_id'], $resultData['transaction_id']);
            if ($ResultOrder['code'] != SUCCESS) return $ResultOrder;
            $pay_status = 1;
        }

        // 更改支付订单状态
        $WechatOrder = WechatOrder::where(['id' => $id, 'order_status' => 1, 'pay_status' => 2])->update(['cash_fee' => $resultData['cash_fee'], 'transaction_id' => $resultData['transaction_id'], 'pay_status' => $pay_status, 'time_end' => time(), 'callback' => json_encode($resultData), 'order_result' => json_encode($ResultOrder)]);
        if (!$WechatOrder) {
            $r['message'] = '更新订单失败';
            return $r;
        }

        return ['code' => SUCCESS, 'message' => '更新成功', 'result' => null];
    }

    /**
     * 退款订单 - 列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function refund_list()
    {
        $where = [];
        $member_id = input('member_id');
        if (!empty($member_id)) $where['member_id'] = $member_id;
        $status = input('status');
        if (!empty($status)) $where['status'] = $status - 1;
        $refund_status = input('refund_status');
        if (!empty($refund_status)) $where['refund_status'] = $refund_status - 1;

        $status = [1 => '处理中', 2 => '下单成功', 3 => '下单失败'];
        $refund_status = [1 => '未退款', 2 => '退款成功', 3 => '退款失败'];
        $list = WechatRefund::where($where)->order('id', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        foreach ($list as &$value) {
            $value['status_name'] = $status[$value['status'] + 1]; // 订单状态
            $value['refund_status_name'] = $refund_status[$value['refund_status'] + 1]; // 支付状态
            $value['total_fee'] = keepPoint(floattostr($value['total_fee'] / 100)); // 订单金额
            $value['refund_fee'] = keepPoint(floattostr($value['refund_fee'] / 100)); // 支付金额
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'status', 'refund_status'));
    }

    /**
     * 退款预下单失败
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refund_pre_fail()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        $id = input('id');
        if (empty($id)) return $r;

        // 判断订单是否存在
        $WechatRefund = WechatRefund::where(['id' => $id, 'status' => 1, 'refund_status' => ['in', [0, 2]]])->find();
        if (!$WechatRefund) {
            $r['message'] = lang('wechat_no_order');
            return $r;
        }

        // 查询微信订单
        $WeChatPay = WeChatPay::RefundQuery($WechatRefund['out_refund_no']);
        if ($WeChatPay['code'] == ERROR1) return $WeChatPay;
        $resultData = $WeChatPay['result'];

        $refund_status = 2; // 退款状态：0未处理 1退款成功 2退款失败
        $order_result = ['error_info' => '退款失败']; // 更新商品订单状态结果
        if ($WechatRefund['refund_fee'] == $resultData['cash_fee']
            && $WechatRefund['refund_fee'] == $resultData['refund_fee']) {
            // 更新商品订单状态
            $order_result = GoodsMainOrders::wx_refund_order($WechatRefund['member_id'], $WechatRefund['gmo_id'], $resultData['cash_fee'] / 100, $resultData['refund_fee'] / 100, $resultData['refund_id_0']);
            if ($order_result['code'] == ERROR1) return $order_result;
            $refund_status = 1;
        }

        // 更新退款订单
        $WechatRefund = WechatRefund::where(['id' => $id, 'status' => 1, 'refund_status' => ['in', [0, 2]]])->update(['refund_id' => $resultData['refund_id_0'], 'refund_status' => $refund_status, 'success_time' => time(), 'callback' => json_encode($resultData), 'order_result' => json_encode($order_result)]);
        if (!$WechatRefund) {
            $r['message'] = '更新订单失败';
            return $r;
        }

        return ['code' => SUCCESS, 'message' => '更新成功', 'result' => null];
    }


}