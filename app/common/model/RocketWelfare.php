<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;
use think\Log;

class RocketWelfare extends Base
{
    /**
     *添加抱彩分红
     * @param int $num    数量
     */
    static function addItem($num) {
        $flag = true;
        try{
            $info = self::find();
            if($info) {
                if ($num > 0) {
                    $flag = self::where(['id'=>$info['id']])->update([
                        'num' => ['inc', $num]
                    ]);
                }
            } else {
                $flag = self::insertGetId([
                    'name' => '抱彩分红',
                    'num' => $num,
                    'add_time' => time(),
                ]);
            }
            if ($flag === false) throw new Exception('添加抱彩分红失败');
        } catch (\Exception $e) {
            return false;
        }
        return $flag;
    }
}