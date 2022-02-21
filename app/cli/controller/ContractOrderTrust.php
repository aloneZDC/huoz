<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/20
 * Time: 19:23
 */

namespace app\cli\controller;
use app\common\model\ContractOrder;
use app\common\model\ContractTrade;
use think\Db;
use think\Exception;
use Workerman\Worker;
use think\Log;
class ContractOrderTrust
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ContractOrderTrust';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 合约委托订单自动处理，每分钟执行一次
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

        Log::write("合约委托订单处理:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $now = time();

            $where = [
                'switch'=>1,
            ];
            $tradeList = ContractTrade::where($where)->order('sort', 'desc')->select();
            $count = 0;
            foreach ($tradeList as $value) {
                $trade_id = $value['id'];
                $price_time = strtotime(date('Y-m-d H:i:00'));
                //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
                //$close_price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
                $priceWhere = [
                    'trade_id'=>$trade_id,
                    'type'=>60,
                    'add_time'=>$price_time,
                ];
                $priceFind = \app\common\model\ContractKline::where($priceWhere)->find();
                if (!$priceFind) {
                    Log::write("合约委托订单处理:trade_id:{$trade_id},未获取到当前价格", 'INFO');
                    continue;
                }
                $close_price = $priceFind['close_price'];
                $high_price = $priceFind['hign_price'];
                $low_price = $priceFind['low_price'];
                //Log::write("合约委托订单处理:trade_id:{$trade_id},close_price:{$close_price},high_price:{$high_price},low_price:{$low_price}", 'INFO');

                //买涨
                //2020-06-09，用户手动设定委托价时，如果委托价高于当前市场价，选择买涨，则以当前市场价立即撮合成交；比方当前价格为9800，设定委托价为9900，买涨，则按9800立即成交
                $where = [
                    'trade_id'=>$trade_id,
                    'buy_type'=>1,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    'trust_price'=>['exp',Db::raw('>=`close_price`')],//委托价 永续合约
                    'close_price'=>['gt',0],//实时价格-用户提交 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",close_price:".$order['close_price'].',买涨时委托价高于当前市场价';
                        $r = ContractOrder::deal_trust($order, 2);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                            Log::write("合约委托订单处理:".$log, 'INFO');
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        //Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }
                //用实时价$close_price判断
                $where = [
                    'trade_id'=>$trade_id,
                    //'buy_type'=>1,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    //'trust_price'=>[['elt', $close_price],['exp',Db::raw('>=`close_price`')]],//委托价 永续合约
                    'trust_price'=>[[['elt', $close_price],['exp',Db::raw('>=`close_price`')],'and'],[['egt', $close_price],['exp',Db::raw('<=`close_price`')],'and'],'or'],//委托价 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",close_price:".$close_price.',用实时价判断';
                        $r = ContractOrder::deal_trust($order);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                            Log::write("合约委托订单处理:".$log, 'INFO');
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        //Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }
                //用最高价$high_price判断
                $where = [
                    'trade_id'=>$trade_id,
                    //'buy_type'=>1,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    'trust_time'=>['elt', $price_time],//委托时间
                    //'trust_price'=>[['elt', $high_price],['exp',Db::raw('>=`close_price`')]],//委托价 永续合约
                    'trust_price'=>[[['elt', $high_price],['exp',Db::raw('>=`close_price`')],'and'],[['egt', $high_price],['exp',Db::raw('<=`close_price`')],'and'],'or'],//委托价 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",high_price:".$high_price.',用最高价判断';
                        $r = ContractOrder::deal_trust($order);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                            Log::write("合约委托订单处理:".$log, 'INFO');
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        //Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }

                //买跌
                //2020-06-09，如果委托价低于当前市场价，选择买跌，则以当前市场价格立即成交。比方当前价是9800，设定委托价9700，买跌，则按9800成交
                $where = [
                    'trade_id'=>$trade_id,
                    'buy_type'=>2,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    'trust_price'=>['exp',Db::raw('<=`close_price`')],//委托价 永续合约
                    'close_price'=>['gt',0],//实时价格-用户提交 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",close_price:".$order['close_price'].',买跌时委托价低于当前市场价';
                        $r = ContractOrder::deal_trust($order, 2);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                            Log::write("合约委托订单处理:".$log, 'INFO');
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        //Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }
                /*//用实时价$close_price判断
                $where = [
                    'trade_id'=>$trade_id,
                    'buy_type'=>2,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    'trust_price'=>[['egt', $close_price],['exp',Db::raw('<=`close_price`')]],//委托价 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",close_price:".$close_price.',用实时价判断';
                        $r = ContractOrder::deal_trust($order);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }*/
                //用最低价$low_price判断
                $where = [
                    'trade_id'=>$trade_id,
                    //'buy_type'=>2,//买类型 1-买涨 2-买跌
                    'type'=>2,//订单类型 1-交割合约 2-永续合约
                    'trust_status'=>1,//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    'trust_time'=>['elt', $price_time],//委托时间
                    //'trust_price'=>[['egt', $low_price],['exp',Db::raw('<=`close_price`')]],//委托价 永续合约
                    'trust_price'=>[[['elt', $low_price],['exp',Db::raw('>=`close_price`')],'and'],[['egt', $low_price],['exp',Db::raw('<=`close_price`')],'and'],'or'],//委托价 永续合约
                ];
                $orders = ContractOrder::where($where)->select();
                if (count($orders) > 0) {
                    $count += count($orders);
                    foreach ($orders as $order) {
                        $log = "order_id:{$order['id']},trade_id:{$trade_id},buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",now:".date('Y-m-d H:i:s', $now).",trust_time:".date('Y-m-d H:i:s', $order['trust_time']).",trust_price:".$order['trust_price'].",low_price:".$low_price.',用最低价判断';
                        $r = ContractOrder::deal_trust($order);
                        if ($r['code'] != SUCCESS) {
                            $log .= ",合约委托订单处理异常:".$r['message'];
                            Log::write("合约委托订单处理:".$log, 'INFO');
                        }
                        else {
                            $log .= ",合约委托订单处理成功";
                        }
                        //Log::write("合约委托订单处理:".$log, 'INFO');
                    }
                }
            }
            if ($count <= 0) sleep(1);
        }
        Log::write("合约委托订单处理:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}