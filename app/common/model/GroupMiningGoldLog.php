<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿拼团金记录表
 * Class GroupMiningLog
 * @package app\common\model
 */
class GroupMiningGoldLog extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }

}
