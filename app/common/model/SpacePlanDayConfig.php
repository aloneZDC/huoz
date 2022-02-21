<?php
//跳跃排名倒序加权算法配置
namespace app\common\model;

use think\Model;

class SpacePlanDayConfig extends Model
{
    static function getDayConfig()
    {
        $list = self::order('day asc')->select();
        if (empty($list)) return [];

        return $list;
    }

    static function getSpaceDayConfig($dayConfigs,$day,$is_force=true) {
        if($is_force && $day<=0) return null;

        foreach ($dayConfigs as $config) {
            if($day<=$config['day']) return $config;
        }
        return null;
    }
}
