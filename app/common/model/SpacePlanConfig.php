<?php
//跳跃排名倒序加权算法配置
namespace app\common\model;

use think\Model;

class SpacePlanConfig extends Model
{
    static function get_key_value()
    {
        $list = self::select();
        if (empty($list)) return [];
        return array_column($list, 'value', 'key');
    }

    static function getValue($key, $default)
    {
        $info = self::where(['key' => $key])->find();
        if ($info) return $info['value'];
        return $default;
    }
}
