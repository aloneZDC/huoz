<?php


namespace app\common\model;

use app\h5\controller\Rocket;
use think\Db;
use think\Exception;
use think\Model;
use think\Log;

class RocketRewardLog extends Base
{
    /**
     * 添加记录
     * @param int $member_id    用户id
     * @param int $level        用户等级
     * @param string $reward    奖励
     * @param string $third_rate 基础比例
     * @param string $third_money 基础金额
     * @param int $third_id       订单ID
     * @param int $type           类型 1静态收益 2分享收益 3团队收益 4幸运池加权分红
     * @param int $share_type     分享类型 1一代 2二代 3三代
     */
    static function addItem($member_id, $level, $reward, $third_rate, $third_money, $third_id, $type, $share_type = 0) {
        $data = [
            'member_id' => $member_id,
            'member_level' => $level,
            'reward' => $reward,
            'type' => $type,
            'share_type' => $share_type,
            'third_rate' => $third_rate,
            'third_money' => $third_money,
            'third_id' => $third_id,
            'add_time' => time()
        ];

        return self::insertGetId($data);
    }

    /**
     * 获取助力燃料
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_help_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $list = self::where(['member_id' => $member_id, 'type' => 2, 'currency_id' => $profit_currency_id])
            ->field('id,reward,share_type,add_time')
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            if ($value['share_type'] == 1) {
                $value['type_name'] = '1D分享赠与';
            } elseif ($value['share_type'] == 2) {
                $value['type_name'] = '2D分享赠与';
            } else {
                $value['type_name'] = '3D分享赠与';
            }
            $value['reward'] = '+' . $value['reward'];
            $value['currency_name'] = '赠与收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取动力燃料
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_power_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $list = self::where(['member_id' => $member_id, 'type' => 3, 'currency_id' => $profit_currency_id])
            ->field('id,reward,add_time')
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['reward'] = '+' . $value['reward'];
            $value['type_name'] = '管理赠与';
            $value['currency_name'] = '赠与收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename,reg_time');
    }

    /**
     * 结算服务奖
     * @param array $data           闯关数据
     * @param array $today_config   时间数据
     */
    static function settlement_centre($data, $today_config) {
        $res = RocketMember::alias('a')->join('member_bind b', 'a.member_id=b.member_id')->where(['b.child_id' => $data['member_id'], 'is_centre' => 1])->find();
        if (!$res) {
            return true;
        }

        $centre_rate = RocketConfig::getValue('centre_rate', 1);
        $reward = sprintf('%.6f', $data['money'] * ($centre_rate / 100));
        $currency_id = RocketConfig::getValue('profit_currency_id');
        $currency_user = CurrencyUser::getCurrencyUser($data['member_id'], $currency_id);
        if (empty($currency_user)) return false;
        Db::startTrans();
        try {
            if ($reward > 0) {
                $item_id = RocketRewardLog::addItem($currency_user['member_id'], $res['level'], $reward, $centre_rate, $data['money'], $data['id'], 5);

                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7116, 'rocket_income_centre', 'in', $reward, $item_id);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $reward);
                if ($flag === false) throw new Exception("添加资产失败");
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            \think\Log::write($e->getMessage());
        }

