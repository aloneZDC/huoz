<?php
//全球一条线 推荐奖任务
namespace app\cli\controller;

use app\common\model\Config;
use app\common\model\TransferToBalance;
use think\Db;
use think\Exception;
use think\Log;
use Workerman\Worker;

class Transferto
{
    protected $worker;

    /**
     * @var TransferToBalance
     */
    protected $transferToBalance;

    public function index()
    {
        $this->transferToBalance = new TransferToBalance();
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'Transferto';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 每分钟执行一次
     * @throws Exception
     */
    protected function doRun()
    {
        set_time_limit(0);
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $transfer_receive_url = Config::get_value('transfer_receive_url','');
        if(empty($transfer_receive_url)) {
            Log::write("交易所推送地址设置错误");
            sleep(1);
            return;
        }

        $count = 0;
        while ($count<100) {
            $count++;

            $transfer_to_balance = $this->transferToBalance->where(['ucb_type'=>'out','ucb_status'=>2])->order('ucb_last_time asc')->find();
            if(empty($transfer_to_balance)) {
                echo "empty\r\n";
                sleep(1);
                continue;
            } else {
                echo $transfer_to_balance['ucb_id']."\r\n";
            }

            //修改最新更新时间
            $flag = $this->transferToBalance->where(['ucb_id'=>$transfer_to_balance['ucb_id'],'ucb_last_time'=>$transfer_to_balance['ucb_last_time']])->update([
                'ucb_count' => ['inc',1],
                'ucb_last_time' => time(),
            ]);
            if(!$flag) {
                sleep(1);
                continue;
            }

            $result = $this->transferToBalance->post_to($transfer_receive_url,['currency_mark'=>$transfer_to_balance['ucb_currency_mark'],'to_address'=>$transfer_to_balance['ucb_to_address'],'to_num'=>$transfer_to_balance['ucb_actual'],'third_id'=>$transfer_to_balance['ucb_id']]);
            if($result['code']==SUCCESS) {
                $this->transferToBalance->where(['ucb_id'=>$transfer_to_balance['ucb_id']])->setField('ucb_status',1);
            }
            sleep(1);
        }
    }
}