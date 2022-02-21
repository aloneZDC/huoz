<?php

namespace app\h5\controller;

use app\common\model\Accountbook;
use app\common\model\Article;
use app\common\model\CurrencyUser;
use app\common\model\GameConfig;
use app\common\model\PublicMsgList;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomMsgList;
use app\common\model\RoomQueueList;
use app\common\model\RoomRobotList;
use app\common\model\RoomSeatList;
use app\common\model\RoomUsersList;
use app\common\model\RoomUsersRecord;
use app\common\model\RoomWhiteList;
use app\common\model\Member;
use app\common\model\UsersVotes;
use think\Cache;
use think\Db;
use think\Exception;
use think\Request;
use think\response\Json;

class Room extends Base
{
    protected $is_decrypt = false; //不验证签名

    /**
     * 获取房间列表
     */
    public function get_room_list(Request $request)
    {
        $level = $request->post('level');
        $type = $request->post('type');//房间类型 1-普通 2-VIP
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($level) || empty($type)) {
            return mobileAjaxReturn($r);
        }

        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $levelInfo['rls_currency_id']);
        if ($type == 1) {//房间类型 1-普通 2-VIP
            if ($levelInfo['rls_num'] > $usersCurrency['num']) {
                $r['message'] = lang('insufficient_balance');
                return mobileAjaxReturn($r);
            }
        }
        else {
            if ($levelInfo['rls_is_vip'] != 1) {//不允许创建VIP房间
                $r['message'] = lang('room_cannot_vip');
                return mobileAjaxReturn($r);
            }
            if ($levelInfo['rls_hour_fee'] > $usersCurrency['num']) {
                $r['message'] = lang('insufficient_balance');
                return mobileAjaxReturn($r);
            }
            $r['message'] = lang('room_vip_not_list');
            return mobileAjaxReturn($r);
        }
        $where = [
            'rl_level_id'=>$level,
            'rl_is_vip'=>2,
            'rl_is_del'=>1,
        ];
        $field = 'rl_room_id,rl_current_num,rl_open_num,rl_status';
        $list = RoomList::where($where)->field($field)->order('rl_create_time ASC')->select();
        foreach ($list as $key => &$value) {
            $value['rl_status_txt'] = RoomList::STATUS_ENUM[$value['rl_status']];
            $value['rl_is_join'] = $find = RoomUsersList::where(['rul_room_id'=>$value['rl_room_id'], 'rul_member_id'=>$this->member_id])->find() ? '1' : '2';//是否进入房间 1-已进入 2-未进入
            $value['rl_result_second'] = 0;
            $value['rl_disband_second'] = 0;
            if ($value['rl_status'] == 2) {//状态 1-未开奖 2-待开奖 3-已开奖
                $value['rl_result_second'] = $value['rl_result_time'] - time() >=0 ? : 0;
            }
            else if ($value['rl_status'] == 3) {
                $value['rl_disband_second'] = $value['rl_disband_time'] - time() >=0 ? : 0;
            }
        }
        //var_dump($list);
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return mobileAjaxReturn($r);
    }

    public function join_now_test(Request $request)
    {
        $level = $request->post('level');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($level)) {
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($levelInfo['rls_is_common'] == 2) {//能否创建普通房 1-是 2-否
            $r['message'] = lang('该等级无法进入普通房间');
            return mobileAjaxReturn($r);
        }

        $userList = [88926,88927,88928,88929,88930,88931,88932,88933];
        //$userList = [88933,88934];
        foreach ($userList as $key => $value) {
            $userId = $value;
            $usersCurrency = CurrencyUser::getCurrencyUser($userId, $levelInfo['rls_currency_id']);
            if ($levelInfo['rls_num'] > $usersCurrency['num']) {
                var_dump(1);
                continue;
            }
            $userWhere = [
                'rul_member_id'=>$userId,
                'rul_status'=>1,
                //'rl_level_id'=>$level,
                //'rl_is_vip'=>1,
                'rl_is_del'=>1,
            ];
            $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
            if ($userFind) {
                var_dump(2);
                continue;
            }
            $where = [
                'rl_level_id'=>$level,
                'rl_is_vip'=>2,
                'rl_status'=>1,
                'rl_is_del'=>1,
            ];
            $roomFind = RoomList::where($where)->order('rl_room_id DESC')->find();
            if (!$roomFind) {
                $roomInfo = [
                    'rl_level_id'=>$level,
                    'rl_currency_id'=>$levelInfo['rls_currency_id'],
                    'rl_num'=>$levelInfo['rls_num'],
                    'rl_open_num'=>$levelInfo['rls_open_num'],
                    'rl_max_num'=>$levelInfo['rls_open_num'],
                ];
                $r = RoomList::auto_create_room($roomInfo);
                //var_dump($r);
                if ($r['code'] != SUCCESS) {
                    $r['message'] = lang('room_no_room').'-1';
                    return mobileAjaxReturn($r);
                }
                $roomFind = $r['result'];
            }
            if ($roomFind['rl_current_num'] >= $roomFind['rl_open_num']) {
                $r['message'] = lang('room_number_full');
                return mobileAjaxReturn($r);
            }
            $find = RoomUsersList::where(['rul_room_id'=>$roomFind['rl_room_id'], 'rul_member_id'=>$userId, 'rul_status'=>1])->find();
            if ($find) {
                var_dump(3);
                continue;
            }
            $whiteFind = RoomWhiteList::with(['level'])->where(['rwl_member_id'=>$userId, 'rwl_status'=>1])->find();
            if (!$whiteFind) {
                if ($whiteFind['rwl_last_win_time'] < todayBeginTimestamp()) {
                    RoomWhiteList::where(['rwl_id'=>$whiteFind['rwl_id']])->setField('rwl_today_win_num', 0);
                }
                if ($whiteFind['level']['rws_win_num'] > $whiteFind['rwl_today_win_num'] || $whiteFind['level']['rws_win_num'] == 0) {
                    $userList = RoomUsersList::where(['rul_room_id'=>$roomFind['rl_room_id'], 'rul_is_robot'=>1, 'rul_status'=>1])->select();
                    foreach ($userList as $key => $value) {
                        $whiteFind1 = RoomWhiteList::where(['rwl_member_id'=>$userId, 'rwl_status'=>1])->find();
                        if ($whiteFind1) {
                            var_dump(4);
                            continue;
                        }
                    }
                }
            }
            $r1 = RoomUsersList::join_room($roomFind, $userId);
            $r1['result'] = ['user_id'=>$userId];
            if ($r1['code'] != SUCCESS) {
                var_dump(5);
                continue;
            }
            $r1['result']['room_id'] = $roomFind['rl_room_id'];
            $r['result'][] = $r1;
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('successful_operation');
        return mobileAjaxReturn($r);
    }

    /**
     * 立即进入
     */
    public function join_now(Request $request)
    {
        //$this->member_id = 88931;
        $level = $request->post('level');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($level)) {
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($levelInfo['rls_is_common'] == 2) {//能否创建普通房 1-是 2-否
            $r['message'] = lang('该等级无法进入普通房间');
            return mobileAjaxReturn($r);
        }

        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $levelInfo['rls_currency_id']);
        if ($levelInfo['rls_num'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return mobileAjaxReturn($r);
        }
        $userWhere = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            //'rl_level_id'=>$level,
            //'rl_is_vip'=>1,
            'rl_is_del'=>1,
        ];
        $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        if ($userFind) {
            $r['message'] = lang('room_already_join');
            return mobileAjaxReturn($r);
        }
        $where = [
            'rl_level_id'=>$level,
            'rl_is_vip'=>2,
            'rl_status'=>1,
            'rl_is_del'=>1,
        ];
        $roomFind = RoomList::where($where)->order('rl_room_id DESC')->find();
        if (!$roomFind) {
            $roomInfo = [
                'rl_level_id'=>$level,
                'rl_currency_id'=>$levelInfo['rls_currency_id'],
                'rl_num'=>$levelInfo['rls_num'],
                'rl_open_num'=>$levelInfo['rls_open_num'],
                'rl_max_num'=>$levelInfo['rls_open_num'],
            ];
            $r = RoomList::auto_create_room($roomInfo);
            //var_dump($r);
            if ($r['code'] != SUCCESS) {
                $r['message'] = lang('room_no_room').'-1';
                return mobileAjaxReturn($r);
            }
            $roomFind = $r['result'];
        }
        if ($roomFind['rl_current_num'] >= $roomFind['rl_open_num']) {
            $r['message'] = lang('room_number_full');
            return mobileAjaxReturn($r);
        }
        $find = RoomUsersList::where(['rul_room_id'=>$roomFind['rl_room_id'], 'rul_member_id'=>$this->member_id, 'rul_status'=>1])->find();
        if ($find) {
            $r['message'] = lang('room_already_join');
            return mobileAjaxReturn($r);
        }
        $whiteFind = RoomWhiteList::with(['level'])->where(['rwl_member_id'=>$this->member_id, 'rwl_status'=>1])->find();
        if (!$whiteFind) {
            if ($whiteFind['rwl_last_win_time'] < todayBeginTimestamp()) {
                RoomWhiteList::where(['rwl_id'=>$whiteFind['rwl_id']])->setField('rwl_today_win_num', 0);
            }
            if ($whiteFind['level']['rws_win_num'] > $whiteFind['rwl_today_win_num'] || $whiteFind['level']['rws_win_num'] == 0) {
                $userList = RoomUsersList::where(['rul_room_id'=>$roomFind['rl_room_id'], 'rul_is_robot'=>1, 'rul_status'=>1])->select();
                foreach ($userList as $key => $value) {
                    $whiteFind1 = RoomWhiteList::where(['rwl_member_id'=>$this->member_id, 'rwl_status'=>1])->find();
                    if ($whiteFind1) {
                        $r['message'] = lang('room_no_room');
                        return mobileAjaxReturn($r);
                    }
                }
            }
        }
        $r = RoomUsersList::join_room($roomFind, $this->member_id);
        $r['result'] = ['room_id'=>$roomFind['rl_room_id']];
        return mobileAjaxReturn($r);
    }

    /**
     * 创建房间
     */
    public function create_room(Request $request)
    {
        $level = $request->post('level');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($level)) {
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($levelInfo['rls_is_vip'] == 2) {//能否创建VIP房 1-是 2-否
            $r['message'] = lang('room_cannot_vip');
            return mobileAjaxReturn($r);
        }

        //创建VIP不扣除房费
        /*$usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $levelInfo['rls_currency_id']);
        if ($levelInfo['rls_hour_fee'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return mobileAjaxReturn($r);
        }*/
        //判断用户是否是VIP，即是否投票
        $flag = UsersVotes::check_create_vip_room($this->member_id);
        if (!$flag) {
            $r['message'] = lang('room_isnot_vip');
            return mobileAjaxReturn($r);
        }
        $where = [
            'rl_level_id'=>$level,
            'rl_is_vip'=>1,
            'rl_creator_id'=>$this->member_id,
            //'rl_status'=>1,
            'rl_is_del'=>1,
        ];
        $roomFind = RoomList::where($where)->find();
        if ($roomFind) {
            $r['message'] = lang('room_already_create');
            return mobileAjaxReturn($r);
        }
        $userWhere = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            //'rl_level_id'=>$level,
            //'rl_is_vip'=>1,
            'rl_is_del'=>1,
        ];
        $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        if ($userFind) {
            $r['message'] = lang('room_already_join1');
            return mobileAjaxReturn($r);
        }
        $r = RoomList::create_vip($levelInfo, $this->member_id);
        return mobileAjaxReturn($r);
    }

    public function join_test(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where(['rl_room_number'=>$room_id, 'rl_is_del'=>1])->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_current_num'] >= $roomInfo['rl_max_num']) {
            $r['message'] = lang('room_number_full');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }

        $userList = [88926,88927,88928,88929,88930,88931,88932,88933,88934,88935];
        foreach ($userList as $key => $value) {
            $userId = $value;
            $usersCurrency = CurrencyUser::getCurrencyUser($userId, $roomInfo['rl_currency_id']);
            if ($roomInfo['rl_num'] > $usersCurrency['num']) {
                continue;
            }
            $where = [
                //'rl_level_id'=>$level,
                'rl_is_vip'=>1,
                'rl_creator_id'=>$userId,
                //'rl_status'=>1,
                'rl_is_del'=>1,
            ];
            $roomFind = RoomList::where($where)->find();
            if ($roomFind) {
                continue;
            }
            $userWhere = [
                'rul_member_id'=>$userId,
                'rul_status'=>1,
                //'rl_level_id'=>$level,
                //'rl_is_vip'=>1,
                'rl_is_del'=>1,
            ];
            $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
            if ($userFind) {
                continue;
            }
            $r = RoomUsersList::join_room($roomInfo, $userId);
            $r['result'] = ['room_id'=>$roomInfo['rl_room_id']];
            if ($r['code'] != SUCCESS) {
                continue;
            }
            $userWhere1 = [
                'rul_room_id'=>$roomInfo['rl_room_id'],
                'rul_member_id'=>$userId,
                'rul_status'=>1,
            ];
            $userFind1 = RoomUsersList::where($userWhere1)->find();
            if (!$userFind1) {
                var_dump(1);
                continue;
            }
            $seatWhere = [
                'rsl_room_id'=>$roomInfo['rl_room_id'],
                'rsl_member_id'=>$userId,
            ];
            $seatFind = RoomSeatList::where($seatWhere)->find();
            if ($seatFind) {
                if ($seatFind['rsl_seat_id'] != $userFind1['rul_seat_id']) {
                    $userRes = RoomUsersList::where('rul_id', $userFind1['rul_id'])->setField('rul_seat_id', $seatFind['rsl_seat_id']);
                }
                var_dump(2);
                continue;
            }
            $r = RoomUsersList::room_ready($roomInfo, $userId);
            if ($r['code'] != SUCCESS) {
                var_dump(3);
                continue;
            }
        }
        return mobileAjaxReturn($r);
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
            return mobileAjaxReturn($r);
        }
        /*$levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }*/
        //$roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        $roomInfo = RoomList::where(['rl_room_number'=>$room_id, 'rl_is_del'=>1])->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_current_num'] >= $roomInfo['rl_max_num']) {
            $r['message'] = lang('room_number_full');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }

        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $roomInfo['rl_currency_id']);
        if ($roomInfo['rl_num'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return mobileAjaxReturn($r);
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
            $r['message'] = lang('room_already_join');
            return mobileAjaxReturn($r);
        }
        $userWhere = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            //'rl_level_id'=>$level,
            //'rl_is_vip'=>1,
            'rl_is_del'=>1,
        ];
        $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        if ($userFind) {
            $r['message'] = lang('room_already_join');
            return mobileAjaxReturn($r);
        }
        $r = RoomUsersList::join_room($roomInfo, $this->member_id);
        $r['result'] = ['room_id'=>$roomInfo['rl_room_id']];
        return mobileAjaxReturn($r);
    }

    /**
     * 退出房间
     */
    public function exit_room(Request $request)
    {
        //$this->member_id = 88930;
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        /*if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }*/
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_creator_id'] == $this->member_id) {
            $r['message'] = lang('room_is_creator');
            return mobileAjaxReturn($r);
        }

        $userWhere = [
            'rul_room_id'=>$room_id,
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
        ];
        //$userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        $userFind = RoomUsersList::where($userWhere)->find();
        if (!$userFind) {
            $r['message'] = lang('room_not_join_1');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            //普通房间加入房间之后一定时间之后可以退出房间
            //room_exit_time
            $exit_time = GameConfig::get_value('room_exit_time', 60);//加入房间之后可以退出房间的时间(秒)
            if ($userFind['rul_join_time'] + $exit_time > time()) {
                $r['message'] = lang('room_not_exit_time');
                return mobileAjaxReturn($r);
            }
            $seatWhere = [
                'rsl_room_id'=>$room_id,
                'rsl_member_id'=>$this->member_id,
            ];
            $seatFind = RoomSeatList::where($seatWhere)->find();
            if ($seatFind) {//已准备，需要先取消准备
                //普通房间退出前先取消准备
                $r = RoomUsersList::room_cancel_ready($roomInfo, $this->member_id, $seatFind['rsl_seat_id']);
                if ($r['code'] != SUCCESS) {
                    return mobileAjaxReturn($r);
                }
            }
        }
        else {
            $seatWhere = [
                'rsl_room_id'=>$room_id,
                'rsl_member_id'=>$this->member_id,
            ];
            $seatFind = RoomSeatList::where($seatWhere)->find();
            if ($seatFind) {
                $r['message'] = lang('room_unable_exit');
                return mobileAjaxReturn($r);
            }
        }
        $r = RoomUsersList::exit_room($roomInfo, $this->member_id);
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间信息
     */
    public function get_room_info(Request $request)
    {
        //$a=array("red","green","blue","yellow","brown");
        //$random_keys=array_rand($a);
        //var_dump($random_keys);
        $where = [
            'rl_status'=>1,
            //'rl_current_num'=>['exp', '<rl_open_num'],
            //'rl_robot_time'=>['elt', time()],
        ];
        //$r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        //return mobileAjaxReturn($r);
        //$room = RoomList::where($where)->order('rl_room_id', 'asc')->find();
        //var_dump(RoomList::getLastSql());
        //var_dump($room['rl_room_id']);
        //$level = $request->post('level');
        //$type = $request->post('type');//房间类型 1-普通 2-VIP
        $room_id = $request->post('room_id');

        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $field = 'rl_room_id,rl_room_number,rl_creator_id,rl_current_num,rl_open_num,rl_status,rl_level_id,rl_currency_id,rl_is_vip,rl_result_time,rl_disband_time';
        /*if ($type == 1) {//房间类型 1-普通 2-VIP
            //普通房时传房间id，获取该房间信息
            if (empty($room_id)) {
                return mobileAjaxReturn($r);
            }
            $roomInfo = RoomList::with(['level', 'currency'])->where('rl_room_id', $room_id)->field($field)->find();
            if (empty($roomInfo)) {
                $r['message'] = lang('no_data');
                return mobileAjaxReturn($r);
            }
        }
        else {*/
            //VIP房时，传等级id，获取该等级下当前在的房间信息
            /*$levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $level)->find();
            if (empty($levelInfo)) {
                $r['message'] = lang('no_data');
                return mobileAjaxReturn($r);
            }
            if ($levelInfo['rls_is_vip'] != 1) {//不允许创建VIP房间
                $r['message'] = lang('room_cannot_vip');
                return mobileAjaxReturn($r);
            }*/
            $userWhere = [
                'rul_member_id'=>$this->member_id,
                'rul_status'=>1,
                'rl_room_id'=>$room_id,
                //'rl_level_id'=>$level,
                //'rl_is_vip'=>1,
                'rl_is_del'=>1,
            ];
            $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
            if (!$userFind) {
                $r['message'] = lang('room_not_join');
                return mobileAjaxReturn($r);
            }
            //$room_id = $userFind['rl_room_id'];
            $roomInfo = RoomList::with(['level', 'currency'])->where('rl_room_id', $room_id)->field($field)->find();
            if (empty($roomInfo)) {
                $r['message'] = lang('no_data');
                return mobileAjaxReturn($r);
            }
        //}
        $seatList = RoomSeatList::get_seat($roomInfo);
        /*$seatSelect = RoomSeatList::where(['rsl_room_id'=>$room_id, 'rsl_member_id'=>['gt',0]])->order('rsl_seat_id', 'asc')->select();
        $seatFind = false;
        if (count($seatSelect)) {
            foreach ($seatSelect as $key => $value) {
                if ($value['rsl_member_id'] == $this->member_id) {
                    $seatFind = $value;
                }
                $seat = $value;
                if ($value['rsl_member_id'] > 0) {
                    $userFind = RoomUsersList::where(['rul_room_id'=>$roomInfo['rl_room_id'], 'rul_member_id'=>$value['rsl_member_id']])->find();
                    if ($userFind['rul_is_robot'] == 1) {//是否是机器人 1-普通用户 2-机器人
                        $user = Member::where('member_id', $value['rsl_member_id'])->field('nick')->find();
                        $seat['nick'] = $user['nick'];
                    }
                    else {
                        $robotInfo = RoomRobotList::where('rrl_id', $value['rsl_member_id'])->find();
                        $seat['nick'] = $robotInfo['rrl_nickname'];
                    }
                }
                else {
                    $seat['nick'] = '';
                }
                $seat['rsl_join_time'] = date('Y-m-d H:i:s', $seat['rsl_join_time']);
                $seatList[] = $seat;
            }
        }*/
        $queueList = [];
        /*$queueSelect = RoomQueueList::where(['rql_room_id'=>$room_id, 'rql_status'=>1])->order('rql_id', 'asc')->select();
        if (count($queueSelect)) {
            foreach ($queueSelect as $key => $value) {
                $user = Member::where('member_id', $this->member_id)->field('nick')->find();
                $queue = $value;
                $queue['rql_create_time'] = date('Y-m-d H:i:s', $queue['rql_create_time']);
                $queue['rql_status_txt'] = RoomQueueList::STATUS_ENUM[$queue['rql_status']];
                $queue['nick'] = $user['nick'];
                $queueList[] = $queue;
            }
        }*/
        $roomInfo['rl_status_txt'] = RoomList::STATUS_ENUM[$roomInfo['rl_status']];
        $roomInfo['rl_is_ready'] = 2;//是否准备 1-已准备 2-未准备
        $seatFind = RoomSeatList::where(['rsl_room_id'=>$roomInfo['rl_room_id'], 'rsl_member_id'=>$this->member_id])->find();
        $roomInfo['rl_result_second'] = 0;
        $roomInfo['rl_result_second1'] = GameConfig::get_value('room_result_time', 30);//房间自动结算时间(秒)
        $roomInfo['rl_disband_second'] = 0;
        $roomInfo['rl_cancel_ready_second'] = 0;
        $roomInfo['rl_num'] = intval($roomInfo['level']['rls_num']);
        $roomInfo['rl_currency'] = $roomInfo['currency']['currency_name'];
        $roomInfo['rl_share'] = '';
        if ($roomInfo['rl_is_vip'] == 1) {//是否是VIP房间 1-VIP房间 2-普通房间
            $roomInfo['rl_share'] = lang('room_share1', [$roomInfo['rl_num'].$roomInfo['rl_currency'], $roomInfo['rl_room_number'], base64_encode('user@'.$this->member_id)]);
        }
        unset($roomInfo['level']);
        unset($roomInfo['currency']);
        if ($seatFind) {
            $roomInfo['rl_is_ready'] = 1;//是否准备 1-已准备 2-未准备
            $ready_time = GameConfig::get_value('room_ready_time', 300);//房间准备之后可以取消准备的时间(秒)
            $roomInfo['rl_cancel_ready_second'] = $seatFind['rsl_join_time'] + $ready_time - time() >= 0 ? ($seatFind['rsl_join_time'] + $ready_time - time()) : 0;
        }
        if ($roomInfo['rl_status'] == 2) {//状态 1-未开奖 2-待开奖 3-已开奖
            $roomInfo['rl_result_second'] = $roomInfo['rl_result_time'] - time() >= 0 ? ($roomInfo['rl_result_time'] - time()) : 0;
        }
        else if ($roomInfo['rl_status'] == 3) {
            $roomInfo['rl_disband_second'] = $roomInfo['rl_disband_time'] - time() >= 0 ? ($roomInfo['rl_disband_time'] - time()) : 0;
        }
        unset($roomInfo['rl_result_time']);
        unset($roomInfo['rl_disband_time']);
        $roomInfo['seat_list'] = $seatList;
        $roomInfo['queue_list'] = $queueList;
        $r['result'] = $roomInfo;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        return mobileAjaxReturn($r);
    }

    /**
     * 开奖
     */
    public function lottery(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('非VIP房间无法手动开奖');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_status'] != 2) {//状态 1-未开奖 2-待开奖 3-已开奖
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_creator_id'] != $this->member_id) {
            $r['message'] = lang('room_not_creator');
            return mobileAjaxReturn($r);
        }
        $where = [
            'rsl_room_id'=>$room_id,
            'rsl_member_id'=>0,
        ];
        $seatFind = RoomSeatList::where($where)->find();
        if ($seatFind) {
            $r['message'] = lang('room_not_full');
            return mobileAjaxReturn($r);
        }
        $r = RoomUsersRecord::lottery($roomInfo, $levelInfo, $this->member_id);
        if ($r['code'] == SUCCESS) {
            /*$room = [
                'status'=>3,//状态 1-未开奖 2-待开奖 3-已开奖
                'part_num'=>$roomInfo['rl_result_num'] + 1,
                'result_list'=>RoomUsersRecord::get_result($roomInfo),
            ];*/
            $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
            $myResult = RoomUsersRecord::with(['currency'])->where(['rur_room_id'=>$roomInfo['rl_room_id'], 'rur_part_num'=>$roomInfo['rl_result_num'], 'rur_member_id'=>$this->member_id])->find();
            $my_result = 0;//结果 0-未参与 1-赢 2-输
            $num = 0;
            $currency = '';
            if ($myResult) {
                $my_result = $myResult['rur_result'];
                $num = number_format($myResult['rur_actual_money'],2,".","");
                $currency = $myResult['currency']['currency_name'];
                if ($my_result == 2) {//输
                    $num = number_format($myResult['rur_lock_num'],2,".","");
                    $currency = lang('votes_score_lock');//券
                }
            }
            $room = [
                'lose_seat'=>RoomUsersRecord::where(['rur_room_id'=>$roomInfo['rl_room_id'], 'rur_part_num'=>$roomInfo['rl_result_num'], 'rur_result'=>2])->value('rur_seat_id'),
                'my_result'=>$my_result,
                'num'=>abs($num),
                'currency'=>$currency,
            ];
            $r['result'] = $room;
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间开奖数据
     */
    public function get_room_result1(Request $request)
    {
        //$this->member_id = 88930;
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_status'] != 3) {//状态 1-未开奖 2-待开奖 3-已开奖
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        /*if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }*/
        $myResult = RoomUsersRecord::with(['currency'])->where(['rur_room_id'=>$roomInfo['rl_room_id'], 'rur_part_num'=>$roomInfo['rl_result_num'], 'rur_member_id'=>$this->member_id])->find();
        $my_result = 0;//结果 0-未参与 1-赢 2-输
        $num = 0;
        $currency = '';
        if ($myResult) {
            $my_result = $myResult['rur_result'];
            $num = number_format($myResult['rur_actual_money'],2,".","");
            $currency = $myResult['currency']['currency_name'];
            if ($my_result == 2) {//输
                $num = number_format($myResult['rur_lock_num'],2,".","");
                $currency = lang('votes_score_lock');//券
            }
        }
        $room = [
            'lose_seat'=>RoomUsersRecord::where(['rur_room_id'=>$roomInfo['rl_room_id'], 'rur_part_num'=>$roomInfo['rl_result_num'], 'rur_result'=>2])->value('rur_seat_id'),
            'my_result'=>$my_result,
            'num'=>abs($num),
            'currency'=>$currency,
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $room;
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间结算数据
     */
    public function get_room_result(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_status'] != 3) {//状态 1-未开奖 2-待开奖 3-已开奖
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        /*if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }*/
        $room = [
            'status'=>$roomInfo['rl_status'],//状态 1-未开奖 2-待开奖 3-已开奖
            'part_num'=>$roomInfo['rl_result_num'],
            'result_list'=>RoomUsersRecord::get_result($roomInfo),
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $room;
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间位置数据
     */
    public function get_room_seat(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        $seatList = RoomSeatList::get_seat($roomInfo);
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'seat_list'=>$seatList,
            'status'=>$roomInfo['rl_status'],//状态 1-未开奖 2-待开奖 3-已开奖
            'is_disband'=>$roomInfo['rl_is_del'],//是否解散 1-未解散 2-已解散
        ];
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间消息
     */
    public function get_room_message(Request $request)
    {
        $room_id = $request->post('room_id');
        $page = $request->post('page', 1);
        $length = $request->post('length', 10);
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }

        $start = ($page - 1) * $length;
        $field = 'rml_id,rml_room_id,rml_member_id,rml_content,from_unixtime(rml_create_time, \'%Y-%m-%d %H:%i:%S\') as rml_create_time';
        $where = [
            'rml_room_id'=>$room_id,
        ];
        $data = RoomMsgList::field($field)->where($where)->limit($start, $length)->order('rml_id', 'DESC')->select();
        if (empty($data)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('no_data'), 'result' => null]);
        }
        $msg_list = [];
        foreach ($data as $key => $value) {
            $msg = $value;
            if ($msg['rml_member_id'] > 0) {//是否是机器人 1-普通用户 2-机器人
                $user = Member::where('member_id', $value['rml_member_id'])->field('nick')->find();
                $nick = $user['nick'];
            }
            else {
                $nick = '系统消息';
            }
            $msg['nick'] = $nick;
            $msg['rml_content'] = userTextDecode($msg['rml_content']);
            $msg_list[] = $msg;
        }
        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $msg_list]);
    }

    /**
     * VIP房间加时
     */
    public function overtime(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        return mobileAjaxReturn($r);
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_creator_id'] != $this->member_id) {
            $r['message'] = lang('room_not_creator');
            return mobileAjaxReturn($r);
        }
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $levelInfo['rls_currency_id']);
        if ($levelInfo['rls_hour_fee'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return mobileAjaxReturn($r);
        }
        try {
            Db::startTrans();

            // 账本
            $flag = Accountbook::add_accountbook($this->member_id, $levelInfo['rls_currency_id'], AccountBookType::VIP_HOUR_FEE, 'vip_hour_fee', 'out', $levelInfo['rls_hour_fee'], $roomInfo['rl_room_id']);
            if ($flag === false) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 保存用户资产
            $usersCurrency['num'] -= $roomInfo['rls_hour_fee'];
            if (!$usersCurrency->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 更新房间信息
            $update = [
                'rl_overtime_num' => ['inc',1],
                'rl_disband_time' => ['inc',3600],
            ];
            $roomSave = RoomList::where(['rl_room_id'=>$room_id])->update($update);
            if(!$roomSave) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            Db::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 房间解散
     */
    public function disband(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_creator_id'] != $this->member_id) {
            $r['message'] = lang('room_not_creator');
            return mobileAjaxReturn($r);
        }

        $r = RoomList::disband($roomInfo);
        return mobileAjaxReturn($r);
    }

    /**
     * 准备
     * 即占座
     */
    public function ready(Request $request)
    {
        //$this->member_id = 88934;
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_status'] == 2) {//状态 1-未开奖 2-待开奖 3-已开奖
            $r['message'] = lang('room_lottery_ing');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_ready_num'] >= $roomInfo['rl_open_num']) {
            $r['message'] = lang('room_seat_full');
            return mobileAjaxReturn($r);
        }

        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $levelInfo['rls_currency_id']);
        if ($levelInfo['rls_num'] > $usersCurrency['num']) {
            $r['message'] = lang('insufficient_balance');
            return mobileAjaxReturn($r);
        }

        $userWhere = [
            'rul_room_id'=>$room_id,
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
        ];
        //$userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        $userFind = RoomUsersList::where($userWhere)->find();
        if (!$userFind) {
            $r['message'] = lang('room_not_join_1');
            return mobileAjaxReturn($r);
        }
        $seatWhere = [
            'rsl_room_id'=>$room_id,
            'rsl_member_id'=>$this->member_id,
        ];
        $seatFind = RoomSeatList::where($seatWhere)->find();
        if ($seatFind) {
            if ($seatFind['rsl_seat_id'] != $userFind['rul_seat_id']) {
                $userRes = RoomUsersList::where('rul_id', $userFind['rul_id'])->setField('rul_seat_id', $seatFind['rsl_seat_id']);
            }
            $r['message'] = lang('room_already_ready');
            return mobileAjaxReturn($r);
        }
        $r = RoomUsersList::room_ready($roomInfo, $this->member_id);
        return mobileAjaxReturn($r);
    }

    /**
     * 取消准备
     * 即下桌
     */
    public function cancel_ready(Request $request)
    {
        //$this->member_id = 88930;
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_status'] == 2) {//状态 1-未开奖 2-待开奖 3-已开奖
            $r['message'] = lang('room_lottery_ing');
            return mobileAjaxReturn($r);
        }

        $userWhere = [
            'rul_room_id'=>$room_id,
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
        ];
        //$userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        $userFind = RoomUsersList::where($userWhere)->find();
        if (!$userFind) {
            $r['message'] = lang('room_not_join_1');
            return mobileAjaxReturn($r);
        }
        $seatWhere = [
            'rsl_room_id'=>$room_id,
            'rsl_member_id'=>$this->member_id,
        ];
        $seatFind = RoomSeatList::where($seatWhere)->find();
        if (!$seatFind) {
            $r['message'] = lang('room_not_ready');
            return mobileAjaxReturn($r);
        }
        if ($seatFind['rsl_seat_id'] != $userFind['rul_seat_id']) {
            $userRes = RoomUsersList::where('rul_id', $userFind['rul_id'])->setField('rul_seat_id', $seatFind['rsl_seat_id']);
        }
        $ready_time = GameConfig::get_value('room_ready_time', 300);//房间准备之后可以取消准备的时间(秒)
        if ($seatFind['rsl_join_time'] + $ready_time > time()) {
            $r['message'] = lang('room_not_time');
            return mobileAjaxReturn($r);
        }
        $r = RoomUsersList::room_cancel_ready($roomInfo, $this->member_id, $seatFind['rsl_seat_id']);
        return mobileAjaxReturn($r);
    }

    /**
     * 聊天
     */
    public function chat(Request $request)
    {
        $room_id = $request->post('room_id');
        $content = $request->post('content');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($content) || empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }

        $flag = RoomMsgList::add_msg($roomInfo['rl_room_id'], $this->member_id, $content);
        if(!$flag) {
            $r['message'] = lang('operation_failed_try_again');
            return mobileAjaxReturn($r);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('successful_operation');
        return mobileAjaxReturn($r);
    }

    /**
     * 分享房间
     */
    public function share(Request $request)
    {
        $room_id = $request->post('room_id');
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if (empty($room_id)) {
            return mobileAjaxReturn($r);
        }
        $roomInfo = RoomList::where('rl_room_id', $room_id)->find();
        if (empty($roomInfo) || $roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_not_exit');
            return mobileAjaxReturn($r);
        }
        $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $roomInfo['rl_level_id'])->find();
        if (empty($levelInfo)) {
            $r['message'] = lang('no_data');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
            $r['message'] = lang('room_not_vip');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
            $r['message'] = lang('room_status_error');
            return mobileAjaxReturn($r);
        }
        if ($roomInfo['rl_creator_id'] != $this->member_id) {
            $r['message'] = lang('room_not_creator');
            return mobileAjaxReturn($r);
        }

        $content = lang('room_share2', [intval($levelInfo['rls_num']), $levelInfo['currency']['currency_name'], $roomInfo['rl_room_number']]);
        $flag = PublicMsgList::add_msg($roomInfo['rl_room_number'], $this->member_id, $content);
        if(!$flag) {
            $r['message'] = lang('operation_failed_try_again');
            return mobileAjaxReturn($r);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('successful_operation');
        return mobileAjaxReturn($r);
    }

    /**
     * 获取快捷语
     */
    public function get_shortcut(Request $request)
    {
        $list = [
            ['cotent'=>lang('room_shortcut_1'),],
            ['cotent'=>lang('room_shortcut_2'),],
            ['cotent'=>lang('room_shortcut_3'),],
            ['cotent'=>lang('room_shortcut_4'),],
            ['cotent'=>lang('room_shortcut_5'),],
            ['cotent'=>lang('room_shortcut_6'),],
            ['cotent'=>lang('room_shortcut_7'),],
            //['cotent'=>lang('room_shortcut_8'),],
            //['cotent'=>lang('room_shortcut_9'),],
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return mobileAjaxReturn($r);
    }

    public function test()
    {
        RoomRobotList::create_robot(500);
        /*$list1 = RoomRobotList::randomMobile(100);
        foreach ($list1 as $key => &$value) {
            $value = substr($value,0,3).'****'.substr($value,-4);
        }
        var_dump($list1);
        $list2 = RoomRobotList::randomEmail(100);
        foreach ($list2 as $key => &$value) {
            $value = substr($value,0,3).'****'.substr($value,-7);
        }
        var_dump($list2);*/
    }
}