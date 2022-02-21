<?php

namespace app\common\model;

class MtkMiningProduct extends Base
{
    /**
     * 获取商品
     * @param int $product_id 商品信息
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function product_find($product_id)
    {
        return self::where('id', $product_id)->where('status', 0)->find();
    }

    /**
     * 商品列表
     * @param int $member_id 用户id
     * @param int $page 页码
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function product_list($member_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];

        // 获取MKT价格
        $mtk_price = MtkCurrencyPrice::where(['currency_id' => 93])->order(['id' => 'desc'])->limit(2)->column('price');
        $mtk_price = [
            'day_price' => keepPoint($mtk_price[0], 4),// 今日MKT价
            'yes_price' => keepPoint($mtk_price[1], 4),// 昨日MKT价
            'currency_name' => 'USDT',
        ];

        // 产出统计
        $total_release = MtkMiningIncome::where(['type' => 4])->sum('third_num');
        $mining_order = MtkMiningOrder::where(['member_id' => $member_id])
            ->field(['sum(last_release_num)' => 'last_release_num', 'sum(surplus_power)' => 'surplus_power'])
            ->find();
        $release = [
            'total_release' => keepPoint($total_release, 6), //全网产出
            'last_release_num' => keepPoint($mining_order['last_release_num'] ?: 0, 6), //今日产币数量
            'surplus_power' => keepPoint($mining_order['surplus_power'] ?: 0, 6), //剩余算力
            'currency_name' => 'MTK',
        ];

        $release_currency_id = MtkMiningConfig::get_value('release_currency_id', 93);
        $list = self::alias('a')->where(['a.status' => 0])
            ->join('currency b', 'b.currency_id = ' . $release_currency_id, 'left')
            ->field("a.id,a.name,a.number_min,a.number_max,a.multiple,a.static_ratios,b.currency_name,a.amount")
            ->page($page, $rows)->order('a.sort asc')->select();
        if (empty($list)) return $r;

        foreach ($list as &$item) {
            $item['number_min'] = keepPoint($item['number_min']);
            $item['number_max'] = keepPoint($item['number_max']);
//            $item['multiple_min'] = keepPoint($item['number_min'] * $item['multiple'], 6);
//            $item['multiple_max'] = keepPoint($item['number_max'] * $item['multiple'], 6);
            $item['static_ratios'] = keepPoint($item['static_ratios'] * 365);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'product_list' => $list,
            'mtk_price' => $mtk_price,
            'release' => $release
        ];
        return $r;
    }
}