<?php


namespace app\admin\controller;


use app\common\model\TransferToBalance;
use think\Request;

class TransferWeight extends Admin
{

    public function index(Request $request)
    {
        $search = input('search/a');
        $is_search = true;
        $where = [];
        if (!empty($search['user_id'])) $where['ucb_user_id'] = $search['user_id'];
        if (!empty($search['ucb_type'])) $where['ucb_type'] = $search['ucb_type'];
        if (!empty($search['ucb_status'])) $where['ucb_status'] = $search['ucb_status'];
        if (!empty($search['ucb_third_id'])) $where['ucb_third_id'] = $search['ucb_third_id'];


        if($is_search) {
            $list = TransferToBalance::with(['user'])->where($where)->order('ucb_id', 'desc')->paginate(null, null, $request->get());
            $page = $list->render();
            $count = $list->total();
        } else {
            $list = [];
            $page = null;
            $count = 0;
        }
        return $this->fetch(null, compact('list', 'page', 'count','search'));


    }
}