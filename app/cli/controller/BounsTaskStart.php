<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *静态分红定时任务
 */
class BounsTaskStart extends Command
{
    protected function configure()
    {
        $this->setName('BounsTaskStart')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');


        //7是16号 跑的15号的新增业绩  5是18号,跑的是17号的新增业绩
        // $days = 8;
        // $task = new BounsDynamicTask();
        // $bounsWeekTask = new BounsWeekTask();
        // $levelClass = new MemberLevelTask();
        // while ($days>=6) {
        //     $task->subtract = $days*86400;
        //     $task->same_day();
        //     $flag = $bounsWeekTask->everyDay($task->same_day);
        //     if($flag) {
        //         Log::write($task->same_day['today'].'新增业绩已跑');
        //     } else {
        //         Log::write($task->same_day['today'].'新增业绩失败');
        //     }
        //     $days--;
        // }
        // for ($i=0; $i < 6; $i++) { 
        //     $levelClass->update_level($task->same_day,$i);
        // }
        // exit;

        // $days = 4;
        // $task = new BounsDynamicTask();
        // $bounsWeekTask = new BounsWeekTask();
        // $levelClass = new MemberLevelTask();
        // while ($days>=1) {
        //     //动态奖励结算
        //     $task->subtract = $days*86400;
        //     $task->same_day();
        //     $task->doRun();

        //     //静态奖励结算
        //     $bounsTask = new BounsTask();
        //     $bounsTask->subtract = $days*86400;
        //     $bounsTask->doRun();

        //     //奖励汇总
        //     $this->total($task->same_day);

        //     //新增业绩
        //     $bounsWeekTask->everyDay($task->same_day);

        //     //用户升级
        //     for ($i=0; $i < 6; $i++) { 
        //         $levelClass->update_level($task->same_day,$i);
        //     }

        //     $days--;
        // }
        // exit;

        #线上yang_boss_bouns_log 清除19号的记录

        //今天是23号 从19号进行结算3 20号2
        $days = 0;
        $task = new BounsDynamicTask();
        $bounsWeekTask = new BounsWeekTask();
        $levelClass = new MemberLevelTask();
        while ($days>=0) {
            //动态奖励结算
            $task->subtract = $days*86400;
            $task->same_day();
            $task->doRun();


            //静态奖励结算
            $bounsTask = new BounsTask();
            $bounsTask->subtract = $days*86400;
            $bounsTask->doRun();


            //奖励汇总
            $this->total($task->same_day);


            //新增业绩
            $bounsWeekTask->everyDay($task->same_day);

            //用户升级
            for ($i=0; $i < 6; $i++) {
                $levelClass->update_level($task->same_day,$i);
            }

            $days--;
        }
    }

    private function total($same_day) {
        try{
            $total_data = [
                'pay_number' => 0,
                'xrp_num' => 0,
                'xrpz' => 0,
                'xrpj' => 0,
                'bouns_num' => 0,
                'base_num' => 0,
                'num1' => 0,
                'num2' => 0,
                'num3' => 0,
                'num4' => 0,
                'num5' => 0,
                'num6' => 0,
                'num7' => 0,
                'num8' => 0,
                'num9' => 0,
                'num10' => 0,
                'add_time' => $same_day['start_time'],
            ];
            $total = Db::name('boss_bouns_log')->field('sum(num) as num,type')->where(['add_time'=>$same_day['start_time']])->group('type')->select();
            if($total) {
                foreach ($total as $t) {
                    $type = intval($t['type']);
                    if($type>0 && $type<=10) {
                        $total_data['num'.$t['type']] = floatval($t['num']);
                        if($type<6){
                            $total_data['base_num'] += floatval($t['num']);
                        } else {
                            $total_data['bouns_num'] += floatval($t['num']);
                        }
                    }
                }
            } 
            //昨日入金
            $yestoday_pay = Db::name('boss_plan_buy')->field('sum(pay_number) as pay_number,sum(xrp_num) as xrp_num,sum(xrpz) as xrpz,sum(xrpj) as xrpj')->where(['add_time'=>['between',[$same_day['yestoday_start'],$same_day['yestoday_stop']]],'status'=>1])->find();
            if(!empty($yestoday_pay['pay_number'])) {
                $total_data['pay_number'] = floatval($yestoday_pay['pay_number']);
                $total_data['xrp_num'] = floatval($yestoday_pay['xrp_num']);
                $total_data['xrpz'] = floatval($yestoday_pay['xrpz']);
                $total_data['xrpj'] = floatval($yestoday_pay['xrpj']);
            }
            $flag = Db::name('boss_bouns_total')->insertGetId($total_data);
            if($flag) {
                Log::write("汇总完成");
            } else {
                Log::write("汇总失败");
            }
        } catch(Exception $e){
            Log::write("汇总出错");
        }
    }
}
