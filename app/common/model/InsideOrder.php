<?php

namespace app\common\model;

use think\Exception;

class InsideOrder extends Base
{
    // 自选交易 - 首页
    public static function trade_index($type = 1, $page = 1)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $where = ['a.status' => ['in', [0, 1]], 'a.type' => 2];
        if ($type == 2) $where['a.type'] = 1;

        $list = self::where($where)->alias('a')
            ->join('member b', 'b.member_id=a.member_id', 'left')
            ->join('currency c', 'c.currency_id=a.currency_id', 'left')
            ->join('currency d', 'd.currency_id=a.pay_currency_id', 'left')
            ->field('a.order_id,a.avail_num,a.price,a.type,
            c.currency_name,d.currency_name as pay_currency_name,b.ename')
            ->order('a.price,a.add_time')
            ->page($page, 10)
            ->select();
        if (empty($list)) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    // 自选交易 - 详情
    public static function trade_info($member_id, $order_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_id)) {
            return $r;
        }

        $list = self::where(['a.order_id' => $order_id, 'a.status' => ['in', [0, 1]]])->alias('a')
            ->join('member b', 'b.member_id=a.member_id', 'left')
            ->join('currency c', 'c.currency_id=a.currency_id', 'left')
            ->join('currency d', 'd.currency_id=a.pay_currency_id', 'left')
            ->field('a.order_id,a.type,a.avail_num,a.price,
            a.currency_id,a.pay_currency_id,
            c.currency_name,d.currency_name as pay_currency_name,b.ename')
            ->find();
        if (empty($list)) return $r;

        $currency_id = $list['currency_id'];
        if ($list['type'] == 2) {
            $currency_id = $list['pay_currency_id'];
        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);

        // 获取支付币种
        $currency_name = Currency::where(['currency_id' => $currency_id])->value('currency_name', 'MTK');

        unset($list['currency_id'], $list['pay_currency_id']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'order_info' => $list,
            'currency_info' => [
                'currency_id' => $currency_id,
                'currency_name' => $currency_name,
                'currency_num' => $currency_user['num']
            ]
        ];
        return $r;
    }

    // 发布广告 - 买卖
    public static function trade_buy_sell($member_id, $id, $price, $trade_num)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($id) || empty($price) || empty($trade_num)) {
            return $r;
        }

        // 配置
        $config = InsideConfig::where('a.id', $id)
            ->alias('a')
            ->join('currency b', 'b.currency_id=a.currency_id', 'left')
            ->join('currency c', 'c.currency_id=a.to_currency_id', 'left')
            ->field('a.min_num,a.trade_type,a.currency_id,a.to_currency_id,a.fee,b.currency_name,c.currency_name as to_currency_name')
            ->find();
        if (empty($config)) {
            $r['message'] = lang('unopened_exchange');
            return $r;
        }

        // 判断数量
        if ($trade_num < $config['min_num']) {
            $r['message'] = lang('inside_min_num', ['min_num' => $config['min_num']]);
            return $r;
        }

        // 判断价格
        $currency_price = MtkCurrencyPrice::yesterday_price(93);
