<?php


namespace app\common\model;


use think\Model;

class UserAirDiff extends Model
{

    public function rewardUser()
    {
        return $this->belongsTo(Member::class, 'reward_user_id');
    }

    public function incomeUser()
    {
        return $this->belongsTo(Member::class, 'income_user_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function level()
    {
        return $this->belongsTo(AirLadderLevel::class, 'level_id');
    }
    
    /**
     * @param int $currencyId
     * @param int $incomeId
     * @param int $rewardUserId
     * @param int $incomeUserId
     * @param int $levelId
     * @param double $number
     * @param double $fee
     * @param double $radio
     * @param int $dayId
     * @return int|string
     */
    public static function add_log($currencyId, $incomeId, $rewardUserId, $incomeUserId, $levelId, $number, $fee, $radio, $dayId)
    {
        return (new self)->insertGetId([
            'day_id' => $dayId,
            'currency_id' => $currencyId,
            'income_id' => $incomeId,
            'reward_user_id' => $rewardUserId,
            'income_user_id' => $incomeUserId,
            'level_id' => $levelId,
            'number' => $number,
            'fee' => $fee,
            'radio' => $radio,
            'add_time' => time(),
        ]);
    }
}