<?php


namespace app\cli\controller;

use think\Log;
use think\Db;
use Workerman\Worker;
use app\common\model\ArkConfig;
use app\common\model\ArkMember;
use app\common\model\ArkGoodsList;
use app\common\model\ArkBuyList;
use app\common\model\ArkOrder;

class ArkFire
{
    protected $name = '自动点火定时任务';
    protected $mining_config = [];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ArkFire';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    /**
     * 每分钟执行一次
     */
    protected function doRun($worker_id=0){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->mining_config = ArkConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }
        //复利
        $this->automatic_investment();
        //预约排队
        $this->reservation_queue();
    }

    //预约排队
    public function reservation_queue() {
        $subscribe_transfer_time = ArkConfig::getValue('queue_start_time', 10);
        $start_time = strtotime(date('Y-m-d', time()));
        $end_time = $start_time + 86399;
        //判断是否可充值预约池
        $res = ArkGoodsList::where(['status' => 1, 'start_time' => ['BETWEEN', [$start_time, $end_time]], 'is_show' => 1])->select();
        if (!$res) return;

        foreach ($res as $key => $value) {
            $check_start_time = keepPoint($value['start_time'] - ($subscribe_transfer_time * 60), 0);
            $check_end_time = $value['start_time'];
            if (time() >= $check_start_time && time() < $check_end_time) {
                $flag = \app\common\model\ArkSubscribeTransfer::reservation_queue($value);
                if ($flag === false) {
                    Log::write('预约排队失败'. $value['id']);
                }
            }
        }
    }

    //复利
    public function automatic_investment() {
        $res = ArkOrder::where(['status' => 1, 'is_auto' => 1])->order('goods_list_id asc')->select();
        if (!$res) {
            return;
        }

        $start_time = strtotime(date('Y-m-d', time()));
        $end_time = $start_time + 86399;
        foreach ($res as $key => $value) {
            $level = ArkOrder::getSettlementLevel($value['level_num']);
            $next_level = keepPoint($level + $value['level_num'], 0);
            $goods_id = ArkGoodsList::where(['id' => $value['goods_list_id']])->value('goods_id');
            $goods_list = ArkGoodsList::where(['goods_id' => $goods_id, 'level' => $next_level, 'status' => 1, 'start_time' => ['BETWEEN', [$start_time, $end_time]], 'is_show' => 1])->find();
            if (!$goods_list) {
                continue;//不存在复利闯关关数
            }

            $num = ArkOrder::where(['member_id' => $value['member_id'], 'goods_list_id' => $value['goods_list_id'], 'status' => 1,'is_auto' => 1])->sum('money');
            if ($num > 0) {
                $flag = ArkBuyList::add_list($value['member_id'], $goods_list['id'], $num, 0, 3);
                if ($flag['code'] != SUCCESS) {
                    Log::write('添加复利排队失败'. $value['member_id']);
                }
            }

            $flag = ArkOrder::where(['member_id' => $value['member_id'], 'goods_list_id' => $value['goods_list_id'], 'status' => 1,'is_auto' => 1])->update(['is_auto' => 2]);
            if ($flag === false) {
                Log::write('更新复利状态失败'. $value['member_id']);
            }
        }
    }
}