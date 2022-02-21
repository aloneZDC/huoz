<?php

namespace app\common\model;

use think\Exception;

class InsideTrade extends Base
{
    // 买卖
    public static function buy_sell($member_id, $order_id, $trade_num)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_id) || empty($trade_num)) {
            return $r;
        }

        // 获取订单详情
        $order_info = InsideOrder::where(['order_id' => $order_id, 'status' => ['elt', 1]])->find();
        if (empty($order_info)) {
            return $r;
        }

        // 不能成交自己的订单
        if ($member_id == $order_info['member_id']) {
            $r['message'] = lang('flop_order_self');
            return $r;
        }

        if ($trade_num > $order_info['avail_num']) {
            $r['message'] = lang('lan_exceeded_the_maximum_limit');
            return $r;
        }

        // 判断类型
        if ($order_info['type'] == 1) { // 出售
            // 支付币种id
            $currency_id = $order_info['currency_id'];

            // 获取资产
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
            if ($trade_num > $currency_user['num']) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }

            // 支付数量
            $pay_num = keepPoint($trade_num * $order_info['price'], 6);

            // 手续费
            $config = InsideConfig::where(['currency_id' => 93, 'trade_type' => 2])->find();
            $fee_num = keepPoint($pay_num * $config['fee'] / 100, 6);

            // 总支付
            $real_pay_num = keepPoint($pay_num - $fee_num, 6);

        } else { // 购买

            // 手续费
            $fee_num = 0;

            // 支付金额
            $pay_num = keepPoint($trade_num * $order_info['price'], 6);

            // 支付币种id
            $currency_id = $order_info['pay_currency_id'];

            // 获取资产
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
            if ($pay_num > $currency_user['num']) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }

            // 计算广告方手续费
            $config = InsideConfig::where(['currency_id' => 93, 'trade_type' => 2])->find();
            $pay_fee_num = keepPoint($pay_num * $config['fee'] / 100, 6);

            // 总支付
            $real_pay_num = keepPoint($pay_num - $pay_fee_num, 6);

        }

        try {
            self::startTrans();

            // 插入订单
            $item_id = self::insertGetId([
                'order_id' => $order_info['order_id'],
                'member_id' => $order_info['member_id'],//广告方
                'currency_id' => $order_info['currency_id'],
                'price' => $order_info['price'],
                'num' => $trade_num,
                'fee' => $fee_num,
                'type' => $order_info['type'] == 1 ? 2 : 1,
                'pay_member_id' => $member_id,
                'pay_num' => $pay_num,
                'pay_currency_id' => $order_info['pay_currency_id'],
                'status' => 1,
                'add_time' => time(),
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            $up_order = ['avail_num' => ['dec', $trade_num]];
            // 修改状态
            $avail_num = keepPoint($order_info['avail_num'] - $trade_num, 6);
            if ($avail_num <= 0) {
                $up_order['status'] = 2;
                $up_order['trade_time'] = time();
            }
            $flag = InsideOrder::where(['order_id' => $order_id, 'avail_num' => $order_info['avail_num']])->update($up_order);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            // 增加账本 扣除资产
            if ($order_info['type'] == 1) { // 出售
                // 广告方 +MTK
                $flag = AccountBook::add_accountbook($order_info['member_id'], $order_info['currency_id'], 9, 'trade_inside_content', 'in', $trade_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $order_mtk = CurrencyUser::getCurrencyUser($order_info['member_id'], $order_info['currency_id']);
                $flag = CurrencyUser::where(['cu_id' => $order_mtk['cu_id'], 'num' => $order_mtk['num']])->setInc('num', $trade_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                // 广告方 -USDT
                $order_usdt = CurrencyUser::getCurrencyUser($order_info['member_id'], $order_info['pay_currency_id']);
                if ($order_usdt['forzen_num'] < $pay_num) throw new Exception('广告方' . lang('insufficient_balance'));
                $flag = CurrencyUser::where(['cu_id' => $order_usdt['cu_id'], 'forzen_num' => $order_usdt['forzen_num']])->setDec('forzen_num', $pay_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                // 用户方 -MTK
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 9, 'trade_inside_content', 'out', $trade_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $trade_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                // 用户方 +USDT
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $order_info['pay_currency_id'], 9, 'trade_inside_content', 'in', $real_pay_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $user_usdt = CurrencyUser::getCurrencyUser($currency_user['member_id'], $order_info['pay_currency_id']);
                $flag = CurrencyUser::where(['cu_id' => $user_usdt['cu_id'], 'num' => $user_usdt['num']])->setInc('num', $real_pay_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            } else {
                // 广告方 +USDT
                $flag = AccountBook::add_accountbook($order_info['member_id'], $order_info['pay_currency_id'], 9, 'trade_inside_content', 'in', $real_pay_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $order_usdt = CurrencyUser::getCurrencyUser($order_info['member_id'], $order_info['pay_currency_id']);
                $flag = CurrencyUser::where(['cu_id' => $order_usdt['cu_id'], 'num' => $order_usdt['num']])->setInc('num', $real_pay_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                // 广告方 -MTK
                $order_mtk = CurrencyUser::getCurrencyUser($order_info['member_id'], $order_info['currency_id']);
                if ($order_mtk['forzen_num'] < $trade_num) throw new Exception('广告方' . lang('insufficient_balance'));
                $flag = CurrencyUser::where(['cu_id' => $order_mtk['cu_id'], 'forzen_num' => $order_mtk['forzen_num']])->setDec('forzen_num', $trade_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                // 用户方 +MTK
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $order_info['currency_id'], 9, 'trade_inside_content', 'in', $trade_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $user_mtk = CurrencyUser::getCurrencyUser($currency_user['member_id'], $order_info['currency_id']);
                $flag = CurrencyUser::where(['cu_id' => $user_mtk['cu_id'], 'num' => $user_mtk['num']])->setInc('num', $trade_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                // 用户方 -USDT
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 9, 'trade_inside_content', 'out', $pay_num, $item_id);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $item_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    // 订单列表
    public static function order_list($member_id, $type, $page, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $where = ['pay_member_id' => $member_id, 'type' => $type];
        if ($type == 2) $where['type'] = $type;
        $list = self::where($where)
            ->alias('a')
            ->join('member b', 'b.member_id=a.member_id', 'left')
            ->join('currency c', 'c.currency_id=a.currency_id', 'left')
            ->join('currency d', 'd.currency_id=a.pay_currency_id', 'left')
            ->field('a.trade_id,a.type,a.price,a.num,a.pay_num,a.fee,b.ename,a.status,a.add_time,c.currency_name,d.currency_name as pay_currency_name')
            ->page($page, $rows)->order(['a.trade_id' => 'desc'])->select();
        if (empty($list)) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }
}