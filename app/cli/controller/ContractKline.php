<?php
namespace app\cli\controller;
use app\common\model\ContractTrade;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

//use think\console\Input;
//use think\console\Output;
//use think\console\Command;

/**
 *合约交易对K线图数据生成
 */
//class ContractKline extends Command
class ContractKline
{
    private $time_list = [
        '1min'=>60, //1分钟
        '5min'=>300,//5分钟
        '15min'=>900,//15分钟
        '30min'=>1800,//30分钟
        '60min'=>3600,//1小时
        '1day'=>86400,//1天
        '1week'=>604800,//1周
        '1mon'=>2592000, //1月
        //'1year'=>31536000, //1年
    ];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ContractKline';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 合约交易对K线图数据生成，每分钟执行一次
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function doRun($worker_id=0)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        Log::write("合约交易对K线图:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $where = [
                'switch'=>1,
            ];
            $tradeList = ContractTrade::where($where)->order('sort', 'desc')->select();
            if (!count($tradeList)) {
                sleep(1);
                continue;
            }

            foreach ($tradeList as $key => $value) {
                $interval_list = json_decode($value['time_interval'], true);
                foreach ($this->time_list as $key1 => $value1) {
                    $task_id = 0;
                    try {
                        Db::startTrans();

                        $time_interval = $interval_list[$key1];
                        $start_time = time();
                        $where = [
                            'trade_id'=>$value['id'],
                            'type'=>$value1,
                            'op_time'=>['elt',$start_time],
                            'status'=>['in','0,3']
                        ];
                        $task_info = Db::name('contract_kline_task')->lock(true)->where($where)->order('id asc')->find();
                        if(!$task_info) throw new Exception('暂无任务'); //暂无任务
                        $task_id = $task_info['id'];
                        $result = Db::name('contract_kline_task')->where('id', $task_id)->update(['status'=>1,'start_time'=>$start_time,'process_id'=>$worker_id]);
                        if(!$result) throw new Exception('合约交易对K线图定时任务:更新失败:'.$task_info['id']);
                        $t1 = microtime(true);
                        //throw new Exception('暂无任务1'); //暂无任务
                        $r = \app\common\model\ContractKline::create_kline($value['id'], $value['currency_id'], $value['trade_currency_id'], $key1, $value1);
                        $t2 = microtime(true);
                        $cost = round($t2-$t1,3);
                        if ($r['code'] == SUCCESS) {
                            $task_info['status'] = 2;$task_info['stop_time'] = time();$task_info['start_time'] = $start_time;
                            unset($task_info['id'],$task_info['status'],$task_info['msg']);
                            $task_info['task_id'] = $task_id;
                            $task_info['cost_time'] = $cost;
                            Db::name('contract_kline_task_his')->insertGetId($task_info);
                            Db::name('contract_kline_task')->where(['id'=>$task_id])->delete();
                            $new_task =[
                                'trade_id'=>$value['id'],
                                'type'=>$value1,
                                'add_time'=>time(),
                                'op_time'=>$task_info['op_time'] + $time_interval,
                            ];
                            Db::name('contract_kline_task')->insertGetId($new_task);
                        }
                        else {
                            throw new Exception($r['message']);
                        }

                        Db::commit();
                    }
                    catch(Exception $e) {
                        @Db::rollback();

                        $msg = $e->getMessage();
                        if($msg!='暂无任务') {
                            $result = Db::name('contract_kline_task')->where(['id'=>$task_id,'status'=>1])->update(['status'=>3,'stop_time'=>time(),'msg'=>'error:'.$msg]);
                            Log::write("合约交易对K线图:定时任务:".$msg, 'INFO');
                        }
                    }
                }
            }
            sleep(1);
        }
        Log::write("合约交易对K线图:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}