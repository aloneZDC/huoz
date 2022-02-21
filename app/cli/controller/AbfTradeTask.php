<?php
namespace app\cli\controller;
use app\common\model\AbfCurrencyRise;
use app\common\model\AbfKline;
use app\common\model\AbfOrders;
use app\common\model\AbfTrade;
use app\common\model\AbfTradeCurrency;
use app\common\model\CurrencyPriceTemp;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * ABF类型币币交易定时任务
 */
class AbfTradeTask
{
    private $name = "ABF类型币币交易";
    private $today = 0;

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'AbfTradeTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                try{
                    $this->doRun($worker->id);
                } catch (Exception $exception) {
                    Log::write("ABF类型币币交易:".$exception->getMessage());
                }
                sleep(1);
            }
        };
        Worker::runAll();
    }

    protected function doRun($worker_id=0) {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->trade_orders();
    }

    //撮合订单
    //每日价格自动上涨 撤销昨日价格的订单
    public function trade_orders() {
        Log::write("ABF类型币币交易开始");
        $this->today = todayBeginTimestamp();

        $abf_currency_list = AbfTradeCurrency::select();
        if(!$abf_currency_list) return false;

        //获取币种的价格
        $abf_trade_price = [];
        $robot_ids = [];
        foreach ($abf_currency_list as $abf_currency) {
            //获取是否是最新价格 不是则更新
            $currency_rise = Db::name('abf_currency_rise')->where([
                'currency_id' => $abf_currency['currency_id'],
                'start_time' => ['elt',$this->today],
                'stop_time' => ['egt',$this->today],
                'last_time' => ['lt',$this->today],
            ])->find();
            if($currency_rise) {
                Log::write("AbfTradeTask".$abf_currency['currency_id']."价格更新");
                $flag = AbfCurrencyRise::updatePrice($currency_rise,$this->today);
                if(!$flag) return false;
            }

            //获取是否是最新价格 不是则更新
            $currency_rise = Db::name('abf_currency_rise')->where([
                'currency_id' => $abf_currency['currency_trade_id'],
                'start_time' => ['elt',$this->today],
                'stop_time' => ['egt',$this->today],
                'last_time' => ['lt',$this->today],
            ])->find();
            if($currency_rise) {
                Log::write("AbfTradeTask".$abf_currency['currency_trade_id']."价格更新");
                $flag = AbfCurrencyRise::updatePrice($currency_rise,$this->today);
                if(!$flag) return false;
            }

            if($abf_currency['robot_id']) $robot_ids[] = $abf_currency['robot_id'];

            //获取最新的一条K线
            $abf_kline = AbfKline::getLastDayKline($abf_currency['currency_id'],$abf_currency['currency_trade_id']);
            if(!$abf_kline || $abf_kline['add_time']!=$this->today) {
                Log::write("AbfTradeTask"."k线图更新");
                $trade_price = AbfKline::insertDayKline($abf_currency['currency_id'],$abf_currency['currency_trade_id'],$this->today);
                if(!$trade_price) return false;
            } else {
                $trade_price = $abf_kline['close_price'];
            }

//            //如果开启自动买 则每日挂自动挂一个单
//            if($abf_currency['robot_id']>0 && $abf_currency['auto_buy']==1) {
//                $robot_orders = AbfOrders::where([
//                    'currency_id' => $abf_currency['currency_id'],
//                    'currency_trade_id' => $abf_currency['currency_trade_id'],
//                    'type' => 'buy',
//                    'member_id' => $abf_currency['robot_id'],
//                    'add_time' => ['gt',$this->today],
//                ])->find();
//                if(empty($robot_orders)) {
//                    Log::write("ABF类型币币交易机器人挂买单");
//                    if($abf_currency['auto_count']>0) {
//                        for ($count=0;$count<$abf_currency['auto_count'];$count++) {
//                            $auto_buy_num = randomFloat($abf_currency['auto_min_num'],$abf_currency['auto_max_num']);
//                            if($auto_buy_num>0) {
//                                AbfOrders::buy($abf_currency['robot_id'],$abf_currency['currency_id'],$abf_currency['currency_trade_id'],0,$auto_buy_num);
//                            }
//                        }
//                    }
//                }
//            }
//
//            //如果开启自动卖
//            if($abf_currency['robot_id']>0 && $abf_currency['auto_sell']==1) {
//                $robot_orders = AbfOrders::where([
//                    'currency_id' => $abf_currency['currency_id'],
//                    'currency_trade_id' => $abf_currency['currency_trade_id'],
//                    'type' => 'sell',
//                    'member_id' => $abf_currency['robot_id'],
//                    'add_time' => ['gt',$this->today],
//                ])->find();
//                if(empty($robot_orders)) {
//                    Log::write("ABF类型币币交易机器人挂卖单");
//                    if($abf_currency['auto_count']>0) {
//                        for ($count=0;$count<$abf_currency['auto_count'];$count++) {
//                            $auto_sell_num = randomFloat($abf_currency['auto_min_num'],$abf_currency['auto_max_num']);
//                            if($auto_sell_num>0) {
//                                AbfOrders::sell($abf_currency['robot_id'],$abf_currency['currency_id'],$abf_currency['currency_trade_id'],0,$auto_sell_num);
//                            }
//                        }
//                    }
//                }
//            }
            $abf_trade_price[$abf_currency['currency_id'].'_'.$abf_currency['currency_trade_id']] = $trade_price;
        }

        //撤销昨日订单
        while (true) {
            $abf_orders = AbfOrders::where(['status'=>0,'add_time'=>['lt',$this->today] ])->order('orders_id asc')->find();
            if(empty($abf_orders)) break;

            //撤销非今日价格的订单
            $res = AbfOrders::cancel($abf_orders['member_id'],$abf_orders['orders_id']);
            if($res['code']!=SUCCESS) {
                sleep(1);
            }
        }

        $count = 0;
        while ($count< 500) {
            $count++;

            $cur_day = todayBeginTimestamp();
            if($cur_day!=$this->today) {
                return true;
            }

            //买单小于5个就自动挂买单（8至10笔，每笔500至1500
            foreach ($abf_currency_list as $abf_currency) {
                if($abf_currency['robot_id']>0 && $abf_currency['auto_buy']==1) {
                    $abf_buy_orders_total = AbfOrders::where([
                        'currency_id' => $abf_currency['currency_id'],
                        'currency_trade_id' => $abf_currency['currency_trade_id'],
                        'type' => 'buy',
                        'status' => 0,
                    ])->count();
                    if($abf_buy_orders_total<$abf_currency['auto_count_check']) {
                        if($abf_currency['auto_count']>0) {
                            $auto_count = rand($abf_currency['auto_count'],$abf_currency['auto_count_max']);
                            Log::write("ABF类型币币交易机器人挂买单".$auto_count);
                            for ($count=0;$count<$auto_count;$count++) {
                                $auto_buy_num = randomFloat($abf_currency['auto_min_num'],$abf_currency['auto_max_num']);
                                if($auto_buy_num>0) {
                                    $res = AbfOrders::buy($abf_currency['robot_id'],$abf_currency['currency_id'],$abf_currency['currency_trade_id'],0,$auto_buy_num);
                                    if($res['code']!=SUCCESS) {
                                        Log::write("ABF类型币币交易机器人挂买单error".$res['message']);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $cur_count = 0;
            foreach ($abf_currency_list as $abf_currency) {
                //优先机器人的订单
                $abf_orders = null;
    //            if(!empty($robot_ids)) {
//                    $abf_orders = AbfOrders::where([
//                        'currency_id'=>$abf_currency['currency_id'],
//                        'currency_trade_id' => $abf_currency['currency_id'],
//                        'status'=>0
//                    ])->where(['member_id'=>['in',$robot_ids]])->order('orders_id asc')->find();
    //            }

                if(empty($abf_orders)) {
                    $abf_orders = AbfOrders::where([
                        'currency_id'=>$abf_currency['currency_id'],
                        'currency_trade_id' => $abf_currency['currency_trade_id'],
                        'status'=> 0
                    ])->order('orders_id asc')->find();
                }

                if(empty($abf_orders)) {
                    continue;
                }

                $cur_count++;

                //昨日的订单 不处理
                if($abf_orders['add_time']<$this->today) {
                    return true;
                }

                if(!isset( $abf_trade_price[ $abf_orders['currency_id'].'_'.$abf_orders['currency_trade_id'] ] ) ) {
                    //撤销已关闭币种的挂单
                    AbfOrders::cancel($abf_orders['member_id'],$abf_orders['orders_id']);
                } else {
                    $cur_price = $abf_trade_price[ $abf_orders['currency_id'].'_'.$abf_orders['currency_trade_id'] ];
                    if($abf_orders['price']!= $cur_price) {
                        //撤销非今日价格的订单
                        AbfOrders::cancel($abf_orders['member_id'],$abf_orders['orders_id']);
                    } else {
                        //撮合交易
                        $flag = AbfTrade::trade($abf_orders);
                        if($flag===2) {
                            //如果无法撮合成功 则休眠1秒
                            sleep(1);
                        }

                    }
                }
            }
            if($cur_count==0) {
                sleep(1);
            }
        }
    }
}
