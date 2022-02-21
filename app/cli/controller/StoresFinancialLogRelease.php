<?php
/**
 * 线下上级理财包释放即O券释放
 */

namespace app\cli\controller;

use app\common\model\CurrencyUser;
use app\common\model\StoresConfig;
use app\common\model\StoresConvertConfig;
use app\common\model\StoresFinancialAwardDetail;
use app\common\model\StoresFinancialLog;
use think\Db;
use think\Log;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class StoresFinancialLogRelease extends Command
{

    protected function configure()
    {
        $this->setName('StoresFinancialLogRelease')->setDescription('This is a StoresFinancialLogRelease');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        set_time_limit(0);
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_stop' => $today_start + 86400 - 1,
        ];
        $this->release_day($today_config);
    }

    //每日释放
    protected function release_day($today_config) {
        Log::write("理财包释放开始");
        $stores_config = StoresConfig::get_key_value();
        if(empty($stores_config) || empty($stores_config['stores_financial_release_percent'])) {
            Log::write("释放配置不存在");
            return;
        }

        $stores_convert_config = StoresConvertConfig::card_to_financial();
        if(empty($stores_convert_config)){
            Log::write("卡包兑换配置不存在");
            return;
        }

        if($stores_config['stores_financial_release_percent']<=0) {
            Log::write("释放关闭");
            return;
        }
        $currency_list = array_column($stores_convert_config,'to_currency_id');
        foreach ($currency_list as $currency_id) {
            $last_id = StoresFinancialLog::where(['type'=>'release','currency_id'=>$currency_id,'create_time'=>['between',[$today_config['today_start'],$today_config['today_stop']]  ] ])->max('third_id');
            while (true){
                $users_currency = CurrencyUser::where(['cu_id'=>['gt',$last_id],'currency_id'=>$currency_id,StoresConvertConfig::FINANCIAL_FIELD=>['gt',0]])->order('cu_id asc')->find();
                if(empty($users_currency)) {
                    Log::write($currency_id."理财包释放结束");
                    break;
                }

                $release_num = keepPoint($users_currency[StoresConvertConfig::FINANCIAL_FIELD] * $stores_config['stores_financial_release_percent']/100,6);
                if($release_num>=0.000001) {
                    //释放
                    StoresFinancialLog::release($users_currency,$release_num,$users_currency[StoresConvertConfig::FINANCIAL_FIELD],$stores_config['stores_financial_release_percent']);
                }
                $last_id = $users_currency['cu_id'];
            }
        }
    }
}