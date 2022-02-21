<?php
namespace app\common\model;
use think\Model;

class BflMiningLevelConfig extends Model
{
    static function getConfigs() {
        $list = self::order('min_num desc')->select();
        if(!$list) return [];

        $result = [];
        foreach ($list as $item) {
            if(!isset($result[$item['currency_id']])) $result[$item['currency_id']] = [];
            $result[$item['currency_id']][] = $item;
        }
        return $result;
    }

    static function getPercent($num,$currency_level_config) {
        foreach ($currency_level_config as $level) {
            if($num>=$level['min_num']) return $level;
        }
        return null;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
