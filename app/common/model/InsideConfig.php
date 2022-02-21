<?php

namespace app\common\model;

class InsideConfig extends Base
{
    // 发布广告 - 配置
    public static function trade_config($type)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $currency_config = InsideConfig::alias('a')
            ->join('currency b', 'b.currency_id=a.currency_id', 'left')
            ->join('currency c', 'c.currency_id=a.to_currency_id', 'left')
            ->where(['a.trade_type' => $type, 'a.status' => 1])
            ->field('a.id,a.min_num,a.day_max_num,a.fee,b.currency_name,c.currency_name as to_currency_name')
            ->find();
        if (empty($currency_config)) return $r;

        // 获取价格
        $yesterday_price = MtkCurrencyPrice::yesterday_price(93);
//        $currency_config['currency_price'] = keepPoint($yesterday_price * 0.8, 6);
        $currency_config['currency_price'] = keepPoint($yesterday_price, 6);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $currency_config;
        return $r;
    }
}