<?php

namespace app\cli\controller;

use app\common\model\ChiaMiningConfig;
use app\common\model\ChiaMiningIncome;
use app\common\model\ChiaMiningPay;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Workerman\Worker;

/**
 * 奇亚矿机 - 定时任务
 * Class AloneMiningTask
 * @package app\cli\controller
 */
class ChiaMiningTask
{
    public $name = '奇亚矿机定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ChiaMiningTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
                sleep(2);
            }
        };
        Worker::runAll();
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

        // 推荐奖励1代2代发放
        $this->recommand_num();

    }

    // 推荐奖励1代2代发放
    protected function recommand_num()
    {
        if ($this->mining_config['recommand_income_open'] != 1) {
            Log::write($this->name . " 推荐奖励关闭");
            return;
        }

        $last_id = ChiaMiningIncome::where(['type' => 1, 'award_time' => $this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $mining_archive = ChiaMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_end']]],
                'is_recommand' => 0,
            ])->order('id asc')->find();Log::write($mining_archive);
            if (empty($mining_archive)) {
                Log::write($this->name . " 推荐奖励已完成");
                break;
            }
            $last_id = $mining_archive['id'];
            echo 'recommand_num:' . $last_id . "\r\n";

            $flag = ChiaMiningPay::where(['id' => $mining_archive['id'], 'is_recommand' => 0])->setField('is_recommand', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否推荐奖励失败" . $mining_archive['id']);
                continue;
            }

            // 添加奖励详情
            ChiaMiningPay::recommand_award($mining_archive, $this->mining_config, $this->today_config['today_start']);
        }
    }

}