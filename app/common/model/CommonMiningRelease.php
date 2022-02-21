<?php
//传统矿机释放
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class CommonMiningRelease extends Base
{
    /**
     * 立即释放
     * @param array $mining_pay 待释放订单
     * @param array $mining_config 矿机配置
     * @param int $release_num_per_tnum 每T释放数量
     * @param array $today_start 时间配置
     * @return int[]
     * @throws \think\exception\PDOException
     */
    public static function release($mining_pay, $mining_config, $release_num_per_tnum, $today_start)
    {
        $real_currency_id = $mining_config['release_currency_id'];
        // 应该释放数量
        $total_currency_num = keepPoint($mining_pay['tnum'] * $release_num_per_tnum, 6);

        $total_award_num = $total_currency_num;
        $fee_num = 0;
        if ($mining_config['release_service_fee'] > 0) {
            // 20% 管理手续费
            $fee_num = keepPoint($total_currency_num * $mining_config['release_service_fee'] / 100, 6);
            // 80% 实际到账
            $total_award_num = keepPoint($total_currency_num - $fee_num, 6);
        }

        // 默认全部到账
        $award_num = $total_award_num;
        $award_lock_num = 0;
        if ($mining_config['release_lock_percent'] > 0) {
            // 产出数量要75%部分锁仓
            $award_lock_num = keepPoint($award_num * $mining_config['release_lock_percent'] / 100, 6);
            $award_num = keepPoint($award_num - $award_lock_num, 6);
        }

        if ($award_num < 0.000001
            && $award_lock_num < 0.000001
        ) return false;

        $real_currency_user = CurrencyUser::getCurrencyUser($mining_pay['member_id'], $real_currency_id);
        if (empty($real_currency_user)) return false;

        try {
            self::startTrans();

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $mining_pay['member_id'],
                'currency_id' => $real_currency_id,
                'num' => $award_num, //到可用数量
                'lock_num' => $award_lock_num, //到锁仓数量
                'release_time' => $today_start['today_start'],
                'third_id' => $mining_pay['id'],
                'third_percent' => $mining_config['release_lock_percent'],
                'third_num' => $release_num_per_tnum,
                'fee_num' => $fee_num, //管理手续费数量
                'add_time' => time(),
                'is_recommand' => $mining_pay['real_pay_currency_id'] != 5 ? 1 : 0,// 如果是积分支付，没有奖励
            ]);
            if (!$item_id) throw new Exception("添加释放记录失败");

            if ($award_num > 0.000001) {
                $update_data = [
                    'last_release_day' => $today_start['today_start'],
                    'last_release_num' => $total_award_num,
                    'total_release_num' => ['inc', $award_num], //总释放到可用数量 25%
                    'total_lock_num' => ['inc', $award_lock_num], //总释放到锁仓数量 75%
                    'total_lock_yu' => ['inc', $award_lock_num], //总释放到锁仓剩余
                ];
                $flag = CommonMiningPay::where(['id' => $mining_pay['id'], 'total_release_num' => $mining_pay['total_release_num']])->update($update_data);
                if (!$flag) throw new Exception("更新订单失败");

                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($real_currency_user['member_id'], $real_currency_user['currency_id'],
                    6507, 'common_mining_release', 'in', $award_num, $item_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'num' => $real_currency_user['num']])->setInc('num', $award_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            if ($award_lock_num > 0.000001) {
                // 添加奖励记录
                $item_id = CommonMiningIncome::insertGetId([
                    'member_id' => $real_currency_user['member_id'],
                    'currency_id' => $real_currency_user['currency_id'],
                    'type' => 4,
                    'num' => $award_lock_num,
                    'third_id' => $mining_pay['id'],
                    'third_num' => $total_award_num,
                    'third_percent' => $mining_config['release_lock_percent'],
                    'add_time' => time(),
                    'award_time' => $today_start['today_start'],
                ]);
                if (!$item_id) throw new Exception("添加奖励记录");

                $flag = CommonMiningMember::where(['member_id' => $real_currency_user['member_id']])->update([
                    'total_child4' => ['inc', $award_lock_num],
                ]);
                if (!$flag) throw new Exception("更新奖励总量4失败");

                // 增加锁仓记录 增加锁仓资产
                $flag = CurrencyLockBook::add_log('release_lock', 'common_mining_award_lock', $real_currency_user['member_id'],
                    $real_currency_user['currency_id'], $award_lock_num, $item_id, $total_award_num, $mining_config['release_lock_percent'], $item_id);
                if (!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'release_lock' => $real_currency_user['release_lock']])
                    ->setInc('release_lock', $award_lock_num);
                if (!$flag) throw new Exception("添加锁仓资产失败");
            }

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("矿机释放任务:失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 产出锁仓释放
     * @param array $mining_pay 矿机订单
     * @param array $mining_config 矿机配置
     * @param array $today_config 时间配置
     * @return bool
     * @throws \think\exception\PDOException
     */
    public static function release_lock_release($mining_pay, $mining_config, $today_config)
    {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($mining_pay['total_lock_yu'] / $mining_config['lock_release_days'], 6);
        $fee_num = 0;

        $currency_user = CurrencyUser::getCurrencyUser($mining_pay['member_id'], $mining_config['release_currency_id']);
        if (empty($currency_user)) return false;

        $award_num = min($award_num, $currency_user['release_lock']);
        if ($award_num < 0.000001) return false;

        try {
            self::startTrans();

            $flag = CommonMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                'total_child5' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("更新奖励总量5失败");

            $flag = CommonMiningPay::where(['id' => $mining_pay['id'], 'total_lock_yu' => $mining_pay['total_lock_yu']])->update([
                'total_lock_yu' => ['dec', $award_num],
            ]);
            if (!$flag) throw new Exception("减少线性释放失败");

            // 添加奖励记录
            $item_id = CommonMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 5,
                'num' => $award_num,
                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_config['today_start'],
                'third_percent' => $mining_config['lock_release_days'],
                'third_num' => $mining_pay['total_lock_yu'],
                'third_id' => $mining_pay['id'],
                'third_member_id' => $currency_user['member_id'],
                'release_id' => $mining_pay['id'],
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            // 增加锁仓减少记录
            $flag = CurrencyLockBook::add_log('release_lock', 'release', $currency_user['member_id'], $currency_user['currency_id'], $award_num, $mining_pay['id'],
                $mining_pay['total_lock_yu'], $mining_config['lock_release_days'], $currency_user['member_id']);
            if (!$flag) throw new Exception("减少锁仓账本失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6508, 'common_mining_award_lock',
                'in', $award_num, $item_id);
            if (!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $award_num],
                'release_lock' => ['dec', $award_num],
            ]);
            if (!$flag) throw new Exception("添加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败" . $e->getMessage());
        }
        return false;
    }

    // 全球锁仓释放
    static function global_lock_release($currency_user, $common_mining_config, $today_start)
    {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($currency_user['global_lock'] / $common_mining_config['global_lock_days'], 6);
        $fee_num = 0;

        $award_num = min($award_num, $currency_user['global_lock']);
        if ($award_num < 0.000001) return false;

        try {
            self::startTrans();

            $flag = CommonMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                'total_child8' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("更新奖励总量6失败");

            // 添加奖励记录
            $item_id = CommonMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 8,
                'num' => $award_num,
                'lock_num' => 0,
                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $common_mining_config['global_lock_days'],
                'third_lock_percent' => 0,
                'third_num' => $currency_user['global_lock'],
                'third_id' => $currency_user['cu_id'],
                'third_member_id' => $currency_user['member_id'],
                'release_id' => 0,
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            // 增加锁仓减少记录
            $flag = CurrencyLockBook::add_log('global_lock', 'release', $currency_user['member_id'], $currency_user['currency_id'], $award_num, $currency_user['cu_id'], $currency_user['global_lock'], $common_mining_config['global_lock_days'], $currency_user['member_id']);
            if (!$flag) throw new Exception("减少锁仓账本失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6510, 'common_mining_award8', 'in', $award_num, $item_id, 0);
            if (!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                'num' => ['inc', $award_num],
                'global_lock' => ['dec', $award_num],
            ]);
            if (!$flag) throw new Exception("添加资产失败");


            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 统计今日新增服务费
     * @param $today_config
     * @return string
     */
    static function today_feenum($today_config)
    {
        // 满存算力手续费
        $common_fee = self::where([
            'fee_num' => ['gt', 0],
            'release_time' => ['between', [$today_config['today_start'], $today_config['today_end']]],
        ])->sum('fee_num');

        // 独享矿机手续费
        $alone_fee = AloneMiningRelease::where([
            'fee_num' => ['gt', 0],
            'release_time' => ['between', [$today_config['today_start'], $today_config['today_end']]],
        ])->sum('fee_num');

        return bcadd($common_fee, $alone_fee, 6);
    }

    public static function getList($member_id, $common_mining_id = 0, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id' => $member_id,
        ];
        if ($common_mining_id) {
            $where['a.third_id'] = $common_mining_id;
        }

        $list = self::alias('a')->field('a.id,a.currency_id,a.num,a.lock_num,third_id,a.add_time,b.currency_name,c.tnum')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "common_mining_pay c", "a.third_id=c.id", "LEFT")
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

            $item['title'] = lang('common_mining_release');
            $item['add_time'] = date('m-d H:i', $item['add_time']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取FIL/USDT交易价格
     * @param int $currency_id 币种ID
     * @param int $currency_trade_id 交易币种ID
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function TradePrice($currency_id, $currency_trade_id)
    {
        $trade_price = \app\common\model\Trade::getLastTradePrice($currency_id, $currency_trade_id);
        $autoTrade = Db::name('currency_autotrade')->where(['currency_id' => $currency_id, 'trade_currency_id' => $currency_trade_id, 'is_autotrade' => 1, 'kline_huobi' => 1])->find();
        if ($autoTrade) {
            $where = [
                'type' => 60,
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
            ];
            $kline = Kline::where($where)->order('add_time', 'DESC')->find();
            if ($kline) {
                $trade_price = $kline['close_price'];
            }
        }
        return $trade_price;
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function realcurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function pay()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_currency_id', 'currency_id')->field('currency_id,currency_name');
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

            $award_all_percent = $mining_config['accel_ratio_' . $count];
            $total_award_num = keepPoint($common_mining['fee_num'] * $award_all_percent / 100, 6);

            // 锁仓 75%
            $award_lock_num = keepPoint($total_award_num * $mining_config['release_lock_percent'] / 100, 6);
            // 立即释放 25%
            $release_award_num = keepPoint($total_award_num - $award_lock_num, 6);

            try {
                self::startTrans();
                $flag = CommonMiningMember::where(['member_id' => $base_member_id])->update([
                    'total_child6' => ['inc', $award_lock_num],
                ]);
                if ($flag === false) throw new Exception("更新奖励总量失败");

                // 添加奖励记录
                $item_id = CommonMiningIncome::insertGetId([
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
                $account_book_id = '665' . $award_level;
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
                Log::write("满存算力:服务收益失败" . $e->getMessage());
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

            $flag = CommonMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                'total_child6' => ['dec', $award_num],
                'total_child7' => ['inc', $award_num],
            ]);
            if (!$flag) throw new Exception("更新奖励总量5失败");

            // 添加奖励记录
            $item_id = CommonMiningIncome::insertGetId([
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
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6654, 'service_reward4',
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
            Log::write("满存算力:产币奖励锁仓释放失败" . $e->getMessage());
        }
        return false;
    }
}
