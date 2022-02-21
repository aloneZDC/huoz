<?php
//房间级别配置表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomLevelSetting extends Model
{

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'rls_currency_id', 'currency_id')->field('currency_name, currency_id');
    }
}