<?php

namespace app\cli\controller;

use app\common\model\CommonMiningConfig;
use app\common\model\CommonMiningIncome;
use app\common\model\CommonMiningLevelConfig;
use app\common\model\CommonMiningLevelIncomeDetail;
use app\common\model\CommonMiningMember;
use app\common\model\CommonMiningPay;
use app\common\model\CommonMiningRelease;
use app\common\model\CurrencyUser;
use think\Log;
use think\Db;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class CommonMiningReleaseTask extends Command
{
    protected $name = '满存算力 - 定时任务';
    protected $today_config = [];
    protected $common_mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('CommonMiningReleaseTask')->setDescription('This is a CommonMiningReleaseTask');
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

        $this->common_mining_config = CommonMiningConfig::get_key_value();
        if (empty($this->common_mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }

//        $this->all_levels = CommonMiningLevelConfig::getAllLevel();
//        if (empty($this->all_levels)) {
//            Log::write($this->name . " 等级为空");
//            return;
//        }
//        $this->all_levels = array_column($this->all_levels, null, 'level_id');

        // 锁仓释放（暂时不用）
//        $this->lock_release_num();

        // 全球加权锁仓75%释放
//        $this->global_lock_release();

        // 增加上级业绩
//        $this->team_num();
        // 推荐奖励1代2代发放
//        $this->recommand_num();

        // 产出锁仓75%释放
        $this->release_lock_release_num();
        // 每天矿机产出，25%到可用，75%到锁仓
        $this->release_num();

        // 产出奖励锁仓75%释放
        $this->service_lock_reward();
        // 产出奖励25%
        $this->service_reward();

        // 等级奖励详情 （暂时不用）
//        $this->level_income_detail();
        // 等级奖励 （暂时不用）
//        $this->level_income();

        // 升级 （暂时不用）
//        $this->levelUpdate();

        // 全球加权 - 入金 （暂时不用）
//        $this->global_income();
        // 全球加权 - T数
//        $this->global_reward();
        // 合伙股东奖励
//        $this->partner_reward();
        // 合伙股东 - 技术服务费
//        $this->partner_reward_service();

        //汇总
//        $this->summary();
    }

    // 服务收益
    protected function service_reward()
    {
        Log::write($this->name . " 产币推荐奖励开始");
        if ($this->common_mining_config['accel_open'] != 1) {
            Log::write($this->name . " 产币推荐奖励关闭");
            return;
        }
        $last_id = 0;
        while (true) {
            $common_mining = CommonMiningRelease::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_end']]],
                'is_recommand' => 0,
            ])->order('id asc')->find();
            if (empty($common_mining)) {
                Log::write($this->name . " 产币推荐奖励已完成");
                break;
            }
            $last_id = $common_mining['id'];
            echo 'service_reward:' . $last_id . "\r\n";

            $flag = CommonMiningRelease::where(['id' => $common_mining['id'], 'is_recommand' => 0])
                ->setField('is_recommand', 1);
            if ($flag === false) {
                Log::write($this->name . " 更新是否产币推荐奖励失败" . $common_mining['id']);
                break;
            }

            // 添加奖励详情
            $flag = CommonMiningRelease::service_reward($common_mining, $this->common_mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 奖励发放失败" . $common_mining['id']);
                break;
            }
        }
    }

    // 产出奖励锁仓75%释放
    protected function service_lock_reward()
    {
        Log::write($this->name . " 产出奖励锁仓释放开始");
        if ($this->common_mining_config['accel_lock_open'] != 1) {
            Log::write($this->name . " 产出奖励锁仓释放关闭");
            return;
        }

        $last_id = CommonMiningIncome::where([
            'currency_id' => $this->common_mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 7,
        ])->max('third_id');
        while (true) {
            $mining_member = CommonMiningMember::where([
                'id' => ['gt', $last_id],
                'total_child6' => ['gt', 0],
            ])->order('id asc')->find();
            if (empty($mining_member)) {
                Log::write($this->name . " 产出奖励锁仓释放已完成");
                break;
            }

            $last_id = $mining_member['id'];
            echo "service_lock_reward {$last_id}\r\n";

            $flag = CommonMiningRelease::service_lock_reward($mining_member, $this->common_mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 奖励发放失败" . $mining_member['id']);
                break;
            }
        }
    }

    // 增加上级业绩 - 满存算力
    protected function team_num()
    {
        $last_id = 0;
        while (true) {
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_team' => 0,
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 上级业绩已完成");
                break;
            }
            $last_id = $common_mining_pay['id'];
            echo 'team_num:' . $last_id . "\r\n";

            $flag = CommonMiningPay::where(['id' => $common_mining_pay['id'], 'is_team' => 0])->setField('is_team', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否团队失败" . $common_mining_pay['id']);
                continue;
            }

            // 增加直推业绩，入金和矿机个数
            $flag = CommonMiningMember::addOneTeamNum($common_mining_pay['id'], $common_mining_pay['member_id'], $common_mining_pay['tnum'], $common_mining_pay['pay_num']);
            if (!$flag) {
                Log::write($this->name . " 增加直推业绩失败" . $common_mining_pay['id']);
            }

            // 增加团队业绩，入金
            $flag = CommonMiningMember::addParentTeamNum($common_mining_pay['member_id'], $common_mining_pay['pay_num']);
            if (!$flag) {
                Log::write($this->name . " 增加上级业绩失败" . $common_mining_pay['id']);
            }

            // 增加团队业绩，T数
            $flag = CommonMiningMember::addParentTeamTnum($common_mining_pay['member_id'], $common_mining_pay['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加上级业绩失败" . $common_mining_pay['id']);
            }
        }
    }

    // 增加上级业绩 - 独享矿机
    protected function alone_team_num()
    {
        $last_id = 0;
        while (true) {
            $mining_archive = \app\common\model\AloneMiningArchive::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_team' => 0,
            ])->order('id asc')->find();
            if (empty($mining_archive)) {
                Log::write($this->name . " 上级业绩已完成");
                break;
            }
            $last_id = $mining_archive['id'];
            echo 'team_num:' . $last_id . "\r\n";

            $flag = \app\common\model\AloneMiningArchive::where(['id' => $mining_archive['id'], 'is_team' => 0])->setField('is_team', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否团队失败" . $mining_archive['id']);
                continue;
            }

            // 增加直推业绩，入金和矿机个数
            $flag = CommonMiningMember::addOneTeamNum($mining_archive['id'], $mining_archive['member_id'], $mining_archive['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加直推业绩失败" . $mining_archive['id']);
            }

            // 增加团队业绩，T数
            $flag = CommonMiningMember::addParentTeamTnum($mining_archive['member_id'], $mining_archive['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加上级业绩失败" . $mining_archive['id']);
            }
        }
    }

    // 推荐奖励发放
    protected function recommand_num()
    {
        Log::write($this->name . " 推荐奖励开始");
        if ($this->common_mining_config['recommand_income_open'] != 1) {
            Log::write($this->name . " 推荐奖励关闭");
            return;
        }

        $last_id = CommonMiningIncome::where(['type' => 1, 'award_time' => $this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
                'is_recommand' => 0,
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 推荐奖励已完成");
                break;
            }
            $last_id = $common_mining_pay['id'];
            echo 'recommand_num:' . $last_id . "\r\n";

            $flag = CommonMiningPay::where(['id' => $common_mining_pay['id'], 'is_recommand' => 0])->setField('is_recommand', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否推荐奖励失败" . $common_mining_pay['id']);
                continue;
            }

            // 添加奖励详情
            $flag = CommonMiningPay::recommand_award($common_mining_pay, $this->common_mining_config, $this->today_config['today_start']);
        }
    }

    //释放昨天的矿机产出
    protected function release_num()
    {
        if ($this->common_mining_config['release_open'] != 1) {
            Log::write($this->name . " 释放奖励关闭");
            return;
        }

        $fil_block_info = cache('fil_block_info');
        if (empty($fil_block_info) || !isset($fil_block_info['fields']) || !isset($fil_block_info['fields']['hour24_avg'])) {
            Log::write($this->name . " 获取平均算力失败");
            return;
        }

        $per_tnum_price = $fil_block_info['fields']['hour24_avg']['number'];
        if (!is_numeric($per_tnum_price) || $per_tnum_price <= 0 || $per_tnum_price > 0.5) {
            Log::write($this->name . " 获取平均算力失败2");
            return;
        }

        $last_id = CommonMiningRelease::where(['release_time' => $this->today_config['today_start']])->max('third_id');
        while (true) {
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'start_day' => ['elt', $this->today_config['today_start']],
                'treaty_day' => ['gt', $this->today_config['today_start']],
                'contract_status' => 1,
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 释放奖励已完成");
                break;
            }

            $last_id = $common_mining_pay['id'];
            echo "release_num {$last_id}\r\n";

            $flag = CommonMiningRelease::release($common_mining_pay, $this->common_mining_config, $per_tnum_price, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 释放奖励失败");
                break;
            }
        }
    }

    // 更新用户等级
    protected function levelUpdate()
    {
        foreach ($this->all_levels as $cur_level) {
            $last_id = 0;
            while (true) {
                $common_mining_member = CommonMiningMember::where([
                    'id' => ['gt', $last_id],
                    'level' => $cur_level['level_id'] - 1,
                ])->order('id asc')->find();
                if (empty($common_mining_member)) {
                    Log::write($this->name . " 升级已完成" . $cur_level['level_id']);
                    break;
                }

                $last_id = $common_mining_member['id'];
                echo $cur_level['level_id'] . "levelUpdate" . $last_id . " \r\n";

                // 自身入金考核
                if ($cur_level['level_self_num'] > 0) {
                    if ($common_mining_member['pay_num'] < $cur_level['level_self_num']) {
                        continue;
                    }
                }

                // 直推入金考核
                if ($cur_level['level_child_recommand'] > 0) {
                    $recommand_count = CommonMiningMember::getTeamRecommandMember($common_mining_member['member_id']);
                    if ($recommand_count < $cur_level['level_child_recommand']) {
                        continue;
                    }
                }

                // 部门等级考核
                if ($cur_level['level_child_level_count'] > 0 && $cur_level['level_child_level'] > 0) {
                    $level_child_level_count = CommonMiningMember::getTeamLevelCount($common_mining_member['member_id'], $cur_level['level_child_level']);
                    if ($level_child_level_count < $cur_level['level_child_level_count']) {
                        continue;
                    }
                }

                Log::write($common_mining_member['id'] . " 升级成功" . $cur_level['level_id']);

                // 更新等级
                CommonMiningMember::where(['id' => $common_mining_member['id']])->setField('level', $cur_level['level_id']);
                // 更新团队最高等级
                CommonMiningMember::updateTeamMaxLevel($common_mining_member['member_id'], $cur_level['level_id']);

                // 增加升级记录
                Db::name('common_mining_level_log')->insertGetId([
                    'third_id' => $common_mining_member['id'],
                    'level' => $cur_level['level_id'],
                    'add_time' => $this->today_config['today_start'],
                ]);
            }
        }
    }

    // 等级级差奖励详情
    protected function level_income_detail()
    {
        Log::write($this->name . " 级差奖励详情开始");
        if ($this->common_mining_config['level_income_open'] != 1) {
            Log::write($this->name . " 级差奖励详情关闭");
            return;
        }

        $last_id = CommonMiningLevelIncomeDetail::where(['award_time' => $this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $pay_start_time = $this->today_config['today_start'] - 86400;
            $pay_stop_time = $this->today_config['yestday_stop'];
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'add_time' => ['between', [$pay_start_time, $pay_stop_time]],
                'is_award' => 0,
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 级差奖励详情已完成");
                break;
            }
            $last_id = $common_mining_pay['id'];
            echo 'level_income_detail:' . $last_id . "\r\n";

            $flag = CommonMiningPay::where(['id' => $common_mining_pay['id'], 'is_award' => 0])->setField('is_award', 1);
            if (!$flag) {
                Log::write($this->name . " 更新是否级差失败" . $common_mining_pay['id']);
                continue;
            }

            // 添加奖励详情
            $flag = CommonMiningLevelIncomeDetail::award_detail($common_mining_pay, $this->common_mining_config, $this->all_levels, $this->today_config['today_start']);
        }
    }

    //等级级差奖励发放
    protected function level_income()
    {
        Log::write($this->name . " 级差奖开始");
        if ($this->common_mining_config['level_income_open'] != 1) {
            Log::write($this->name . " 等级级差奖励关闭");
            return;
        }

        $last_id = CommonMiningIncome::where([
            'type' => 4,
            'award_time' => $this->today_config['today_start'],
        ])->max('member_id');
        $last_id = intval($last_id);
        while (true) {
            $income_detail = CommonMiningLevelIncomeDetail::where([
                'currency_id' => $this->common_mining_config['default_lock_currency_id'],
                'award_time' => $this->today_config['today_start'],
                'member_id' => ['gt', $last_id],
            ])->order('member_id asc')->find();
            if (empty($income_detail)) {
                Log::write($this->name . " 级差奖已完成");
                break;
            }

            $last_id = $income_detail['member_id'];
            echo "level_income {$last_id}\r\n";

            $income_sum = CommonMiningLevelIncomeDetail::where([
                'currency_id' => $income_detail['currency_id'],
                'award_time' => $this->today_config['today_start'],
                'member_id' => $income_detail['member_id'],
            ])->sum('num');

            if ($income_sum > 0) {
                CommonMiningIncome::award($income_detail['member_id'], $income_detail['currency_id'], 4, $income_sum, 0, 0, 0, 0, $this->today_config['today_start']);
            }
        }
    }

    // 锁仓释放及清除
    protected function lock_release_num()
    {
        $last_id = CommonMiningIncome::where([
            'currency_id' => $this->common_mining_config['default_lock_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 5,
        ])->max('member_id');

        while (true) {
            $currency_user = CurrencyUser::where([
                'currency_id' => $this->common_mining_config['default_lock_currency_id'],
                'common_lock_num' => ['gt', 0],
                'member_id' => ['gt', $last_id],
            ])->order('member_id asc')->find();
            if (empty($currency_user)) {
                Log::write($this->name . " 锁仓释放已完成");
                break;
            }

            $last_id = $currency_user['member_id'];
            echo "lock_release_num {$last_id}\r\n";

            CommonMiningPay::lock_release($currency_user, $this->common_mining_config, $this->today_config['today_start']);
        }
    }

    // 产出锁仓释放
    protected function release_lock_release_num()
    {
        if ($this->common_mining_config['lock_release_open'] != 1) {
            Log::write($this->name . " 产出锁仓释放关闭");
            return;
        }

        $last_id = CommonMiningIncome::where([
            'currency_id' => $this->common_mining_config['release_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 5,
        ])->max('third_id');

        while (true) {
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
                'total_lock_yu' => ['gt', 0],
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 产出锁仓释放已完成");
                break;
            }

            $last_id = $common_mining_pay['id'];
            echo "release_lock_release_num {$last_id}\r\n";

            $flag = CommonMiningRelease::release_lock_release($common_mining_pay, $this->common_mining_config, $this->today_config);
            if ($flag === false) {
                Log::write($this->name . " 产出锁仓释放失败");
                break;
            }

        }
    }

    // 全球加权分红
    protected function global_income()
    {
        Log::write($this->name . " 全球加权分红-开始");

        //新增业绩总量
        $total_pay_num_all = CommonMiningPay::where([
            'pay_currency_id' => $this->common_mining_config['pay_currency_id'],
            'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
        ])->sum('pay_num');
        if (empty($total_pay_num_all)) {
            Log::write($this->name . " 全球加权分红-无业绩");
            return;
        }
        if ($this->common_mining_config['income_type'] == 'fixed') {
            // 固定奖励 /T
            $total_tnum_all = CommonMiningPay::where([
                'pay_currency_id' => $this->common_mining_config['pay_currency_id'],
                'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
            ])->sum('tnum');

            $fixed_num = $total_tnum_all * $this->common_mining_config['fixed_global'];
            $total_pay_num = min($total_pay_num_all, $fixed_num);
        } else {
            // 新增业绩10%
            $total_pay_num = $total_pay_num_all * $this->common_mining_config['gloabl_percent'] / 100;
        }

        //总业绩 没有入金的没有该奖励
        $total_one_team_count = CommonMiningMember::where(['one_team_count' => ['egt', $this->common_mining_config['one_team_count']], 'pay_num' => ['gt', 0]])->sum('team_total');
        if (empty($total_one_team_count)) {
            Log::write($this->name . " 全球加权分红-总业绩为0");
            return;
        }

        $last_id = CommonMiningIncome::where([
            'currency_id' => $this->common_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 7,
        ])->max('third_id');
        while (true) {
            $common_mining_member = CommonMiningMember::where([
                'member_id' => ['gt', $last_id],
                'one_team_count' => ['egt', $this->common_mining_config['one_team_count']],
                'pay_num' => ['gt', 0]
            ])->order('member_id asc')->find();
            if (empty($common_mining_member)) {
                Log::write($this->name . " 全球加权分红已完成");
                break;
            }

            $last_id = $common_mining_member['member_id'];
            echo "global_income {$last_id}\r\n";

            $income = keepPoint($total_pay_num * $common_mining_member['team_total'] / $total_one_team_count, 6);
            //20210225 改为  25%立即到账  75%锁仓线性释放
            $num_num = keepPoint($income * $this->common_mining_config['gloabl_num_percent'] / 100, 6); // 25%
            $lock_percent = 100 - $this->common_mining_config['gloabl_num_percent'];
            $lock_num = keepPoint($income - $num_num, 6); // 75%
            if ($income > 0.000001) {
                CommonMiningIncome::award($common_mining_member['member_id'], $this->common_mining_config['pay_currency_id'], 7, $num_num, 0, $total_pay_num_all, 0, $this->common_mining_config['gloabl_percent'], $this->today_config['today_start'], 0, 0, $lock_percent, $lock_num);
            }
        }
    }

    // 锁仓释放及清除
    protected function global_lock_release()
    {
        $last_id = CommonMiningIncome::where([
            'currency_id' => $this->common_mining_config['default_lock_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 8,
        ])->max('member_id');

        while (true) {
            $currency_user = CurrencyUser::where([
                'currency_id' => $this->common_mining_config['default_lock_currency_id'],
                'global_lock' => ['gt', 0],
                'member_id' => ['gt', $last_id],
            ])->order('member_id asc')->find();
            if (empty($currency_user)) {
                Log::write($this->name . " 锁仓释放已完成");
                break;
            }

            $last_id = $currency_user['member_id'];
            echo "global_lock_release {$last_id}\r\n";

            CommonMiningRelease::global_lock_release($currency_user, $this->common_mining_config, $this->today_config['today_start']);
        }
    }

    // 全球加权 - T数
    protected function global_reward()
    {
        $total_common_tnum = CommonMiningPay::where(['tnum' => ['egt', 1]])->sum('tnum'); // 总业绩 - 满存算力
        $total_alone_tnum = \app\common\model\AloneMiningArchive::where(['tnum' => ['egt', 1]])->sum('tnum'); // 总业绩 - 独享矿机
        $total_tnum = bcadd($total_common_tnum, $total_alone_tnum, 6);
        if ($total_tnum < 0.0001) {
            Log::write($this->name . " 全球加权分红-无业绩");
            return;
        }

        // 今日新增业绩
        $total_today_tnum = CommonMiningPay::today_tnum($this->today_config);
        if ($total_today_tnum < 0.0001) {
            Log::write($this->name . " 全球加权分红-无新增业绩");
            return;
        }

        // 计算奖励
        $reward = bcmul($total_today_tnum, $this->common_mining_config['fixed_global'], 6);

        $last_id = CommonMiningIncome::where(['type' => 7, 'award_time' => $this->today_config['today_start']])->max('member_id');
        while (true) {
            $mining_member = CommonMiningMember::where([
                'member_id' => ['gt', $last_id],
                'one_team_count' => ['egt', $this->common_mining_config['one_team_count']],
                'pay_tnum' => ['gt', 0],
                'team_tnum' => ['gt', 0]
            ])->order('member_id asc')->find();
            if (empty($mining_member)) {
                Log::write($this->name . " 全球加权分红已完成");
                break;
            }

            $last_id = $mining_member['member_id'];
            echo "global_income {$last_id}\r\n";

            $proportion = bcdiv($mining_member['team_tnum'], $total_tnum, 6); // 比例
            $award_num = bcmul($reward, $proportion, 6);
            if ($award_num > 0.0001) {
                CommonMiningIncome::award($mining_member['member_id'], $this->common_mining_config['pay_currency_id'], 7, $award_num, 0, $total_today_tnum, 0, $this->common_mining_config['gloabl_percent'], $this->today_config['today_start']);
            }
        }
    }

    // 合伙股东奖励
    protected function partner_reward()
    {
        Log::write($this->name . " 合伙股东奖励开始");

        // 满足条件的用户
        $mining_member = CommonMiningMember::partner_member($this->common_mining_config);
        if (empty($mining_member)) {
            Log::write($this->name . " 合伙股东奖励-无满足条件的用户");
            return;
        }

        // 今日新增业绩
        $total_today_tnum = CommonMiningPay::today_tnum($this->today_config);
        if ($total_today_tnum < 0.0001) {
            Log::write($this->name . " 合伙股东奖励-无新增业绩");
            return;
        }

        // 计算奖励
        $total_reward = bcmul($total_today_tnum, $this->common_mining_config['partner_reward'], 6);  // 计算总奖励
        $mining_member_count = count($mining_member); // 人数
        $member_reward = bcdiv($total_reward, $mining_member_count, 6);
        if ($member_reward > 0.0001) {
            $last_id = CommonMiningIncome::where(['type' => 9, 'award_time' => $this->today_config['today_start']])->max('member_id');
            foreach ($mining_member as $value) {
                if ($value['member_id'] <= $last_id) {
                    continue;
                }

                $last_id = $value['member_id'];
                echo "partner_reward {$last_id}\r\n";

                CommonMiningIncome::award($value['member_id'], $this->common_mining_config['pay_currency_id'], 9, $member_reward, 0, $total_today_tnum, 0, $this->common_mining_config['gloabl_percent'], $this->today_config['today_start']);
            }
        }
    }

    // 合伙股东 - 技术服务费
    protected function partner_reward_service()
    {
        Log::write($this->name . " 合伙股东奖励已完成");
        // 满足条件的用户
        $mining_member = CommonMiningMember::partner_member($this->common_mining_config);
        if (empty($mining_member)) {
            Log::write($this->name . " 技术服务费-无满足条件的用户");
            return;
        }

        // 今日新增服务费
        $today_feenum = CommonMiningRelease::today_feenum($this->today_config);
        if ($today_feenum < 0.0001) {
            Log::write($this->name . " 技术服务费-无新增业绩");
            return;
        }

        // 获取FIL/USDT交易价格
        $TradePrice = CommonMiningRelease::TradePrice(81, 5);
        if ($TradePrice < 0.0001) {
            Log::write($this->name . " 技术服务费-获取FIL价格失败");
            return;
        }

        // 计算奖励
        $total_reward = bcdiv(bcmul($today_feenum, $this->common_mining_config['partner_reward_service'], 6), 100, 6);
        $mining_member_count = count($mining_member); // 人数
        $member_reward = bcdiv($total_reward, $mining_member_count, 6); // 平均每人得到奖励FIL
        $member_reward = bcmul($member_reward, $TradePrice, 6); // FIL转换成USDT
        if ($member_reward > 0.0001) {
            $last_id = CommonMiningIncome::where(['type' => 10, 'award_time' => $this->today_config['today_start']])->max('member_id');
            foreach ($mining_member as $value) {
                if ($value['member_id'] <= $last_id) {
                    continue;
                }

                $last_id = $value['member_id'];
                echo "partner_reward_service {$last_id}\r\n";

                CommonMiningIncome::award($value['member_id'], $this->common_mining_config['pay_currency_id'], 10, $member_reward, 0, $today_feenum, 0, $this->common_mining_config['gloabl_percent'], $this->today_config['today_start']);
            }
        }
    }

    protected function summary()
    {
        $insert_data = [
            'today' => $this->today_config['today'],
            'pay_num' => 0,
            'release_num' => 0,
            'team1_num' => 0,
            'team2_num' => 0,
            'team3_num' => 0,
            'team4_num' => 0,
            'team5_num' => 0,
            'team6_num' => 0,
            'team7_num' => 0,
            'team8_num' => 0,
            'team9_num' => 0,
            'team10_num' => 0,
            'add_time' => time(),
        ];

        // 入金数量
        $insert_data['pay_num'] = CommonMiningPay::where([
            'add_time' => ['between', [$this->today_config['yestday_start'], $this->today_config['yestday_stop']]],
        ])->sum('pay_num');

        $insert_data['release_num'] = CommonMiningRelease::where([
            'release_time' => $this->today_config['today_start'],
        ])->sum('num');

        $insert_data['team1_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 1,
        ])->sum('num');

        $insert_data['team2_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 2,
        ])->sum('num');

        $insert_data['team3_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 3,
        ])->sum('num');

        $insert_data['team4_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 4,
        ])->sum('num');

        $insert_data['team5_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 5,
        ])->sum('num');

        $insert_data['team6_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 6,
        ])->sum('num');
        $insert_data['team7_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 7,
        ])->sum('num');

        $insert_data['team8_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 8,
        ])->sum('num');

        $insert_data['team9_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 9,
        ])->sum('num');

        $insert_data['team10_num'] = CommonMiningIncome::where([
            'award_time' => $this->today_config['today_start'],
            'type' => 10,
        ])->sum('num');

        Db::name('common_mining_summary')->insertGetId($insert_data);
    }
}
