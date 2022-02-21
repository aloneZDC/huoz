<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;

class FeelMining extends Base
{
    const STATUS_OK = 0; // 释放中
    const STATUS_OUT = 1; //释放完毕

    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function realCurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    /**
     * 添加记录
     * @param $member_id
     * @return int|string
     */
    public static function AddLog($member_id)
    {
        $data = [
            'member_id' => $member_id,
            'currency_id' => Config::get_value('feel_currency_id', 5),
            'release_num_total' => Config::get_value('feel_release_num_total', 100),
            'release_percent' => Config::get_value('feel_release_percent', 0),
            'real_currency_id' => Config::get_value('feel_real_currency_id', 67),
            'add_time' => time(),
        ];
        return self::insert($data);
    }

    /**
     * 获取体验矿机列表
     * @param $member_id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getList($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];

        $list = self::where(['status' => 0, 'member_id' => $member_id])->with(['currency', 'realCurrency'])
            ->field(['id','currency_id','release_num_total','release_num_avail','release_num','real_currency_id','status','release_time'])->find();
        if (empty($list)) return $r;

        $list['release_status'] = 0;
        $today_start = strtotime(date('Y-m-d'));
        if ($list['release_time'] >= $today_start) {
            $list['release_status'] = 1;
        }
        // 总数去掉小数点
        $list['release_num_total'] = (int)$list['release_num_total'];

        // 今日释放fil
        $release_num = FeelMiningRelease::where(['third_id' => $list['id'], 'release_time' => ['egt', $today_start]])->find();
        $list['today_release_num'] = $release_num['real_currency_num'] ? $release_num['real_currency_num'] : 0;

        // 累计释放fil
        $list['real_currency_num'] = FeelMiningRelease::where('third_id', $list['id'])->sum('real_currency_num');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 支付发行币种
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function supportCurrency()
    {
        $r = ['code' => ERROR1, 'message' => lang('lan_orders_illegal_request'), 'result' => null];

        // 体验矿机-释放币种ID
        $feel_currency_id = Config::get_value('feel_currency_id', 5);

        // 释放币种信息
        $pay_currency = Currency::where(['currency_id' => $feel_currency_id])->find();
        if (empty($pay_currency)) return $r;

        // 支持的币种
        $currency_list = Currency::where(['account_type' => 'mining'])->field('currency_id,currency_name')->select();
        if (empty($currency_list)) return $r;

        $pay_currency_price = CurrencyPriceTemp::get_price_currency_id($feel_currency_id, 'USD');
        foreach ($currency_list as &$currency) {
            $currency['pay_currency_name'] = $pay_currency['currency_name'];
            if ($currency['currency_id'] == $pay_currency['currency_id']) {
                $currency['ratio'] = 1;
            } else {
                $price = FilMining::getReleaseCurrencyPrice($currency['currency_id']);
                $currency['ratio'] = $price > 0 ? keepPoint($pay_currency_price / $price, 6) : 0;
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $currency_list;
        return $r;
    }

    /**
     * 修改开采币种
     * @param $real_currency_id
     * @param $feel_id
     * @return mixed
     */
    public static function mineCurrency($real_currency_id, $feel_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('lan_orders_illegal_request'), 'result' => null];

        // 判断价格
        $price = FilMining::getReleaseCurrencyPrice($real_currency_id);
        if ($price < 0) {
            $r['message'] = lang('feel_not_supported');
            return $r;
        }

        $mineCurrency = self::where('id', $feel_id)->update(['real_currency_id' => $real_currency_id, 'update_time' => time()]);
        if (empty($mineCurrency)) {
            $r['message'] = lang('lan_operation_failure');
            return $r;
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        return $r;
    }

    /**
     * 签到开采
     * @param int $member_id 用户ID
     * @param int $feel_id 矿机ID
     * @return array|bool|int
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public static function release($member_id, $feel_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('lan_orders_illegal_request'), 'result' => null];

        $feel_release_time = Config::get_value('feel_release_time', 0);
        if (strtotime($feel_release_time) > time()) {
            $r['message'] = lang('feel_release_time_msg');
            return $r;
        }

        $real_detail = self::where(['id' => $feel_id, 'member_id' => $member_id])->find();
        if (empty($real_detail)) return $r;

        // 判断今日是否开采
        $today_start = strtotime(date('Y-m-d'));
        if ($real_detail['release_time'] >= $today_start) {
            $r['message'] = lang('feel_release_already');
            return $r;
        }

        // 判断是否已经释放完成
        if ($real_detail['status'] == 1) {
            $r['message'] = lang('feel_release_finish');
            return $r;
        }

        // 获取币种当前价格
        $currency_price = CurrencyPriceTemp::get_price_currency_id($real_detail['currency_id'], 'USD'); // USDT价格
        $real_currency_price = FilMining::getReleaseCurrencyPrice($real_detail['real_currency_id']);// 最终到账币种价格
        if ($currency_price <= 0 || $real_currency_price <= 0) {
            $r['message'] = lang('lan_reg_the_network_busy');
            return $r;
        }

        $currency_ratio = keepPoint($currency_price / $real_currency_price, 6); // USDT价格:最终到账币种价格
        $real_num = $real_detail['release_num_total'] * $real_detail['release_percent'] / 100; // 释放USDT数量

        // 判断是否释放完成
        $is_out = false;
        $release_num = keepPoint($real_detail['release_num_total'] - $real_detail['release_num_avail'], 6);
        if ($release_num <= $real_num) {
            $is_out = true;
            $real_num = $release_num;
        }

        // 最终到账数量
        $real_currency_num = keepPoint($real_num * $currency_ratio, 6);

        return FeelMiningRelease::Release($real_detail, $is_out, $real_num, $currency_ratio, $real_currency_num);
    }
}
