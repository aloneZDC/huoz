<?php


namespace app\common\model;


use think\Model;

class AirEditLevelLog extends Model
{
    /**
     * @param $userId
     * @param $childId
     * @param $levelId
     * @return int|string
     */
    public static function add_log($userId, $childId, $levelId)
    {
        return (new self)->insertGetId([
            'user_id' => $userId,
            'child_id' => $childId,
            'level_id' => $levelId,
            'add_time' => time()
        ]);
    }
}