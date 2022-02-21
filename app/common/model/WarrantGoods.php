<?php

namespace app\common\model;

/**
 * 承包商品
 * Class WarrantGoods
 * @package app\common\model
 */
class WarrantGoods extends Base
{
    const STATUS_UP = 1;
    const STATUS_DOWN = 2;
    const STATUS_DEL = 3;

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    /**
     * 获取商品列表
     * @param int $page
     * @param int $rows
     * @param int|null $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_goods_list($page = 1, $rows = 10, $type = null)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (isInteger($page) && $rows <= 50) {
            $where = [];
            if (!empty($type)) {
                $where['category_id'] = $type;
            }
            $list = self::where('status', self::STATUS_UP)->where($where)->page($page, $rows)
                ->field(['id', 'title', 'img', 'price', 'currency_id'])->with(['currency'])->order(['sort' => 'desc'])->select();
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $img = !empty($value['img']) ? explode(",", $value['img']) : null;
                    $value['img'] = !empty($img) ? $img[0] : null;
                    $value['price'] = floatval($value['price']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 获取商品详情
     * @param $goods_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function goods_details($goods_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($goods_id)) return $r;

        $list = self::where(['id' => $goods_id, 'status' => self::STATUS_UP])->with(['currency'])->find();
        if (empty($list)) {
            $r['message'] = lang("no_data");
            return $r;
        }

        $list['price']=floattostr($list['price']);
        $list['img']=!empty($list['img'])?explode(",",$list['img']):null;
        $list['banners'] = $list['banners'] ? json_decode($list['banners']) : null;
        $list['content']=!empty($list['content'])?html_entity_decode($list['content']):null;
        $list['contract']=!empty($list['contract'])?html_entity_decode($list['contract']):null;

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $list;
        return $r;
    }


}