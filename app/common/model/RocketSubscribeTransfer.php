<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;
use think\Log;

class RocketSubscribeTransfer extends Base
{
    const STATUS_FIRST = 0;
    const STATUS_SECOND = 1;
    const STATUS_THIRD = 2;

    const STATUS_ENUM = [
        self::STATUS_FIRST => "排队中",
        self::STATUS_SECOND => "成功",
        self::STATUS_THIRD => "失败",
    ];

    /**
     * 预约记录
     * @param int $member_id    用户ID
     * @param int $page         页
     * @param int $rows         页数
     */
    static function get_queue_log($member_id, $page, $rows=15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $result = RocketBuyList::alias('a')
            ->join('rocket_goods_list b', 'a.goods_list_id=b.id')
            ->field('a.*,b.name,b.level')
            ->where(['member_id' => $member_id, 'type' => 2])
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$result) return $r;

        $statusList = self::STATUS_ENUM;
        foreach ($result as &$value) {
            $value['add_time'] = date('m/d H:i', $value['add_time']);
            $value['status_name'] = $statusList[$value['status']];
            if ($value['level'] == 1) {
                $value['name'] = $value['name'] . ' 点火';
            } else {
                $value['name'] = $value['name'] . ' 推进' . $value['level'];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 提交划转
     * @param int $member_id          用户ID
     * @param int $currency_id        转入币种
     * @param int $out_currency_id    转出币种
     * @param number $num             数量
     * @param number $mtk_num          mtk数量
     */
    static function add_transfer($member_id, $currency_id, $out_currency_id, $num, $mtk_num) {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_failure'), 'result' => null];

        $subscribe_transfer_time = RocketConfig::getValue('subscribe_transfer_time', 15);
        $start_time = strtotime(date('Y-m-d'));
        $end_time = $start_time + 86399;
        //判断是否可充值预约池
        $res = RocketGoodsList::where(['status' => 1, 'is_show' => 1, 'start_time' => ['BETWEEN', [$start_time, $end_time]]])->select();
        if ($res) {
            foreach ($res as $value) {
                $check_start_time = keepPoint($value['start_time'] - ($subscribe_transfer_time * 60), 0);
                $check_end_time = $value['start_time'];
                if (time() >= $check_start_time && time() <= $check_end_time) {
                    $r['message'] = '加权排队中，请稍后再充值';
                    return $r;
                }
            }
        }
        if ($num <= 0) {
            $r['message'] = '请输入预约金额';
            return $r;
        }

        //转出账户
        $out_currency_user = CurrencyUser::getCurrencyUser($member_id, $out_currency_id);
        if (empty($out_currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($out_currency_user['num'] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $mtk_rate = RocketConfig::getValue('mtk_rate', 2);
        $usdt_num = sprintf('%.6f', $num * ($mtk_rate / 100));
        //$price = Db::name('mtk_currency_price')->order('id desc')->value('price');
        $price = Trade::getLastTradePrice(99, 5);
        $ac_mtk_num = $mtk_num;
        if ($price > 0) {
            //实际扣除 USDT / 每日MTK
            $price = sprintf('%.6f', $price);
            $ac_mtk_num = sprintf('%.4f', $usdt_num / $price);
        }
        if (sprintf('%.2f', $ac_mtk_num) != sprintf('%.2f', $mtk_num)) {
            $r['message'] = '燃料有误，请重新参与';
            return $r;
        }

        //平台币账户
        $platform_currency_id = RocketConfig::getValue('platform_currency_id');
        $platform_currency_user = CurrencyUser::getCurrencyUser($member_id, $platform_currency_id);
        if (empty($platform_currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($platform_currency_user['num'] < $mtk_num) {
            $r['message'] = '燃料' . lang('insufficient_balance');
            return $r;
        }

        //转入账户
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $type = 1;//转入
        Db::startTrans();
        try {
            $data = [
                'member_id' => $member_id,
                'type' => $type,
                'num' => $num,
                'add_time' => time()
            ];
            $item_id = self::insertGetId($data);
            if ($item_id === false) throw new Exception('添加划转记录失败');

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($out_currency_user['member_id'], $out_currency_user['currency_id'], 5201, 'Wallet transfer', 'out', $num, $item_id, 0);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $out_currency_user['cu_id'], 'num' => $out_currency_user['num']])->setDec('num', $num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 5201, 'Wallet transfer', 'in', $num, $item_id, 0);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            //注册奖励释放
            $is_register_handsel = \app\common\model\RocketConfig::getValue('is_register_handsel');
            $release_rate = \app\common\model\RocketConfig::getValue('release_rate');
            if ($is_register_handsel == 1 && $num > 0) {
                $reward_currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
                $reward_currency_user = CurrencyUser::getCurrencyUser($member_id, $reward_currency_id);
                if(empty($reward_currency_user)) throw new Exception(lang('lan_network_busy_try_again'));

                $reward = sprintf('%.6f', $num * ($release_rate / 100));
                if (sprintf('%.6f', $reward) > sprintf('%.6f', $reward_currency_user['lock_num'])) {
                    $reward = $reward_currency_user['lock_num'];
                }
                if ($reward > 0) {
                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($reward_currency_user['member_id'], $reward_currency_user['currency_id'], 7120, 'register_reward3', 'out', $reward, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $reward_currency_user['cu_id'], 'num' => $reward_currency_user['num']])->setInc('num', $reward);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $reward_currency_user['cu_id'], 'lock_num' => $reward_currency_user['lock_num']])->setDec('lock_num', $reward);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            // 扣除平台币资产
            if ($mtk_num > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($platform_currency_user['member_id'], $platform_currency_user['currency_id'], 7106, 'rocket_paly_mtk', 'out', $mtk_num, $item_id, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id'], 'num' => $platform_currency_user['num']])->setDec('num', $mtk_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }
            //更新每日预约汇总
            $date = strtotime(date('Y-m-d'));
            $flag = RocketDaySummary::addItem($date, $num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));


            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = $flag;
        } catch (\Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * 余额明细
     * @param int $member_id    用户ID
     * @param int $page         页
     * @param int $rows         页数
     */
    static function get_balance_detail($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $result = Db::name('accountbook')->alias('a')
            ->join('accountbook_type b', 'a.type=b.id')
            ->field('a.number_type,a.number,a.add_time,b.name_tc,a.type,a.third_id,a.number_type')
            ->where(['a.member_id' => $member_id, 'a.currency_id' => 102])
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$result) return $r;

        foreach ($result as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            if ($value['number_type'] == 1) {
                $value['name'] = '+'.$value['number'];
            } else {
                $value['name'] = '-'.$value['number'];
            }
            $value['currency_name'] = '火米';
            if ($value['type'] == 600) {
                $CurrencyUserTransfer = CurrencyUserTransfer::where(['cut_id' => $value['third_id']])->find();
                $value['name_tc'] = $CurrencyUserTransfer['cut_user_id'] . ' 转入';
                if ($value['number_type'] == 2) {
                    $value['name_tc'] = '转出 ' . $CurrencyUserTransfer['cut_target_user_id'];
                }
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 预约排队
     * @param array $data    闯关数据
     */
    static function reservation_queue($data) {
        $currency_id = RocketConfig::getValue('subscribe_currency_id');//预约池币种
        //账户余额大于等于30U
        $seniority = RocketConfig::getValue('subscribe_seniority');
        $res = CurrencyUser::where(['currency_id' => $currency_id, 'num' => ['egt', $seniority]])->select();
        if (!$res) return true;

        $subscribe_rate = RocketConfig::getValue('subscribe_rate');//预约排队比例
        $finish_money = RocketBuyList::where(['type' => 3, 'goods_list_id' => $data['id']])->sum('num');//已复利数量
        $queue_money = sprintf('%.0f', ($data['price'] - $finish_money) * ($subscribe_rate / 100));//预约排队金额
        $subscribe_money = CurrencyUser::where(['currency_id' => $currency_id, 'num' => ['egt', $seniority]])->sum('num');//预约池金额
        $type = 1;//全部投入
        if (sprintf('%.2f', $subscribe_money) > sprintf('%.2f', $queue_money)) {
            $type = 2;//按比例
        }
        $price = Db::name('mtk_currency_price')->order('id desc')->value('price');
        foreach ($res as $key => $value) {
            $total_num = RocketBuyList::where(['goods_list_id' => $data['id'], 'type' => 2])->sum('num');
            if (sprintf('%.4f', $total_num) >= sprintf('%.4f', $queue_money)) {//预约排队数量已满，退出循环
                return true;
            }
            if ($type == 1) {//全部投入
                $num = sprintf("%.2f", substr(sprintf("%.3f", $value['num']), 0, -1));
                //$usdt_num = sprintf('%.4f', $num * ($data['kmt_rate'] / 100));
                $mtk_num = 0;
//                if ($price > 0) {
//                    //实际扣除 USDT / 每日MTK
//                    $mtk_num = sprintf('%.4f', $usdt_num / $price);
//                }
                $flag = RocketBuyList::add_list($value['member_id'], $data['id'], $num, $mtk_num, 2);
                if ($flag['code'] != 10000) {
                    Log::write('添加预约排队失败'. $value['member_id']);
                }
            } else {//按比例
                //参与金额 = 预约排队金额 / 预约池总金额 * 预约池金额（用户）（向上取整）
                //$num = ceil($queue_money / $subscribe_money * $value['num']);
                //$num = substr(sprintf("%.3f",$queue_money / $subscribe_money * $value['num']),0,-1);
                $num = sprintf('%.2f', $queue_money / $subscribe_money * $value['num']);
                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
                //判断账户资金是否足够，不够则向下取整
                if (sprintf('%.2f', $currency_user['num']) < sprintf('%.2f', $num)) {
                    //$num = floor($queue_money / $subscribe_money * $value['num']);
                    //$num = substr(sprintf("%.3f",$currency_user['num']),0,-1);
                    $num = $currency_user['num'];
                }

                $mtk_num = 0;
                $flag = RocketBuyList::add_list($value['member_id'], $data['id'], $num, $mtk_num, 2);
                if ($flag['code'] != 10000) {
                    Log::write('添加预约排队失败'. $value['member_id']);
                }
            }
        }
    }

    /**
     * 添加预约池充值记录
     * @param int $member_id    用户id
     * @param int $type    类型 1用户充值 2系统充值
     * @param int $num     数量
     */
    static function addItem($member_id, $type, $num) {
        $data = [
            'member_id' => $member_id,
            'type' => $type,
            'num' => $num,
            'add_time' => time() - 86400
        ];
        return self::create($data);
    }

    /**
     * 获取社区信息
     * @param int $member_id    用户id
     * @param int $type    社区类型 1大区 2小区
     * @param array $time_arr    时间数据
     */
    static function getCommunityInfo($member_id, $type, $time_arr) {
        $res = MemberBind::where(['member_id' => $member_id, 'level' => 1])->select();

        $today_arr = [];
        $month_arr = [];
        $last_month_arr = [];
        $total_arr = [];
        $bonus_arr = [];
        if ($res) {
            foreach ($res as $key => $value) {
                //预购-当日
                $first_num = self::where(['member_id' => $value['child_id'], 'type' => 1, 'add_time' => ['between', [$time_arr['today_start'], $time_arr['today_end']]]])->sum('num');
                $first_team = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['child_id'], 'a.type' => 1, 'a.add_time' => ['between', [$time_arr['today_start'], $time_arr['today_end']]]])->sum('num');
                $today_arr[] = sprintf('%.6f', $first_num + $first_team);
                //预购-当月
                $second_num = self::where(['member_id' => $value['child_id'], 'type' => 1, 'add_time' => ['between', [$time_arr['month_start'], $time_arr['month_end']]]])->sum('num');
                $second_team = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['child_id'], 'a.type' => 1, 'a.add_time' => ['between', [$time_arr['month_start'], $time_arr['month_end']]]])->sum('num');
                $month_arr[] = sprintf('%.6f', $second_num + $second_team);
                //预购-上个月
                $third_num = self::where(['member_id' => $value['child_id'], 'type' => 1, 'add_time' => ['between', [$time_arr['last_month_start'], $time_arr['last_month_end']]]])->sum('num');
                $third_team = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['child_id'], 'a.type' => 1, 'a.add_time' => ['between', [$time_arr['last_month_start'], $time_arr['last_month_end']]]])->sum('num');
                $last_month_arr[] = sprintf('%.6f', $third_num + $third_team);
                //预购-累计
                $fourth_num = self::where(['member_id' => $value['child_id'], 'type' => 1])->sum('num');
                $fourth_team = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['child_id'], 'a.type' => 1])->sum('num');
                $total_arr[] = sprintf('%.6f', $fourth_num + $fourth_team);
                //抱彩-累计
                $fifth_num = self::where(['member_id' => $value['child_id'], 'type' => 2])->sum('num');
                $fifth_team = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['child_id'], 'a.type' => 2])->sum('num');
                $bonus_arr[] = sprintf('%.6f', $fifth_num + $fifth_team);
            }
        }

