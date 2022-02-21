<?php

namespace app\common\model;

use think\Db;
use think\Log;
use think\Model;

/**
 * 独享矿机 - 产品
 * Class AloneMiningProduct
 * @package app\common\model
 */
class AloneMiningProduct extends Model
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
            ->field("a.id,a.name,a.node_name,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.quota,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name,d.currency_name as mining_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.mining_currency_id=d.currency_id", "LEFT")
            ->page($page, $rows)->order(['a.sort' => 'asc'])->select();
        if (empty($list)) return $r;

        $alone_mining_config = AloneMiningConfig::get_key_value();
        foreach ($list as &$item) {
            $item['days'] = $alone_mining_config['contract_period'];
            $item['pay_days'] = $alone_mining_config['output_time'];
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
            ->field("a.id,a.name,a.node_name,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.quota,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name,d.currency_name as mining_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.mining_currency_id=d.currency_id", "LEFT")
            ->find();
        if (empty($list)) return $r;

        $alone_mining_config = AloneMiningConfig::get_key_value();
        $list['percent'] = 100 - $alone_mining_config['release_manager_fee_percent'];

        // Gas费
        $average_out = self::average_out();

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['result'] = [
            'product' => $list,
            'fgas' => [
                'gas32' => $average_out,
                'gas64' => [
                    'preGas_1T' => keepPoint($average_out['preGas_1T'] / 2, 6),
                    'payment_1T' => $average_out['payment_1T'],
                ]
            ]
        ];
        return $r;
    }

    // 获取平均价格
    static function average_out()
    {
        $fgas_task = 'fgas_task' . date('Y-m-d');
        $fgas_task_result = cache($fgas_task);
        if (empty($fgas_task_result) || empty($fgas_task_result['preGas_1T']) || empty($fgas_task_result['payment_1T'])) {
            $total_fgas_average = AloneMiningConfig::getValue('total_fgas_average', '');
            $fgas_average = explode(',', $total_fgas_average);
            $start_time = strtotime(date('Y-m-d ' . $fgas_average[0] . ':00:00')) - 86400;
            $start_end = strtotime(date('Y-m-d ' . $fgas_average[1] . ':00:00')) - 86400;
            $average = Db::name('fgas_task')->where('time', '>', $start_time)->where('time', '<=', $start_end)->field('avg(preGas_1T) as preGas_1T,avg(payment_1T) as payment_1T')->find();
            $preGas_1T = keepPoint($average['preGas_1T'], 6);
            $payment_1T = keepPoint($average['payment_1T'], 6);
            $fgas_task_result = ['preGas_1T' => $preGas_1T, 'payment_1T' => $payment_1T];
            cache($fgas_task, $fgas_task_result);
            return $fgas_task_result;
        }
        return $fgas_task_result;
    }


}