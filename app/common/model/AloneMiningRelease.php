<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

/**
 * 独享矿机产币
 * Class AloneMiningRelease
 * @package app\common\model
 */
class AloneMiningRelease extends Model
{
    /**
     * 独享矿机产币
     * @param array $mining_pay 入金记录
     * @param array $mining_config 配置
     * @param int $release_num_per_tnum 获取Fil24H平均价格
     * @param array $today_start 时间
     * @return int[]
     * @throws \think\exception\PDOException
     */
    static function release($mining_pay, $mining_config, $release_num_per_tnum, $today_start)
    {
        $res = [
            'release_id' => 0,
            'release_num' => 0,
        ];

        // 应该释放数量
        $total_currency_num = keepPoint($mining_pay['archive'] * $release_num_per_tnum, 6);
        $config = \app\common\model\AloneMiningTake::where(['member_id' => $mining_pay['member_id'], 'third_id' => $mining_pay['id']])->find();
        $total_award_num = $total_currency_num;
        $fee_num = $platform_num = 0;
//        if ($mining_config['release_manager_fee_percent'] > 0) {
//            // 20% 管理手续费
//            $fee_num = keepPoint($total_currency_num * $mining_config['release_manager_fee_percent'] / 100, 6);
//            // 80% 实际到账
//            $total_award_num = keepPoint($total_currency_num - $fee_num, 6);
//        }
        if (!empty($config['take_rate']) || $mining_config['release_platform_percent'] > 0) {//平台抽点 5%
            $take_rate = !empty($config['take_rate']) ? $config['take_rate'] : $mining_config['release_platform_percent'];
            $platform_num = keepPoint($total_currency_num * $take_rate / 100, 6);
        }
        if (!empty($config['service_rate']) || $mining_config['release_fee_percent'] > 0) {//技术服务费
            $service_rate = !empty($config['service_rate']) ? $config['service_rate'] : $mining_config['release_fee_percent'];
            $total_currency_num = keepPoint($total_currency_num - $platform_num, 6);
            $fee_num = keepPoint($total_currency_num * $service_rate / 100, 6);
        }

        // 默认全部到账
        $award_num = keepPoint($total_award_num - $platform_num - $fee_num, 6);
        $total_num = $award_num;
        $award_lock_num = 0;
        if ($mining_config['release_lock_percent'] > 0) {
            // 产出数量要75%部分锁仓
            $award_lock_num = keepPoint($award_num * $mining_config['release_lock_percent'] / 100, 6);
            $award_num = keepPoint($award_num - $award_lock_num, 6);
        }
        if ($award_num < 0.000001 && $award_lock_num < 0.000001) {
            return $res;
        }

        $real_currency_user = CurrencyUser::getCurrencyUser($mining_pay['member_id'], $mining_config['release_currency_id']);
        if (empty($real_currency_user)) {
            return $res;
        }

        try {
            self::startTrans();

            if ($award_num > 0.000001) {
                $update_data = [
                    'last_release_day' => $today_start['today_start'],
                    'last_release_num' => $total_num,
                    'total_release_num' => ['inc', $award_num], //总释放到可用数量 25%
                    'total_lock_yu' => ['inc', $award_lock_num], //总释放到锁仓数量 75%
                ];
                $flag = AloneMiningPay::where(['id' => $mining_pay['id'], 'total_release_num' => $mining_pay['total_release_num']])->update($update_data);
                if (!$flag) throw new Exception("更新订单失败");
            }

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $real_currency_user['member_id'],
                'currency_id' => $real_currency_user['currency_id'],
                'num' => $award_num, //到可用数量
                'lock_num' => $award_lock_num, //到锁仓数量
                'release_time' => $today_start['today_start'],
                'third_id' => $mining_pay['id'],
                'third_num' => $release_num_per_tnum,
                'fee_num' => $fee_num, //管理手续费数量
                'platform_num' => $platform_num,//技术服务费
                'archive' => $mining_pay['archive'],//当日封存T数
                'total_num' => $total_award_num,
                'add_time' => time(),
            ]);
            if (!$item_id) throw new Exception("添加产币记录失败");

