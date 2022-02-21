<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/10
 * Time: 14:47
 */

namespace app\common\model;


class WalletEverydaySummary extends Base
{
    /**添加一条当天汇总成功的统计数
     * @param $currency_id      币种id
     * @param $value            数值
     * @param $time             时间戳(成功时的时间戳)
     * @return bool
     * Created by Red.
     * Date: 2018/12/10 14:47
     */
    static function addWalletEverydaySummary($currency_id, $value,$time=null)
    {
        if (!empty($currency_id) && $value > 0) {
            if(!empty($time)){
                $date = date("Y-m-d", $time);
            }else{
                $date = date("Y-m-d", time());
            }
            $find = self::where(['wes_currency_id' => $currency_id, 'wes_time' => $date])->find();
            if (!empty($find)) {
                $find->wes_total += $value;
                $result = $find->save();
                return $result? true : false;
            } else {
                $object=new WalletEverydaySummary();
                $object->wes_currency_id = $currency_id;
                $object->wes_time = $date;
                $object->wes_total = $value;
                return $object->save()? true : false;
            }
        }
        return false;
    }
}