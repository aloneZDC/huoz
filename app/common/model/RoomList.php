<?php
//房间列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomList extends Model
{
    /**
     * 状态枚举
     * @var array
     */
    const STATUS_ENUM = [
        1 => "未开奖",
        2 => "待开奖",
        3 => "已开奖",
    ];

    public function getStatusTextAttr($value,$data)
    {
        return STATUS_ENUM[$data['rl_status']];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'rl_currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    public function level()
    {
        return $this->belongsTo(RoomLevelSetting::class, 'rl_level_id', 'rls_level')->field('rls_level, rls_num');
    }

    /**
     * 自动创建房间
     * @param $room_info
     * @return bool
     * @throws Exception
     */
    static public function auto_create_room($room_info)
    {
        try {
            self::startTrans();

            $room_robot_time = GameConfig::get_value('room_robot_time', 600);
            $room_disband_time = GameConfig::get_value('room_disband_time', 3600);
            $robot_default_min = GameConfig::get_value('room_robot_default_min', 1);
            $robot_default_max = GameConfig::get_value('room_robot_default_max', 2);
            $res = self::create([
                'rl_level_id'=>$room_info['rl_level_id'],
                'rl_currency_id'=>$room_info['rl_currency_id'],
                'rl_num'=>$room_info['rl_num'],
                'rl_open_num'=>$room_info['rl_open_num'],
                'rl_max_num'=>$room_info['rl_max_num'],
                'rl_robot_time'=>(time() + $room_robot_time),
                'rl_disband_time'=>(time() + $room_disband_time),
                'rl_create_time'=>time(),
            ]);
            if (empty($res)) {
                throw new Exception(lang('插入记录失败-1'));
                //return false;
            }
            $room_id = $res->getLastInsID();
            $seatList = [];
            for ($i = 1; $i <= 10; $i ++) {
                $seatList[] = [
                    'rsl_room_id'=>$room_id,
                    'rsl_seat_id'=>$i,
                ];
            }
            $roomSeat = new RoomSeatList;
            $res1 = $roomSeat->saveAll($seatList);
            if (empty($res1)) {
                throw new Exception(lang('插入记录失败-2'));
                //return false;
            }
            $roomInfo = self::get($room_id);
            //加入2-5个机器人
            $robotNum = mt_rand($robot_default_min, $robot_default_max);
            $robotList = RoomRobotList::select();
            for ($i = 1; $i <= $robotNum; $i++) {
                $robotKey = array_rand($robotList);
                $robot = $robotList[$robotKey];
                $r = RoomUsersList::join_room($roomInfo, $robot['rrl_id'], true);
                if ($r['code'] != SUCCESS) {
                    Log::write("房间机器人加入异常:" . $r['message'], 'INFO');
                    throw new Exception(lang('房间机器人加入异常') . $r['message']);
                    //return false;
                }
                else {
                    unset($robotList[$robotKey]);
                }
            }

            self::commit();
            $r['result'] = $roomInfo;
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        }
        catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 创建VIP房间
     * @param $level_info
     * @param $user_id
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function create_vip($level_info, $user_id)
    {
        try {
            self::startTrans();

            $room_number = self::get_room_number();
            $room = self::create([
                'rl_room_number'=>$room_number,
                'rl_level_id'=>$level_info['rls_level'],
                'rl_is_vip'=>1,
                'rl_creator_id'=>$user_id,
                'rl_currency_id'=>$level_info['rls_currency_id'],
                'rl_num'=>$level_info['rls_num'],
                'rl_hour_fee'=>$level_info['rls_hour_fee'],
                'rl_current_num'=>1,
                'rl_open_num'=>10,
                'rl_max_num'=>30,
                'rl_overtime_num'=>1,
                'rl_disband_time'=>time() + 3600,
                'rl_create_time'=>time(),
            ]);
            if (empty($room)) {
                throw new Exception(lang('插入记录失败-1'));
            }

            $seatList = [];
            for ($i = 1; $i <= 10; $i ++) {
                $seatList[] = [
                    'rsl_room_id'=>$room['rl_room_id'],
                    'rsl_seat_id'=>$i,
                ];
            }
            $roomSeat = new RoomSeatList;
            $res1 = $roomSeat->saveAll($seatList);
            if (empty($res1)) {
                throw new Exception(lang('插入记录失败-2'));
            }

            /*$seat_id = RoomUsersList::get_seat($room, $user_id);
            if (!$seat_id) {//占位失败
                throw new Exception(lang('占位失败'));
            }*/

            $user = RoomUsersList::create([
                'rul_room_id'=>$room['rl_room_id'],
                'rul_member_id'=>$user_id,
                //'rul_seat_id'=>$seat_id,
                'rul_is_creator'=>2,
                'rul_join_num'=>1,
                'rul_join_time'=>time(),
            ]);
            if (empty($user)) {
                throw new Exception(lang('插入记录失败-3'));
            }

            //添加消息
            $nick = Member::where('member_id', $user_id)->value('nick');
            $content = $nick.lang('room_join');
            $flag = RoomMsgList::add_msg($room['rl_room_id'], 0, $content);
            if(!$flag) throw new Exception(lang('operation_failed_try_again').'-3');

            /*// 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($user_id, $level_info['rls_currency_id']);

            // 账本
            $flag = AccountBook::add_accountbook($user_id, $level_info['rls_currency_id'], AccountBookType::VIP_HOUR_FEE, 'vip_hour_fee', 'out', $level_info['rls_hour_fee'], $room['rl_room_id']);
            if ($flag === false) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setDec('num',$level_info['rls_hour_fee']);
            if ($flag === false) {
                throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
            }*/

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            $r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 解散房间--目前只做VIP房间解散
     * @param $room_info
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function disband($room_info, $rl_disband_admin_id = 0)
    {
        try {
            self::startTrans();

            /*if ($room_info['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                throw new Exception(lang('只有VIP房间才能解散'));
            }*/

            if (!$rl_disband_admin_id) {
                $seatList = RoomSeatList::where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>['gt',0]])->order('rsl_seat_id', 'asc')->select();
                if (count($seatList) >= $room_info['rl_open_num']) {
                    Log::write("房间解散失败，房间人数已满，当前正在结算中", 'INFO');
                    throw new Exception(lang('房间解散失败，房间人数已满，当前正在结算中'));
                }
            }
            else {
                //管理后台解散房间，直接解散，不管房间状态
            }

            $userList = RoomUsersList::where(['rul_room_id' => $room_info['rl_room_id'], 'rul_status' => 1])->select();
            foreach ($userList as $key => $value) {
                $update = [
                    'rul_exit_num'=>['inc',1],
                    'rul_status'=>2,
                    'rul_exit_time'=>time(),
                ];
                $seatFind = RoomSeatList::where(['rsl_room_id'=>$room_info['rl_room_id'], 'rsl_member_id'=>$value['rul_member_id']])->find();
                if ($seatFind) {
                    $update['rul_seat_id'] = 0;
                    $update['rul_cancel_ready_num'] = ['inc',1];
                }
                else {
                    if ($value['rul_seat_id'] > 0) {
                        $update['rul_seat_id'] = 0;
                    }
                }
                $userSave = RoomUsersList::where(['rul_id' => $value['rul_id']])->update($update);
                if(!$userSave) {
                    throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);
                }

                if ($seatFind) {
                    $seatSave = RoomSeatList::where(['rsl_id'=>$seatFind['rsl_id']])->update([
                        'rsl_member_id'=>0,
                        'rsl_join_time'=>0,
                    ]);
                    if(!$seatSave){
                        throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);
                    }
                }

                if ($value['rul_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    continue;
                } else {
                    if ($seatFind) {//退还已上桌用户资产
                        // 保存用户资产
                        $usersCurrency = CurrencyUser::getCurrencyUser($value['rul_member_id'], $room_info['rl_currency_id']);

                        // 账本
                        $flag = AccountBook::add_accountbook($value['rul_member_id'], $room_info['rl_currency_id'], AccountBookType::ROOM_DISBAND, 'room_disband', 'in', $room_info['rl_num'], $room_info['rl_room_id']);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later'));
                        }

                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setInc('num',$room_info['rl_num']);
                        if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                }
            }

            //更新房间数据
            $update = [
                'rl_current_num' => 0,
                'rl_ready_num' => 0,
                'rl_is_del' => 2,
                'rl_real_disband_time' => time(),
                'rl_disband_admin_id' => $rl_disband_admin_id,
            ];
            $roomSave = RoomList::where(['rl_room_id'=>$room_info['rl_room_id']])->update($update);
            if(!$roomSave) {
                throw new Exception(lang("更新记录失败").'-in line:'.__LINE__);
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
     * 获取VIP房间编号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static private function get_room_number()
    {
        $room_number = randNum(6);
        if (self::where(['rl_room_number'=>$room_number, 'rl_is_del'=>1])->find()) {
            return self::get_room_number();
        }
        return $room_number;
    }
}