<?php

namespace app\common\model;

use think\Db;
use think\Exception;

class MtkMiningPay extends Base
{
    /**
     * 商品购买
     * @param int $member_id 用户id
     * @param int $product_id 商品id
     * @param int $amount 数量
     * @param int $pay_id 支付id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function product_buy($member_id, $product_id, $amount, $pay_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        if ($product_id <= 0 || $amount <= 0) return $r;
        $product_info = MtkMiningProduct::product_find($product_id);
        if (empty($product_info)) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        if ($amount < $product_info['number_min']
            || $amount > $product_info['number_max']
        ) {
            return $r;
        }

        // 获取支付方式
        $pay_type_info = Db::name('fil_mining_pay_type')->where(['id' => $pay_id, 'status' => 0])->field(['id', 'currency_id', 'other_currency_id'])->find();
        if (empty($pay_type_info)) return $r;

        // 如果是积分支付*2
        $pay_num = $amount;
        if ($pay_type_info['currency_id'] == 98) {
            // 纯积分支付，积分要转换成MTK
            $mtk_price = MtkCurrencyPrice::yesterday_price(93);
            $pay_num = keepPoint($amount * $mtk_price * 2, 6);
        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 比较资产
        if ($currency_user['num'] < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 产币id
        $release_currency_id = MtkMiningConfig::get_value('release_currency_id',0);

        try {
            self::startTrans();

            // 统计汇总
            $flag = MtkMiningMember::add_item($member_id, $amount);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            // 订单汇总
            $total_power = keepPoint($amount * $product_info['multiple'], 6);
            $mining_order = MtkMiningOrder::where(['member_id' => $currency_user['member_id'], 'product_id' => $product_info['id']])->find();
            if (!empty($mining_order)) {
                $flag = MtkMiningOrder::where(['id' => $mining_order['id']])
                    ->update([
                        'total_number' => ['inc', $amount],
                        'total_power' => ['inc', $total_power],
                        'surplus_power' => ['inc', $total_power],
                    ]);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                $order_id = $mining_order['id'];
            } else {
                $order_id = MtkMiningOrder::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'product_id' => $product_info['id'],
                    'currency_id' => $release_currency_id,
                    'total_number' => $amount,
                    'total_power' => $total_power,
                    'static_ratios' => $product_info['static_ratios'],
                    'surplus_power' => $total_power,
                ]);
                if ($order_id === false) throw new Exception(lang('operation_failed_try_again'));
            }

            // 保存支付数据
            $pay_data = [
                'member_id' => $currency_user['member_id'],
                'product_id' => $product_info['id'],
                'order_id' => $order_id,
                'pay_num' => $amount,
                'pay_currency_id' => $pay_type_info['currency_id'],
                'add_time' => time(),
            ];

            if ($pay_id == 98) {
                $pay_data['real_pay_integral'] = $pay_num;
                $pay_data['real_pay_integral_currency_id'] = $pay_type_info['currency_id'];
            } else {
                $pay_data['real_pay_num'] = $pay_num;
                $pay_data['real_pay_currency_id'] = $pay_type_info['currency_id'];
            }
            //插入订单
            $item_id = self::insertGetId($pay_data);
            if ($item_id == false) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7201, 'buy_mtk_mining',
                'out', $pay_num, $item_id);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

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
}