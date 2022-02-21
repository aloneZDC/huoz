<?php
//太空计划 支付记录
namespace app\common\model;

use think\Model;

class SpacePlanPay extends Model
{
    static function addPay($member_id,$currency_id,$num,$type,$actual_num,$third_id=0) {
        return self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'num' => $num,
            'actual_num' => $actual_num,
            'type' => $type,
            'add_time' => time(),
            'third_id' => $third_id,
            'is_award' => 0,
        ]);
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
