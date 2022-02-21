<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

/**
 * 独享 - 矿机封存记录
 * Class AloneMiningArchive
 * @package app\common\model
 */
class AloneMiningArchive extends Model
{
    /**
     * 获取支付信息
     * @param $member_id
     * @return array
     */
    static function pay_type($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if ($member_id <= 0) return $r;
        // 获取配置
        $alone_mining_config = AloneMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $alone_mining_config['archive_currency_id']);
        $currency_name = Currency::where(['currency_id' => $alone_mining_config['archive_currency_id']])->value('currency_name');
        $gas_fee = AloneMiningProduct::average_out();

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'preGas_1T' => keepPoint($gas_fee['preGas_1T'], 6),
            'payment_1T' => keepPoint($gas_fee['payment_1T'], 6),
            'currency_id' => $alone_mining_config['archive_currency_id'],
            'currency_name' => $currency_name,
            'currency_num' => $currency_user['num'],
        ];
        return $r;
    }

    /**
     * 矿机封存
     * @param int $member_id 用户ID
     * @param int $product_id 矿机订单ID
     * @param float $amount 封存数量
     * @param int $start_time 时间
     * @param array $mining_archive 数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function buy($member_id, $product_id, $amount, $start_time = 0, $mining_archive = [])
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if ($product_id <= 0 || $amount <= 0) return $r;
        // 查询硬件
        $alone_mining_pay = AloneMiningPay::where(['id' => $product_id, 'member_id' => $member_id])->find();
        if (empty($alone_mining_pay)) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        // 合期到期时间
        if ($alone_mining_pay['treaty_day'] > 0 && $alone_mining_pay['treaty_day'] < time()) {
            $r['message'] = lang('last_release_day_error');
            return $r;
        }

        // 获取配置
        $alone_mining_config = AloneMiningConfig::get_key_value();

        // 判断封存T数
        if (($amount + $alone_mining_pay['archive']) > $alone_mining_pay['max_tnum'] ||
            $amount < $alone_mining_config['archive_min_num']) {
            $r['message'] = lang('release_day_error');
            return $r;
        }

        // 计算支付金额
        if (empty($mining_archive['pregas']) && empty($mining_archive['payment'])) {
            $gas_fee = AloneMiningProduct::average_out();
            $preGas_1T = ($gas_fee['preGas_1T'] / 2) + $alone_mining_pay['gas_fee'];
            $pregas = keepPoint($amount * $preGas_1T, 6);
            $payment = bcmul($amount, $gas_fee['payment_1T'], 6);
            $real_pay_num = bcadd($pregas, $payment, 6);
        } else {
            $pregas = $mining_archive['pregas'];
            $payment = $mining_archive['payment'];
            $real_pay_num = $mining_archive['real_pay_num'];
        }

        // 查询账户资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $alone_mining_config['archive_currency_id']);
        if (empty($currency_user) || $currency_user['num'] < $real_pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 计算质押费解冻时间
        $thaw_time = $alone_mining_pay['stop_day'];
        if ($alone_mining_pay['stop_day'] < time()) {
            $thaw_time = $alone_mining_pay['treaty_day'];
        }

        try {
            self::startTrans();

            // 统计T数
            $flag = CommonMiningMember::addItem($member_id,$amount);
            if(!$flag) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            //插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'mining_pay_id' => $alone_mining_pay['id'],
                'tnum' => $amount,
                'pregas' => $pregas,
                'payment' => $payment,
                'real_pay_num' => $real_pay_num,
                'real_pay_currency_id' => $currency_user['currency_id'],
                'thaw_time' => $thaw_time,
                'add_time' => $start_time == 0 ? time() : $start_time + 10,  // 方便测试
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 增加封存数量
            $flag = AloneMiningPay::where(['id' => $product_id, 'member_id' => $member_id, 'archive' => $alone_mining_pay['archive']])->update([
                'archive' => ['inc', $amount],
                'total_lock_pledge' => ['inc', $payment]
            ]);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            //设置合同到期时间
            if (empty($alone_mining_pay['treaty_day'])) {
                $flag = AloneMiningPay::where(['id' => $product_id, 'member_id' => $member_id])->update([
                    'treaty_day' => $start_time + ($alone_mining_pay['cycle_time'] * 86400)
                ]);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            // 更新合期时间、开挖时间
            if ($alone_mining_pay['last_release_day'] == 0
                && $alone_mining_pay['stop_day'] == 0) {
                $stop_day = time();
                $last_release_day = $stop_day + $alone_mining_config['contract_period'] * 84600;
                $flag = AloneMiningPay::where(['id' => $product_id, 'member_id' => $member_id])->update(['stop_day' => $stop_day, 'last_release_day' => $last_release_day]);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //增加账本 扣除资产
            if ($real_pay_num > 0) {
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6621, 'archive_alone_mining', 'out', $real_pay_num, $item_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $real_pay_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

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

    /**
     * 封存记录
     * @param int $member_id 用户ID
     * @param int $product_id 矿机包ID
     * @param int $page 页
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function archive_list($member_id, $product_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if (empty($member_id) || empty($product_id)) return $r;

        $list = self::alias('a')->field('a.id,a.tnum,a.payment,a.pregas,a.add_time,a.thaw_time,a.is_thaw,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.real_pay_currency_id=b.currency_id", "LEFT")
            ->where(['a.member_id' => $member_id, 'mining_pay_id' => $product_id])
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            $value['thaw_time'] = date('Y-m-d', $value['thaw_time']);
            $value['pregas'] = '-' . $value['pregas'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 退质押币
     * @param array $mining_archive 质押记录
     * @return bool
     * @throws \think\exception\PDOException
     */
    static function refund_pledged_coins($mining_archive)
    {
        $currency_user = CurrencyUser::getCurrencyUser($mining_archive['member_id'], $mining_archive['real_pay_currency_id']);
        if (empty($currency_user)) return false;

        try {
            self::startTrans();

            $flag = AloneMiningArchive::where([
                'id' => $mining_archive['id'],
                'is_thaw' => 0
            ])->update([
                'is_thaw' => 1
            ]);
            if (!$flag) throw new Exception("修改封存记录状态失败");

            $flag = AloneMiningPay::where([
                'id' => $mining_archive['mining_pay_id'],
                'total_lock_pledge' => ['egt', $mining_archive['payment']],
            ])->update([
                'total_lock_pledge' => ['dec', $mining_archive['payment']],
                'total_thaw_pledge' => ['inc', $mining_archive['payment']],
            ]);
            if (!$flag) throw new Exception("减少封存质押失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6622, 'refund_pledged_coins', 'in', $mining_archive['payment'], $mining_archive['id']);
            if (!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $mining_archive['payment']],
            ]);
            if (!$flag) throw new Exception("添加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("独享矿机:退质押币失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 推荐奖励1代2代发放
     * @param array $mining_pay 封存信息
     * @param array $mining_config 配置信息
     * @param int $today_start 时间
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function recommand_award($mining_pay, $mining_config, $today_start)
    {
        $base_member_id = $mining_pay['member_id'];

        //直推 间推奖励
        for ($count = 1; $count <= 2; $count++) {
            $award_level = $count; //奖励类型

            $member = Member::where(['member_id' => $base_member_id])->field('pid')->find();
            if (empty($member) || $member['pid'] == 0) {
                return;
            }

            $base_member_id = $member['pid'];

            // 购买独享矿机或满存算力
            $AloneMiningPay = AloneMiningPay::where(['member_id' => $base_member_id])->find(); // 独享矿机
            $CommonMiningPay = CommonMiningPay::where(['member_id' => $base_member_id])->find(); // 传统矿机
            if (empty($CommonMiningPay) && empty($AloneMiningPay)) {
                continue;
            }

            // 固定奖励 /T
            $award_num = $mining_pay['tnum'] * $mining_config['fixed_recommand'];
            if ($award_num > 0.000001) {
                AloneMiningIncome::recommand_award($award_level, $base_member_id, $mining_config['recommand_currency_id'], $award_num, $mining_pay, $today_start);
            }
        }
    }
}