<?php
//游戏参数设置表
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class FlopWhite extends Base
{
    //获取是否白名单
    public static function check($member_id)
    {
        $find = self::where(['member_id'=>$member_id])->find();
        if($find) return true;
        return false;
    }
}
