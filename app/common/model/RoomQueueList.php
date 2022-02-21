<?php
//房间排队列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomQueueList extends Model
{
    /**
     * 状态枚举
     * @var array
     */
    const STATUS_ENUM = [
        1 => "排队中",
        2 => "占位成功",
        3 => "退出房间"
    ];

    public function user()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'rql_member_id', 'member_id')/*->field('user_nickname,user_email,user_phone')*/;
    }
}