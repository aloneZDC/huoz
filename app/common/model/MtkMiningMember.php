<?php

namespace app\common\model;

use think\Model;

class MtkMiningMember extends Model
{
    /**
     * @param $member_id
     * @param int $price_usdt
     * @return MtkMiningMember|bool|int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function add_item($member_id, $price_usdt = 0)
    {
        $info = self::where(['member_id' => $member_id])->find();
        if ($info) {
            $flag = true;
            if ($price_usdt > 0) {
                $flag = self::update([
                    'pay_num' => ['inc', $price_usdt],
                ], ['member_id' => $member_id]);
            }
        } else {
            $flag = self::create([
                'member_id' => $member_id,
                'pay_num' => $price_usdt,
                'add_time' => time(),
            ]);
        }
        return $flag;
    }

    /**
     * 增加直推业绩，入金和矿机个数
     * @param $member_id
     * @param int $num
     * @return MtkMiningMember|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function add_one_team_num($member_id, $num = 0)
    {
        $pid_member = Member::where(['member_id' => $member_id])->field('pid')->find();
        if (empty($pid_member) || $pid_member['pid'] <= 0) return true;

        $pid_mining = self::where(['member_id' => $pid_member['pid']])->find();
        if (empty($pid_mining)) return true;

        // 统计直推数据
        $mining_member = self::alias('a')
            ->join("member_bind b", "a.member_id = b.child_id AND b.`level` = 1")
            ->where('b.member_id = ' . $pid_member['pid'] . ' AND a.pay_num > 0')->field('COUNT(*) user_count,SUM(a.pay_num) num_total')->find();

        return self::where(['id' => $pid_mining['id'], 'one_team_total' => $pid_mining['one_team_total']])->update([
            'one_team_total' => ['inc', $num], // 直推业绩
            'one_team_count' => empty($mining_member['user_count']) ? 0 : $mining_member['user_count'], // 有效直推个数
        ]);
    }

    /**
     * 增加团队业绩 - 入金
     * @param $child_member_id
     * @param $num
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    public static function add_parent_team_num($child_member_id, $num)
    {
        return self::execute('update ' . config("database.prefix") . 'mtk_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_num = a.team_num + ' . $num . ' where a.member_id = b.member_id and b.child_id=' . $child_member_id . ';');
    }

    /**
     * 算力社区
     * @param int $member_id 用户id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function community_index($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        // IPFS矿机
        $one_team_count = CommonMiningMember::where(['member_id' => $member_id])->value('one_team_count', 0);
        $last_award_num = CommonMiningIncome::where(['member_id' => $member_id, 'type' => 6, 'award_time' => todayBeginTimestamp()])->sum('third_num');
        $total_award_num = CommonMiningIncome::where(['member_id' => $member_id, 'type' => 6])->sum('third_num');
        $r['result']['common'] = [
            'one_team_count' => keepPoint($one_team_count, 0),
            'last_award_num' => keepPoint($last_award_num, 6),
            'total_award_num' => keepPoint($total_award_num, 6),
            'currency_name' => 'FIL',
        ];

        // MTK矿机
        $one_team_count = MtkMiningMember::where(['member_id' => $member_id])->value('one_team_count', 0);
        $last_award_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 6, 'award_time' => todayBeginTimestamp()])->sum('third_num');
        $total_award_num = MtkMiningIncome::where(['member_id' => $member_id, 'type' => 6])->sum('third_num');
        $r['result']['mtk'] = [
            'one_team_count' => keepPoint($one_team_count,0),
            'last_award_num' => keepPoint($last_award_num, 6),
            'total_award_num' => keepPoint($total_award_num, 6),
            'currency_name' => 'MTK',
        ];
        $r['code'] = SUCCESS;
        return $r;
    }
}