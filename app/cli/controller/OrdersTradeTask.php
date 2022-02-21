<?php
namespace app\cli\controller;
use app\common\model\Currency;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 币币交易订单撮合交易定时任务
 */
class OrdersTradeTask
{
    protected $tradeList = [];

    protected $tradeNameList = [];

    protected $sleep = true;

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'OrdersTradeTaskWorker';
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

		Log::write("币币交易订单交易定时任务:".date('Y-m-d H:i:s'), 'INFO');

        $this->tradeList = null;
        $this->tradeNameList = null;
        $select = Db::name('OrdersRebotTrade')->order('id asc')->select();
        foreach ($select as $key => $value) {
            $this->tradeList[] = $value;
            $currency = Currency::get($value['currency_id']);
            $currency_trade = Currency::get($value['trade_currency_id']);
            $tradeName = $currency['currency_name'].'/'.$currency_trade['currency_name'];
            $this->tradeNameList[$value['currency_id'].'_'.$value['trade_currency_id']] = $tradeName;
        }

        $runNum = 1;
        while($runNum < 2000) {
			$runNum++;

			$this->sleep = true;
            foreach ($this->tradeList as $key => $value) {
                $currency_id = $value['currency_id'];
                $trade_currency_id = $value['trade_currency_id'];
                $tradeName = $this->tradeNameList[$currency_id.'_'.$trade_currency_id];
                $buyOneOrder = $this->getOneOrders('buy', $currency_id, 0, $trade_currency_id);
                //如果没有相匹配的订单，sleep(1)
                if (empty($buyOneOrder)) {
                    //sleep(1);
                    //usleep(500000);//0.5s
                    continue;
                }
                /*else {
                    $this->sleep = $this->sleep && false;
                }*/

                try {
                    Db::startTrans();

                    $num = $buyOneOrder['num'] - $buyOneOrder['trade_num'];
                    $num = floatval(bcsub($buyOneOrder['num'], $buyOneOrder['trade_num'], 6));
                    if ($num > 0) {
                        $result = $this->trade($buyOneOrder, $buyOneOrder['currency_id'], $buyOneOrder['type'], $num, $buyOneOrder['price'], $buyOneOrder['currency_trade_id']);
                    }

                    Db::commit();
                }
                catch (Exception $e) {
                    Db::rollback();

                    Log::write("币币交易订单交易定时任务,交易对:{$tradeName},订单:{$buyOneOrder['orders_id']},交易失败,异常信息:".$e->getMessage());
                }
            }
            if ($this->sleep) {
                usleep(500000);//0.5s
            }
		}
        Log::write("币币交易订单交易定时任务:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }

    private function trade($order, $currencyId, $type, $num, $price,$trade_currency_id)
    {
        $tradeName = $this->tradeNameList[$currencyId.'_'.$trade_currency_id];
        $time1 = microtime(true);
        if ($type == 'buy') {
            $trade_type = 'sell';
            //$rebotName = '买单机器人';
        } else {
            $trade_type = 'buy';
            //$rebotName = '卖单机器人';
        }
        $memberId = $order['member_id'];
        //获取操作人一个订单
        //$order=$this->getFirstOrdersByMember($memberId,$type ,$currencyId,$trade_currency_id);
        //$order = db('Orders')->where('orders_id', $orders_id)->find();
        //获取对应交易的一个订单
        $trade_order=$this->getOneOrders($trade_type, $currencyId,$price,$trade_currency_id);
        //如果没有相匹配的订单，直接返回
        if (empty($trade_order) ) {
            //sleep(1);
            //usleep(500000);//0.5s
            return true;
        }
        else {
            $this->sleep = $this->sleep && false;
        }

        //如果有就处理订单
        $trade_num = min($num, keepPoint($trade_order['num'] - $trade_order['trade_num'], 6));
        $num1 = floatval(bcsub($trade_order['num'], $trade_order['trade_num'], 6));
        $trade_num = min($num, $num1);

        //增加本订单的已经交易的数量
        //$r[] = db('Orders')->where("orders_id={$order['orders_id']}")->setInc('trade_num', $trade_num);
        //$r[] = db('Orders')->where("orders_id={$order['orders_id']}")->setField('trade_time', time());
        /*$flag = db('Orders')->where("orders_id",$order['orders_id'])->update([
            'trade_num'=>['inc', $trade_num],'trade_time'=>time(),
        ]);
        if ($flag === false) throw new Exception('更新本订单的已经交易的数量失败-in line:'.__LINE__);*/
        $flag = db('Orders')->where(['orders_id'=>$order['orders_id'],'trade_num'=>$order['trade_num']])->setInc('trade_num', $trade_num);
        if (!$flag) throw new Exception('更新本订单的已经交易的数量失败-in line:'.__LINE__);
        $flag = db('Orders')->where("orders_id",$order['orders_id'])->setField('trade_time', time());
        if ($flag === false) throw new Exception('更新本订单的交易时间失败-in line:'.__LINE__);

        //增加trade订单的已经交易的数量
        //$r[] = db('Orders')->where("orders_id={$trade_order['orders_id']}")->setInc('trade_num', $trade_num);
        //$r[] = db('Orders')->where("orders_id={$trade_order['orders_id']}")->setField('trade_time', time());
        /*$flag = db('Orders')->where("orders_id",$trade_order['orders_id'])->update([
            'trade_num'=>['inc', $trade_num],'trade_time'=>time(),
        ]);
        if ($flag === false) throw new Exception('更新trade订单的已经交易的数量失败-in line:'.__LINE__);*/
        $flag = db('Orders')->where(['orders_id'=>$trade_order['orders_id'],'trade_num'=>$trade_order['trade_num']])->setInc('trade_num', $trade_num);
        if (!$flag) throw new Exception('更新trade订单的已经交易的数量失败-in line:'.__LINE__);
        $flag = db('Orders')->where("orders_id",$trade_order['orders_id'])->setField('trade_time', time());
        if ($flag === false) throw new Exception('更新trade订单的交易时间失败-in line:'.__LINE__);

        //更新一下订单状态
        //$r[] = db('Orders')->where("trade_num>0 and status=0")->setField('status', 1);
        $where = "orders_id in ({$order['orders_id']},{$trade_order['orders_id']}) and ";
        //$flag = db('Orders')->where("trade_num>0 and status=0")->setField('status', 1);
        $flag = db('Orders')->where($where."trade_num>0 and status=0")->setField('status', 1);
        if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);
        //$r[] = db('Orders')->where("num=trade_num")->setField('status', 2);
        //$flag = db('Orders')->where("num=trade_num")->setField('status', 2);
        $flag = db('Orders')->where($where."num=trade_num")->setField('status', 2);
        if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);

