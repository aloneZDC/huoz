<?php
namespace app\common\model;
use think\Model;

/**
 * 拼团挖矿燃烧记录表
 * Class GroupMiningBurnLog
 * @package app\common\model
 */
class GroupMiningBurnLog extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function MoneyCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'money_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }
}
