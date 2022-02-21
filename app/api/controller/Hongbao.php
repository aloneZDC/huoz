<?php
//投票 俱乐部
namespace app\api\controller;

use app\common\model\Currency;
use app\common\model\DcLockLog;
use app\common\model\HongbaoKeepLog;
use app\common\model\HongbaoLog;
use app\common\model\HongbaoNodeAward;
use app\common\model\Member;
use think\Db;
use think\Exception;
use think\Request;

class Hongbao extends Base
{
    //卡包
    public function config() {
        $result = HongbaoLog::config($this->member_id);
        $this->output_new($result);
    }

    //红包列表
    public function hb_list() {
        $page = intval(input('page'));
        $result = HongbaoLog::get_list_chai_log($this->member_id,$page);
        $this->output_new($result);
    }

    public function add_hb() {
        $num = intval(input('num'));
        $result = HongbaoLog::add_log($this->member_id,$num);
        $this->output_new($result);
    }

    public function add_super_hb() {
        $num = intval(input('num'));

        //验证支付密码
        $pwd = input('post.pwd');
        $password = Member::verifyPaypwd($this->member_id, $pwd);
        if ($password['code'] != SUCCESS) return $this->output_new($password);

        $result = HongbaoLog::add_super_log($this->member_id,$num);
        $this->output_new($result);
    }

    //拆红包
    public function open_hb() {
        $hongbao_id = intval(input('hongbao_id'));
        $result = HongbaoLog::open_hongbao($this->member_id,$hongbao_id);
        $this->output_new($result);
    }

    public function keep_log_num() {
        $res1 = HongbaoKeepLog::num($this->member_id);
        $res2 = DcLockLog::num($this->member_id);
        $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => [$res2, $res1]
        ]);
    }

    public function keep_log(){
        $income_type = input('income_type','strval,trim');
        $page = intval(input("post.page"));
        $currencyId = input('post.currency_id', Currency::XRP_PLUS_ID, 'intval');
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (empty($currencyId)) {
            $this->output_new($r);
        }
        if (Currency::XRP_PLUS_ID == $currencyId) {
            $r = HongbaoKeepLog::get_list($this->member_id, $income_type, $page);
        } elseif (Currency::DNC_ID == $currencyId) {
            $r = DcLockLog::get_list($this->member_id, $income_type, $page);
        }


        $this->output_new($r);
    }

    //节点奖励
    public function node_award() {
        $page = intval(input("post.page"));
        $result = HongbaoNodeAward::get_list($this->member_id,$page);
        $this->output_new($result);
    }

    public function node_award_info() {
        $result = HongbaoNodeAward::award_level_info($this->member_id);
        $this->output_new($result);
    }
}
