<?php


namespace app\admin\model;


use think\Model;

class CurrencyRiseTask extends  Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_mark');
    }
}