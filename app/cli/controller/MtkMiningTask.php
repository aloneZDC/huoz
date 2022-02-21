<?php

namespace app\cli\controller;

use app\common\model\MtkCurrencyPrice;
use app\common\model\MtkMiningConfig;
use app\common\model\MtkMiningIncome;
use app\common\model\MtkMiningMember;
use app\common\model\MtkMiningOrder;
use app\common\model\MtkMiningRelease;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;

class MtkMiningTask extends Command
{
    protected $name = 'MTK算力 - 定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('MtkMiningTask')->setDescription('This is a MtkMiningTask');
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

        $this->mining_config = MtkMiningConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }

        $this->create_price(); // 创建MKT价格

        // 静态产出
        $this->release_lock_static(); // 线性释放25%
        $this->release_static(); // 立即释放75%

        // 产出奖励
        $this->service_lock_reward(); // 线性释放25%
        $this->service_reward();// 立即释放75%

        $this->create_yefi_price(); // 创建yefi价格
    }

    // 创建MKT价格
    protected function create_price()
    {
        Log::write($this->name . " 创建MKT价格开始");

        if ($this->mining_config['price_open'] != 1) {
            Log::write($this->name . " 创建MKT价格关闭");
            return;
        }

        $flag = MtkCurrencyPrice::create_price($this->mining_config);
        if ($flag === false) {
            Log::write($this->name . " 创建MKT价格失败");
            return;
        }

        Log::write($this->name . " 创建MKT价格结束");
    }

    // 静态产出
    protected function release_static()
    {
        Log::write($this->name . " 静态产出开始");

        if ($this->mining_config['release_open'] != 1) {
            Log::write($this->name . " 静态产出关闭");
            return;
        }
        $last_id = MtkMiningIncome::where([
            'currency_id' => $this->mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 4,
        ])->max('third_id');
        while (true) {
            $mining_order = MtkMiningOrder::where([
                'id' => ['gt', $last_id],
                'currency_id' => $this->mining_config['release_currency_id'],
                'surplus_power' => ['gt', 0]
            ])->order('id asc')->find();
            if (empty($mining_order)) {
                Log::write($this->name . " 静态产出已完成");
                break;
            }
            $last_id = $mining_order['id'];
            echo 'release_static:' . $last_id . "\r\n";

            // 添加奖励详情
            $flag = MtkMiningRelease::release_static($mining_order, $this->mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 静态产出失败" . $mining_order['id']);
                break;
            }
        }
        Log::write($this->name . " 静态产出结束");
    }

    // 线性释放
    protected function release_lock_static()
    {
        Log::write($this->name . " 静态产出线性释放开始");
        if ($this->mining_config['lock_release_open'] != 1) {
            Log::write($this->name . " 产出锁仓释放关闭");
            return;
        }

        $last_id = MtkMiningIncome::where([
            'currency_id' => $this->mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 5,
        ])->max('third_id');
        while (true) {
            $mining_order = MtkMiningOrder::where([
                'id' => ['gt', $last_id],
                'currency_id' => $this->mining_config['release_currency_id'],
                'total_lock_num' => ['gt', 0],
            ])->order('id asc')->find();
            if (empty($mining_order)) {
                Log::write($this->name . " 产出锁仓释放已完成");
                break;
            }

            $last_id = $mining_order['id'];
            echo "release_lock_static {$last_id}\r\n";

            $flag = MtkMiningRelease::release_lock_static($mining_order, $this->mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 静态产出失败" . $mining_order['id']);
                break;
            }
        }
    }

    // 产出奖励25%
    protected function service_reward()
    {
        Log::write($this->name . " 产币推荐奖励开始");
        if ($this->mining_config['accel_open'] != 1) {
            Log::write($this->name . " 产币推荐奖励关闭");
            return;
        }
        $last_id = MtkMiningIncome::where([
            'currency_id' => $this->mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 6,
        ])->max('release_id');
        while (true) {
            $common_mining = MtkMiningRelease::where([
                'id' => ['gt', $last_id],
                'currency_id' => $this->mining_config['release_currency_id'],
                'release_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_end']]],
                'is_recommand' => 0,
            ])->order('id asc')->find();
            if (empty($common_mining)) {
                Log::write($this->name . " 产币推荐奖励已完成");
                break;
            }
            $last_id = $common_mining['id'];
            echo 'service_reward:' . $last_id . "\r\n";

            $flag = MtkMiningRelease::where(['id' => $common_mining['id'], 'is_recommand' => 0])
                ->setField('is_recommand', 1);
            if ($flag === false) {
                Log::write($this->name . " 更新是否产币推荐奖励失败" . $common_mining['id']);
                break;
            }

            // 添加奖励详情
            $flag = MtkMiningRelease::service_reward($common_mining, $this->mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 奖励发放失败" . $common_mining['id']);
                break;
            }
        }
    }

    // 线性释放25%
    protected function service_lock_reward()
    {
        Log::write($this->name . " 产出奖励锁仓释放开始");
        if ($this->mining_config['accel_lock_open'] != 1) {
            Log::write($this->name . " 产出奖励锁仓释放关闭");
            return;
        }

        $last_id = MtkMiningIncome::where([
            'currency_id' => $this->mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 7,
        ])->max('third_id');
        while (true) {
            $mining_member = MtkMiningMember::where([
                'id' => ['gt', $last_id],
                'total_child6' => ['gt', 0],
            ])->order('id asc')->find();
            if (empty($mining_member)) {
                Log::write($this->name . " 产出奖励锁仓释放已完成");
                break;
            }

            $last_id = $mining_member['id'];
            echo "service_lock_reward {$last_id}\r\n";

            $flag = MtkMiningRelease::service_lock_reward($mining_member, $this->mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 奖励发放失败" . $mining_member['id']);
                break;
            }
        }
    }

    // 创建yefi价格
    protected function create_yefi_price()
    {
        Log::write($this->name . " 创建yefi价格开始");

        $flag = \app\common\model\YefiCurrencyPrice::create_price($this->mining_config);
        if ($flag === false) {
            Log::write($this->name . " 创建yefi价格失败");
            return;
        }

        Log::write($this->name . " 创建yefi价格结束");
    }
}