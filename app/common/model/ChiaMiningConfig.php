<?php

namespace app\common\model;

use think\Model;

/**
 * CHIA(奇亚)云算力
 * Class ChiaMiningConfig
 * @package app\common\model
 */
class ChiaMiningConfig extends Model
{
    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];

        return array_column($list, 'value', 'key');
    }

    static function getValue($key,$default) {
        $info = self::where(['key'=>$key])->find();
        if($info) return $info['value'];
        return $default;
    }
}