        $order1 = db('Orders')->where('orders_id', $order['orders_id'])->find();
        $a = bcsub($order1['num'], $order1['trade_num'], 6);
        if (bccomp('0.000002', $a, 6) >= 0) {
            $flag = db('Orders')->where("orders_id",$order['orders_id'])->setField('status', 2);
            if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);
        }
        $order2 = db('Orders')->where('orders_id', $trade_order['orders_id'])->find();
        $b = bcsub($order2['num'], $order2['trade_num'], 6);
        if (bccomp('0.000002', $b, 6) >= 0) {
            $flag = db('Orders')->where("orders_id",$trade_order['orders_id'])->setField('status', 2);
            if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);
        }

        //处理资金
        $trade_price = 0;
        switch ($type) {
            case 'buy':
                //$order_money = sprintf('%.6f', $trade_num * $order['price'] * (1 + $order['fee']));
                $order_money = keepPoint($trade_num * $order['price'] * (1 + $order['fee']), 6);
                //$trade_order_money = $trade_num * $trade_order['price'] * (1 - $trade_order['fee']);
                $trade_order_money = keepPoint($trade_num * $trade_order['price'] * (1 - $trade_order['fee']), 6);
                $trade_price = min($order['price'], $trade_order['price']);
                //$trade_price=$order['price'];
                $flag = model("AccountBook")->addLog([
                    'member_id'=>$memberId,
                    'currency_id'=>$order['currency_id'],
                    'number_type'=>1,
                    'number'=>$trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=>0,
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_trade_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败,trade_num:'.$trade_num.'-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'inc', 'num');
                $flag = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=>$trade_order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    //'fee'=>$trade_num * $trade_order['price'] * ($trade_order['fee']),
                    'fee'=>keepPoint($trade_num * $trade_order['price'] * ($trade_order['fee']), 6),
                    'to_member_id'=>$order['member_id'],
                    'to_currency_id'=>$trade_order['currency_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'inc', 'num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                $back_price = $order['price'] - $trade_price;
                if ($back_price > 0) {
                    if ($order['fee'] > 0) {
                        $backFee = keepPoint($trade_num * $back_price * $order['fee'], 6);
                        if ($backFee > 0) {
                            //返还未成交部分手续费
                            $flag = model("AccountBook")->addLog([
                                'member_id'=>$memberId,
                                'currency_id'=>$order['currency_trade_id'],
                                'number_type'=>1,
                                'number'=> $backFee,
                                'type'=>11,
                                'content'=>"lan_Return_charges",
                                'fee'=>0,
                                'to_member_id'=>$trade_order['member_id'],
                                'to_currency_id'=>$order['currency_id'],
                                'third_id'=>$order['orders_id'],
                            ]);
                            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                            //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                            $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $backFee, 'inc', 'num');
                            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                        }
                    }

                    $backNum = keepPoint($trade_num * $back_price, 6);
                    if ($backNum > 0) {
                        //返还多扣除的挂单金额
                        $flag = model("AccountBook")->addLog([
                            'member_id'=>$memberId,
                            'currency_id'=>$order['currency_trade_id'],
                            'number_type'=>1,
                            'number'=> $backNum,
                            'type'=>11,
                            'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                            'fee'=>0,
                            'to_member_id'=>$trade_order['member_id'],
                            'to_currency_id'=>$order['currency_id'],
                            'third_id'=>$order['orders_id'],
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                        //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                        $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $backNum, 'inc', 'num');
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                    }
                }
                break;
            case 'sell':
                //$order_money = $trade_num * $order['price'] * (1 - $order['fee']);
                $order_money = keepPoint($trade_num * $order['price'] * (1 - $order['fee']), 6);
                //$trade_order_money = sprintf('%.6f', $trade_num * $trade_order['price'] * (1 + $trade_order['fee']));
                $trade_order_money = keepPoint($trade_num * $trade_order['price'] * (1 + $trade_order['fee']), 6);
                $trade_price = max($order['price'], $trade_order['price']);

                //$r[] = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$memberId,
                    'currency_id'=>$order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=> $order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    //'fee'=> $trade_num * $order['price'] * ($order['fee']),
                    'fee'=> keepPoint($trade_num * $order['price'] * ($order['fee']), 6),
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'inc', 'num');
                $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_id'],
                    'number_type'=>1,
                    'number'=> $trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> 0,
                    'to_member_id'=>$memberId,
                    'to_currency_id'=>$trade_order['currency_trade_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'inc', 'num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                $back_price=$trade_price-$order['price'];

                if ($back_price > 0) {

                    if ($order['fee'] > 0) {
                        $backFee = keepPoint($trade_num * $back_price * $order['fee'], 6);
                        if ($backFee > 0) {
                            //返还未成交部分手续费
                            $flag = model("AccountBook")->addLog([
                                'member_id'=>$memberId,
                                'currency_id'=>$order['currency_trade_id'],
                                'number_type'=>1,
                                'number'=> $backFee,
                                'type'=>11,
                                'content'=>"lan_Return_charges",
                                'fee'=>0,
                                'to_member_id'=>$trade_order['member_id'],
                                'to_currency_id'=>$order['currency_id'],
                                'third_id'=>$order['orders_id'],
                            ]);
                            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                            //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                            $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $backFee, 'inc', 'num');
                            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                        }
                    }

                    $backNum = keepPoint($trade_num * $back_price, 6);
                    if ($backNum > 0) {
                        //返还多扣除的挂单金额
                        $flag = model("AccountBook")->addLog([
                            'member_id'=>$memberId,
                            'currency_id'=>$order['currency_trade_id'],
                            'number_type'=>1,
                            'number'=> $backNum,
                            'type'=>11,
                            'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                            'fee'=>0,
                            'to_member_id'=>$trade_order['member_id'],
                            'to_currency_id'=>$order['currency_id'],
                            'third_id'=>$order['orders_id'],
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                        //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                        $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $backNum, 'inc', 'num');
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                    }
                }
                break;
        }

        //写入成交表
        //$r[] = $trade_id = $this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'], $trade_price, $trade_num, $order['type'], $order['fee'],$trade_order['orders_id'],$trade_order['member_id']);
        $flag = $trade_id = $this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'], $trade_price, $trade_num, $order['type'], $order['fee'],$trade_order['orders_id'],$order['orders_id'],$trade_order['member_id']);
        if ($flag === false) throw new Exception('新增交易记录失败-in line:'.__LINE__);
        //$r[] = $trade_id2 = $this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'], $trade_order['fee'],$order['orders_id'],$order['member_id']);
        $flag = $trade_id2 = $this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'], $trade_order['fee'],$order['orders_id'],$trade_order['orders_id'],$order['member_id']);
        if ($flag === false) throw new Exception('新增交易记录失败-in line:'.__LINE__);

        //手续费
        $time = time();
        $order_fee = ($trade_num * $trade_price) * $order['fee'];
        $trade_order_fee = ($trade_num * $trade_price) * $trade_order['fee'];

        if ($order_fee > 0) {

            //$r[] = $this->addFinance($order['member_id'], 11, lang('lan_trade_exchange_charge'), $order_fee, 2, $order['currency_id'],$trade_id);
            $flag = $this->addFinance($order['member_id'], 11, lang('lan_trade_exchange_charge'), $order_fee, 2, $order['currency_id'],$trade_id);
            if ($flag === false) throw new Exception('新增财务日志失败-in line:'.__LINE__);
            //写入手续费表
            $add = [
                'member_id' => $order['member_id'],
                'fee' => $order_fee,
                'currency_id' => $order['currency_id'],
                'currency_trade_id' => $order['currency_trade_id'],
                'type' => $order['type'],
                'add_time' => $time
            ];
            //$r[] = db('mining_fee')->insert($add);
            $flag = db('mining_fee')->insert($add);
            if ($flag === false) throw new Exception('新增挖矿分红手续费记录失败-in line:'.__LINE__);

        }

        if ($trade_order_fee > 0) {
            //$r[] = $this->addFinance($trade_order['member_id'], 11, lang('lan_trade_exchange_charge'), $trade_order_fee, 2, $trade_order['currency_id'],$trade_id2);
            $flag = $this->addFinance($trade_order['member_id'], 11, lang('lan_trade_exchange_charge'), $trade_order_fee, 2, $trade_order['currency_id'],$trade_id2);
            if ($flag === false) throw new Exception('新增财务日志失败-in line:'.__LINE__);
            //写入手续费表
            $add2 = [
                'member_id' => $trade_order['member_id'],
                'fee' => $trade_order_fee,
                'currency_id' => $trade_order['currency_id'],
                'currency_trade_id' => $trade_order['currency_trade_id'],
                'type' => $trade_order['type'],
                'add_time' => $time
            ];
            //$r[] = db('mining_fee')->insertGetId($add2);
            $flag = db('mining_fee')->insertGetId($add2);
            if ($flag === false) throw new Exception('新增挖矿分红手续费记录失败-in line:'.__LINE__);
        }
        $time2 = microtime(true);
        $cost = $time2 - $time1;
        Log::write("币币交易订单交易定时任务,交易对:{$tradeName},交易,订单:{$order['orders_id']},num:{$num},交易订单:{$trade_order['orders_id']},trade_num:{$trade_num},cost:{$cost}");

        //$num = keepPoint($num - $trade_num, 6);
        $num = floatval(bcsub($num, $trade_num, 6));
        if ($num > 0) {
            $order = db('Orders')->where(['orders_id'=>$order['orders_id']])->find();
            //递归
            Log::write("币币交易订单交易定时任务,交易对:{$tradeName},交易-2,订单:{$order['orders_id']},num:{$num}");
            return $this->trade($order, $currencyId, $type, $num, $price,$trade_currency_id);
        }
        return true;
    }

    /**
     * 返回用户第一条未成交的挂单
     * @param int $memberId 用户id
     * @param int $currencyId 积分类型id
     * @return array 挂单记录
     */
    private function getFirstOrdersByMember($memberId,$type,$currencyId,$trade_currency_id){
        $where['member_id']=$memberId;
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order('add_time desc,orders_id desc')->find();
    }

    /**
     * 返回一条挂单记录
     * @param int $currencyId 积分类型id
     * @param float $price 交易价格
     * @return array 挂单记录
     */
    private function getOneOrders($type,$currencyId,$price,$trade_currency_id){
        switch ($type){
            case 'buy':$gl='egt';$order='price desc'; break;
            case 'sell':$gl='elt'; $order='price asc';break;
        }
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        if ($price > 0) $where['price']=array($gl,$price);
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order($order.',add_time asc')->find();
    }

    /**
     * 增加交易记录
     * @param unknown $member_id
     * @param unknown $currency_id
     * @param unknown $currency_trade_id
     * @param unknown $price
     * @param unknown $num
     * @param unknown $type
     * @return boolean
     */
    private function addTrade($member_id, $currency_id, $currency_trade_id, $price, $num, $type, $fee,$orders_id,$other_orders_id=0,$other_member_id=0)
    {
        $fee = $price * $num * $fee;
        $data = array(
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'other_orders_id' => $other_orders_id,
            'other_member_id' => $other_member_id,
            'price' => $price,
            'num' => $num,
            'fee' => $fee,
            'money' => $price * $num,
            'type' => $type,
            'orders_id' => $orders_id,
            'add_time' => time(),
            'trade_no' => 'T'.time()
        );
        if ($res = db('Trade')->insertGetId($data)) {
            return $res;
        } else {
            return false;
        }
    }

    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id, $trade_id=0)
    {
        $data = [
            'member_id' => $member_id,
            'trade_id' => $trade_id,
            'type' => $type,
            'content' => $content,
            'money_type' => $money_type,
            'money' => $money,
            'add_time' => time(),
            'currency_id' => $currency_id,
            'ip' => get_client_ip_extend(),
        ];

        $list = Db::name('finance')->insertGetId($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    /**
     *  /**
     * 添加消息库
     * @param int $member_id 用户ID -1 为群发
     * @param int $type 分类  4=系统  -1=文章表系统公告 -2 个人信息
     * @param String $title 标题
     * @param String $content 内容
     * @return bool|mixed  成功返回增加Id 否则 false
     */
    public function addMessage_all($member_id, $type, $title, $content)
    {
        $data['u_id'] = $member_id;
        $data['type'] = $type;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['add_time'] = time();
        $id = Db::name('Message_all')->insertGetId($data);
        if ($id) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 设置账户资金
     * @param int $currency_id 积分类型ID
     * @param int $num 交易数量
     * @param char $inc_dec setDec setInc 是加钱还是减去
     * @param char forzen_num num
     */
    protected function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
    {
        $inc_dec = strtolower($inc_dec);
        $field = strtolower($field);
        //允许传入的字段
        if (!in_array($field, array('num', 'forzen_num'))) {
            return false;
        }
        //如果是RMB
        if ($currency_id == 0) {
            //修正字段
            switch ($field) {
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                case 'num':
                    $field = 'rmb';
                    break;
            }
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        }
    }
}
