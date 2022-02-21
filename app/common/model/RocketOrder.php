<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;
use think\Log;

class RocketOrder extends Base
{
    const TYPE_FIRST = 1;
    const TYPE_SECOND = 2;
    const TYPE_THIRD = 3;
    const TYPE_FOURTH = 4;

    const STATUS_ENUM = [
        self::TYPE_FIRST => "抢单",
        self::TYPE_SECOND => "预约",
        self::TYPE_THIRD => "复利",
        self::TYPE_FOURTH => "预购抢单",
    ];

    /**
     * 新增订单
     * @param int $member_id    用户ID
     * @param int $product_id  子闯关ID
     * @param number $num      支付金额
     * @param number $kmt_num  kmt燃料
     */
    static function add_order($member_id, $product_id, $num, $kmt_num) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $goods_info = RocketGoodsList::where(['id' => $product_id, 'status' => 1])->find();
        if (!$goods_info) return $r;

        $is_order = self::where(['member_id' => $member_id, 'goods_list_id' => $product_id])->find();
        if ($is_order) {
            $r['message'] = '不能重复参与此关';
            return $r;
        }

        $today = time();//当天
        $date = intval(date('H'));//当前小时
        $start_date = date('Y-m-d', $goods_info['last_settlement_time']);
        //判断闯关是否可参与
        if ($today < $goods_info['start_time']) {
            $r['message'] = $goods_info['name'] . '未开启';
            return $r;
        }
        if ($today > $goods_info['end_time']) {
            $r['message'] = $goods_info['name'] . '已结束';
            return $r;
        }
        if ($goods_info['rocket_status'] == 2) {
            $r['message'] = $goods_info['name'] . '已结束';
            return $r;
        }
        if ($goods_info['rocket_status'] == 3) {
            $r['message'] = $goods_info['name'] . '已结算';
            return $r;
        }

        if ($num <= 0) {
            $r['message'] = '请输入参与金额';
            return $r;
        }
        if ($kmt_num <= 0 && $goods_info['kmt_rate'] > 0) {
            $r['message'] = '燃料不能为空';
            return $r;
        }
        if ($num < $goods_info['min_payment']) {
            $r['message'] = '参与金额不能小于最小支付金额';
            return $r;
        }
        if ($num > $goods_info['max_payment']) {
            $r['message'] = '参与金额不能大于最大支付金额';
            return $r;
        }

