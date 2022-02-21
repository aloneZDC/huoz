<?php

namespace app\common\model;

use think\Model;

class MtkMiningIncome extends Model
{
    /**
     * 获取奖励记录
     * @param int $member_id 用户id
     * @param array $type 类型
     * @param int $page 页
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function income_list($member_id, $type = [], $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];

        $third_type = 0;
        if (!is_array($type)
            && $type == 7) {
            $type = [6, 7];
            $third_type = 7;
        }

        $where = ['a.member_id' => $member_id, 'a.num' => ['gt', 0]];
        if (!empty($type)) $where['a.type'] = ['in', $type];

        $list = self::alias('a')->field('a.id,a.currency_id,a.third_num,a.lock_num,a.num,a.type,a.add_time,b.currency_name,m.ename')
            ->join("currency b", "a.currency_id=b.currency_id", "left")
            ->join("member m", "a.third_member_id=m.member_id", "left")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            if ($third_type == 7
                && $item['type'] == 6) {
                $item['num'] = $item['lock_num'];
            }

            $item['title'] = lang('common_mining_award' . $item['type']);
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            if (!$item['ename']) $item['ename'] = '';
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }
}