<?php

namespace app\cli\controller;

use app\common\model\WarrantConfig;
use app\common\model\WarrantExchange;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;

/**
 * 承保任务
 * Class WarrantTask
 * @package app\cli\controller
 */
class WarrantTask extends Command
{
    public $name = '承保任务';

    protected function configure()
    {
        $this->setName('WarrantTask')->setDescription('This is a FilMiningReleaseTask');
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
        Log::write($this->name . " 开始 ");

        $this->expire($today_start);
    }

    // 承保到期处理
    public function expire($today_start)
    {
        while (true) {
            $warrant_exchange = WarrantExchange::where(['end_time' => ['<', $today_start], 'status' => 0])->order(['id' => 'asc'])->find();
            if (empty($warrant_exchange)) {
                Log::write($this->name . " | 承保到期处理已完成");
                break;
            }
            $last_id = $warrant_exchange['id'];
            echo $this->name . ' | 执行ID | ' . $last_id . "\r\n";

            $result = WarrantExchange::exchange_expire($warrant_exchange);
            if ($result['code'] == ERROR1) {
                Log::write($this->name . " | 错误 | " . $result['message']);
            }
        }
    }
}