//        $currency_price = keepPoint($currency_price * 0.8, 6);
        $currency_price = keepPoint($currency_price, 6);
        if ($price < $currency_price) {
            $r['message'] = lang('inside_min_price', ['min_price' => $currency_price]);
            return $r;
        }

        // 应支付
        $fee_num = 0;
        $pay_num = keepPoint($trade_num * $price, 6);
        $pay_currency_id = $config['to_currency_id'];
        $pay_currency_name = $config['to_currency_name'];
        $real_pay_num = $pay_num;
        if ($config['trade_type'] == 2) { // 卖
            // 计算手续费
//            $fee_num = keepPoint($pay_num * $config['fee'] / 100, 6);
            // 判断手续费余额
//            $currency_user_fee = CurrencyUser::getCurrencyUser($member_id, $config['to_currency_id']);
//            if ($currency_user_fee['num'] < $fee_num) {
//                $r['message'] = $config['to_currency_name'] . ' ' . lang('insufficient_balance');
//                return $r;
//            }

            $pay_currency_id = $config['currency_id'];
            $pay_currency_name = $config['currency_name'];
            $real_pay_num = $trade_num;
        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_currency_id);
        if ($currency_user['num'] < $real_pay_num) {
            $r['message'] = $pay_currency_name . ' ' . lang('insufficient_balance');
            return $r;
        }

        try {
            self::startTrans();

            // 插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $config['currency_id'],
                'price' => $price,
                'num' => $trade_num,
                'avail_num' => $trade_num,
                'fee' => $fee_num,
                'type' => $config['trade_type'],
                'pay_num' => $pay_num,
                'pay_currency_id' => $config['to_currency_id'],
                'add_time' => time(),
                'status' => 1,
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣手续费
//            if ($fee_num > 0) {
//                // 增加账本 扣除资产
//                $flag = AccountBook::add_accountbook($currency_user_fee['member_id'], $currency_user_fee['currency_id'], 33, 'trade_inside_content',
//                    'out', $fee_num, $item_id);
//                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
//                // 扣资产
//                $flag = CurrencyUser::where(['cu_id' => $currency_user_fee['cu_id'], 'num' => $currency_user_fee['num']])->setDec('num', $fee_num);
//                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
//            }

            // 资产进入锁仓
            // 增加账本 扣除资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 16, 'trade_inside_content',
                'out', $real_pay_num, $item_id);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])
                ->update([
                    'num' => ['dec', $real_pay_num],
                    'forzen_num' => ['inc', $real_pay_num],
                ]);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $item_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    // 撤销广告
    public static function trade_revoke($member_id, $order_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_id)) {
            return $r;
        }

        // 查询订单
        $order_info = self::where(['order_id' => $order_id, 'member_id' => $member_id])->find();
        if (empty($order_info)) {
            return $r;
        }

        // 判断状态
        if (!in_array($order_info['status'], [0, 1])) {
            return $r;
        }

        try {
            self::startTrans();

            // 判断类型
            $currency_id = $order_info['currency_id'];
            $avail_pay_num = $order_info['avail_num'];
            if ($order_info['type'] == 1) { // 买 退USDT
                $currency_id = $order_info['pay_currency_id'];
                $avail_pay_num = keepPoint($order_info['avail_num'] * $order_info['price'], 6);
            }

            // 获取资产
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
            if ($currency_user['forzen_num'] < $avail_pay_num) {
                throw new Exception(lang('insufficient_balance'));
            }

            // 退手续费（撤数量 * 单价 * 0.06）
//            if ($order_info['type'] == 2) { // 买 退USDT
//                $config = InsideConfig::where(['currency_id' => $order_info['currency_id'], 'to_currency_id' => $order_info['pay_currency_id'], 'trade_type' => 2])->find();
//                $fee_num = keepPoint($avail_pay_num * $order_info['price'] * $config['fee'] / 100, 6);
//                if ($fee_num > 0.000001) {
//                    // 获取资产
//                    $currency_user_usdt = CurrencyUser::getCurrencyUser($member_id, $order_info['pay_currency_id']);
//                    $flag = AccountBook::add_accountbook($currency_user_usdt['member_id'], $currency_user_usdt['currency_id'], 10, 'trade_inside_content',
//                        'in', $fee_num, $order_info['order_id']);
//                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
//
//                    $flag = CurrencyUser::where(['cu_id' => $currency_user_usdt['cu_id'], 'num' => $currency_user_usdt['num']])
//                        ->update([
//                            'num' => ['inc', $fee_num],
//                        ]);
//                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
//                }
//            }

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 10, 'trade_inside_content',
                'in', $avail_pay_num, $order_info['order_id']);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])
                ->update([
                    'num' => ['inc', $avail_pay_num],
                    'forzen_num' => ['dec', $avail_pay_num],
                ]);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            // 修改订单
            $flag = self::where(['order_id' => $order_info['order_id']])->update([
                'status' => 3,
                'trade_time' => time(),
            ]);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    // 我的广告
    public static function trade_order($member_id, $status = 0, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $where = ['member_id' => $member_id, 'a.status' => ['in', [0, 1]]];
        if ($status > 0) $where['a.status'] = ['in', [2, 3]];
        $list = self::field('a.order_id,a.type,a.num,a.avail_num,a.status,a.add_time,b.currency_name')
            ->alias('a')
            ->join('currency b', 'b.currency_id=a.currency_id', 'left')
            ->where($where)
            ->page($page, $rows)
            ->order("a.order_id desc")
            ->select();
        if (empty($list)) return $r;

        foreach ($list as &$item) {
            $item['status'] = $item['status'] > 1 ? $item['status'] : 1;
            $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    // 广告详情
    public static function order_info($member_id, $order_id, $page)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $order_info = [];
        if ($page == 1) {
            $order_info = self::where(['a.order_id' => $order_id, 'a.member_id' => $member_id])
                ->alias('a')
                ->field('a.order_id,a.type,a.price,a.num,a.avail_num,a.pay_num,a.add_time,a.trade_time,a.status,a.fee as pay_fee,b.currency_name,d.currency_name as pay_currency_name')
                ->join('currency b', 'b.currency_id=a.currency_id', 'left')
                ->join('currency d', 'd.currency_id=a.pay_currency_id', 'left')
                ->find();
            $order_info['add_time'] = date('Y-m-d H:i', $order_info['add_time']);

            // 订单状态
            if ($order_info['status'] == 2
                || $order_info['status'] == 3
            ) {
                $order_info['trade_time'] = date('Y-m-d H:i', $order_info['trade_time']);
            }
            $order_info['status'] = $order_info['status'] > 1 ? $order_info['status'] : 1;

            // 交易统计
            $pay_trade = InsideTrade::where(['order_id' => $order_info['order_id']])
                ->field(['sum(pay_num)' => 'pay_num', 'sum(num)' => 'num',
//                'sum(fee)' => 'fee'
                ])
                ->find();
            // 已成交
            $order_info['pay_mtk'] = keepPoint($pay_trade['num'] ?: 0, 6);
            // 已付款
            $order_info['pay_usdt'] = keepPoint($pay_trade['pay_num'] ?: 0, 6);
            // 已手续费
//        $order_info['pay_fee'] = keepPoint($pay_trade['fee'] ?: 0, 6);
        }

        // 查询交易记录
        $inside_trade = InsideTrade::where(['a.order_id' => $order_id])
            ->alias('a')
            ->join('currency b', 'b.currency_id=a.currency_id', 'left')
            ->join('currency c', 'c.currency_id=a.pay_currency_id', 'left')
            ->join('member d', 'd.member_id=a.pay_member_id', 'left')
            ->field('a.trade_id,a.type,a.price,a.pay_num,a.num,a.fee,b.currency_name,c.currency_name as pay_currency_name,d.ename,a.status,a.add_time')
            ->page($page, 10)
            ->order(['a.trade_id' => 'desc'])
            ->select();
        foreach ($inside_trade as &$item) {
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'order_info' => $order_info,
            'trade_list' => $inside_trade
        ];
        return $r;
    }

}