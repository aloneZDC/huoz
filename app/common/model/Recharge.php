<?php


namespace app\common\model;


use think\Model;

class Recharge extends Model
{

    const STATUS_VERIFY = 1;

    const STATUS_SUCCESS = 2;

    const STATUS_FAIL = 3;

    const STATUS_ZH_CN_MAP = [
        self::STATUS_VERIFY => '审核中',
        self::STATUS_SUCCESS => '通过',
        self::STATUS_FAIL => '撤销'
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_id, currency_name, currency_type');
    }

    public static function store($userId, $currencyId, $number, $to, $img = '')
    {
        return (new self)->insertGetId([
            'tx' => '',
            'from' => '',
            'to' => $to,
            'fee' => 0,
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'number' => $number,
            'verify_number' => 0,
            'img' => $img,
            'status' => self::STATUS_VERIFY,
            'add_time' => time(),
            'verify_time' => ''
        ]);
    }
}