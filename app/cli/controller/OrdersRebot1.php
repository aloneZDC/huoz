<?php
namespace app\cli\controller;

use app\common\model\Currency;
use think\Log;
use think\Db;

use Workerman\Worker;

/**
 * 挂单机器人1
 */
class OrdersRebot1 extends OrdersRebotCommon
{
    protected $tradeList = [];

    protected $rebotId = 1;//机器人id

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'OrdersRebot'.$this->rebotId;
        $this->worker->onWorkerStart = function($worker) {
            while (true) {
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 挂单机器人
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $today = date('Y-m-d');

        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        $this->rebotId > 1 && sleep(($this->rebotId - 1));

        Log::write("挂单机器人:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $select = Db::name('OrdersRebotTrade')->where(['rebot_id'=>$this->rebotId,'rebot_switch'=>1])->order('id asc')->select();
        foreach ($select as $key => $value) {
            $this->tradeList[] = $value;
            $currency = Currency::get($value['currency_id']);
            if (!array_key_exists($value['currency_id'], $this->currencyList)) {
                $this->currencyList[$value['currency_id']] = $currency;
            }
            $currency_trade = Currency::get($value['trade_currency_id']);
            if (!array_key_exists($value['trade_currency_id'], $this->currencyList)) {
                $this->currencyList[$value['trade_currency_id']] = $currency_trade;
            }
            $tradeName = $currency['currency_name'].'/'.$currency_trade['currency_name'];
            $this->tradeNameList[$value['currency_id'].'_'.$value['trade_currency_id']] = $tradeName;
        }

        $runNum = 1;
        while($runNum < 100) {
            $runNum++;

            $this->sleep = true;

            foreach ($select as $key => $value) {
                if ($value['rebot_type'] == 1) {//机器人类型 1-平台币机器人 2-主流币机器人
                    $time = $this->sleepTime1;
                    $sec = date('s');
                    $diff = $time - $sec;
                    if ($diff < 0) $diff += $time;
                    if ($diff > 0) sleep($diff);
                    $this->rebot($value);
                }
                else if ($value['rebot_type'] == 2) {
                    $this->rebot_huobi($value);
                }
            }

            //sleep(1);

            if ($this->sleep) {
                usleep($this->sleepTime);
            }
        }
        Log::write("挂单机器人:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}
