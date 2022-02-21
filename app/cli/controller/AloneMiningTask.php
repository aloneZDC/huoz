<?php

namespace app\cli\controller;

use app\common\model\AloneMiningArchive;
use app\common\model\AloneMiningConfig;
use app\common\model\AloneMiningIncome;
use app\common\model\AloneMiningPay;
use app\common\model\AloneMiningRelease;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;

/**
 * 独享矿机 - 定时任务
 * Class AloneMiningTask
 * @package app\cli\controller
 */
class AloneMiningTask extends Command
{
    public $name = '独享矿机定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected function configure()
    {
        $this->setName('AloneMiningTask')->setDescription('This is a AloneMiningTask');
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

        $this->mining_config = AloneMiningConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }

        // 540天退质押币
        $this->refund_pledged_coins();

        // 独享矿机产币 75%锁仓180天线性释放
        $this->release_lock_release();

        // 封存每日预封存算力
        $this->archive_tnum();

        // 独享矿机产币 25%立即释放
        $this->release_num();

        //计算提成
        $this->team_xch_num();
    }

    // 540天退质押币
    protected function refund_pledged_coins()
    {
        $last_id = 0;
        while (true) {
            $mining_archive = AloneMiningArchive::where([
                'id' => ['gt', $last_id],
                'is_thaw' => 0,
                'thaw_time' => ['lt', $this->today_config['today_start']],
                'payment' => ['gt', 0]
            ])->order(['id' => 'asc'])->find();
            if (empty($mining_archive)) {
                Log::write($this->name . " 退质押币 已完成");
                break;
            }

            $last_id = $mining_archive['id'];
            echo "refund_pledged_coins {$last_id}\r\n";

            AloneMiningArchive::refund_pledged_coins($mining_archive);
        }
    }

    // 独享矿机产币 25%立即释放
    protected function release_num()
    {
        if ($this->mining_config['release_income_open'] != 1) {
            Log::write($this->name . " 产币 关闭");
            return;
        }

        // 获取Fil24H平均价格
        $fil_block_info = cache('fil_block_info');
        if (empty($fil_block_info) || !isset($fil_block_info['fields']) || !isset($fil_block_info['fields']['hour24_avg'])) {
            Log::write($this->name . " 获取平均算力失败");
            return;
        }

        $per_tnum_price = $fil_block_info['fields']['hour24_avg']['number'];
        if (!is_numeric($per_tnum_price) || $per_tnum_price <= 0 || $per_tnum_price > 0.5) {
            Log::write($this->name . " 获取平均算力失败2");
            return;
        }

        $last_id = AloneMiningRelease::where(['release_time' => $this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $mining_pay = AloneMiningPay::where([
                'id' => ['gt', $last_id],
                'start_day' => ['elt', $this->today_config['today_start']],
                'treaty_day' => ['egt', $this->today_config['today_start']],
                'last_release_day' => ['lt', $this->today_config['today_start']],
            ])->order(['id' => 'asc'])->find();

            if (empty($mining_pay)) {
                Log::write($this->name . " 产币 已完成");
                break;
            }
            $last_id = $mining_pay['id'];
            echo "独享 - release_num {$last_id}\r\n";

            AloneMiningRelease::release($mining_pay, $this->mining_config, $per_tnum_price, $this->today_config);
        }
    }

    // 独享矿机产币 75%锁仓180天线性释放
    protected function release_lock_release()
    {
        $last_id = AloneMiningIncome::where([
            'currency_id' => $this->mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 4,
        ])->max('third_id');

        while (true) {
            $mining_pay = AloneMiningPay::where([
                'id' => ['gt', $last_id],
                'total_lock_yu' => ['gt', 0],
            ])->order('id asc')->find();
            if (empty($mining_pay)) {
                Log::write($this->name . " 产出锁仓释放已完成");
                break;
            }

            $last_id = $mining_pay['id'];
            echo "release_lock_release {$last_id}\r\n";

            AloneMiningRelease::release_lock_release($mining_pay, $this->mining_config, $this->today_config['today_start']);
        }
    }

    // 推荐奖励1代2代发放
    protected function recommand_num()
    {
        if ($this->mining_config['recommand_income_open'] != 1) {
            Log::write($this->name . " 推荐奖励关闭");
            return;
        }

        $last_id = AloneMiningIncome::where(['type' => 1, 'award_time' => $this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $mining_archive = AloneMiningArchive::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_recommand' => 0,
            ])->order('id asc')->find();
            if (empty($mining_archive)) {
                Log::write($this->name . " 推荐奖励已完成");
                break;
            }
            $last_id = $mining_archive['id'];
            echo 'recommand_num:' . $last_id . "\r\n";

            $flag = AloneMiningArchive::where(['id' => $mining_archive['id'], 'is_recommand' => 0])->setField('is_recommand', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否推荐奖励失败" . $mining_archive['id']);
                continue;
            }

            // 添加奖励详情
            AloneMiningArchive::recommand_award($mining_archive, $this->mining_config, $this->today_config['today_start']);
        }
    }

    //计算提成
    protected function team_xch_num() {
        Log::write('提成计算 start');
        if ($this->mining_config['commission_open'] != 1) {
            Log::write($this->name . " 提成计算 关闭");
            return;
        }
        $last_id = 0;
        while (true) {
            $mining_archive = \app\common\model\AloneMiningRelease::where([
                'id' => ['gt', $last_id],
                'release_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_end']]],
                'is_team' => 0,
            ])->order('id asc')->find();
            if (empty($mining_archive)) {
                Log::write($this->name . " 提成计算已完成");
                break;
            }
            $last_id = $mining_archive['id'];
            echo 'team_num:' . $last_id . "\r\n";

            $today = date('Y-m-d', $this->today_config['today_start']);
            $flag = \app\common\model\AloneMiningMember::addCommission($mining_archive['member_id'], $today, $mining_archive, $this->mining_config);
            if ($flag === false) {
                Log::write($this->name . " 提成计算失败" . $mining_archive['id']);
                continue;
            }

            $flag = \app\common\model\AloneMiningRelease::where(['id' => $mining_archive['id'], 'is_team' => 0])->setField('is_team', 1);
            if ($flag === false) {
                Log::write($this->name . " 更新是否计算提成失败" . $mining_archive['id']);
                continue;
            }
        }
        Log::write('提成计算 end');
    }

    // 封存每日预封存算力
    protected function archive_tnum() {
        Log::write('封存每日预封存算力 start');
        $gas_fee = \app\common\model\AloneMiningProduct::average_out();

        $last_id = 0;
        $order_id = 0;
        while (true) {
            $mining_archive = \app\common\model\AloneMiningArchiveLog::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'status' => 0,
            ])->order('id asc')->find();
            if (empty($mining_archive)) {
                $mining = \app\common\model\AloneMiningPay::alias('a')->field('a.*')
                    ->where("NOT EXISTS (select * from yang_alone_mining_archive as b where b.add_time between {$this->today_config['today_start']} and {$this->today_config['today_end']} and b.member_id=a.member_id and b.mining_pay_id=a.id)")
                    ->where(['id' => ['gt', $order_id], 'start_day' => ['elt', $this->today_config['today_start']]])->order('id asc')->find();
                if (empty($mining)) {
                    Log::write($this->name . " 封存每日预封存算力已完成");
                    break;
                }
                if ($mining['max_tnum'] > $mining['archive']) {
                    $amount = intval($mining['max_tnum'] - $mining['archive']);
                    if ($amount >= $this->mining_config['archive_tnum']) {
                        $amount = $this->mining_config['archive_tnum'];
                    }
                } else {
                    continue;
                }
                echo 'team_num:' . $order_id . "\r\n";
                $order_id = $mining['id'];
                $mining_archive = [
                    'member_id' => $mining['member_id'],
                    'tnum' => $amount,
                    'mining_pay_id' => $mining['id'],
                    'add_time' => $this->today_config['yestday_stop'],
                ];
            } else {
                echo 'team_num:' . $last_id . "\r\n";
                $last_id = $mining_archive['id'];

                $flag = \app\common\model\AloneMiningArchiveLog::where(['id' => $mining_archive['id'], 'status' => 0])->setField('status', 1);
                if ($flag === false) {
                    Log::write($this->name . " 更新是否封存成功失败" . $mining_archive['id']);
                    continue;
                }
            }

            $flag = \app\common\model\AloneMiningArchive::buy($mining_archive['member_id'], $mining_archive['mining_pay_id'], $mining_archive['tnum'], $mining_archive['add_time'], $mining_archive);
            if ($flag === false) {
                Log::write($this->name . " 封存每日预封存算力失败" . $mining_archive['id']);
                continue;
            }
        }
        Log::write('封存每日预封存算力 end');
    }
}