<?php
//游戏参数设置表
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class GameConfig extends Base
{
    /**
     * 获取配置
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function get_value($key, $default = "")
    {
        $find = (new self)->where('gc_key', $key)->find();
        if (empty($find)) {
            return $default;
        }
        return $find['gc_value'];
    }
}