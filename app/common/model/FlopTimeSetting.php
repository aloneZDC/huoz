<?php
//翻牌 交易时间限制
namespace app\common\model;
use think\Exception;
use think\Model;

class FlopTimeSetting extends Model
{
    //检测是否处于可交易时间
    static function check() {
        $hour = date('H');
        $info = self::where([
            'start_hour' => ['elt',$hour],
            'stop_hour' => ['gt',$hour],
        ])->find();
        return $info;
    }
}
