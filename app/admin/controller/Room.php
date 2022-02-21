<?php

namespace app\admin\controller;

use app\common\model\AccountBook;
use app\common\model\GameConfig;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomRobotList;
use app\common\model\RoomSeatList;
use app\common\model\RoomUsersList;
use app\common\model\RoomUsersRecord;
use app\common\model\RoomWhiteList;
use app\common\model\RoomWhiteSetting;
use think\Db;
use think\Exception;
use think\Request;

class Room extends Admin
{
    protected $typeList = [
        '1'=>'VIP房',
        '2'=>'普通房',
    ];
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 房间列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function index(Request $request)
    {
        $where = [];
        $room_id = input('room_id');
        $rl_is_vip = input('rl_is_vip');
        $rl_status = input('rl_status');
        $rl_is_del = input('rl_is_del');

        if (!empty($room_id)) {
            $where['room_id'] = $room_id;
        }

        if (!empty($rl_is_vip)) {
            $where['rl_is_vip'] = $rl_is_vip;
        }

        if (!empty($rl_status)) {
            $where['rl_status'] = $rl_status;
        }

        if (!empty($rl_is_del)) {
            $where['rl_is_del'] = $rl_is_del;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = RoomList::with(['currency'])
            ->where($where)
            ->order("rl_create_time", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        return $this->fetch('', [
            'list' => $list,
            'room_id' => $room_id,
            'rl_is_vip' => $rl_is_vip,
            'rl_status' => $rl_status,
            'rl_is_del' => $rl_is_del,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => $this->typeList,
            'statusList' => RoomList::STATUS_ENUM,
            'delStatus' => ['1'=>'未删除', '2'=>'已删除'],
        ]);
    }

    /**
     * 用户列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function user_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $room_id = input('room_id');
        $rl_is_vip = input('rl_is_vip');
        $rul_is_robot = input('rul_is_robot');
        $rul_status = input('rul_status');

        if (!empty($member_id)) {
            $where['rul_member_id'] = $member_id;
        }

        if (!empty($room_id)) {
            $where['rul_room_id'] = $room_id;
        }

        if (!empty($rl_is_vip)) {
            $where['rl_is_vip'] = $rl_is_vip;
        }

        if (!empty($rul_is_robot)) {
            $where['rul_is_robot'] = $rul_is_robot;
        }

        if (!empty($rul_status)) {
            $where['rul_status'] = $rul_status;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        //$list = RoomUsersList::with(['room'])->alias('a')->where($where)->field('a.*,b.nick')
        $list = db::name('RoomUsersList')->alias('a')->where($where)->field('a.*,b.nick,c.*,d.rrl_nickname,e.currency_name')
            ->join(config('DB_PREFIX').'member b', 'a.rul_member_id=b.member_id', 'left')
            ->join(config('DB_PREFIX').'room_list c', 'a.rul_room_id=c.rl_room_id', 'left')
            ->join(config('DB_PREFIX').'room_robot_list d', 'a.rul_member_id=d.rrl_id', 'left')
            ->join(config('DB_PREFIX').'currency e', 'c.rl_currency_id=e.currency_id', 'left')
            ->order("rul_id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'room_id' => $room_id,
            'rl_is_vip' => $rl_is_vip,
            'rul_is_robot' => $rul_is_robot,
            'rul_status' => $rul_status,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => $this->typeList,
            'typeList1' => ['1'=>'普通用户', '2'=>'机器人'],
            'typeList2' => ['1'=>'普通玩家', '2'=>'房间创建者'],
            'statusList' => RoomUsersList::STATUS_ENUM,
        ]);
    }

    /**
     * 位置列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function seat_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $room_id = input('room_id');
        $rl_is_vip = input('rl_is_vip');
        $rul_is_robot = input('rul_is_robot');

        if (!empty($member_id)) {
            $where['rsl_member_id'] = $member_id;
        }

        if (!empty($room_id)) {
            $where['rsl_room_id'] = $room_id;
        }

        if (!empty($rl_is_vip)) {
            $where['rl_is_vip'] = $rl_is_vip;
        }

        if (!empty($rul_is_robot)) {
            $where['rul_is_robot'] = $rul_is_robot;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = db::name('RoomSeatList')->alias('a')->where($where)->field('a.*,b.*,c.*,d.nick,e.rrl_nickname,f.currency_name')
            ->join(config('DB_PREFIX').'room_users_list b', 'a.rsl_room_id=b.rul_room_id and a.rsl_member_id=b.rul_member_id', 'left')
            ->join(config('DB_PREFIX').'room_list c', 'a.rsl_room_id=c.rl_room_id', 'left')
            ->join(config('DB_PREFIX').'member d', 'a.rsl_member_id=d.member_id', 'left')
            ->join(config('DB_PREFIX').'room_robot_list e', 'a.rsl_member_id=e.rrl_id', 'left')
            ->join(config('DB_PREFIX').'currency f', 'c.rl_currency_id=f.currency_id', 'left')
            ->order("rsl_id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        //var_dump(db::name('RoomSeatList')->getLastSql());
        $page = $list->render();

        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'room_id' => $room_id,
            'rl_is_vip' => $rl_is_vip,
            'rul_is_robot' => $rul_is_robot,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => $this->typeList,
            'typeList1' => ['1'=>'普通用户', '2'=>'机器人'],
            'typeList2' => ['1'=>'普通玩家', '2'=>'房间创建者'],
        ]);
    }

    /**
     * 战绩列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function record_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $room_id = input('room_id');
        $rl_is_vip = input('rl_is_vip');
        $rur_is_robot = input('rur_is_robot');
        $rur_result = input('rur_result');

        if (!empty($member_id)) {
            $where['rur_member_id'] = $member_id;
        }

        if (!empty($room_id)) {
            $where['rur_room_id'] = $room_id;
        }

        if (!empty($rl_is_vip)) {
            $where['rl_is_vip'] = $rl_is_vip;
        }

        if (!empty($rur_is_robot)) {
            $where['rur_is_robot'] = $rur_is_robot;
        }

        if (!empty($rur_result)) {
            $where['rur_result'] = $rur_result;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = db::name('RoomUsersRecord')->alias('a')->where($where)->field('a.*,b.*,c.*,d.nick,e.rrl_nickname,f.currency_name')
            ->join(config('DB_PREFIX').'room_users_list b', 'a.rur_room_id=b.rul_room_id and a.rur_member_id=b.rul_member_id', 'left')
            ->join(config('DB_PREFIX').'room_list c', 'a.rur_room_id=c.rl_room_id', 'left')
            ->join(config('DB_PREFIX').'member d', 'a.rur_member_id=d.member_id', 'left')
            ->join(config('DB_PREFIX').'room_robot_list e', 'a.rur_member_id=e.rrl_id', 'left')
            ->join(config('DB_PREFIX').'currency f', 'c.rl_currency_id=f.currency_id', 'left')
            ->order("rur_id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        //var_dump(db::name('RoomSeatList')->getLastSql());
        $page = $list->render();

        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'room_id' => $room_id,
            'rl_is_vip' => $rl_is_vip,
            'rur_is_robot' => $rur_is_robot,
            'rur_result' => $rur_result,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => $this->typeList,
            'typeList1' => ['1'=>'普通用户', '2'=>'机器人'],
            'typeList2' => ['1'=>'普通玩家', '2'=>'房间创建者'],
            'resultList' => RoomUsersRecord::RESULT_ENUM,
        ]);
    }

    /**
     * 表单名列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function white_setting(Request $request)
    {
        $where = [];

        $list = Db::name('room_white_setting')
            ->where($where)
            ->order('rws_level', 'asc')
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        return $this->fetch('', compact('list', 'page'));
    }

    /**
     * 添加白名单配置
     * @param Request $request
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add_white_setting(Request $request)
    {
        $rws_level = input('rws_level', 0, 'intval');
        if ($request->isPost()) {
            $data = $request->post();

            $setting = new RoomWhiteSetting();
            $res = $setting->where('rws_level', $rws_level)->update($data);
            if ($res === false) {
                return $this->error('修改失败!请重试');
            }
            return $this->success('修改成功!', url('white_setting'));
        } else {
            $info = Db::name('room_white_setting')->where('rws_level', $rws_level)->find();

            return $this->fetch('', [
                'list' => $info,
            ]);
        }
    }

    /**
     * 白名单列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function white_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $rwl_level_id = input('rwl_level_id');
        $rwl_status = input('rwl_status');

        if (!empty($member_id)) {
            $where['rwl_member_id'] = $member_id;
        }

        if (!empty($rwl_level_id)) {
            $where['rwl_level_id'] = $rwl_level_id;
        }

        if (!empty($rwl_status)) {
            $where['rwl_status'] = $rwl_status;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = db::name('RoomWhiteList')->alias('a')->where($where)->field('a.*,b.nick,c.*')
            ->join(config('DB_PREFIX').'member b', 'a.rwl_member_id=b.member_id', 'left')
            ->join(config('DB_PREFIX').'room_white_setting c', 'a.rwl_level_id=c.rws_level', 'left')
            ->order("rwl_id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $levelSelect = RoomWhiteSetting::order('rws_level', 'asc')->select();
        $levelList = [];
        foreach ($levelSelect as $key => $value) {
            $levelList[$value['rws_level']] = $value['rws_name'];
        }
        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'rwl_level_id' => $rwl_level_id,
            'rwl_status' => $rwl_status,
            'page' => $page,
            'empty' => '暂无数据',
            'levelList' => $levelList,
            'statusList' => ['1'=>'生效中', '2'=>'已删除'],
        ]);
    }

    /**
     * 添加白名单
     * @param Request $request
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add_white(Request $request)
    {
        $rwl_id = input('rwl_id', 0, 'intval');
        if ($request->isPost()) {
            $data = $request->post();

            $list = new RoomWhiteList();
            if (!empty($rwl_id)) {
                unset($data['rwl_member_id']);
                $data['rwl_update_time'] = time();
                $res = $list->where('rwl_id', $rwl_id)->update($data);
            }
            else {
                $data['rwl_update_time'] = time();
                $data['rwl_create_time'] = time();
                $res = $list->insertGetId($data);
            }
            if ($res === false) {
                return $this->error('修改失败!请重试');
            }
            return $this->success('修改成功!', url('white_list'));
        } else {
            if (!empty($rwl_id)) {
                $info = Db::name('room_white_list')->where('rwl_id', $rwl_id)->find();
            }
            else {
                $info = [
                    'rwl_id'=>null,
                    'rwl_member_id'=>null,
                    'rwl_level_id'=>0,
                    'rwl_status'=>0,
                ];
            }

            $levelSelect = RoomWhiteSetting::order('rws_level', 'asc')->select();
            $levelList = [];
            foreach ($levelSelect as $key => $value) {
                $levelList[$value['rws_level']] = $value['rws_name'];
            }
            return $this->fetch('', [
                'list' => $info,
                'levelList' => $levelList,
                'statusList' => ['1'=>'生效中', '2'=>'已删除'],
            ]);
        }
    }

    //配置列表
    public function setting(Request $request)
    {
        $where = [];

        $list = Db::name('room_level_setting')
            ->field('a.*,b.currency_name')
            ->alias('a')
            ->where($where)
            ->order('a.rls_level', 'desc')
            ->join(config('DB_PREFIX').'currency b', 'a.rls_currency_id=b.currency_id')
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $status_list = ['1'=>'能', '2'=>'不能'];
        return $this->fetch('', compact('list', 'page', 'status_list'));
    }

    //添加配置
    public function add_setting(Request $request)
    {
        $rls_level = input('rls_level', 0, 'intval');
        if ($request->isPost()) {
            $data = $request->post();

            $setting = new RoomLevelSetting;
            $res = $setting->where('rls_level', $rls_level)->update($data);
            if ($res === false) {
                return $this->error('修改失败!请重试');
            }
            return $this->success('修改成功!', url('setting'));
        } else {
            $info = [];
            if (!empty($rls_level)) {
                $info = Db::name('room_level_setting')->where(['rls_level' => $rls_level])->find();
            } else {
                // fix PHP7 warning
                $info = [
                    "id" => null,
                    'currency_id' => null,
                    'months' => null,
                    'min_num' => null,
                    'max_num' => null,
                    'rate' => null,
                    'add_time' => null,
                    'cn_title' => null,
                    'en_title' => null,
                    'cn_characteristic' => null,
                    'en_characteristic' => null,
                    'cn_details' => null,
                    'en_details' => null,
                    'type' => null,
                    'days' => null,
                    'day_rate' => null
                ];
            }
            return $this->fetch('', [
                'list' => $info,
                'currency' => Db::name('currency')->select(),
                'status_list' => ['1'=>'能', '2'=>'不能'],
            ]);
        }
    }

    public function del_setting()
    {
        $id = input('id', 0, 'intval');
        if (empty($id)) return successJson(0, lang('lan_Illegal_operation'));
        $model = Db::name('money_interest_config');
        $info = $model->where(['id' => $id])->find();
        if (empty($info)) return successJson(0, lang('lan_Illegal_operation'));

        $result = $model->where(['id' => $id])->delete();
        if (!$result) {
            return successJson(0, lang('lan_network_busy_try_again'));
        } else {
            return successJson(1, lang('lan_operation_success'));
        }
    }

    /**
     * 解散房间
     * @param Request $request
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function disband(Request $request)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if ($request->isPost()) {

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
            if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
                $r['message'] = lang('room_status_error');
                return mobileAjaxReturn($r);
            }

            $r = RoomList::disband($roomInfo, $this->admin['admin_id']);
            return mobileAjaxReturn($r);
        } else {
            return mobileAjaxReturn($r);
        }
    }

    /**
     * 开奖
     * @param Request $request
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lottery(Request $request)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if ($request->isPost()) {

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
            if ($roomInfo['rl_status'] != 2) {//状态 1-未开奖 2-待开奖 3-已开奖
                $r['message'] = lang('room_status_error');
                return mobileAjaxReturn($r);
            }
            if ($roomInfo['rl_is_del'] == 2) {//是否删除 1-未删除 2-已删除
                $r['message'] = lang('room_status_error');
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
            $r = RoomUsersRecord::lottery($roomInfo, $levelInfo, 0);
            return mobileAjaxReturn($r);
        } else {
            return mobileAjaxReturn($r);
        }
    }

    /**
     * 下桌
     * @param Request $request
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cancel_ready(Request $request)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
        if ($request->isPost()) {

            $rsl_id = $request->post('rsl_id');
            $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => []];
            if (empty($rsl_id)) {
                return mobileAjaxReturn($r);
            }
            $seatWhere = [
                'rsl_id'=>$rsl_id,
            ];
            $seatInfo = RoomSeatList::where($seatWhere)->find();
            if (!$seatInfo) {
                $r['message'] = lang('获取位置数据失败');
                return mobileAjaxReturn($r);
            }
            if ($seatInfo['rsl_member_id'] <= 0) {
                $r['message'] = lang('该位置上用户已下桌，请刷新重试');
                return mobileAjaxReturn($r);
            }
            $room_id = $seatInfo['rsl_room_id'];
            $user_id = $seatInfo['rsl_member_id'];
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
                'rul_member_id'=>$user_id,
                'rul_status'=>1,
            ];
            $userFind = RoomUsersList::where($userWhere)->find();
            if (!$userFind) {
                $r['message'] = lang('room_not_join_1');
                return mobileAjaxReturn($r);
            }
            if ($seatInfo['rsl_seat_id'] != $userFind['rul_seat_id']) {
                $userRes = RoomUsersList::where('rul_id', $userFind['rul_id'])->setField('rul_seat_id', $seatInfo['rsl_seat_id']);
            }
            $r = RoomUsersList::room_cancel_ready($roomInfo, $user_id, $seatInfo['rsl_seat_id']);
            return mobileAjaxReturn($r);
        } else {
            return mobileAjaxReturn($r);
        }
    }
}