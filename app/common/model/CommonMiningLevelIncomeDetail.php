<?php
//传统矿机 级差奖励详情

namespace app\common\model;

use think\Db;
use think\Log;

class CommonMiningLevelIncomeDetail extends Base
{
    static function award_detail($common_mining_pay,$common_mining_config,$all_levels,$today_start) {
        $has_award_percent = 0;
        $has_award_level = 0;
        $base_member_id = $common_mining_pay['member_id'];

        $base_num = $common_mining_pay['pay_num'];
        if($common_mining_config['income_type']=='fixed') {
            // 固定奖励 /T
            $fixed_num = $common_mining_pay['tnum'] * $common_mining_config['fixed_level'];
            $base_num = min($base_num,$fixed_num);
        }

        while (true) {
            // 查找等级大于我的 离我最近的上级
            $next_pid_level = Db::name('member_bind')->field('b.id,b.member_id,b.level')->alias('a')
                ->join(config('database.prefix').'common_mining_member b','a.member_id=b.member_id','LEFT')
                ->where(['a.child_id'=> $base_member_id,'b.level'=> ['gt',$has_award_level]])
                ->order('a.level asc')->find();
            if(empty($next_pid_level)) break;

            if(!isset($all_levels[$next_pid_level['level']]) || $all_levels[$next_pid_level['level']]['level_award']!=1) {
                break;
            }

            $has_award_level = $next_pid_level['level']; //已经奖励的级别
            $level_percent = $all_levels[$next_pid_level['level']]['level_percent']; //当前等级的奖励比例
            $award_percent = $level_percent - $has_award_percent; // 实际能获得的比例
            if($award_percent>0) {
                $has_award_percent = $level_percent;
                $award_num = keepPoint($base_num * $award_percent / 100,6);
                if($award_num>0.000001) {
                    $flag = self::insertGetId([
                        'member_id' => $next_pid_level['member_id'],
                        'currency_id' => $common_mining_pay['pay_currency_id'],
                        'num' => $award_num,
                        'award_time' => $today_start,
                        'add_time' => time(),
                        'third_percent' => $award_percent,
                        'third_num' => $base_num,
                        'third_id' => $common_mining_pay['id'],
                        'third_member_id' => $common_mining_pay['member_id'],
                    ]);
                    if(!$flag) {
                        Log::write("级差奖励详情插入失败".$common_mining_pay['id']." ".$next_pid_level['member_id']);
                    }
                }
            }
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function thirdmember() {
        return $this->belongsTo('app\\common\\model\\Member', 'third_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}
