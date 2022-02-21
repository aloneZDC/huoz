<?php
//太空计划 - 动力源 - 详情
namespace app\common\model;

use think\Model;

class SpacePlanPowerDetail extends Model
{
    static function addDetail($member_id,$currency_id,$num,$third_id,$third_num,$percent,$add_time) {
        return self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'num' => $num,
            'third_id' => $third_id,
            'third_num' => $third_num,
            'percent' => $percent,
            'add_time' => $add_time,
        ]);
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
