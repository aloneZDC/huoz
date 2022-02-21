<?php
//太空B计划汇总 节点
namespace app\common\model;

use think\Exception;
use think\Model;

class CurrencyNodeLockSummary extends Model
{
    static function addItem($member_id,$currency_id) {
        try{
            $info = self::where(['member_id'=>$member_id])->find();
            if($info) return true;

            $flag = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'total_num' => 0,
                'total_lock_num' => 0,
                'total_recommand' => 0,
                'total_release' => 0,
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

    static function getItem($member_id) {
        $item = self::where(['member_id'=>$member_id])->field('total_num,total_lock_num,total_recommand,total_release')->find();
        if(!$item) return ['total_num'=>0,'total_lock_num'=>0,'total_recommand'=>0,'total_release'=>0];

        $item['total_lock_num'] = intval($item['total_lock_num']);
        return $item;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