        $max_today = !empty($today_arr) ? max($today_arr) : 0;
        $max_month = !empty($month_arr) ? max($month_arr) : 0;
        $max_last_month = !empty($last_month_arr) ? max($last_month_arr) : 0;
        $max_total = !empty($total_arr) ? max($total_arr) : 0;
        $max_bonus = !empty($bonus_arr) ? max($bonus_arr) : 0;
        if ($type == 1) {
            return [
                'today_num' => sprintf('%.6f', $max_today),
                'month_num' => sprintf('%.6f', $max_month),
                'last_month_num' => sprintf('%.6f', $max_last_month),
                'total_num' => sprintf('%.6f', $max_total),
                'total_lately_num' => sprintf('%.6f', $max_bonus),
            ];
        } else {
            $min_today = sprintf('%.6f', array_sum($today_arr) - $max_today);
            $min_month = sprintf('%.6f', array_sum($month_arr) - $max_month);
            $min_last_month = sprintf('%.6f', array_sum($last_month_arr) - $max_last_month);
            $min_total = sprintf('%.6f', array_sum($total_arr) - $max_total);
            $min_bonus = sprintf('%.6f', array_sum($bonus_arr) - $max_bonus);
            return [
                'today_num' => $min_today,
                'month_num' => $min_month,
                'last_month_num' => $min_last_month,
                'total_num' => $min_total,
                'total_lately_num' => $min_bonus,
            ];
        }
    }

    /**
     * 数据统计明细
     * @param int $member_id    用户id
     */
    static function get_statistics_info($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        if (!$member_id) {
            return $r;
        }

        $today_start = strtotime(date('Y-m-d'));
        $today_end = $today_start + 86399;
        $month_start = strtotime(date('Y-m-01', strtotime(date("Y-m-d"))));
        $month_end = strtotime(date('Y-m-d', strtotime("$month_start +1 month -1 day")));
        $last_month_start = strtotime(date('Y-m-d', strtotime(date('Y-m-01') . ' -1 month')));
        $last_month_end = strtotime(date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day')));
        $lately = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2])->order('id desc')->value('add_time');
        $lately_start = strtotime(date('Y-m-d', $lately));
        $lately_end = $lately_start + 86399;

        //本人预购
        $today_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$today_start, $today_end]]])->sum('num');
        $month_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$month_start, $month_end]]])->sum('num');
        $last_month_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$last_month_start, $last_month_end]]])->sum('num');
        $total_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1])->sum('num');

        //本人抱彩
        $lately_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2, 'add_time' => ['between', [$lately_start, $lately_end]]])->sum('num');
        $total_lately_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2])->sum('num');

        $time_arr = [
            'today_start' => $today_start,
            'today_end' => $today_end,
            'month_start' => $month_start,
            'month_end' => $month_end,
            'last_month_start' => $last_month_start,
            'last_month_end' => $last_month_end,
        ];
        $oneself_info = [
            'today_num' => sprintf('%.6f', $today_num),
            'month_num' => sprintf('%.6f', $month_num),
            'last_month_num' => sprintf('%.6f', $last_month_num),
            'total_num' => sprintf('%.6f', $total_num),
            'lately_num' => sprintf('%.6f', $lately_num),
            'total_lately_num' => sprintf('%.6f', $total_lately_num),
        ];

        //大区信息
        $max_info = self::getCommunityInfo($member_id, 1, $time_arr);
        //小区信息
        $min_info = self::getCommunityInfo($member_id, 2, $time_arr);

        $result = [
            'member_id' => $member_id,
            'today' => date('Y-m-d'),
            'oneself_info' => $oneself_info,
            'max_info' => $max_info,
            'min_info' => $min_info
        ];

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }
}

