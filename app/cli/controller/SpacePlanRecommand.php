<?php
namespace app\cli\controller;
use app\common\model\SpacePlan;
use app\common\model\SpacePlanConfig;
use app\common\model\SpacePlanPay;
use app\h5\controller\Air;
use Workerman\Worker;
use think\Log;

/**
 * 太空计划推荐奖励
 */
class SpacePlanRecommand
{
    protected $name = '太空计划推荐奖励';
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'SpacePlanRecommand';
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

        Log::write($this->name." 开始:".date('Y-m-d H:i:s'), 'INFO');

        $space_plan_config = SpacePlanConfig::get_key_value();
        if(empty($space_plan_config)) {
            Log::write($this->name." 配置错误\r\n");
            return;
        }

        $currency_ratio = SpacePlan::getRadio();
        if($currency_ratio<=0) {
            sleep(5);
            return;
        }

        $runNum = 1;
        while($runNum<200) {
            $runNum++;

            $space_plan = SpacePlanPay::where(['is_award'=>0])->order('id asc')->find();
            if(empty($space_plan)) {
                sleep(1);
                continue;
            }
            $flag = \app\common\model\SpacePlanRecommand::recommand_award($space_plan,$space_plan_config,$currency_ratio);
        }
        Log::write($this->name." 结束:".date('Y-m-d H:i:s'), 'INFO');
    }
}
