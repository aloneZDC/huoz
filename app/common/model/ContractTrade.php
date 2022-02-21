<?php
//合约交易对表
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class ContractTrade extends Base
{
    /**
     * 获取交易对列表
     */
    public static function get_trade_list()
    {
        $select = (new self)->where('switch', 1)->order('sort', 'desc')->select();
        if (!count($select)) {
            return [];
        }
        $trade_list = [];
        $lever_max = ContractConfig::get_value('contract_lever_max', 50);
        $lever_min = ContractConfig::get_value('contract_lever_min', 5);
        foreach ($select as $key => $value) {
            $currency = Currency::get($value['currency_id']);
            $trade_currency = Currency::get($value['trade_currency_id']);
            $price = \app\common\model\ContractKline::get_price($value['id'], 60, 'close_price');
            $trade_list[] = [
                'trade_id'=>$value['id'],
                'currency_name_left'=>$currency['currency_name'],
                'currency_name_right'=>$trade_currency['currency_name'],
                'name'=>$currency['currency_name'].'/'.$trade_currency['currency_name'],
                'logo'=>$currency['currency_logo'],
                'lever_max'=>$lever_max,
                'lever_min'=>$lever_min,
                'daily_gain'=>ContractKline::get_daily_gain($value['id']),
                'price'=>floattostr($price),
            ];
        }
        return $trade_list;
    }

    /**
     * 获取交易对列表
     * @return array
     */
    public static function get_trade_list1()
    {
        $select = (new self)/*->where('switch', 1)*/->order('sort', 'desc')->select();
        if (!count($select)) {
            return [];
        }
        foreach ($select as $key => $value) {
            $currency = Currency::get($value['currency_id']);
            $trade_currency = Currency::get($value['trade_currency_id']);
            $trade_list[$value['id']] = $currency['currency_name'].'/'.$trade_currency['currency_name'];
        }
        return $trade_list;
    }

    /**
     * 获取配置
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_default_trade_id()
    {
        $default_trade_id = (new self)->where('switch', 1)->order('sort', 'desc')->value('id');
        !$default_trade_id && $default_trade_id = 0;
        return $default_trade_id;
    }
}