        if (sprintf('%.2f', $num + $goods_info['finish_money']) > sprintf('%.2f', $goods_info['price'])) {
            $r['message'] = '参与金额不能大于闯关金额';
            return $r;
        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $goods_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($currency_user['num'] < $num) {
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
        // 判断账户是否足够
        if ($platform_currency_user['num'] < $kmt_num) {
            $r['message'] = '燃料' . lang('insufficient_balance');
            return $r;
        }

        try {
            Db::startTrans();
            $data = [
                'member_id' => $member_id,
                'goods_list_id' => $product_id,
                'order_code' => self::GetRandOrderSn(),
                'money' => $num,
                'kmt_num' => $kmt_num,
                'level_num' => $goods_info['level'],
                'add_time' => time()
            ];

            $result = self::insertGetId($data);
            // 扣除资产
            if ($num > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7105, 'rocket_play_game', 'out', $num, $result, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }
            // 扣除平台币资产
            if ($kmt_num > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($platform_currency_user['member_id'], $platform_currency_user['currency_id'], 7106, 'rocket_paly_mtk', 'out', $kmt_num, $result, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id'], 'num' => $platform_currency_user['num']])->setDec('num', $kmt_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = RocketKmtLog::addItem($platform_currency_user['member_id'], $product_id, $kmt_num);
                if (!$flag) throw new Exception('生成燃料记录失败');
            }
            //更新闯关完成金额
            //$flag = Db::name('rocket_goods_list')->where(['id' => $product_id])->lock(true)->find();

            $flag = RocketGoodsList::where(['id' => $product_id])->setInc('finish_money', $num);
            if ($flag === false) throw new Exception('参与金额不能大于闯关金额');
            //更新用户汇总
            $flag = RocketMember::addItem($member_id, $num);
            if ($flag === false) throw new Exception('更新用户汇总失败');

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $result;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    /**
     * 生成随机订单号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function GetRandOrderSn()
    {
        $string = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        $cdkey = "";
        for ($i = 0; $i < 20; $i++) {
            $cdkey .= $string[rand(0, strlen($string) - 1)];
        }

        $out_trade_no = $cdkey . time();
        $is_out_trade_no = self::where('order_code', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }

    /**
     * 闯关结算
     * @param array $data 闯关信息
     * @param array $today_config 时间信息
     * @param array $config 闯关配置
     */
    static function settlement_order($data, $today_config, $config) {
        //判断是否结算
        $finsh_date = strtotime(date('Y-m-d', $data['end_time'] + 86400));//凌晨
        if (time() >= $data['start_time'] && $finsh_date >= $today_config['today_start']) {
            //更新闯关时间
            $flag = RocketGoodsList::update_goods($data['id'], $today_config['today_start']);
            if ($flag === false) return false;
            if ($finsh_date != $today_config['today_start']) {//没到结算日，只更新闯关时间
                return true;
            }
        } else {
            return true;
        }
        //结算关
        $settlement_level = self::getSettlementLevel($data['level']);
        Db::startTrans();
        try {
            //判断闯关是否完成
            $finsh_money = self::where(['goods_list_id' => $data['id']])->sum('money');
            if (sprintf('%.2f', $finsh_money) >= sprintf('%.2f', $data['price'])) {//成功
                $level = 0;
                if ($data['level'] >= $settlement_level) {
                    $level = keepPoint($data['level'] - $settlement_level + 1, 0);
                }
                //结算关id
                $first_id = RocketGoodsList::where(['goods_id' => $data['goods_id'], 'level' => $level, 'status' => 2, 'rocket_status' => 2])->value('id');
                if ($level > 0 && $first_id) {
                    $res = self::where(['goods_list_id' => $first_id, 'level_num' => $level, 'status' => 0])->order('member_id desc')->select();
                    if (!$res) {
                        Db::commit();
                        return true;
                    }
                    $goods_info = RocketGoodsList::where(['id' => $first_id])->find();
                    if (!$goods_info) {
                        Db::commit();
                        return true;
                    }

                    foreach ($res as $key => $value) {
                        //本金
                        $capital = self::handle_principal($value['member_id'], $first_id, $level, $value['id'], 1);
                        //静态收益
                        $reward = self::get_statics_reward($value['member_id'], $first_id, $level, $data['profit'], $value['id']);
                        //团队收益
                        if ($value['type'] == 3) {//自动复利，获得级差奖
                            self::team_reward($value['member_id'], $value['money'], $value['id']);
                        }

                        //更新订单数据
                        $orderdata = [
                            'capital' => $capital,
                            'statics_reward' => $reward,
//                            'share_reward' => $share_reward,
                            'is_team' => 1,
                            'status' => 1,
                            'settlement_time' => time(),
                            'is_auto' => 3
                        ];
                        $flag = self::where(['id' => $value['id']])->update($orderdata);
                        if ($flag === false) throw new Exception("更新订单数据失败");

                        //更新用户汇总
                        $flag = RocketMember::where(['member_id' => $value['member_id']])->update(['statics_reward' => ['inc', $reward]]);
                        if ($flag === false) throw new Exception("更新用户汇总失败");
                    }

                    //分享收益
//                    $member_info = RocketMember::select();
//                    $qualified = RocketConfig::getValue('check_reward_qualified');//获取奖励资格
//                    $transfer_id = RocketConfig::getValue('transfer_currency_id');//便捷账户
//                    $transfer_num = RocketConfig::getValue('transfer_currency_num');//便捷账户获取奖励资格
//                    $subscribe_id = RocketConfig::getValue('subscribe_currency_id');//预约池账户
//                    foreach ($member_info as $k) {
//                        //判断用户存在参与未结算的闯关且金额小于100或者便捷账户金额小于500或者预约池账户余额小于100
//                        $is_check = self::where(['member_id' => $k['member_id'], 'status' => 0])->sum('money');
//                        $transfer = CurrencyUser::getCurrencyUser($k['member_id'], $transfer_id);
//                        $subscribe = CurrencyUser::getCurrencyUser($k['member_id'], $subscribe_id);
//                        if ($is_check < $qualified && $transfer['num'] < $transfer_num && $subscribe['num'] < $qualified) {
//                            continue;
//                        }
//                        $share_reward = self::get_share_reward($k['member_id'],$first_id, $level);
//                    }
                    $finsh_money = self::where(['goods_list_id' => $first_id])->sum('money');//闯关总金额
                    //幸运池
                    $lucky_reward = 0;
                    if ($goods_info['lucky_Jackpot'] > 0) {
                        $lucky_reward = sprintf('%.2f', $finsh_money * ($goods_info['lucky_Jackpot'] / 100));
                    }
                    //基金
                    $fund_reward = 0;
                    if ($goods_info['fund_Jackpot'] > 0) {
                        $fund_reward = sprintf('%.2f', $finsh_money * ($goods_info['fund_Jackpot'] / 100));
                        $flag = self::handle_warehouse($fund_reward, 1);
                        if ($flag === false) throw new Exception("添加市值舱账本失败");
                    }
                    //平台
                    $platform_reward = 0;
                    if ($goods_info['platform_Jackpot'] > 0) {
                        $platform_reward = sprintf('%.2f', $finsh_money * ($goods_info['platform_Jackpot'] / 100));
                        $flag = self::handle_warehouse($platform_reward, 2);
                        if ($flag === false) throw new Exception("添加工具舱账本失败");
                    }
                    //更新主商品数据
                    $goods_id = RocketGoodsList::where(['id' => $first_id])->value('goods_id');
                    $gdata = [
                        'warehouse1' => ['inc', $lucky_reward],
                        'warehouse2' => ['inc', $fund_reward],
                        'warehouse3' => ['inc', $platform_reward],
                    ];
                    $flag = RocketGoods::where(['id' => $goods_id])->update($gdata);
                    if ($flag === false) throw new Exception("更新主商品数据失败");

                    $gldata = [
                        'warehouse1' => $lucky_reward,
                        'warehouse2' => $fund_reward,
                        'warehouse3' => $platform_reward,
                        'rocket_status' => 3//已结算
                    ];
                    $flag = RocketGoodsList::where(['id' => $first_id])->update($gldata);
                    if ($flag === false) throw new Exception("更新用户汇总失败");

                    //更新闯关数据(已结算)
                    $flag = RocketGoodsList::update_goods($data['id'], $today_config['today_start'], 2);
                    if ($flag === false) throw new Exception("更新闯关数据失败");
                } else {
                    //更新闯关时间(已结束)
                    $flag = RocketGoodsList::update_goods($data['id'], $today_config['today_start'], 2);
                    if ($flag === false) throw new Exception("更新闯关时间失败");
                }
                //更新状态
                $flag = RocketGoodsList::where(['id' => $data['id']])->update(['status' => 2]);
                if ($flag === false) throw new Exception("更新状态失败");
            } else {//失败
                $goods_ids = RocketGoodsList::where(['goods_id' => $data['goods_id'], 'rocket_status' => ['in', [0,1,2]], 'end_time' => ['elt', $today_config['today_start']], 'is_show' => 1])->column('id');
                $res = self::where(['goods_list_id' => ['in', $goods_ids], 'status' => 0])->order('member_id desc')->select();

                $max_level = self::where(['goods_list_id' => $data['id'], 'status' => 0])->order('level_num DESC')->value('level_num');
                //判断是否大于第三关（初始结算关）
                if ($data['level'] > $settlement_level) {
                    $warehouse = RocketGoods::where(['id' => $data['goods_id']])->value('warehouse1');
                    if ($res) {
                        foreach ($res as $key => $value) {
                            $integral = 0;
                            if ($value['level_num'] == $max_level) {//失败关
                                //本金(本金全部返还+6%加权)
                                $capital = self::handle_principal($value['member_id'], $value['goods_list_id'], $value['level_num'], $value['id'], 2, $warehouse);
                            } else {//失败关的上一关和上上关
                                //本金(本金返还70%+积分30%)
                                $capital = self::handle_principal($value['member_id'], $value['goods_list_id'], $value['level_num'], $value['id'], 3);
                                //积分
                                $integral = sprintf('%.2f', $value['money'] - $capital);
                            }

                            //更新订单数据
                            $orderdata = [
                                'capital' => $capital,
                                'integral' => $integral,
                                'status' => 2,
                                'settlement_time' => time(),
                                'is_auto' => 3
                            ];
                            $flag = self::where(['id' => $value['id']])->update($orderdata);
                            if ($flag === false) throw new Exception("更新订单数据失败");
                        }
                    }
                } else {
                    if ($res) {
                        foreach ($res as $key => $value) {
                            //本金
                            $capital = self::handle_principal($value['member_id'], $value['goods_list_id'], $value['level_num'], $value['id'], 2);

                            //更新订单数据
                            $orderdata = [
                                'capital' => $capital,
                                'status' => 2,
                                'settlement_time' => time(),
                                'is_auto' => 3
                            ];
                            $flag = self::where(['id' => $value['id']])->update($orderdata);
                            if ($flag === false) throw new Exception("更新订单数据失败");
                        }
                    }
                }
                //更新子闯关信息
                foreach ($goods_ids as $gv) {
                    //更新闯关时间
                    $flag = RocketGoodsList::update_goods($gv, $today_config['today_start'], 3);
                    if ($flag === false) throw new Exception("更新闯关时间失败");
                    //更新状态
                    $flag = RocketGoodsList::where(['id' => $gv])->update(['status' => 3]);
                    if ($flag === false) throw new Exception("更新状态失败");
                }
                //清除幸运舱
                $flag = RocketGoods::where(['id' => $data['goods_id']])->update(['warehouse1' => 0]);
                if ($flag === false) throw new Exception("清除幸运舱失败");
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('闯关结算失败：'.$data['id']);
            return false;
        }

        return true;
    }

    /**
     * 获取结算关数
     * @param int $level     当前关
     */
    static function getSettlementLevel($level) {
        $result = 4;
//        if ($level <= 10) {//第1关至第10关（每3关清算一轮）
//            $result = 3;
//        } elseif ($level <= 20) {//第11关至第20关（每4关清算一轮）
//            $result = 4;
//        } elseif ($level > 20) {//第21关以后（每5关清算一轮）
//            $result = 5;
//        }
        return $result;
    }

    /**
     * 处理市值舱、工具舱
     * @param int $num       金额
     * @param int $type      1市值舱 2工具舱
     */
    static function handle_warehouse($num, $type) {
        if ($num <= 0) {
            return true;
        }
        //获得市值舱、工具舱的用户
        $show_user_id = RocketConfig::getValue('show_user_id');
        if ($show_user_id) {
            $reward_currency_id = RocketConfig::getValue('profit_currency_id');
            $currency_user = CurrencyUser::getCurrencyUser($show_user_id, $reward_currency_id);
            if (empty($currency_user)) return false;

            if ($type == 1) {//市值舱
                //账本类型
                $account_book_id = 7107;
                $account_book_content = 'fund_warehouse';
            } else {//工具舱
                //账本类型
                $account_book_id = 7108;
                $account_book_content = 'platform_warehouse';
            }

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $num, 1);
            if ($flag === false) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $num);
            if ($flag === false) throw new Exception("添加资产失败");
            return $flag;
        }
        return true;
    }

    /**
     * 处理本金返还
     * @param int $member_id 用户ID
     * @param int $goods_list_id 子商品ID
     * @param int $level 闯关数
     * @param int $id    订单id
     * @param int $type    1成功 2失败(本金全部返还+6%加权) 3失败(本金返还70%+积分30%)
     * @param number $warehouse    幸运舱奖池
     */
    static function handle_principal($member_id, $goods_list_id, $level, $id, $type, $warehouse = 0) {
        $result = 0;
        $res = self::where(['member_id' => $member_id, 'goods_list_id' => $goods_list_id, 'level_num' => $level, 'status' => 0, 'id' => $id])->find();
        if (!$res) return $result;

        $goods_info = RocketGoodsList::where(['id' => $goods_list_id])->find();
        if (!$goods_info) return $result;

        $money = $res['money'];
        $currency_id = RocketConfig::getValue('reward_currency_id');
        $reward = 0;
        $profit = $goods_info['lucky_Jackpot'];
        if ($type == 2) {
            $fail_lucky_rate = RocketConfig::getValue('fail_lucky_rate');
            $reward = sprintf('%.6f', ($money / $goods_info['price']) * ($warehouse * ($fail_lucky_rate / 100)));
        } elseif ($type == 3) {
            $fail_principal_rate = RocketConfig::getValue('fail_principal_rate');
            $money = sprintf('%.6f', $res['money'] * ($fail_principal_rate / 100));
        }
        $result = $money;

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) return $result;

        Db::startTrans();
        try {
            if ($money > 0) {//本金
                //账本类型
                $account_book_id = 7112;
                $account_book_content = 'rocket_income_capital';
                $special_user_id = RocketConfig::getValue('special_user_id');
                $special_user_id = explode(',', $special_user_id);
                $subscribe_currency_id = RocketConfig::getValue('subscribe_currency_id');

                //闯关成功并且自动复利和不是特殊账号，本金返还到预约池
                if ($type == 1 || in_array($member_id, $special_user_id)) {//预约排队
                    //$subscribe_currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
                    $subscribe_currency_user = $currency_user;//可用账户

                    $flag = AccountBook::add_accountbook($subscribe_currency_user['member_id'], $subscribe_currency_user['currency_id'], 7112, 'rocket_income_capital', 'in', $money, $id);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $subscribe_currency_user['cu_id']])->setInc('num', $money);
                    if ($flag === false) throw new Exception("添加资产失败");
                    //$result = 0;//自动复利时，本金为0
                } else {//闯关失败
                    //爆仓当前关抢单订单、特殊账号闯关，或者闯关成功取消复利返回可用账户
                    $subscribe_currency_user = $currency_user;
                    //爆仓当前关复利和预约订单、爆仓前三关且不是特殊账号返回预约池账户
//                    if ((in_array($res['type'], [2,3,4]) && $type == 2) || $type == 3) {
//                        if (!in_array($member_id, $special_user_id)) {
//                            $subscribe_currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
//                            if ($type == 3) {
//                                $flag = RocketSubscribeTransfer::addItem($member_id, 2, $money);
//                                if ($flag === false) throw new Exception("添加预约池充值记录失败");
//                            }
//                        }
//                    }
//                    if ((in_array($res['type'], [2,3,4]) && $type == 2)) {
//                        if (!in_array($member_id, $special_user_id)) {
//                            $subscribe_currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
//                        }
//                    }
                    $flag = AccountBook::add_accountbook($subscribe_currency_user['member_id'], $subscribe_currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $money, $id);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $subscribe_currency_user['cu_id'], 'num' => $subscribe_currency_user['num']])->setInc('num', $money);
                    if ($flag === false) throw new Exception("添加资产失败");
                }

                if ($type == 3) {//积分
                    $integral_currency_id = RocketConfig::getValue('integral_currency_id');
                    $integral_currency_user = CurrencyUser::getCurrencyUser($member_id, $integral_currency_id);
                    $integral = sprintf('%.6f', $res['money'] - $money);
                    if ($integral > 0) {
                        //增加账本 增加资产
                        $flag = AccountBook::add_accountbook($integral_currency_user['member_id'], $integral_currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $integral, $id);
                        if ($flag === false) throw new Exception("添加账本失败");

                        $flag = CurrencyUser::where(['cu_id' => $integral_currency_user['cu_id'], 'num' => $integral_currency_user['num']])->setInc('num', $integral);
                        if ($flag === false) throw new Exception("添加资产失败");
                    }
                }
            }

