<?php
namespace app\cli\controller;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\FlopTrade;
use app\common\model\FlopTradeRelease;
use app\common\model\FlopTradeReleaseConfig;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoLog;
use Workerman\Worker;
use think\Log;

/**
 * 红包返还定时任务
 */
class FlopReleaseTask
{
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'FlopReleaseTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true) {
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    protected function doRun(){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        Log::write("翻牌释放定时任务:".date('Y-m-d H:i:s'), 'INFO');

        $flop_team_release_max = $flop_team_contract_min = 0;
        $config = HongbaoConfig::get_key_value();
        if($config) {
            if($config['flop_team_release_max']) $flop_team_release_max = $config['flop_team_release_max'];
            if($config['flop_team_contract_min']) $flop_team_contract_min = $config['flop_team_contract_min'];
        }

        $flop_release_config = FlopTradeReleaseConfig::getList();
        $runNum = 1;
        while($runNum<200) {
            $runNum++;

            $flop_trade = FlopTrade::where(['type'=>'buy','is_release'=>0])->order('trade_id asc')->find();
            if(empty($flop_trade)) {
                sleep(1);
                continue;
            }
            $flag = FlopTradeRelease::release_num($flop_trade,$flop_release_config,$flop_team_release_max,$flop_team_contract_min);
        }
        Log::write("翻牌释放定时任务结束:".date('Y-m-d H:i:s'), 'INFO');
    }
}
