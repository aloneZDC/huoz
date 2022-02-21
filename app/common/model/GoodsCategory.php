<?php

namespace app\common\model;

use think\exception\DbException;
use think\Model;

class GoodsCategory extends Model
{
    const GIFT_TYPE = 1;//新人礼包

    const NORMAL_TYPE = 2;//正常商品区

    const HIGH_TYPE = 3;//高贡献值区

    const INTEGRAL_TYPE = 4;//积分专区

    const GROUP_TYPE = 5;//拼团专区

    const TYPE_ENUM = [
        self::GIFT_TYPE=>'新人礼包',
        self::NORMAL_TYPE=>'正常商品区',
        self::HIGH_TYPE=>'高贡献值区',
        self::INTEGRAL_TYPE=>'积分专区',
        self::GROUP_TYPE=>'拼团专区',
    ];

    /**
     * 获取商品分类列表
     * @param int $type
     * @return mixed
     * @throws DbException
     */
    static function get_category_list($type = 0)
    {
        $field = "id,name,pic as image";
        if ($type == 0) {
            $list = self::where('pid', $type)->where('status', 1)->field($field)->limit(3)->order("sort asc")->select();
        } else {
            $list = self::where('pid', $type)->where('status', 1)->field($field)->order("sort asc")->select();
        }
        if (!empty($list)) {
            foreach ($list as &$value) {
                $value = $value->toArray();
            }
        }
        if($type != 0) {
            array_unshift($list, ['id' => 0, 'name' => '全部', 'image' => '']);
        }

        return $list;
    }
}