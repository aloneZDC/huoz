<?php
//资产互转配置
namespace app\common\model;

use think\Model;

class CurrencyUserTransferConfig extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}