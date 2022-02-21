<?php
/**
累计质押100- 299枚USDT，日挖矿0.1% USDT
累计质押300- 599枚USDT，日挖矿0.2% USDT
累计质押600-999枚USDT，日挖矿0.3% USDT
累计质押1000- 1499枚USDT，日挖矿0.4% USDT
累计质押1500- 2099枚USDT，日挖矿0.5% USDT
累计质押2100- 2799枚USDT，日挖矿0.6% USDT
累计质押2800- 3599枚USDT，日挖矿0.7% USDT
累计质押3600 - 4499枚USDT，日挖矿0.8% USDT
累计质押4500 - 5499枚USDT，日挖矿0.9% USDT
累计质押5500及以上 USDT，日挖矿1% USDT
注：
解除矿机 = 累计质押数量 - 静态收益 - 动态收益 -  累计质押数量*10%
静态收益+动态收益 = 累计质押数量*3 则自动出局，不再享受收益，增加质押后自动重新享受收益
0-8点禁止增加质押, 提示： 挖矿中，不能增加质押
 */
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;


class BflMiningCurrencyConfig extends Model
{
    static function getList() {
        return self::with(['currency','configs'])->field('currency_id,min_num_limit,min_create_num_limit')->select();
    }

    static function getCurrencyConfig($currency_id) {
        return self::where(['currency_id'=>$currency_id])->find();
    }


    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function configs() {
        return $this->hasMany('app\\common\\model\\BflMiningLevelConfig', 'currency_id', 'currency_id')->field('currency_id,min_num,max_num,percent');
    }
}
