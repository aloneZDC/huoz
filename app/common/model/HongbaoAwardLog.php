<?php
//红包  上级奖励记录
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoAwardLog extends Model
{
    static function add_award($user_id,$currency_id,$num,$base_num,$percent,$fee,$third_id) {
        return self::insertGetId([
           'user_id' => $user_id,
            'currency_id' => $currency_id,
            'num' => $num,
            'base_num' => $base_num,
            'percent' => $percent,
            'fee' => $fee,
            'third_id' => $third_id,
            'create_time' => time(),
        ]);
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

}
