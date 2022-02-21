<?php

namespace app\common\model;

/**
 * 承包分类
 * Class warrantCategory
 * @package app\common\model
 */
class WarrantCategory extends Base
{
    /**
     * 获取商品分类列表
     * @return bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_category_list()
    {
        $list = [];
        $field = "id,name,pic as image";
        $list = self::where('status', 1)->field($field)->order("sort asc")->select();
        if (!empty($list)) {
            foreach ($list as &$value) {
                $value = $value->toArray();
            }
        }
        array_unshift($list, ['id' => 0, 'name' => '全部', 'image' => '']);
        return $list;
    }
}