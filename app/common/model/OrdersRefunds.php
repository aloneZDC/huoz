<?php


namespace app\common\model;


use think\Model;

class OrdersRefunds extends Model
{
    protected $table = "yang_orders_refunds";

    public static function add(array $data)
    {
        return self::create($data);
    }

    public static function createNumber()
    {
        $number = "R" . date("YmdHi") . randNum();
        $find = self::where(['number' => $number])->value('id');
        if (empty($find)) {
            return $number;
        }
        return self::createNumber();
    }
}