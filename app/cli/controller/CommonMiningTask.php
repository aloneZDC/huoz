<?php

namespace app\cli\controller;

use app\common\model\CommonMiningMember;
use app\common\model\CommonMiningPay;
use app\common\model\InsideOrder;
use app\common\model\MtkMiningMember;
use app\common\model\MtkMiningPay;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

class CommonMiningTask extends Command
{
    protected $name = '矿机定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected function configure()
    {
        $this->setName('CommonMiningTask')->setDescription('This is a CommonMiningTask');
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

        // 增加上级业绩
        $this->common_team_num(); // 满存算力
        $this->mtk_team_num(); // MTK矿机

        // 矿机任务
        (new CommonMiningReleaseTask())->doRun($today);// 满存算力
        (new MtkMiningTask())->doRun($today);// MTK矿机

        // 撤销OTC订单
        $this->otc_revoke_order();

        // 汇总统计
//        $this->summary();
    }

    // 撤销OTC订单 (每天凌晨00:01 分平台自动撤销昨日所有订单)
    protected function otc_revoke_order()
    {
        $last_id = 0;
        while (true) {
            $inside_order = InsideOrder::where([
                'order_id' => ['gt', $last_id],
                'add_time' => ['lt', $this->today_config['today_start']],
                'status' => ['in', [0, 1]]
            ])->order(['order_id' => 'asc'])->find();
            if (empty($inside_order)) {
                Log::write($this->name . " 撤销OTC订单 已完成");
                break;
            }
            $last_id = $inside_order['order_id'];
            echo 'otc_revoke_order:' . $last_id . "\r\n";

            $flag = InsideOrder::trade_revoke($inside_order['member_id'], $inside_order['order_id']);
            if ($flag['code'] == ERROR1) {
                Log::write($this->name . " 撤销OTC订单 失败" . $inside_order['member_id']);
            }
        }
    }

    // 汇总统计
    protected function summary()
    {
        Log::write($this->name . " 汇总统计开始");
        $currency_list = [5, 93];
        foreach ($currency_list as $value) {
            $summary = Db::name('currency_user_summary')
                ->where(['currency_id' => $value, 'create_time' => $this->today_config['yestday_start']])
                ->find();
            if (!empty($summary)) continue;

            // 币种
            $result['currency_id'] = $value;
            // 充
            $result['charge'] = Db::name('accountbook')
                ->where(['type' => 5, 'currency_id' => $value, 'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]]])
                ->sum('number');
            // 提
            $result['carry'] = Db::name('accountbook')
                ->where(['type' => 6, 'currency_id' => $value, 'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]]])
                ->sum('number');
            // 统计时间
            $result['create_time'] = $this->today_config['yestday_start'];
            $flag = Db::name('currency_user_summary')->insert($result);
            if ($flag === false) {
                Log::write($this->name . " 汇总统计" . $value);
                break;
            }
        }
        Log::write($this->name . " 汇总统计结束");
    }

    // 增加上级业绩 - 满存算力
    protected function common_team_num()
    {
        $last_id = 0;
        while (true) {
            $mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_team' => 0,
            ])->order('id asc')->find();
            if (empty($mining_pay)) {
                Log::write($this->name . " 上级业绩已完成");
                break;
            }
            $last_id = $mining_pay['id'];
            echo 'common_team_num:' . $last_id . "\r\n";

            $flag = CommonMiningPay::where(['id' => $mining_pay['id'], 'is_team' => 0])->setField('is_team', 1);
            if ($flag === false) {
                Log::write($this->name . " 更新是否团队失败" . $mining_pay['id']);
                continue;
            }

            // 增加直推业绩，入金和矿机个数
            $flag = CommonMiningMember::add_one_team_num($mining_pay['member_id'], $mining_pay['pay_num']);
            if ($flag === false) {
                Log::write($this->name . " 增加直推业绩失败" . $mining_pay['id']);
            }

            // 增加团队业绩，入金
            $flag = CommonMiningMember::add_parent_team_num($mining_pay['member_id'], $mining_pay['pay_num']);
            if ($flag === false) {
                Log::write($this->name . " 增加团队业绩 入金失败" . $mining_pay['id']);
            }

            // 增加团队业绩，T数
            $flag = CommonMiningMember::add_parent_team_tnum($mining_pay['member_id'], $mining_pay['tnum']);
            if ($flag === false) {
                Log::write($this->name . " 增加团队业绩 T数失败" . $mining_pay['id']);
            }
        }
    }

    // 增加上级业绩 - MTK矿机
    protected function mtk_team_num()
    {
        $last_id = 0;
        while (true) {
            $mining_pay = MtkMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_team' => 0,
            ])->order('id asc')->find();
            if (empty($mining_pay)) {
                Log::write($this->name . " 上级业绩已完成");
                break;
            }
            $last_id = $mining_pay['id'];
            echo 'mtk_team_num:' . $last_id . "\r\n";

            $flag = MtkMiningPay::where(['id' => $mining_pay['id'], 'is_team' => 0])->setField('is_team', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否团队失败" . $mining_pay['id']);
                continue;
            }

            // 增加直推业绩，入金和矿机个数
            $flag = MtkMiningMember::add_one_team_num($mining_pay['member_id'], $mining_pay['pay_num']);
            if (!$flag) {
                Log::write($this->name . " 增加直推业绩失败" . $mining_pay['id']);
            }

            // 增加团队业绩，入金
            $flag = MtkMiningMember::add_parent_team_num($mining_pay['member_id'], $mining_pay['pay_num']);
            if (!$flag) {
                Log::write($this->name . " 增加上级业绩失败" . $mining_pay['id']);
            }
        }
    }

}