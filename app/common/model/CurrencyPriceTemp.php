<?php
//币种价格

namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class CurrencyPriceTemp extends Model
{
    //获取币种价格
    static function get_price_currency_id($currency_id,$rate_type="USD"){
        if(is_numeric($currency_id) && $currency_id>0){
            $find=Db::name("CurrencyPriceTemp")->where(['cpt_currency_id'=>$currency_id])->find();
            if(!empty($find)){
                if($rate_type=="CNY"){
                    return $find['cpt_cny_price'];
                }else{
                    return $find['cpt_usd_price'];
                }
            }
        }
        return 0;
    }

    /**
     * 根据币种的mark标识获取币种的价格
     */
    static function get_price_mark($mark,$rate_type="USD"){
        if(!empty($mark)){
            $field="cpt_cny_price,cpt_usd_price";
            $find=Db::name("CurrencyPriceTemp")->alias("cpt")->field($field)->where(['currency_mark'=>$mark])->join(config("database.prefix")."currency c","cpt.cpt_currency_id=c.currency_id")->find();
            if(!empty($find)){
                if($rate_type=="CNY"){
                    return $find['cpt_cny_price'];
                }else{
                    return $find['cpt_usd_price'];
                }
            }
        }
        return 0;
    }

    //获取币种美元价格
    static function getPriceUSD($currency_name)
    {
        $cu_price = 0;
        try{
            if ($currency_name != 'USDT') {
                $ticker = "HUOBIPRO:" . $currency_name . "USDT";
                $url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
                $res = _curl($url, '', '', 'GET', 5);
                $res = json_decode($res, true);
                if (isset($res['close'])) {
                    $cu_price = round(isset($res['close']) ? $res['close'] : 0, 6);
                }
            } else {
                $cu_price = 1;
            }
        } catch (Exception $e) {
            $cu_price = 0;
        }
        return $cu_price;
    }

    //获取usdt人民币价格
    static function getUsdtCny() {
        $cu_price =  0;
        try{
            $ticker = "HUOBIPRO:BTCUSDT";
            $url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=cny";
            $res = _curl($url, '', '', 'GET', 5);
            $res = json_decode($res, true);
            $url2 = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
            $res2 = _curl($url2, '', '', 'GET', 5);
            $res2 = json_decode($res2, true);
            if (isset($res['close']) && isset($res2['close'])) {
                $cu_price = round($res['close'] / $res2['close'], 6);
            }
        } catch (Exception $e) {
            $cu_price = 0;
        }
        return $cu_price;
    }

    static function getUsdtCnyByHuobiOtc() {
        $cu_price =  0;
        try{
            $url = "https://otc-api.huobi.pro/v1/data/market/detail";
            $res = _curl($url, '', '', 'GET', 5);
            $res = json_decode($res, true);
            if (isset($res['data']) && isset($res['data']['detail'])) {
                foreach ($res['data']['detail'] as $coin) {
                    if ($coin['coinName'] == "USDT") {
                        return $coin['buy'];
                    }
                }
            }
        } catch (Exception $e) {
            $cu_price = 0;
        }
        return $cu_price;
    }

    static function getByApi($apiurl) {
        $cu_price =  0;
        try{
            $res = file_get_contents($apiurl);
            if($res) {
                $res = json_decode($res,true);
                if($res['code']==SUCCESS && is_numeric($res['result'])) {
                    $cu_price = $res['result'];
                }
            }
        } catch (Exception $e) {
            $cu_price = 0;
        }
        return $cu_price;
    }

    //如果有币币交易就按照币币交易价格  如果无则按照固定价格
    static function BBFirst_currency_price($currency_id,$unit="USD"){
        //如果不是币币账户  自动获取对应的币币账户
        $currency = Currency::where(['currency_id'=>$currency_id])->field('account_type,currency_name')->find();
        if(!$currency) return 0;

        if($currency['account_type']!='currency') {
            $currency_bb = Currency::where(['currency_name'=>$currency['currency_name'],'account_type'=>'currency'])->field('currency_id,currency_name')->find();
            if(!$currency_bb) return 0;

            $currency_id = $currency_bb['currency_id'];
        }

        $cur_currency_id = 0;
        $cur_price = 0;
        foreach (Trade::MOST_CURRENCY as $most_currency_id) {
            $most_price = Trade::getLastTradePrice($currency_id,$most_currency_id);
            if($most_price) {
                $cur_currency_id = $most_currency_id;
                $cur_price = $most_price;
                break;
            }
        }
        //没有获取到币币 交易价格 则获取固定价格
        if(!$cur_price) {
            return self::get_price_currency_id($currency_id,$unit);
        }

        //币币 交易价格  * 后面币种人民币价格
        $usdt_money = CurrencyPriceTemp::get_price_currency_id($cur_currency_id,$unit);
        return keepPoint($cur_price * $usdt_money,6);
    }
}
