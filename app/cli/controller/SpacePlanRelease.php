<?php
namespace app\cli\controller;
use app\common\model\SpacePlan;
use app\common\model\SpacePlanConfig;
use app\common\model\SpacePlanDayConfig;
use app\common\model\SpacePlanPower;
use app\common\model\SpacePlanPowerDetail;
use app\common\model\UserAirLevel;
use think\Db;
use think\Log;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 太空计划每日释放
 */
class SpacePlanRelease extends Command
{
    public $name = '太空计划每日释放';
    protected $today_config = [];
    protected $day_configs = [];
    protected $currency_ratio = 0;

    protected function configure()
    {
        $this->setName('SpacePlanRelease')->setDescription('This is a SpacePlanRelease');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();

        //清空数据 重新跑
//        Db::execute("update yang_space_plan set total_release=0,today_release=0,status=1;");
//        Db::execute("TRUNCATE table yang_space_plan_release;");
//        Db::execute("TRUNCATE table yang_space_plan_power;");
//        Db::execute("TRUNCATE table yang_space_plan_power_detail;");
//        Db::execute("update yang_space_plan_summary set total_power=0,total_release=0;");

        //模拟重新跑90天
//        $start_day = date('Y-m-d');
//        $start_time = strtotime($start_day);
//        $stop_time = $start_time + 86400 *90;
//
//        $stop_time += 86400 * 10;
//        while ($start_time<=$stop_time) {
//            $this_day = date('Y-m-d',$start_time);
//            $this->doRun($this_day);
//
//            $start_time += 86400;
//            echo $this_day."\r\n";
//            sleep(1);
//        }
    }

    public function doRun($today='') {
        if(empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name." 开始 ");
        $this->day_configs = SpacePlanDayConfig::getDayConfig();
        if(empty($this->day_configs)) {
            Log::write($this->name." 日释放配置获取失败 ");
            return;
        }

        $space_plan_config = SpacePlanConfig::get_key_value();
        if(empty($space_plan_config)) {
            Log::write($this->name." 配置获取失败 ");
            return;
        }

        $this->currency_ratio = SpacePlan::getRadio();
        if($this->currency_ratio<=0) {
            Log::write($this->name." 转换比例获取失败 ");
            return;
        }

        //释放
        $this->release($space_plan_config);
        //动力源 释放奖励
        $this->release_award($space_plan_config);

        //设置已废弃
        SpacePlan::where(['stop_time'=>$this->today_config['today_start'],'status'=>SpacePlan::STATUS_OK])->setField('status',SpacePlan::STATUS_OUT);

        Log::write($this->name." 结束 ");
    }

    //释放
    protected function release($space_plan_config) {
        $last_id = \app\common\model\SpacePlanRelease::where(['add_time'=>['gt',$this->today_config['today_start']] ])->max('third_id');
        while (true) {
            echo "release:".$last_id."\r\n";
            $space_plan = SpacePlan::where([
                'id'=>['gt',$last_id],
                'status'=> SpacePlan::STATUS_OK,
            ])->order('id asc')->find();
            if(empty($space_plan)) {
                Log::write($this->name." 结束");
                break;
            }

            $last_id = $space_plan['id'];

            if($space_plan['start_time']>$this->today_config['today_start']) continue;
            if($space_plan['stop_time']<$this->today_config['today_start']) continue;

            //获取加入天数
            $space_plan_day = SpacePlan::getSpacePlanDays($this->today_config['today_start'],$space_plan['start_time']);
            $space_plan_day_config = SpacePlanDayConfig::getSpaceDayConfig($this->day_configs,$space_plan_day);
            if($space_plan_day_config==null) continue;

            $release_percent = $space_plan_day_config['percent'];
            $user_air_level = UserAirLevel::where(['user_id'=>$space_plan['member_id']])->find();
            if($user_air_level && $user_air_level['level_id']>=$space_plan_config['space_air_min_level_id']){
                $release_percent = $space_plan_day_config['air_percent'];
            }
            \app\common\model\SpacePlanRelease::release($space_plan,$release_percent,$space_plan_config,$this->today_config,$this->currency_ratio);
        }
    }

    //上级动力源奖励
    protected function release_award($space_plan_config) {
        Log::write($this->name." release_award 开始");

        $last_id = \app\common\model\SpacePlanPower::where(['add_time'=>['gt',$this->today_config['today_start']] ])->max('member_id');
        while (true) {
            echo "release_award:".$last_id."\r\n";
            $power_detail = SpacePlanPowerDetail::where([
                'member_id' => ['gt',$last_id],
                'add_time'=> $this->today_config['today_start']
            ])->order('member_id asc')->find();
            if(empty($power_detail)) {
                Log::write($this->name." release_award 结束");
                break;
            }

            $last_id = $power_detail['member_id'];

            $sum = SpacePlanPowerDetail::where([
                'member_id' => $power_detail['member_id'],
                'add_time'=> $this->today_config['today_start']
            ])->sum('num');
            if($sum && $sum>=0.000001) {
                SpacePlanPower::award($power_detail['member_id'],$power_detail['currency_id'],$sum,$space_plan_config,$this->currency_ratio);
            }
        }
    }
}
