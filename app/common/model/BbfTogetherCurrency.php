<?php
//四币连发配置表
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class BbfTogetherCurrency extends Base
{
    static function getListApi($member_id=0) {
        $list = self::alias('a')->field('a.*,b.currency_name as pay_total_currency_name,c.currency_name as pay_currency_name,d.currency_name as pay_other_currency_name,e.currency_name as pledge_currency_name,f.currency_name as release_currency_name')
            ->join(config("database.prefix") . "currency b", "a.pay_total_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.pay_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.pay_other_currency_id=d.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency e", "a.pledge_currency_id=e.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency f", "a.release_currency_id=f.currency_id", "LEFT")
            ->select();

        if(!$list) return [];

        $currency_prices= CurrencyPriceTemp::select();
        if(empty($currency_prices)) return [];

        $currency_prices = array_column($currency_prices,'cpt_usd_price','cpt_currency_id');

        foreach ($list as &$item) {
            $item = self::filterItem($currency_prices,$item,$member_id);
        }

        return $list;
    }

    static function getCurrency($bbf_currency_id,$member_id) {
        $item = self::alias('a')->field('a.*,b.currency_name as pay_total_currency_name,c.currency_name as pay_currency_name,d.currency_name as pay_other_currency_name,e.currency_name as pledge_currency_name,f.currency_name as release_currency_name')
            ->join(config("database.prefix") . "currency b", "a.pay_total_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.pay_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.pay_other_currency_id=d.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency e", "a.pledge_currency_id=e.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency f", "a.release_currency_id=f.currency_id", "LEFT")
            ->where(['id'=>$bbf_currency_id])
            ->find();

        if(!$item) return [];

        $currency_prices= CurrencyPriceTemp::select();
        if(empty($currency_prices)) return [];

        $currency_prices = array_column($currency_prices,'cpt_usd_price','cpt_currency_id');

        $item = self::filterItem($currency_prices,$item,$member_id);
        return $item;
    }

    static function filterItem($currency_prices,$item,$member_id) {
        //第一个币种支付数量
        $item['pay_currency_num'] = 0;
        if ($item['pay_currency_id'] >0 && $item['pay_currency_percent'] >0 ) {
            $pay_from_currency_num =  $item['pay_total_currency_num'] * $item['pay_currency_percent'] / 100;
            $item['pay_currency_num'] = keepPoint($pay_from_currency_num * self::getConvertRatio($currency_prices,$item['pay_total_currency_id'],$item['pay_currency_id']),6);
        }

        //第二个币种支付数量
        $item['pay_other_currency_num'] = 0;
        if ($item['pay_other_currency_id'] >0 &&  $item['pay_other_currency_percent'] >0 ) {
            $pay_from_currency_num =  $item['pay_total_currency_num'] * $item['pay_other_currency_percent'] / 100;
            $item['pay_other_currency_num'] = keepPoint($pay_from_currency_num * self::getConvertRatio($currency_prices,$item['pay_total_currency_id'],$item['pay_other_currency_id']),6);
        }

        //质押币种支付数量
        $item['pledge_currency_num'] = 0;
        if ($item['pledge_currency_id'] >0 &&  $item['pledge_currency_percent'] >0 ) {
            $pay_from_currency_num =  $item['pay_total_currency_num'] * $item['pledge_currency_percent'] / 100;
            $item['pledge_currency_num'] = keepPoint($pay_from_currency_num * self::getConvertRatio($currency_prices,$item['pay_total_currency_id'],$item['pledge_currency_id']),6);
        }

        //实际锁仓币种数量
        $item['lock_currency_num'] = 0;
        if ($item['lock_currency_id'] >0 &&  $item['lock_currency_percent'] >0 ) {
            $pay_from_currency_num =  $item['pay_total_currency_num'] * $item['lock_currency_percent'] / 100;
            $item['lock_currency_num'] = keepPoint($pay_from_currency_num * self::getConvertRatio($currency_prices,$item['pay_total_currency_id'],$item['lock_currency_id']),6);
        }

        //实际支付价值 不包含质押
        $item['pay_total_num'] = keepPoint($item['pay_total_currency_num'] * (100-$item['pledge_currency_percent']) / 100,6);

        if(!empty($member_id)) {
            $currency_user = CurrencyUser::where(['member_id' => $member_id])->select();
            if(!empty($currency_user)) {
                $currency_user = array_column($currency_user->toArray(),null,'currency_id');
            }

            $item['user_num'] = [
                'pay_currency_num' => isset($currency_user[$item['pay_currency_id']]) ? $currency_user[$item['pay_currency_id']]['num'] : 0,
                'pay_other_currency_num' => isset($currency_user[$item['pay_other_currency_id']]) ? $currency_user[$item['pay_other_currency_id']]['num'] : 0,
                'pledge_currency_num' => isset($currency_user[$item['pledge_currency_id']]) ? $currency_user[$item['pledge_currency_id']]['num'] : 0,
                'lock_currency_num' => isset($currency_user[$item['lock_currency_id']]) ? $currency_user[$item['lock_currency_id']]['num'] : 0,
            ];
        }

        return $item;
    }

    static function getConvertRatio($currency_prices,$from_currency_id,$to_currency_id) {
        if(!isset($currency_prices[$from_currency_id]) || !isset($to_currency_id) ) return 0;
        if($from_currency_id == $to_currency_id) return 1;

        return $currency_prices[$from_currency_id]/$currency_prices[$to_currency_id];
    }
}
