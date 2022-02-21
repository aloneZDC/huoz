<?php
//跳跃排名倒序加权算法配置
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class JumpRankingCurrencyConfig extends Model
{
    static function getList($today_config) {
        return self::where(['auto_start_time'=>['elt',$today_config['today_start']] ])->select();
    }

    static function getAllList() {
        $currency = self::alias('a')->field('a.currency_id,a.raning_min_mum,a.auto_start_time,b.currency_name,b.currency_logo,b.is_trade_currency')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->select();
        return $currency;
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
