<?php


namespace app\common\model;


class YunfastpayLog extends Base
{
    /*
     * 创建记录
     * @param int $gmo_id       订单id
     * @param int $member_id    用户id
     * @param array $content    数据
     * @param int $type         类型 1传参 2返回
     */
    static function addItem($gmo_id, $member_id, $content, $type) {
        if (empty($gmo_id) || empty($content)) {
            return false;
        }
        $data = [
            'type' => $type,
            'gmo_id' => $gmo_id,
            'member_id' => $member_id,
            'content' => json_encode($content),
            'add_time' => time()
        ];

        return self::create($data);
    }
}