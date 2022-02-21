<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿联创红利奖励详情表
 * Class GroupMiningIncomeAirdropDetail
 * @package app\common\model
 */
class GroupMiningIncomeAirdropDetail extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    // 用户信息
    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    // 矿源等级
    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }
}
