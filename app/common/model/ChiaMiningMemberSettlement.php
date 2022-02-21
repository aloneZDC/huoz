<?php
// 用户收益结算
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use app\common\model\ChiaMiningPay;
use app\common\model\ChiaMiningConfig;

class ChiaMiningMemberSettlement extends Model
{
	/**
     * 更新收益结算
     * @param int $member_id 用户ID
     * @param string $today 日期
     * @param array $mining_info 数据
     * @return int
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */ 
	static function addSettlement($member_id, $today, $mining_info) {
        $res = self::where(['member_id' => $member_id, 'today' => $today])->find();
        $personal_num = sprintf('%.6f', $mining_info['team1_num'] + $mining_info['team2_num']);//个人业绩收益
        $release_num = sprintf('%.6f', $mining_info['team3_num'] + $mining_info['team4_num']);//个人挖矿收益
        if ($res) {
            self::where(['member_id' => $member_id, 'today' => $today])->update([
                'pay_num' => ['inc', $mining_info['pay_num']],
                'pay_tnum' => ['inc', $mining_info['pay_tnum']],
                'personal_num' => ['inc', $personal_num],
                'release_num' => ['inc', $release_num],
            ]);
            $item_id = $res['id'];
        } else {
            $add_time = strtotime($today."+1 day");
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'today' => $today,
                'pay_num' => $mining_info['pay_num'],
                'pay_tnum' => $mining_info['pay_tnum'],
                'personal_num' => $personal_num,
                'release_num' => $release_num,
                'add_time' => $add_time
            ]);
        }

        //增加团队业绩、挖矿
        self::addTeamNum($member_id, $today, $personal_num, $release_num);
        return $item_id;
    }

	/**
     * 增加团队业绩、挖矿
     * @param int $child_member_id 用户ID
     * @param int $num 业绩级差
     * @param int $release_num 挖矿级差
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addTeamNum($child_member_id, $today, $num=0, $release_num=0)
    {
    	return self::execute('update ' . config("database.prefix") . 'chia_mining_member_settlement a,' . config("database.prefix") . 'member_bind b
            set a.team_num=a.team_num+' .$num. ', a.team_release_num=a.team_release_num+' .$release_num. ' where a.member_id = b.member_id and  b.child_id=' . $child_member_id . " and a.today='".$today."';");
    }

	/**
     * 累计奖池
     * @param int $member_id 用户ID
     * @param int $type 类型 1业绩 2挖矿
     * @param int $page 当前页
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */ 
    static function total_award_list($member_id, $type, $page=1, $page_size=10) {
        $r = ['code' => SUCCESS, 'message' => lang('success_operation'), 'result' => null, 'total' => 0];
        $where = [];
        $where['member_id'] = $member_id;
        if ($type == 1) {
            $where['personal_num'] = ['gt', 0];
        } else {
            $where['release_num'] = ['gt', 0];
        }
        $result = self::field('personal_num,release_num,today,add_time')->where($where)->order('id DESC')->page($page, $page_size)->select();
        if (empty($result)) return $r;

        foreach ($result as &$value) {
            if ($type == 1) {//业绩
                $value['num'] = $value['personal_num'];
                $value['title'] = '10日业绩奖池';
                $value['currency_name'] = ChiaMiningConfig::where(['key' => 'reward_achievement_unit'])->value('value');
            } else {//挖矿
                $value['num'] = $value['release_num'];
                $value['title'] = '10日挖矿奖池';
                $value['currency_name'] = ChiaMiningConfig::where(['key' => 'reward_release_unit'])->value('value');
            }
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        $r['total'] = count($result);
        return $r;
    }
}