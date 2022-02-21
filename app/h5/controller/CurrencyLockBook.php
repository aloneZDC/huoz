<?php
//锁仓
namespace app\h5\controller;

use app\common\model\CurrencyNodeLock;
use think\Db;
use think\Exception;
use think\Request;

class CurrencyLockBook extends Base
{
    //总量汇总
    public function node_lock_config() {
        $res = CurrencyNodeLock::move_config($this->member_id);
        return $this->output_new($res);
    }
    //节点锁仓兑换
    public function node_lock_move() {
        $num = intval(input('num'));
        $pwd = strval(input('pwd'));
        $res = CurrencyNodeLock::move($this->member_id,$num,$pwd);
        return $this->output_new($res);
    }

    //节点锁仓兑换记录
    public function node_lock_move_list() {
        $page = intval(input('page'));
        $res = CurrencyNodeLock::move_list($this->member_id,$page);
        return $this->output_new($res);
    }

    public function node_lock_list() {
        $type = strval(input('type','')); //income expend
        $page = intval(input('page'));
        $res = CurrencyNodeLock::node_lock_list($this->member_id,$type,$page);
        return $this->output_new($res);
    }
}
