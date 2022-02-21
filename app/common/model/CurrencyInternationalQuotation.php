<?php
//国际行情

namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class CurrencyInternationalQuotation extends Model
{
    static function get_list() {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = [];

        $list = self::field('cpt_currency_mark,cpt_currency_to_mark,cpt_day_close,cpt_day_degree,cpt_day_vol,cpt_currency_to_mark_price')->order('cpt_sort asc')->select();
        if($list) {
            foreach ($list as &$value) {
                $value['price'] = $value['cpt_day_close'];
                $value['cny_price'] = keepPoint($value['price'] * $value['cpt_currency_to_mark_price'],2);
                $value['cpt_day_vol'] = keepPoint($value['cpt_day_vol'],2);
                if($value['cpt_day_vol']>10000){
                    $value['cpt_day_vol'] = keepPoint($value['cpt_day_vol']/1000,2).'K';
                }
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang('data_success');
            $r['result'] = $list;
        }

        return $r;
    }

    //更新国际行情
    static function update_quotation($quotation_info) {
        if($quotation_info['cpt_from']=='huobi') {
            $day_last = self::getQuotationFromHuobi($quotation_info['cpt_currency_mark'].$quotation_info['cpt_currency_to_mark']);
        }

        $update_day = [];
        if(!empty($day_last)) {
            $update_day = [
                'cpt_day_high' => $day_last['high'],
                'cpt_day_low' => $day_last['low'],
                'cpt_day_open' => $day_last['open'],
                'cpt_day_close' => $day_last['close'],
                'cpt_day_vol' => $day_last['vol'],
                'cpt_day_degree' => $day_last['degree'],
                'cpt_update_time' => time(),
            ];
        }

        $to_currency_mark_price = [];
        $usdt_cny = CurrencyPriceTemp::getUsdtCny();
        $price = CurrencyPriceTemp::getPriceUSD($quotation_info['cpt_currency_to_mark']);
        if($usdt_cny>0 && $price>0) {
            $to_currency_mark_price = [
                'cpt_currency_to_mark_price' => keepPoint($price*$usdt_cny,6),
            ];
        }
        $update = array_merge($update_day,$to_currency_mark_price);
        if(!empty($update)){
            self::where(['cpt_currency_mark'=>$quotation_info['cpt_currency_mark'],'cpt_currency_to_mark'=>$quotation_info['cpt_currency_to_mark']])->update($update);
        }
    }

    static function getQuotationFromHuobi($symbol) {
        $cur = [];
        try{
            $url = "http://api.coindog.com/api/v1/tick/HUOBIPRO:{$symbol}?unit=base";
            $res = _curl($url, '', '', 'GET', 5);
            $res = json_decode($res, true);
            if(is_array($res) && !empty($res['high'])) {
                $cur = $res;
            }
        } catch (Exception $e) {
            $cur = [];
        }
        return $cur;
    }

    //获取usdt人民币价格  MONTH W1 D1 H4 H1 M30 M15 M5 M1;
    static function getKlinesFromHuobi($symbol,$type) {
        $cur = [];
        try{
            $url = "http://api.coindog.com/api/v1/klines/HUOBIPRO:{$symbol}/{$type}?unit=CNY";
            $res = _curl($url, '', '', 'GET', 5);
            $res = json_decode($res, true);
            if(is_array($res) && !empty($res)) {
                $cur = $res[0];
            }
        } catch (Exception $e) {
            $cur = [];
        }
        return $cur;
    }
}