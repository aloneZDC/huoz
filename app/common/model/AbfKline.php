<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class AbfKline extends Base
{
    //获取今日价格
    static function getTodayKline($currency_id,$currency_trade_id) {
        return self::where([
            'type' => 86400,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'add_time' => todayBeginTimestamp(),
        ])->order('id desc')->find();
    }

    static function getYestdayKline($currency_id,$currency_trade_id) {
        return self::where([
            'type' => 86400,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'add_time' => todayBeginTimestamp() - 86400,
        ])->order('id desc')->find();
    }

    //获取最新的一条K线条
    static function getLastDayKline($currency_id,$currency_trade_id) {
        return self::where([
            'type' => 86400,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id
        ])->order('id desc')->find();
    }

    static function getFirstKline($currency_id,$currency_trade_id) {
        return self::where([
            'type' => 86400,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id
        ])->order('id asc')->find();
    }

    static function insertDayKline($currency_id,$currency_trade_id,$add_time) {
        $currency_id_price = CurrencyPriceTemp::get_price_currency_id($currency_id,'USD');
        if(!$currency_id_price) return false;

        $currency_trade_id_price = CurrencyPriceTemp::get_price_currency_id($currency_trade_id,'USD');
        if(!$currency_trade_id_price) return false;

        //交易价格
        $trade_price = keepPoint($currency_id_price / $currency_trade_id_price,6);
        if(!$trade_price) return false;

        $flag = self::insertGetId([
            'type' => 86400,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'open_price' => $trade_price,
            'close_price' => $trade_price,
            'high_price' => $trade_price,
            'low_price' => $trade_price,
            'num' => 0,
            'add_time' => $add_time,
        ]);
        if(!$flag) return false;

        return $trade_price;
    }

    //K线图列表
    static function kline($currency_id,$currency_trade_id,$page=1,$rows=1000) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        if($page<1) $page = 1;

        $where = [
            'a.currency_id' => $currency_id,
            'a.currency_trade_id' => $currency_trade_id,
        ];
        $field = "a.close_price,a.add_time";
        $list = self::field($field)->alias('a')->where($where)
            ->page($page, $rows)->order("a.id asc")->select();

        if (!empty($list)) {
            foreach ($list as $key=>&$value) {
                if ('1' == date("j", $value['add_time'])) {
                    $value['first'] = true;
                }
                $value['date'] = $value['add_time'] = date('Y-m-d', $value['add_time']);
                $value['steps'] = floatval($value['close_price']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = $list;
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }
}
