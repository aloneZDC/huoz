<?php


namespace app\common\model;


use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * Class ConvertConfig
 * @package app\common\model
 */
class ConvertConfig extends Model
{
    /**
     * @var string
     */
    protected $pk = "id";


    const BB_PRICE = 2;

    const REVERSAL_BB_PRICE = 3;

    const DEFAULT_PRICE = 1;
    const FIXED_RATIO = 4;

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
        return $this->belongsTo(Currency::class, 'to_currency_id', 'currency_id')->field('currency_id, currency_name as currency_mark');
    }

    /**
     * @return HasMany
     */
    public function toConfig()
    {
        return $this->hasMany(ConvertConfig::class, 'currency_id', 'currency_id')->field('id, currency_id, to_currency_id, day_max_num, currency_price_type, currency_bb_id, to_currency_bb_id,fixed_ratio')->with('to_currency');
    }


    /**
     * @return int|string|double
     */
    public function getRatio()
    {
        switch ($this['currency_price_type']) {
            case self::DEFAULT_PRICE:
//                $currencyPrice = CurrencyPriceTemp::get_price_currency_id($this['currency_id'], "USD");
//                $toCurrencyPrice = CurrencyPriceTemp::get_price_currency_id($this['to_currency_id'], "USD");
//                return keepPoint($currencyPrice / $toCurrencyPrice, 6);
                if ($this['currency_id'] == 5) {
                    $currencyPrice = 1;
                    $toCurrencyPrice = MtkCurrencyPrice::yesterday_price(93);
                }
                if ($this['to_currency_id'] == 5) {
                    $currencyPrice = MtkCurrencyPrice::yesterday_price(93);
                    $toCurrencyPrice = 1;
                }
                return keepPoint($currencyPrice / $toCurrencyPrice, 6);
            case self::BB_PRICE:
                return keepPoint(Trade::getLastTradePrice($this['currency_bb_id'], $this['to_currency_bb_id']), 6);
            case self::REVERSAL_BB_PRICE:
                return keepPoint(1 / (Trade::getLastTradePrice($this['currency_bb_id'], $this['to_currency_bb_id'])), 6);
            case self::FIXED_RATIO:
                return $this['fixed_ratio'];
            default:
                return 0;
        }

    }
}
