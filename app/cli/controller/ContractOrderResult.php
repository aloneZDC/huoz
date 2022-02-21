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
class ContractOrderResult
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ContractOrderResult';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 房间自动结算，每分钟执行一次
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

        Log::write("合约订单结算:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $now = time();
            $where = [
                'status'=>4,//状态 1-队列中 2-队列处理失败 3-持仓中 4-结算中 5-已结算
                'closeout_type'=>['gt', 0],//平仓类型 1-到期平仓 2-止盈平仓 3-止损平仓 4-手动平仓
                'closeout_price'=>['gt', 0],//平仓价格
            ];
            $order = ContractOrder::where($where)->order(['status'=>'asc', 'id'=>'asc'])->find();
            if (empty($order)) {
                sleep(1);
                continue;
            }

            $log = "order_id:{$order['id']},now:".date('Y-m-d H:i:s', $now).",start_time:".date('Y-m-d H:i:s', $order['start_time']).",end_time:".date('Y-m-d H:i:s', $order['end_time']).",closeout_type:".ContractOrder::CLOSEOUT_TYPE_ENUM[$order['closeout_type']].",money_type:".ContractOrder::get_money_type_list()[$order['money_type'].'_'.$order['money_currency_id']]/*ContractOrder::MONEY_TYPE_ENUM[$order['money_type']]*/.",deal_price:{$order['deal_price']},stop_profit_type:".ContractOrder::STOP_ENUM[$order['stop_profit_type']].",stop_profit_percent:{$order['stop_profit_percent']},stop_profit_price:{$order['stop_profit_price']},stop_loss_type:".ContractOrder::STOP_ENUM[$order['stop_loss_type']].",stop_loss_percent:{$order['stop_loss_percent']},stop_loss_price:{$order['stop_loss_price']}";
            $r = ContractOrder::result_order($order, $log);
            if ($r['code'] != SUCCESS) {
                //Log::write("合约订单处理异常:" . $r['message'], 'INFO');
                $log .= ",合约订单结算异常:".$r['message'];
            }
            else {
                $log .= ",合约订单结算成功";
            }
            Log::write("合约订单结算:".$log, 'INFO');
        }
        Log::write("合约订单结算:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}