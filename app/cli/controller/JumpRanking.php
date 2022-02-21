<?php
namespace app\cli\controller;

use app\common\model\JumpRankingConfig;
use app\common\model\JumpRankingCurrencyConfig;
use app\common\model\JumpRankingCurrencyUser;
use app\common\model\JumpRankingIncome;
use app\common\model\JumpRankingPower;
use app\common\model\JumpRankingPowerIncome;
use app\common\model\Member;
use think\Db;
use think\Log;

use think\console\Input;
use think\console\Output;
use think\console\Command;

class JumpRanking extends Command
{
    public $name = '跳跃排名:';
    protected $jump_ranking_currency_config = [];
    protected $today_config = [];
    protected $table_name = '';

    protected function configure()
    {
        $this->setName('JumpRanking')->setDescription('This is a JumpRanking');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    protected function doRun() {
        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_stop' => $today_start + 86399,
            'yestoday_start' => $today_start - 86400,
            'yestoday_stop' => $today_start - 1,
        ];

        $this->jump_ranking_currency_config = JumpRankingCurrencyConfig::getList($this->today_config);
        if(empty($this->jump_ranking_currency_config)) {
            Log::write($this->name."无币种配置");
            return;
        }

        //创建新的临时表
        $flag = JumpRankingCurrencyUser::create_table($this->today_config);
        if(!$flag) {
            Log::write($this->name."创建表失败");
            return;
        }

        $flag = JumpRankingCurrencyUser::load_today($this->jump_ranking_currency_config,$this->today_config);
        if(!$flag) {
            Log::write($this->name." 加载数据失败");
            return;
        }

        $this->table_name = JumpRankingCurrencyUser::getTableName($this->today_config);

        //今日重新排名
        $this->today_ranking();
        //今日算力
        $this->today_ranking_power();

        //今日排名收入
        $this->today_ranking_income();
        //今日算力收入
        $this->today_ranking_power_income();
        //汇总
        $this->summary();
    }

    //今日重新排名
    protected function today_ranking() {
        foreach ($this->jump_ranking_currency_config as $currency_config) {
            $black_ids = explode(',',$currency_config['black_id']);

            $last_id = 0;
            while (true) {
                echo $currency_config['currency_id']."today_ranking:".$last_id."\r\n";
                $jump_ranking = Db::table($this->table_name)->where([
                    'cu_id'=>['gt',$last_id],
                    'currency_id'=>$currency_config['currency_id'],
                    'num'=>['egt',$currency_config['power_min_num']], //power_min_num 大于该值给上级增加业绩 raning_min_mum 大于该值才能拿排名奖励
                ])->order('cu_id asc')->find();
                if(empty($jump_ranking)) {
                    Log::write($this->name."今日重新排名已结束");
                    break;
                }

                $last_id = $jump_ranking['cu_id'];
                //黑名单ID 不参与结算
                if(in_array($jump_ranking['member_id'],$black_ids)) {
                    continue;
                }

                if($jump_ranking['num']>=$currency_config['raning_min_mum']) {
                    //设置排名
                    $flag = JumpRankingCurrencyUser::setRanking($jump_ranking['cu_id'],$jump_ranking['num'],$currency_config,$this->today_config);
                    if(!$flag) {
                        Log::write($this->name.$last_id."更新排名失败");
                    }
                }

                //增加上级推广业绩
                $flag = JumpRankingCurrencyUser::addParentTeam($jump_ranking['member_id'],$jump_ranking['num'],$currency_config,$this->today_config);
                if($flag===false) {
                    Log::write($this->name.$last_id."增加上级业绩失败");
                }
            }
        }
    }

    //今日算力
    protected function today_ranking_power() {
        foreach ($this->jump_ranking_currency_config as $currency_config) {
            $black_ids = explode(',',$currency_config['black_id']);


            $last_id = 0;
            while (true) {
                echo $currency_config['currency_id']."today_ranking_power:".$last_id."\r\n";
                //不合格的也给计算算力
                $ranking_currency_user = Db::table($this->table_name)->where([
                    'cu_id'=>['gt',$last_id],
                    'currency_id'=>$currency_config['currency_id'],
                    'child_num'=>['gt',0],
                ])->order('cu_id asc')->find();
                if(empty($ranking_currency_user)) {
                    Log::write($this->name."今日算力已结束");
                    break;
                }

                $last_id = $ranking_currency_user['cu_id'];
                //黑名单ID 不参与结算
                if(in_array($ranking_currency_user['member_id'],$black_ids)) {
                    continue;
                }

                $flag = JumpRankingCurrencyUser::setPower($ranking_currency_user['member_id'],$currency_config,$this->today_config);
                if(!$flag) {
                    Log::write($this->name. $last_id."设置算力失败");
                }
            }
        }
    }

