<?php
namespace app\common\model;
use think\Model;

class GroupMiningSourceLevel extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    //获取所有矿源
    static function getLevelList()
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");

        $select = self::where(['type'=>1])->with(['currency'])->order('level_id', 'DESC')->select();
        $list = [];
        if (count($select)) {
            foreach ($select as $key => $value) {
                $index = $value['type'] == 2 ? 0 : $value['level_id'] - 1;
                unset($value['type']);
                unset($value['win_percent']);
                $value['price'] = floattostr($value['price']);
                $value['index'] = $index;
                $list[] = $value;
            }
        }
        $r['result'] = $list;

        return $r;
    }

    //获取矿源信息
    static function getLevelInfo($level_id) {

        return self::get($level_id);
    }
}
