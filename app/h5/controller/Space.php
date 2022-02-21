<?php
//太空计划
namespace app\h5\controller;

use app\common\model\JumpRankingChild;
use app\common\model\SpacePlanPower;
use app\common\model\SpacePlanRecommand;
use app\common\model\SpacePlanRelease;
use think\Db;
use think\Exception;
use think\Request;

class Space extends Base
{
    //总量汇总
    public function all_items_total() {
        $res = \app\common\model\SpacePlan::all_item_total($this->member_id);
        return $this->output_new($res);
    }

    public function all_items() {
        $page = intval(input('post.page'));
        $res = \app\common\model\SpacePlan::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    public function transfer_in() {
        $num = intval(input('num'));
        $pwd = strval(input('pwd'));
        $res = \app\common\model\SpacePlan::addItems($this->member_id,$num,$pwd);
        return $this->output_new($res);
    }

    //复投
    public function item_restart() {
        $space_id = intval(input('space_id'));
        $num = intval(input('num'));
        $pwd = strval(input('pwd'));
        $res = \app\common\model\SpacePlan::itemsRestart($this->member_id,$space_id,$num,$pwd);
        return $this->output_new($res);
    }

    //日燃料
    public function release() {
        $page = intval(input('post.page'));
        $space_id = intval(input('space_id'));
        $res = SpacePlanRelease::getList($this->member_id,$space_id,$page);
        return $this->output_new($res);
    }

    //助力
    public function recommand() {
        $page = intval(input('post.page'));
        $res = SpacePlanRecommand::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    //动力
    public function power() {
        $page = intval(input('post.page'));
        $res = SpacePlanPower::getList($this->member_id,$page);
        return $this->output_new($res);
    }
}
