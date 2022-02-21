<?php

namespace app\cli\controller;

use app\common\model\ChiaMiningMember;
use app\common\model\ChiaMiningIncome;
use app\common\model\ChiaMiningPay;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use Workerman\Worker;

/**
 * 奇亚矿机 - 定时任务
 * Class ChiaLevelTask
 * @package app\cli\controller
 */
class ChiaLevelTask
{
    public $name = '奇亚矿机矿工身份定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ChiaLevelTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
                sleep(1800);
            }
        };
        Worker::runAll();
    }

    public function doRun()
    {
        Log::write($this->name . ' start');

        $ChiaMiningMember = new ChiaMiningMember();
        $res = $ChiaMiningMember->order('add_time DESC')->column('member_id');
        if (empty($res)) return;
        foreach ($res as $key => $value) {
            try {
                Db::startTrans();
                $result = $ChiaMiningMember->updateLevel($value);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                Log::write($e->getMessage());
            }
        }
        Log::write('task stop');
        echo 'task stop';
    }
}