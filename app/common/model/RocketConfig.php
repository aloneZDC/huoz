<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class RocketConfig extends Base
{
    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];

        return array_column($list, 'value', 'key');
    }

    static function getValue($key, $default = 0) {
        $info = self::where(['key'=>$key])->find();
        if($info) return $info['value'];
        return $default;
    }
}