<?php
//线下商家兑换配置
namespace app\common\model;

use think\Model;

class StoresConvertConfig extends Model {
    const NUM_FIELD = 'num'; //用户资产 可用对应字段
    const CARD_FIELD = 'uc_card'; //用户资产 卡包锁仓对应字段
    const FINANCIAL_FIELD = 'uc_card_lock'; //用资产  理财包锁仓对应字段
    const DNC_LOCK = 'dnc_lock';
    const KEEP_NUM = 'keep_num';
    //可用兑换到卡包锁仓
    static function num_to_card() {
        $list =  self::where(['currency_field'=>'num','to_currency_field'=>'card'])->alias('a')->field('a.*,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->select();
        if($list){
            foreach ($list as $key=>&$value){
                $ratio  = self::get_ratio($value);
                if($ratio<=0) {
                    unset($list[$key]);
                } else {
                    $value['from_ratio'] = $ratio;
                }
                $value['to_currency_inc_percent'] = rtrim(rtrim($value['to_currency_inc_percent']/100,0),'.');
                $value['to_currency_name'] = lang('uc_card');
            }
        }
        return $list;
    }

    static function num_to_card_one($currency_id,$to_currency_id) {
        $config = self::where(['currency_id'=>$currency_id,'to_currency_id'=>$to_currency_id,'currency_field'=>'num','to_currency_field'=>'card'])->find();
        if(!$config) return null;

        $ratio = self::get_ratio($config);
        if($ratio<=0) return null;

        $config['ratio'] = $ratio;
        return $config;
    }

    //可用 兑换到 可用
    static function num_to_num() {
        $list = self::where(['currency_field'=>'num','to_currency_field'=>'num'])->alias('a')->field('a.*,b.currency_name,c.currency_name as to_currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.to_currency_id=c.currency_id", "LEFT")
            ->select();
        if($list){
            foreach ($list as $key=>&$value){
                $ratio  = self::get_ratio($value);
                if($ratio<=0) {
                    unset($list[$key]);
                } else {
                    $value['from_ratio'] = $ratio;
                }
            }
        }
        return $list;
    }
    //可用 兑换到 可用
    static function num_to_num_one($currency_id,$to_currency_id) {
        $config = self::where(['currency_id'=>$currency_id,'to_currency_id'=>$to_currency_id,'currency_field'=>'num','to_currency_field'=>'num'])->find();
        if(!$config) return null;

        $ratio = self::get_ratio($config);
        if($ratio<=0) return null;

        $config['ratio'] = $ratio;
        return $config;
    }


    //卡包 兑换到 理财包
    static function card_to_financial() {
        $list = self::where(['currency_field'=>'card','to_currency_field'=>'financial'])->select();
        if($list){
            foreach ($list as $key=>&$value){
                $ratio  = self::get_ratio($value);
                if($ratio<=0) {
                    unset($list[$key]);
                } else {
                    $value['from_ratio'] = $ratio;
                }
            }
        }
        return $list;
    }
    //卡包 兑换到 理财包
    static function card_to_financial_one($currency_id,$to_currency_id) {
        $config = self::where(['currency_id'=>$currency_id,'to_currency_id'=>$to_currency_id,'currency_field'=>'card','to_currency_field'=>'financial'])->find();
        if(!$config) return null;

        $ratio = self::get_ratio($config);
        if($ratio<=0) return null;

        $config['ratio'] = $ratio;
        return $config;
    }

    //获得兑换比例
    static function get_ratio ($stores_convert_config) {
        $ratio = 1; //兑换比例
        if($stores_convert_config['currency_id']!=$stores_convert_config['to_currency_id']){
            $currency_price = CurrencyPriceTemp::get_price_currency_id($stores_convert_config['currency_id'],'CNY');
            $to_currency_price = CurrencyPriceTemp::get_price_currency_id($stores_convert_config['to_currency_id'],'CNY');
            if($currency_price<=0 || $to_currency_price<=0) {
                $ratio = 0;
            } else {
                $ratio = keepPoint($currency_price/$to_currency_price,6);
            }
        }
        return $ratio;
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function tocurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'to_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}