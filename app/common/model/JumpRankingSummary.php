<?php
//跳跃排名倒序加权算法配置
namespace app\common\model;

use think\Model;

class JumpRankingSummary extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
