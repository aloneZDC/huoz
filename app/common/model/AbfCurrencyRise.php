<?php
namespace app\common\model;

use app\cli\controller\CurrencyPrice;
use think\Log;
use think\Db;
use think\Exception;

class AbfCurrencyRise extends Base
{
    //更新币种价格
    static function updatePrice($currency_rise,$add_time) {
        try {
            self::startTrans();

            $currency_price = CurrencyPriceTemp::get_price_currency_id($currency_rise['currency_id'],'USD');
            if(!$currency_price) throw new Exception("获取币种价格失败");

//            $new_price = keepPoint($currency_price + $currency_rise['base_price'] * $currency_rise['percent'] / 100,6);
            $new_price = keepPoint($currency_price + $currency_price * $currency_rise['percent'] / 100,6);
            $flag = Db::name('abf_currency_rise')->where(['id'=>$currency_rise['id'],'last_time'=>$currency_rise['last_time']])->update([
                'curr_price' => $new_price,
                'last_time' => $add_time,
            ]);
            if(!$flag) throw new Exception("获取币种价格失败");

            $flag =CurrencyPriceTemp::where(['cpt_currency_id'=>$currency_rise['currency_id']])->update([
                'cpt_usd_price' => $new_price,
            ]);
            if(!$flag) throw new Exception("更新币种价格失败");

            self::commit();
        } catch (Exception $e) {
            self::rollback();
            Log::write("AbfCurrencyRise::updatePrice".$e->getMessage());
            return false;
        }

        self::CurrencyPriceTask();
        return true;
    }

    //手动调用更新币种价格
    static function CurrencyPriceTask() {
        try {
            $currency_price_Task = new CurrencyPrice();
            $currency_price_Task->doRun();
        } catch (Exception $e) {
            Log::write("AbfCurrencyRise::CurrencyPriceTask".$e->getMessage());
        }
    }
}