            if ($award_num > 0.000001) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($real_currency_user['member_id'], $real_currency_user['currency_id'], 6623, 'output_alone_mining', 'in', $award_num, $item_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'num' => $real_currency_user['num']])->setInc('num', $award_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            if ($award_lock_num > 0.000001) {
                // 添加奖励记录
                $item_id = AloneMiningIncome::insertGetId([
                    'member_id' => $real_currency_user['member_id'],
                    'currency_id' => $real_currency_user['currency_id'],
                    'type' => 5,
                    'num' => $award_lock_num,
                    'add_time' => time(),
                    'award_time' => $today_start['today_start'],
                    'third_id' => $mining_pay['id'],
                ]);
                if (!$item_id) throw new Exception("添加奖励记录");

                // 增加锁仓记录 增加锁仓资产
                $flag = CurrencyLockBook::add_log('alone_lock_num', 'alone_mining_lock', $real_currency_user['member_id'], $real_currency_user['currency_id'], $award_lock_num, $item_id, $total_award_num, $mining_config['release_lock_percent']);
                if (!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'alone_lock_num' => $real_currency_user['alone_lock_num']])->setInc('alone_lock_num', $award_lock_num);
                if (!$flag) throw new Exception("添加锁仓资产失败");
            }

            self::commit();
            //更新个人挖矿收益
            AloneMiningMember::where(['member_id' => $real_currency_user['member_id']])->setInc('release_num', $award_num);

            $res['release_id'] = $item_id;
            $res['release_num'] = $award_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write("独享矿机产币任务:失败" . $e->getMessage());
        }
        return $res;
    }

    /**
     * 独享矿机产币 75%锁仓180天线性释放
     * @param array $mining_pay 入金信息
     * @param array $mining_config 配置信息
     * @param int $today_start 时间
     * @return bool
     * @throws \think\exception\PDOException
     */
    static function release_lock_release($mining_pay, $mining_config, $today_start)
    {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($mining_pay['total_lock_yu'] / $mining_config['release_lock_days'], 6);
        Log::write('锁仓释放金额：' . $award_num);
        $fee_num = 0;

        $currency_user = CurrencyUser::getCurrencyUser($mining_pay['member_id'], $mining_config['release_currency_id']);
        if (empty($currency_user)) return false;

        $award_num = min($award_num, $currency_user['alone_lock_num']);
        if ($award_num < 0.000001) return false;

        try {
            self::startTrans();

            $flag = AloneMiningPay::where([
                'id' => $mining_pay['id'],
                'total_lock_yu' => $mining_pay['total_lock_yu'],
                'total_lock_num' => $mining_pay['total_lock_num'],
            ])->update([
                'total_lock_yu' => ['dec', $award_num],
                'total_lock_num' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("减少线性释放失败");

            // 添加奖励记录
            $item_id = AloneMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 4,
                'num' => $award_num,
                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $mining_config['release_lock_days'],
                'third_num' => $mining_pay['total_lock_yu'],
                'third_id' => $mining_pay['id'],
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            // 增加锁仓减少记录
            $flag = CurrencyLockBook::add_log('alone_lock_num', 'release', $currency_user['member_id'], $currency_user['currency_id'], $award_num, $mining_pay['id'], $mining_pay['total_lock_yu'], $mining_config['release_lock_days'], $currency_user['member_id']);
            if (!$flag) throw new Exception("减少锁仓账本失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6624, 'output_alone_mining_lock', 'in', $award_num, $item_id);
            if (!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $award_num],
                'alone_lock_num' => ['dec', $award_num],
            ]);
            if (!$flag) throw new Exception("添加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("独享矿机:线性释放失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 产币记录
     * @param int $member_id 用户ID
     * @param int $product_id 订单ID
     * @param int $page 页
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function release_log($member_id, $product_id, $page = 1, $rows = 10)
    {
        $mining_config = \app\common\model\AloneMiningConfig::get_key_value();
        $content = "提示：遵循Filecoin生态机制，目前每日产出Filecoin的{$mining_config['release_percent']}%立即释放、{$mining_config['release_lock_percent']}%分{$mining_config['release_lock_days']}天线性释放（即每日释放1/{$mining_config['release_lock_days']}）。";
        $result = ['num' => 0, 'list' => [], 'content' => $content];
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => $result];
        if (empty($member_id)) return $r;

        $where['a.member_id'] = $member_id;
        if ($product_id) $where['a.third_id'] = $product_id;

        $list = self::alias('a')->field('a.id,a.third_id,a.currency_id,a.num,a.lock_num,a.add_time,b.currency_name,c.product_name as name,a.archive as tnum')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "alone_mining_pay c", "a.third_id=c.id", "LEFT")
            //->join(config("database.prefix") . "alone_mining_product d", "c.product_id=d.id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        $currency_price = [];
        foreach ($list as &$item) {
            if (!isset($currency_price[$item['currency_id']])) {
                $currency_price_id = $item['currency_id'];
                if ($currency_price_id == 85) {
                    $currency_price_id = 81;
                }
                $price = CurrencyPriceTemp::get_price_currency_id($currency_price_id, 'CNY');
                $currency_price[$item['currency_id']] = $price;
            } else {
                $price = $currency_price[$item['currency_id']];
            }

            $item['total_num'] = keepPoint($item['num'] + $item['lock_num'], 6);
            $item['total_num_cny'] = keepPoint($item['total_num'] * $currency_price[$item['currency_id']], 2);
            $item['num_cny'] = keepPoint($item['num'] * $price, 2);
            $item['lock_num_cny'] = keepPoint($item['lock_num'] * $price, 2);

            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
        }
        $result['list'] = $list;
        $info = \app\common\model\AloneMiningPay::where(['member_id' => $member_id, 'id' => $product_id])->find();
        $result['num'] = keepPoint($info['total_release_num'] + $info['total_lock_num'] + $info['total_lock_yu'], 6);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

}