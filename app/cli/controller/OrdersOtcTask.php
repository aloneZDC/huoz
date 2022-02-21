<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *OTC取消小于1元的广告
 */
class OrdersOtcTask extends Command
{
    protected function configure()
    {
        $this->setName('OrdersOtcTask')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    protected function doRun() {
        $last_id = 0;
        while (true) {
            $ordersInfo = Db::name('orders_otc')->where(['orders_id'=>['gt',$last_id],'status'=>['lt',2]])->order('orders_id asc')->find();
            if(!$ordersInfo){
                Log::write('OTC取消小于1元的广告已完成');
                break;
            }
            $last_id = $ordersInfo['orders_id'];

            //有未完成订单
            $count = Db::name('trade_otc')->where(['sell_orders'=>$ordersInfo['orders_id'],'type'=>'buy','status'=>['lt',3]])->count();
            if($count>0) continue;

            //获取广告剩余金额
            $avail_money = keepPoint($ordersInfo['avail_num'] * $ordersInfo['price'],2);
            if($avail_money > 100) continue;

            if ($ordersInfo['type'] != 'sell') continue;

            try{
                Db::startTrans();

                $avail = $ordersInfo['avail_num'];
                //返还手续费
                $fee_back = keepPoint($avail * $ordersInfo['fee'], 6);
                //返还未成交数量
                $avail = keepPoint($avail + $fee_back, 6);

                if($avail>0) {
                    //添加账本
                    $result = model('AccountBook')->addLog([
                        'member_id' => $ordersInfo['member_id'],
                        'currency_id' => $ordersInfo['currency_id'],
                        'type'=> 10,
                        'content' => 'lan_otc_cancel',
                        'number_type' => 1,
                        'number' => $avail,
                        'fee' => $fee_back,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $ordersInfo['orders_id'],
                    ]);
                    if(!$result) throw new Exception('OTC取消小于1元的广告:添加账本失败');

                    $flag = Db::name('currency_user')->where(['member_id'=>$ordersInfo['member_id'],'currency_id'=>$ordersInfo['currency_id']])->update([
                        'num' => ['inc',$avail],
                        'forzen_num'=> ['dec',$avail],   
                    ]);                
                    if(!$flag) throw new Exception('OTC取消小于1元的广告:增加资产失败');
                }

                $flag = Db::name('orders_otc')->where(['orders_id'=>$ordersInfo['orders_id']])->setField('status',3);
                if(!$flag) throw new Exception('OTC取消小于1元的广告:撤销失败');
                
                Db::commit();
            } catch(Exception $e){
                Db::rollback();
                Log::write('OTC取消小于1元的广告:'.$e->getMessage());
            }
        }
    }
}
