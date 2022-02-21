<?php

namespace app\cli\controller;

use think\Log;
use think\Db;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\common\model\CurrencyUser;
use app\common\model\ChiaMiningPay;
use app\common\model\ChiaMiningMember;
use app\common\model\ChiaMiningConfig;
use app\common\model\ChiaMiningRelease;
use app\common\model\ChiaMiningMemberSummary;
use app\common\model\ChiaMiningMemberSettlement;
use app\common\model\ChiaMiningReward;

class ChiaMiningIncomeTask extends Command
{
    protected $name = 'chia矿机收益定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('ChiaMiningIncomeTask')->setDescription('This is a ChiaMiningIncomeTask');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun($today = '')
    {
        if (empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name . " 开始 ");

        $this->mining_config = ChiaMiningConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }
        //每天矿机产出
        $this->release_num();

        //提成计算
        $this->team_xch_num();
    }

    // 提成计算
    public function team_xch_num()
    {
        Log::write('提成计算 start');
        if ($this->mining_config['commission_open'] != 1) {
            Log::write($this->name . " 提成计算 关闭");
            return;
        }
        $last_id = 0;
        while (true) {
            $mining_archive = ChiaMiningRelease::where([
                'id' => ['gt', $last_id],
                'release_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_end']]],
                'is_team' => 0,
                'type' => 1
            ])->order('id asc')->find();
            if (empty($mining_archive)) {
                Log::write($this->name . " 提成计算已完成");
                break;
            }
            $last_id = $mining_archive['id'];
            echo 'team_num:' . $last_id . "\r\n";

            $today = date('Y-m-d', $this->today_config['today_start']);
            $flag = ChiaMiningMember::addCommission($mining_archive['member_id'], $today, $mining_archive, $this->mining_config);
            if ($flag === false) {
                Log::write($this->name . " 提成计算失败" . $mining_archive['id']);
                continue;
            }

            $flag = ChiaMiningRelease::where(['id' => $mining_archive['id'], 'is_team' => 0])->setField('is_team', 1);
            if ($flag === false) {
                Log::write($this->name . " 更新是否团队失败" . $mining_archive['id']);
                continue;
            }
        }
        Log::write('提成计算 end');
    }

    // chia矿机产币
    protected function release_num()
    {
        Log::write('chia矿机产币 start');
        if ($this->mining_config['release_income_open'] != 1) {
            Log::write($this->name . " 产币 关闭");
            return;
        }

        // 获取Chia24H平均价格
        $chia_block_info = cache('chia_block_info');
        if (empty($chia_block_info) || !isset($chia_block_info['fields']) || !isset($chia_block_info['fields']['chia_hour24_avg'])) {
            Log::write($this->name . " 获取平均算力失败");
            return;
        }

        $per_tnum_price = $chia_block_info['fields']['chia_hour24_avg']['number'];
        if (!is_numeric($per_tnum_price) || $per_tnum_price <= 0 || $per_tnum_price > 0.5) {
            Log::write($this->name . " 获取平均算力失败2");
            return;
        }

        //实付T数
        $last_id = ChiaMiningRelease::where(['release_time' => $this->today_config['today_start'], 'type' => 1])->max('third_id');
        while (true) {
            $mining_pay = ChiaMiningPay::where([
                'id' => ['gt', $last_id],
                'tnum' => ['gt', 0],
                'start_day' => ['elt', $this->today_config['today_start']],
                'treaty_day' => ['egt', $this->today_config['today_start']],
                'last_release_day' => ['lt', $this->today_config['today_start']],
            ])->order(['id' => 'asc'])->find();
            if (empty($mining_pay)) {
                Log::write($this->name . " 实际产币 已完成");
                break;
            }
            $last_id = $mining_pay['id'];
            echo "CHIA - release_num {$last_id}\r\n";

            ChiaMiningRelease::release($mining_pay, $this->mining_config, $per_tnum_price, $this->today_config);
        }

        Log::write('chia矿机产币 end');
    }

}