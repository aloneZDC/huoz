<?php

namespace app\common\model;

use think\Exception;
use think\Log;

class MtkMiningRelease extends Base
{
    /**
     * 静态产出
     * @param array $mining_order 订单信息
     * @param array $mining_config 矿机配置
     * @param array $today_config 时间配置
     * @return bool
     * @throws \think\exception\PDOException
     */
    public static function release_static($mining_order, $mining_config, $today_config)
    {
        // 总释放
        $total_currency_num = keepPoint($mining_order['total_number'] * $mining_order['static_ratios'] / 100, 6);
        $total_currency_num = min($total_currency_num, $mining_order['surplus_power']);
        if ($total_currency_num < 0.000001) return false;

        // 手续费
        $release_service_fee = 0;
        if ($mining_config['release_service_fee'] > 0) {
            $release_service_fee = keepPoint($total_currency_num * $mining_config['release_service_fee'] / 100, 6);
        }
        // 实际到账
        $total_award_num = keepPoint($total_currency_num - $release_service_fee, 6);

        // 线性释放
        $award_lock_num = 0;
        if ($mining_config['release_lock_percent'] > 0) {
            $award_lock_num = keepPoint($total_award_num * $mining_config['release_lock_percent'] / 100, 6);
        }

        // 立即释放
        $award_num = keepPoint($total_award_num - $award_lock_num, 6);

        // 获取资产
        $real_currency_user = CurrencyUser::getCurrencyUser($mining_order['member_id'], $mining_order['currency_id']);
        if (empty($real_currency_user)) return false;

        try {
            self::startTrans();

            // 更新记录
            $flag = MtkMiningOrder::where(['id' => $mining_order['id'], 'total_release_num' => $mining_order['total_release_num']])
                ->update([
                    'last_release_day' => $today_config['today_start'],
                    'last_release_num' => $total_award_num,
                    'surplus_power' => ['dec', $total_award_num], // 总释放到锁仓剩余
                    'total_release_num' => ['inc', $award_num], //立即释放 75%
                    'total_lock_num' => ['inc', $award_lock_num], //线性释放 25%
                ]);
            if ($flag === false) throw new Exception("更新订单失败");

            $flag = MtkMiningMember::where(['member_id' => $real_currency_user['member_id']])->update([
                'total_child4' => ['inc', $award_num],
            ]);
            if ($flag === false) throw new Exception("更新奖励总量4失败");

            // 添加奖励记录
            $flag = MtkMiningIncome::create([
                'member_id' => $real_currency_user['member_id'],
                'currency_id' => $real_currency_user['currency_id'],
                'type' => 4,
                'num' => $award_lock_num,
                'third_id' => $mining_order['id'],
                'third_num' => $total_award_num,
                'third_percent' => $mining_config['release_lock_percent'],
                'add_time' => time(),
                'award_time' => $today_config['today_start'],
            ]);
            if ($flag == false) throw new Exception("添加奖励记录");

            // 添加订单
            $item_id = self::insertGetId([
                'member_id' => $real_currency_user['member_id'],
                'currency_id' => $real_currency_user['currency_id'],
                'num' => $award_num, //到可用数量
                'lock_num' => $award_lock_num, //到锁仓数量
                'release_time' => $today_config['today_start'],
                'third_id' => $mining_order['id'],
                'third_num' => $total_award_num,
                'third_percent' => $mining_config['release_lock_percent'],
                'fee_num' => $release_service_fee, //管理手续费数量
                'add_time' => time(),
            ]);
            if ($item_id == false) throw new Exception("添加释放记录失败");

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($real_currency_user['member_id'], $real_currency_user['currency_id'], 7202,
                'release_static', 'in', $award_num, $item_id);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'num' => $real_currency_user['num']])->setInc('num', $award_num);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("MTK矿机:静态产出失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 产出锁仓释放
     * @param array $mining_order 订单信息
     * @param array $mining_config 矿机配置
     * @param array $today_config 时间配置
     * @return bool
     * @throws \think\exception\PDOException
     */
    public static function release_lock_static($mining_order, $mining_config, $today_config)
    {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($mining_order['total_lock_num'] / $mining_config['lock_release_days'], 6);
        $award_num = min($award_num, $mining_order['total_lock_num']);
        if ($award_num < 0.000001) return false;

        // 手续费
        $fee_num = 0;

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($mining_order['member_id'], $mining_order['currency_id']);
        if (empty($currency_user)) return false;

        try {
            self::startTrans();

            $flag = MtkMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                'total_child5' => ['inc', $award_num],
            ]);
            if ($flag === false) throw new Exception("更新奖励总量5失败");

            $flag = MtkMiningOrder::where(['id' => $mining_order['id'], 'total_lock_num' => $mining_order['total_lock_num']])->update([
                'total_lock_num' => ['dec', $award_num],
            ]);
            if ($flag === false) throw new Exception("减少线性释放失败");

            // 添加奖励记录
            $item_id = MtkMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 5,
                'num' => $award_num,
                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_config['today_start'],
                'third_percent' => $mining_config['lock_release_days'],
                'third_num' => $mining_order['total_lock_num'],
                'third_id' => $mining_order['id'],
                'third_member_id' => $currency_user['member_id'],
            ]);
            if ($flag == false) throw new Exception("添加奖励记录");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7203, 'release_lock_static',
                'in', $award_num, $item_id);
            if ($flag === false) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $award_num],
            ]);
            if ($flag === false) throw new Exception("添加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("MTK矿机:锁仓释放失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 服务收益
     * @param array $common_mining 产币记录
     * @param array $mining_config 矿机配置
     * @param array $today_config 时间配置
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function service_reward($common_mining, $mining_config, $today_config)
    {
        $base_member_id = $common_mining['member_id'];

        //直推 间推奖励
        for ($count = 1; $count <= 3; $count++) {
            $award_level = $count; // 奖励类型
            $member = Member::where(['member_id' => $base_member_id])->field('pid')->find();
            if (empty($member) || $member['pid'] == 0) return true;
            $base_member_id = $member['pid'];

            $mtk_member = MtkMiningMember::where(['member_id' => $base_member_id, 'one_team_count' => ['egt', $award_level]])->find();
            $common_member = CommonMiningMember::where(['member_id' => $base_member_id, 'one_team_count' => ['egt', $award_level]])->find();
            if (empty($mtk_member) && empty($common_member)) continue;

            // 查询是否还剩
            $surplus_power = MtkMiningOrder::where(['member_id' => $base_member_id, 'currency_id' => $common_mining['currency_id'], 'surplus_power' => ['gt', 0]])
                ->sum('surplus_power');
            if ($surplus_power <= 0) continue;

            $award_all_percent = $mining_config['accel_ratio_' . $count];
            $total_award_num = keepPoint($common_mining['fee_num'] * $award_all_percent / 100, 6);
            $total_award_num = min($total_award_num, $surplus_power);

            // 锁仓 75%
            $award_lock_num = keepPoint($total_award_num * $mining_config['release_lock_percent'] / 100, 6);
            // 立即释放 25%
            $release_award_num = keepPoint($total_award_num - $award_lock_num, 6);

            try {
                self::startTrans();
                $flag = MtkMiningMember::where(['member_id' => $base_member_id])->update([
                    'total_child6' => ['inc', $award_lock_num],
                ]);
                if ($flag === false) throw new Exception("更新奖励总量失败");

                // 杠杆扣除
                $flag = MtkMiningOrder::service_reward($base_member_id, $total_award_num);
                if ($flag === false) throw new Exception("杠杆扣除失败");

                // 添加奖励记录
                $item_id = MtkMiningIncome::insertGetId([
                    'member_id' => $base_member_id,
                    'currency_id' => $common_mining['currency_id'],
                    'type' => 6,
                    'num' => $release_award_num,
                    'lock_num' => $award_lock_num,
                    'add_time' => time(),
                    'award_time' => $today_config['today_start'],
                    'third_percent' => 100 - $mining_config['release_lock_percent'],
                    'third_lock_percent' => $mining_config['release_lock_percent'],
                    'third_num' => $total_award_num,
                    'third_id' => $award_level,
                    'third_member_id' => $common_mining['member_id'],
                    'release_id' => $common_mining['id'],
                ]);
                if (!$item_id) throw new Exception("添加奖励记录");

                $currency_user = CurrencyUser::getCurrencyUser($base_member_id, $common_mining['currency_id']);
                if (empty($currency_user)) throw new Exception("获取用户资产失败");

                // 增加账本 增加资产
                $account_book_id = 7203 + $award_level;
                $account_book_content = 'service_reward' . $award_level;
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'],
                    $account_book_id, $account_book_content, 'in', $release_award_num, $item_id);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])
                    ->setInc('num', $release_award_num);
                if ($flag === false) throw new Exception("添加资产失败");

                self::commit();
            } catch (Exception $e) {
                self::rollback();
                Log::write("MTK矿机:服务收益失败" . $e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * 产币奖励锁仓释放
     * @param $mining_member
     * @param $mining_config
     * @param $today_config
     * @return bool
     * @throws \think\exception\PDOException
     */
    public static function service_lock_reward($mining_member, $mining_config, $today_config)
    {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($mining_member['total_child6'] / $mining_config['lock_release_days'], 6);
        $award_num = min($award_num, $mining_member['total_child6']);
        if ($award_num < 0.000001) return false;

        // 手续费
        $fee_num = 0;

        // 资产
        $currency_user = CurrencyUser::getCurrencyUser($mining_member['member_id'], $mining_config['release_currency_id']);
        if (empty($currency_user)) return false;

        try {
            self::startTrans();

            $flag = MtkMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                'total_child6' => ['dec', $award_num],
                'total_child7' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("更新奖励总量5失败");

            // 添加奖励记录
            $item_id = MtkMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 7,
                'num' => $award_num,
                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_config['today_start'],
                'third_percent' => $mining_config['lock_release_days'],
                'third_num' => $mining_member['total_child6'],
                'third_id' => $mining_member['id'],
                'third_member_id' => $currency_user['member_id'],
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7207, 'service_reward4',
                'in', $award_num, $item_id);
            if (!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("添加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("MTK矿机:产币奖励锁仓释放失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 产出明细
     * @param int $member_id 用户id
     * @param int $product_id 订单id
     * @param int $page 页
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function release_list($member_id, $product_id = 0, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];

        $where = ['a.member_id' => $member_id];
        if ($product_id) $where['a.third_id'] = $product_id;

        $list = self::alias('a')->field('a.id,a.num,a.lock_num,a.third_num,a.add_time,b.currency_name')
            ->join("currency b", "a.currency_id=b.currency_id", "left")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")
            ->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 产币 统计
     * @param int $member_id 用户id
     * @param int $product_id 订单id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function release_count($member_id, $product_id = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if (empty($member_id)) return $r;

        // 获取配置
        $mining_config = MtkMiningConfig::get_key_value();
        // 获取释放币种
        $currency_name = Currency::where(['currency_id' => $mining_config['release_currency_id']])->value('currency_name', 'MTK');

        // 统计总产币数量
        $rel_where = ['member_id' => $member_id];
        if ($product_id) $rel_where['third_id'] = $product_id;
        $mining_release = self::where($rel_where)->field([
            'sum(third_num)' => 'total_release_num',
            'sum(lock_num)' => 'total_lock_count',
        ])->find();

        // 24H加速服务收益
        $last_award_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 6, 'award_time' => todayBeginTimestamp()])->sum('third_num');

        $order_where = ['member_id' => $member_id];
        if ($product_id) $order_where['id'] = $product_id;
        $mining_order = MtkMiningOrder::where($order_where)
            ->field('total_lock_num')->find();

        // 今日锁仓
        $last_lock_num = MtkMiningIncome::where([
            'type' => 4,
            'award_time' => todayBeginTimestamp(),
        ])->where($rel_where)->sum('num');

        // 累计释放
        $last_release_num = MtkMiningIncome::where([
            'type' => 5,
            'award_time' => todayBeginTimestamp(),
        ])->where($rel_where)->sum('num');

        $mtk_member = MtkMiningMember::where(['member_id' => $member_id])->field(['total_child6', 'total_child7'])->find();
        // 累计锁仓
        $community_total_lock_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 6])->field(['sum(lock_num)' => 'total_lock_num'])->find();
        // 今日锁仓
        $community_lock_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 6, 'award_time' => todayBeginTimestamp()])->sum('lock_num');
        // 今日释放
        $community_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 7, 'award_time' => todayBeginTimestamp()])->sum('num');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            // 产币 数据统计头
            'release_count' => [
                'total_release_num' => keepPoint($last_award_num, 6),
//                'release_percent' => keepPoint(100 - $mining_config['release_lock_percent']),
//                'release_lock_percent' => $mining_config['release_lock_percent'],
//                'lock_release_days' => $mining_config['lock_release_days'],
            ],
            // 产币 线性释放明细 - 数据统计头
            'release_lock_count' => [
                'total_lock_yu' => $mining_order['total_lock_num'] ?: '0.000000', // 剩余锁仓
                'total_lock_count' => keepPoint($mining_release['total_lock_count'], 6), // 累计锁仓
                'last_lock_num' => keepPoint($last_lock_num, 6),  // 今日锁仓
                'total_lock_num' => keepPoint($mining_release['total_lock_count'] - $mining_order['total_lock_num'], 6),// 累计释放
                'last_release_num' => keepPoint($last_release_num, 6), // 今日释放
            ],
            // 算力社区 线性释放明细 - 数据统计头
            'community_head' => [
                'total_lock_yu' => keepPoint($mtk_member['total_child6'], 6), // 剩余锁仓
                'total_lock_count' => keepPoint($community_total_lock_num['total_lock_num'], 6), // 累计锁仓
                'last_lock_num' => keepPoint($community_lock_num, 6),  // 今日锁仓
                'total_lock_num' => keepPoint($mtk_member['total_child7'], 6),// 累计释放
                'last_release_num' => keepPoint($community_num, 6), // 今日释放
            ],
            'currency_name' => $currency_name
        ];
        return $r;
    }
}