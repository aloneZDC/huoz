<?php


namespace app\common\model;


use think\Model;
use think\model\relation\BelongsTo;

class ConvertLog extends Model
{
    /**
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_id, currency_name as currency_mark');
    }

    /**
     * @return BelongsTo
     */
    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id','currency_id')->field('currency_id, currency_name as currency_mark');
    }
}