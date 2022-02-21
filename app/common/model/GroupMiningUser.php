<?php
namespace app\common\model;
use PDOStatement;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class GroupMiningUser extends Model
{
    public function user() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    // 矿源等级
    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }

    /**
     * 获取用户信息
     * @param int $userId
     * @param bool $isInit 是否初始化
     * @return UserAirLevel|array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getUserInfo($userId, $isInit = false)
    {
        $info = self::where('user_id', $userId)->with(['user'])->find();
        if (empty($info) and $isInit) {
            return self::initUserInfo($userId);
        }
        if ($info) {
            $todayStartTime = strtotime(date('Y-m-d 00:00:00', time()));
            if ($info['last_mining_time'] < $todayStartTime && $info['today_num'] > 0) {
                $flag = self::where('id', $info['id'])->setField('today_num', 0);
                $info = self::where('id', $info['id'])->find();
            }
            if ($info['dividend_end_time'] < time() && $info['dividend_auth_status'] == 1) {
                $flag = self::where('id', $info['id'])->setField('dividend_auth_status', 2);
                $info = self::where('id', $info['id'])->find();
            }
            $buyMaxLevelId = GroupMiningSourceBuy::where('user_id', $userId)->max('level_id') ? : 0;
            if ($info['level_id'] < $buyMaxLevelId) {
                $flag = self::where('id', $info['id'])->setField('level_id', $buyMaxLevelId);
                $info = self::where('id', $info['id'])->find();
            }
        }

        return $info;
    }

    /**
     * 初始化用户信息
     * @param int $userId
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private static function initUserInfo($userId)
    {
        $pioneerCurrencyId1 = GroupMiningConfig::get_value('group_mining_pioneer_reward_currency_id1', 66);
        $pioneerCurrencyId2 = GroupMiningConfig::get_value('group_mining_pioneer_reward_currency_id2', 69);
        $id = self::insertGetId([
            'user_id' => $userId,
            'pioneer_reward_currency_id1' => $pioneerCurrencyId1,
            'pioneer_reward_currency_id2' => $pioneerCurrencyId2,
            'add_time' => time(),
        ]);
        return self::getUserInfo($userId);
    }

    //一键挖矿
    public static function onekey_mining($user_id, $buy_id)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $userInfo = GroupMiningUser::getUserInfo($user_id);
        if (empty($userInfo)) return $r;
        $buyInfo = GroupMiningSourceBuy::getBuyInfo($user_id, $buy_id);
        if (empty($buyInfo)) return $r;
        $levelInfo = GroupMiningSourceLevel::get($buyInfo['level_id']);
        if (empty($levelInfo)) return $r;
        if ($buyInfo['status'] == 1) {
            $r['message'] = lang('当前挖矿权限未开启，请刷新重试');return $r;
        }
        if ($buyInfo['status'] == 3) {
            $r['message'] = lang('当前挖矿权限已关闭，请刷新重试');return $r;
        }
        $now = time();
        if ($buyInfo['end_time'] <= $now) {
            $flag = GroupMiningSourceBuy::where('id', $buyInfo['id'])->setField('status', 3);
            $r['message'] = lang('当前挖矿权限已关闭，请刷新重试');return $r;
        }
        if ($buyInfo['today_num'] >= $levelInfo['daily_num']) {
            $r['message'] = lang('今日挖矿次数已用完');return $r;
        }
        $lastMiningNum = $levelInfo['daily_num'] - $buyInfo['today_num'];
        $n1 = $n = min($lastMiningNum, 100);//默认先一键挖矿最多100次，中途出现问题中断
        $resultList = [];

        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        while ($n > 0) {
            $res = self::mining($user_id, $buy_id, 2);
            $r['code'] = $res['code'];
            $r['message'] = $res['message'];
            if ($res['code'] != SUCCESS) {
                break;
            }
            else {
                $result = $res['result'];
                $result['index'] = $n1 - $n + 1;
                $resultList[] = $result;
            }
            $n--;
        }
        $r['result'] = $resultList;
        return $r;
    }

    //挖矿
    public static function mining($user_id, $buy_id, $mining_type = 1)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $buy_id <= 0) return $r;
        $now = time();
        $miningSwitch = GroupMiningConfig::get_value('group_mining_switch', 1);
        if (!$miningSwitch) {
            $res['message'] = lang('拼团挖矿已关闭');return $res;
        }
        $userInfo = GroupMiningUser::getUserInfo($user_id);
        if (empty($userInfo)) return $r;
        $buyInfo = GroupMiningSourceBuy::getBuyInfo($user_id, $buy_id);
        if (empty($buyInfo)) return $r;
        if ($buyInfo['type'] == 2) {//2-体验
            $experienceSwitch = GroupMiningConfig::get_value('group_mining_experience_switch', 1);
            if (!$experienceSwitch) {
                $res['message'] = lang('体验挖矿已关闭');return $res;
            }
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
            if ($mining_type == 2) {//游客不能点击一键拼团
                $res['message'] = lang('游客暂无权限');return $res;
            }
        }
        else {
            $startTime = strtotime(GroupMiningConfig::get_value('group_mining_start_time', '2020-11-11 12:20:00'));
            if ($now < $startTime) {
                $res['message'] = lang('拼团挖矿未开始');return $res;
            }
        }
        $levelInfo = GroupMiningSourceLevel::get($buyInfo['level_id']);
        if (empty($levelInfo)) return $r;
        if ($buyInfo['status'] == 1) {
            $r['message'] = lang('当前挖矿权限未开启，请刷新重试');return $r;
        }
        if ($buyInfo['status'] == 3) {
            $r['message'] = lang('当前挖矿权限已关闭，请刷新重试');return $r;
        }
        if ($buyInfo['end_time'] <= $now) {
            $flag = GroupMiningSourceBuy::where('id', $buyInfo['id'])->setField('status', 3);
            $r['message'] = lang('当前挖矿权限已关闭，请刷新重试');return $r;
        }
        if ($buyInfo['today_num'] >= $levelInfo['daily_num']) {
            $r['message'] = lang('今日挖矿次数已用完');return $r;
        }

        $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);//拼团挖矿币种id
        $moneyNum = GroupMiningConfig::get_value('group_mining_money_num', 20);//拼团挖矿支付币种数量
        $moneyCurrencyId = GroupMiningConfig::get_value('group_mining_money_currency_id', 66);//拼团挖矿支付币种id
        $ticketsNum = GroupMiningConfig::get_value('group_mining_tickets_num', 0.2);//拼团挖矿门票币种数量

        $money = keepPoint($moneyNum + $ticketsNum, 6);
        $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
        if(!$currencyUser || $currencyUser['num'] < $money) {
            $r['code'] = 10099;
            $r['message'] = lang('insufficient_balance');
            //$r['message'] = lang('拼团账户预留20.2 USDT/团次，游客先点击“立即获取”。');
            return $r;
        }

        $buyUpdate = [
            'today_num'=>['inc', 1],
            'total_num'=>['inc', 1],
            'cur_mining_num'=>['inc', 1],
            'total_tickets_num'=>['inc', $ticketsNum],
            'last_mining_time'=>$now,
        ];
        $userUpdate = [
            'today_num'=>['inc', 1],
            'total_num'=>['inc', 1],
            'total_tickets_num'=>['inc', $ticketsNum],
            'last_mining_time'=>$now,
        ];
        //计算中奖率
        $winPercent = $levelInfo['win_percent'];//中奖比例 X次中一次
        $curMiningNum = $buyInfo['cur_mining_num'];//当前挖矿次数 用于计算中奖率
        $curWinNum = $buyInfo['cur_win_num'];//当前挖矿赢次数 用于计算中奖率
        $curLoseNum = $buyInfo['cur_lose_num'];//当前挖矿输次数 用于计算中奖率
        $curRoundNum = $buyInfo['cur_round_num'];//当前第几轮
        $result = 2;//结果 1-赢 2-输
        if ($curWinNum >= 1) {//已经赢过，后面必输
            $result = 2;
        }
        else {
            if ($curMiningNum >= ($winPercent - 1)) {//当前是最后一把，前面没赢过，必赢
                $result = 1;
            }
            else {//否则随机
                //if ($curMiningNum >= 5) {//前5次必输
                    $rand = mt_rand(1, ($winPercent - $curMiningNum));
                    if ($rand == 1) {
                        $result = 1;
                    }
                //}
            }
        }

        //结果
        $rewardNum = 0;
        $forzenNum = 0;
        $loseFeeNum = 0;
        $actualNum = 0;
        $currencyPrice = CurrencyPriceTemp::BBFirst_currency_price($currencyId);//abf价格
        if ($result == 1) {//1-赢
            if ($levelInfo['reward_num_min'] == $levelInfo['reward_num_max']) {
                $rewardNum = $levelInfo['reward_num_min'];
            }
            else {
                $rewardNum = mt_rand($levelInfo['reward_num_min'], $levelInfo['reward_num_max']);
            }
            $actualNum = $rewardNum;
            $buyUpdate['cur_win_num'] = ['inc', 1];
            $buyUpdate['total_win_num'] = $userUpdate['total_win_num'] = ['inc', 1];
            $buyUpdate['total_win_reward'] = $userUpdate['total_win_reward'] = ['inc', $rewardNum];
            //冻结数量
            $forzenNum = keepPoint($rewardNum / $currencyPrice, 6);
            $buyUpdate['forzen_num'] = $userUpdate['total_forzen_num'] = ['inc', $forzenNum];
        } else {//2-输
            $loseFeeNum = GroupMiningConfig::get_value('group_mining_lose_fee_num', 0.2);//拼团挖矿输矿工费币种数量
            $rewardNum = GroupMiningConfig::get_value('group_mining_lose_reward_num', 2);//拼团挖矿输奖励币种数量
            $actualNum = keepPoint($moneyNum + $rewardNum - $loseFeeNum, 6);
            $buyUpdate['cur_lose_num'] = ['inc', 1];
            $buyUpdate['total_lose_num'] = $userUpdate['total_lose_num'] = ['inc', 1];
            $buyUpdate['total_lose_reward'] = $userUpdate['total_lose_reward'] = ['inc', $rewardNum];
            $buyUpdate['total_lose_fee_num'] = $userUpdate['total_lose_fee_num'] = ['inc', $loseFeeNum];
        }
        $goldLogFlag = false;//是否添加拼团金记录
        $roundList = [];
        $loseRewardTotal = 0;
        $loseFeeTotal = 0;
        $ticketsTotal = 0;
        $actualTotal = 0;
        $forzenTotal = 0;
        $moneyTotal = 0;
        if ($curMiningNum >= ($winPercent - 1)) {//当前是最后一把，重置 当前挖矿次数、当前挖矿赢次数、当前挖矿输次数为0，当前第几轮+1
            $buyUpdate['cur_mining_num'] = 0;
            $buyUpdate['cur_win_num'] = 0;
            $buyUpdate['cur_lose_num'] = 0;
            $buyUpdate['cur_round_num'] = ['inc', 1];
            $goldLogFlag = true;//玩够一轮，添加拼团金记录
            $ticketsTotal += $ticketsNum;
            $moneyTotal += $moneyNum;
            if ($result == 1) {//1-赢
                $forzenTotal += $forzenNum;
            }
            else {//2-输
                $loseRewardTotal += $rewardNum;
                $loseFeeTotal += $loseFeeNum;
            }
            $roundLog = GroupMiningLog::where(['user_id' => $user_id,'level_id' => $levelInfo['level_id'],'buy_id' => $buy_id,'date' => date('Y-m-d', $now),'round_num' => $curRoundNum,'result_type'=>1,'result_status'=>['in',[1,2]],])->select();
            if (count($roundLog) > 0) {
                foreach ($roundLog as $key => $value) {
                    if ($value['result_status'] == 2) {
                        $roundList[] = [
                            'id'=>$value['id'],
                        ];
                    }
                    if ($value['result'] == 1) {//1-赢
                        $forzenTotal += $value['forzen_num'];
                    }
                    else {//2-输
                        $loseRewardTotal += $value['reward_num'];
                        $loseFeeTotal += $value['lose_fee_num'];
                    }
                    $moneyTotal += $value['money'];
                    $ticketsTotal += $value['tickets_num'];
                }
            }
            $actualTotal = keepPoint($loseRewardTotal - $loseFeeTotal, 6);
        }

        try{
            self::startTrans();

            //添加挖矿记录
            $data = [
                'user_id' => $user_id,
                'level_id' => $levelInfo['level_id'],
                'buy_id' => $buy_id,
                'type' => $levelInfo['type'],//类型 1-申购 2-体验
                'mining_type' => $mining_type,//挖矿类型 1-单次挖矿 2-一键挖矿
                'date' => date('Y-m-d', $now),
                'round_num' => $curRoundNum,
                'mining_num' => ($curMiningNum + 1),
                'money' => $moneyNum,
                'money_currency_id' => $moneyCurrencyId,
                'tickets_num' => $ticketsNum,
                'result' => $result,
                'reward_num' => $rewardNum,
                'lose_fee_num' => $loseFeeNum,
                'actual_num' => $actualNum,
                'forzen_num' => $forzenNum,
                'forzen_currency_id' => $currencyId,
                'forzen_currency_price' => $currencyPrice,
                'result_type' => 1,//结算类型 1-挖矿结算(每局) 2-补偿金结算(每轮) 3-补偿金失效(每天)
                'result_status'=>1,//结算状态 1-已结算 2-未结算 3-已失效
                'result_time' => $now,
                'add_time' => $now,
            ];
            if ($result == 2) {//2-输
                //补偿金每一轮结算
                if (!$goldLogFlag) {//没玩够一轮
                    $data['result_status'] = 2;//结算状态 1-已结算 2-未结算 3-已失效
                    $data['result_time'] = 0;
                }
            }
            /*if ($result == 2) {//2-输
                $data['money'] = 0;
                $data['money_currency_id'] = 0;
            }*/
            $item_id = GroupMiningLog::insertGetId($data);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            //添加拼团金记录
            $item_id1 = 0;
            if ($goldLogFlag) {
                //添加挖矿记录
                $data1 = [
                    'user_id' => $user_id,
                    'level_id' => $levelInfo['level_id'],
                    'buy_id' => $buy_id,
                    'type' => $levelInfo['type'],//类型 1-申购 2-体验
                    'mining_type' => $mining_type,//挖矿类型 1-单次挖矿 2-一键挖矿
                    'date' => date('Y-m-d', $now),
                    'round_num' => $curRoundNum,
                    'mining_num' => ($curMiningNum + 1),
                    'money' => $moneyTotal,
                    'money_currency_id' => $moneyCurrencyId,
                    'tickets_num' => $ticketsTotal,
                    'result' => 0,
                    'reward_num' => $loseRewardTotal,
                    'lose_fee_num' => $loseFeeTotal,
                    'actual_num' => $actualTotal,
                    'forzen_num' => $forzenTotal,
                    'forzen_currency_id' => $currencyId,
                    'forzen_currency_price' => $currencyPrice,
                    'result_type' => 2,//结算类型 1-挖矿结算(每局) 2-补偿金结算(每轮) 3-补偿金失效(每天)
                    'result_status'=>1,//结算状态 1-已结算 2-未结算 3-已失效
                    'result_time' => $now,
                    'add_time' => $now,
                ];
                $item_id1 = GroupMiningLog::insertGetId($data1);
                if(!$item_id1) throw new Exception(lang('operation_failed_try_again'));

                //更新挖矿记录
                foreach ($roundList as $value) {
                    $flag = GroupMiningLog::where('id', $value['id'])->update([
                        'result_status'=>1,//结算状态 1-已结算 2-未结算 3-已失效
                        'result_time'=>$now,
                    ]);
                    if($flag == false) throw new Exception(lang('operation_failed_try_again'));
                }

                $gold_id = GroupMiningGoldLog::insertGetId([
                    'user_id' => $user_id,
                    'level_id' => $levelInfo['level_id'],
                    'currency_id' => $moneyCurrencyId,
                    'buy_id' => $buy_id,
                    'type' => $levelInfo['type'],//类型 1-申购 2-体验
                    'date' => date('Y-m-d', $now),
                    'round_num' => $curRoundNum,
                    'num' => GroupMiningConfig::get_value('group_mining_lose_reward_num', 2),
                    'add_time' => $now,
                ]);
                if(!$gold_id) throw new Exception(lang('operation_failed_try_again'));
            }

            //优先冻结和扣除门票
            //扣除+冻结
            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6101,'group_mining','out',$moneyNum,$item_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setDec('num',$moneyNum);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'forzen_num'=>$currencyUser['forzen_num']])->setInc('forzen_num',$moneyNum);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
            //扣除门票
            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6103,'group_mining_tickets','out',$ticketsNum,$item_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setDec('num',$ticketsNum);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if ($result == 1) {//1-赢
                $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                //扣除冻结
                $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'forzen_num'=>$currencyUser['forzen_num']])->setDec('forzen_num',$moneyNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加冻结
                $find = GroupMiningForzen::where(['user_id'=>$user_id,'level_id'=>$levelInfo['level_id'],'currency_id'=>$currencyId])->find();
                $current = 0;
                if ($find) {//更新
                    $flag = GroupMiningForzen::where(['id'=>$find['id']])->setInc('forzen_num', $forzenNum);
                    if($flag == false) throw new Exception(lang('operation_failed_try_again'));
                    $current = $find['forzen_num'];
                }
                else {//新增
                    $flag = GroupMiningForzen::insertGetId([
                        'user_id'=>$user_id,
                        'level_id'=>$levelInfo['level_id'],
                        'currency_id'=>$currencyId,
                        'forzen_num'=>$forzenNum,
                        'add_time'=>$now,
                    ]);
                    if($flag == false) throw new Exception(lang('operation_failed_try_again'));
                }
                //增加冻结记录
                $log_id = GroupMiningForzenLog::insertGetId([
                    'user_id'=>$user_id,
                    'level_id'=>$levelInfo['level_id'],
                    'currency_id'=>$currencyId,
                    'type' => 1,//类型 1-冻结 2-释放
                    'date' => date('Y-m-d', $now),
                    'num'=>$forzenNum,
                    'current'=>$current,
                    'reward_currency_id'=>$currencyId,
                    'reward_num'=>$rewardNum,
                    'currency_price'=>$currencyPrice,
                    'add_time'=>$now,
                ]);
                if($log_id == false) throw new Exception(lang('operation_failed_try_again'));
                //增加冻结记录详情
                $flag = GroupMiningForzenDetail::insertGetId([
                    'user_id'=>$user_id,
                    'level_id'=>$levelInfo['level_id'],
                    'currency_id'=>$currencyId,
                    'buy_id'=>$buy_id,
                    'log_id'=>$log_id,
                    'type' => 1,//类型 1-冻结 2-释放
                    'date' => date('Y-m-d', $now),
                    'num'=>$forzenNum,
                    'reward_currency_id'=>$currencyId,
                    'reward_num'=>$rewardNum,
                    'currency_price'=>$currencyPrice,
                    'add_time'=>$now,
                ]);
                if($flag == false) throw new Exception(lang('operation_failed_try_again'));

                //减去来源奖励矿池
                $pool = $forzenNum;
                $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningWin',$item_id);
                if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);

                $return = [
                    'result'=>$result,//结果 1-赢 2-输
                    'reward_num'=>$forzenNum,
                    'reward_currency_name'=>Currency::where('currency_id', $currencyId)->value('currency_name'),
                ];
            }
            else {//2-输
                $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                //返还扣除，减去冻结
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6112,'group_mining_back2','in',$moneyNum,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$moneyNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'forzen_num'=>$currencyUser['forzen_num']])->setDec('forzen_num',$moneyNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                /*$currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                //增加补偿金
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6114,'group_mining_lose_reward','in',$rewardNum,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$rewardNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                //减去矿工费
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6113,'group_mining_lose_fee','out',$loseFeeNum,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setDec('num',$loseFeeNum);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));*/

                /*//减去来源奖励矿池
                $pool = keepPoint($rewardNum / $currencyPrice, 6);
                $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningLose',$item_id);
                if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                $return = [
                    'result'=>$result,//结果 1-赢 2-输
                    'reward_num'=>$rewardNum,
                    'reward_currency_name'=>Currency::where('currency_id', $moneyCurrencyId)->value('currency_name'),
                ];
            }

            if ($goldLogFlag) {
                if ($loseRewardTotal > 0) {
                    $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                    //增加补偿金
                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6114,'group_mining_lose_reward','in',$loseRewardTotal,$item_id1,0);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$loseRewardTotal);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                }

                if ($loseFeeTotal > 0) {
                    $currencyUser = CurrencyUser::getCurrencyUser($user_id, $moneyCurrencyId);
                    //减去矿工费
                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($user_id,$moneyCurrencyId,6113,'group_mining_lose_fee','out',floattostr($loseFeeTotal),$item_id1,0);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setDec('num',$loseFeeTotal);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            //更新申购数据
            $flag = GroupMiningSourceBuy::where('id', $buyInfo['id'])->update($buyUpdate);
            if($flag == false) throw new Exception(lang('operation_failed_try_again'));

            //更新用户数据
            $flag = GroupMiningUser::where('id', $userInfo['id'])->update($userUpdate);
            if($flag == false) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $return;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //签到释放
    public static function forzen_free($user_id, $level_id)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $level_id <= 0) return $r;
        $now = time();
        $forzenFreeSwitch = GroupMiningConfig::get_value('group_mining_forzen_free_switch', 1);
        if (!$forzenFreeSwitch) {
            $res['message'] = lang('拼团挖矿签到释放已关闭');return $res;
        }
        $userInfo = GroupMiningUser::getUserInfo($user_id);
        if (empty($userInfo)) return $r;
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if (empty($levelInfo)) return $r;
        $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);//拼团挖矿币种id
        $currency = Currency::get($currencyId);
        if (empty($currency)) return $r;
        $forzenInfo = GroupMiningForzen::getForzenInfo($user_id, $level_id, $currencyId);
        if (empty($forzenInfo)) return $r;
        if ($forzenInfo['today_free_num'] > 0) {
            $r['message'] = lang('当前等级今日已签到释放');return $r;
        }
        if ($forzenInfo['forzen_num'] <= 0) {
            $r['message'] = lang('当前等级冻结已全部释放');return $r;
        }
        /*if ($forzenInfo['total_free_num'] >= $forzenInfo['forzen_num']) {
            $r['message'] = lang('当前等级冻结已全部释放');return $r;
        }*/

        //释放比例
        //$openBuy = GroupMiningSourceBuy::getOpenBuy($user_id);
        //$freeRateMin = $levelInfo['free_rate_min'];
        //$freeRateMax = $levelInfo['free_rate_max'];
        $actualfreeRateMax = $levelInfo['actual_free_rate'];
        if ($levelInfo['type'] == 1) {//1-申购
            $buyFind1 = GroupMiningSourceBuy::where(['user_id'=>$user_id,'level_id'=>$levelInfo['level_id'],'status'=>['in',[1,2]]])->find();
            $buyFind2 = GroupMiningSourceBuy::where(['user_id'=>$user_id,'level_id'=>$levelInfo['level_id'],'status'=>3])->find();
            if (!$buyFind1 && $buyFind2) {
                //$freeRateMin = $levelInfo['close_free_rate'];
                //$freeRateMax = $levelInfo['close_free_rate'];
                $actualfreeRateMax = $levelInfo['close_free_rate'];
            }
        }
        /*if ($openBuy) {
            if ($levelInfo['type'] == 1) {//1-申购
                if ($openBuy['level_id'] != $levelInfo['level_id']) {
                    $freeRateMin = $levelInfo['close_free_rate'];
                    $freeRateMax = $levelInfo['close_free_rate'];
                }
            }
        }
        else {
            if ($levelInfo['type'] == 1) {//1-申购
                $freeRateMin = $levelInfo['close_free_rate'];
                $freeRateMax = $levelInfo['close_free_rate'];
            }
        }*/
        //$freeRate = keepPoint(mt_rand(($freeRateMin * 100), ($freeRateMax * 100)) / 100, 2);
        //$freeRate = $freeRateMin;
        $freeRate = $actualfreeRateMax;
        $lastFreeNum = keepPoint($forzenInfo['forzen_num'] - $forzenInfo['total_free_num'] , 6);
        $freeNum = keepPoint($forzenInfo['forzen_num'] * ($freeRate / 100), 6);
        //$freeNum = min($freeNum, $lastFreeNum);

        $currencyUser = CurrencyUser::getCurrencyUser($user_id, $currencyId);

        $forzenUpdate = [
            'today_free_num'=>['inc', $freeNum],
            'total_free_num'=>['inc', $freeNum],
            'forzen_num'=>['dec', $freeNum],
            'last_free_time'=>$now,
        ];
        $userUpdate = [
            'total_free_num'=>['inc', $freeNum],
        ];

        $buyList = GroupMiningSourceBuy::where(['user_id'=>$user_id,'level_id'=>$level_id,'forzen_num'=>['exp',Db::raw('>`free_num`')],])->order('add_time', 'ASC')->select();
        $buyUpdateList = [];
        $forzenDetail = [];
        if (count($buyList) > 0) {
            foreach ($buyList as $key => $value) {
                $lastFreeNum1 = keepPoint($value['forzen_num'] - $value['free_num'] , 6);
                $freeNum1 = keepPoint($value['forzen_num'] * ($freeRate / 100), 6);
                $freeNum1 = min($freeNum1, $lastFreeNum1);
                $buyUpdateList[] = [
                    'buy_id'=>$value['id'],
                    'free_num'=>$freeNum1,
                ];
                $forzenDetail[] = [
                    'user_id'=>$user_id,
                    'level_id'=>$level_id,
                    'currency_id'=>$currencyId,
                    'buy_id'=>$value['id'],
                    'log_id'=>0,
                    'type' => 2,//类型 1-冻结 2-释放
                    'date' => date('Y-m-d', $now),
                    'num'=>$freeNum1,
                    'forzen_num'=>$value['forzen_num'],
                    'free_rate'=>$freeRate,
                    'add_time'=>$now,
                ];
            }
        }

        try{
            self::startTrans();

            //添加冻结记录
            $data = [
                'user_id'=>$user_id,
                'level_id'=>$level_id,
                'currency_id'=>$currencyId,
                'type' => 2,//类型 1-冻结 2-释放
                'date' => date('Y-m-d', $now),
                'num'=>$freeNum,
                'current'=>$forzenInfo['forzen_num'],
                'forzen_num'=>$forzenInfo['forzen_num'],
                'free_rate'=>$freeRate,
                'add_time'=>$now,
            ];
            $item_id = GroupMiningForzenLog::insertGetId($data);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            foreach ($forzenDetail as $key => $value) {
                $forzenDetail[$key]['log_id'] = $item_id;
            }

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($user_id,$currencyId,6104,'group_mining_forzen_free','in',$freeNum,$item_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$freeNum);
            if(!$flag) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            //更新申购数据
            if (!empty($buyUpdateList)) {
                foreach ($buyUpdateList as $value) {
                    $flag = GroupMiningSourceBuy::where('id', $value['buy_id'])->update([
                        'forzen_num'=>['dec', $value['free_num']],
                        'free_num'=>['inc', $value['free_num']],
                    ]);
                    if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
                }
            }

            //增加冻结记录详情
            if (!empty($forzenDetail)) {
                $flag = GroupMiningForzenDetail::insertAll($forzenDetail);
                if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
            }

            //更新冻结数据
            $flag = GroupMiningForzen::where('id', $forzenInfo['id'])->update($forzenUpdate);
            if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            //更新用户数据
            $flag = GroupMiningUser::where('id', $userInfo['id'])->update($userUpdate);
            if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            $return = [
                'free_num'=>$freeNum,
                'currency_name'=>$currency['currency_name'],
            ];

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $return;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //获取升级首页信息
    public static function getUpgradeInfo($user_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = [];

        $userInfo = self::getUserInfo($user_id, true);
        $burncCrrencyId = GroupMiningConfig::get_value('group_mining_burn_currency_id', 66);//拼团挖矿奖励等级升级燃烧币种id
        $burncCrrency = Currency::get($burncCrrencyId);
        $burncMoneyCrrencyId = GroupMiningConfig::get_value('group_mining_burn_money_currency_id', 69);//拼团挖矿奖励等级升级燃烧支付币种id
        $burncMoneyCrrency = Currency::get($burncMoneyCrrencyId);
        $ticketsRewardCrrencyId = GroupMiningConfig::get_value('group_mining_tickets_reward_currency_id', 66);//拼团挖矿拼团券奖励币种id
        $ticketsRewardCrrency = Currency::get($ticketsRewardCrrencyId);
        $feeRewardCrrencyId = GroupMiningConfig::get_value('group_mining_fee_reward_currency_id', 66);//拼团挖矿矿工费奖励币种id
        $feeRewardCrrency = Currency::get($feeRewardCrrencyId);
        $goldRewardCrrencyId = GroupMiningConfig::get_value('group_mining_gold_reward_currency_id', 66);//拼团挖矿拼团金奖励币种id
        $goldRewardCrrency = Currency::get($goldRewardCrrencyId);
        $dividendRewardCrrencyId = GroupMiningConfig::get_value('group_mining_dividend_reward_currency_id', 66);//拼团挖矿联创红利奖励币种id
        $dividendRewardCrrency = Currency::get($dividendRewardCrrencyId);
        $airdropRewardCrrencyId = GroupMiningConfig::get_value('group_mining_airdrop_reward_currency_id', 69);//拼团挖矿ABF空投奖励币种id
        $airdropRewardCrrency = Currency::get($airdropRewardCrrencyId);
        $now = time();
        $dividendRewardDay = 0;
        if ($userInfo['dividend_end_time'] > $now) {
            //$dividendRewardDay = floor(($userInfo['dividend_end_time'] - max($now, $userInfo['dividend_start_time'])) / 86400);
            $dividendRewardDay = intval(($userInfo['dividend_end_time'] - max($now, $userInfo['dividend_start_time'])) / 86400);
        }
        $upgradeInfo = [
            'reward_level'=>$userInfo['reward_level'],
            'reward_level_name'=>$userInfo['reward_level'] > 0 ? GroupMiningRewardLevel::where('level_id', $userInfo['reward_level'])->value('level_name') : 'F0',
            'zhitui_num'=>$userInfo['zhitui_num'],
            'team_num'=>$userInfo['team_num'],
            'burn_num'=>$userInfo['burn_num'],
            'burn_currency_name'=>$burncCrrency['currency_name'],
            'burn_money_num'=>$userInfo['burn_money_num'],
            'burn_money_currency_name'=>$burncMoneyCrrency['currency_name'],
            'total_tickets_reward'=>$userInfo['total_tickets_reward'],
            'tickets_reward_currency_name'=>$ticketsRewardCrrency['currency_name'],
            'total_fee_reward'=>$userInfo['total_fee_reward'],
            'fee_reward_currency_name'=>$feeRewardCrrency['currency_name'],
            'total_gold_reward'=>$userInfo['total_gold_reward'],
            'gold_reward_currency_name'=>$goldRewardCrrency['currency_name'],
            'total_dividend_reward'=>$userInfo['total_dividend_reward'],
            'dividend_reward_currency_name'=>$dividendRewardCrrency['currency_name'],
            'total_airdrop_reward'=>$userInfo['total_airdrop_reward'],
            'airdrop_reward_currency_name'=>$airdropRewardCrrency['currency_name'],
            'dividend_reward_day'=>$dividendRewardDay,
        ];
        $levels = GroupMiningRewardLevel::order('level_id', 'ASC')->select();
        $level_list = [];
        if (count($levels) > 0) {
            foreach ($levels as $key => $value) {
                $status = 2;//状态 1-有效 2-无效
                if ($userInfo['burn_num'] >= $value['burn_num']) $status = 1;
                $level_list[] = [
                    'level_id'=>$value['level_id'],
                    'level_name'=>$value['level_name'],
                    'zhitui_num'=>$value['zhitui_num'],
                    'team_num'=>$value['team_num'],
                    'burn_num'=>$value['burn_num'],
                    'last_burn_num'=>$value['burn_num'] - $userInfo['burn_num'] > 0 ? $value['burn_num'] - $userInfo['burn_num'] : 0,
                    'status'=>$status,
                ];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $upgradeInfo['level_list'] = $level_list;
        $r['result'] = $upgradeInfo;
        return $r;
    }

    //升级燃烧
    public static function upgrade_burn($user_id, $level_id)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($user_id<=0 || $level_id <= 0) return $r;
        $now = time();

        $userInfo = GroupMiningUser::getUserInfo($user_id);
        if (empty($userInfo)) return $r;
        $levelInfo = GroupMiningRewardLevel::get($level_id);
        if (empty($levelInfo)) return $r;
        $burncCrrencyId = GroupMiningConfig::get_value('group_mining_burn_currency_id', 66);//拼团挖矿奖励等级升级燃烧币种id
        $burncCrrency = Currency::get($burncCrrencyId);
        if (empty($burncCrrency)) return $r;
        $burncMoneyCrrencyId = GroupMiningConfig::get_value('group_mining_burn_money_currency_id', 69);//拼团挖矿奖励等级升级燃烧支付币种id
        $burncMoneyCrrency = Currency::get($burncMoneyCrrencyId);
        if (empty($burncMoneyCrrency)) return $r;
        $burncMoneyCrrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($burncMoneyCrrencyId);//abf价格

        //燃烧数量
        $lastBurnNum = $levelInfo['burn_num'] - $userInfo['burn_num'];
        if ($lastBurnNum <= 0) {
            $r['message'] = lang('当前等级已燃烧');return $r;
        }
        $moneyNum = keepPoint($lastBurnNum / $burncMoneyCrrencyPrice, 6);

        $currencyUser = CurrencyUser::getCurrencyUser($user_id, $burncMoneyCrrencyId);
        if(!$currencyUser || $currencyUser['num'] < $moneyNum) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $userUpdate = [
            'burn_num'=>['inc', $lastBurnNum],
            'burn_money_num'=>['inc', $moneyNum],
        ];

        //判断用户奖励等级是否升级
        $upLevel = 0;
        $dividendDay = 0;
        for ($i = $userInfo['reward_level'] + 1; $i <= $level_id; $i++) {
            $levelInfo1 = GroupMiningRewardLevel::get($i);
            if ($levelInfo1['zhitui_num'] <= $userInfo['zhitui_num'] && $levelInfo1['team_num'] <= $userInfo['team_num']) {
                $upLevel++;
                $dividendDay += $levelInfo1['dividend_reward_time'];
            }
        }
        if ($upLevel > 0) $userUpdate['reward_level'] = ['inc', $upLevel];
        if ($userInfo['dividend_auth_status'] == 1 && $dividendDay > 0) $userUpdate['dividend_end_time'] = ['inc', $dividendDay * 86400];

        try{
            self::startTrans();

            //添加燃烧记录
            $data = [
                'user_id'=>$user_id,
                'level_id'=>$level_id,
                'currency_id'=>$burncCrrencyId,
                'num'=>$lastBurnNum,
                'current'=>$userInfo['burn_num'],
                'money_currency_id'=>$burncMoneyCrrencyId,
                'money_num'=>$moneyNum,
                'money_currency_price'=>$burncMoneyCrrencyPrice,
                'add_time'=>$now,
            ];
            $item_id = GroupMiningBurnLog::insertGetId($data);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$burncMoneyCrrencyId,6105,'group_mining_upgrade_burn','out',$moneyNum,$item_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setDec('num',$moneyNum);
            if(!$flag) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            //更新用户数据
            $flag = GroupMiningUser::where('id', $userInfo['id'])->update($userUpdate);
            if($flag == false) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);

            $return = [
                'burn_num'=>$lastBurnNum,
                'burn_currency_name'=>$burncCrrency['currency_name'],
                'money_num'=>$moneyNum,
                'money_currency_name'=>$burncMoneyCrrency['currency_name'],
            ];

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $return;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    // 获取我的团队
    public static function getMyTeam($member_id, $child_id, $page, $page_size)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('not_data'),
            'result' => null
        ];

        $where = [
            't2.member_id' => $member_id,
            't2.level' => 1,
            't4.status' => 2,
            't4.type' => 1,
        ];
        if (!empty($child_id)) {
            $where['t1.user_id'] = $child_id;
        }
        $list = self::alias('t1')->field(['t1.user_id', 't3.ename', 't4.money','t5.currency_name', 't1.team_num', 't1.pioneer_reward_rate'])
            ->where($where)
            ->join([config("database.prefix") . 'member_bind' => 't2'], "t1.user_id = t2.child_id", "LEFT")
            ->join([config("database.prefix") . "member" => 't3'], "t1.user_id = t3.member_id", "LEFT")
            ->join([config("database.prefix") . "group_mining_source_buy" => 't4'], "t1.user_id = t4.user_id", "LEFT")
            ->join([config("database.prefix") . "currency" => 't5'], "t4.money_currency_id = t5.currency_id", "LEFT")
            ->page($page, $page_size)->order("t1.team_num desc,t1.add_time asc")->select();
        if (!$list) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }
}
