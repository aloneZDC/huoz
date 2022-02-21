<?php
namespace app\cli\controller;
use app\common\model\Currency;
use app\common\model\FlopCurrency;
use app\common\model\FlopOrders;
use app\common\model\FlopRobot;
use app\common\model\FlopTrade;
use app\common\model\FlopTradeRelease;
use app\common\model\FlopTradeReleaseConfig;
use app\common\model\HongbaoConfig;
use Workerman\Worker;
use think\Log;

/**
 * 方舟机器人定时任务
 */
class FlopRobotTask
{
    public $config=[];
    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'FlopRobotTask';
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

        Log::write("方舟机器人定时任务:".date('Y-m-d H:i:s'), 'INFO');

        $config = HongbaoConfig::get_key_value();
        $flop_robot_start_num = isset($config['flop_robot_start_num']) ? intval($config['flop_robot_start_num']) : 0;
        if($flop_robot_start_num<=0) {
            Log::write("方舟机器人定时任务: 配置为0 机器人休眠中", 'INFO');
            sleep(10);
            return;
        }
        $this->config['flop_robot_start_num'] = $flop_robot_start_num;

        $flop_robot_must_sell_num = isset($config['flop_robot_must_sell_num']) ? intval($config['flop_robot_must_sell_num']) : 0;
        $this->config['flop_robot_must_sell_num'] = $flop_robot_must_sell_num;

        $buy_currency = Currency::where(['currency_mark'=>FlopOrders::BUY_CURRENCY_MARK])->field('currency_id,currency_name,currency_mark')->find();
        if(!$buy_currency) {
            Log::write("方舟机器人定时任务: 支付币种不存在 机器人休眠中", 'INFO');
            sleep(10);
            return;
        }
        $this->config['buy_currency'] = $buy_currency;

        //获取机器人
        $flop_robots = FlopRobot::getList();
        if(empty($flop_robots)) {
            Log::write("方舟机器人定时任务: 机器人不存在", 'INFO');
            sleep(10);
            return;
        }
        $this->config['flop_robots'] = $flop_robots;


        //获取方舟币种
        $flop_currency = FlopCurrency::select();
        if(empty($flop_currency)) return;
        $this->config['flop_currency'] = $flop_currency;


        $count = 0;
        while ($count<50) {
            $this->auto_buy();
            sleep(2);
            $this->auto_sell(); //当方舟关闭时 出售会多休眠10秒
            sleep(2);
            $count++;
        }
    }

    //自动买 小于20个自动补上
    protected function auto_buy() {
        foreach ($this->config['flop_currency'] as $fcurrency) {
            $where = [
                'type' => 'buy',
                'currency_id' => $fcurrency['currency_id'],
                'status'=>0,
                'avail_num'=>['gt',0],
            ];
            $wait_flop_orders = FlopOrders::where($where)->count();
            if($wait_flop_orders>=$this->config['flop_robot_start_num']) {
                continue;
            }

            $robot = $this->randRobot();
            $flag = FlopRobot::buy($robot['member_id'],$fcurrency['currency_id'],$this->config['buy_currency']['currency_id']);
            if($flag>1) {
                sleep($flag);
            }
        }
    }

    //自动卖 大于20个自动卖一个
    protected function auto_sell(){
        foreach ($this->config['flop_currency'] as $fcurrency){
            $where = [
                'type' => 'buy',
                'currency_id' => $fcurrency['currency_id'],
                'status'=>0,
                'avail_num'=>['gt',0],
            ];
            $wait_flop_orders = FlopOrders::where($where)->count();
            if($wait_flop_orders<=$this->config['flop_robot_start_num']) {
                continue;
            }

            //如果1分钟内没有用户出售  则机器人开始出售
            $flop_last_trade = FlopTrade::order('trade_id desc')->find();
            if(($this->config['flop_robot_must_sell_num']>0 && $wait_flop_orders>=$this->config['flop_robot_must_sell_num'])  || empty($flop_last_trade) || $flop_last_trade['add_time']<(time()-20) ){
                //随机一个机器人
                $robot = $this->randRobot();
                //获取一个订单
                $where['member_id'] = ['neq',$robot['member_id']];
                $flop_order = FlopOrders::where($where)->order('orders_id asc')->find();
                if(empty($flop_order)) {
                    continue;
                }

                //机器人不卖给自己
                if(!FlopRobot::isRobot($flop_order['member_id'])) {
                    $flag = FlopRobot::sell($robot['member_id'],$flop_order['orders_id'],$fcurrency['currency_id'],$flop_order['avail_num']);
                    if($flag>1) {
                        sleep($flag);
                    }
                } else {
                    sleep(2);
                }
            }
        }
    }

    private function randRobot() {
        return $this->config['flop_robots'][array_rand($this->config['flop_robots'])];
    }
}
