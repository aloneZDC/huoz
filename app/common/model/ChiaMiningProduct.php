<?php

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * CHIA(奇亚)云算力
 * Class ChiaMiningProduct
 * @package app\common\model
 */
class ChiaMiningProduct extends Model
{
    /**
     * 获取商品记录
     * @param int $product_id 商品ID
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function get_product($product_id)
    {
        return self::where('id', $product_id)->where('status', 1)->find();
    }

    /**
     * 获取商品列表
     * @param int $page 页面
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function product_list($page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];

        $list = self::alias('a')->where(['a.status' => 1])
            ->field("a.id,a.name,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.cycle_time,a.deliver_time,a.add_time,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->page($page, $rows)->order(['a.sort' => 'asc'])->select();
        if (empty($list)) return $r;

        $mining_config = ChiaMiningConfig::get_key_value();
        foreach ($list as &$item) {
            $item['pre_sale_start'] = date('m-d H:i',$item['add_time']);
            $item['pre_sale_stop'] = date('m-d H:i',$item['add_time'] + $mining_config['pre_sale_time'] * 86400);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取商品详情
     * @param int $product_id 商品ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function product_info($product_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];

        $list = self::alias('a')->where(['a.id' => $product_id, 'a.status' => 1])
            ->field("a.id,a.name,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.cycle_time,a.deliver_time,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->find();
        if (empty($list)) return $r;

        $mining_config = ChiaMiningConfig::get_key_value();
        $list['manager_fee'] = $mining_config['release_manager_fee_percent'];
        $list['percent'] = 100 - $mining_config['release_manager_fee_percent'];
        $list['min_buy'] = $mining_config['min_buy'];

        // Gas费
//        $average_out = self::average_out();

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
//        $r['result'] = [
//            'product' => $list,
//            'fgas' => [
//                'gas32' => $average_out,
//                'gas64' => [
//                    'preGas_1T' => keepPoint($average_out['preGas_1T'] / 2, 6),
//                    'payment_1T' => $average_out['payment_1T'],
//                ]
//            ]
//        ];
        return $r;
    }
    
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'mining_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function usdtcurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_usdt_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function cnycurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_cny_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}