<?php
//商城参数设置表
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class ShopConfig extends Base
{
    const TIME_CONFIG = 5;
    /**
     * 获取配置
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_value($key, $default = "")
    {
        $find = (new self)->where('sc_key', $key)->find();
        if (empty($find)) {
            return $default;
        }
        return $find['sc_value'];
    }

    /**
     * 获取所有配置
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_configs()
    {
        $select = (new self)->select();
        if (!count($select)) {
            return [];
        }
        $configs = [];
        foreach ($select as $key => $value) {
            $configs[$value['sc_key']] = $value['sc_value'];
        }
        return $configs;
    }
}