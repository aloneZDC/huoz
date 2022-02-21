<?php
namespace app\common\model;
use think\Exception;
use think\Log;
use think\Model;

/**
 * BFL矿池记录表
 * Class BflPoolTask
 * @package app\common\model
 */
class BflPoolTask extends Model
{
    /**
     * @param $from 来源区块名称 例：BflPool::MINERAL
     * @param $to  目标区块名称 例：BflPool::HOLE
     * @param $num
     * @param string $third_type
     * @param string $third_id
     * @return int|string
     */
    static function addTask($currency_id,$from,$to,$num,$third_type='',$third_id='') {
        return self::insertGetId([
            'currency_id' => $currency_id,
            'from' => $from,
            'to' => $to,
            'num' => $num,
            'third_type' => $third_type,
            'third_id' => $third_id,
        ]);
    }
}
