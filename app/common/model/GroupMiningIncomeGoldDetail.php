<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿拼团金(奖励等级)奖励详情表
 * Class GroupMiningIncomeGoldDetail
 * @package app\common\model
 */
class GroupMiningIncomeGoldDetail extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    // 用户信息
    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }
}
