<?php
//chia等级配置

namespace app\common\model;

class ChiaMiningLevelConfig extends Base
{
    // 获取所有等级
    static function getAllLevel() {
        $all = self::where(['level_id'=>['gt',0]])->order('level_id asc')->select();
        if(empty($all)) return [];

        return $all;
    }

    static function getGlobalLevel() {
        $find = self::where(['level_global'=> 1])->order('level_id desc')->find();
        if(empty($find)) return [];

        return $find;
    }

    /**
     * 获取团队等级
     * @param int $member_id 用户ID
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function getTeamLevel($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        
        $result = ChiaMiningMember::where(['member_id' => $member_id])->find();
        if (empty($result)) return $r;

        $res = self::field('level_id,level_name')->where(['level_id' => ['lt', $result['level']]])->select();
        if (!empty($res)) {
            foreach($res as &$value) {
                $value['level_name'] = $value['level_name'] . '矿工';
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $res;
        
        return $r;
    }
}
