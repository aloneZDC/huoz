<?php


namespace app\backend\controller;

use think\Db;
use think\Request;

class Reward extends AdminQuick
{
    protected $pid = 'member_id';
    protected $public_action = ['ark_edit', 'ark_profit_list'];

    public function index(Request $request) {
        $member_id = input('member_id');
        $level = input('level');
        $ename = input('ename');
        $phone = input('phone');
        $where = [];
        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }
        if (!empty($level)) {
            $where['level'] = $level;
        }
        if($phone) {
            $user = \app\common\model\Member::where(['phone'=>$phone])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $list = \app\common\model\RocketMember::with(['users', 'rocketLevel'])->where($where)->order('member_id desc')->paginate(null, false, ['query' => $request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $subscribe_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 102])->sum('num');
                $value['subscribe_num'] = $subscribe_num ? $subscribe_num : 0;
            }
        }
        $page = $list->render();
        $levels = \app\common\model\RocketLevel::select();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'levels', 'count'));
    }

    public function edit(Request $request) {
        if($this->request->isPost()) {
            $id = intval(input('id'));

            $level = input('level');
            $is_centre = input('is_centre');
            $is_area = input('is_area');
            $info = \app\common\model\RocketMember::where(['member_id'=>$id])->find();
            if(empty($info)) return $this->successJson(ERROR1,"该记录不存在",null);

            $result = \app\common\model\RocketMember::where(['member_id'=>$id])->update(['level' => $level, 'is_centre' => $is_centre, 'is_area' => $is_area]);
            if(false === $result){
                return $this->successJson(ERROR1,"操作失败",null);
            } else {
                $data = [
                    'member_id' => $id,
                    'level' => $info['level'],
                    'new_level' => $level,
                    'add_time' => time(),
                    'remark' => 0,
                    'is_centre' => $is_centre,
                    'is_area' => $is_area
                ];
                Db::name('rocket_level_log')->insert($data);
                return $this->successJson(SUCCESS,"操作成功",['url'=>url('')]);
            }
        } else {
            $id = intval(input('id'));
            $levels = \app\common\model\RocketLevel::select();
            $info = \app\common\model\RocketMember::where(['member_id'=>$id])->find();
            return $this->fetch(null,compact('info','levels'));
        }
    }

    public function profit_list(Request $request) {
        $member_id = input('member_id');
        $type = input('type');
        $where = [];
        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        $list = \app\common\model\RocketRewardLog::with(['users'])
            ->where($where)->order('member_id desc')->paginate(null, false, ['query' => $request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                if ($value['type'] == 1) {
                    $value['name'] = '静态收益';
                } elseif ($value['type'] == 2) {
                    $value['name'] = '分享收益';
                } else {
                    $value['name'] = '团队收益';
                }
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    public function ark_list(Request $request) {
        $member_id = input('member_id');
        $level = input('level');
        $ename = input('ename');
        $phone = input('phone');
        $where = [];
        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }
        if (!empty($level)) {
            $where['level'] = $level;
        }
        if($phone) {
            $user = \app\common\model\Member::where(['phone'=>$phone])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $list = \app\common\model\ArkMember::with(['users', 'rocketLevel'])->where($where)->order('member_id desc')->paginate(null, false, ['query' => $request->get()]);
//        if ($list) {
//            foreach ($list as &$value) {
//                $share_integral = \app\common\model\RocketRewardLog::where(['member_id' => $value['member_id'], 'status' => 1])->sum('pers_num');
//                $value['share_integral'] = $share_integral ?: 0;
//                $reward = \app\common\model\RocketRewardLog::where(['member_id' => $value['member_id'], 'status' => 1])->order('overdue_time asc')->value('surplus_reward');
//                $value['reward'] = $reward ?: 0;
//            }
//        }
        $page = $list->render();
        $levels = \app\common\model\ArkLevel::select();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'levels', 'count'));
    }

    public function ark_edit(Request $request) {
        if($this->request->isPost()) {
            $id = intval(input('id'));
            $is_centre = input('is_centre');
            $level = input('level');
            $info = \app\common\model\ArkMember::where(['member_id'=>$id])->find();
            if(empty($info)) return $this->successJson(ERROR1,"该记录不存在",null);

            $result = \app\common\model\ArkMember::where(['member_id'=>$id])->update(['level' => $level, 'is_centre' => $is_centre]);
            if(false === $result){
                return $this->successJson(ERROR1,"操作失败",null);
            } else {
                $data = [
                    'member_id' => $id,
                    'level' => $info['level'],
                    'new_level' => $level,
                    'add_time' => time(),
                    'remark' => 0,
                    'is_centre' => $is_centre
                ];
                Db::name('ark_level_log')->insert($data);
                return $this->successJson(SUCCESS,"操作成功",['url'=>url('')]);
            }
        } else {
            $id = intval(input('id'));
            $levels = \app\common\model\ArkLevel::select();
            $info = \app\common\model\ArkMember::where(['member_id'=>$id])->find();
            return $this->fetch(null,compact('info','levels'));
        }
    }

    public function ark_profit_list(Request $request) {
        $member_id = input('member_id');
        $type = input('type');
        $where = [];
        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        $list = \app\common\model\ArkRewardLog::with(['users'])
            ->where($where)->order('member_id desc')->paginate(null, false, ['query' => $request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                if ($value['type'] == 1) {
                    $value['name'] = '静态收益';
                } elseif ($value['type'] == 2) {
                    $value['name'] = '分享收益';
                } else {
                    $value['name'] = '团队收益';
                }
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}