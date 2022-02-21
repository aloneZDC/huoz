<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/14
 * Time: 17:20
 */

namespace app\im\model;

use app\common\model\Currency;
use think\Exception;

class MnemonicHot extends Base
{
    static function getHot() {
        $currency = Currency::where(['currency_mark'=>'eth','is_line'=>1])->find();
        if(empty($currency)) return [];

        $list = self::where('system_currency_id',$currency['currency_id'])->field('currency_name,currency_token,currency_logo')->select();
        if(empty($list)) return [];

        return $list;
    }
}
