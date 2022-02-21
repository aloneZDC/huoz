<?php
namespace app\admin\controller;
use app\common\model\MemberBind;
use think\Db;
use think\Request;

class HongbaoNodeAward extends Admin
{
    //奖励列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['user_id'] = $user_id;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['user_id'] = $user['member_id'];
            } else {
                $where['user_id'] = 0;
            }
        }
        $list = \app\common\model\HongbaoNodeAward::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //奖励详情
    public function award_detail(Request $request) {
        $list = [];$page = null;
        $today = $request->get('today');
        $table_name = config("database.prefix").'hongbao_node_award_detail'.$today;
        $table_exist = Db::query('show tables like "'.$table_name.'"');
        $total_award = 0;
        if(count($table_exist)==1) {
            $where = [];
            $user_id = $request->get('user_id');
            if ($user_id) $where['user_id'] = $user_id;
            $list = Db::table($table_name)->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
            $page = $list->render();
            $total_award = Db::table($table_name)->where($where)->sum('num');
        }
        return $this->fetch(null, compact('list', 'page','total_award','today'));
    }

    //奖励详情--基于用户
    public function award_detail_user(Request $request) {
        $where = [];
        $user_id = $request->get('third_user_id',0,'intval');
        $where['third_user_id'] = $user_id;
        $today = $request->get('today');
        $member_bind = MemberBind::where(['child_id'=>$user_id])->order('level desc')->limit(1000)->select();
        $table_name = config("database.prefix").'hongbao_node_award_detail'.$today;
        $list = Db::table($table_name)->where($where)->order('id desc')->select();
        return $this->fetch(null, compact('list','member_bind','user_id'));
    }

    public function summary(Request $request) {
        $list = Db::name('hongbao_node_award_summary')->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }
}
