<?php

namespace app\common\model;

use think\Model;

class YunMiningIncome extends Model
{
    // 收益记录
    static function machine_income($member_id, $id, $page)
    {
        $r = ['code' => ERROR1, 'message' => lang("no_data"), 'result' => null];
        if ($id <= 0) return $r;

        $result = self::where([
            'member_id' => $member_id,
            'third_id' => $id,
            'type' => ['in', [1, 2, 4]]
        ])->field('id,type as title,num,add_time')->order('id desc')
            ->page($page, 10)->select();
        if (empty($result)) return $r;
        $type = [1 => '提取额度', 2 => '加速收益', 4 => '收益回流'];
        foreach ($result as &$item) {
            $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
            $num = keepPoint($item['num'], 4);
            if (in_array($item['title'], [1, 4])) {
                $item['num'] = '-' . $num;
            } else {
                $item['num'] = '+' . $num;
            }
            $item['title'] = $type[$item['title']];
            $item['currency_name'] = 'MTK';
        }

        $r['result'] = $result;
        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }
}