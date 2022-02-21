<?php

namespace app\h5\controller;

/**
 * Chia矿机
 * Class ChiaMining
 * @package app\h5\controller
 */
class ChiaMining extends Base
{
    // 我的团队
    public function my_team()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ChiaMiningPay::myTeam($this->member_id, $page);
        $this->output_new($res);
    }

    // 获取团队等级
    public function getteamlevel()
    {
        $res = \app\common\model\ChiaMiningLevelConfig::getTeamLevel($this->member_id);
        $this->output_new($res);
    }

    // 更新矿工等级
    public function update_level()
    {
    	$child_id = intval(input('child_id'));
    	$level_id = intval(input('level_id'));
    	$res = \app\common\model\ChiaMiningMember::updateMemberLevel($this->member_id, $child_id, $level_id);
    	$this->output_new($res);
    }
}