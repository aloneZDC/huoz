<?php

namespace app\common\model;


use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * Class ContractIncomeLog
 * 合约收益记录表
 * @package app\common\model
 */
class ContractIncomeLog extends Model
{
    /**
     * 账户类型枚举
     * @var array
     */
    const TYPE_ENUM = [
        1 => "直推奖励",
        2 => "锁仓释放",
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_name, currency_id');
    }
}