<?php
//房间消息列表
namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class RoomMsgList extends Model
{
    public function room()
    {
        return $this->belongsTo(RoomList::class, 'rml_room_id', 'rl_room_id');
    }

    public function user()
    {
        return $this->belongsTo('app\\common\\model\\Users', 'rml_user_id', 'user_id')/*->field('user_nickname,user_email,user_phone')*/;
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
            'rml_room_id'=>$room_id,
            'rml_member_id'=>$user_id,
            'rml_content'=>userTextEncode($content),
            'rml_create_time'=>time(),
        ]);
        if (!$res) return false;
        return true;
    }
}