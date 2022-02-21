<?php
//红包  拆红包记录
namespace app\common\model;

use think\Model;
class HongbaoChaiLog extends Model
{
    static function add_award($log_id,$user_id,$currency_id,$num,$base_num,$percent) {
        return self::insertGetId([
            'log_id' => $log_id,
            'user_id' => $user_id,
            'currency_id' => $currency_id,
            'num' => $num,
            'base_num' => $base_num,
            'percent' => $percent,
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
