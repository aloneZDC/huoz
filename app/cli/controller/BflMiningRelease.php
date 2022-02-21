<?php
namespace app\cli\controller;
use app\common\model\BflMining;
use app\common\model\BflMiningBonus;
use app\common\model\BflMiningBonusDetail;
use app\common\model\BflMiningCurrencyConfig;
use app\common\model\BflMiningLevelConfig;
use think\Db;
use think\Log;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * BFL项目 矿机每日挖矿
 */
class BflMiningRelease extends Command
{
    public $name = 'BFL项目-矿机每日挖矿';
    protected $today_config = [];
    protected $day_configs = [];
    protected $currency_ratio = 0;

    protected function configure()
    {
        $this->setName('BflMiningRelease')->setDescription('This is a '.$this->name);
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun('2020-10-15');
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
        $currency_config = BflMiningCurrencyConfig::where(['auto_start_time'=>['elt',$this->today_config['today_start']] ])->select();
        if(empty($currency_config)) {
            Log::write($this->name." 币种配置获取失败");
            return;
        }
        $currency_config = array_column($currency_config,null,'currency_id');


        $level_config = BflMiningLevelConfig::getConfigs();
        if(empty($level_config)) {
            Log::write($this->name." 配置获取失败 ");
            return;
        }

        //释放
        $this->release($currency_config,$level_config);
        //释放动态奖
        $this->release_bonus($currency_config);

        Log::write($this->name." 结束 ");
    }

    //释放
    protected function release($currency_config,$level_config) {
        $last_id = \app\common\model\BflMiningRelease::where(['add_day'=>$this->today_config['today_start'] ])->max('third_id');
        while (true) {
            echo "release:".$last_id."\r\n";
            $mining = BflMining::where([
                'id'=>['gt',$last_id],
                'status'=> BflMining::STATUS_OK,
            ])->order('id asc')->find();
            if(empty($mining)) {
                Log::write($this->name." release结束");
                break;
            }

            $last_id = $mining['id'];

            if(isset($level_config[$mining['currency_id']]) && isset($currency_config[$mining['currency_id']])) {
                $cur_level_config = BflMiningLevelConfig::getPercent($mining['num'],$level_config[$mining['currency_id']]);
                if($cur_level_config) {
                    //释放
                    $release_num = \app\common\model\BflMiningRelease::release($mining,$currency_config[$mining['currency_id']],$cur_level_config,$this->today_config);
                    if($release_num>0) {
                        //释放上级奖励
                        \app\common\model\BflMiningRelease::release_award($release_num,$mining,$currency_config[$mining['currency_id']],$cur_level_config,$this->today_config);
                    }
                }
            } else {
                Log::write($this->name.' '.$last_id." 币种配置不存在");
            }
        }
    }

    //上级释放奖励
    protected function release_bonus($currency_config) {
        Log::write($this->name." release_bonus 开始");

        foreach ($currency_config as $config) {
            $last_id = \app\common\model\BflMiningBonus::where(['currency_id'=>$config['currency_id'],'add_day'=>$this->today_config['today_start'] ])->max('member_id');
            while (true) {
                echo $config['currency_id']."release_bonus:".$last_id."\r\n";
                $bonus_detail = BflMiningBonusDetail::where([
                    'currency_id' => $config['currency_id'],
                    'add_day'=> $this->today_config['today_start'],
                    'member_id' => ['gt',$last_id],
                ])->order('member_id asc')->find();
                if(empty($bonus_detail)) {
                    Log::write($this->name." release_bonus 结束".$config['currency_id']);
                    break;
                }

                $last_id = $bonus_detail['member_id'];

                $sum = BflMiningBonusDetail::where([
                    'currency_id' => $config['currency_id'],
                    'add_day'=> $this->today_config['today_start'],
                    'member_id' => $bonus_detail['member_id'],
                ])->sum('num');
                if($sum && $sum>=0.000001) {
                    $mining = BflMining::getOKMining($bonus_detail['member_id'],$config['currency_id']);
                    if($mining) {
                        //判断是否出局
                        $is_out = false;
                        $yu_num = keepPoint($mining['out_num'] - $mining['total_release'] - $mining['total_award'],6);
                        if($yu_num<=0 || $sum>=$yu_num) {
                            echo $mining['id']." 动态OUT".PHP_EOL;
                            $is_out = true;
                        }

                        $sum = min($sum,$yu_num);
                        if($sum && $sum>=0.000001) {
                            BflMiningBonus::award($bonus_detail['member_id'],$bonus_detail['currency_id'],$sum,$is_out,$mining,$this->today_config);
                        }
                    }
                }
            }
        }
    }
}
