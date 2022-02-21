<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use app\common\model\ChiaMiningPay;
use app\common\model\ChiaMiningRelease;

class ChiaMiningMemberSummary extends Model
{
	/**
     * 添加每日汇总
     * @param int $member_id 用户ID
     * @param string $today 日期
     * @param int $level 当前等级
     * @param int $team1_num 业绩级差
     * @param int $team2_num 业绩平级
     * @param int $team3_num 挖矿级差
     * @param int $team4_num 挖矿平级
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	static function addSummary($member_id, $today, $level, $team1_num=0, $team2_num=0, $team3_num=0, $team4_num=0) {
        try{
            $info = self::where(['member_id'=>$member_id, 'today' => $today])->find();
            
            if($info) {
                $flag = true;
                if ($team1_num > 0 || $team2_num > 0 || $team3_num > 0 || $team4_num > 0) {
                    $flag = self::where(['member_id'=>$member_id, 'today' => $today])->update([
                        'team1_num' => ['inc',$team1_num],
                        'team2_num' => ['inc',$team2_num],
                        'team3_num' => ['inc',$team3_num],
                        'team4_num' => ['inc',$team4_num]
                    ]);
                }
            } else {
            	$yestday_start = strtotime($today);
            	$yestday_stop = $yestday_start + 86399;
            	$res = ChiaMiningPay::field('sum(tnum) as pay_tnum,sum(real_pay_num) as pay_num')->where(['member_id' => $member_id, 'add_time' => ['between', [$yestday_start, $yestday_stop]]])->find();
            	$teamInfo = ChiaMiningPay::alias('a')->field('sum(a.tnum) as pay_tnum,sum(a.real_pay_num) as pay_num')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $member_id, 'a.add_time' => ['between', [$yestday_start, $yestday_stop]]])->find();
            	$team_release_num = ChiaMiningRelease::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $member_id, 'a.release_time' => ['between', [$yestday_start, $yestday_stop]]])->sum('a.num');
            	$release_num = ChiaMiningRelease::where(['member_id' => $member_id, 'release_time' => ['between', [$yestday_start, $yestday_stop]]])->sum('num');
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'pay_tnum' => !empty($res['pay_tnum']) ? $res['pay_tnum'] : 0,
                    'pay_num' => !empty($res['pay_num']) ? $res['pay_num'] : 0,
                    'level' => $level,
                    'today' => $today,
                    'team_num' => !empty($teamInfo['pay_num']) ? $teamInfo['pay_num'] : 0,
                    'team_tnum' => !empty($teamInfo['pay_tnum']) ? $teamInfo['pay_tnum'] : 0,
                    'release_num' => $release_num,
                    'team_release_num' => $team_release_num,
                    'team1_num' => $team1_num,
                    'team2_num' => $team2_num,
                    'team3_num' => $team3_num,
                    'team4_num' => $team4_num,
                    'add_time' => time(),
                ]);
            }

            return $flag;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 每日业绩（每日挖矿）
     * @param int $member_id 用户ID
     * @param int $type 类型 1业绩 2挖矿
     * @param int $page 当前页
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function achievement_list($member_id, $type, $page=1, $page_size=10) {
    	$r = ['code' => SUCCESS, 'message' => lang('success_operation'), 'result' => null, 'total' => 0];
    	$where = [];
    	$where['member_id'] = $member_id;
    	if ($type == 1) {
    		$where['team_num'] = ['gt', 0];
    	} else {
    		$where['team_release_num'] = ['gt', 0];
    	}

    	$result = self::field('team_num,team_release_num,today,add_time')->where($where)
	    	->order('id DESC')->page($page, $page_size)->select();
    	if (empty($result)) return $r;

    	foreach ($result as &$value) {
    		if ($type == 1) {//业绩
    			$value['num'] = $value['team_num'];
    			$value['title'] = date('m月d日',strtotime($value['today'])) . '新增业绩';
    			$value['currency_name'] = ChiaMiningConfig::where(['key' => 'reward_achievement_unit'])->value('value');
    		} else {//挖矿
    			$value['num'] = $value['team_release_num'];
    			$value['title'] = date('m月d日',strtotime($value['today'])) . '新增挖矿';
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

    /**
     * 每日收益
     * @param int $member_id 用户ID
     * @param int $type 类型 1业绩 2挖矿
     * @param int $page 当前页
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function award_list($member_id, $type, $page=1, $page_size=10) {
    	$r = ['code' => SUCCESS, 'message' => lang('success_operation'), 'result' => null, 'total' => 0];
    	$result = self::field('team1_num,team2_num,team3_num,team4_num,today,add_time')->where(['member_id' => $member_id])
    	->where(function($query) use($type){
	    		if ($type == 1) {
	    			$query->where(['team1_num' => ['gt', 0]])->whereOr(['team2_num' => ['gt', 0]]);
	    		} else {
	    			$query->where(['team3_num' => ['gt', 0]])->whereOr(['team4_num' => ['gt', 0]]);
	    		}	
	    	})
    	->order('id DESC')->page($page, $page_size)->select();
    	if (empty($result)) return $r;

    	foreach ($result as &$value) {
    		if ($type == 1) {//业绩
    			$value['num'] = sprintf('%.6f', $value['team1_num'] + $value['team2_num']);
    			$value['title'] = date('m月d日',strtotime($value['today'])) . '业绩奖池收益';
    			$value['currency_name'] = ChiaMiningConfig::where(['key' => 'reward_achievement_unit'])->value('value');
    		} else {//挖矿
    			$value['num'] = sprintf('%.6f', $value['team3_num'] + $value['team4_num']);
    			$value['title'] = date('m月d日',strtotime($value['today'])) . '挖矿奖池收益';
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
