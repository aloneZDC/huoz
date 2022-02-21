<?php
//涡轮增加
namespace app\api\controller;

use think\Db;
use think\Exception;
use think\Request;

class FilMining extends Base
{
    //矿机支付类型
    public function pay_type() {
        $type = input('type',1,'intval');
        $res = \app\common\model\FilMining::payType($this->member_id,$type);
        return $this->output_new($res);
    }

    //配置
    public function config() {
        $res = \app\common\model\FilMining::config($this->member_id);
        return $this->output_new($res);
    }

    //入金
    public function buy() {
        $num = intval(input('num'));
        $pay_id = intval(input('pay_id'));
        $res =  \app\common\model\FilMining::buy($this->member_id,$num,$pay_id);
        return $this->output_new($res);
    }

    public function release_list() {
        $page = intval(input('page'));
        $file_mining_id = intval(input('file_mining_id'));

        $res =  \app\common\model\FilMiningRelease::getList($this->member_id,$file_mining_id,$page);
        return $this->output_new($res);
    }

    public function income_list() {
        $page = intval(input('page'));

        $type = intval(input('type'));
        if($type==1) {
            $res =  \app\common\model\FilMiningRelease::getList($this->member_id,0,$page);
        } elseif($type==2) {
            $res =  \app\common\model\FilMiningIncome::getList($this->member_id,[15],$page);
        } elseif($type==3) {
            $res =  \app\common\model\FilMiningIncome::getList($this->member_id,[4],$page);
        } elseif($type==5) {
            $res =  \app\common\model\FilMiningIncome::getList($this->member_id,[5],$page);
        } else {
            $res =  \app\common\model\FilMiningIncome::getList($this->member_id,[11,12,13,16],$page);
        }
        return $this->output_new($res);
    }

    // 线性释放统计
    public function lock_count() {
        $FilMining = \app\common\model\FilMining::where(['member_id'=>$this->member_id])->find();
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = [
            'lock_num'=>$FilMining['lock_num'], // 产出剩余锁仓
            'total_lock_num'=>$FilMining['total_lock_num'], // 产出已释放数量
            'total_release15'=>$FilMining['total_release15'], // 小区剩余锁仓
            'total_thaw15'=>$FilMining['total_thaw15'], //  小区已释放数量
        ];
        $this->output_new($r);
    }

    // 冻结详情
    public function lock_detail()
    {
        $page = intval(input('page'));
        $type = intval(input('type'));
        if ($type == 1) {
            // 产出释放详情
            $res = \app\common\model\CurrencyLockBook::get_list($this->member_id, 'fil_lock_num', '', '', $page);
        } else {
            // 团队产出释放详情
            $res = \app\common\model\CurrencyLockBook::get_list($this->member_id, 'fil_area_lock_num', '', '', $page);
        }
        $this->output_new($res);
    }

    public function currency_num() {
        $res = \app\common\model\FilMiningIncome::getLockNum($this->member_id);
        return $this->output_new($res);
    }

    public function pay_list() {
        $page = intval(input('page'));
        $res =  \app\common\model\FilMiningPay::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    //我的团队
    public function my_team() {
        $page = intval(input('page'));
        $res = \app\common\model\FilMining::myTeam($this->member_id,$page);
        return $this->output_new($res);
    }

    public function support_currency() {
        $res =  \app\common\model\FilMining::releaseSupportCurrency();
        return $this->output_new($res);
    }

    public function change_currency() {
        $fil_mining_id = intval(input('fil_mining_id'));
        $currency_id = intval(input('currency_id'));
        $res =  \app\common\model\FilMining::changeReleaseCurrency($this->member_id,$fil_mining_id,$currency_id);
        return $this->output_new($res);
    }
}