            if ($reward > 0) {//幸运池加权分红
                $profit_currency_id = RocketConfig::getValue('profit_currency_id');//赠与收益
                $currency_user = CurrencyUser::getCurrencyUser($member_id, $profit_currency_id);
                // 收益记录
                $member_level = RocketMember::where(['member_id' => $member_id])->value('level');
                $item_id = RocketRewardLog::addItem($currency_user['member_id'], $member_level, $reward, $profit, $money, $id, 4);

                //账本类型
                $account_book_id = 7104;
                $account_book_content = 'rocket_income_lucky';
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $reward, $item_id);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id']])->setInc('num', $reward);
                if ($flag === false) throw new Exception("添加资产失败");

                $flag = RocketGoodsList::where(['id' => $goods_list_id])->setInc('warehouse1', $reward);
                if ($flag === false) throw new Exception("添加资产失败");
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('本金返还失败：'.$member_id);
        }
        return $result;
    }

    /**
     * 获取静态收益
     * @param int $member_id 用户ID
     * @param int $goods_list_id 子商品ID
     * @param int $level 闯关数
     * @param int $id    订单id
     */
    static function get_statics_reward($member_id, $goods_list_id, $level, $profit, $id) {
        $result = 0;
        $res = self::where(['member_id' => $member_id, 'goods_list_id' => $goods_list_id, 'level_num' => $level, 'status' => 0, 'id' => $id])->find();
        if (!$res) return $result;

        $money = $res['money'];
        $result = sprintf('%.6f', $res['money'] * ($profit / 100));
        if ($result <= 0) return $result;

        $currency_id = RocketConfig::getValue('profit_currency_id');
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) return $result;
        Db::startTrans();
        try {
            // 收益记录
            $member_level = RocketMember::where(['member_id' => $member_id])->value('level');
            $item_id = RocketRewardLog::addItem($currency_user['member_id'], $member_level, $result, $profit, $money, $id, 1);

            //账本类型
            $account_book_id = 7109;
            $account_book_content = 'rocket_income_solidity';
            if ($result > 0) {
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $result, $item_id);
                if (!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $result);
                if (!$flag) throw new Exception("添加资产失败");

                $flag = RocketMember::where(['member_id' => $member_id])->setInc('share_reward', $result);
                if ($flag === false) throw new Exception("更新累计静态收益失败");
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('静态收益失败：'.$member_id);
        }
        return $result;
    }

    /**
     * 获取分享收益
     * @param int $member_id 用户ID
     * @param int $goods_list_id 子商品ID
     * @param int $level 闯关数
     */
    static function get_share_reward($member_id, $goods_list_id, $level) {
        $result = 0;
        $valid_num = RocketMember::where(['member_id' => $member_id])->value('valid_num');
        $share_config = RocketShareConfig::where(['valid_num' => ['elt', $valid_num]])->order('id DESC')->find();
        if ($share_config) {
            $first_reward = $second_reward = $third_reward = 0;
            $member_level = RocketMember::where(['member_id' => $member_id])->value('level');
            if ($share_config['first'] > 0) {//第一代投入金额的收益
                $first_total_num = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')
                    ->where(['a.goods_list_id' => $goods_list_id, 'a.level_num' => $level, 'b.member_id' => $member_id, 'b.level' => 1])->sum('money');
                $first_reward = sprintf('%.2f', $first_total_num * ($share_config['first'] / 100));
                // 收益记录
                if ($first_reward > 0) {
                    RocketRewardLog::addItem($member_id, $member_level, $first_reward, $share_config['first'], $first_total_num, 0, 2, 1);
                }
            }
            if ($share_config['second'] > 0) {//第二代投入金额的收益
                $second_total_num = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')
                    ->where(['a.goods_list_id' => $goods_list_id, 'a.level_num' => $level, 'b.member_id' => $member_id, 'b.level' => 2])->sum('money');
                $second_reward = sprintf('%.2f', $second_total_num * ($share_config['second'] / 100));
                // 收益记录
                if ($second_reward > 0) {
                    RocketRewardLog::addItem($member_id, $member_level, $second_reward, $share_config['second'], $second_total_num, 0, 2, 2);
                }
            }
            if ($share_config['third'] > 0) {//第三代投入金额的收益
                $third_total_num = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')
                    ->where(['a.goods_list_id' => $goods_list_id, 'a.level_num' => $level, 'b.member_id' => $member_id, 'b.level' => 3])->sum('money');
                $third_reward = sprintf('%.2f', $third_total_num * ($share_config['third'] / 100));
                // 收益记录
                if ($third_reward > 0) {
                    RocketRewardLog::addItem($member_id, $member_level, $third_reward, $share_config['third'], $third_total_num, 0, 2, 3);
                }
            }
            $result = sprintf('%.2f', $first_reward + $second_reward + $third_reward);
        }
        Db::startTrans();
        try {
            $currency_id = RocketConfig::getValue('reward_currency_id');
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
            if (empty($currency_user)) return $result;
            //账本类型
            $account_book_id = 7102;
            $account_book_content = 'rocket_income_share';
            if ($result > 0) {
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $result, 1);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $result);
                if ($flag === false) throw new Exception("添加资产失败");

                $flag = RocketMember::where(['member_id' => $member_id])->setInc('share_reward', $result);
                if ($flag === false) throw new Exception("更新累计分享收益失败");
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('分享收益失败：'.$member_id);
        }
        return $result;
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

        $currency_id = RocketConfig::getValue('profit_currency_id');//收益账户
        $subscribe_id = RocketConfig::getValue('subscribe_currency_id');//预约池账户
        Db::startTrans();
        try {
            $first = RocketLevel::order('level DESC')->value('profit');//最大级差
            $second = 0;//级别
            $third = 0;//已存在的级差
            $equality = 0;//是否已领平级奖励
            $num = 0;//平级奖励
            foreach ($res as $key => $value) {
                //判断用户存在参与未结算的闯关且金额大于等于100或者便捷账户金额小于500
                $is_check = Db::name('rocket_summary')->where(['member_id' => $value['member_id']])->order('id desc')->value('is_reward');
                $subscribe = CurrencyUser::getCurrencyUser($value['member_id'], $subscribe_id);
                if ($is_check != 1) {
                    continue;
                }

                $level_config = RocketLevel::where(['level' => $value['level']])->find();
                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
                $max_level = RocketLevel::where(['equality' => ['gt', 0]])->value('level');
                if (empty($currency_user)){
                    Db::rollback();
                    return false;
                }
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
                $account_book_id = 7122;
                $account_book_content = 'rocket_income_team2';
                if (!empty($income)) {
                    // 收益记录
                    $item_id = RocketRewardLog::addItem($currency_user['member_id'], $value['level'], $income, $rate, $money, $order_id, 3);
                    if ($item_id) {
                        $flag = RocketRewardLog::where(['id' => $item_id])->update(['currency_id' => $currency_id]);
                        if ($flag === false) throw new Exception("添加收益记录失败");
                    }

                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
                    if ($flag === false) throw new Exception("添加资产失败");

                    $flag = RocketMember::where(['member_id' => $value['member_id']])->setInc('n_auto_reward', $income);
                    if ($flag === false) throw new Exception("更新累计团队收益失败");
                }
            }
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            Log::write('团队收益失败：订单' . $order_id);
        }

        return true;
    }

    /**
     * 团队收益
     * @param int $member_id 用户ID
     * @param int $type 类型 1全部 2待结算 3已清算 4已清退
     * @param int $page      页
     * @param int $rows      页数
     */
    static function buy_detail($member_id, $type, $page, $rows=15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null, 'total_num' => 0, 'integral_num' => 0];

        if (empty($type)) $type = 1;
        $where = [
            'a.member_id' => $member_id,
        ];
        if ($type == 2) {
            $where['a.status'] = 0;//0待开启
        } elseif ($type == 3) {
            $where['a.status'] = 1;//1成功
        } elseif ($type == 4) {
            $where['a.status'] = 2;//2失败
        }
        $list = self::alias('a')->where($where)
            ->join('rocket_goods_list b', 'a.goods_list_id=b.id')
            ->field('a.id,b.name,a.level_num,a.money,a.add_time,a.capital,a.integral,a.statics_reward,a.settlement_time,a.status,b.profit,a.is_auto,b.goods_id,a.type,a.goods_list_id')
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$list) return $r;
        $time_num = RocketConfig::getValue('cancel_time_num');
        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            if ($value['settlement_time']) {
                $value['settlement_time'] = date('Y-m-d H:i:s', $value['settlement_time']);
            }
            if ($value['level_num'] == 1) {
                $value['level_name'] = '点火';
            } else {
                $value['level_name'] = '推进'.$value['level_num'];
            }
            $value['estimate_statics_reward'] = 0;
            if ($value['profit']) {
                $value['estimate_statics_reward'] = keepPoint($value['money'] * ($value['profit'] / 100));
            }
            $value['auto_status'] = 1;//按钮状态 1自动中 2已取消
            if ($value['is_auto'] == 3) {
                $value['auto_status'] = 2;
            }
            $value['show_status'] = 0;//0置灰 1亮
            $level_num = self::getSettlementLevel($value['level_num']);
            $goods_info = RocketGoodsList::where(['goods_id' => $value['goods_id'], 'level' => keepPoint($value['level_num'] + $level_num - 1, 0), 'id' => ['gt', $value['goods_list_id']]])->find();
            if ($goods_info && $value['auto_status'] == 1) {
                $check_time = keepPoint($goods_info['start_time'] - $time_num, 0);
                if ($check_time <= time() && $goods_info['start_time'] > time()) {
                    $value['show_status'] = 1;
                }
            }

            $value['type_name'] = self::STATUS_ENUM[$value['type']];
            $value['auto_name'] = '自动推进' . keepPoint($value['level_num'] + $level_num,0);
        }
        $integral_currency_id = RocketConfig::getValue('integral_currency_id');
        $CurrencyUser = new CurrencyUser();
        $integral_num = $CurrencyUser->getNum($member_id, $integral_currency_id);
        $total_num = self::where(['member_id' => $member_id])->sum('statics_reward');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['total_num'] = sprintf('%.6f', $total_num);
        $r['integral_num'] = sprintf('%.6f', $integral_num);//积分
        return $r;
    }

    /**
     * 获取订单记录
     * @param int $member_id 用户ID
     * @param int $goods_id  主闯关id
     */
    static function get_order_index($member_id, $goods_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        if (!$goods_id) return $r;

        $where = [];
        $special_user_id = RocketConfig::getValue('special_user_id');
        if ($special_user_id) {//判断是否特殊账号
            $special_user_id = explode(',', $special_user_id);
            $where['a.member_id'] = ['not in', $special_user_id];
        }
        $limit = 10;
        $list = self::alias('a')->where(['c.goods_id' => $goods_id, 'is_show' => 1])->where($where)
            ->join('member b', 'a.member_id=b.member_id')
            ->join('rocket_goods_list c', 'a.goods_list_id=c.id')
            ->field('a.id,b.ename,a.add_time,a.money')
            ->limit($limit)
            ->order("a.id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $ename = mb_substr($value['ename'], 0, 2) . '**' . mb_substr($value['ename'], 4);
            $value['name'] = $ename . '推进' . round($value['money']) . '火米';
            $value['add_time'] = date('m-d H:i', $value['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取订单记录
     * @param int $member_id 用户ID
     * @param int $goods_id  主闯关id
     * @param int $page      页
     * @param int $rows      页数
     */
    static function get_order_list($member_id, $goods_id, $page, $rows=15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $list_ids = RocketGoodsList::where(['goods_id' => $goods_id, 'is_show' => 1])->order('id desc')->limit(2)->column('id');
        $where = [];
        $special_user_id = RocketConfig::getValue('special_user_id');
        if ($special_user_id) {//判断是否特殊账号
            $special_user_id = explode(',', $special_user_id);
            $where['a.member_id'] = ['not in', $special_user_id];
        }
        $list = self::alias('a')->where(['c.goods_id' => $goods_id])->whereIn('c.id', $list_ids)->where($where)
            ->join('member b', 'a.member_id=b.member_id')
            ->join('rocket_goods_list c', 'a.goods_list_id=c.id')
            ->field('a.id,b.ename,a.add_time,a.money,a.level_num')
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            $ename = mb_substr($value['ename'], 0, 2) . '**' . mb_substr($value['ename'], 4);
            $value['ename'] = $ename;
            $value['money'] = '推进' . round($value['money']) . '火米';
            $value['add_time'] = date('m-d H:i', $value['add_time']);
            if ($value['level_num'] == 1) {
                $value['title'] = '点火';
            } else {
                $value['title'] = '推进' . $value['level_num'] . '期预购';
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 创建订单
     * @param int $member_id    用户ID
     * @param int $product_id  子闯关ID
     * @param number $num      支付金额
     * @param number $kmt_num  kmt燃料
     * @param array $item      队列数据
     */
    static function create_order($member_id, $product_id, $num, $kmt_num, $item) {
        $goods_info = RocketGoodsList::where(['id' => $product_id, 'status' => 1])->find();
        if (!$goods_info) return true;
        $is_order = self::where(['member_id' => $member_id, 'goods_list_id' => $product_id, 'type' => $item['type']])->find();
        if ($is_order) {
            $flag = RocketBuyList::where(['id' => $item['id']])->update(['status' => 99]);
            if ($flag === false) Log::write('用户多记录更新队列失败：' . $member_id);
            return true;
        }
        $surplus_num = 0;//剩余金额
        $surplus_kmt_num = 0;//剩余mtk燃料
        //购买金额不够，取剩余金额
        if (sprintf('%.2f', $num + $goods_info['finish_money']) > sprintf('%.2f', $goods_info['price'])) {
            $num = sprintf('%.2f', $goods_info['price'] - $goods_info['finish_money']);
            if ($num > 0) {
                $usdt_num = sprintf('%.4f', $num * ($goods_info['kmt_rate'] / 100));
                $kmt_num = $usdt_num;
                $price = Db::name('mtk_currency_price')->order('id desc')->value('price');
                if ($price > 0) {
                    //实际扣除 USDT / 每日MTK
                    $price = sprintf('%.4f', $price);
                    $kmt_num = sprintf('%.4f', $usdt_num / $price);
                }
            } else {
                $kmt_num = 0;
            }
            $surplus_num = sprintf('%.2f', $item['num'] - $num);
            $surplus_kmt_num = sprintf('%.4f', $item['kmt_num'] - $kmt_num);
        }
        if ($item['type'] == 2 || $item['type'] == 3 || $item['type'] == 4) {//预约排队
            $subscribe_currency_id = RocketConfig::getValue('subscribe_currency_id');
            // 获取资产
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
            if (empty($currency_user)) {
                return true;
            }
            $kmt_num = 0;
            $surplus_kmt_num = 0;
        } else {//手动参与
            // 获取资产
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $goods_info['currency_id']);
            if (empty($currency_user)) {
                return true;
            }
        }

        //平台币账户
        $platform_currency_id = RocketConfig::getValue('platform_currency_id');
        $platform_currency_user = CurrencyUser::getCurrencyUser($member_id, $platform_currency_id);
        if (empty($platform_currency_user)) {
            return true;
        }

        try {
            Db::startTrans();
            if ($num > 0) {
                $data = [
                    'member_id' => $member_id,
                    'goods_list_id' => $product_id,
                    'order_code' => self::GetRandOrderSn(),
                    'money' => $num,
                    'kmt_num' => $kmt_num,
                    'level_num' => $goods_info['level'],
                    'add_time' => time(),
                    'type' => $item['type'],
                    'is_auto' => 3//取消复利
                ];

                $result = self::insertGetId($data);
                // 扣除资产
                if ($num > 0) {
                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'forzen_num' => $currency_user['forzen_num']])
                        ->setDec('forzen_num', $num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
                // 扣除平台币资产
                if ($kmt_num > 0) {
                    $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id'], 'forzen_num' => $platform_currency_user['forzen_num']])->setDec('forzen_num', $kmt_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = RocketKmtLog::addItem($platform_currency_user['member_id'], $product_id, $kmt_num);
                    if (!$flag) throw new Exception('生成燃料记录失败');
                }

                //更新闯关完成金额
                $flag = RocketGoodsList::where(['id' => $product_id])->setInc('finish_money', $num);
                if ($flag === false) throw new Exception('参与金额不能大于闯关金额');
                //更新用户汇总
                $flag = RocketMember::addItem($member_id, $num);
                if ($flag === false) throw new Exception('更新用户汇总失败');
                //更新队列信息
                $flag = RocketBuyList::where(['id' => $item['id']])->update(['status' => 1, 'actual_num' => $num, 'actual_kmt' => $kmt_num]);
                if ($flag === false) throw new Exception('更新队列信息失败');
            } else {
                //更新队列状态
                $flag = RocketBuyList::where(['id' => $item['id']])->update(['status' =>2]);
                if ($flag === false) throw new Exception('更新队列状态失败');
            }

            //解除冻结 返还资产
            if ($surplus_num > 0) {//返还剩余
                if (sprintf('%.2f', $item['num']) == sprintf('%.2f', $surplus_num)) {//排队失败
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7110, 'rocket_subscribe_fail', 'in', $surplus_num, 1);
                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                } else {//部分返还
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7111, 'rocket_subscribe_part', 'in', $surplus_num, 1);
                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                }

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id']])->setDec('forzen_num', $surplus_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id']])->setInc('num', $surplus_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            }
            if ($surplus_kmt_num > 0) {//返还剩余
                if (sprintf('%.2f', $item['kmt_num']) == sprintf('%.2f', $surplus_kmt_num)) {//排队失败
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($platform_currency_user['member_id'], $platform_currency_user['currency_id'], 7110, 'rocket_subscribe_fail', 'in', $surplus_kmt_num, 1);
                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                } else {//部分返还
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($platform_currency_user['member_id'], $platform_currency_user['currency_id'], 7111, 'rocket_subscribe_part', 'in', $surplus_kmt_num, 1);
                    if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
                }

                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id']])->setDec('forzen_num', $surplus_kmt_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id']])->setInc('num', $surplus_kmt_num);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $result;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
            $r['code'] = ERROR1;
            $r['result'] = null;
        }

        return $r;
    }

    /**
     * 取消复利
     * @param int $member_id    用户ID
     * @param int $order_id     订单ID
     */
    static function sub_cancel($member_id, $order_id) {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_failure'), 'result' => null];
        $res = self::where(['member_id' => $member_id, 'id' => $order_id])->find();
        if (!$res) return $r;
        if ($res['status'] > 0) {
            $r['message'] = '该订单已复利';
            return $r;
        }

        $goods_id = RocketGoodsList::where(['id' => $res['goods_list_id']])->value('goods_id');
        $level_num = self::getSettlementLevel($res['level_num']) - 1;
        $level_num = $res['level_num'] + $level_num;
        $goods_info = RocketGoodsList::where(['goods_id' => $goods_id, 'level' => $level_num, 'id' => ['gt', $res['goods_list_id']]])->find();
        $time_num = RocketConfig::getValue('cancel_time_num');
        if ($goods_info) {
            $check_time = keepPoint($goods_info['start_time'] - $time_num, 0);
            if ($check_time <= time() && $goods_info['start_time'] > time()) {
                $flag = self::where(['member_id' => $member_id, 'id' => $order_id])->update(['is_auto' => 3]);
                if ($flag === false) return $r;

                $r['code'] = SUCCESS;
                $r['message'] = lang('lan_operation_success');
                $r['result'] = $order_id;
            }
        }

        return $r;
    }
}