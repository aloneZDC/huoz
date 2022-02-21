<?php
namespace app\api\controller;

use app\common\model\CurrencyUser;
use app\common\model\GameConfig;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomSeatList;
use app\common\model\RoomUsersList;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;

class Room extends Base
{
    /**
     * 获取房间是否存在
     */
    public function get_room_exist(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return $this->output_new($r);
        }
        //$roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        $roomInfo = RoomList::where(['rl_room_number'=>$room_id, 'rl_is_del'=>1])->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return $this->output_new($r);
        }
        $r['result'] = null;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        return $this->output_new($r);
    }
    
    /**
     * 进入房间
     */
    public function join_room(Request $request)
    {
        //$this->member_id = 88934;
        //$level = $request->post('level');
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return $this->output_new($r);
        }
        //$roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        $roomInfo = RoomList::where(['rl_room_number'=>$room_id, 'rl_is_del'=>1])->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return $this->output_new($r);
        }
        if ($roomInfo['rl_current_num'] >= $roomInfo['rl_max_num']) {
            $r['message'] = lang('room_number_full');
            return $this->output_new($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return $this->output_new($r);
        }

        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $roomInfo['rl_currency_id']);
        if ($roomInfo['rl_num'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return $this->output_new($r);
        }
        $where = [
            //'rl_level_id'=>$level,
            'rl_is_vip'=>1,
            'rl_creator_id'=>$this->member_id,
            //'rl_status'=>1,
            'rl_is_del'=>1,
        ];
        $roomFind = RoomList::where($where)->find();
        if ($roomFind) {
            $r['message'] = lang('room_already_join_other');
            return $this->output_new($r);
        }
        $userWhere = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            'rul_room_id'=>$roomInfo['rl_room_id'],
            //'rl_is_vip'=>1,
            'rl_is_del'=>1,
        ];
        $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        if ($userFind) {
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            $r['result'] = ['room_id'=>$userFind['rul_room_id']];
            return $this->output_new($r);
        }
        $userWhere1 = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            //'rl_is_vip'=>1,
            'rl_is_del'=>1,
        ];
        $userFind1 = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere1)->find();
        if ($userFind1) {
            $r['message'] = lang('room_already_join_other');
            return $this->output_new($r);
        }
        $r = RoomUsersList::join_room($roomInfo, $this->member_id);
        $r['result'] = ['room_id'=>$roomInfo['rl_room_id']];
        return $this->output_new($r);
    }
}