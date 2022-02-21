<?php
namespace app\cli\controller;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * OTC定时取消订单及
 */
class OtcTask
{
	public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'OtcTaskWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true) {
                $this->doRun();
                sleep(2);
            }
        };
        Worker::runAll();
    }

    protected function doRun(){
        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write("OTC定时任务:".date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while($runNum<1000) {
			$runNum++;

			//$flag = $this->order_complete();
			$flag = false;
			$flag2 = $this->cancel();

			if($flag==false && $flag2==false) sleep(1);
		}
    }

    //设置广告为已完成
   	private function order_complete() {
        try{
            $ordersInfoList = Db::name('orders_otc')->field('orders_id')->where("avail_num=0 and status<2")->order('trade_time desc')->limit(50)->select();
            if(!$ordersInfoList) return false;

            $flag = false;
            foreach ($ordersInfoList as $ordersInfo) {
                //有未完成订单
                $count = Db::name('trade_otc')->where(['sell_orders'=>$ordersInfo['orders_id'],'type'=>'buy','status'=>['lt',3]])->count();
                if($count>0) continue;

                $flag = true;
                Db::name('orders_otc')->where("orders_id=".$ordersInfo['orders_id'].' and avail_num=0 and status<2')->setField('status',2);
            }
            return $flag;
        } catch(Exception $e) {
        	Log::write("OTC定时任务错误:".$e->getMessage(), 'INFO');
            return false;
        }
    }

    //超时取消订单
    private function cancel() {
    	$time = time();
        $tradeInfo = Db::name('trade_otc')->where(['type'=>'buy','status'=>0,'limit_time'=>['lt',$time]])->order('trade_id desc')->find();
        if(!$tradeInfo) return false;

        try{
        	Db::startTrans();

            $order_otc = Db::name('orders_otc')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();
            if(!$order_otc) throw new Exception('广告不存在');

            if($order_otc['type'] == 'buy') {
                $other_tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
                if(!$other_tradeInfo) throw new Exception('对应订单不存在');

                //如果是买单 要返回给卖家资产
                $all_num = keepPoint($other_tradeInfo['num'] + $other_tradeInfo['fee'],6);
                $result = model('AccountBook')->addLog([
                    'member_id' => $other_tradeInfo['member_id'],
                    'currency_id' => $other_tradeInfo['currency_id'],
                    'type'=> 9,
                    'content' => 'lan_otc_sell_to_buy_cancel',
                    'number_type' => 1,
                    'number' => $all_num,
                    'fee' => $other_tradeInfo['fee'],
                    'to_member_id' => 0,
                    'to_currency_id' => 0,
                    'third_id' => $other_tradeInfo['trade_id'],
                ]);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = Db::name('currency_user')->where(['member_id'=>$other_tradeInfo['member_id'],'currency_id'=>$other_tradeInfo['currency_id']])->update([
                    'num' => ['inc',$all_num],
                    'forzen_num'=> ['dec',$all_num],
                ]);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            //更改订单状态
            $flag = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['trade_id'],'status'=>0])->update(['status'=>4,'update_time'=>$time]);
            if(!$flag) throw new Exception('OTC取消订单失败');

            //更改卖家订单状态
            $flag = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id'],'status'=>0])->setField('status',4);
            if(!$flag) throw new Exception('OTC取消订单失败');

            //减去已交易量
            $flag = Db::name('orders_otc')->where(['orders_id'=>$tradeInfo['sell_orders']])->setInc('avail_num',$tradeInfo['num']);
            if(!$flag) throw new Exception('OTC对应广告减少已交易量失败');

            Db::commit();

            return true;
        } catch(Exception $e){
            Db::rollback();
            Log::write("OTC定时任务错误:".$e->getMessage(), 'INFO');
            return false;
        }
    }
}
