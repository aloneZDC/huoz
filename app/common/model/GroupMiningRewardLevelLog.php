<?php
namespace app\common\model;
use think\Model;

/**
 * 拼团挖矿用户奖励等级记录表
 * Class GroupMiningRewardLevelLog
 * @package app\common\model
 */
class GroupMiningRewardLevelLog extends Model
{
    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    // 奖励等级
    public function rewlevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningRewardLevel', 'reward_level', 'level_id')->field('level_id,level_name');
    }
}
