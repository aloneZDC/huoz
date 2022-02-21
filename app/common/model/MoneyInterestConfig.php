<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/22
 * Time: 14:57
 */

namespace app\common\model;


use think\Db;

class MoneyInterestConfig extends Base
{
    /**
     * 到期释放利息 + 本金
     */
    const TYPE_EXPIRE = 1;
    /**
     * 每日释放利息
     */
    const TYPE_DAY = 2;

    const TYPE_ENUM = [
        self::TYPE_EXPIRE => '到期释放',
        self::TYPE_DAY => '可支取'
    ];

    // 设置返回数据集的对象名
    protected $resultSetType = 'collection';

    public function currency()
    {
        return $this->belongsTo('Currency', 'currency_id')->field('currency_id, currency_name, currency_mark');
    }

    /**
     * 持币生息可选币种列表
     * @param int $member_id 用户id
     * @return null
     * Created by Red.
     * Date: 2018/12/22 18:16
     */
    static function getCurrencyList($member_id)
    {
        $list = Db::query("SELECT a.currency_id,c.currency_mark,c.currency_logo from yang_money_interest_config a LEFT JOIN yang_currency c ON (a.currency_id = c.currency_id) GROUP BY currency_id ORDER BY  c.currency_mark='XRP' asc");
        if (!empty($list)) {
            foreach ($list as &$value) {
                $currencyUser = CurrencyUser::getCurrencyUser($member_id, $value['currency_id']);
                $value['num'] = 0;
                $value['cny'] = 0;
                if (!empty($currencyUser)) {
                    $value['num'] = $currencyUser->num;
                    //$value['cny'] = keepPoint(getPriceUSD($value['currency_mark']) * $currencyUser->num, 6);
                }

            }
        }
        return $list;
    }

    /*
     * 根据币种id获取持币生息期数列表
     * @param $currency_id
     * @return array|null
     * Created by Red.
     * Date: 2019/1/3 18:42
     */
    static function getConfigByCurrenciId($currency_id)
    {
        if (!empty($currency_id)) {
            return self::where(['currency_id' => $currency_id])->order("months asc")->select()->toArray();
        }
        return null;
    }
}