<?php


namespace app\cli\controller;

use app\admin\controller\Common;
use think\Log;
use think\Db;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\common\model\ArkConfig;
use app\common\model\ArkOrder;
use app\common\model\ArkMember;
use app\common\model\ArkGoods;
use app\common\model\ArkGoodsList;

class ArkTask extends Command
{
    protected $name = '方舟闯关定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('ArkTask')->setDescription('This is a ArkTask');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun($today = '')
    {
        if (empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name . " 开始 ");

        $this->mining_config = ArkConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }
        //用户提升等级
        $this->upgrade_level();

        //闯关结算
        $this->settlement_order();

        //结算奖励
        $this->settlement_reward();
    }

    //提升等级
    public function upgrade_level() {
        Log::write('提升等级 start');
        if ($this->mining_config['upgrade_level'] != 1) {
            Log::write($this->name . " 提升等级 关闭");
            return;
        }

        $member_info = ArkMember::order('member_id asc')->select();
        if ($member_info) {
            foreach ($member_info as $key => $value) {
                $flag = ArkMember::updateLevel($value['member_id']);
                if ($flag === false) {
                    Log::write($this->name . " 用户升级失败：" . $value['member_id']);
                }

                //合格考核
                $flag = \app\common\model\ArkDaySummary::handle_reward($value['member_id'], $this->today_config['yestday_start']);
                if ($flag === false) {
                    Log::write($this->name . " 合格考核失败：" . $value['member_id']);
                }
            }
        }

        Log::write('提升等级 end');
    }

    //闯关结算
    public function settlement_order() {
        Log::write('闯关结算 start');
        if ($this->mining_config['settlement_switch'] != 1) {
            Log::write($this->name . " 闯关结算 关闭");
            return;
        }

        $res = ArkGoodsList::where(['status' => 1, 'end_time' => ['elt', $this->today_config['today_start']], 'is_show' => 1])->order('id asc')->select();
        if (!$res){
            Log::write($this->name . " 闯关结算：没有正在运行的闯关");
            return;
        }

        foreach ($res as $key => $value) {
            $flag = ArkOrder::settlement_order($value, $this->today_config, $this->mining_config);
            if ($flag === false) {
                Log::write($this->name . "闯关结算：结算失败" . $value['id']);
            }
        }

        Log::write('闯关结算 end');
    }

    //结算奖励
    public function settlement_reward() {
        Log::write('结算奖励 start');
        if ($this->mining_config['set_reward_open'] != 1) {
            Log::write($this->name . " 结算奖励 关闭");
            return;
        }

        $res = \app\common\model\ArkSubscribeTransfer::where([
            'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
            'is_settlement' => 0
        ])->select();
        if (!$res) {
            Log::write("无预约池充值记录");
            return;
        }

        foreach ($res as $key => $value) {
            $flag = \app\common\model\ArkRewardLog::settlement_reward($value, $this->today_config);
            if ($flag === false) {
                Log::write('结算奖励失败：'.$value['id']);
            }

            $flag = \app\common\model\ArkSubscribeTransfer::where(['id' => $value['id'], 'is_settlement' => 0])->update(['is_settlement' => 1]);
            if ($flag === false) {
                Log::write('更新结算状态失败：'.$value['id']);
            }
        }

        Log::write('结算奖励 end');
    }
}