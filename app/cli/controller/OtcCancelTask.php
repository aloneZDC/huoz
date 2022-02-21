<?php
namespace app\cli\controller;
use app\common\model\Config;
use app\common\model\OrdersOtc;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * OTC广告 超出48小时自动撤销
 */
class OtcCancelTask
{
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'OtcCancelTaskWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true) {
                $this->doRun();
                sleep(5);
            }
        };
        Worker::runAll();
    }

    protected function doRun(){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->cancel();
    }

    //超时取消订单
    private function cancel() {
        $otc_orders_timeout_cancel = intval(Config::get_value('otc_orders_timeout_cancel',0));
        if($otc_orders_timeout_cancel<=0) {
            sleep(10);
            return;
        }
        //1小时内撤销需要手续费
        $otc_cancel_limit = intval(Config::get_value('otc_cancel_limit',0));

        $last_id = 0;
        $model = model('OrdersOtc');
        while (true) {
            $limit_time = time()-$otc_orders_timeout_cancel;
            $ordersInfo = Db::name('orders_otc')->where(['orders_id'=>['gt',$last_id],'status'=>['lt',2],'add_time'=>['lt',$limit_time] ])->order('orders_id asc')->find();
            if(!$ordersInfo){
                Log::write('OTC广告超时撤销已完成');
                sleep(10);
                break;
            }
            $last_id = $ordersInfo['orders_id'];


            $model->cancel($ordersInfo['member_id'],$ordersInfo['orders_id'],$otc_cancel_limit);
            sleep(1);
        }
    }
}
