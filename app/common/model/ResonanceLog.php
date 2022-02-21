<?php


namespace app\common\model;


use think\Model;

/**
 * 共振记录
 * Class ResonanceLog
 * @package app\common\model
 */
class ResonanceLog extends Model
{

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id')->field('currency_id, currency_name');
    }


    /**
     * 获取已使用数量
     * @param $member_id
     * @return float|int
     */
    public function getUsedNumber($member_id)
    {
        return $this->where('member_id', $member_id)->sum('to_number');
    }

    /**
     * 添加记录
     * @param int $memberId
     * @param int $currencyId
     * @param int $toCurrencyId
     * @param double $number
     * @param double $toNumber
     * @param double $fee
     * @param double $radio
     * @return int|string
     */
    public static function add_log($memberId, $currencyId, $toCurrencyId, $number, $toNumber, $fee, $radio)
    {
        return (new self)->insertGetId([
            'member_id' => $memberId,
            'currency_id' => $currencyId,
            'to_currency_id' => $toCurrencyId,
            'number' => $number,
            'to_number' => $toNumber,
            'fee' => $fee,
            'radio' => $radio,
            'add_time' => time()
        ]);
    }
}