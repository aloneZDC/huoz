<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿矿工费(奖励等级)奖励详情表
 * Class GroupMiningIncomeFeeDetail
 * @package app\common\model
 */
class GroupMiningIncomeFeeDetail extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    // 用户信息
    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    // 奖励等级
    public function rewlevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningRewardLevel', 'reward_level', 'level_id')->field('level_id,level_name');
    }
}
