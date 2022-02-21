<?php
//跳跃排名倒序加权算法
namespace app\h5\controller;

use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\CurrencyUserTransfer;
use app\common\model\JumpRankingMemberSummary;
use app\common\model\Member;
use app\common\model\MemberBind;
use think\Db;
use think\Exception;
use think\Request;

class JumpRanking extends Base
{
    public function flash() {
        $flash = Db::name('flash')->field('title,pic,jump_url')->where(['type'=>10,'lang'=>$this->lang])->order('flash_id desc')->limit(5)->select();
        if(!$flash) $flash = [];
        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $flash,
        ]);
    }

    public function index() {
        $res = JumpRankingMemberSummary::getList($this->member_id);
        return $this->output_new($res);
    }

    public function info() {
        $currency_id = intval(input('currency_id'));
        $res = JumpRankingMemberSummary::getOne($this->member_id,$currency_id);
        return $this->output_new($res);
    }

    public function income() {
        $currency_id = intval(input('currency_id'));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $type = strval(input('type',''));

        $res = JumpRankingMemberSummary::getIncomeList($this->member_id,$currency_id,$type,$this->lang,$page,$page_size);
        return $this->output_new($res);
    }

    public function team() {
        $currency_id = intval(input('currency_id'));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $child_name = input('child_name', '', 'strval');
        $res = JumpRankingMemberSummary::myTeam($this->member_id,$currency_id,$child_name,$page,$page_size);
        return $this->output_new($res);
    }

    public function myteam(){
        $currency_id = Currency::PUBLIC_CHAIN_ID;
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $child_name = input('child_name', '', 'strval');
        $res = JumpRankingMemberSummary::myTeam($this->member_id,$currency_id,$child_name,$page,$page_size);
        return $this->output_new($res);
    }

    //给自己的直推 批量转账
    public function mul_transfer() {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']= 0;
        $r['err_msg'] = [];
        set_time_limit(0);

        $ids = input('ids/a');
        $top_num = input('top_num',0);
        if(!is_array($ids) || count($ids)>10 || !is_numeric($top_num) || $top_num<=0) return $this->output_new($r);

        $member_ids = [];
        foreach ($ids as $item) {
            $item = intval($item);
            if($item>0) $member_ids[] = $item;
        }

        $success = 0;
        $error_message = [];
        foreach ($member_ids as $child_id) {
            //直推才可批量转
            $member_bind = MemberBind::where(['member_id'=>$this->member_id,'child_id'=>$child_id,'level'=>1])->find();
            if($member_bind) {
                $target = Member::where('member_id', $child_id)->find();
                if($target) {
                    //小于封顶值 补齐
                    $currency_user = CurrencyUser::getCurrencyUser($child_id,Currency::PUBLIC_CHAIN_ID);
                    if($currency_user && $currency_user['num']<$top_num) {
                        $transfer_num = $top_num - $currency_user['num'];

                        $target_account = $target['ename'];
                        $result = CurrencyUserTransfer::transfer($this->member_id,Currency::PUBLIC_CHAIN_ID,$target['member_id'],$target_account,$transfer_num,'num');
                        if($result['code']==SUCCESS) {
                            $success++;
                        } else {
                            $error_message[] = $target_account ." : ". $result['message'];
                        }
                    } else {
                        $error_message[] = $target['ename'] ." : ". lang('currency_user_enough');
                    }
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['err_msg'] = $error_message;
        $r['result'] = $success;
        return $this->output_new($r);
    }
}
