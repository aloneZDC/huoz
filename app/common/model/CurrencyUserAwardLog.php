<?php


namespace app\common\model;


use think\Model;

class CurrencyUserAwardLog extends Model
{

    /**
     * 注册奖励配置KEY
     * @var string
     */
    const KEY_REG_AWARD = "reg_award";

    /**
     * 直推奖励配置KEY
     * @var string
     */
    const KEY_ONE_INVITE_AWARD = "one_invite_award";

    /**
     * 二代奖励配置KEY
     * @var string
     */
    const KEY_TWO_INVITE_AWARD = "two_invite_award";

    /**
     * 注册
     * @var int
     */
    const TYPE_REG = 1;
    /**
     * 直推
     * @var int
     */
    const TYPE_ONE_INVITE = 2;
    /**
     * @二代
     * @var int
     */
    const TYPE_TWO_INVITE = 3;

    /**
     * 提取
     * @var int
     */
    const TYPE_TAKE = 4;

    /**
     * 收入
     * @var int
     */
    const NUMBER_TYPE_ADD = 1;

    /**
     * 支出
     * @var int
     */
    const NUMBER_TYPE_SUB = 2;

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_mark, currency_name');
    }

    /**
     * 添加记录
     * @param int $memberId 赠送用户ID
     * @param int $regMemberId 注册用户ID
     * @param int $currencyId 币种ID
     * @param int $numberType 1收入 2支出
     * @param double $num 赠送数量
     * @param int $type 类型
     * @return int|string
     */
    public static function addLog($memberId, $regMemberId, $num, $currencyId, $type = self::TYPE_REG, $numberType = self::NUMBER_TYPE_ADD)
    {

        return (new self)->insertGetId([
            'member_id' => $memberId,
            'reg_member_id' => $regMemberId,
            'num' => $num,
            'type' => $type,
            'number_type' => $numberType,
            'currency_id' => $currencyId,
            'create_time' => time()
        ]);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function getAward($key)
    {
        return Config::get_value($key);
    }
}