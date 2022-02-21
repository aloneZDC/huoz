<?php


namespace app\common\model;


use PDOStatement;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

/**
 * Class UserAirJackpot
 * @package app\common\model
 */
class UserAirJackpot extends Model
{
    /**
     * @var int 未到账
     */
    const NOT_INCOME = 1;

    /**
     * @var int 已到账
     */
    const ALREADY_INCOME = 2;

    const INCOME_ENUM = [
        self::NOT_INCOME => '未到账',
        self::ALREADY_INCOME => '已到账'
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    public function log()
    {
        return $this->belongsTo(AirJackpotLog::class, 'ajl_id');
    }

    /**
     * @param int $userId
     * @param int $currencyId
     * @param double $number
     * @param int $ajlId
     * @param double $fee
     * @return int|string
     */
    public static function add_log($userId, $currencyId, $number, $ajlId, $fee)
    {
        return (new self)->insertGetId([
            'ajl_id' => $ajlId,
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'number' => $number,
            'is_income' => self::NOT_INCOME,
            'fee' => $fee,
            'add_time' => time()
        ]);
    }

    /**
     * 获取未入账的数据
     * @param int $userId
     * @param int $currencyId
     * @return false|PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getNotIncomeData($userId, $currencyId)
    {
        return $this->where('user_id', $userId)->where('is_income', self::NOT_INCOME)->where('currency_id', $currencyId)->select();
    }

    public function todayExist($userId)
    {
        return !empty($this->where('user_id', $userId)->whereTime('add_time', 'today')->value('id'));
    }

    /**
     * @param $ids
     * @return UserAirJackpot
     */
    public function setAlreadyIncome($ids)
    {
        return $this->where('id', 'in', $ids)->where('is_income', self::NOT_INCOME)->update([
            'is_income' => self::ALREADY_INCOME
        ]);
    }
}