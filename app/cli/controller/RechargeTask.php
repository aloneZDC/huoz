<?php
namespace app\cli\controller;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 充币处理定时任务
 */
class RechargeTask
{
    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'RechargeTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    protected function doRun($worker_id=0) {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        try{
            (new Recharge())->recharge();
        } catch (Exception $e) {
            Log::error("rechangeTask 错误:".$e->getMessage());
        }
    }
}
