<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;
use OSS\Core\OssException;
use think\exception\PDOException;
use think\Log;

class RocketMember extends Base
{
    /**
     *添加用户汇总
     * @param int $member_id 用户id
     * @param number $num    金额
     */
    static function addItem($member_id, $num) {
        $flag = true;
        try{
            $info = self::where(['member_id'=>$member_id])->find();
            $type = 1;
            $valid = RocketConfig::getValue('valid_username');//获取成为有效账户金额
            if($info) {
                if ($num > 0) {
                    $flag = self::where(['member_id'=>$member_id])->update([
                        'total_num' => ['inc', $num],
                        'count_num' => ['inc', $num]
                    ]);
                }
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'total_num' => $num,
                    'add_time' => time(),
                    'count_num' => $num
                ]);
            }
            //统计业绩(直推、团队)
            $flag = self::updateData($member_id, $num, $type);
            if ($flag === false) throw new Exception('统计业绩失败');
        } catch (\Exception $e) {
            return false;
        }
        return $flag;
    }

    /**
     *计算团队业绩
     * @param int $member_id 用户id
     * @param number $num    金额
     * @param int $type    是否有效账户 1否 2是
     */
    static function updateData($member_id, $num, $type) {
        $pid = Member::where(['member_id' => $member_id])->value('pid');
        $res = self::where(['member_id' => $pid])->find();
        if ($res) {
            $data = ['team_num' => ['inc', $num]];//直推业绩
            if ($type == 2) {
                $data['valid_num'] = ['inc', 1];//有效直推
            }
            $flag = self::where(['member_id'=>$pid])->update($data);
            if ($flag === false) return $flag;
        }

        // 更新团队业绩
        $flag = self::execute('update ' . config("database.prefix") . 'rocket_member a,' . config("database.prefix") . 'member_bind b
    set a.team_total=a.team_total+' . $num . ', a.count_num=a.count_num+ '.$num.' where a.member_id = b.member_id and  b.child_id=' . $member_id . ';');
        if ($flag === false) return false;

        return true;
    }

    /**
     * 用户晋升
     * @param int $member_id 用户id
     */
    static function updateLevel($member_id) {
        $member_info = self::where(['member_id' => $member_id])->find();
        if (!$member_info) return true;

        //找到首个大区用户
        $max_member_id = self::getActiveMaxCount($member_id);
        if ($max_member_id <= 0) {
            return true;
        }

        //小区业绩
        $active_min = self::getActiveMinCount($member_id, $max_member_id);
        $membersLevel = RocketLevel::where(['valid_num' => ['elt', $member_info['valid_num']], 'active_min' => ['elt', $active_min]])->order(['id' => 'DESC'])->find();
        $flag = true;
        if ($membersLevel) {
            if ($membersLevel['level'] > $member_info['level']) {
                //更新等级
                $flag = self::where(['member_id' => $member_id])->update(['level' => $membersLevel['level']]);
            }
        }
        $valid = RocketConfig::getValue('valid_username');//获取成为有效账户金额
        $valid_num = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')
            ->where(['b.member_id' => $member_id, 'b.level' => 1, 'a.total_num' => ['egt', $valid]])
            ->count('a.member_id');
        if ($valid_num > 0) {
            //更新有效直推
            $flag = self::where(['member_id' => $member_id])->update(['valid_num' => $valid_num]);
        }

        return $flag;
    }

    /**
     * 获取大区用户
     * @param int $member_id 用户ID
     * @return int
     */
    static function getActiveMaxCount($member_id) {
        $result = MemberBind::alias('a')
            ->join(config("database.prefix") . "rocket_member b", 'a.child_id = b.member_id')
            ->where(['a.member_id' => $member_id, 'a.level' => 1])
            ->order('b.count_num DESC')
            ->group('b.member_id')
            ->value('b.member_id');

        return $result;
    }

    /**
     * 获取小区数量
     * @param int $member_id 用户ID
     * @param int $max_member_id 大区用户ID
     * @return int
     */
    static function getActiveMinCount($member_id, $max_member_id = 0) {
        $result = 0;
        if ($max_member_id > 0) {
            $child_id = MemberBind::where(['member_id' => $member_id, 'level' => 1])->whereNotIn('child_id', $max_member_id)->column('child_id');
            $result = self::whereIn('member_id', $child_id)->sum('count_num');
        }

        return $result;
    }

    /**
     * 获取支付信息
     * @param int $member_id   用户ID
     * @param int $product_id  子闯关ID
     * @param int $money       支付金额
     * @return array
     */
    static function get_pay_info($member_id, $product_id, $money) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        if (empty($money)) {
            $money = 0;
        }
        $goods_info = RocketGoodsList::where(['id' => $product_id])->find();
        if (!$goods_info) return $r;

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $goods_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //平台币账户
        $platform_currency_id = RocketConfig::getValue('platform_currency_id');
        $platform_currency_user = CurrencyUser::getCurrencyUser($member_id, $platform_currency_id);
        if (empty($platform_currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        $balance = sprintf('%.2f', $goods_info['price'] - $goods_info['finish_money']);
        $num = sprintf('%.6f', $money * ($goods_info['kmt_rate'] / 100));
        //$price = Db::name('mtk_currency_price')->order('id desc')->value('price');
        $price = Trade::getLastTradePrice(99, 5);
        $mtk_num = $num;
        if ($price > 0) {
            $price = sprintf('%.6f', $price);
            //实际扣除 USDT / 每日MTK
            $mtk_num = sprintf('%.4f', $num / $price);
        }
        $max_payment = $goods_info['max_payment'];
        $special_user_id = RocketConfig::getValue('special_user_id');
        $is_special = 0;//是否特殊账号 1是 0否
        if ($special_user_id) {//判断是否特殊账号
            $special_user_id = explode(',', $special_user_id);
            if (in_array($member_id, $special_user_id)) {//最大支付数量 = 剩余闯关数量
                $max_payment = sprintf('%.2f', $goods_info['price'] - $goods_info['finish_money']);
                $is_special = 1;
            }
        }
        $reward_currency_id = RocketConfig::getValue('reward_currency_id');
        $subscribe_currency_id = RocketConfig::getValue('subscribe_currency_id');
        $list = CurrencyUser::field('currency_id as id,num')->where(['member_id' => $member_id])->whereIn('currency_id', [$reward_currency_id, $subscribe_currency_id])->order('currency_id desc')->select();

        $result = [
            'level' => $goods_info['level'],
            'min_payment' => $goods_info['min_payment'],
            'max_payment' => $max_payment,
            'name' => $goods_info['name'],
            'level_name' => $goods_info['level'],
            'num' => $money,
            'kmt_num' => $mtk_num,
            'user_num' => $currency_user['num'],
            'usre_kmt_num' => $platform_currency_user['num'],
            'balance' => $balance,
            'pay_type' => $list,
            'is_special' => $is_special
        ];
        if ($goods_info['level'] == 1) {
            $result['level_name'] = '点火';
        } else {
            $result['level_name'] = '推进'.$goods_info['level'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 获取推进社区
     * @param int $member_id   用户ID
     * @return array
     */
    static function get_community_info($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $result = self::where(['member_id' => $member_id])->field('level,total_num,team_total,valid_num,share_reward,team_reward,n_share_reward,n_team_reward,n_centre_reward,n_auto_reward')->find();
        if (!$result) return $r;

        $max_member_id = self::getActiveMaxCount($member_id);
        $max_info = self::where(['member_id' => $max_member_id])->field('total_num,team_total')->find();
        $max_num = sprintf('%.2f', $max_info['total_num'] + $max_info['team_total']);
        $result['max_team_total'] = $max_num;
        $result['team_total'] = sprintf('%.2f', $result['team_total'] - $max_num);
        $result['level'] = 'H' . $result['level'];
        $one_num = Db::name('member')->where(['pid' => $member_id])->count('member_id');
        $result['one_num'] = $one_num ?: 0;
        $valid_num = RocketRewardLog::getValidNum($member_id);
        $result['valid_num'] = $valid_num ?: 0;
        $today_start = strtotime(date("Y-m-d"));
        $today_end = $today_start + 86399;
        $today_share_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 2, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $result['today_share_reward'] = $today_share_reward ? sprintf('%.2f', $today_share_reward): 0;
        $today_team_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 3, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $result['today_team_reward'] = $today_team_reward ? sprintf('%.2f', $today_team_reward): 0;
//        $yestday_start = strtotime(date("Y-m-d",strtotime("-1 day")));
//        $yestday_end = $yestday_start + 86399;
        //$is_seniority = RocketRewardLog::where(['member_id' => $member_id, 'type' => 3, 'add_time' => ['between', [$today_start, $today_end]]])->find();
        $is_seniority = Db::name('rocket_summary')->where(['member_id' => $member_id])->order('id desc')->value('is_reward');
        $result['is_seniority'] = !empty($is_seniority) ? 1 : 0;//昨日本人是否合格 1是 0否
        $first_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=1')
            ->where(['b.member_id' => $member_id, 'a.type' => 1, 'a.add_time' => ['between', [$today_start, $today_end]]])
            ->sum('a.third_money');
        $result['first_reward'] = $first_reward ? sprintf('%.2f', $first_reward): 0;//昨日一代流水
        $total_first_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=1')
            ->where(['b.member_id' => $member_id, 'a.type' => 1])
            ->sum('a.third_money');
        $result['total_first_reward'] = $total_first_reward ? sprintf('%.2f', $total_first_reward): 0;//累计一代流水
        $second_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=2')
            ->where(['b.member_id' => $member_id, 'a.type' => 1, 'a.add_time' => ['between', [$today_start, $today_end]]])
            ->sum('a.third_money');
        $result['second_reward'] = $second_reward ? sprintf('%.2f', $second_reward): 0;//昨日二代流水
        $total_second_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=2')
            ->where(['b.member_id' => $member_id, 'a.type' => 1])
            ->sum('a.third_money');
        $result['total_second_reward'] = $total_second_reward ? sprintf('%.2f', $total_second_reward): 0;//累计二代流水
        $third_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=3')
            ->where(['b.member_id' => $member_id, 'a.type' => 1, 'a.add_time' => ['between', [$today_start, $today_end]]])
            ->sum('a.third_money');
        $result['third_reward'] = $third_reward ? sprintf('%.2f', $third_reward): 0;//昨日三代流水
        $total_third_reward = RocketRewardLog::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id and b.level=3')
            ->where(['b.member_id' => $member_id, 'a.type' => 1])
            ->sum('a.third_money');
        $result['total_third_reward'] = $total_third_reward ? sprintf('%.2f', $total_third_reward): 0;//累计三代流水
        $today_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 3, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('third_money');
        $result['today_reward'] = $today_reward ? sprintf('%.2f', $today_reward): 0;//昨日团队流水
        $total_today_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 3])->sum('third_money');
        $result['total_today_reward'] = $total_today_reward ? sprintf('%.2f', $total_today_reward): 0;//累计团队流水
        $share_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 2])->sum('reward');
        $result['share_reward'] = $share_reward ? sprintf('%.2f', $share_reward): 0;

        $personal_subscribe = RocketSubscribeTransfer::where(['member_id' => $member_id])->sum('num');
        $result['personal_subscribe'] = $personal_subscribe ? sprintf('%.6f', $personal_subscribe): 0;//累计个人预充
        $team_subscribe = RocketSubscribeTransfer::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $member_id])->sum('a.num');
        $result['team_subscribe'] = $team_subscribe ? sprintf('%.6f', $team_subscribe): 0;//累计团队预充
        $max_subscribe = self::getMaxSubscribe($member_id);
        $result['max_subscribe'] = $max_subscribe ? sprintf('%.6f', $max_subscribe): 0;//累计大区预充
        $min_subscribe = $team_subscribe ? sprintf('%.6f', $team_subscribe - $max_subscribe): 0;
        $result['min_subscribe'] = $min_subscribe ? sprintf('%.6f', $min_subscribe): 0;//累计小区预充
        $valid_username = RocketConfig::getValue('valid_username');
        $result['is_valid'] = $result['total_num'] >= $valid_username ? 1 : 0;//本人是否有效用户 1是 0否

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    //获取大区预充
    static function getMaxSubscribe($member_id) {
        $result = 0;
        $res = MemberBind::where(['member_id' => $member_id, 'level' => 1])->column('child_id');
        if ($res) {
            $arr = [];
            foreach ($res as $key => $value) {
                $team_num = RocketSubscribeTransfer::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value])->sum('a.num');
                $num = RocketSubscribeTransfer::where(['member_id' => $value])->sum('num');
                $arr[] = sprintf('%.6f', $team_num + $num);
            }
            $result = max($arr);
        }
        return $result;
    }

    /**
     * 获取我的辅导
     * @param int $member_id   用户ID
     * @param int $page   页
     * @param int $rows   页数
     * @return array
     */
    static function get_my_info($member_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $list = self::alias('a')
            ->join('member_bind b', 'a.member_id=b.child_id')
            ->where(['b.member_id' => $member_id, 'b.level' => 1])->field('a.member_id,a.level,a.total_num,a.team_total,a.valid_num,a.add_time')
            ->page($page, $rows)
            ->order("a.member_id asc")
            ->select();
        if (!$list) return $r;

        $today_start = strtotime(date("Y-m-d"));
        $today_end = $today_start + 86399;
        $yestday_start = strtotime(date("Y-m-d",strtotime("-1 day")));
        $yestday_end = $yestday_start + 86399;
        $valid_username = RocketConfig::getValue('valid_username');
        foreach ($list as &$value) {
            $one_num = Db::name('member')->where(['pid' => $value['member_id']])->count('member_id');
            $value['one_num'] = $one_num;
//            $valid_num = RocketRewardLog::getValidNum($value['member_id']);
//            $value['valid_num'] = $valid_num;
            $member_info = Db::name('member')->where(['member_id' => $value['member_id']])->find();
            $value['level'] = 'H' . $value['level'];
            $value['count_num'] = sprintf('%.2f', $value['total_num'] + $value['team_total']);
            $value['ename'] = $member_info['ename'];
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            $is_seniority = Db::name('rocket_summary')->where(['member_id' => $value['member_id']])->order('id desc')->value('is_reward');
            $value['is_seniority'] = !empty($is_seniority) ? 1 : 0;//昨日本人是否合格 1是 0否

            $yestday_reward = RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => ['in', [2,3,5,6,7,8]], 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward') ?: 0.00;
            $total_reward = RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => ['in', [2,3,5,6,7,8]]])->sum('reward') ?: 0.00;
            $value['reward'] = sprintf('%.4f', $yestday_reward) .' / '. sprintf('%.4f', $total_reward);//昨日本人收益/累计收益

            $yestday_team = RocketRewardLog::alias('a')
                ->join('member_bind b', 'a.member_id=b.child_id')
                ->where(['b.member_id' => $value['member_id'], 'a.type' => 3, 'a.add_time' => ['between', [$today_start, $today_end]]])
                ->sum('a.third_money') ?: 0;
            $total_team = RocketRewardLog::alias('a')
                ->join('member_bind b', 'a.member_id=b.child_id')
                ->where(['b.member_id' => $value['member_id'], 'a.type' => 3])
                ->sum('a.third_money') ?: 0;
            $value['team_reward'] = sprintf('%.2f', $yestday_team) .' / '. sprintf('%.2f', $total_team);//昨日团队流水/累计团队流水

            $yestday_num = RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => 3, 'add_time' => ['between', [$today_start, $today_end]]])->sum('third_money') ?:0;
            $total_num = RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => 3])->sum('third_money') ?: 0;
            $value['personal_num'] = sprintf('%.2f', $yestday_num) .' / '. sprintf('%.2f', $total_num);//昨日个人流水/累计团队流水

            $yestday_personal_subscribe = RocketSubscribeTransfer::where(['member_id' => $value['member_id'], 'add_time' => ['between', [$yestday_start, $yestday_end]]])->sum('num') ?: 0;
            $personal_subscribe = RocketSubscribeTransfer::where(['member_id' => $value['member_id']])->sum('num') ?: 0;
            $value['personal_subscribe'] = sprintf('%.2f', $yestday_personal_subscribe) .' / '. sprintf('%.2f', $personal_subscribe);//昨日个人预充/累计预充

            $yestday_team_subscribe = RocketSubscribeTransfer::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['member_id'], 'add_time' => ['between', [$yestday_start, $yestday_end]]])->sum('a.num');
            $team_subscribe = RocketSubscribeTransfer::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $value['member_id']])->sum('a.num');
            $value['team_subscribe'] = sprintf('%.2f', $yestday_team_subscribe) .' / '. sprintf('%.2f', $team_subscribe);//昨日团队预充/累计预充

            $total_personal_reward = RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => 1])->sum('third_money');
            $value['total_personal_reward'] = sprintf('%.2f', $total_personal_reward);//累计本人流水
            $total_team_reward = RocketRewardLog::alias('a')
                ->join('member_bind b', 'a.member_id=b.child_id')
                ->where(['b.member_id' => $value['member_id'], 'a.type' => 1])
                ->sum('a.third_money');
            $value['total_team_reward'] = sprintf('%.2f', $total_team_reward);//累计团队流水
            $value['total_reward'] = sprintf('%.2f', $total_personal_reward + $total_team_reward);//累计总流水

            $value['is_valid'] = $value['total_num'] >= $valid_username ? 1 : 0;//本人是否有效用户 1是 0否
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 设置自动点火
     * @param int $member_id        用户ID
     * @param int $is_fire          是否自动点火 1是 0否
     * @param int $quota_type       额度类型 1每期最大额 2自定义额度
     * @param number $quota_price   自定义额度
     * @return array
     */
    static function set_fire($member_id, $is_fire, $quota_type, $quota_price = 0) {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_failure'), 'result' => null];

        if ($quota_type == 2) {
            if (empty($quota_price)) {
                $r['message'] = '请输入自定义额度';
                return $r;
            }
        }
        $data = [
            'is_fire' => $is_fire,
            'quota_type' => $quota_type,
            'quota_price' => $quota_price
        ];
        $flag = RocketMember::where(['member_id' => $member_id])->update($data);

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['result'] = $flag;
        return $r;
    }

    /**
     * 自动点火信息
     * @param int $member_id        用户ID
     * @return array
     */
    static function get_fire_info($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $list = self::where(['member_id' => $member_id])->find();
        if (!$list) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 自动点火
     * @param array $data     子闯关数据
     * @return bool
     */
    static function auto_fire($data) {
        $res = self::where(['is_fire' => 1])->select();
        if (!$res) return true;

        foreach ($res as $key => $value) {
            $num = $kmt_num = 0;
            if ($value['quota_type'] == 2) {//额度类型 1每期最大额 2自定义额度
                $num = $value['quota_price'];
            } else {
                $num = $data['max_payment'];
            }
            if ($data['kmt_rate'] > 0) {
                $kmt_num = keepPoint($num * ($data['kmt_rate'] / 100), 6);
            }

            //添加订单
            $flag = RocketOrder::add_order($value['member_id'], $data['id'], $num, $kmt_num);
            if (empty($flag) || $flag['code'] != 10000) {
                \think\Log::write($value['member_id'] . '自动点火失败' . $data['id']);
                \think\Log::write(json_encode($flag));
                continue;
            }
        }
        return true;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename,reg_time');
    }

    public function rocketLevel() {
        return $this->belongsTo('app\\common\\model\\RocketLevel', 'level', 'level')->field('level');
    }

    /**
     * 订单合同
     * @param int $member_id 用户id
     * @param int $order_type 类型 1满存 2独享
     * @param int $order_id 订单id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function order_contract($member_id, $order_type, $order_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_type) || empty($order_id))
            return $r;

        if (!in_array($order_type, [1, 2])) return $r;

        $result = [
            'contract_list' => [
                'https://sdmine.oss-cn-shanghai.aliyuncs.com/contract/template/1.jpg',
                'https://sdmine.oss-cn-shanghai.aliyuncs.com/contract/template/2.jpg',
                'https://sdmine.oss-cn-shanghai.aliyuncs.com/contract/template/3.jpg',
                'https://sdmine.oss-cn-shanghai.aliyuncs.com/contract/template/4.jpg',
                'https://sdmine.oss-cn-shanghai.aliyuncs.com/contract/template/5.jpg',
            ],
            'contract_status' => 0// 状态 0未签 1已签
        ];

        // 满存
        if ($order_type == 1) {
            $mining_pay = self::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']
                && !empty($mining_pay['contract_text'])
            ) {
                $result_json = json_decode($mining_pay['contract_text']);
                $result['contract_list'][1] = $result_json[1];
                $result['contract_list'][4] = $result_json[2];
                $result['contract_status'] = 1;
            }
        } // 独享
        elseif ($order_type == 2) {
            $mining_pay = AloneMiningPay::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']
                && !empty($mining_pay['contract_text'])
            ) {
                $result_json = json_decode($mining_pay['contract_text']);
                $result['contract_list'][1] = $result_json[1];
                $result['contract_list'][4] = $result_json[2];
                $result['contract_status'] = 1;
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 提交合同签名
     * @param int $member_id 用户id
     * @param int $order_type 类型 1满存 2独享
     * @param int $order_id 订单id
     * @param string $autograph 签名
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function submit_autograph($member_id, $order_type, $order_id, $autograph)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_type) || empty($order_id) || empty($autograph))
            return $r;

        if (!in_array($order_type, [1, 2])) return $r;

        // 满存
        if ($order_type == 1) {
            $mining_pay = self::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']) {
                $r['message'] = lang('no_signed');
                return $r;
            }

            // 生成签名文件
            $result = self::generate_contract($autograph, $order_type, $mining_pay['mining_code']);
            if ($result === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }

            // 更新状态
            $res = self::where(['id' => $mining_pay['id']])->update([
                'contract_status' => 1,
                'contract_text' => json_encode($result),
                'contract_time' => time()
            ]);
            if ($res === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }
        } // 独享
        elseif ($order_type == 2) {
            $mining_pay = AloneMiningPay::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']) {
                $r['message'] = lang('no_signed');
                return $r;
            }

            // 生成签名文件
            $result = self::generate_contract($autograph, $order_type, $mining_pay['mining_code']);
            if ($result === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }

            // 更新状态
            $res = AloneMiningPay::where(['id' => $mining_pay['id']])->update([
                'contract_status' => 1,
                'contract_text' => json_encode($result),
                'contract_time' => time()
            ]);
            if ($res === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        return $r;
    }

    /**
     * 生成合同
     * @param string $watermark_path 签名图片
     * @param int $order_type 类型
     * @param string $mining_code 合同编号
     * @return array|false
     * @throws OssException
     */
    public static function generate_contract($watermark_path, $order_type, $mining_code)
    {
        if (empty($watermark_path)) return false;
        // 目录
        $catalogue = 'common_mining';
        if ($order_type == 2) $catalogue = 'alone_mining';

        if (preg_match("/^(data:\s*image\/(\w+);base64,)/", $watermark_path, $result)) {
            $image_type = strtolower($result[2]);
            if (in_array($image_type, ['jpeg', 'jpg', 'png'])) {
                $mkdir_catalogue = ROOT_PATH . '/public/mining_img/' . $catalogue . '/';
                if (!is_dir($mkdir_catalogue))
                    mkdir($mkdir_catalogue, 0777);

                $new_file_path = $mkdir_catalogue . $mining_code . '.' . $image_type;
                $upload_result = file_put_contents($new_file_path, base64_decode(str_replace($result[1], '', $watermark_path)));
                if (empty($upload_result)) return false;
                $result_image_watermark = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '.' . $image_type, $mkdir_catalogue . $mining_code . '.' . $image_type);
            } else {
                return false;
            }
        } else {
            return false;
        }

        //合同模板
        $template_image_2 = imagecreatefromjpeg(ROOT_PATH . '/public/mining_img/template/2.jpg');
        $template_image_5 = imagecreatefromjpeg(ROOT_PATH . '/public/mining_img/template/5.jpg');

        //插入合同编号
        $font_path = ROOT_PATH . '/public/static/font/hagin.otf';
        $black = imagecolorallocate($template_image_2, 15, 23, 25);//字体颜色
        imageTtfText($template_image_2, 20, 0, 980, 210, $black, $font_path, $mining_code);

        // 签字日期
        imageTtfText($template_image_5, 15, 0, 735, 278, $black, $font_path, date('Y'));
        imageTtfText($template_image_5, 15, 0, 870, 278, $black, $font_path, date('m'));
        imageTtfText($template_image_5, 15, 0, 960, 278, $black, $font_path, date('d'));

        // 盖章日期
        imageTtfText($template_image_5, 15, 0, 235, 278, $black, $font_path, date('Y'));
        imageTtfText($template_image_5, 15, 0, 365, 278, $black, $font_path, date('m'));
        imageTtfText($template_image_5, 15, 0, 455, 278, $black, $font_path, date('d'));

        //签名图像
        $watermark_image = imagecreatefrompng($new_file_path);

        //合并合同图片
        imagecopy($template_image_2, $watermark_image, 360, 300, 0, 0, imagesx($watermark_image), imagesy($watermark_image));
        imagecopy($template_image_5, $watermark_image, 650, 150, 0, 0, imagesx($watermark_image), imagesy($watermark_image));

        //输出合并后合同图片
        imagejpeg($template_image_2, $mkdir_catalogue . $mining_code . '_2.jpg');
        $result_image_2 = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '_2.jpg', $mkdir_catalogue . $mining_code . '_2.jpg');
        imagejpeg($template_image_5, $mkdir_catalogue . $mining_code . '_5.jpg');
        $result_image_5 = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '_5.jpg', $mkdir_catalogue . $mining_code . '_5.jpg');

        if (empty($result_image_2)
            || empty($result_image_5)
        ) return false;
        return [$result_image_watermark, $result_image_2, $result_image_5];
    }

    /**
     * 图片推送oss
     * @param string $object oss路径
     * @param string $content 图片路径
     * @return false|string
     * @throws OssException
     */
    public static function oss_upload($object, $content)
    {
        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, false);
        try {
            if (!empty($object)
                && !empty($content)
            ) {
                $getOssInfo = $ossClient->uploadFile($bucket, $object, $content);
                $scheme = (!isset($_SERVER['HTTPS']) ||
                    $_SERVER['HTTPS'] == 'off' ||
                    $_SERVER['HTTPS'] == '') ? 'http' : 'https';
                return $getOssInfo['info']['url'] ?: $scheme . '://' . $bucket . '.' . $endpoint . '/' . $object;
            }
        } catch (OssException $e) {
            Log::write("合同上传:失败" . $e->getMessage());
            return false;
        }
    }

    /**
     * 统计业绩
     * @param int $member_id 用户ID
     * @param int $team_num 数量
     * @return bool
     */
    static function number_team($member_id, $team_num = 0)
    {
        // 直推业绩
        $flag = self::execute('update ' . config("database.prefix") . 'rocket_member a,' . config("database.prefix") . 'member_bind b
    set a.team_num=a.team_num+' . $team_num . ' where a.member_id = b.member_id and b.level=1 and b.child_id=' . $member_id . ';');
        if ($flag === false) return false;

        // 团队业绩
        $flag = self::execute('update ' . config("database.prefix") . 'rocket_member a,' . config("database.prefix") . 'member_bind b
    set a.team_total=a.team_total+' . $team_num . ' where a.member_id = b.member_id and  b.child_id=' . $member_id . ';');
        if ($flag === false) return false;
        return true;
    }

    /**
     * 获取预约信息
     * @param int $member_id 用户ID
     * @return array
     */
    static function get_subscribe_num($member_id) {
        $today_start = strtotime(date("Y-m-d"));
        $today_end = $today_start + 86399;

        $self_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'add_time' => ['between', [$today_start, $today_end]], 'type' => 1])->sum('num');
        $result['self_num'] = $self_num ? sprintf('%.2f', $self_num) : 0;
        $total_self_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1])->sum('num');
        $result['total_self_num'] = $total_self_num ? sprintf('%.2f', $total_self_num) : 0;

        $small_num = $total_small_num = 0;
        $small_ids = MemberBind::where(['member_id' => $member_id, 'level' => 1])->column('child_id');
        $arr = $total_arr = $a = [];
        if ($small_ids) {
            foreach ($small_ids as $item) {
                $frist = $second  = 0;
                $one = Db::name('rocket_subscribe_transfer')->where(['member_id' => $item, 'add_time' => ['between', [$today_start, $today_end]], 'type' => 1])->sum('num');
                $frist = Db::name('rocket_subscribe_transfer')->alias('a')
                    ->join('member_bind b', 'a.member_id = b.child_id')
                    ->where(['b.member_id' => $item, 'a.add_time' => ['between', [$today_start, $today_end]], 'a.type' => 1])
                    ->sum('num');
                $arr[] = sprintf('%.2f', $frist + $one);
                $total_one = Db::name('rocket_subscribe_transfer')->where(['member_id' => $item, 'type' => 1])->sum('num');
                $second = Db::name('rocket_subscribe_transfer')->alias('a')
                    ->join('member_bind b', 'a.member_id = b.child_id')
                    ->where(['b.member_id' => $item, 'a.type' => 1])
                    ->sum('num');
                $total_arr[] = sprintf('%.2f', $second + $total_one);
                $small_num = sprintf('%.2f', $small_num + $frist + $one);
                $total_small_num = sprintf('%.2f', $total_small_num + $second + $total_one);
            }
        }
        $big_num = !empty($arr) ? max($arr) : 0;
        $result['big_num'] = $small_num > 0 ? sprintf('%.2f', $big_num) : 0;
        $total_big_num = !empty($total_arr) ? max($total_arr) : 0;
        $result['total_big_num'] = $total_small_num > 0 ? sprintf('%.2f', $total_big_num) : 0;
        $result['small_num'] = $small_num > 0 ? sprintf('%.2f', $small_num - $result['big_num']) : 0;
        $result['total_small_num'] = $total_small_num > 0 ? sprintf('%.2f', $total_small_num - $result['total_big_num']) : 0;

        return $result;
    }


}