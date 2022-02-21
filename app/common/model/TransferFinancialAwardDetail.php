<?php
//理财包上级奖励明细

namespace app\common\model;


use think\Exception;
use think\Model;

class TransferFinancialAwardDetail extends Model
{
    static function add_item($user_id,$currency_id,$num,$asset_type,$time,$third_user_id,$base_num,$percent,$level) {
        return self::insertGetId([
            'tta_user_id'  => $user_id,
            'tta_currency_id'  => $currency_id,
            'tta_num'  => $num,
            'tta_time'  => time(),
            'tta_asset_type'  => $asset_type,
            'tta_day' => $time,
            'tta_third_user_id' => $third_user_id,
            'tta_base_num' => $base_num,
            'tta_percent' => $percent,
            'tta_level' => $level,
        ]);
    }
}