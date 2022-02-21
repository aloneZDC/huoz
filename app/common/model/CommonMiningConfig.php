<?php

namespace app\common\model;

use think\Model;

/**
 * 满存算力配置
 * Class CommonMiningConfig
 * @package app\common\model
 */
class CommonMiningConfig extends Model
{
    /**
     * 获取配置列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_key_value()
    {
        $list = self::select();
        if (empty($list)) return [];

        return array_column($list, 'value', 'key');
    }

    /**
     * 获取配置值
     * @param string $key 配置名称
     * @param string $default 默认值
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_value($key, $default)
    {
        $info = self::where(['key' => $key])->find();
        if ($info) return $info['value'];
        return $default;
    }
}
