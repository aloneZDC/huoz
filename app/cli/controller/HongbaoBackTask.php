<?php
namespace app\cli\controller;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoLog;
use Workerman\Worker;
use think\Log;

/**
 * 红包返还定时任务
 */
class HongbaoBackTask
{
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'HongbaoBackTask';
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

        Log::write("红包返还定时任务:".date('Y-m-d H:i:s'), 'INFO');
        $hongbao_config = HongbaoConfig::get_key_value();
        $hongbao_back_time = isset($hongbao_config['hongbao_back_time']) ? $hongbao_config['hongbao_back_time'] : 86400;

        $hongbao_currency = Currency::where(['currency_mark'=>$hongbao_config['hongbao_currency_mark']])->field('currency_id,currency_mark')->find();
        if(empty($hongbao_currency)) {
            Log::write("红包返还定时任务:币种错误");
            sleep(1);
            return;
        }

        $hongbao_back_currency = Currency::where(['currency_mark'=>$hongbao_config['hongbao_back_currency_mark']])->field('currency_id,currency_mark')->find();
        if(empty($hongbao_back_currency)) {
            Log::write("红包返还定时任务:返还币种错误");
            sleep(1);
            return;
        }

        $hongbao_config['hongbao_back_currency_id'] = $hongbao_back_currency['currency_id'];
        if($hongbao_currency['currency_id']==$hongbao_back_currency['currency_id']){
            $hongbao_config['hongbao_currency_ratio'] = 1;
        } else {
            $hongbao_currency_price = CurrencyPriceTemp::get_price_currency_id($hongbao_currency['currency_id'],'CNY');
            $hongbao_back_currency_price = CurrencyPriceTemp::get_price_currency_id($hongbao_back_currency['currency_id'],'CNY');
            if(empty($hongbao_currency_price) || empty($hongbao_back_currency_price)) {
                Log::write("红包返还定时任务:返还币种比例错误");
                sleep(1);
                return;
            }
            $hongbao_config['hongbao_currency_ratio'] = keepPoint($hongbao_currency_price/$hongbao_back_currency_price,6);
        }

        $runNum = 1;
        while($runNum<200) {
            $runNum++;

            $start_time = time()-$hongbao_back_time;
            $hongbao = HongbaoLog::where(['is_back'=>0,'create_time'=>['lt',$start_time]])->order('id asc')->find();
            if(empty($hongbao)) {
                sleep(1);
                continue;
            }

            $flag = HongbaoLog::back_hongbao($hongbao,$hongbao_config);
        }
        Log::write("红包返还定时任务结束:".date('Y-m-d H:i:s'), 'INFO');
    }
}
