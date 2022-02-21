<?php
//房间列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomWhiteList extends Model
{
    public function level()
    {
        return $this->belongsTo(RoomWhiteSetting::class, 'rwl_level_id', 'rws_level')->field('rws_level, rws_win_num');
    }
}