<?php
namespace app\cli\controller;
use app\common\model\Config;
use app\common\model\DcLockLog;
use app\common\model\Trade;
use think\Log;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 币币交易 根据购买量释放 DNC
 */
class TradeRelease extends Command
{
    const CURRENCY_ID = 40; //币币交易币种ID
    const RELEASE_CURRENCY_ID = 38; //释放币种ID

    public $name = '币币交易释放';
    protected $today_config = [];
    protected $config = [];

    protected function configure()
    {
        $this->setName('TradeRelease')->setDescription('This is a TradeRelease');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun() {
        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name." 开始 ");
        $this->config['trade_release_max_num'] = Config::get_value('trade_release_max_num',0);
        $this->config['trade_release_min_percent'] = Config::get_value('trade_release_min_percent',0);
        $this->config['trade_release_max_percent'] = Config::get_value('trade_release_max_percent',0);
        $this->config['trade_release_limit_min_percent'] = Config::get_value('trade_release_limit_min_percent',0);
        $this->config['trade_release_limit_max_percent'] = Config::get_value('trade_release_limit_max_percent',0);
        if($this->config['trade_release_max_percent']<=0) {
            Log::write($this->name."最大比例设置为0，自动结束");
            return;
        }

        $this->release();
    }

    protected function release() {
        $last_id = DcLockLog::where(['type'=>DcLockLog::TRADE_RELEASE,'create_time'=>['gt',$this->today_config['today_start']] ])->max('user_id');
        while (true) {
            echo "release:".$last_id."\r\n";
            $info = Trade::where([
                'member_id'=>['gt',$last_id],
                'currency_id'=>self::CURRENCY_ID,
                'type'=>'buy',
                'add_time' => ['between',[ $this->today_config['yestday_start'],$this->today_config['yestday_stop'] ] ],
            ])->order('member_id asc')->find();
            if(empty($info)) {
                Log::write($this->name." 结束");
                break;
            }

            $last_id = $info['member_id'];

            //该用户昨天币币交易总量
            $member_sum = Trade::where([
                'member_id'=>$info['member_id'],
                'currency_id'=>self::CURRENCY_ID,
                'type'=>'buy',
                'add_time' => ['between',[ $this->today_config['yestday_start'],$this->today_config['yestday_stop'] ] ],
            ])->sum('num');
            if($member_sum) {
                Trade::trade_release($info['member_id'],$member_sum,$this->config);
            }
        }
    }
}
