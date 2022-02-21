<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/20
 * Time: 19:23
 */

namespace app\cli\controller;
use app\common\model\ContractConfig;
use app\common\model\ContractOrder;
use app\common\model\ContractTrade;
use app\common\model\RegTemp;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomUsersRecord;
use app\common\model\UsersRelationship;
use think\Db;
use think\Exception;
use Workerman\Worker;
use think\Log;
class ContractOrderCloseout
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ContractOrderCloseout';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 合约订单自动平仓处理，每分钟执行一次
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

        Log::write("合约订单平仓处理:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            //首先处理持仓中的订单
            $now = time();
            $where = [
                'status'=>['in', '3,4'],//状态 1-队列中 2-队列处理失败 3-持仓中 4-结算中 5-已结算
                'closeout_type'=>['in', '0,4'],//平仓类型 1-到期平仓 2-止盈平仓 3-止损平仓 4-手动平仓
                //'closeout_type'=>0,//平仓类型 1-到期平仓 2-止盈平仓 3-止损平仓 4-手动平仓
                //'last_closeout_time'=>['elt', $now - 60],
                'next_closeout_time'=>['elt', $now],
                'closeout_price'=>['elt', 0],//平仓价格
            ];
            $order = ContractOrder::where($where)->order(['money_type'=>'asc','closeout_type'=>'desc','type'=>'asc','next_closeout_time'=>'asc'])->find();
            if (empty($order)) {
                sleep(1);
                continue;
            }

            $log = "order_id:{$order['id']},now:".date('Y-m-d H:i:s', $now).",add_time:".date('Y-m-d H:i:s', $order['add_time']).",start_time:".date('Y-m-d H:i:s', $order['start_time']).",end_time:".date('Y-m-d H:i:s', $order['end_time']).",next_closeout_time:".date('Y-m-d H:i:s', $order['next_closeout_time']).",buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",closeout_type:".ContractOrder::CLOSEOUT_TYPE_ENUM[$order['closeout_type']];
            $trade_info = ContractTrade::get($order['trade_id']);
            if ($order['closeout_type'] != 4) {//不是手动平仓
                $last_closeout_time = $order['last_closeout_time'];
                $closeout_time = $last_closeout_time > 0 ? $last_closeout_time + 60 : strtotime(date('Y-m-d H:i:00', $order['add_time']));//用于获取价格的时间点，默认订单订单成交时间分钟整点时间，每次处理成功(获取到价格)之后将last_closeout_time更新为该时间
                $log .= ",closeout_time,:".date('Y-m-d H:i:s', $closeout_time);
                /*//根据$closeout_time获取该时间点分钟整点的最高价、最低价，如22点23分50秒，那么获取22点23分的最高价、最低价
                $hign_price = 10644.57;
                $hign_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'hign_price', $closeout_time);
                $low_price = 9944.57;
                $low_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'low_price', $closeout_time);*/
                //根据当前时间获取这个时间分钟整点时间的收盘价，如22点23分50秒，那么获取22点23分的收盘价
                $close_price = 10544.57;
                if ($order['type'] == 1) {//1-时时合约
                    $price_time = $closeout_time;
                    $close_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'close_price', $price_time);
                    $hign_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'hign_price', $price_time);
                    $low_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'low_price', $price_time);
                }
                else {//2-永续合约
                    $price_time = $order['start_time'] ? : strtotime(date('Y-m-d H:i:00', $now));
                    $price_time = $closeout_time;
                    $close_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'close_price', $price_time);
                    $hign_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'hign_price', $price_time);
                    $low_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'low_price', $price_time);
                }
                //根据收盘价和交易对的价格涨跌比例，计算出买涨、买跌价格
                $zhang_price = keepPoint($close_price * (1 + $trade_info['price_rate'] / 10000), $trade_info['price_length']);
                //根据最高价和交易对的价格涨跌比例，计算出买涨价格
                $zhang_price = keepPoint($hign_price * (1 + $trade_info['price_rate'] / 10000), $trade_info['price_length']);
                $die_price = keepPoint($close_price * (1 - $trade_info['price_rate'] / 10000), $trade_info['price_length']);
                //根据最低价和交易对的价格涨跌比例，计算出买跌价格
                $die_price = keepPoint($low_price * (1 - $trade_info['price_rate'] / 10000), $trade_info['price_length']);
                $closeout_type = 0;
                $closeout_price = 0;
                if ($order['stop_profit_percent'] > 0) {
                    $stop_profit_price = $order['stop_profit_price'];
                }
                else {
                    $usdt_cny_price = $order['usdt_cny_price'];
                    $currency_cny_price = $order['money_currency_cny_price'];
                    $currency_num = $order['currency_num'];
                    $deal_price = $order['deal_price'];
                    $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 500);
                    if ($order['buy_type'] == 1) {//买涨
                        $stop_profit_price = $stop_profit_percent_max > 0 ? keepPoint((($stop_profit_percent_max / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    }
                    else {//买跌
                        $stop_profit_price = $stop_profit_percent_max > 0 ? keepPoint((-($stop_profit_percent_max / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    }
                }
                //if ($hign_price && $low_price) {//最高价、最低价获取成功
                if ($close_price && $zhang_price && $die_price) {//收盘价、买涨价、买跌价获取成功
                    //$log .= ",最高价、最低价获取成功";
                    $log .= ",收盘价、买涨价、买跌价获取成功";
                    //判断是否止盈/止损平仓
                    if ($order['buy_type'] == 1) {//买涨
                        //止盈平仓
                        //用最高价与订单的止盈平仓价格比较，如果价格大于等于订单的止盈平仓价格，那么该订单止盈平仓
                        //if ($hign_price >= $order['stop_profit_price']) {
                        //用买涨价与订单的止盈平仓价格比较，如果价格大于等于订单的止盈平仓价格，那么该订单止盈平仓
                        //2020-03-25，止盈平仓价>0时才需要判断止盈平仓
                        if ($zhang_price >= $stop_profit_price && $stop_profit_price > 0) {
                            $closeout_price = $zhang_price;
                            $closeout_type = 2;
                            $log .= ",止盈平仓";
                        }
                        else {
                            //止损平仓
                            //用最低价与订单的止损平仓价格比较，如果价格小于等于订单的止损平仓价格，那么该订单止损平仓
                            //if ($low_price <= $order['stop_loss_price']) {
                            //用买跌价与订单的止损平仓价格比较，如果价格小于等于订单的止损平仓价格，那么该订单止损平仓
                            //2020-03-25，止损平仓价>0时才需要判断止损平仓
                            if ($die_price <= $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {
                                $closeout_price = $die_price;
                                $closeout_type = 3;
                                $log .= ",止损平仓";
                            }
                        }
                    }
                    else {//买跌
                        //止盈平仓
                        //用最低价与订单的止盈平仓价格比较，如果价格小于等于订单的止盈平仓价格，那么该订单止盈平仓
                        //if ($low_price <= $order['stop_profit_price']) {
                        //用买跌价与订单的止盈平仓价格比较，如果价格小于等于订单的止盈平仓价格，那么该订单止盈平仓
                        //2020-03-25，止盈平仓价>0时才需要判断止盈平仓
                        if ($die_price <= $stop_profit_price && $stop_profit_price > 0) {
                            $closeout_price = $die_price;
                            $closeout_type = 2;
                            $log .= ",止盈平仓";
                        }
                        else {
                            //止损平仓
                            //用最高价与订单的止损平仓价格比较，如果价格大于等于订单的止损平仓价格，那么该订单止损平仓
                            //if ($hign_price >= $order['stop_loss_price']) {
                            //用买涨价与订单的止损平仓价格比较，如果价格大于等于订单的止损平仓价格，那么该订单止损平仓
                            //2020-03-25，止损平仓价>0时才需要判断止损平仓
                            if ($zhang_price >= $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {
                                $closeout_price = $zhang_price;
                                $closeout_type = 3;
                                $log .= ",止损平仓";
                            }
                        }
                    }
                    if ($closeout_type > 0 && $closeout_price > 0) {//处理成功(获取到相应价格进行比较)
                        $order['last_closeout_time'] = $closeout_time;
                        $log .= ",closeout_type,:".$closeout_type.",closeout_price,:".$closeout_price;
                        $r = ContractOrder::order_closeout($order, $closeout_type, $closeout_price);
                        if ($r['code'] != SUCCESS) {
                            //Log::write("合约订单平仓处理异常:" . $r['message'], 'INFO');
                            $log .= ",合约订单平仓处理异常:".$r['message'];
                        }
                        else {
                            $log .= ",合约订单平仓处理成功";
                        }
                    }
                    else {
                        $log .= ",非止盈/止损平仓";
                        //$order['last_closeout_time'] = ['inc', 60];
                        $order['last_closeout_time'] = $closeout_time;
                        //$order['next_closeout_time'] = ['inc', 60];//处理成功(获取到相应价格进行比较)之后+60秒，等待下一次平仓或者订单等待结算
                        $diff = intval((strtotime(date('Y-m-d H:i:00', $order['next_closeout_time'])) - $order['last_closeout_time']) / 60);
                        //if ($order['last_closeout_time'] < strtotime(date('Y-m-d H:i:00', $now)) - 60) {//说明前面有时间点处理异常，导致当前时间点没在默认的时间执行处理，下次平仓时间+10秒，等待下次处理
                        if ($diff > 1) {//说明前面有时间点处理异常，导致当前时间点没在默认的时间执行处理，下次平仓时间+10秒，等待下次处理
                            $order['next_closeout_time'] = ['inc', 10];
                        }
                        else {
                            $order['next_closeout_time'] = strtotime(date('Y-m-d H:i:00', $order['next_closeout_time'])) + 60;//处理成功(获取到相应价格进行比较)之后，设置为next_closeout_time的分钟整点时间+60秒，等待下一次平仓或者订单等待结算
                        }
                        $res = $order->save();
                        if ($res === false) {
                            //Log::write("合约订单平仓处理异常:更新记录失败-2", 'INFO');
                            $log .= ",更新记录失败-in line:".__LINE__;
                        }

                        if ($order['end_time'] <= $now && $order['type'] == 1) {//订单到期，且为时时合约订单
                            $log .= ",订单到期";
                            if ($order['end_time'] - $closeout_time <= 60) {//前面时间点都已平仓处理，自动平仓
                                $log .= ",前面时间点都已平仓处理";
                                //根据当前订单的期数时间获取这个时间段的收盘价作为平仓价，如22点20分-25分，那么获取22点20分-25分的收盘价作为平仓价
                                $close_price = 10544.57;
                                $timeDiff = $order['end_time'] - $order['start_time'];
                                if ($timeDiff == 300 || $timeDiff == 900 || $timeDiff == 1800 || $timeDiff == 3600) {//5分钟 15分钟 30分钟 1小时
                                    $close_price = \app\common\model\ContractKline::get_price($order['trade_id'], $timeDiff, 'close_price', $order['start_time']);
                                } else if ($timeDiff == 86400 || $timeDiff == 604800 || ($timeDiff >= 2419200 && $timeDiff <= 2678400)) {//1天 1周 1月
                                    $price_time = $order['end_time'] - 3600;
                                    $close_price = \app\common\model\ContractKline::get_price($order['trade_id'], 3600, 'close_price', $price_time);
                                } else {
                                    $price_time = $order['end_time'] - 60;
                                    $close_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'close_price', $price_time);
                                }
                                /*//根据收盘价和交易对的价格涨跌比例，计算出买涨、买跌价格
                                $zhang_price = keepPoint($close_price * (1 + $trade_info['price_rate'] / 10000), $trade_info['price_length']);
                                $die_price = keepPoint($close_price * (1 - $trade_info['price_rate'] / 10000), $trade_info['price_length']);*/
                                if ($close_price) {//平仓价格获取成功，平仓类型设置为1-自动平仓
                                    $log .= ",收盘价获取成功,自动平仓";
                                    $closeout_price = $close_price;
                                    /*if ($order['buy_type'] == 1) {//买涨
                                        $log .= ",买涨,买跌价作为平仓价";
                                        $closeout_price = $die_price;
                                    }
                                    else {//买跌
                                        $log .= ",买跌,买涨价作为平仓价";
                                        $closeout_price = $zhang_price;
                                    }*/
                                    $closeout_type = 1;
                                }
                                else {
                                    $log .= ",收盘价获取失败";
                                    if ($order['status'] != 4) {//订单到期且订单不在结算中
                                        $log .= ",订单到期且订单不在结算中,订单改为结算中,等待下一次平仓";
                                        $order['status'] = 4;
                                    }
                                    else {
                                        $log .= ",订单到期且订单在结算中,等待下一次平仓";
                                    }
                                }
                                $r = ContractOrder::order_closeout($order, $closeout_type, $closeout_price);
                                if ($r['code'] != SUCCESS) {
                                    //Log::write("合约订单平仓处理异常:" . $r['message'], 'INFO');
                                    $log .= ",合约订单平仓处理异常:".$r['message'];
                                }
                                else {
                                    $log .= ",合约订单平仓处理成功";
                                }
                            }
                            else {//前面时间点还有为平仓处理的，订单改为结算中，等待下一次平仓
                                if ($order['status'] != 4) {//订单到期且订单不在结算中
                                    $log .= ",前面时间点还有未平仓处理的,订单到期且订单不在结算中,订单改为结算中,等待下一次平仓";
                                    $order['status'] = 4;
                                    $res = $order->save();
                                    if ($res === false) {
                                        //Log::write("合约订单平仓处理异常:更新记录失败-3", 'INFO');
                                        $log .= ",更新记录失败-in line:".__LINE__;
                                    }
                                }
                                else {
                                    $log .= ",前面时间点还有未平仓处理的,订单到期且订单在结算中,等待下一次平仓";
                                }
                            }
                        }
                    }
                }
                else {
                    if ($now - $closeout_time >= 600) {//超过10分钟最高价、最低价获取失败，为防止堵塞平仓流程，跳过该时间点
                        $log .= ",最高价、最低价获取失败超过10分钟,跳过该时间点";
                        $order['last_closeout_time'] = $closeout_time;
                        $diff = intval((strtotime(date('Y-m-d H:i:00', $order['next_closeout_time'])) - $order['last_closeout_time']) / 60);
                        //if ($order['last_closeout_time'] < strtotime(date('Y-m-d H:i:00', $now)) - 60) {//说明前面有时间点处理异常，导致当前时间点没在默认的时间执行处理，下次平仓时间+10秒，等待下次处理
                        if ($diff > 1) {//说明前面有时间点处理异常，导致当前时间点没在默认的时间执行处理，下次平仓时间+10秒，等待下次处理
                            $order['next_closeout_time'] = ['inc', 10];
                            $log .= ",时间+10秒,等待下次处理";
                        }
                        else {
                            $order['next_closeout_time'] = strtotime(date('Y-m-d H:i:00', $order['next_closeout_time'])) + 60;//处理成功(获取到相应价格进行比较)之后，设置为next_closeout_time的分钟整点时间+60秒，等待下一次平仓或者订单等待结算
                            $log .= ",时间+60秒,等待下次处理";
                        }
                    }
                    else {
                        $log .= ",最高价、最低价获取失败,时间+10秒,等待下次处理";
                        $order['next_closeout_time'] = ['inc', 10];//处理失败(获取价格异常)，时间+10秒，等待下次处理
                    }
                    if ($order['end_time'] <= $now && $order['status'] != 4 && $order['type'] == 1) {//订单到期且订单不在结算中，且为时时合约订单
                        $log .= ",订单到期且订单不在结算中,订单改为结算中,等待下一次平仓";
                        $order['status'] = 4;
                    }
                    $res = $order->save();
                    if ($res === false) {
                        //Log::write("合约订单平仓处理异常:更新记录失败-3", 'INFO');
                        $log .= ",更新记录失败-in line:".__LINE__;
                    }
                }
            }
            else {//手动平仓
                $log .= ",closeout_price_1,:".$order['closeout_price_1'];
                if ($order['closeout_price'] <= 0) {//订单平仓价未获取到，重新进行获取
                    $closeout_time = strtotime(date('Y-m-d H:i:00', $order['closeout_time']));//用于获取价格的时间点，默认订单订单成交时间分钟整点时间，每次处理成功(获取到价格)之后将last_closeout_time更新为该时间
                    /*//根据订单平仓时间获取那个时间点分钟整点的收盘价作为平仓价，如22点23分50秒，那么获取22点23分的收盘价作为平仓价
                    $closeout_price = 10384.57;
                    $price_time = strtotime(date('Y-m-d H:i:00', $order['closeout_time']));
                    //$closeout_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'open_price', $price_time);
                    $closeout_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'close_price', $price_time);*/
                    $priceWhere = [
                        'trade_id'=>$order['trade_id'],
                        'type'=>60,
                        'add_time'=>$closeout_time,
                    ];
                    $priceFind = \app\common\model\ContractKline::where($priceWhere)->find();
                    if (!$priceFind) {
                        $log .= ",获取实时价格失败,时间+10秒,等待下次处理";
                        if ($now - $closeout_time >= 600) {//超过10分钟实时价格获取失败，为防止堵塞平仓流程，平仓时间+60s
                            $log .= ",实时价格获取失败超过10分钟,平仓时间+60s";
                            $order['closeout_time'] = ['inc', 60];
                        }
                        else {
                            $order['next_closeout_time'] = ['inc', 10];//处理失败(获取价格异常)，时间+10秒，等待下次处理
                        }
                        $res = $order->save();
                        if ($res === false) {
                            $log .= ",更新记录失败-in line:".__LINE__;
                        }
                    }
                    else {
                        //获取订单平仓价格-用户提交
                        $closeout_price_1 = $order['closeout_price_1'];
                        $hign_max = keepPoint($priceFind['hign_price'] * (1 + floatval(mt_rand(0, 10) / 10) * $trade_info['price_rate'] / 1000), $trade_info['price_length']);
                        $closeout_price = min($closeout_price_1, $hign_max);//订单平仓价格-用户提交如果大于最高价格，最高价格作为平仓价格
                        $low_min = keepPoint($priceFind['low_price'] * (1 - floatval(mt_rand(0, 10) / 10) * $trade_info['price_rate'] / 1000), $trade_info['price_length']);
                        $closeout_price = max($closeout_price, $low_min);//平仓价格如果小于最低价格，最低价格作为平仓价格
                        if (!$closeout_price) {
                            $log .= ",获取平仓价格失败";
                        }
                        else {
                            $order['closeout_price'] = $closeout_price;
                            $order['last_closeout_time'] = $closeout_time;
                            $res = $order->save();
                            if ($res === false) {
                                $log .= ",更新记录失败-in line:".__LINE__;
                            }
                            $log .= ",处理成功";
                        }
                    }
                }
                else {
                    $log .= ",平仓价格已经获取";
                }
            }
            Log::write("合约订单平仓处理:".$log, 'INFO');
            //sleep(1);
        }
        Log::write("合约订单平仓处理:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}