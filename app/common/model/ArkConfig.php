<?php


namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;
use think\Log;

class ArkConfig extends Model
{
    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];

        return array_column($list, 'value', 'key');
    }

    static function getValue($key, $default = 0) {
        $info = self::where(['key'=>$key])->find();
        if($info) return $info['value'];
        return $default;
    }
}