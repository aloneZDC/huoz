<?php

namespace app\common\model;

/**
 * 承包参数设置表
 * Class WarrantConfig
 * @package app\common\model
 */
class WarrantConfig extends Base
{
    /**
     * 获取配置
     * @param $key
     * @param string $default
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_value($key, $default = "")
    {
        $find = (new self)->where('key', $key)->find();
        if (empty($find)) {
            return $default;
        }
        return $find['value'];
    }

    /**
     * 获取所有配置
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_configs()
    {
        $select = (new self)->select();
        if (!count($select)) {
            return [];
        }
        $configs = [];
        foreach ($select as $key => $value) {
            $configs[$value['key']] = $value['value'];
        }
        return $configs;
    }
}