    //今日排名收入
    protected function today_ranking_income() {
        foreach ($this->jump_ranking_currency_config as $currency_config) {
            //排名总和
            $total_ranking = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id']])->sum('ranking');
            if(!$total_ranking) {
                Log::write($currency_config['currency_id'].$this->name."今日排名总和为0");
                continue;
            }

            $last_id = JumpRankingIncome::where(['currency_id'=>$currency_config['currency_id'],'add_time'=>$this->today_config['today_start']])->max('third_id');
            $last_id = intval($last_id);
            while (true) {
                echo $currency_config['currency_id']."today_ranking_income:".$last_id."\r\n";
                $jump_ranking = Db::table($this->table_name)->where([
                    'cu_id'=>['gt',$last_id],
                    'currency_id'=>$currency_config['currency_id'],
                    'ranking'=>['gt',0]
                ])->order('cu_id asc')->find();
                if(empty($jump_ranking)) {
                    Log::write($this->name."今日重新排名已结束");
                    break;
                }

                $last_id = $jump_ranking['cu_id'];

                $income = keepPoint($jump_ranking['ranking']/$total_ranking * $currency_config['today_award_num'],6);
                if($income>=$currency_config['income_min_num']) {
                    //收益倍数
                    $multiple = keepPoint($income/$jump_ranking['num'],6);
                    JumpRankingIncome::addItem($jump_ranking['member_id'],$jump_ranking['currency_id'],$income,$jump_ranking['ranking'],$multiple,$this->today_config['today_start'],$jump_ranking['cu_id'],$currency_config);
                }
            }
        }
    }

    //今日算力收入
    protected function today_ranking_power_income() {
        foreach ($this->jump_ranking_currency_config as $currency_config) {
            $total_power = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id'],'num'=>['egt',$currency_config['raning_min_mum']]])->sum('power');
            if($total_power<=0) continue;

            $last_id = JumpRankingPowerIncome::where(['currency_id'=>$currency_config['currency_id'],'add_time' => $this->today_config['today_start']])->max('third_id');
            $last_id = intval($last_id);
            while (true) {
                echo $currency_config['currency_id']."today_ranking_power_income:" . $last_id . "\r\n";
                $ranking_power = Db::table($this->table_name)->where([
                    'cu_id' => ['gt', $last_id],
                    'currency_id'=>$currency_config['currency_id'],
                    'num'=>['egt',$currency_config['raning_min_mum']],
                    'power' => ['gt', 0]
                ])->order('cu_id asc')->find();
                if (empty($ranking_power)) {
                    Log::write($this->name . "今日算力已结束");
                    break;
                }

                $last_id = $ranking_power['cu_id'];
                $income = $ranking_power['power'] / $total_power * $currency_config['today_power_award_num'];
                if ($income>=$currency_config['income_min_num']) {
                    JumpRankingPowerIncome::addItem($ranking_power['member_id'], $ranking_power['currency_id'], $income, $ranking_power['power'],$ranking_power['cu_id'], $this->today_config['today_start'], $currency_config);
                }
            }
        }
    }

    protected function summary() {
        foreach ($this->jump_ranking_currency_config as $currency_config) {
            $data = [
                'today' => $this->today_config['today'],
                'currency_id' => $currency_config['currency_id'],
                'jump_ranking_count' => 0, //有效订单个数
                'jump_ranking_num' => 0, //有效订单入金量
                'jump_ranking_total' => 0, //排名总和
                'jump_ranking_income' => 0, //排名收入总和
                'jump_ranking_max_mul' => 0, //最佳量
                'jump_ranking_power_total' => 0, //算力总和
                'jump_ranking_power_income' => 0, //算力收入总和
                'add_time' => time(),
            ];
            $data['jump_ranking_count'] = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id'],'num' => ['egt', $currency_config['raning_min_mum']]])->count();
            $data['jump_ranking_num'] = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id'],'num' => ['egt', $currency_config['raning_min_mum']]])->sum('num');
            $data['jump_ranking_total'] = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id']])->sum('ranking');

            $data['jump_ranking_income'] = JumpRankingIncome::where(['currency_id'=>$currency_config['currency_id'],'add_time' => $this->today_config['today_start']])->sum('num');
            $jump_ranking_max_mul = JumpRankingIncome::where(['currency_id'=>$currency_config['currency_id'],'add_time' => $this->today_config['today_start']])->order('multiple desc')->find();
            if ($jump_ranking_max_mul) {
                $jump_ranking_max = Db::table($this->table_name)->where(['cu_id' => $jump_ranking_max_mul['third_id']])->find();
                if ($jump_ranking_max) $data['jump_ranking_max_mul'] = $jump_ranking_max['num'];
            }

            $data['jump_ranking_power_total'] = Db::table($this->table_name)->where(['currency_id'=>$currency_config['currency_id'],'num'=>['egt',$currency_config['raning_min_mum']]])->sum('power');
            $data['jump_ranking_power_income'] = JumpRankingPowerIncome::where(['currency_id'=>$currency_config['currency_id'],'add_time' => $this->today_config['today_start']])->sum('num');
            Db::name('jump_ranking_summary')->insertGetId($data);
        }
    }
}
