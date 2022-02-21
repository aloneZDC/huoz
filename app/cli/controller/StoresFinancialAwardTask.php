<?php
namespace app\cli\controller;
use app\common\model\StoresConfig;
use app\common\model\StoresConvertLog;
use app\common\model\StoresFinancialLog;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 划转入理财包0券奖励定时任务
 */
class StoresFinancialAwardTask
{
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'StoresFinancialAwardTask';
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

        Log::write("划转入理财包0券推荐奖:定时任务:".date('Y-m-d H:i:s'), 'INFO');

        $config = StoresConfig::get_key_value();
        if(empty($config)){
            Log::write('划转入理财包0券推荐奖: 配置错误');
            return;
        }

        $runNum = 1;
        while($runNum<200) {
            $runNum++;

            $info = Db::name('stores_financial_award_task')->where(['is_award'=>0])->order('id asc')->find();
            if(empty($info)) {
                sleep(1);
                continue;
            }

            try{
                $flag = Db::name('stores_financial_award_task')->where(['id'=>$info['id']])->setField('is_award',1);
                if(!$flag) throw new Exception("任务更新失败".$info['id']);

                StoresFinancialLog::to_financial_award($info['third_id'],$config);
            } catch(Exception $e){
                Log::write("划转入理财包0券推荐奖错误:".$e->getMessage(), 'INFO');
            }
        }
        Log::write("划转入理财包0券推荐奖:定时任务结束:".date('Y-m-d H:i:s'), 'INFO');
    }
}
