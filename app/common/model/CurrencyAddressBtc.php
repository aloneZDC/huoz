<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/11
 * Time: 18:25
 */

namespace app\common\model;


class CurrencyAddressBtc extends Base
{
    static function get_address()
    {
        $count = self::where(['cab_is_use' => 1])->count();
        if ($count > 0) {
            $range = rand(1, $count);
            $result = self::where(['cab_is_use' => 1])->page($range, 1)->select();//随机获取一条未使用过的BTC地址
            if(isset($result[0])){
                return $result[0];
            }
        }
        return null;
    }
}