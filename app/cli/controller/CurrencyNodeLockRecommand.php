<?php
namespace app\cli\controller;
use app\common\model\Config;
use app\common\model\CurrencyLockBook;
use app\common\model\CurrencyNodeLock;
use app\common\model\CurrencyUser;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 节点兑换推荐奖励
 */
class CurrencyNodeLockRecommand extends Command
{
    protected $name = '节点兑换推荐奖励';
    public $config=[];


    protected function configure()
    {
        $this->setName('CurrencyNodeLockRecommand')->setDescription('This is a CurrencyNodeLockRecommand');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $today_start = strtotime(date('Y-m-d'));
//        $today_start = strtotime('2021-06-27');
        $this->doRun($today_start);
        $this->release($today_start);
    }

    protected function doRun($today_start){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        Log::write($this->name." 开始:".date('Y-m-d H:i:s').$today_start, 'INFO');

        $awad_config = [
            'node_lock_percent1' => Config::get_value('node_lock_percent1',0),
            'node_lock_percent2' => Config::get_value('node_lock_percent2',0),
            'node_lock_recommand_num' => Config::get_value('node_lock_recommand_num',0),
        ];
//        $yestoday_start = $today_start - 86400;
//        $yestoday_stop = $today_start - 1;
        while(true) {
            //'create_time'=>['between',[$yestoday_start,$yestoday_stop] ] ,
            $node_lock = CurrencyNodeLock::where(['is_deal'=>0])->order('id asc')->find();
            if(empty($node_lock)) {
                Log::write($this->name." 结束:".date('Y-m-d H:i:s').$today_start, 'INFO');
                break;
            }

            $flag = CurrencyNodeLock::award_task($node_lock,$awad_config);
        }
    }

    protected function release($today_start) {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        Log::write($this->name." 释放开始:".date('Y-m-d H:i:s').$today_start, 'INFO');
        $awad_config = [
            'node_lock_stop_time' => Config::get_value('node_lock_stop_time',1598889600),
            'node_lock_total_month' => Config::get_value('node_lock_total_month',10),
        ];
        if($awad_config['node_lock_stop_time']>$today_start) {
            Log::write($this->name." 释放尚未开始:".date('Y-m-d H:i:s').$today_start, 'INFO');
            return;
        }

        //总天数
        $total_turn = intval(($today_start - $awad_config['node_lock_stop_time'])/86400);
        //30天释放一次
        $is_release = $total_turn%30;
        if($is_release!=0){
            Log::write($this->name." 今日不释放:".date('Y-m-d',$today_start), 'INFO');
            return;
        }

        $percent = $awad_config['node_lock_total_month'];
        $is_all = false;

        $total_month = intval($total_turn/30) +1;
        if($total_month==$awad_config['node_lock_total_month']) {
            $is_all = true;
            Log::write($this->name." 剩余全部释放");
        }

        if($total_month>$awad_config['node_lock_total_month']) {
            Log::write($this->name." 释放任务已完成，不再释放:".date('Y-m-d',$today_start), 'INFO');
            return;
        }


        $currency_id = CurrencyLockBook::FIELD_CURRENCY_ID[CurrencyNodeLock::LOCK_FIELD];

        $last_id = CurrencyLockBook::where(['field'=>CurrencyNodeLock::LOCK_FIELD,'type'=>'release','create_time'=>['egt',$today_start]])->max('third_id');
        while (true){
            $currency_user = CurrencyUser::where(['cu_id'=>['gt',$last_id],'currency_id'=>$currency_id,CurrencyNodeLock::LOCK_FIELD=>['gt',0]])->order('cu_id asc')->find();
            if(empty($currency_user)) {
                break;
            }

            $last_id = $currency_user['cu_id'];
            CurrencyNodeLock::release($currency_user,$percent,$is_all);
        }

        Log::write($this->name." 今日释放结束:".date('Y-m-d',$today_start), 'INFO');
    }
}
