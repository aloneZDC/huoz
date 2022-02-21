<?php
//涡轮 级差奖励详情

namespace app\common\model;

use think\Db;
use think\Log;

class FilMiningLevelIncomeDetail extends Base
{
    static function award_detail($fil_mining_pay,$all_levels,$fil_mining_config,$today_start) {
        $has_award_percent = 0;
        $has_award_level = 0;
        $base_member_id = $fil_mining_pay['member_id'];
        while (true) {
            // 查找等级大于我的 离我最近的上级
            $next_pid_level = Db::name('member_bind')->field('b.id,b.member_id,b.currency_id,b.level,b.release_num_avail,b.pay_num,b.release_num_total,b.total_release,b.total_child1,b.total_child2,total_child3,total_child4,total_child5,total_child11,total_child12,total_child13,total_child15,total_child16')->alias('a')
                ->join(config('database.prefix').'fil_mining b','a.member_id=b.member_id','LEFT')
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
                $award_num = keepPoint($fil_mining_pay['pay_num'] * $award_percent / 100,6);

                //20200118修改为推荐+级差 封顶9倍
                $max_award_num = FilMining::getNewHelpAwardLimit($next_pid_level,$fil_mining_config);
                $award_num = min($award_num,$max_award_num);
                if($award_num>0.000001) {
                    $flag = self::insertGetId([
                        'member_id' => $next_pid_level['member_id'],
                        'currency_id' => $next_pid_level['currency_id'],
                        'num' => $award_num,
                        'award_time' => $today_start,
                        'add_time' => time(),
                        'third_percent' => $award_percent,
                        'third_num' => $fil_mining_pay['pay_num'],
                        'third_id' => $fil_mining_pay['id'],
                        'third_member_id' => $fil_mining_pay['member_id'],
                    ]);
                    if(!$flag) {
                        Log::write("级差奖励详情插入失败".$fil_mining_pay['id']." ".$next_pid_level['member_id']);
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
