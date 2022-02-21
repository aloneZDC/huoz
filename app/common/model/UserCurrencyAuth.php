<?php


namespace app\common\model;


use think\Model;

class UserCurrencyAuth extends Model
{
    public static function isAuth($memberId, $currencyId)
    {
        return (new self)->where('user_id', $memberId)->where('currency_id', $currencyId)->value('is_auth');
    }

}