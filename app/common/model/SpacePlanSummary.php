<?php
//跳跃排名倒序加权算法 - 算力
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class SpacePlanSummary extends Model
{
    const LOWER_NUM = 10000;
    const MULTIPLE = 10;
    static function addItem($member_id) {
        try{
            $info = self::where(['member_id'=>$member_id])->find();
            if($info) return true;

            $flag = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => SpacePlan::CURRENCY_ID,
                'total_num' => 0,
                'total_recommand' => 0,
                'total_power' => 0,
                'total_release' => 0,
                'power_stop_time' => 0,
            ]);
            if($flag===false) {
                return false;
            } else {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    //检测是否能拿到动力源
    static function powerCheck() {

    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
