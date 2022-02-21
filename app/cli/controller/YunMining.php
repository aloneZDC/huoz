<?php

namespace app\cli\controller;

use app\common\model\CurrencyUser;
use app\common\model\YunMiningConfig;
use app\common\model\YunMiningPay;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;

class YunMining extends Command
{
    protected $mining_config = [];

    protected function configure()
    {
        $this->setName('YunMining')->setDescription('This is a YunMining');
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
        $this->mining_config = YunMiningConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write(" 配置为空");
            return;
        }

        $this->m_to_mtk_release(); // M令牌释放到MTK
        $this->release_static(); // 矿机收益
    }

    // M令牌释放到MTK
    protected function m_to_mtk_release()
    {
        Log::write(" M令牌释放到MTK开始");

        if ($this->mining_config['m_to_mtk_open'] != 1) {
            Log::write(" M令牌释放到MTK关闭");
            return;
        }
        $last_id = 0;
        while (true) {
            $CurrencyUser = CurrencyUser::where([
                'cu_id' => ['gt', $last_id],
                'num' => ['gt', 0],
                'currency_id' => $this->mining_config['m_currency_id'],
            ])->order('cu_id asc')->find();
            if (empty($CurrencyUser)) {
                Log::write(" M令牌释放到MTK已完成");
                break;
            }
            $last_id = $CurrencyUser['cu_id'];
            echo 'm_to_mtk_release:' . $last_id . "\r\n";

            // 添加奖励详情
            $flag = CurrencyUser::m_to_mtk_release($CurrencyUser, $this->mining_config);
            if ($flag === false) {
                Log::write(" M令牌释放到MTK失败" . $CurrencyUser['cu_id']);
            }
        }
        Log::write(" M令牌释放到MTK结束");
    }

    // 矿机收益
    protected function release_static()
    {
        Log::write(" 矿机收益开始");

        if ($this->mining_config['static_open'] != 1) {
            Log::write(" 矿机收益关闭");
            return;
        }
        $last_id = 0;
        while (true) {
            $YunMiningPay = YunMiningPay::where([
                'id' => ['gt', $last_id],
                'status' => 0,
            ])->order('id asc')->find();
            if (empty($YunMiningPay)) {
                Log::write(" 矿机收益已完成");
                break;
            }
            $last_id = $YunMiningPay['id'];
            echo 'release_static:' . $last_id . "\r\n";

            // 添加奖励详情
            $flag = YunMiningPay::release_static($YunMiningPay, $this->mining_config);
            if ($flag === false) {
                Log::write(" 矿机收益失败" . $YunMiningPay['id']);
            }
        }
        Log::write(" 矿机收益结束");
    }
}