<?php
//房间用户战绩列表
namespace app\common\model;


use think\Error;
use think\Exception;
use think\Log;
use think\Model;

class RoomUsersRecord extends Model
{
    /**
     * 结果枚举
     * @var array
     */
    const RESULT_ENUM = [
        1 => "赢",
        2 => "输",
    ];

    public function getStatusTextAttr($value,$data)
    {
        return STATUS_ENUM[$data['rul_status']];
    }

    public function room()
    {
        return $this->belongsTo(RoomList::class, 'rur_room_id', 'rl_room_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'rur_currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    /**
     * 房间开奖
     * @param $room_info
     * @param $user_id
     * @param bool $is_robot
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function lottery($room_info, $level_info, $user_id)
    {
        try {
            self::startTrans();

            $seatList = RoomSeatList::/*with(['user'])->*/where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>['gt',0]])->order('rsl_seat_id', 'asc')->select();
            if (count($seatList) < 10) {
                throw new Exception(lang('开奖失败，房间人数未满'));
            }

            $creator_win = GameConfig::get_value('room_vip_creator_win', 0);//VIP房间房主是否必赢 1-是 0-否
            $reward_currency_id = GameConfig::get_value('game_give_reward_currency_id', 22);//游戏赠送奖励币种id
            $reward_rate = GameConfig::get_value('game_give_reward_rate', 2.5);//游戏赠送奖励比率(%)
            $draw_rate = GameConfig::get_value('room_vip_creator_draw_rate', 50);//VIP房间房主抽水比率(%)
            $recordList = [];
            $seatUsers = [];
            $userList = [];
            $whiteUsers = [];
            $winMoney = keepPoint($room_info['rl_num'] / $room_info['rl_open_num'], 6);
            $fee_rate = GameConfig::get_value('game_win_money_fee_rate', 0);//赢的金额的手续费比率
            $fee = 0;
            if ($fee_rate > 0) {
                $fee = keepPoint($winMoney * $fee_rate / 100, 6);
            }
            $rewardNum = 0;
            if ($reward_rate > 0) {
                $rewardNum = keepPoint($room_info['rl_num'] * $reward_rate / 100, 6);
            }
            $drawNum = 0;
            /*if ($room_info['rl_is_vip'] == 1 && $draw_rate > 0) {//是否是VIP房间 1-VIP房间 2-普通房间
                $drawNum = keepPoint($winMoney * $draw_rate / 100, 6);
            }*/
            //抽水逻辑，VIP房间房主抽水=((房间最小可用数量(如1000)/房间开奖人数)*手续费比率/100)*(房间开奖人数-1)，即所有赢的用户扣除的手续费归房主抽水
            if ($room_info['rl_is_vip'] == 1 && $fee > 0) {//是否是VIP房间 1-VIP房间 2-普通房间
                $drawNum = keepPoint(($room_info['rl_open_num'] - 1) * $fee, 6);
            }
            $lock_multiple = GameConfig::get_value('game_lose_lock_multiple', 10);//游戏输时锁仓(io券)放大倍数
            $lockNum = keepPoint($room_info['rl_num'] * $lock_multiple , 6);//io券数量
            $now = time();
            foreach ($seatList as $key => $value) {
                if ($value['rsl_member_id'] <= 0) {
                    Log::write("continue-1", 'INFO');
                    continue;
                }
                $userFind = RoomUsersList::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$value['rsl_member_id']])->find();
                if (!$userFind) {
                    Log::write("continue-2", 'INFO');
                    continue;
                }
                $userList[$value['rsl_member_id']] = $userFind;
                if ($userFind['rul_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    //普通房间机器人包赢
                    $recordList[] = [
                        'rur_room_id'=>$room_info['rl_room_id'],
                        'rur_member_id'=>$value['rsl_member_id'],
                        'rur_part_num'=>$room_info['rl_current_part'],
                        'rur_seat_id'=>$value['rsl_seat_id'],
                        'rur_is_robot'=>$userFind['rul_is_robot'],
                        'rur_result'=>1,//结果 1-赢 2-输
                        'rur_currency_id'=>$room_info['rl_currency_id'],
                        'rur_money'=>$winMoney,
                        'rur_actual_money'=>($winMoney - $fee),
                        'rur_fee_rate'=>$fee_rate,
                        'rur_fee'=>$fee,
                        'rur_create_time'=>$now,
                    ];
                }
                else {
                    if ($creator_win && $room_info['rl_creator_id'] == $value['rsl_member_id']) {//VIP房间房主必赢
                        Log::write("continue-3", 'INFO');
                        $recordList[] = [
                            'rur_room_id'=>$room_info['rl_room_id'],
                            'rur_member_id'=>$value['rsl_member_id'],
                            'rur_part_num'=>$room_info['rl_current_part'],
                            'rur_seat_id'=>$value['rsl_seat_id'],
                            'rur_is_robot'=>$userFind['rul_is_robot'],
                            'rur_result'=>1,//结果 1-赢 2-输
                            'rur_currency_id'=>$room_info['rl_currency_id'],
                            'rur_money'=>$winMoney,
                            'rur_actual_money'=>($winMoney - $fee),
                            'rur_fee_rate'=>$fee_rate,
                            'rur_fee'=>$fee,
                            'rur_create_time'=>$now,
                        ];
                        /*$roomUserList[] = [
                            'rul_id'=>$userFind['rul_id'],
                            'rul_result_num'=>['inc',1],
                            'rul_win_num'=>['inc',1],
                            'rul_win_money'=>['inc',$winMoney],
                        ];*/
                    }
                    else {
                        $seatUsers[$value['rsl_member_id']] = $value;
                    }
                }
            }
            if (count($seatUsers) <= 0) {
                throw new Exception(lang('开奖失败，房间用户人数不足，全部都是机器人'));
            }
            else if (count($seatUsers) > 1) {
                Log::write("房间结算,1,seatUsers:".count($seatUsers), 'INFO');
                foreach ($seatUsers as $key => $value) {
                    $whiteFind = RoomWhiteList::with(['level'])->where(['rwl_member_id'=>$value['rsl_member_id'], 'rwl_status'=>1])->find();
                    if ($whiteFind) {
                        $whiteUsers[$whiteFind['rwl_member_id']] = $whiteFind['rwl_id'];
                        if ($whiteFind['level']['rws_win_num'] > $whiteFind['rwl_today_win_num'] || $whiteFind['level']['rws_win_num'] == 0) {//白名单用户，包赢
                            if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                                //普通房间白名单用户包赢
                                $recordList[] = [
                                    'rur_room_id'=>$room_info['rl_room_id'],
                                    'rur_member_id'=>$value['rsl_member_id'],
                                    'rur_part_num'=>$room_info['rl_current_part'],
                                    'rur_seat_id'=>$value['rsl_seat_id'],
                                    'rur_is_robot'=>$userList[$value['rsl_member_id']]['rul_is_robot'],
                                    'rur_result'=>1,//结果 1-赢 2-输
                                    'rur_currency_id'=>$room_info['rl_currency_id'],
                                    'rur_money'=>$winMoney,
                                    'rur_actual_money'=>($winMoney - $fee),
                                    'rur_fee_rate'=>$fee_rate,
                                    'rur_fee'=>$fee,
                                    'rur_create_time'=>$now,
                                ];
                                unset($seatUsers[$value['rsl_member_id']]);
                            }
                        }
                    }
                }
            }
            Log::write("房间结算,2,seatUsers:".count($seatUsers), 'INFO');
            $lose = array_rand($seatUsers);
            foreach ($seatUsers as $key => $value) {
                $data = [
                    'rur_room_id'=>$room_info['rl_room_id'],
                    'rur_member_id'=>$value['rsl_member_id'],
                    'rur_part_num'=>$room_info['rl_current_part'],
                    'rur_seat_id'=>$value['rsl_seat_id'],
                    'rur_is_robot'=>$userList[$value['rsl_member_id']]['rul_is_robot'],
                    //'rur_result'=>1,//结果 1-赢 2-输
                    'rur_currency_id'=>$room_info['rl_currency_id'],
                    //'rur_money'=>$winMoney,
                    //'rur_actual_money'=>($winMoney - $fee),
                    //'rur_fee_rate'=>$fee_rate,
                    //'rur_fee'=>$fee,
                    'rur_create_time'=>$now,
                ];
                if ($value['rsl_member_id'] == $lose) {//输的用户
                    $data['rur_result'] = 2;
                    $data['rur_money'] = -$room_info['rl_num'];
                    $data['rur_actual_money'] = -$room_info['rl_num'];
                    $data['rur_lock_num'] = $lockNum;
                }
                else {
                    $data['rur_result'] = 1;
                    $data['rur_money'] = $winMoney;
                    $data['rur_actual_money'] = ($winMoney - $fee);
                    $data['rur_fee_rate'] = $fee_rate;
                    $data['rur_fee'] = $fee;
                }
                $recordList[] = $data;
            }
            //全部用户下桌
            for ($i = 1; $i <= 10; $i ++) {
                $seatWhere = [
                    'rsl_room_id'=>$room_info['rl_room_id'],
                    'rsl_seat_id'=>$i,
                ];
                //$seatSave = RoomSeatList::where($seatWhere)->update(['rsl_member_id'=>0]);
                $seatSave = RoomSeatList::where($seatWhere)->update(['rsl_exit_time'=>(time() + 5)]);
                if (!$seatSave) {
                    throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
                }
            }
            // 入库
            foreach ($recordList as $value) {
                //$record = new self();
                //$recordRes = $record->saveAll($value);
                $recordRes = self::create($value);
                if (empty($recordRes)) {
                    throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                }

                if ($value['rur_result'] == 2) {//结果 1-赢 2-输
                    $roomUserUpdate = [
                        'rul_id'=>$userList[$value['rur_member_id']]['rul_id'],
                        'rul_result_num'=>['inc',1],
                        'rul_lose_num'=>['inc',1],
                        'rul_lose_money'=>['inc',$room_info['rl_num']],
                        'rul_seat_id'=>0,
                    ];

                    if (array_key_exists($value['rur_member_id'], $whiteUsers)) {
                        $whiteUpdate = [
                            'rwl_id'=>$whiteUsers[$value['rur_member_id']],
                            'rwl_lose_num'=>['inc',1],
                        ];
                    }
                }
                else {
                    $roomUserUpdate = [
                        'rul_id'=>$userList[$value['rur_member_id']]['rul_id'],
                        'rul_result_num'=>['inc',1],
                        'rul_win_num'=>['inc',1],
                        'rul_win_money'=>['inc',$winMoney],
                        'rul_seat_id'=>0,
                    ];

                    if (array_key_exists($value['rur_member_id'], $whiteUsers)) {
                        if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                            //普通房间白名单用户包赢
                            $whiteUpdate = [
                                'rwl_id'=>$whiteUsers[$value['rur_member_id']],
                                'rwl_win_num'=>['inc',1],
                                'rwl_today_win_num'=>['inc',1],
                                'rwl_last_win_time'=>$now,
                            ];
                        }
                    }
                }
                if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                    //普通房间结算之后用户退出房间
                    $roomUserUpdate['rul_status'] = 2;//状态 1-加入房间 2-退出房间
                    $roomUserUpdate['rul_exit_num'] = ['inc',1];
                    $roomUserUpdate['rul_exit_time'] = time();
                }

                $roomUser = new RoomUsersList();
                $roomUserRes = $roomUser->update($roomUserUpdate);
                if (!$roomUserRes) {
                    throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
                }

                $number = $room_info['rl_num'] + $value['rur_actual_money'];
                if ($value['rur_is_robot'] == 1) {//是否是机器人 1-普通用户 2-机器人
                    //普通用户更新白名单信息和用户资产相关
                    if (!empty($whiteUpdate)) {
                        $roomWhite = new RoomWhiteList();
                        $roomWhiteRes = $roomWhite->update($whiteUpdate);
                        if (!$roomWhiteRes) {
                            throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
                        }
                    }

                    $usersCurrency = CurrencyUser::getCurrencyUser($value['rur_member_id'], $room_info['rl_currency_id']);
                    if ($value['rur_result'] == 1) {//结果 1-赢 2-输
                        //更新用户资产
                        //$usersCurrency['uc_num'] += $number;
                        // 账本
                        $flag = AccountBook::add_accountbook($value['rur_member_id'], $room_info['rl_currency_id'], AccountBookType::GAME_WIN_MONEY, 'room_win_money', 'in', $number, $recordRes['rur_id'], $value['rur_fee']);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setInc('num',$number);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                    }
                    else {
                        //更新用户锁仓
                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'game_lock'=>$usersCurrency['game_lock']])->setInc('game_lock',$lockNum);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                        //添加用户锁仓记录
                        $flag = GameLockLog::add_log(1, $value['rur_member_id'], $lockNum, $recordRes['rur_id']);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                    }

                    //赠送奖励
                    $rewardUsersCurrency = CurrencyUser::getCurrencyUser($value['rur_member_id'], $reward_currency_id);
                    // 账本
                    $flag = AccountBook::add_accountbook($value['rur_member_id'], $reward_currency_id, AccountBookType::GAME_GIVE_REWARD, 'room_give_reward', 'in', $rewardNum, $recordRes['rur_id']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                    $flag = CurrencyUser::where(['cu_id'=>$rewardUsersCurrency['cu_id'],'num'=>$rewardUsersCurrency['num']])->setInc('num',$rewardNum);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    //更新VIP用户积分豆
                    $flag = UsersVotes::where('user_id', $value['rur_member_id'])->setInc('game_money', $room_info['rl_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }

                //添加消息
                if ($value['rur_is_robot'] == 1) {//是否是机器人 1-普通用户 2-机器人
                    $nick = Member::where('member_id', $value['rur_member_id'])->value('nick');
                }
                else {
                    $nick = RoomRobotList::where('rrl_id', $value['rur_member_id'])->value('rrl_nickname');
                }
                if ($value['rur_result'] == 2) {//结果 1-赢 2-输
                    //$num = $room_info['rl_num'];
                    $num = number_format($lockNum,2,".","");
                    $content = $nick.lang('room_won').$num.lang('votes_score_lock');//券
                }
                else {
                    //$num = $number;
                    $num = $value['rur_actual_money'];
                    $currency = Currency::where('currency_id', $room_info['rl_currency_id'])->find();
                    $content = $nick.lang('room_won').$num.$currency['currency_name'];
                    Log::write("房间结算,num:" . $num, 'INFO');
                }
                $flag = RoomMsgList::add_msg($room_info['rl_room_id'], 0, $content);
                if(!$flag) throw new Exception(lang('operation_failed_try_again').'-in line:'.__LINE__);
            }

            //VIP房间房主抽水
            if ($room_info['rl_is_vip'] == 1 && $drawNum > 0) {//是否是VIP房间 1-VIP房间 2-普通房间
                $drawUsersCurrency = CurrencyUser::getCurrencyUser($room_info['rl_creator_id'], $room_info['rl_currency_id']);
                // 账本
                $flag = AccountBook::add_accountbook($room_info['rl_creator_id'], $room_info['rl_currency_id'], AccountBookType::VIP_ROOM_DRAW, 'vip_room_draw', 'in', $drawNum, $room_info['rl_room_id']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }
                $flag = CurrencyUser::where(['cu_id'=>$drawUsersCurrency['cu_id'],'num'=>$drawUsersCurrency['num']])->setInc('num',$drawNum);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }
            }

            //更新房间数据
            $update = [
                'rl_status' => 3,
                'rl_real_result_time' => $now,
                'rl_result_num' => ['inc',1],
                'rl_current_part' => ['inc',1],
                'rl_ready_num' => 0,
            ];
            if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                //普通房间结算之后所有用户退出房间
                $update['rl_current_num'] = 0;
            }
            $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
            if(!$roomSave) {
                throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);
                //return false;
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            Log::write("房间结算成功", 'INFO');
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
            Log::write("房间结算异常:" . $r['message'], 'INFO');
        }
        return $r;
    }

    static public function get_result($room_info)
    {
        //rl_is_vip
        $field = 'rur_id,rur_member_id,rur_part_num,rur_seat_id,rur_result,rur_currency_id,rur_money,rur_is_robot,rur_actual_money,rur_create_time,rur_lock_num';
        $recordSelect = self::with(['currency'])->field($field)->where(['rur_room_id'=>$room_info['rl_room_id'], 'rur_part_num'=>$room_info['rl_result_num']])->select();
        $resultList = [];
        if (count($recordSelect)) {
            foreach ($recordSelect as $key => $value) {
                if ($value['rur_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    $user = RoomRobotList::where('rrl_id', $value['rur_member_id'])->field('rrl_nickname')->find();
                    $nick = $user['rrl_nickname'];
                }
                else {
                    $user = Member::where('member_id', $value['rur_member_id'])->field('nick')->find();
                    $nick = $user['nick'];
                }
                //$record = $value;
                //$record['nick'] = $nick;
                //$resultList[] = $record;

                if ($value['rur_result'] == 2) {//输
                    $rur_money = number_format($value['rur_lock_num'],2,".","");
                    $currency_name = lang('votes_score_lock');//券
                }
                else {
                    $rur_money = number_format($value['rur_actual_money'],2,".","");
                    $currency_name = $value['currency']['currency_name'];
                }
                $resultList[] = [
                    'rur_id'=>$value['rur_id'],
                    'rur_member_id'=>$value['rur_member_id'],
                    'rur_seat_id'=>$value['rur_seat_id'],
                    'rur_result'=>$value['rur_result'],
                    'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[$value['rur_result']],
                    'rur_money'=>$rur_money,
                    'currency_name'=>$currency_name,
                    'nick'=>$nick,
                    'rur_create_time'=>date('Y-m-d H:i:s', $value['rur_create_time']),
                ];
            }
        }
        return $resultList;
    }
}