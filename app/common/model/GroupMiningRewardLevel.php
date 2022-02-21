<?php
namespace app\common\model;
use think\Model;

/**
 * 拼团挖矿奖励等级表
 * Class GroupMiningRewardLevel
 * @package app\common\model
 */
class GroupMiningRewardLevel extends Model
{
    public function BurnCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'burn_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function MoneyCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'burn_money_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    // 获取奖励等级信息
    static function getLevelInfo($level_id) {
        return self::get($level_id);
    }
}
