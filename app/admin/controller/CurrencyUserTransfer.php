<?php


namespace app\admin\controller;

use app\common\model\HongbaoLog;
use think\Exception;
use think\Request;

class CurrencyUserTransfer extends Admin
{
    protected $type_list = [
        'num' => '可用',
        'game_lock' => 'io券',
        'uc_card' => 'i券',
        'uc_card_lock' => 'o券',
        'dnc_lock' => 'DNC锁仓',
        'keep_num' => 'xrp+赠送金'
    ];

    //线下上级列表
    public function index(Request $request) {
        $where = [];

        $user_id = $request->get('user_id');
        if ($user_id) $where['cut_user_id'] = $user_id;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['cut_user_id'] = $user['member_id'];
            } else {
                $where['cut_user_id'] = 0;
            }
        }

        $target_user_id = $request->get('target_user_id');
        if ($target_user_id) $where['cut_target_user_id'] = $target_user_id;


        $list = \app\common\model\CurrencyUserTransfer::with(['users','currency','targetusers'])->where($where)->order('cut_id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $type_list = $this->type_list;
        return $this->fetch(null, compact('list', 'page','type_list'));
    }
}
