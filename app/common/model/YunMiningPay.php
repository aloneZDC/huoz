<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class YunMiningPay extends Model
{
    // 加速器
    static function machine_index($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("no_data"), 'result' => null];
        if ($member_id <= 0) return $r;

        $config = YunMiningConfig::get_key_value();
        $jin_currency = CurrencyUser::getCurrencyUser($member_id, 98);
        $mtk_currency = CurrencyUser::getCurrencyUser($member_id, 99);
//        $mtk_price = MtkCurrencyPrice::yesterday_price(93);
        $mtk_price = Trade::getLastTradePrice(99, 5);

        $total_num = self::where(['member_id' => $member_id, 'status' => 0])
            ->field('sum(jin_num) jin_num,sum(mtk_num) mtk_num,sum(num) num,sum(income_num) income_num')
            ->find();
        $total_base_num = self::where(['status' => 0])->field('sum(num) num')->find();

        $total_num['income_num'] = $total_num['income_num'] ? keepPoint($total_num['income_num'], 4) : '0.0000';

        $total_whole_base_num = keepPoint($total_base_num['num'] * $config['static_percent'], 4);
        $expected_profit = '0.0000';
        if ($total_whole_base_num > 0) {
            $expected_profit = keepPoint($total_num['income_num'] / $total_whole_base_num * $config['whole_num'], 4);
        }
        $r['result'] = [
            'total_profit' => $total_num['income_num'],//加速器收益
            'about_total_profit' => keepPoint($total_num['income_num'] * $mtk_price, 4),//约x金米
            'base_num' => $total_num['num'] ? keepPoint($total_num['num'], 4) : '0.0000',//结算基数
            'expected_profit' => $expected_profit,//预期今日收益
            'total_base_num' => $total_whole_base_num,//全网结算基数
            'issue_base_num' => $config['whole_num'],//今日发行
            'quota_percent' => $config['quota_percent'],//提取额度比例

            'jin_num' => keepPoint($jin_currency['num'], 4),//可用金米数量
            'mtk_num' => keepPoint($mtk_currency['num'], 4),//可用MTK数量
            'mtk_price' => keepPoint($mtk_price, 4),//当前入仓价格

            'now_jin_num' => $total_num['jin_num'] ? keepPoint($total_num['jin_num'], 4) : '0.0000',//当前质押（金米）
            'now_mtk_num' => $total_num['mtk_num'] ? keepPoint($total_num['mtk_num'], 4) : '0.0000',//当前质押（MTK）

            'min_buy' => $config['min_buy'],//购买最少数量
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }

    // 质押
    static function machine_buy($member_id, $amount)
    {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        if ($amount <= 0) return $r;

        // 获取配置
        $config = YunMiningConfig::get_key_value();

        // 判断购买数量
        if ($config['min_buy'] > $amount) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        // 获取金米资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['jin_currency_id']);
        if (empty($currency_user) || $currency_user['num'] < $amount) {
            $Currency = Currency::where(['currency_id' => $config['jin_currency_id']])->find();
            $r['message'] = $Currency['currency_name'] . lang('insufficient_balance');
            return $r;
        }

        // 获取M令牌的价格
//        $MtkCurrencyPrice = MtkCurrencyPrice::yesterday_price($config['m_currency_id']);
        $MtkCurrencyPrice = Trade::getLastTradePrice(99, 5);
        $mtk_num = keepPoint($amount / $MtkCurrencyPrice, 6);

        // 获取MTK资产
        $mtk_currency_user = CurrencyUser::getCurrencyUser($member_id, $config['mtk_currency_id']);
        if (empty($mtk_currency_user) || $mtk_currency_user['num'] < $mtk_num) {
            $Currency = Currency::where(['currency_id' => $config['mtk_currency_id']])->find();
            $r['message'] = $Currency['currency_name'] . lang('insufficient_balance');
            return $r;
        }

        try {
            self::startTrans();

            //插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'jin_num' => $amount,
                'mtk_num' => $mtk_num,
                'price' => $MtkCurrencyPrice,
                'num' => $amount,
                'quota_num' => keepPoint($amount * $config['quota_percent'], 6),
                'add_time' => time(),
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣金米
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6640, 'buy_chia_mining', 'out', $amount, $item_id);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $amount);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 扣G令牌
            $flag = AccountBook::add_accountbook($mtk_currency_user['member_id'], $mtk_currency_user['currency_id'], 6640, 'buy_chia_mining', 'out', $mtk_num, $item_id);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $mtk_currency_user['cu_id'], 'num' => $mtk_currency_user['num']])->setDec('num', $mtk_num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    // 质押记录
    static function machine_list($member_id, $type, $page)
    {
        $r = ['code' => ERROR1, 'message' => lang("no_data"), 'result' => null];
        if ($member_id <= 0) return $r;

//        $config = YunMiningConfig::get_key_value();
//        $MtkCurrencyPrice = MtkCurrencyPrice::yesterday_price($config['m_currency_id']);
        $MtkCurrencyPrice = Trade::getLastTradePrice(99, 5);

        $where = ['member_id' => $member_id];
        if ($type > 0) $where['status'] = $type - 1;
        $result = self::where($where)->page($page, 10)
            ->order('id desc')
            ->select();
        foreach ($result as &$item) {
            $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
            if ($item['cancel_time'] > 0) {
                $item['cancel_time'] = date('Y-m-d H:i:s', $item['cancel_time']);
            }

            // 极限可提
            $limit_num = keepPoint($item['quota_num'] - $item['already_quota'], 4);
            $item['surplus_num'] = $limit_num;

            // 收益可提
//            $usable_num = keepPoint($item['income_num'] - $item['already_quota'], 4);
//            $item['surplus_num'] = min($limit_num, $usable_num);

            // 收益回流状态 0未回流 1已回流
            $item['return_status'] = 0;
            if ($item['surplus_num'] <= 0) {
                $item['return_status'] = 1;
            }
            // 未提取数量(MTK）
            //$item['income_num'] = keepPoint($item['income_num'] - ($item['already_quota'] / $MtkCurrencyPrice), 4);
            //$item['already_quota'] = keepPoint($item['already_quota'] / $MtkCurrencyPrice, 4);

            //已提取数量       22.01.25修改
            $already_quota = YunMiningIncome::where(['third_id' => $item['id'], 'type' => 1])->sum('num');
            $item['already_quota'] = keepPoint($already_quota, 4);
            //未提取数量       22.01.25修改
            $income_num = YunMiningIncome::where(['third_id' => $item['id'], 'type' => 2])->sum('num');
            $item['income_num'] = keepPoint($income_num - $already_quota, 4);
        }

        $r['result'] = $result;
        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }

    // 提取收益
    static function machine_extract($member_id, $id, $amount)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if ($id <= 0 || $amount <= 0) return $r;

        // 矿机
        $YunMiningPay = YunMiningPay::where(['id' => $id, 'member_id' => $member_id])->find();
        if (empty($YunMiningPay)) return $r;

        if ($YunMiningPay['status'] != 0) {
            $r['message'] = '已解仓不能提取收益';
            return $r;
        }
        if ($YunMiningPay['already_quota'] >= $YunMiningPay['quota_num']) {
            $r['message'] = '收益已提取完';
            return $r;
        }

        // 极限可提
        $limit_num = keepPoint($YunMiningPay['quota_num'] - $YunMiningPay['already_quota'], 4);
        // 收益可提
        $MtkCurrencyPrice = Trade::getLastTradePrice(99, 5);
        $usable_num = keepPoint(($YunMiningPay['income_num'] * $MtkCurrencyPrice) - $YunMiningPay['already_quota'], 4);
        $surplus_num = min($limit_num, $usable_num);
        if ($amount > $surplus_num) {
            $r['message'] = '可提取额度不足';
            return $r;
        }

        $m_num = keepPoint($amount / $MtkCurrencyPrice, 6);
        $config = YunMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['mtk_currency_id']);
        try {
            self::startTrans();

            // 更新数据
            $flag = self::where(['id' => $id, 'member_id' => $member_id])->update([
                'already_quota' => ['inc', $amount]
            ]);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            //插入订单
            $item_id = YunMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'type' => 1,
                'num' => $m_num,
                'third_percent' => $MtkCurrencyPrice,
                'third_num' => $amount,
                'third_id' => $YunMiningPay['id'],
                'add_time' => time(),
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // MTK
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6641, 'buy_chia_mining', 'in', $m_num, $item_id);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $m_num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    // 解仓
    static function machine_cancel($member_id, $id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if ($id <= 0 || $member_id <= 0) return $r;
        $result = self::where(['member_id' => $member_id, 'id' => $id, 'status' => 0])->find();
        if (empty($result)) return $r;
        $config = YunMiningConfig::get_key_value();
        try {
            self::startTrans();
            $flag = self::where(['member_id' => $member_id, 'id' => $id, 'status' => 0])->update([
                'status' => 1,
                'cancel_time' => time(),
            ]);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // MTK
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['mtk_currency_id']);
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6642, 'buy_chia_mining', 'in', $result['mtk_num'], $result['id']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $result['mtk_num']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    // 矿机收益
    static function release_static($YunMiningPay, $mining_config)
    {
        $total_base_num = self::where(['status' => 0])->sum('num');
        $total_base_num = keepPoint($total_base_num * $mining_config['static_percent'], 6);
        $release_num = keepPoint($YunMiningPay['num'] / $total_base_num * $mining_config['whole_num'], 6);
        if ($release_num <= 0) return false;
        try {
            self::startTrans();

            //插入订单
            $item_id = YunMiningIncome::insertGetId([
                'member_id' => $YunMiningPay['member_id'],
                'type' => 2,
                'num' => $release_num,
                'third_percent' => $mining_config['whole_num'],
                'third_num' => $total_base_num,
                'third_id' => $YunMiningPay['id'],
                'add_time' => time(),
            ]);
            if (!$item_id) throw new Exception('插入订单' . $YunMiningPay['id']);

            $flag = self::where(['id' => $YunMiningPay['id'], 'income_num' => $YunMiningPay['income_num']])->setInc('income_num', $release_num);
            if (!$flag) throw new Exception('更新收益失败' . $YunMiningPay['id']);

            if ($YunMiningPay['income_num'] == $YunMiningPay['quota_num']) {
                //插入订单
                $create = YunMiningIncome::create([
                    'member_id' => $YunMiningPay['member_id'],
                    'type' => 4,
                    'num' => $release_num,
                    'third_percent' => $mining_config['whole_num'],
                    'third_num' => $total_base_num,
                    'third_id' => $YunMiningPay['id'],
                    'add_time' => time(),
                ]);
                if (!$create) throw new Exception('插入订单' . $YunMiningPay['id']);
            }

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("矿机收益:" . $e->getMessage());
        }
        return false;
    }
}