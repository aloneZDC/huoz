<?php
namespace app\cli\controller;
use app\common\model\BbfTogether;
use app\common\model\BbfTogetherCurrency;
use app\common\model\BbfTogetherIncome;
use app\common\model\BbfTogetherIncomeDetail;
use app\common\model\BbfTogetherMember;
use app\common\model\BbfTogetherRelease;
use app\common\model\CurrencyPriceTemp;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

class BbfReleaseTask extends Command
{
    public $name = '四币连发释放任务';
    protected $today_config = [];
    protected $bbf_currency_list = [];
    protected $currency_prices = [];

    protected function configure()
    {
        $this->setName('BbfReleaseTask')->setDescription('This is a BbfReleaseTask');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
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

        $bbf_currency_list = BbfTogetherCurrency::getListApi();
        if(empty($bbf_currency_list)) {
            Log::write($this->name." 币种列表为空");
            return;
        }

        $currency_prices = CurrencyPriceTemp::select();
        if(empty($currency_prices)) {
            Log::write($this->name." 币种价格获取失败");
            return;
        }

        $this->currency_prices = array_column($currency_prices,'cpt_usd_price','cpt_currency_id');
        $this->bbf_currency_list = array_column($bbf_currency_list,null,'release_currency_id');

        //增加上级业绩
        $this->team_num();
        //释放
        $this->release_num();
        $this->release_third_num();
        //汇总
        $this->summary();
    }

