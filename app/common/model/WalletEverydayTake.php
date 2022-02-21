<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/10
 * Time: 14:44
 */

namespace app\common\model;


class WalletEverydayTake extends Base
{
    /**
     * 添加一条当天提币成功的统计数
     * @param int $currency_id 币种id
     * @param int $value 数值
     * @param null $time 时间戳(成功时的时间戳)
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function addWalletEverydayTake($currency_id, $value, $time = null)
    {
        if (!empty($currency_id) && $value > 0) {
            if (!empty($time)) {
                $date = date("Y-m-d", $time);
            } else {
                $date = date("Y-m-d", time());
            }
            $find = self::where(['wet_currency_id' => $currency_id, 'wet_time' => $date])->find();
            if (!empty($find)) {
                $find->wet_total += $value;
                $result = $find->save();
                return $result ? true : false;
            } else {
                $object = new WalletEverydayTake();
                $object->wet_currency_id = $currency_id;
                $object->wet_time = $date;
                $object->wet_total = $value;
                return $object->save() ? true : false;
            }
        }
        return false;
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'wet_currency_id');
    }
}