        return false;
    }

    /**
     * 预约充值记录-结算奖励
     * @param array $data           闯关数据
     * @param array $today_config   时间数据
     */
    static function settlement_reward($data, $today_config) {
        //推荐奖
        self::get_share_reward($data['member_id'], $data['num'], $data['id']);

        //级差奖
        self::team_reward($data['member_id'], $data['num'], $data['id']);

        //服务奖
        self::centre_reward($data['member_id'], $data['num'], $data['id']);

        return true;
    }

    /**
     * 区县合伙人奖励
     * @param array $today_config 时间数据
     */
    static function area_reward($today_config) {
        //判断区县奖励开关
        $area_reward_open = RocketConfig::getValue('area_reward_open');
        if ($area_reward_open != 1) {
            return true;
        }

        $res = RocketMember::where(['is_area' => 1])->select();
        if (!$res) return true;

        //当日预约数量
        $welfare = \app\common\model\RocketSubscribeTransfer::where(['add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]], 'type' => 1])->sum('num');
        //区县合伙人奖励比例
        $welfare_reward_rate = RocketConfig::getValue('area_reward_rate');
        //判断当日预约数量或奖励比例是否为0
        if ($welfare <= 0 || $welfare_reward_rate <= 0) {
            return true;
        }
        //人数
        $num = count($res);
        //当日预约数量 * 区县合伙人奖励比例 / 人数
        $reward = sprintf('%.6f', $welfare * ($welfare_reward_rate / 100) / $num);
        if ($reward <= 0) {
            return true;
        }
        $currency_id = RocketConfig::getValue('profit_currency_id');
        if (!$currency_id) {
            return true;
        }
        try {
            Db::startTrans();

            foreach ($res as $key => $value) {
                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);

                $item_id = RocketRewardLog::addItem($currency_user['member_id'], $value['level'], $reward, $welfare_reward_rate, $welfare, 1, 9);
                if ($item_id) {
                    $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                    if ($flag === false) throw new Exception("添加收益记录失败");
                }

                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7126, 'area_reward', 'in', $reward, $item_id);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $reward);
                if ($flag === false) throw new Exception("添加资产失败");
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
        }
    }

    /**
     * 服务中心奖
     * @param int $member_id 用户ID
     * @param string $money 入金
     * @param int $order_id 订单ID
     */
    static function centre_reward($member_id, $money, $order_id) {
        $res = RocketMember::alias('a')->join('member_bind b', 'a.member_id=b.member_id')->field('a.level,a.member_id')->where(['b.child_id' => $member_id, 'is_centre' => 1])->order('b.level asc')->find();
        if (!$res) {
            return true;
        }

        $centre_rate = RocketConfig::getValue('centre_rate', 1);
        $reward = sprintf('%.6f', $money * ($centre_rate / 100));
        $currency_id = RocketConfig::getValue('profit_currency_id');
        $currency_user = CurrencyUser::getCurrencyUser($res['member_id'], $currency_id);
        if (empty($currency_user)) return false;
        Db::startTrans();
        try {
            if ($reward > 0) {
                $item_id = RocketRewardLog::addItem($currency_user['member_id'], $res['level'], $reward, $centre_rate, $money, $order_id, 5);
                if ($item_id) {
                    $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                    if ($flag === false) throw new Exception("添加收益记录失败");
                }

                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7116, 'rocket_income_centre', 'in', $reward, $item_id);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $reward);
                if ($flag === false) throw new Exception("添加资产失败");

                $flag = RocketMember::where(['member_id' => $currency_user['member_id']])->setInc('n_centre_reward', $reward);
                if ($flag === false) throw new Exception("更新累计服务津贴失败");
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
        }

        return false;
    }

    /**
     * 团队收益
     * @param int $member_id 用户ID
     * @param string $money 入金
     * @param int $order_id 订单ID
     */
    static function team_reward($member_id, $money, $order_id) {
        $res = RocketMember::alias('a')->join(config("database.prefix") . 'member_bind b', 'a.member_id=b.member_id')->field('a.member_id,a.level')->where(['b.child_id' => $member_id, 'a.level' => ['gt', 0]])->order('b.level ASC')->select();
        if (!$res) return true;

        $currency_id = RocketConfig::getValue('profit_currency_id');
        $qualified = RocketConfig::getValue('check_reward_qualified');
        $subscribe_id = RocketConfig::getValue('subscribe_currency_id');//预约池账户
        Db::startTrans();
        try {
            $first = RocketLevel::order('level DESC')->value('profit');//最大级差
            $second = 0;//级别
            $third = 0;//已存在的级差
            $equality = 0;//是否已领平级奖励
            $num = 0;//平级奖励
            foreach ($res as $key => $value) {
                //判断用户存在预约账户金额大于等于100
                $is_check = Db::name('rocket_summary')->where(['member_id' => $value['member_id']])->order('id desc')->value('is_reward');
                $subscribe = CurrencyUser::getCurrencyUser($value['member_id'], $subscribe_id);
                if ($is_check != 1) {
                    continue;
                }

                $level_config = RocketLevel::where(['level' => $value['level']])->find();
                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
                $max_level = RocketLevel::where(['equality' => ['gt', 0]])->value('level');

                $income = $rate = 0;
                if ($value['level'] > $second && $first > 0) {//级差
                    $reward = $level_config['profit'];
                    if ($third > 0) {
                        $rate = $reward - $third;
                    } else {
                        $rate = $reward;
                    }
                    $income = sprintf('%.6f',$money * ($rate / 100));
                    $second = $value['level'];
                    $first = $first - $rate;
                    $third = $third + $rate;
                    //H6级差金额
                    if ($value['level'] == $max_level) {
                        $num = $income;
                    }
                } elseif ($value['level'] == $second && $equality == 0 && $value['level'] == $max_level) {//H6的平级奖励
                    $rate = $level_config['equality'];
                    if ($num > 0) {
                        $income = sprintf('%.6f',$num * ($rate / 100));
                        $equality = 1;
                    }
                }

                //账本类型
                $account_book_id = 7123;
                $account_book_content = 'rocket_income_team3';
                if (!empty($income)) {
                    if ($equality == 1) {//平级：辅导奖
                        // 收益记录
                        $item_id = RocketRewardLog::addItem($currency_user['member_id'], $value['level'], $income, $rate, $money, $order_id, 8);

                        //增加账本 增加资产
                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7117, 'rocket_income_flat', 'in', $income, $item_id);
                        if ($flag === false) throw new Exception("添加账本失败");
                    } else {//级差：管理奖
                        // 收益记录
                        $item_id = RocketRewardLog::addItem($currency_user['member_id'], $value['level'], $income, $rate, $money, $order_id, 7);

                        //增加账本 增加资产
                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
                        if ($flag === false) throw new Exception("添加账本失败");
                    }
                    if ($item_id) {
                        $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                        if ($flag === false) throw new Exception("添加收益记录失败");
                    }

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
                    if ($flag === false) throw new Exception("添加资产失败");

                    $flag = RocketMember::where(['member_id' => $value['member_id']])->setInc('n_team_reward', $income);
                    if ($flag === false) throw new Exception("更新累计级差奖失败");
                }
            }
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('团队收益失败：订单' . $order_id);
            return false;
        }

        return true;
    }

    /**
     * 获取分享收益
     * @param int $member_id 用户ID
     * @param int $num    数量
     * @param int $id    数据id
     */
    static function get_share_reward($member_id, $num, $id) {
        $member_info = MemberBind::where(['child_id' => $member_id, 'level' => ['in', [1,2]]])->select();
        $qualified = RocketConfig::getValue('check_reward_qualified');//获取奖励资格
        $subscribe_id = RocketConfig::getValue('subscribe_currency_id');//预约池账户
        $valid_username = RocketConfig::getValue('valid_username');

        Db::startTrans();
        try {
            foreach ($member_info as $k => $v) {
                //判断用户预约池账户余额小于100
                $subscribe = CurrencyUser::getCurrencyUser($v['member_id'], $subscribe_id);
                $is_check = Db::name('rocket_summary')->where(['member_id' => $v['member_id']])->order('id desc')->value('is_reward');
                if ($is_check != 1) {
                    continue;
                }
                $valid_num = RocketMember::where(['member_id' => $v['member_id']])->value('valid_num');
                $count_num = MemberBind::alias('a')->join('rocket_subscribe_transfer b', 'a.child_id=b.member_id')->where(['a.member_id' => $v['member_id'], 'b.num' => ['egt', $valid_username], 'a.level' => 1])->count('a.member_id');
                if ($count_num > $valid_num) {
                    $valid_num = $count_num;
                }
                $share_config = RocketShareConfig::where(['valid_num' => ['elt', $valid_num]])->order('id DESC')->find();

                $currency_id = RocketConfig::getValue('profit_currency_id');
                $currency_user = CurrencyUser::getCurrencyUser($v['member_id'], $currency_id);

                $reward = 0;
                if ($share_config) {
                    $first_reward = $second_reward = $third_reward = 0;
                    $member_level = RocketMember::where(['member_id' => $v['member_id']])->value('level');
                    if ($share_config['first'] > 0 && $v['level'] == 1) {//第一代投入金额的收益
                        $first_reward = sprintf('%.6f', $num * ($share_config['first'] / 100));
                        // 收益记录
                        if ($first_reward > 0) {
                            $item_id = RocketRewardLog::addItem($v['member_id'], $member_level, $first_reward, $share_config['first'], $num, $id, 6, 1);
                            if ($item_id) {
                                $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                                if ($flag === false) throw new Exception("添加收益记录失败");
                            }
                        }
                    }
                    if ($share_config['second'] > 0 && $v['level'] == 2) {//第二代投入金额的收益
                        $second_reward = sprintf('%.6f', $num * ($share_config['second'] / 100));
                        // 收益记录
                        if ($second_reward > 0) {
                            $item_id = RocketRewardLog::addItem($v['member_id'], $member_level, $second_reward, $share_config['second'], $num, $id, 6, 2);
                            if ($item_id) {
                                $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                                if ($flag === false) throw new Exception("添加收益记录失败");
                            }
                        }
                    }
                    $reward = sprintf('%.6f', $first_reward + $second_reward);
                }

                //账本类型
                $account_book_id = 7102;
                $account_book_content = 'rocket_income_share';
                if ($reward > 0) {
                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $reward, $id);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $reward);
                    if ($flag === false) throw new Exception("添加资产失败");

                    $flag = RocketMember::where(['member_id' => $v['member_id']])->setInc('n_share_reward', $reward);
                    if ($flag === false) throw new Exception("更新累计分享收益失败");
                }
            }

            Db::commit();
            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('分享收益失败：'.$member_id);
            return false;
        }
    }

    //获取有效用户
    static function getValidNum($member_id) {
//        $res = Member::where(['pid' => $member_id])->column('member_id');
//        $result = 0;
//        if ($res) {
//            foreach ($res as $key => $value) {
//                $is_check = Db::name('rocket_summary')->where(['member_id' => $value])->order('id desc')->value('is_reward');
//                if ($is_check) {
//                    $result = $result + 1;
//                }
//            }
//        }
        $result = 0;
        $valid_username = RocketConfig::getValue('valid_username');
        $num = RocketMember::alias('a')->join('member_bind b', 'a.member_id = b.child_id')
            ->where(['b.member_id' => $member_id, 'b.level' => 1, 'a.total_num' => ['egt', $valid_username]])->count('a.member_id');
        if ($num) {
            $result = $num;
        }
        return $result;
    }

    /**
     * 获取预约助力燃料
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_subscribe_help_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $list = self::where(['member_id' => $member_id, 'type' => 6, 'currency_id' => $profit_currency_id])
            ->field('id,reward,share_type,add_time')
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            if ($value['share_type'] == 1) {
                $value['type_name'] = '1D分享赠与';
            } elseif ($value['share_type'] == 2) {
                $value['type_name'] = '2D分享赠与';
            } else {
                $value['type_name'] = '3D分享赠与';
            }
            $value['reward'] = '+' . $value['reward'];
            $value['currency_name'] = '赠与收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取预约动力燃料
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_subscribe_power_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $list = self::where(['member_id' => $member_id, 'currency_id' => $profit_currency_id])
            ->whereIn('type', [7,8])
            ->field('id,reward,add_time,type')
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['reward'] = '+' . $value['reward'];
            if ($value['type'] == 7) {
                $value['type_name'] = '管理赠与';
            } else {
                $value['type_name'] = '辅导赠与';
            }

            $value['currency_name'] = '赠与收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取预约服务津贴
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_centre_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $list = self::where(['member_id' => $member_id, 'type' => 5, 'currency_id' => $profit_currency_id])
            ->field('id,reward,add_time')
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['reward'] = '+' . $value['reward'];
            $value['type_name'] = '服务赠与';
            $value['currency_name'] = '赠与收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取预约奖励信息
     * @param int $member_id   用户ID
     * @return array
     */
    static function get_subscribe_info($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $result = RocketMember::where(['member_id' => $member_id])->field('s_share_reward,s_team_reward')->find();
        if (!$result) return $r;

        $today_start = strtotime(date("Y-m-d"));
        $today_end = $today_start + 86399;
        $share_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 6, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('reward');
        $result['yesterday_share_reward'] = $share_reward ? sprintf('%.4f', $share_reward): 0;//昨日助力
        $team_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 7, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('reward');
        $result['yesterday_team_reward'] = $team_reward ? sprintf('%.4f', $team_reward): 0;//昨日动力
        $flat_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 8, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('reward');
        $result['yesterday_flat_reward'] = $flat_reward ? sprintf('%.4f', $flat_reward): 0;//昨日平级
        $total_first_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 6, 'share_type' => 1, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('third_money');
        $result['total_first_reward'] = $total_first_reward ? sprintf('%.4f', $total_first_reward): 0;//累计1D
        $total_second_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 6, 'share_type' => 2, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('third_money');
        $result['total_second_reward'] = $total_second_reward ? sprintf('%.4f', $total_second_reward): 0;//累计2D
        $subscribe = RocketRewardLog::where(['member_id' => $member_id, 'type' => 7, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('third_money');
        $result['yesterday_subscribe'] = $subscribe ? sprintf('%.4f', $subscribe): 0;//昨日团队预充
        $total_subscribe = RocketRewardLog::where(['member_id' => $member_id, 'type' => 7])->sum('third_money');
        $result['total_subscribe'] = $total_subscribe ? sprintf('%.4f', $total_subscribe): 0;//累计团队预充
        $flat_subscribe = RocketRewardLog::where(['member_id' => $member_id, 'type' => 8, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('third_money');
        $result['yesterday_flat_subscribe'] = $flat_subscribe ? sprintf('%.4f', $flat_subscribe): 0;//昨日平级预充
        $centre_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 5, 'add_time' => ['between', [$today_start, $today_end]]])
            ->sum('reward');
        $result['yesterday_centre_reward'] = $centre_reward ? sprintf('%.4f', $centre_reward): 0;//昨日服务
        $total_centre_reward = RocketRewardLog::where(['member_id' => $member_id, 'type' => 5])
            ->sum('reward');
        $result['total_centre_reward'] = $total_centre_reward ? sprintf('%.4f', $total_centre_reward): 0;//累计服务

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 结算预购分红
     * @param int $num         当日预购数量
     * @return bool
     */
    static function settlement_special($num) {
        Db::startTrans();
        try {
            $member_id = 98581;//预购分红账号
            $rate = 2;//预购分红比例
            $currency_id = RocketConfig::getValue('profit_currency_id');
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
            if (!$currency_user) {
                throw new Exception("查询资金账户失败");
            }

            $reward = sprintf('%.6f', $num * ($rate / 100));

            //账本类型
            $account_book_id = 7125;
            $account_book_content = 'rocket_subscribe_bonus';
            if ($reward > 0) {
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $reward, 1);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $reward);
                if ($flag === false) throw new Exception("添加资产失败");
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
        }
        return false;
    }
}