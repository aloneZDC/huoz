<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class AbfTradeCurrency extends Base
{
    const TRADE_UNIX = 'CNY'; //币币交易 CNY USD  空字符串时和  NEW_PRICE_UNIT 一致
    const MOST_CURRENCY = [ //非主流币种交易对获取价格
        Currency::USDT_BB_ID,
    ];

    static function getList() {
        $list = self::alias('a')->field('a.currency_id,a.currency_trade_id,a.is_buy,a.is_sell,a.buy_fee,a.sell_fee,b.currency_name,c.currency_name as currency_trade_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->select();

        return $list;
    }

    static function getListApi() {
        $list = self::getList();
        if($list) {
            $cache_key = 'AbfTradeCurrency_getListApi';
            $data = cache($cache_key);
            if(empty($data)) {
                foreach ($list as $key => $item) {
                    $list[$key] = self::tradeCurrencyFomat($item);
                }
                $data = $list;
                cache($cache_key,$data,2);
            }
            return $data;
        }
        return [];
    }

    static function tradeCurrencyFomat($abf_currency) {
        $new_price = AbfTrade::getTodayPriceByKline($abf_currency['currency_id'],$abf_currency['currency_trade_id']);
        $yestday_price = AbfTrade::getYestdayPriceByKline($abf_currency['currency_id'],$abf_currency['currency_trade_id']);
        $abf_currency['new_price'] = $new_price; //今日价格
        $abf_currency['new_price_unit'] = AbfTrade::getPriceRealMoney($new_price,$abf_currency['currency_trade_id'],self::TRADE_UNIX); //最新价格人民币价格
        $abf_currency['yestday_price'] = $yestday_price;
        $abf_currency['percent'] = $yestday_price>0 ? keepPoint(($new_price-$yestday_price)/$yestday_price*100,2) : 0; //涨跌幅

        $first_price = AbfTrade::getFirstPriceByKline($abf_currency['currency_id'],$abf_currency['currency_trade_id']);
        $abf_currency['first_percent'] = $first_price>0 ? keepPoint(($new_price-$first_price)/$first_price*100,2) : 0; //涨跌幅

        return $abf_currency;
    }

    static function getAbfCurrencyApi($currency_id,$currency_trade_id) {
        $item = self::alias('a')
            ->where([
                'a.currency_id' => $currency_id,
                'a.currency_trade_id' => $currency_trade_id,
            ])->field('a.currency_id,a.currency_trade_id,a.is_buy,a.is_sell,a.buy_fee,a.sell_fee,b.currency_name,c.currency_name as currency_trade_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->find();
        if(!$item) return [];

        return self::tradeCurrencyFomat($item);
    }

    //查看币种介绍
    static function getAbfCurrencyDescApi($currency_id,$currency_trade_id) {
        $abf_currency = self::where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
        ])->find();
        if($abf_currency) return json_decode($abf_currency['currency_desc']);

        return [];
    }

    static function getOne($currency_id,$currency_trade_id) {
        return self::where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
        ])->find();
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function currencytrade() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_trade_id', 'currency_id')->field('currency_id,currency_name');
    }
}
