<?php

namespace app\common\model;

use think\Model;

/**
 * 独享矿机 - 配置
 * Class AloneMiningConfig
 * @package app\common\model
 */
class AloneMiningConfig extends Model
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