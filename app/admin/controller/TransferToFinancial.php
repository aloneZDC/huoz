<?php
namespace app\admin\controller;

use app\common\model\TransferFinancialAward;
use app\common\model\TransferToAsset;
use app\common\model\UsersVotes;
use app\common\model\UsersVotesConfig;
use think\Request;

class TransferToFinancial extends Admin {
    //资产包记录
    public function transfer_to_asset(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['tta_user_id'] = $user_id;

        $tta_asset_type = input('tta_asset_type','asset');
        $where['tta_asset_type'] = $tta_asset_type;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['tta_user_id'] = $user['member_id'];
            } else {
                $where['tta_user_id'] = 0;
            }
        }
        $list = TransferToAsset::with('users,tousers,currency,tocurrency')->where($where)->order('tta_id desc')->paginate(null, false, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                if ($value['tta_type'] == 'in') {
                    $value['title'] = lang('asset_in');
                } elseif ($value['tta_type'] == 'out') {
                    $value['title'] = lang('asset_out');
                } elseif ($value['tta_type'] == 'transfer_in') {
                    $value['title'] = lang('asset_transfer_in');
                } elseif ($value['tta_type'] == 'transfer_out') {
                    $value['title'] = lang('asset_transfer_out');
                } elseif ($value['tta_type'] == 'award') {
                    $value['title'] = lang('asset_transfer_award');
                }
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page','tta_asset_type'));
    }

    //理财宝记录
    public function transfer_to_financial(Request $request){
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['ttf_user_id'] = $user_id;

        $ttf_asset_type = input('ttf_asset_type','asset');
        $where['ttf_asset_type'] = $ttf_asset_type;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['ttf_user_id'] = $user['member_id'];
            } else {
                $where['ttf_user_id'] = 0;
            }
        }
        $list = \app\common\model\TransferToFinancial::with('users,currency')->where($where)->order('ttf_id desc')->paginate(null, false, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                if ($value['ttf_type'] == 'in') {
                    $value['title'] = lang('financial_in');
                } elseif ($value['ttf_type'] == 'out') {
                    $value['title'] = lang('financial_out');
                } elseif ($value['ttf_type']=='award') {
                    $value['title'] = lang('financial_award');
                }
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page','ttf_asset_type'));
    }

    //资产包配置
    public function transfer_to_asset_config() {

    }

    //理财包奖励
    public function transfer_financial_award(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['tta_user_id'] = $user_id;

        $tta_asset_type = input('tta_asset_type','asset');
        $where['tta_asset_type'] = $tta_asset_type;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['tta_user_id'] = $user['member_id'];
            } else {
                $where['tta_user_id'] = 0;
            }
        }
        $list = TransferFinancialAward::with('users,currency')->where($where)->order('tta_id desc')->paginate(null, false, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                if ($value['ttf_type'] == 'in') {
                    $value['title'] = lang('financial_in');
                } elseif ($value['ttf_type'] == 'out') {
                    $value['title'] = lang('financial_out');
                } elseif ($value['ttf_type']=='award') {
                    $value['title'] = lang('financial_award');
                }
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page','tta_asset_type'));
    }

    //理财包奖励详情
    public function transfer_financial_award_detail() {

    }
}