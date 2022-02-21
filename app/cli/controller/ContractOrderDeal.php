<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/20
 * Time: 19:23
 */

namespace app\cli\controller;
use app\common\model\ContractOrder;
use app\common\model\RegTemp;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomUsersRecord;
use app\common\model\UsersRelationship;
use think\Db;
use think\Exception;
use Workerman\Worker;
use think\Log;
class ContractOrderDeal
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ContractOrderDeal';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 合约订单队列自动处理，每分钟执行一次
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/6/25 14:54
     */
    protected function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        Log::write("合约订单处理:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $now = time();
            //首先处理队列中的订单
            $where = [
                'status'=>['in', '1,2'],//状态 1-队列中 2-队列处理失败 3-持仓中 4-结算中 5-已结算
                //'deal_price'=>['gt', 0],//成交价格
                'deal_price_1'=>['gt', 0],//成交价格-用户提交
            ];
            $order = ContractOrder::where($where)->order(['status'=>'asc', 'id'=>'asc'])->find();
            if (empty($order)) {
                sleep(1);
                continue;
            }

            $log = "order_id:{$order['id']},now:".date('Y-m-d H:i:s', $now).",start_time:".date('Y-m-d H:i:s', $order['start_time']).",end_time:".date('Y-m-d H:i:s', $order['end_time']);
            $r = ContractOrder::deal_order($order);
            if ($r['code'] != SUCCESS) {
                //Log::write("合约订单处理异常:" . $r['message'], 'INFO');
                $log .= ",合约订单处理异常:".$r['message'];
            }
            else {
                $log .= ",合约订单处理成功";
            }
            Log::write("合约订单处理:".$log, 'INFO');
            sleep(1);
        }
        Log::write("合约订单处理:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}