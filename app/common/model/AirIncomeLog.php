<?php


namespace app\common\model;


use think\Model;

class AirIncomeLog extends Model
{
    /**
     * 类型：激活
     */
    const TYPE_ACTIVATION = 'activation';

    /**
     * 类型: 补差价
     */
    const TYPE_DIFF = 'diff';


    const TYPE_ZH_CN_ENUM = [
        self::TYPE_ACTIVATION => '激活',
        self::TYPE_DIFF => '补差价'
    ];

    /**
     * 资产类型：可用
     */
    const ASSETS_TYPE_NUM = 'num';

    /**
     * 资产类型：云攒金
     */
    const ASSETS_TYPE_AIR_NUM = 'air_num';

    const ASSETS_TYPE_COMBINE = 'combine';


    const ASSETS_ZH_CN_MAP = [
        self::ASSETS_TYPE_NUM => '可用',
        self::ASSETS_TYPE_AIR_NUM => '云攒金',
        self::ASSETS_TYPE_COMBINE => '组合支付'
    ];

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    public function payUser()
    {
        return $this->belongsTo(Member::class, 'pay_user_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function giveCurrency()
    {
        return $this->belongsTo(Currency::class, 'give_currency_id');
    }

    /**
     * 入金记录
     * @param int $userId 入金用户ID
     * @param int $payUserId 支付用户ID
     * @param int $currencyId 入金币种ID
     * @param double $number 入金数量
     * @param int $giveCurrencyId 赠送币种ID
     * @param double $giveNumber 赠送数量
     * @param string $type 入金类型(使用类常量，数据库增加枚举类型后，常量同步增加) 激活｜补差价
     * @param string $assetsType 资产类型 (使用类常量) num 可用 air_num 云攒金
     * @param double|int $airNum
     * @return int|string
     */
    public static function add_log($userId, $payUserId, $currencyId, $number, $giveCurrencyId, $giveNumber, $type = self::TYPE_ACTIVATION, $assetsType = self::ASSETS_TYPE_NUM, $airNum = 0)
    {
        return (new AirIncomeLog)->insertGetId([
            'user_id' => $userId,
            'pay_user_id' => $payUserId,
            'currency_id' => $currencyId,
            'number' => $number,
            'give_currency_id' => $giveCurrencyId,
            'give_number' => $giveNumber,
            'type' => $type,
            'assets_type' => $assetsType,
            'air_num' => $airNum,
            'add_time' => time()
        ]);
    }
}