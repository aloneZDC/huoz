<?php


namespace app\common\model;


use think\Model;

class AirJackpotLog extends Model
{
    public static function add_log($levelName, $radio, $number, $persons)
    {
        return (new self)->insertGetId([
            'level_name' => $levelName,
            'radio' => $radio,
            'number' => $number,
            'persons' => $persons,
            'add_time' => time()
        ]);
    }
}