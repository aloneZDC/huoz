<?php
//房间用户列表
namespace app\common\model;


use think\Error;
use think\Exception;
use think\Log;
use think\Model;

class RoomUsersList extends Model
{
    /**
     * 状态枚举
     * @var array
     */
    const STATUS_ENUM = [
        1 => "加入房间",
        2 => "退出房间",
    ];

    public function getStatusTextAttr($value,$data)
    {
        return STATUS_ENUM[$data['rul_status']];
    }

    public function room()
    {
        return $this->belongsTo(RoomList::class, 'rul_room_id', 'rl_room_id');
    }

    /**
     * 进入房间
     * @param $room_info
     * @param $user_id
     * @param bool $is_robot
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function join_room($room_info, $user_id, $is_robot = false)
    {
        try {
            self::startTrans();

            // 入库
            if ($room_info['rl_is_vip'] == 1) {//是否是VIP房间 1-VIP房间 2-普通房间
                //VIP房间进入房间之后需要点击准备操作进行占位
                if ($is_robot) {
                    throw new Exception(lang('VIP房间不允许加入机器人'));
                }
                $userFind = self::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$user_id])->find();
                if ($userFind) {
                    $userSave = self::where('rul_id', $userFind['rul_id'])->update([
                        //'rul_seat_id'=>$seat_id,
                        'rul_join_num'=>['inc',1],
                        'rul_status'=>1,
                        'rul_join_time'=>time(),
                    ]);
                    if(!$userSave) throw new Exception("更新失败-2");
                }
                else {
                    $res = self::create([
                        'rul_room_id'=>$room_info['rl_room_id'],
                        'rul_member_id'=>$user_id,
                        //'rul_seat_id'=>$seat_id,
                        'rul_is_creator'=>$room_info['rl_creator_id'] == $user_id ? 2 : 1,
                        'rul_join_num'=>1,
                        'rul_join_time'=>time(),
                    ]);
                    if (empty($res)) {
                        throw new Exception(lang('插入记录失败-1'));
                    }
                }

                //添加消息
                $nick = Member::where('member_id', $user_id)->value('nick');
                $content = $nick.lang('room_join');
                $flag = RoomMsgList::add_msg($room_info['rl_room_id'], 0, $content);
                if(!$flag) throw new Exception(lang('operation_failed_try_again').'-3');

                //更新房间数据
                $update = [
                    'rl_current_num' => ['inc',1],
                ];
                $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
                if(!$roomSave) {
                    throw new Exception("更新失败-1");
                }
            }
            else {
                $userFind = self::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$user_id])->find();
                if ($userFind) {
                    $userSave = self::where('rul_id', $userFind['rul_id'])->update([
                        //'rul_seat_id'=>$seat_id,
                        'rul_join_num'=>['inc',1],
                        'rul_status'=>1,
                        'rul_join_time'=>time(),
                    ]);
                    if(!$userSave) throw new Exception("更新失败-2");
                    $id = $userFind['rul_id'];
                }
                else {
                    $res = self::create([
                        'rul_room_id'=>$room_info['rl_room_id'],
                        'rul_member_id'=>$user_id,
                        //'rul_seat_id'=>$seat_id,
                        'rul_is_robot'=>$is_robot ? 2 : 1,
                        'rul_join_num'=>1,
                        'rul_ready_num'=>1,
                        'rul_join_time'=>time(),
                    ]);
                    if (empty($res)) {
                        throw new Exception(lang('插入记录失败-2'));
                    }
                    $id = $res->getLastInsID();
                }

                //添加消息
                if (!$is_robot) {
                    $nick = Member::where('member_id', $user_id)->value('nick');
                }
                else {
                    $nick = RoomRobotList::where('rrl_id', $user_id)->value('rrl_nickname');
                }
                $content = $nick.lang('room_join');
                $flag = RoomMsgList::add_msg($room_info['rl_room_id'], 0, $content);
                if(!$flag) throw new Exception(lang('operation_failed_try_again').'-4');

                //更新房间数据
                $update = [
                    'rl_current_num' => ['inc',1],
                ];
                if ($is_robot) {
                    $update['rl_robot_num'] = ['inc', 1];
                }
                $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
                if(!$roomSave) {
                    throw new Exception("更新失败-1");
                }

                //普通房间进入房间自动准备占位
                $seat_id = self::get_seat($room_info, $user_id);
                if (!$seat_id) {//占位失败
                    throw new Exception(lang('占位失败'));
                }

                $res1 = self::where('rul_id', $id)->update(['rul_seat_id'=>$seat_id]);
                if (!$res1) {
                    throw new Exception(lang('更新失败-3'));
                }

                if (!$is_robot) {
                    //扣除对应资产
                    $currency_user = CurrencyUser::getCurrencyUser($user_id,$room_info['rl_currency_id']);
                    //添加账本
                    $flag = AccountBook::add_accountbook($user_id,$room_info['rl_currency_id'],AccountBookType::GAME_READY,'game_ready','out',$room_info['rl_num'],$id);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again').'-1');

                    $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$room_info['rl_num']);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again').'-2');
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 退出房间
     * @param $room_info
     * @param $user_id
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function exit_room($room_info, $user_id)
    {
        try {
            self::startTrans();

            // 入库
            //if ($room_info['rl_is_vip'] == 1) {//是否是VIP房间 1-VIP房间 2-普通房间
                $userFind = self::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$user_id])->find();
                if ($userFind) {

                    $userSave = self::where('rul_id', $userFind['rul_id'])->update([
                        'rul_seat_id'=>0,
                        'rul_exit_num'=>['inc',1],
                        'rul_status'=>2,
                        'rul_exit_time'=>time(),
                    ]);
                    if(!$userSave) throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);

                    //添加消息
                    $nick = Member::where('member_id', $user_id)->value('nick');
                    $content = $nick.lang('room_exit');
                    $flag = RoomMsgList::add_msg($room_info['rl_room_id'], 0, $content);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again').'-in line:'.__LINE__);

                    //更新房间数据
                    $update = [
                        'rl_current_num' => ['dec',1],
                    ];
                    $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
                    if(!$roomSave) {
                        throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);
                    }
                }
                else {
                    throw new Exception(lang('用户暂未加入该房间'));
                }
            /*}
            else {
                throw new Exception(lang('非VIP房间不能进行退出操作'));
            }*/

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 房间准备
     * @param $room_info
     * @param $user_id
     * @param bool $is_robot
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function room_ready($room_info, $user_id, $is_robot = false)
    {
        try {
            self::startTrans();

            $seat_id = self::get_seat($room_info, $user_id);
            if (!$seat_id) {//占位失败
                throw new Exception(lang('占位失败'));
            }

            // 入库
            if ($room_info['rl_is_vip'] == 1) {//是否是VIP房间 1-VIP房间 2-普通房间
                if ($is_robot) {
                    throw new Exception(lang('VIP房间不允许加入机器人'));
                }
                $userFind = self::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$user_id])->find();
                if ($userFind) {
                    $userSave = self::where('rul_id', $userFind['rul_id'])->update([
                        'rul_seat_id'=>$seat_id,
                        'rul_ready_num'=>['inc',1],
                        //'rul_status'=>1,
                        //'rul_join_time'=>time(),
                    ]);
                    if(!$userSave) throw new Exception("更新失败-2");

                    //扣除对应资产
                    $currency_user = CurrencyUser::getCurrencyUser($user_id,$room_info['rl_currency_id']);
                    //添加账本
                    $flag = AccountBook::add_accountbook($user_id,$room_info['rl_currency_id'],AccountBookType::GAME_READY,'game_ready','out',$room_info['rl_num'],$userFind['rul_id']);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again').'-1');

                    $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$room_info['rl_num']);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again').'-2');
                }
                else {
                    throw new Exception(lang('用户暂无加入该房间'));
                }
            }
            else {
                throw new Exception(lang('非VIP房间不能进行准备操作'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 房间取消准备
     * @param $room_info
     * @param $user_id
     * @param $seat_id
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function room_cancel_ready($room_info, $user_id, $seat_id)
    {
        try {
            self::startTrans();

            // 入库
            //if ($room_info['rl_is_vip'] == 1) {//是否是VIP房间 1-VIP房间 2-普通房间
                $userFind = self::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$user_id])->find();
                if ($userFind) {
                    $seatWhere = [
                        'rsl_room_id'=>$room_info['rl_room_id'],
                        'rsl_seat_id'=>$seat_id,
                    ];
                    $seatSave = RoomSeatList::where($seatWhere)->update([
                        'rsl_member_id'=>0,
                        'rsl_join_time'=>0,
                    ]);
                    if(!$seatSave) throw new Exception("更新失败-1");
                    $userUpdate = [
                        'rul_seat_id'=>0,
                        'rul_cancel_ready_num'=>['inc',1],
                        //'rul_status'=>1,
                        //'rul_join_time'=>time(),
                    ];
                    if ($userFind['rul_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                        //机器人下桌退出房间
                        $userUpdate['rul_exit_num']=['inc',1];
                        $userUpdate['rul_status']=2;
                        $userUpdate['rul_exit_time']=time();
                    }
                    $userSave = self::where('rul_id', $userFind['rul_id'])->update($userUpdate);
                    if(!$userSave) throw new Exception("更新失败-2");

                    if ($userFind['rul_is_robot'] == 1) {//是否是机器人 1-普通用户 2-机器人
                        //扣除对应资产
                        $currency_user = CurrencyUser::getCurrencyUser($user_id,$room_info['rl_currency_id']);
                        //添加账本
                        $flag = AccountBook::add_accountbook($user_id,$room_info['rl_currency_id'],AccountBookType::GAME_CANCEL_READY,'game_cancel_ready','in',$room_info['rl_num'],$userFind['rul_id']);
                        if(!$flag) throw new Exception(lang('operation_failed_try_again').'-1');

                        $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$room_info['rl_num']);
                        if(!$flag) throw new Exception(lang('operation_failed_try_again').'-2');
                    }

                    //更新房间数据
                    $update = [
                        'rl_ready_num' => ['dec',1],
                    ];
                    if ($userFind['rul_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                        $update['rl_robot_num'] = ['dec',1];
                        $update['rl_current_num'] = ['dec',1];//机器人下桌退出房间
                    }
                    $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
                    if(!$roomSave) {
                        throw new Exception("更新失败-1");
                    }
                }
                else {
                    throw new Exception(lang('用户暂无加入该房间'));
                }
            /*}
            else {
                throw new Exception(lang('非VIP房间不能进行准备操作'));
            }*/

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 占位
     * @param $room_info
     * @param $user_id
     * @return mixed
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static public function get_seat($room_info, $user_id)
    {
        $save = RoomSeatList::where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>0])->order('rsl_seat_id')->limit(1)->update(['rsl_member_id'=>$user_id, 'rsl_join_time'=>time(), 'rsl_exit_time'=>0, 'rsl_real_exit_time'=>0]);
        if ($save === false) {
            //throw new Exception(lang('占位失败'));
            return false;
        }
        $find = RoomSeatList::where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>$user_id])->find();
        if (!$find) {
            //throw new Exception(lang('占位失败'));
            return false;
        }
        //更新房间数据
        $update = [
            //'rl_current_num' => ['inc',1],
            'rl_ready_num' => ['inc',1],
        ];
        $find1 = RoomSeatList::where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>0])->find();
        if (!$find1) {
            $result_time = GameConfig::get_value('room_result_time', 30);//房间自动结算时间(秒)
            $update['rl_full_time'] = time();
            $update['rl_result_time'] = time() + $result_time;
            $update['rl_status'] = 2;//待开奖
        }
        $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
        if(!$roomSave) {
            //throw new Exception("更新失败-1");
            return false;
        }
        if (!$find1) {
            if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                $create = RoomList::auto_create_room($room_info);
            }

            //添加消息
            $content = lang('room_lottery');
            $flag = RoomMsgList::add_msg($room_info['rl_room_id'], 0, $content);
        }
        return $find['rsl_seat_id'];
    }
}