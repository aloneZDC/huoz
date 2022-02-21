<?php


namespace app\admin\controller;

use app\common\model\StoresCardLog;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;
use think\response\Json;

class StoresList extends Admin
{
    //线下上级列表
    public function stores_list(Request $request) {
        $where = [];
        $status = $request->get('status');
        if($status) $where['status']  = $status;

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

        $count = \app\common\model\StoresList::where($where)->count();
        $list = \app\common\model\StoresList::where($where)->with(['users'])->order('add_time desc')->paginate(null, $count, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page', 'count','status'));
    }

    //线下商家审核
    public function stores_audit() {
        $id = intval(input('id'));
        $status = intval(input('status'));
        $flag = \app\common\model\StoresList::where(['user_id'=>$id])->setField('status',$status);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'审核失败']);
        } else {

            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'审核成功']);
        }
    }

    //卡包列表 I券
    public function card_log_list(Request $request) {
        $type_list = [
            'convert' => lang('convert'),
            'transfer_in' => lang('asset_transfer_in'),
            'transfer_out' => lang('asset_transfer_out'),
            'transfer_financial' => lang('asset_out'),
            'shop' => lang('shopping'),
        ];

        $where = [];

        $type = $request->get('type');
        if($type) $where['type']  = $type;

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
        $count = \app\common\model\StoresCardLog::where($where)->count();
        $list = \app\common\model\StoresCardLog::where($where)->with(['users'])->order('create_time desc')->paginate(null, $count, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                $value['title'] = isset($type_list[$value['type']]) ? $type_list[$value['type']] : '';
                $value['currency_name'] = lang('uc_card');
                $value['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page', 'count','status','type_list'));
    }

    //理财包列表 O券
    public function financial_log_list(Request $request) {
        $type_list = [
            'transfer_financial' => lang('asset_out'),
            'release' => lang('financial_release'),
            'recommand_award' => lang('asset_transfer_award'),
            'shop' => lang('shopping'),
            'transfer_in' => lang('asset_transfer_in'),
            'transfer_out' => lang('asset_transfer_out'),
        ];

        $where = [];

        $type = $request->get('type');
        if($type) $where['type']  = $type;

        $user_id = $request->get('user_id');
        if ($user_id) $where['user_id'] = $user_id;

        $third_id = $request->get('third_id');
        if($third_id) $where['third_id'] = $third_id;

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
        $count = \app\common\model\StoresFinancialLog::where($where)->count();
        $list = \app\common\model\StoresFinancialLog::where($where)->with(['users'])->order('create_time desc')->paginate(null, $count, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                $value['title'] = isset($type_list[$value['type']]) ? $type_list[$value['type']] : '';
                $value['currency_name'] = lang('uc_card');
                $value['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page', 'count','status','type_list'));
    }

    public function financial_award(Request $request) {
        $type_list = [
            'transfer_financial' => lang('asset_out'),
            'release' => lang('financial_release'),
            'recommand_award' => lang('asset_transfer_award'),
            'shop' => lang('shopping'),
            'transfer_in' => lang('asset_transfer_in'),
            'transfer_out' => lang('asset_transfer_out'),
        ];

        $where = [];
        $where['third_id'] = intval($request->get('third_id'));
        $count = \app\common\model\StoresFinancialLog::where($where)->count();
        $list = \app\common\model\StoresFinancialLog::where($where)->with(['users'])->order('create_time desc')->paginate(null, $count, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                $value['title'] = isset($type_list[$value['type']]) ? $type_list[$value['type']] : '';
                $value['currency_name'] = lang('uc_card');
                $value['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page', 'count','status','type_list'));
    }
}