<?php


namespace app\backend\model;

use think\Db;
use think\Model;

class BackendInformation extends Model
{
    /**
     * 资讯列表
     * @param int $member_id        用户ID
     * @param int $page 页
     * @param int $rows 页数
     * @return array
     */
    static function getInformationList(int $member_id, int $page, $rows = 10) {
        $r = ['code' => ERROR1, 'message' => lang("no data"), 'result' => null];

        $result = self::where(['status' => 1])->page($page, $rows)->order('add_time desc')->select();
        if (empty($result)) return $r;

        foreach ($result as &$value) {
            $value['add_time'] = !empty($value['add_time']) ? date('Y-m-d H:i', $value['add_time']) : '';
            $value['file_infos'] = json_decode($value['file_infos'], true);
            $value['openFlag'] = false;//内容缩放
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        return $r;
    }
}