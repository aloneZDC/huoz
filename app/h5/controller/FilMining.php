<?php
//涡轮增加
namespace app\h5\controller;

use think\Db;
use think\Exception;
use think\Request;

class FilMining extends Base
{
    //配置
    public function my_level() {
        $res = \app\common\model\FilMining::my_level($this->member_id);
        return $this->output_new($res);
    }

    //我的团队
    public function my_team() {
        $page = intval(input('page'));
        $res = \app\common\model\FilMining::myTeam($this->member_id,$page);
        return $this->output_new($res);
    }
}
