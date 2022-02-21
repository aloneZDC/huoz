<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class GoodsFormat extends Model
{
    /**
     * 获取商品规格列表
     * @param int $goods_id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_format_list($goods_id)
    {
        $field = "id,name,goods_price,goods_market,goods_stock,goods_img";
        $list = self::where(['goods_id'=>$goods_id, 'status'=>1])->field($field)->order('sort desc')->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value = $value->toArray();
                $value['goods_price'] = floattostr($value['goods_price']);
                $value['goods_market'] = floattostr($value['goods_market']);
            }
        }

        return $list;
    }
}