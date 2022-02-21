<?php

namespace app\cli\controller;

use app\common\model\CurrencyPriceTemp;
use app\common\model\FeelMining;
use app\common\model\FeelMiningRelease;
use app\common\model\FilMining;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;

class FeelMiningReleaseTask extends Command
{
    public $name = '体验矿机释放任务';

    protected function configure()
    {
        $this->setName('FeelMiningReleaseTask')->setDescription('This is a FeelMiningReleaseTask');
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

        Log::write($this->name . "|开始");

        $last_id = FeelMiningRelease::where(['release_time' => ['elt', $today_start]])->max('third_id');
        while (true) {
            $real_detail = FeelMining::where([
                'status' => 0,
                'id' => ['gt', $last_id],
                'release_time' => ['elt', $today_start]
            ])->order('id asc')->find();
            if (empty($real_detail)) {
                Log::write($this->name . '|' . $last_id . "结束");
                break;
            }

            $currency_price = CurrencyPriceTemp::get_price_currency_id($real_detail['currency_id'], 'USD'); // USDT价格
            $real_currency_price = FilMining::getReleaseCurrencyPrice($real_detail['real_currency_id']);// 最终到账币种价格
            if ($currency_price <= 0 || $real_currency_price <= 0) {
                Log::write($this->name . '|' . $real_detail['real_currency_id'] . '|' . $real_currency_price . '|' . $real_detail['currency_id'] . '|' . '$currency_price' . "|币种价格获取失败");
                continue;
            }

            $currency_ratio = keepPoint($currency_price / $real_currency_price, 6); // USDT价格:最终到账币种价格
            $real_num = $real_detail['release_num_total'] * $real_detail['release_percent'] / 100; // 释放USDT数量

            $is_out = false;
            $release_num = keepPoint($real_detail['release_num_total'] - $real_detail['release_num_avail'], 6);
            if ($release_num <= $real_num) {
                $is_out = true;
                $real_num = $release_num;
            }

            $real_currency_num = keepPoint($real_num * $currency_ratio, 6); // 最终到账数量
            if ($real_currency_num > 0.000001) {
                FeelMiningRelease::Release($real_detail, $is_out, $real_num, $currency_ratio, $real_currency_num);
            }
        }
    }
}
