<?php

namespace app\common\model;

class MtkMiningOrder extends Base
{
    /**
     * 我的购买列表
     * @param $member_id
     * @param int $page
     * @param int $rows
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function order_list($member_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        $release_currency_id = MtkMiningConfig::get_value('release_currency_id', 93);
        $list = self::alias('a')
            ->join('currency b', 'b.currency_id = ' . $release_currency_id, 'left')
            ->join('mtk_mining_product p', 'p.id = a.product_id', 'left')
            ->field(['a.id', 'a.total_number', 'a.total_power', 'a.surplus_power', 'a.last_release_num', 'a.total_release_num'])
            ->field(['b.currency_name'])
            ->field(['p.name'])
            ->where(['a.member_id' => $member_id])
            ->page($page, $rows)->order(['a.id' => 'asc'])
            ->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['status'] = 0;
            if ($item['surplus_power'] <= 0) {
                $item['status'] = 1;// 状态 0产出中 1已完成
            }
            $total_release_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 4, 'third_id' => $item['id']])->sum('third_num');
            $item['total_release_num'] = keepPoint($total_release_num, 6);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 杠杆扣除
     * @param int $base_member_id 用户id
     * @param float $total_award_num 扣除数据
     * @return MtkMiningOrder|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function service_reward($base_member_id, $total_award_num)
    {
        if (empty($base_member_id)
            || empty($total_award_num)
        ) {
            return false;
        }

        // 查询订单
        $release_currency_id = MtkMiningConfig::get_value('release_currency_id', 93);
        $mining_order = self::where(['member_id' => $base_member_id, 'currency_id' => $release_currency_id, 'surplus_power' => ['gt', 0]])->find();
        if (empty($mining_order)) {
            return true;
        }

        // 当余额足够
        if ($mining_order['surplus_power'] >= $total_award_num) {
            return self::where(['id' => $mining_order['id']])->update([
                'surplus_power' => ['dec', $total_award_num]
            ]);
        }

        // 当余额不足
        $flag = self::where(['id' => $mining_order['id']])->update([
            'surplus_power' => 0
        ]);
        if ($flag === false) return false;

        $total_award_num = keepPoint($total_award_num - $mining_order['surplus_power'], 6);
        return self::service_reward($base_member_id, $total_award_num);
    }
}