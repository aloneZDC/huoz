<?php
//Fil项目 涡轮增压 配置
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class FilMiningConfig extends Model
{
    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];

        return array_column($list, 'value', 'key');
    }

    static function getValue($key,$default) {
        $info = self::where(['key'=>$key])->find();
        if($info) return $info['value'];
        return $default;
    }
}
