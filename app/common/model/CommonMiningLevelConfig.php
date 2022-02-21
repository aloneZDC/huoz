<?php
//Fil项目 入金数量及释放比例

namespace app\common\model;

class CommonMiningLevelConfig extends Base
{
    // 获取所有等级
    static function getAllLevel() {
        $all = self::where(['level_id'=>['gt',0]])->order('level_id asc')->select();
        if(empty($all)) return [];

        return $all;
    }

    static function getGlobalLevel() {
        $find = self::where(['level_global'=> 1])->order('level_id desc')->find();
        if(empty($find)) return [];

        return $find;
    }
}
