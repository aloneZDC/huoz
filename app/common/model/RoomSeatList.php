<?php
//房间用户列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomSeatList extends Model
{
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'rsl_member_id', 'member_id')/*->field('user_nickname,user_email,user_phone')*/;
    }

    static public function get_seat($room_info)
    {
        $field = 'rsl_id,rsl_member_id,rsl_seat_id,rsl_exit_time';
        $seatSelect = self::field($field)->where(['rsl_room_id'=>$room_info['rl_room_id']/*, 'rsl_member_id'=>['gt', 0]*/])->order('rsl_seat_id', 'ASC')->select();
        if (count($seatSelect)) {
            $now = time();
            foreach ($seatSelect as $key => $value) {
                $seat = $value;
                if ($seat['rsl_member_id'] > 0) {
                    if ($seat['rsl_exit_time'] <= $now && $seat['rsl_exit_time'] > 0) {//自动下桌
                        $seatWhere = [
                            'rsl_id'=>$value['rsl_id'],
                        ];
                        $seatSave = RoomSeatList::where($seatWhere)->update(['rsl_member_id'=>0, 'rsl_real_exit_time'=>$now]);
                        if ($seatSave) {
                            $seat['rsl_member_id'] = 0;
                        }
                    }
                    $userFind = RoomUsersList::where(['rul_room_id'=>$room_info['rl_room_id'], 'rul_member_id'=>$seat['rsl_member_id']])->find();
                    if ($userFind['rul_is_robot'] == 1) {//是否是机器人 1-普通用户 2-机器人
                        $user = Member::where('member_id', $seat['rsl_member_id'])->field('nick')->find();
                        $seat['nick'] = $user['nick'];
                        //$seat['user_email'] = $user['user_email'];
                        //$seat['user_phone'] = $user['user_phone'];
                    }
                    else {
                        $robotInfo = RoomRobotList::where('rrl_id', $seat['rsl_member_id'])->find();
                        $seat['nick'] = $robotInfo['rrl_nickname'];
                    }
                }
                else {
                    $seat['nick'] = '';
                }
                $seatList[] = $seat;
            }
        }
        return $seatSelect;
    }
}