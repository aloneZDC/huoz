<?php


namespace app\cli\controller;

use think\Log;
use think\Db;
use Workerman\Worker;
use app\common\model\ArkConfig;
use app\common\model\ArkMember;
use app\common\model\ArkGoodsList;
use app\common\model\ArkBuyList;
use app\common\model\ArkOrder;

class ArkAutomatic
{
    protected $name = '自动生成订单定时任务';
    protected $mining_config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ArkAutomatic';
        $this->worker->onWorkerStart = function ($worker) {
            while (true) {
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    /**
     * 每分钟执行一次
     */
    protected function doRun($worker_id = 0)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $this->mining_config = ArkConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }
        //生成订单
        $this->add_order();
    }

    //生成订单
    public function add_order() {
        $res = ArkGoodsList::where(['status' => 1, 'rocket_status' => 0, 'is_show' => 1])->select();
        if (!$res) return;
        foreach ($res as $key => $value) {
            $order_list = ArkBuyList::where(['goods_list_id' => $value['id'], 'status' => 0])->select();
            $auto_list = ArkBuyList::where(['goods_list_id' => $value['id'], 'status' => 0, 'type' => 3])->select();
            if (!$order_list && !$auto_list) {
                continue;
            }
            if (!empty($auto_list)) {//复利
                $flag = ArkBuyList::handle_order($auto_list);
                if ($flag === false) {
                    Log::write('生成订单失败：'. $value['id']);
                    return;
                }
            }
            //time() >= $value['start_time'] &&
            if (!empty($order_list)) {
                $flag = ArkBuyList::handle_order($order_list);
                if ($flag === false) {
                    Log::write('生成订单失败：'. $value['id']);
                    return;
                }
            }
        }
    }
}