    //昨日新入金 增加上级业绩
    public function team_num() {
        $last_id = 0;
        while (true) {
            $bbf_together = BbfTogether::where([
                'id'=>['gt',$last_id],
                'add_time' => ['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
            ])->order('id asc')->find();
            if(empty($bbf_together)) {
                Log::write($this->name." 上级业绩已完成");
                break;
            }
            $last_id = $bbf_together['id'];
            echo 'team_num:'.$last_id."\r\n";

            $flag = BbfTogetherMember::addParentTeamNum($bbf_together['member_id'],$bbf_together['release_currency_id'],$bbf_together['lock_currency_num']);
            if(!$flag) {
                Log::write($this->name." 增加上级业绩失败".$bbf_together['id']);
            }
        }
    }

    //释放
    public function release_num() {
        $last_id = BbfTogetherRelease::where(['release_time'=>$this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $bbf_together = BbfTogether::where([
                'id'=>['gt',$last_id],
                'add_time' => ['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
                'status' => 0,
            ])->order('id asc')->find();
            if(empty($bbf_together)) {
                Log::write($this->name." 释放奖励已完成");
                break;
            }

            $last_id = $bbf_together['id'];
            echo "release_num {$last_id}\r\n";

            if($bbf_together['lock_currency_avail']<=0) {
                BbfTogether::where(['id'=>$bbf_together['id'],'status'=>0])->setField('status',1);
                continue;
            }

            $lock_currency_price = isset($this->currency_prices[$bbf_together['lock_currency_id']]) ? $this->currency_prices[$bbf_together['lock_currency_id']] : 0;
            $release_currency_price = isset($this->currency_prices[$bbf_together['release_currency_id']]) ? $this->currency_prices[$bbf_together['release_currency_id']] : 0;
            if($lock_currency_price<=0 || $release_currency_price<=0) {
                Log::write($this->name." 币种价格错误".$bbf_together['id']);
                continue;
            }

            // 释放币种
            if(!isset($this->bbf_currency_list[$bbf_together['release_currency_id']])) {
                Log::write($this->name." 释放设置不存在".$bbf_together['id']);
                continue;
            }
            $bbf_currency_config = $this->bbf_currency_list[$bbf_together['release_currency_id']];

            //释放
            $release_res = BbfTogetherRelease::release($bbf_together,$bbf_currency_config,$lock_currency_price,$release_currency_price,$this->today_config['today_start']);
            if($release_res['release_num']>0) {
                BbfTogetherRelease::release_award($bbf_together,$bbf_currency_config,$release_res,$this->today_config['today_start']);
            }
        }
    }

    //释放3代奖励
    public function release_third_num() {
        foreach ($this->bbf_currency_list as $bbf_currency) {
            $last_id = BbfTogetherIncome::where([
                'currency_id' => $bbf_currency['release_currency_id'],
                'award_time'=> $this->today_config['today_start'],
                'type' => 3,
            ])->max('member_id');
            $last_id = intval($last_id);
            while (true) {
                $income_detail = BbfTogetherIncomeDetail::where([
                    'currency_id' => $bbf_currency['release_currency_id'],
                    'award_time'=> $this->today_config['today_start'],
                    'member_id'=>['gt',$last_id],
                ])->order('member_id asc')->find();
                if(empty($income_detail)) {
                    Log::write($this->name." 释放3代奖励已完成");
                    break;
                }

                $last_id = $income_detail['member_id'];
                echo "release_third_num {$last_id}\r\n";

                $income_sum = BbfTogetherIncomeDetail::where([
                    'currency_id' => $bbf_currency['release_currency_id'],
                    'award_time'=> $this->today_config['today_start'],
                    'member_id'=> $income_detail['member_id'],
                ])->sum('num');
                if($income_sum>0) {
                    //添加奖励
                    BbfTogetherIncome::award($income_detail['member_id'],$income_detail['currency_id'],3,$income_sum,0,0,0,0,$this->today_config['today_start']);
                }
            }
        }
    }

    private function summary() {
        foreach ($this->bbf_currency_list as $bbf_currency) {
            $insert_data = [
                'today' => $this->today_config['today'],
                'pay_currency_num' => 0,
                'pay_other_currency_num' => 0,
                'pledge_currency_num' => 0,
                'pay_usdt' => 0,
                'release' => 0,
                'release_usdt' => 0,
                'team1' => 0,
                'team1_usdt' => 0,
                'team2' => 0,
                'team2_usdt' => 0,
                'team3' => 0,
                'team3_usdt' => 0,
                'add_time' => time(),
            ];

            //支付数量
            $pay_currency_num = BbfTogether::where([
                'release_currency_id' => $bbf_currency['release_currency_id'],
                'add_time' => ['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
            ])->field("sum(pay_currency_num) as pay_currency_num,sum(pay_other_currency_num) as pay_other_currency_num,sum(pay_total_num) as pay_total_num,sum(pledge_currency_num) as pledge_currency_num")->find();
            if($pay_currency_num) {
                $insert_data['pay_currency_num'] = doubleval($pay_currency_num['pay_currency_num']);
                $insert_data['pay_other_currency_num'] = doubleval($pay_currency_num['pay_other_currency_num']);
                $insert_data['pledge_currency_num'] = doubleval($pay_currency_num['pledge_currency_num']);
                $insert_data['pay_usdt'] = doubleval($pay_currency_num['pay_total_num']);
            }

            $ratio = BbfTogetherCurrency::getConvertRatio($this->currency_prices,$bbf_currency['release_currency_id'],$bbf_currency['pay_total_currency_id']);
            $release = BbfTogetherRelease::where([
                'release_currency_id' => $bbf_currency['release_currency_id'],
                'release_time' => $this->today_config['today_start'],
            ])->sum('release_currency_num');
            $insert_data['release'] = $release;
            $insert_data['release_usdt'] = keepPoint($release*$ratio,6);

            $team1 = BbfTogetherIncome::where([
                'currency_id' => $bbf_currency['release_currency_id'],
                'add_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end']] ],
                'type' => 1,
            ])->sum('num');
            $insert_data['team1'] = $team1;
            $insert_data['team1_usdt'] = keepPoint($team1*$ratio,6);

            $team2 = BbfTogetherIncome::where([
                'currency_id' => $bbf_currency['release_currency_id'],
                'add_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end']] ],
                'type' => 2,
            ])->sum('num');
            $insert_data['team2'] = $team2;
            $insert_data['team2_usdt'] = keepPoint($team2*$ratio,6);

            $team3 = BbfTogetherIncome::where([
                'currency_id' => $bbf_currency['release_currency_id'],
                'add_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end']] ],
                'type' => 3,
            ])->sum('num');
            $insert_data['team3'] = $team3;
            $insert_data['team3_usdt'] = keepPoint($team3*$ratio,6);
            Db::name('bbf_together_summary')->insertGetId($insert_data);
        }
    }
}
