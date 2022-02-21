<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/11
 * Time: 18:25
 */

namespace app\common\model;


class CurrencyAddressBnb extends Base
{
    static function get_address()
    {
        $count = self::where(['cae_is_use' => 1])->count();
        if ($count > 0) {
            $range = rand(1, $count);
            $result = self::where(['cae_is_use' => 1])->page($range, 1)->select();//随机获取一条未使用过的地址
            if(isset($result[0])){
                return $result[0];
            }
        }
        return null;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'cae_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}
