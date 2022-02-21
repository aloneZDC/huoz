<?php

namespace app\common\model;

class MtkMiningConfig extends Base
{
    /**
     * 获取配置列表
     * @return array|false|string
     */
    public static function get_key_value()
    {
        $list = self::column('value', 'key');
        if (empty($list)) return [];
        return $list;
    }

    /**
     * 获取配置值
     * @param $key
     * @param $default
     * @return float|mixed|string
     */
    public static function get_value($key, $default)
    {
        return self::where(['key' => $key])->value('value', $default);
    }
}