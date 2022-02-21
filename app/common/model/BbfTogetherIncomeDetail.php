<?php
//四币连发 三级奖励详情
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class BbfTogetherIncomeDetail extends Base
{
    static function award($member_id,$currency_id,$award_num,$base_id,$base_num,$release_id,$base_percent,$today_start) {
        //添加奖励记录
        return self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'num' => $award_num,
            'award_time' => $today_start,
            'add_time' => time(),
            'third_percent' => $base_percent,
            'third_num' => $base_num,
            'third_id' => $base_id,
            'release_id' => $release_id,
        ]);
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
