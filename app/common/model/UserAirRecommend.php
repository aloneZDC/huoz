<?php


namespace app\common\model;


use think\Model;

class UserAirRecommend extends Model
{
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    public function recommendUser()
    {
        return $this->belongsTo(Member::class, 'recommend_user_id')->field('member_id, email, phone');
    }
}