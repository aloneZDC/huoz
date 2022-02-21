<?php


namespace app\common\model;


class YefiCurrencyPrice extends Base
{
    /**
     * 创建yefi价格
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
            'currency_id' => 103,
            'currency_name' => 'yefi',
            'price' => $mining_config['yefi_price_default'],
            'create_time' => todayBeginTimestamp(),
        ];

        // 获取昨天数据
        $url = "https://api.starfish3.com/api/getcoinprice?market=USDT&coin=yefi";
        $result = self::getFromUrl($url);
        if ($result === false) return 0;

        $json = json_decode($result, true);
        if (is_array($json) && isset($json['data'])) {
            $default_data['price'] = $json['data'];
        }
        return self::create($default_data);
    }

    /**
     * 查询数据
     * @param string $url api地址
     * @param string $method 请求方式
     */
    static function getFromUrl($url, $method = 'GET')
    {
        try {
            $opts = array(
                'http' => array(
                    'method' => $method,
                    'timeout' => 5,//单位秒
                )
            );
            $result = file_get_contents($url, false, stream_context_create($opts));
            if (empty($result)) return false;

            return $result;
        } catch (\Exception $e) {
            \think\Log::write("YefiCurrencyPrice error:" . $e->getMessage());
        }
        return false;
    }
}