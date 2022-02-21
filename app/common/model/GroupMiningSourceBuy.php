<?php
namespace app\common\model;
use think\Db;
use think\Exception;
use think\Model;

class GroupMiningSourceBuy extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function MoneyCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'money_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function MoneyCurrency1() {
        return $this->belongsTo('app\\common\\model\\Currency', 'money_currency_id1', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename');
    }

    //申购矿源初始化
    static function buy_init($user_id, $level_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $level_id<=0) return $r;
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if (empty($levelInfo)) return $r;

        $money = $levelInfo['price'];
        $currencyId = $levelInfo['price_currency_id'];
        $currency = Currency::get($currencyId);
        $currencyUser = CurrencyUser::getCurrencyUser($user_id,$currencyId);
        //单币种(USDT)支付
        $list[] = [
            'money_type'=>1,
            'money_num'=>floattostr($currencyUser['num']),
            'money_num_currency_name'=>$currency['currency_name'],
            'money'=>floattostr($money),
            'money_currency_name'=>$currency['currency_name'],
            'money1'=>0,
            'money_currency_name1'=>'',
        ];
        $result = [
            //'price'=>floattostr($money),
            //'price_currency_name'=>$currency['currency_name'],
            'money_num'=>floattostr($currencyUser['num']),
            'money_num_currency_name'=>$currency['currency_name'],
        ];
        //2-组合支付
        //改为优先币币交易价格 获取不到则获取固定价格
        $moneyType2Switch = GroupMiningConfig::get_value('group_mining_money_type2_switch', 0);
        $result['money_type2_switch'] = $moneyType2Switch;
        if ($moneyType2Switch) {
            $moneyType2Percent = GroupMiningConfig::get_value('group_mining_money_type2_percent', 0);
            $currencyId1 = GroupMiningConfig::get_value('group_mining_money_type2_currency_id', 0);
            $currency1 = Currency::get($currencyId1);
            $currencyUser1 = CurrencyUser::getCurrencyUser($user_id,$currencyId1);
            $result['money_num1'] = floattostr($currencyUser1['num']);
            $result['money_num_currency_name1'] = $currency1['currency_name'];
            $currencyPrice1 = CurrencyPriceTemp::BBFirst_currency_price($currencyId1);
            $result['currency_price1'] = $currencyPrice1;
            if ($moneyType2Percent > 0) {
                $money1 = keepPoint($money * ($moneyType2Percent / 100), 6);
                $money = keepPoint($money - $money1, 6);
                $money1 = keepPoint($money1 / $currencyPrice1, 6);
                $result['money_type2_num1'] = floattostr($money);
                $result['money_type2_num1_currency_id'] = $currency['currency_id'];
                $result['money_type2_num1_currency_name'] = $currency['currency_name'];
                $result['money_type2_num2'] = floattostr($money1);
                $result['money_type2_num2_currency_id'] = $currency1['currency_id'];
                $result['money_type2_num2_currency_name'] = $currency1['currency_name'];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    //申购矿源
    static function buy($user_id, $level_id, $money_type = 1, $currency_id = 0, $num = 0) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $level_id<=0 || $money_type<=0) return $r;
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if (empty($levelInfo)) return $r;

        $userInfo = GroupMiningUser::getUserInfo($user_id, true);
        $startTime = strtotime(GroupMiningConfig::get_value('group_mining_start_time', '2020-11-11 12:20:00'));
        $startTime = strtotime(date('Y-m-d 00:00:00', $startTime));
        $currencyId1 = 0;
        $moneyType2Percent = 0;
        $currencyPrice1 = 0;
        $money = $levelInfo['price'];
        $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);
        //改为优先币币交易价格 获取不到则获取固定价格
        $currencyPrice = CurrencyPriceTemp::BBFirst_currency_price($currencyId);
        $money1 = 0;
        $moneyPool1 = 0;
        if ($money_type == 2) {//2-组合支付
            $moneyType2Switch = GroupMiningConfig::get_value('group_mining_money_type2_switch', 0);
            $moneyType2Percent = GroupMiningConfig::get_value('group_mining_money_type2_percent', 0);
            $currencyId1 = GroupMiningConfig::get_value('group_mining_money_type2_currency_id', 0);
            if (!$moneyType2Switch || !$currencyId1) return $r;
            $currencyPrice1 = CurrencyPriceTemp::BBFirst_currency_price($currencyId1);
            if ($moneyType2Percent > 0) {
                $money1 = keepPoint($money * ($moneyType2Percent / 100), 6);
                $money = keepPoint($money - $money1, 6);
                $money1 = keepPoint($money1 / $currencyPrice1, 6);
            }
            if ($currency_id > 0) {
                if ($currency_id == $currencyId1) {
                    //if ($money1 < $num) {
                    if (bccomp($money1, $num, 6) == -1) {
                        $r['message'] = lang('传入数量不符合规则');
                        return $r;
                    }
                    if (bccomp($num, $money1, 6) != 0) {
                        $money1 = keepPoint($num * $currencyPrice1, 6);
                        $money = keepPoint($levelInfo['price'] - $money1, 6);
                        $money1 = $num;
                    }
                }
                else {
                    //if ($money > $num) {
                    if (bccomp($money, $num, 6) == 1) {
                        $r['message'] = lang('传入数量不符合规则');
                        return $r;
                    }
                    if (bccomp($num, $money, 6) != 0) {
                        $money = $num;
                        $money1 = keepPoint($levelInfo['price'] - $money, 6);
                        $money1 = keepPoint($money1 / $currencyPrice1, 6);
                    }
                }
            }
            if ($currencyId == $currencyId1) {
                $moneyPool1 = $money1;
            }
            else {
                //$moneyPool1 = keepPoint(($money * ($moneyType2Percent / 100)) / $currencyPrice, 6);
                $moneyPool1 = keepPoint($money1 * $currencyPrice1 / $currencyPrice, 6);
            }
        }
        if(empty($startTime)) return $r;

        $now = time();
        $nowTime = strtotime(date('Y-m-d 00:00:00', $now));
        //获取最近一次申购的矿源，用于新矿源的开启时间判断
        $source = self::where([
            'user_id' => $user_id,
            'type' => $levelInfo['type'] == 2 ? 2 : ['in', [1,3]],
        ])->order('add_time', 'DESC')->find();
        if(!empty($mining)) {
            $startTime = max($startTime, $nowTime);
        }
        else {
            $startTime = max($startTime, $nowTime, $source['end_time']);
        }

        //实际扣除数量
        $currency_user = CurrencyUser::getCurrencyUser($user_id,$levelInfo['price_currency_id']);
        if ($money1 > 0) {//2-组合支付
            $currency_user1 = CurrencyUser::getCurrencyUser($user_id,$currencyId1);
            if(!$currency_user1 || $currency_user1['num'] < $money1) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        }
        if(!$currency_user || $currency_user['num'] < $money) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $userUpdate = [];
        if ($userInfo['level_id'] < $level_id) {
            $userUpdate['level_id'] = $level_id;
            $buyMaxLevelId = self::where('user_id', $user_id)->max('level_id') ? : 0;
            if ($userUpdate['level_id'] < $buyMaxLevelId) {
                $userUpdate['level_id'] = $buyMaxLevelId;
            }
        }
        //联创红利
        if ($userInfo['dividend_auth_status'] != 1) {
            $dividendAuthSwitch = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_switch', 0);//拼团挖矿联创红利奖励资格获取开关 1-开启 0-关闭
            if ($dividendAuthSwitch == 1) {
                $dividendAuthEnd = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_end', '');//拼团挖矿联创红利奖励资格获取结束时间 格式：Y-m-d H:i:s
                if (!empty($dividendAuthEnd)) {
                    if ($now < strtotime($dividendAuthEnd)) {//联创红利奖励资格获取未结束
                        $dividendAuthLevel = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_level', 0);//拼团挖矿联创红利奖励资格需申购的矿源等级
                        if ($dividendAuthLevel == $level_id) {
                            $dividendAuthUser = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_user', 0);//拼团挖矿联创红利奖励资格获取用户数
                            $dividendAuthUser1 = GroupMiningUser::where(['dividend_auth_status'=>['gt', 0]])->count('id');
                            if ($dividendAuthUser1 < $dividendAuthUser) {
                                $dividendAuthDay = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_day', 0);//拼团挖矿联创红利奖励资格获取天数
                                $dividendRewardStart = GroupMiningConfig::get_value('group_mining_dividend_reward_start_time', '');//拼团挖矿联创红利奖励资格开始时间 格式：Y-m-d H:i:s
                                $authStartTime = strtotime(date('Y-m-d 00:00:00', $now)) + 86400;
                                if (!empty($dividendRewardStart)) $authStartTime = max($authStartTime, strtotime($dividendRewardStart));
                                $userUpdate['dividend_auth_status'] = 1;
                                $userUpdate['dividend_start_time'] = $authStartTime;
                                $userUpdate['dividend_end_time'] = $authStartTime + $dividendAuthDay * 86400;
                            }
                        }
                    }
                }
            }
        }

        try{
            self::startTrans();

            //添加申购记录
            $data = [
                'user_id' => $user_id,
                'level_id' => $level_id,
                'type' => $levelInfo['type'],
                'price' => $levelInfo['price'],
                'price_currency_id' => $levelInfo['price_currency_id'],
                'money_type' => $money_type,
                //'money_percent' => (100 - $moneyType2Percent),
                //'money' => $money,
                //'money_currency_id' => $levelInfo['price_currency_id'],
                //'money_percent1' => $moneyType2Percent,
                //'money1' => $money1,
                //'money_currency_id1' => $currencyId1,
                //'money_currency_price1' => $currencyPrice1,
                'days' => $levelInfo['days'],
                'daily_num' => $levelInfo['daily_num'],
                //'total_num' => $levelInfo['days'] * $levelInfo['daily_num'],
                'win_percent' => $levelInfo['win_percent'],
                'cur_round_num' => 1,
                'reward_num_min' => $levelInfo['reward_num_min'],
                'reward_num_max' => $levelInfo['reward_num_max'],
                'free_rate_min' => $levelInfo['free_rate_min'],
                'free_rate_max' => $levelInfo['free_rate_max'],
                'start_time' => $startTime,
                'end_time' => $startTime + $levelInfo['days'] * 86400,
                'add_time' => $now,
            ];
            if($money > 0) {
                $data['money_percent'] = (100 - $moneyType2Percent);
                $data['money'] = $money;
                $data['money_currency_id'] = $levelInfo['price_currency_id'];
            }
            if($money1 > 0) {
                $data['money_percent1'] = $moneyType2Percent;
                $data['money1'] = $money1;
                $data['money_currency_id1'] = $currencyId1;
                $data['money_currency_price1'] = $currencyPrice1;
            }
            if ($levelInfo['type'] == 2) {//2-体验
                $data['status'] = 2;//2-已开启
                //$data['start_time'] = $nowTime;
                //$data['end_time'] = $nowTime + 86400;
            }
            else {
                if ($startTime <= $now) {
                    $data['cur_round_num'] = 1;
                    $data['status'] = 2;//2-已开启
                }
            }
            $item_id = self::insertGetId($data);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            if($money > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($user_id,$levelInfo['price_currency_id'],6100,'group_mining_buy','out',$money,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$money);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $moneyPool = keepPoint($money / $currencyPrice, 6);

                //减去来源矿源矿池
                $bflRes = BflPool::fromToTask(BflPool::MINERAL,BflPool::HOLE,$moneyPool,'GroupMiningSourceBuy',$item_id);
                if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);
            }

            if($money1 > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($user_id,$currencyId1,6100,'group_mining_buy','out',$money1,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user1['cu_id'],'num'=>$currency_user1['num']])->setDec('num',$money1);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //减去流动矿池
                $bflRes = BflPool::fromToTask(BflPool::MINERAL,BflPool::HOLE,$moneyPool1,'GroupMiningSourceBuyMoney1',$item_id);
                if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);
            }

            //更新用户数据
            if (!empty($userUpdate)) {
                $flag = GroupMiningUser::where('id', $userInfo['id'])->update($userUpdate);
                if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //转赠矿源
    static function give($user_id, $buy_id, $target_account) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $buy_id<=0 || empty($target_account)) return $r;
        $now = time();
        $buyInfo = GroupMiningSourceBuy::getBuyInfo($user_id, $buy_id);
        if (empty($buyInfo)) return $r;
        $level_id = $buyInfo['level_id'];
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if (empty($levelInfo)) return $r;

        if ($buyInfo['status'] != 1) {
            $r['message'] = lang('只有未开启矿源才能转赠，请刷新重试');return $r;
        }

        $target = Member::where(['ename'=>$target_account])->find();
        if (empty($target)) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }
        $targetUserId = $target['member_id'];
        if ($targetUserId == $user_id) return $r;
        $feeCurrencyId = GroupMiningConfig::get_value('group_mining_source_give_fee_currency_id', 66);
        $feeNum = GroupMiningConfig::get_value('group_mining_source_give_fee_num', 1);
        $currency_user = CurrencyUser::getCurrencyUser($user_id, $feeCurrencyId);
        if(!$currency_user || $currency_user['num'] < $feeNum) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        /*if ( !$target || ( $target['phone'] != $target_account_verify and $target['email'] != $target_account_verify) ) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('lan_account_member_not_exist'),
                'result' => null
            ]);
        }*/

        $userInfo = GroupMiningUser::getUserInfo($user_id, true);
        $targetUserInfo = GroupMiningUser::getUserInfo($targetUserId, true);
        $startTime = strtotime(GroupMiningConfig::get_value('group_mining_start_time', '2020-11-11 12:20:00'));
        $startTime = strtotime(date('Y-m-d 00:00:00', $startTime));
        if(empty($startTime)) return $r;

        $nowTime = strtotime(date('Y-m-d 00:00:00', $now));
        //获取最近一次申购的矿源，用于新矿源的开启时间判断
        $source = self::where([
            'user_id' => $targetUserId,
            'type' => $levelInfo['type'] == 2 ? 2 : ['in', [1,3]],
        ])->order('add_time', 'DESC')->find();
        if(!empty($mining)) {
            $startTime = max($startTime, $nowTime);
        }
        else {
            $startTime = max($startTime, $nowTime, $source['end_time']);
        }

        $userUpdate = [];
        $targetUserUpdate = [];
        if ($targetUserInfo['level_id'] < $level_id) {
            $targetUserUpdate['level_id'] = $level_id;
            $buyMaxLevelId = self::where('user_id', $targetUserId)->max('level_id') ? : 0;
            if ($targetUserUpdate['level_id'] < $buyMaxLevelId) {
                $targetUserUpdate['level_id'] = $buyMaxLevelId;
            }
        }
        //联创红利
        //目标用户
        if ($targetUserInfo['dividend_auth_status'] != 1) {
            $dividendAuthSwitch = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_switch', 0);//拼团挖矿联创红利奖励资格获取开关 1-开启 0-关闭
            if ($dividendAuthSwitch == 1) {
                $dividendAuthEnd = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_end', '');//拼团挖矿联创红利奖励资格获取结束时间 格式：Y-m-d H:i:s
                if (!empty($dividendAuthEnd)) {
                    if ($now < strtotime($dividendAuthEnd)) {//联创红利奖励资格获取未结束
                        $dividendAuthLevel = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_level', 0);//拼团挖矿联创红利奖励资格需申购的矿源等级
                        if ($dividendAuthLevel == $level_id) {
                            $dividendAuthUser = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_user', 0);//拼团挖矿联创红利奖励资格获取用户数
                            $dividendAuthUser1 = GroupMiningUser::where(['dividend_auth_status'=>['gt', 0]])->count('id');
                            if ($dividendAuthUser1 < $dividendAuthUser) {
                                $dividendAuthDay = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_day', 0);//拼团挖矿联创红利奖励资格获取天数
                                $dividendRewardStart = GroupMiningConfig::get_value('group_mining_dividend_reward_start_time', '');//拼团挖矿联创红利奖励资格开始时间 格式：Y-m-d H:i:s
                                $authStartTime = strtotime(date('Y-m-d 00:00:00', $now)) + 86400;
                                if (!empty($dividendRewardStart)) $authStartTime = max($authStartTime, strtotime($dividendRewardStart));
                                $targetUserUpdate['dividend_auth_status'] = 1;
                                $targetUserUpdate['dividend_start_time'] = $authStartTime;
                                $targetUserUpdate['dividend_end_time'] = $authStartTime + $dividendAuthDay * 86400;
                            }
                        }
                    }
                }
            }
        }
        //源用户
        if ($userInfo['dividend_auth_status'] == 1) {//源用户具有联创红利奖励资格
            $dividendAuthLevel = GroupMiningConfig::get_value('group_mining_dividend_reward_auth_level', 0);//拼团挖矿联创红利奖励资格需申购的矿源等级
            if ($dividendAuthLevel == $level_id) {
                $levelCount = self::where(['user_id'=>$user_id,'level_id'=>$dividendAuthLevel,'status'=>['in',[1,2,3]]])->count('id') ? : 0;
                if ($levelCount < 2) {//转赠之后剩余资格等级矿源数量<1，清除用户奖励资格
                    $userUpdate['dividend_auth_status'] = 3;//联创红利奖励资格状态 0-无资格 1-有资格 2-已过期 3-已失效(转赠)
                }
            }
        }

        try{
            self::startTrans();

            //添加申购记录
            $data = [
                'user_id' => $targetUserId,
                'level_id' => $level_id,
                'type' => 3,//类型 1-申购 2-体验 3-他人赠送
                'price' => $levelInfo['price'],
                'price_currency_id' => $levelInfo['price_currency_id'],
                'money_type' => 1,
                //'money_percent' => (100 - $moneyType2Percent),
                //'money' => $money,
                //'money_currency_id' => $levelInfo['price_currency_id'],
                //'money_percent1' => $moneyType2Percent,
                //'money1' => $money1,
                //'money_currency_id1' => $currencyId1,
                //'money_currency_price1' => $currencyPrice1,
                'days' => $levelInfo['days'],
                'daily_num' => $levelInfo['daily_num'],
                //'total_num' => $levelInfo['days'] * $levelInfo['daily_num'],
                'win_percent' => $levelInfo['win_percent'],
                'cur_round_num' => 1,
                'reward_num_min' => $levelInfo['reward_num_min'],
                'reward_num_max' => $levelInfo['reward_num_max'],
                'free_rate_min' => $levelInfo['free_rate_min'],
                'free_rate_max' => $levelInfo['free_rate_max'],
                'start_time' => $startTime,
                'end_time' => $startTime + $levelInfo['days'] * 86400,
                'add_time' => $now,
                'give_from_user_id' => $user_id,
                'give_from_buy_id' => $buy_id,
            ];
            if ($startTime <= $now) {
                $data['cur_round_num'] = 1;
                $data['status'] = 2;//2-已开启
            }
            $item_id = self::insertGetId($data);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            $log_id = GroupMiningSourceGiveLog::insertGetId([
                'user_id' => $user_id,
                'level_id' => $level_id,
                'buy_id' => $buy_id,
                'to_account' => $target_account,
                'to_user_id' => $targetUserId,
                'to_buy_id' => $item_id,
                'fee_currency_id' => $feeCurrencyId,
                'fee_num' => $feeNum,
                'add_time' => $now,
            ]);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            if($feeNum > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($user_id,$feeCurrencyId,6115,'group_mining_give_fee','out',$feeNum,$log_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$feeNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //更新源申购信息
            $flag = self::where('id', $buy_id)->update([
                'status'=>4,
                'give_to_user_id'=>$targetUserId,
                'give_to_buy_id'=>$item_id,
                'give_to_time'=>$now,
            ]);
            if($flag == false) throw new Exception(lang('operation_failed_try_again'));

            //更新用户数据
            //目标用户
            if (!empty($targetUserUpdate)) {
                $flag = GroupMiningUser::where('id', $targetUserInfo['id'])->update($targetUserUpdate);
                if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
            }
            //源用户
            if (!empty($userUpdate)) {
                $flag = GroupMiningUser::where('id', $userInfo['id'])->update($userUpdate);
                if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //获取体验次数
    static function getExperience($user_id) {
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $levelInfo = GroupMiningSourceLevel::where('type', 2)->find();
        if (!$levelInfo) return $res;
        $miningSwitch = GroupMiningConfig::get_value('group_mining_switch', 1);
        if (!$miningSwitch) {
            $res['message'] = lang('拼团挖矿已关闭');return $res;
        }
        $experienceSwitch = GroupMiningConfig::get_value('group_mining_experience_switch', 1);
        if (!$experienceSwitch) {
            $res['message'] = lang('体验挖矿已关闭');return $res;
        }
        $now = time();
        $experienceStartTime = strtotime(GroupMiningConfig::get_value('group_mining_experience_start_time', '2020-11-11 12:20:00'));
        if ($now < $experienceStartTime) {
            $res['message'] = lang('体验挖矿未开始');return $res;
        }
        $experienceEndTime = GroupMiningConfig::get_value('group_mining_experience_end_time', '');
        if ($experienceEndTime != '') {
            if ($now >= strtotime($experienceEndTime)) {
                $res['message'] = lang('体验挖矿已结束');return $res;
            }
        }
        $openBuy = self::getOpenBuy($user_id);
        if ($openBuy) {
            //类型 1-申购 2-体验
            if ($openBuy['type'] == 1) {
                $res['message'] = lang('您已申购矿源，无法体验挖矿');return $res;
            }
            else {
                $res['message'] = lang('今日已领取完');return $res;
            }
        }
        return self::buy($user_id,$levelInfo['level_id']);
    }

    //获取开启状态的申购
    static function getOpenBuy($user_id)
    {
        $now = time();
        $buy  = [];
        $find = self::where(['user_id'=>$user_id,'status'=>2])->order(['type'=>'ASC', 'start_time'=>'ASC'])->find();
        if ($find) {
            if ($find['end_time'] <= $now) {
                $flag = self::where('id', $find['id'])->setField('status', 3);
            }
            else {
                $buy = $find;
            }
        }
        if (!$buy) {
            $find = self::where(['user_id'=>$user_id,'status'=>1,'start_time'=>['elt',$now]])->order(['type'=>'ASC', 'start_time'=>'ASC'])->find();
            if ($find) {
                $flag = self::where('id', $find['id'])->setField('status', 2);
                $buy = self::where('id', $find['id'])->find();
            }
        }
        if ($buy) {
            $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
            if ($buy['last_mining_time'] < $todayStartTime && $buy['today_num'] > 0) {
                $flag = self::where('id', $buy['id'])->update([
                    'today_num'=>0,
                    'cur_mining_num'=>0,
                    'cur_win_num'=>0,
                    'cur_lose_num'=>0,
                    'cur_round_num'=>1,
                ]);
                $buy = self::where('id', $buy['id'])->find();
            }
            if ($buy['cur_round_num'] <= 0) {
                $miningNum = GroupMiningLog::where(['user_id' => $user_id,'buy_id' => $find['id'],'date' => date('Y-m-d', $now)])->count('id') ? : 0;
                $curRoundNum = intval($miningNum / 10) + 1;
                $flag = self::where('id', $find['id'])->setField('cur_round_num', $curRoundNum);
                $buy = self::where('id', $find['id'])->find();
            }
        }
        return $buy;
    }

    //获取申购信息
    static function getBuyInfo($user_id, $buy_id)
    {
        $buyInfo = self::where(['id'=>$buy_id,'user_id'=>$user_id])->find();
        if ($buyInfo) {
            $now = time();
            $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
            if ($buyInfo['last_mining_time'] < $todayStartTime && $buyInfo['today_num'] > 0) {
                $flag = self::where('id', $buyInfo['id'])->update([
                    'today_num'=>0,
                    'cur_mining_num'=>0,
                    'cur_win_num'=>0,
                    'cur_lose_num'=>0,
                    'cur_round_num'=>1,
                ]);
                $buyInfo = self::where('id', $buyInfo['id'])->find();
            }
            if ($buyInfo['cur_round_num'] <= 0) {
                $miningNum = GroupMiningLog::where(['user_id' => $user_id,'buy_id' => $buy_id,'date' => date('Y-m-d', $now)])->count('id') ? : 0;
                $curRoundNum = intval($miningNum / 10) + 1;
                $flag = self::where('id', $buyInfo['id'])->setField('cur_round_num', $curRoundNum);
                $buyInfo = self::where('id', $buyInfo['id'])->find();
            }
        }
        return $buyInfo;
    }

    //申购记录
    static function getList($member_id,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($member_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['a.user_id' => $member_id, 'a.type' => ['in', [1,3]], 'a.status' => ['lt', 4]];
            $field = "a.id,a.price,a.price_currency_id,a.days,a.status,a.daily_num,a.today_num,a.total_num,a.add_time,a.start_time,a.end_time,b.currency_name as price_currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.price_currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order(Db::raw("a.status=3 asc,a.id asc"))->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['currency_name'] = 'ABF'; //暂时写死
                    $value['add_time'] = date('m-d H:i', $value['add_time']);
                    $value['start_time'] = date('m-d H:i', $value['start_time']);
                    $value['end_time'] = date('m-d H:i', $value['end_time']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }
}
