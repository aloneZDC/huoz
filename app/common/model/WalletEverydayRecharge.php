<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/10
 * Time: 14:31
 */

namespace app\common\model;


class WalletEverydayRecharge extends Base
{
    /**添加一条当天充币成功的统计数
     * @param $currency_id      币种id
     * @param $value            数值
     * @param $time             时间戳(充币的时间戳)
     * @return bool
     * Created by Red.
     * Date: 2018/12/10 14:43
     */
    static function addWalletEverydayRecharge($currency_id, $value,$time=null)
    {
        if (!empty($currency_id) && $value > 0) {
            if(!empty($time)){
                $date = date("Y-m-d", $time);
            }else{
                $date = date("Y-m-d", time());
            }
            $find = self::where(['wer_currency_id' => $currency_id, 'wer_time' => $date])->find();
            if (!empty($find)) {
                $find->wer_total += $value;
                $result = $find->save();
                return $result? true : false;
            } else {
                $object=new WalletEverydayRecharge();
                $object->wer_currency_id = $currency_id;
                $object->wer_time = $date;
                $object->wer_total = $value;
                return $object->save()? true : false;
            }
        }
        return false;
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'wer_currency_id');
    }
}