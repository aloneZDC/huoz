<?php


namespace app\common\model;


use think\Model;

class EmailSystem extends Model
{
    static function get_list(){
        return self::where(['es_status'=>1])->select();
    }

    static function choice_rand($hosts) {
        return $hosts[array_rand($hosts)];
    }
}