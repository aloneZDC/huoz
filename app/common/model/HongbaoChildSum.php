<?php
//红包  下级直推数量
namespace app\common\model;

use think\Model;
class HongbaoChildSum extends Model
{
    static function getSumLog($user_id,$type,$today) {
        return self::where(['user_id'=>$user_id,'type'=>$type,'today'=>$today])->find();
    }

    /**
     * @param $type hongbao 红包 flop翻牌
     * @param $user_id
     * @param $num
     * @param $today 日期
     * @return int|string
     */
    static function add_log($type,$user_id,$child_num,$today) {
        return self::insertGetId([
            'type' => $type,
            'user_id' => $user_id,
            'today' => $today,
            'child_num' => $child_num,
        ]);
    }
}
