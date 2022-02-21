<?php
//Fil项目 涡轮增压

namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class FilMining extends Base
{
    const STATUS_OK = 0; //释放中
    const STATUS_EXPIRED = 1; //释放完毕

    const MINING_NEW = 1; //新矿机
    const MINING_OLD = 2; //旧矿机

    /**
     * 支付方式
     * @param int $member_id 用户ID
     * @param int $type Chia
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function payType($member_id, $type = 1)
    {
        $where = [];
        $order = 'asc';
        // ipfs矿机组合支付
        if ($type == 1) {
            $where['id'] = ['in', [1, 2]];
        } // mtk矿机组合支付
        elseif ($type == 2) {
            $where['id'] = ['in', [2, 4]];
            $order = 'desc';
        }

        $r = ['code' => ERROR1, 'message' => lang('lan_No_data')];
        $fil_pay_types = Db::name('fil_mining_pay_type')->where($where)->where(['status' => 0])->field(['id', 'currency_id', 'other_currency_id'])
            ->order('id ' . $order)
            ->select();
        if (empty($fil_pay_types)) return $r;

        $currency_list = [];
        foreach ($fil_pay_types as $val) {
            $val['currency_price'] = 1;
            if ($val['currency_id'] == 98
                && $type == 1
            ) {
                $val['currency_price'] = 1;
            } elseif ($val['currency_id'] == 98
                && $type == 2
            ) {
                $yesterday_price = MtkCurrencyPrice::yesterday_price(93);
                $val['currency_price'] = keepPoint($yesterday_price * 2, 6);
            }

            if ($val['other_currency_id'] > 0) {
                $warrant_currency1 = Currency::where(['currency_id' => $val['currency_id']])->field(['currency_id', 'currency_name'])->find();
                $warrant_currency2 = Currency::where(['currency_id' => $val['other_currency_id']])->field(['currency_id', 'currency_name'])->find();

                $CurrencyUser = CurrencyUser::getCurrencyUser($member_id, $val['currency_id']);
                $to_CurrencyUser = CurrencyUser::getCurrencyUser($member_id, $val['other_currency_id']);
                $currency_list[] = [
                    'id' => $val['id'],
                    'currency_id' => $warrant_currency1['currency_id'],
                    'currency_name' => $warrant_currency1['currency_name'],
                    'to_currency_id' => $warrant_currency2['currency_id'],
                    'to_currency_name' => $warrant_currency2['currency_name'],
                    'currency_num' => $CurrencyUser['num'],
                    'to_currency_num' => $to_CurrencyUser['num'],
                    'currency_price' => $val['currency_price'],
                ];
            } else {
                $warrant_currency = Currency::where(['currency_id' => $val['currency_id']])->field(['currency_id', 'currency_name'])->find();
                if ($warrant_currency) {
                    $CurrencyUser = CurrencyUser::getCurrencyUser($member_id, $val['currency_id']);
                    $currency_list[] = [
                        'id' => $val['id'],
                        'currency_id' => $warrant_currency['currency_id'],
                        'currency_name' => $warrant_currency['currency_name'],
                        'currency_num' => $CurrencyUser['num'],
                        'currency_price' => $val['currency_price'],
                    ];
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $currency_list;
        return $r;
    }

    static function config($member_id)
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang('success');
        $r['result'] = null;

        $config = FilMiningConfig::get_key_value();
        if (!empty($config)) {
            $pay_currency = Currency::where(['currency_id' => $config['real_pay_currency_id']])->field('currency_id,currency_name')->find();
            $pay_currency_user = CurrencyUser::getCurrencyUser($member_id, $config['real_pay_currency_id']);

            $yestoday = todayBeginTimestamp();

            // 昨日收益总量
//            $yestoday_income = FilMiningIncome::where(['member_id'=>$member_id,'currency_id'=>$config['pay_currency_id'],'award_time'=>$yestoday])->sum('num');

            // 昨日释放
            $yestoday_release = FilMiningRelease::alias('a')->field('a.*,b.currency_name')->where(['a.member_id' => $member_id, 'a.currency_id' => $config['pay_currency_id'], 'a.release_time' => $yestoday])
                ->join(config("database.prefix") . "currency b", "a.real_currency_id=b.currency_id", "LEFT")
                ->find();

            // 总释放量
            $total_release = FilMiningRelease::where(['member_id' => $member_id, 'currency_id' => $config['pay_currency_id']])
                ->sum('real_currency_num');

            $fil_mining = FilMining::alias('a')->field('a.*,b.currency_name')->where(['a.member_id' => $member_id, 'a.currency_id' => $config['pay_currency_id']])
                ->join(config("database.prefix") . "currency b", "a.real_currency_id=b.currency_id", "LEFT")
                ->find();

            // 今日开采率
            if ($fil_mining && $fil_mining['release_percent_day'] == $yestoday) {
                $percent = $fil_mining['release_percent'] . ''; //昨日开采率
            } else {
                $percent = '--';
            }

            // 明日开采率
            $first_release_percent = FilMiningLevel::getMonthFirstPercent();
            $all_month_percent = FilMiningLevel::getMonthAllPercent();
            $next_percent = empty($fil_mining) ? $first_release_percent : FilMiningLevel::getCurMonthPercent($all_month_percent, $fil_mining['release_start_day'], $yestoday + 86400);

            $level_name = 'V0';
            if ($fil_mining && $fil_mining['level'] > 0) {
                $fil_level = FilMiningLevel::where(['level_id' => $fil_mining['level']])->find();
                if (!empty($fil_level)) $level_name = $fil_level['level_name'];
            }

            $all_income = $static_income = $new6_income = 0;
            $all_top = $all_yu = 0;
            if ($fil_mining) {
                $all_income = FilMining::getTotalIncome($fil_mining);

                // 大于3倍出局
                if ($all_income > $fil_mining['pay_num'] * $config['total_multiple']) {
                    $static_income = $fil_mining['pay_num'] * $config['total_multiple'];
                    $new6_income = keepPoint($all_income - $fil_mining['pay_num'] * $config['total_multiple'], 6);
                } else {
                    $static_income = $all_income;
                    $new6_income = 0;
                }

                $all_top = $fil_mining['pay_num'] * $config['new_help_max_multiple'];
                $all_yu = keepPoint($all_top - $all_income, 6);
            }

            $r['result'] = [
                'currency_nick_name' => $config['currency_nick_name'],
                'currency_name' => $pay_currency ? $pay_currency['currency_name'] : '',
                'currency_num' => $pay_currency_user ? $pay_currency_user['num'] : 0,
                'pay_num' => $fil_mining ? $fil_mining['pay_num'] : 0, //入金数量
                'pay_multiple' => $config['total_multiple'], //总倍数
                'pay_multiple_show' => $config['new_help_max_multiple'], //总倍数
                'fil_mining_id' => $fil_mining ? $fil_mining['id'] : 0,
                'next_currency_name' => $fil_mining ? $fil_mining['currency_name'] : '', //预开采币种
                'next_release_percent' => $config['new_fixed_release_percent'], //预开采比例 $next_percent
                'level_id' => $fil_mining ? $fil_mining['level'] : 0,
                'level_name' => $level_name,
                'total_income' => $all_income,
                'release' => [
                    'release_percent' => $config['new_fixed_release_percent'], //当前释放比例$percent
                    'release_percent_top' => $config['new_fixed_release_percent'], //最大释放比例 FilMiningLevel::getMonthMaxPercent()
                    'yestoday_release' => $yestoday_release ? $yestoday_release['real_currency_num'] : 0, //昨日涡轮
                    'yestoday_release_currency_name' => $yestoday_release ? $yestoday_release['currency_name'] : '', //昨日涡轮
//                    'total_release' => $fil_mining ? $fil_mining['total_release'] : 0, ////总涡轮
                    'total_release' => $total_release ? $total_release : 0, ////总涡轮
                ],
                'income' => [
                    'total_income_top' => $fil_mining ? $fil_mining['pay_num'] * $config['total_multiple'] : 0, //奖励获得封顶
                    'total_income' => $static_income, //总奖励 涡轮+助力+动力
                    'total_income1' => $fil_mining ? $fil_mining['total_child15'] : 0, //新助力
//                    'total_income2' => $fil_mining ? keepPoint($fil_mining['total_child4'] + $fil_mining['total_child5'],6) : 0, // 动力
                    'total_income2' => $fil_mining ? $fil_mining['total_child4'] : 0, // 级差奖励
                    'total_child5' => $fil_mining ? $fil_mining['total_child5'] : 0, // 加权平分奖励
                    'total_income_recommand' => $fil_mining ? keepPoint($fil_mining['total_child11'] + $fil_mining['total_child12'] + $fil_mining['total_child13'] + $fil_mining['total_child16']) : 0, //总推荐
                ],
                'income_new6' => [
                    'total_num' => $fil_mining ? $fil_mining['pay_num'] * $config['new_help_max_multiple_show'] : 0,
                    'curr_num' => $new6_income,
                ],
                'income_summary' => [
                    'total_income' => $all_income, //当前总收入
                    'total_top' => $all_top,
                    'total_yu' => $all_yu,
                ],
            ];
        }
        return $r;
    }

    //入金
    static function buy($member_id, $pay_num, $pay_id, $add_time = 0)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_close');
        $r['result'] = null;

        $config = FilMiningConfig::get_key_value();
        if (empty($config)) return $r;

        if ($config['pay_open'] != 1) return $r;

        if (empty($add_time)) $add_time = time();

        $pay_num = intval($pay_num);
        // 20210225 100的倍数 改为>100的整数
        if ($pay_num <= 0 || $pay_num < $config['pay_multiple_base']) {
            $r['message'] = lang('fil_mining_pay_base', ['num' => $config['pay_multiple_base']]);
            return $r;
        }

        //可释放数量
        $total_num = $pay_num * $config['total_multiple'];

        // 支付类型
        if (empty($pay_id)) {
            $r['message'] = lang('parameter_error');
            return $r;
        }
        $pay_type_info = Db::name('fil_mining_pay_type')->where(['status' => 0, 'id' => $pay_id])->field(['id', 'currency_id', 'other_currency_id'])->find();
        if (empty($pay_type_info)) return $r;

        // 判断组合支付
        $other_currency_num = 0;
        if ($pay_type_info['other_currency_id'] > 0) {
            $other_currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['other_currency_id']);
            if (!empty($currency_user) || $other_currency_user['num'] > 0) {
                $other_currency_num = $other_currency_user['num'];
            }
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        $currency_user_num = bcadd($currency_user['num'], $other_currency_num, 6);
        if ($currency_user_num < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //余额不足 实际扣除币种
//        $currency_user = CurrencyUser::getCurrencyUser($member_id,$config['real_pay_currency_id']);
//        if(empty($currency_user) || $currency_user['num']<$pay_num) {
//            $r['message'] = lang('insufficient_balance');
//            return $r;
//        }

        // 入金后币种
        $pay_currency_id = $config['pay_currency_id'];

        $fil_mining = self::where(['member_id' => $currency_user['member_id'], 'currency_id' => $pay_currency_id])->find();
        if ($config['total_pay_limit'] > 0) {
            if ($fil_mining) {
                $buy_yu = $config['total_pay_limit'] - $fil_mining['pay_num'];
                if ($pay_num > $buy_yu) {
                    $r['message'] = lang('fil_mining_total_limit', ['num' => $config['total_pay_limit']]);
                    return $r;
                }
            } elseif ($pay_num > $config['total_pay_limit']) {
                $r['message'] = lang('fil_mining_total_limit', ['num' => $config['total_pay_limit']]);
                return $r;
            }
        }

        $today_start = todayBeginTimestamp() + 86400;
        try {
            self::startTrans();


            if ($fil_mining) {
                $flag = self::where(['id' => $fil_mining['id'], 'pay_num' => $fil_mining['pay_num']])->update([
                    'pay_num' => ['inc', $pay_num],
                    'release_num_total' => ['inc', $total_num], //可释放总量
                    'release_num_avail' => ['inc', $total_num], //已释放数量
                    'release_start_day' => $today_start, //释放日期开始计算时间
                ]);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                $item_id = $fil_mining['id'];
            } else {
                $first_percent = FilMiningLevel::getMonthFirstPercent();
                //插入新矿机
                $item_id = self::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $pay_currency_id,
                    'pay_num' => $pay_num,
                    'release_num_total' => $total_num,
                    'release_num_avail' => $total_num,
                    'release_percent' => $first_percent,
                    'real_currency_id' => $config['default_release_currency_id'],
                    'status' => 0,
                    'add_time' => $add_time,
                    'release_start_day' => $today_start, //释放日期开始计算时间
                ]);
                if (!$item_id) throw new Exception(lang('operation_failed_try_again'));
            }

            //增加入金记录
            $flag = FilMiningPay::addPay($currency_user['member_id'], $pay_currency_id, $pay_num, $add_time, $item_id);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            if ($pay_num > 0) {

                // 组合支付
                if ($other_currency_num > 0) {
                    $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
                    if ($other_currency_pay_num >= 0) {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6321, 'fil_mining', 'out', $other_currency_user['num'], $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $other_currency_user['num']);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6321, 'fil_mining', 'out', $other_currency_pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $other_currency_pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    } else {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6321, 'fil_mining', 'out', $pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                } else {
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6321, 'fil_mining', 'out', $pay_num, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
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

    static function releaseSupportCurrency()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_orders_illegal_request');
        $r['result'] = null;

        $config = FilMiningConfig::get_key_value();
        if (empty($config)) return $r;

        $pay_currency = Currency::where(['currency_id' => $config['pay_currency_id']])->find();

        $currency_list = Currency::where(['account_type' => 'mining', 'is_line' => 1, 'currency_id' => ['in', $config['support_currency_ids']]])->field('currency_id,currency_name')->select();
        if (empty($currency_list)) return $r;

        $pay_currency_price = CurrencyPriceTemp::get_price_currency_id($config['pay_currency_id'], 'USD');
        foreach ($currency_list as &$currency) {
            $currency['pay_currency_name'] = $pay_currency['currency_name'];
            if ($currency['currency_id'] == $pay_currency['currency_id']) {
                $currency['ratio'] = 1;
            } else {
                $price = self::getReleaseCurrencyPrice($currency['currency_id']);
                $currency['ratio'] = $price > 0 ? keepPoint($pay_currency_price / $price, 6) : 0;
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $currency_list;
        return $r;
    }

    static function changeReleaseCurrency($member_id, $fil_mining_id, $currency_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_orders_illegal_request');
        $r['result'] = null;

        $config = FilMiningConfig::get_key_value();

        if ($config['fil_mining_change_open'] != 1) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        // 后台任务未执行完毕 不能更改开采币种
        $today_summay = Db::name('fil_mining_summary')->where(['today' => date('Y-m-d')])->find();
        if (empty($today_summay)) {
            $count = Db::name('fil_mining_summary')->count();
            if ($count) {
                $r['message'] = lang('fil_mining_release_change', ['num' => $config['fil_mining_change_open_time']]);
                return $r;
            }
        }

        //获取当前用户最后入金记录
        $fil_mining = self::where(['id' => $fil_mining_id, 'member_id' => $member_id])->find();
        if (empty($fil_mining)) return $r;

        $currency = Currency::where(['currency_id' => $currency_id, 'is_line' => 1])->find();
        if (empty($currency) || $currency['account_type'] != 'mining') return $r;

        if (isset($config['support_currency_id_level_limit_' . $currency_id])) {
            $level_limit = $config['support_currency_id_level_limit_' . $currency_id];
            if ($level_limit > 0 && $fil_mining['level'] < $level_limit) {
                $r['message'] = lang('fil_mining_change_currency_level_limit', ['num' => $level_limit]);
                return $r;
            }
        }

        $currency_price = self::getReleaseCurrencyPrice($currency['currency_id']);
        $pay_currency_price = CurrencyPriceTemp::get_price_currency_id($config['pay_currency_id'], 'USD');
        if ($currency_price <= 0 || $pay_currency_price <= 0) return $r;

        $flag = self::where(['id' => $fil_mining_id, 'member_id' => $member_id])->setField('real_currency_id', $currency['currency_id']);
        if ($flag === false) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }

    static function getList($member_id, $status, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $config = FilMiningConfig::get_key_value();

        $where = [
            'a.member_id' => $member_id,
        ];
        if (is_numeric($status)) $where['a.status'] = intval($status);

        $list = self::alias('a')->field('a.id,a.pay_num,a.release_num_total,a.release_num_avail,a.release_percent,a.status,a.add_time,a.is_new,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('m-d H:i', $item['add_time']);
            $item['release_num'] = keepPoint($item['release_num_total'] - $item['release_num_avail'], 6);
            $item['currency_nick_name'] = $config['currency_nick_name'];
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getReleaseCurrencyPrice($currency_id)
    {
        $currency_map = [
            83 => [
                'cpt_type' => 'huobi_kline',
                'cpt_same_currency_id' => 79,
            ],
            84 => [
                'cpt_type' => 'huobi_kline',
                'cpt_same_currency_id' => 80,
            ],
            85 => [
                'cpt_type' => 'huobi_kline',
                'cpt_same_currency_id' => 82,
            ],
            86 => [
                'cpt_type' => 'exchange',
                'cpt_same_currency_id' => 87,
            ],
            88 => [
                'cpt_type' => 'exchange',
                'cpt_same_currency_id' => 87,
            ],

        ];
        // 暂时只支持Fil
        if (!isset($currency_map[$currency_id])) return 0;
        $currency_price_temp = $currency_map[$currency_id];

        if ($currency_price_temp['cpt_same_currency_id'] == Currency::USDT_BB_ID || $currency_price_temp['cpt_same_currency_id'] == Currency::USDT_ID) return 1;

        $today_start = todayBeginTimestamp();
        if ($currency_price_temp['cpt_type'] == 'huobi_kline') {
            //昨日火币的K线图的最高价格
            $last_kline = Db::name('kline')->where(['currency_id' => $currency_price_temp['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID, 'add_time' => ['lt', $today_start]])->order('id desc')->find();
            if (empty($last_kline)) return 0;

            $last_day = strtotime(date('Y-m-d', $last_kline['add_time']));
            $last_high = Db::name('kline')->where(['currency_id' => $currency_price_temp['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID, 'add_time' => ['between', [$last_day, $last_day + 86399]]])->max('hign_price');
            return $last_high ?: 0;
        } elseif ($currency_price_temp['cpt_type'] == 'exchange') {
            //昨日火币的K线图的最高价格
            $last_trade = Db::name('trade')->where(['currency_id' => $currency_price_temp['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID, 'add_time' => ['lt', $today_start]])->order('trade_id desc')->find();
            if (empty($last_trade)) return 0;

            $last_day = strtotime(date('Y-m-d', $last_trade['add_time']));
            $last_high = Db::name('trade')->where(['currency_id' => $currency_price_temp['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID, 'add_time' => ['between', [$last_day, $last_day + 86399]]])->max('price');
            return $last_high ?: 0;
        } elseif ($currency_price_temp['cpt_type'] == 'abf_trade') {
            //获取ABF类型的币币交易价格
            $last_kline = AbfKline::where(['currency_id' => $currency_price_temp['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID, 'add_time' => ['lt', $today_start]])->order('id desc')->find();
            if (empty($last_kline)) return 0;

            return $last_kline['high_price'] ?: 0;
        }
        return 0;
    }

    // 增加直推业绩
    static function addOneTeamNum($member_id, $currency_id, $num)
    {
        $pid_member = Member::where(['member_id' => $member_id])->field('pid')->find();
        if (empty($pid_member) || $pid_member['pid'] <= 0) return true;

        $pid_fil_mining = self::where(['member_id' => $pid_member['pid'], 'currency_id' => $currency_id])->find();
        if (empty($pid_fil_mining)) return true;

        return self::where(['id' => $pid_fil_mining['id'], 'one_team_total' => $pid_fil_mining['one_team_total']])->setInc('one_team_total', $num);
    }

    // 增加团队业绩
    static function addParentTeamNum($child_member_id, $currency_id, $num)
    {
        return self::execute('update ' . config("database.prefix") . 'fil_mining a,' . config("database.prefix") . 'member_bind b 
            set a.team_total=a.team_total+' . $num . ' where a.member_id = b.member_id and a.currency_id=' . $currency_id . ' and  b.child_id=' . $child_member_id . ';');
    }

    //能否获取3代奖励
    static function isThirdAward($pid_member_id, $child_member_id, $currency_id)
    {
        //获取直推中的最大区
        $child_max_num = self::query('select member_id,(pay_num+team_total) as team_total  from ' . config("database.prefix") . 'fil_mining where member_id in(
            select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $pid_member_id . ' and level=1
        ) and currency_id=' . $currency_id . ' order by team_total desc limit 1;');

        $max_member_id = 0;
        if ($child_max_num && isset($child_max_num[0])) {
            //在大区中 获取不到奖励
            $max_member_id = intval($child_max_num[0]['member_id']);
            if ($max_member_id == $child_member_id) return false;

            $member_bind = MemberBind::where(['member_id' => $max_member_id, 'child_id' => $child_member_id])->find();
            if (!empty($member_bind)) return false;
        } else {
            //没有大区 获取不到奖励
            return false;
        }
        return true;
    }

    // 获取小区总业绩
    static function getBigTeamNum($pid_member_id, $currency_id)
    {
        // 获取直推中的最大区业绩
        $child_max_num = self::query('select (pay_num+team_total) as team_total from ' . config("database.prefix") . 'fil_mining where member_id in(
            select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $pid_member_id . ' and level=1
        ) and currency_id=' . $currency_id . ' order by team_total desc limit 1;');

        if ($child_max_num && isset($child_max_num[0])) {
            return $child_max_num[0]['team_total'];
        } else {
            // 没有业绩
            return 0;
        }
    }

    // 获取最大等级是level的部门数量
    static function getTeamLevelCount($pid_member_id, $currency_id, $level)
    {
        // 获取直推中的最大区业绩
        $child_max_num = self::query('select count(*) as count from ' . config("database.prefix") . 'fil_mining where member_id in(
            select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $pid_member_id . ' and level=1
        ) and currency_id=' . $currency_id . ' and (level>=' . $level . ' or team_max_level>=' . $level . ' );');

        if ($child_max_num && isset($child_max_num[0])) {
            return $child_max_num[0]['count'];
        } else {
            // 没有业绩
            return 0;
        }
    }

    // 更改团队中的最大等级
    static function updateTeamMaxLevel($child_member_id, $currency_id, $level)
    {
        return self::execute('update ' . config("database.prefix") . 'fil_mining a,' . config("database.prefix") . 'member_bind b 
            set a.team_max_level=' . $level . ' where a.member_id = b.member_id and a.currency_id=' . $currency_id . ' and a.team_max_level<' . $level . ' and  b.child_id=' . $child_member_id . ';');
    }

    static function getAwardLimit($member_id, $currency_id)
    {
        $mining = self::where([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
        ])->find();
        if (empty($mining)) return 0;

        return $mining['release_num_avail'];
    }

    static function my_level($member_id)
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang('success');

        $config = FilMiningConfig::get_key_value();
        $fil_mining = self::where(['member_id' => $member_id, 'currency_id' => $config['pay_currency_id']])->find();

        $level_name = 'V0';
        if ($fil_mining && $fil_mining['level'] > 0) {
            $fil_level = FilMiningLevel::where(['level_id' => $fil_mining['level']])->find();
            if (!empty($fil_level)) $level_name = $fil_level['level_name'];
        }

        // 统计直推人数
//       $direct_people =  MemberBind::where(['member_id'=>$member_id,'level'=>1])->count();
        $direct_people = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time')
            ->where(['b.member_id' => $member_id, 'b.level' => 1])
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->count();

        // 统计团队人数
//        $team_people = MemberBind::where(['member_id'=>$member_id])->count();
        $team_people = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time')
            ->where(['b.member_id' => $member_id])
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->count();

        $r['result'] = [
            'level' => $fil_mining ? $fil_mining['level'] : 0,
            'level_name' => $level_name,
            'team_total' => $fil_mining ? $fil_mining['team_total'] : 0,
            'pay_num' => $fil_mining ? $fil_mining['pay_num'] : 0,
            'direct_people' => $direct_people,
            'team_people' => $team_people,
        ];
        return $r;
    }

    // 我的直推入金人数
    static function myOneTeamCount($member_id)
    {
        $where = [
            'b.member_id' => $member_id,
            'b.level' => 1,
        ];

        $total = self::alias('a')->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->count();
        return intval($total);
    }

    static function myTeam($member_id, $page = 1, $page_size = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;
        $r['total'] = 0;


        $where = [
            'b.member_id' => $member_id,
            'b.level' => 1,
        ];
        $list = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time,c.currency_name,m.ename,m.phone,m.email')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_id=c.currency_id", "LEFT")
            ->page($page, $page_size)->order("a.level desc,a.id asc")->select();
        if (!$list) return $r;

        $total = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->count();

        $all_levels = FilMiningLevel::getAllLevel();
        if ($all_levels) $all_levels = array_column($all_levels, null, 'level_id');

        foreach ($list as $key => &$value) {
            $value['add_time'] = date('m-d H:i', $value['add_time']);
            $value['level_name'] = isset($all_levels[$value['level']]) ? $all_levels[$value['level']]['level_name'] : 'V0';
            if (empty($value['phone'])) $value['phone'] = $value['email'];
            unset($value['email']);

            // 统计直推人数
//            $value['direct_people'] =  MemberBind::where(['member_id'=>$member_id,'level'=>1])->count();
            $value['direct_people'] = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time')
                ->where(['b.member_id' => $value['member_id'], 'b.level' => 1])
                ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
                ->count();

            // 统计团队人数
//            $value['team_people'] = MemberBind::where(['member_id'=>$member_id])->count();
            $value['team_people'] = self::alias('a')->field('a.member_id,a.level,a.pay_num,a.team_total,a.add_time')
                ->where(['b.member_id' => $value['member_id']])
                ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
                ->count();
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['total'] = $total;
        return $r;
    }

    // 所有收益3倍后  涡轮和小区助力奖停止
    static function getNewStaticLimit($fil_mining, $fil_mining_config)
    {
        $limit = keepPoint($fil_mining['pay_num'] * $fil_mining_config['total_multiple'] - $fil_mining['total_release'] -
            $fil_mining['total_child1'] - $fil_mining['total_child2'] - $fil_mining['total_child3'] - $fil_mining['total_child4'] - $fil_mining['total_child5'] -
            $fil_mining['total_child11'] - $fil_mining['total_child12'] - $fil_mining['total_child13'] - $fil_mining['total_child15'] - $fil_mining['total_child16'], 6);
        return $limit > 0 ? $limit : 0;
    }

    // 推荐奖+级差奖 9倍停止
    static function getNewHelpAwardLimit($fil_mining, $fil_mining_config)
    {
        $limit = keepPoint($fil_mining['pay_num'] * $fil_mining_config['new_help_max_multiple'] - $fil_mining['total_release'] -
            $fil_mining['total_child1'] - $fil_mining['total_child2'] - $fil_mining['total_child3'] - $fil_mining['total_child4'] - $fil_mining['total_child5'] -
            $fil_mining['total_child11'] - $fil_mining['total_child12'] - $fil_mining['total_child13'] - $fil_mining['total_child15'] - $fil_mining['total_child16'], 6);
        return $limit > 0 ? $limit : 0;
    }

    // 推荐奖+级差奖 9倍停止
    static function getTotalIncome($fil_mining)
    {
        $limit = keepPoint($fil_mining['total_release'] +
            $fil_mining['total_child1'] + $fil_mining['total_child2'] + $fil_mining['total_child3'] + $fil_mining['total_child4'] + $fil_mining['total_child5'] +
            $fil_mining['total_child11'] + $fil_mining['total_child12'] + $fil_mining['total_child13'] + $fil_mining['total_child15'] + $fil_mining['total_child16'], 6);
        return $limit > 0 ? $limit : 0;
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
}
