<?php
namespace app\cli\controller;


use app\common\model\FlopTrade;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoLog;
use think\Log;
use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 下级每交易10000 直推上级增加
 * 同时处理方舟  和 红包
 */
class FlopChildNum extends Command
{
    public $name = '方舟红包下级数量';
    protected $today_config = [];

    protected function configure()
    {
        $this->setName('FlopChildNum')->setDescription('This is a FlopChildNum');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun() {
        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday' => date('Y-m-d',$today_start - 86400),
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name." 开始");

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config)) {
            Log::write($this->name.' 红包获取配置失败');
            return;
        }

        $flag = \app\common\model\FlopChildNum::reload_today();
        if(!$flag) {
            Log::write($this->name.' 清除昨日数据失败');
            return;
        }

        $this->flop($hongbao_config);
        $this->hongbao(($hongbao_config));
        Log::write($this->name.' 结束');
    }

    private function flop($hongbao_config) {
        if($hongbao_config['flop_day_count_one_num']<=0) {
            Log::write($this->name.' flop关闭');
            return;
        }

        $last_id = 0;
        while (true) {
            $last_info = FlopTrade::where([
                'member_id'=>['gt',$last_id],
                'type'=>'buy',
                'add_time'=>['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ]
            ])->order('member_id asc')->find();
            if(empty($last_info)) {
                Log::write($this->name.' flop 完成');
                break;
            }

            $last_id = $last_info['member_id'];
            echo 'flop:'.$last_id."\r\n";

            $sum = FlopTrade::where([
                'member_id'=> $last_info['member_id'],
                'type'=>'buy',
                'add_time'=>['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ]
            ])->sum('num');
            echo 'flop:'.$last_id." ".$sum."\r\n";
            if($sum && $sum>=$hongbao_config['flop_day_count_one_num']) {
                $flag = \app\common\model\FlopChildNum::parent_inc('flop',$last_info['member_id'],$this->today_config['yestday']);
                if(!$flag) {
                    Log::write($this->name.':'.$last_info['member_id'].' flop增加上级业绩失败');
                }
            }
        }
    }

    private function hongbao($hongbao_config) {
        if($hongbao_config['hongbao_day_count_one_num']<=0) {
            Log::write($this->name.' 红包关闭');
            return;
        }

        $last_id = 0;
        while (true) {
            $last_info = HongbaoLog::where([
                'user_id'=>['gt',$last_id],
                'create_time'=>['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ]
            ])->order('user_id asc')->find();
            if(empty($last_info)) {
                Log::write($this->name.' hongbao 完成');
                break;
            }

            $last_id = $last_info['user_id'];
            echo 'hongbao:'.$last_id."\r\n";

            $sum = HongbaoLog::where([
                'user_id'=> $last_info['user_id'],
                'create_time'=>['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ]
            ])->sum('num');
            echo 'hongbao:'.$last_id." ".$sum."\r\n";
            if($sum && $sum>=$hongbao_config['hongbao_day_count_one_num']) {
                $flag = \app\common\model\FlopChildNum::parent_inc('hongbao',$last_info['user_id'],$this->today_config['yestday']);
                if(!$flag) {
                    Log::write($this->name.':'.$last_info['user_id'].' flop增加上级业绩失败');
                }
            }
        }
    }
}
