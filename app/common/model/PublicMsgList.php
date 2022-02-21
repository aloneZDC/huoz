<?php
//公共消息列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class PublicMsgList extends Model
{
    public function room()
    {
        return $this->belongsTo(RoomList::class, 'pml_room_id', 'rl_room_id');
    }

    public function user()
    {
        return $this->belongsTo('app\\common\\model\\Users', 'pml_user_id', 'user_id')/*->field('user_nickname,user_email,user_phone')*/;
    }

    /**
     * 添加消息
     * @param $room_id
     * @param $user_id
     * @param $content
     * @return bool
     */
    static public function add_msg($room_id, $user_id, $content)
    {
        $res = self::create([
            'pml_member_id'=>$user_id,
            'pml_content'=>userTextEncode($content),
            'pml_room_id'=>$room_id,
            'pml_create_time'=>time(),
        ]);
        if (!$res) return false;
        return true;
    }
}