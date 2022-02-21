<?php

namespace app\common\model;

class MtkCurrencyPrice extends Base
{
    /**
     * 创建MTK价格
     * @param array $mining_config 矿机配置
     * @return MtkCurrencyPrice|false
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function create_price($mining_config)
    {
        // 查询今日是否有创建数据
        $list = self::where(['create_time' => ['egt', todayBeginTimestamp()]])->find();
        if (!empty($list)) return true;

        // 默认数据
        $default_data = [
            'currency_id' => 93,
            'currency_name' => 'MTK',
            'price' => $mining_config['price_default'],
            'create_time' => todayBeginTimestamp(),
        ];

        // 获取昨天数据
        $yesterday_price = self::yesterday_price(93);
        if ($yesterday_price > 0) {
            $increase_price = keepPoint($yesterday_price * $mining_config['price_multiple'] / 100, 6);
            $default_data['price'] = keepPoint($yesterday_price + $increase_price, 6);
        }

        return self::create($default_data);
    }

    /**
     * 获取昨日价格
     * @param int $currency_id 币种id
     * @return float|mixed|string
     */
    public static function yesterday_price($currency_id)
    {
        return self::where(['currency_id' => $currency_id])->order(['id' => 'desc'])->value('price', 1);
    }
}