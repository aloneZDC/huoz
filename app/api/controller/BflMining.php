<?php
//BFL矿机
namespace app\api\controller;

use think\Db;

class BflMining extends Base
{
    //租赁矿机
    public function buy()
    {
        $currency_id = intval(input('currency_id'));
        $num = intval(input('num'));
        $res = \app\common\model\BflMining::buy($this->member_id,$currency_id,$num);
        return $this->output_new($res);
    }

    //增加质押
    public function supply() {
        $mining_id = intval(input('mining_id'));
        $num = intval(input('num'));
        $res = \app\common\model\BflMining::supply($this->member_id,$mining_id,$num);
        return $this->output_new($res);
    }

    //撤销矿机
    public function cancel() {
        $mining_id = intval(input('mining_id'));
        $res = \app\common\model\BflMining::cancel($this->member_id,$mining_id);
        return $this->output_new($res);
    }

    //所有矿机
    public function all() {
        $page = intval(input('page'));
        $res = \app\common\model\BflMining::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    //矿机配置表
    public function config() {
        $res = \app\common\model\BflMining::config($this->member_id);
        return $this->output_new($res);
    }
}
