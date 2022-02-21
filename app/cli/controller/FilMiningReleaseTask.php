<?php
namespace app\cli\controller;
use app\common\model\AccountBook;
use app\common\model\CommonMiningMember;
use app\common\model\CommonMiningPay;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyLockBook;
use app\common\model\CurrencyNodeLock;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\FilMining;
use app\common\model\FilMiningConfig;
use app\common\model\FilMiningIncome;
use app\common\model\FilMiningIncomeDetail;
use app\common\model\FilMiningLevel;
use app\common\model\FilMiningLevelIncomeDetail;
use app\common\model\FilMiningPay;
use app\common\model\FilMiningRelease;
use app\common\model\Member;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

class FilMiningReleaseTask extends Command
{
    public $name = '涡轮增压释放任务';
    protected $today_config = [];
    protected $fil_mining_config = [];
    protected $real_currency_price = [];

//    protected $all_release_percent = 0;
//    protected $first_release_percent = 0;
//    protected $last_release_percent = 0;

    protected $all_month_percent = null;
    protected $last_month_percent = 0;

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('FilMiningReleaseTask')->setDescription('This is a FilMiningReleaseTask');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');
        $this->doRun();
    }

    public function doRun($today='') {
        if(empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name." 开始 ");

        $this->fil_mining_config = FilMiningConfig::get_key_value();
        if(empty($this->fil_mining_config)) {
            Log::write($this->name." 配置为空");
            return;
        }

        $real_currency_list = FilMining::field('real_currency_id')->distinct('real_currency_id')->select();
        if(empty($real_currency_list)) {
            Log::write($this->name." 释放币种为空");
            return;
        }

        foreach ($real_currency_list as $real_currency) {
            $currency_price = FilMining::getReleaseCurrencyPrice($real_currency['real_currency_id']);
            if($currency_price<=0) {
                Log::write($this->name." ".$real_currency['real_currency_id']."币种价格获取失败");
            } else {
                $this->real_currency_price[$real_currency['real_currency_id']] =$currency_price;
            }
        }

        if(empty($this->real_currency_price)) {
            Log::write($this->name." 释放币种价格为空");
            return;
        }

        // 支付币种USD价格
        $pay_currency_price = CurrencyPriceTemp::get_price_currency_id($this->fil_mining_config['pay_currency_id'],'USD');
        if($pay_currency_price<=0) {
            Log::write($this->name." 支付币种价格为空");
            return;
        }
        $this->real_currency_price[$this->fil_mining_config['pay_currency_id']] = $pay_currency_price;

        $this->all_month_percent = FilMiningLevel::getMonthAllPercent();
        if(empty($this->all_month_percent)) {
            Log::write($this->name." 释放比例为空");
            return;
        }
        $this->last_month_percent = FilMiningLevel::getMonthLastPercent();

        $this->all_levels = FilMiningLevel::getAllLevel();
        if(empty($this->all_levels)) {
            Log::write($this->name." 等级为空");
            return;
        }
        $this->all_levels = array_column($this->all_levels,null,'level_id');

        // 产币线性释放 75%
        $this->release_lock_release_num();
        // 小区线性释放 75%
        $this->release_third_release_num();

        //增加上级业绩
        $this->team_num();
        //推荐奖励发放
        $this->recommand_num();
        //释放
        $this->release_num();
        //释放的小区奖励
        $this->release_third_num();

        // 等级奖励详情
        $this->level_income_detail();
        // 等级奖励
        $this->level_income();
        // 全球奖励
        $this->level_income_global();

        // 锁仓释放
        $this->lock_release_num();

        // 升级
        $this->levelUpdate();

        //汇总
        $this->summary();

        // 注册锁仓释放
        $this->register_lock();
    }

    // 产币线性释放 75%
    public function release_lock_release_num() {
        $last_id = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['default_release_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 21,
        ])->max('third_id');
        while (true) {
            $fil_mining = FilMining::where([
                'id'=>['gt',$last_id],
                'lock_num' => ['gt',0],
            ])->order('id asc')->find();
            if(empty($fil_mining)) {
                Log::write($this->name." 产出锁仓释放已完成");
                break;
            }

            $last_id = $fil_mining['id'];
            echo "release_lock_release_num {$last_id}\r\n";

            FilMiningRelease::release_lock_release($fil_mining,$this->fil_mining_config,$this->today_config['today_start']);
        }
    }

    // 小区线性释放 75%
    public function release_third_release_num() {
        $last_id = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 22,
        ])->max('third_id');
        while (true) {
            $fil_mining = FilMining::where([
                'id'=>['gt',$last_id],
                'total_release15' => ['gt',0],
            ])->order('id asc')->find();
            if(empty($fil_mining)) {
                Log::write($this->name." 产出锁仓释放已完成");
                break;
            }

            $last_id = $fil_mining['id'];
            echo "release_third_release_num {$last_id}\r\n";

            FilMiningRelease::release_third_release_num($fil_mining,$this->fil_mining_config,$this->today_config['today_start']);
        }
    }

    //昨日新入金 增加上级业绩
    public function team_num() {
        $last_id = 0;
        while (true) {
            $fil_mining_pay = FilMiningPay::where([
                'id'=>['gt',$last_id],
                'add_time' => ['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
                'is_team' => 0,
            ])->order('id asc')->find();
            if(empty($fil_mining_pay)) {
                Log::write($this->name." 上级业绩已完成");
                break;
            }
            $last_id = $fil_mining_pay['id'];
            echo 'team_num:'.$last_id."\r\n";

            $flag = FilMiningPay::where(['id'=> $fil_mining_pay['id'], 'is_team' => 0])->setField('is_team',1);
            if(!$flag) {
                Log::write($this->name." 更新是否团队失败".$fil_mining_pay['id']);
                continue;
            }

            // 增加直推业绩
            $flag = FilMining::addOneTeamNum($fil_mining_pay['member_id'],$fil_mining_pay['currency_id'],$fil_mining_pay['pay_num']);
            if(!$flag) {
                Log::write($this->name." 增加直推业绩失败".$fil_mining_pay['id']);
            }

            // 增加团队业绩
            $flag = FilMining::addParentTeamNum($fil_mining_pay['member_id'],$fil_mining_pay['currency_id'],$fil_mining_pay['pay_num']);
            if(!$flag) {
                Log::write($this->name." 增加上级业绩失败".$fil_mining_pay['id']);
            }
        }
    }

    // 推荐奖励发放
    public function recommand_num() {
        Log::write($this->name." 推荐奖励开始");
        if($this->fil_mining_config['recommand_income_open']!=1) {
            Log::write($this->name." 推荐奖励关闭");
            return;
        }

        $last_id = FilMiningIncome::where(['type'=>11,'award_time'=>$this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $fil_mining_pay = FilMiningPay::where([
                'id'=>['gt',$last_id],
                'add_time' => ['between',[ $this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
                'is_recommand' => 0,
            ])->order('id asc')->find();
            if(empty($fil_mining_pay)) {
                Log::write($this->name." 推荐奖励已完成");
                break;
            }
            $last_id = $fil_mining_pay['id'];
            echo 'level_income_detail:'.$last_id."\r\n";

            $flag = FilMiningPay::where(['id'=> $fil_mining_pay['id'], 'is_recommand' => 0])->setField('is_recommand',1);
            if(!$flag) {
                Log::write($this->name." 更新是否推荐奖励失败".$fil_mining_pay['id']);
                continue;
            }

            // 添加奖励详情
            $flag = FilMiningPay::recommand_award($fil_mining_pay,$this->fil_mining_config,$this->today_config['today_start']);
        }
    }

    //释放昨天的
    public function release_num() {
        if($this->fil_mining_config['release_income_open']!=1) {
            Log::write($this->name." 释放奖励关闭");
            return;
        }

        $last_id = FilMiningRelease::where(['release_time'=>$this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $fil_mining = FilMining::where([
                'id'=>['gt',$last_id],
                'add_time' => ['lt',$this->today_config['today_start'] ],
                'release_num_avail' => ['gt',0],
            ])->order('id asc')->find();
            if(empty($fil_mining)) {
                Log::write($this->name." 释放奖励已完成");
                break;
            }

            $last_id = $fil_mining['id'];
            echo "release_num {$last_id}\r\n";

            if($fil_mining['release_num_avail']<=0) {
                continue;
            }

//            $release_percent = FilMiningLevel::getCurMonthPercent($this->all_month_percent,$fil_mining['release_start_day'],$this->today_config['today_start']);
//            if($release_percent<=0) $release_percent = $this->last_month_percent;

            $release_percent = $this->fil_mining_config['new_fixed_release_percent'];

            // 更改今日释放比例
            $flag = FilMining::where(['id'=>$fil_mining['id']])->update([
                'release_percent_day' => $this->today_config['today_start'],
                'release_percent' => $release_percent,
            ]);

            //释放
            $release_res = FilMiningRelease::release($fil_mining,$release_percent,$this->real_currency_price,$this->fil_mining_config,$this->today_config['today_start']);
            // 新矿机才给上级奖励
            if($release_res['release_num']>0) {
                FilMiningRelease::release_award($fil_mining,$this->fil_mining_config,$release_res,$this->today_config['today_start']);
            }
        }
    }

    //释放3代奖励
    public function release_third_num() {
        if($this->fil_mining_config['release_income_open']!=1) {
            Log::write($this->name." 释放奖励关闭");
            return;
        }

        $last_id = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 15,
        ])->max('member_id');
        $last_id = intval($last_id);
        while (true) {
            $income_detail = FilMiningIncomeDetail::where([
                'currency_id' => $this->fil_mining_config['pay_currency_id'],
                'award_time'=> $this->today_config['today_start'],
                'member_id'=>['gt',$last_id],
            ])->order('member_id asc')->find();
            if(empty($income_detail)) {
                Log::write($this->name." 释放小区奖励已完成");
                break;
            }

            $last_id = $income_detail['member_id'];
            echo "release_third_num {$last_id}\r\n";

            $income_sum = FilMiningIncomeDetail::where([
                'currency_id' => $this->fil_mining_config['pay_currency_id'],
                'award_time'=> $this->today_config['today_start'],
                'member_id'=> $income_detail['member_id'],
            ])->sum('num');

            if($income_sum>0) {
                $pid_fil_mining = FilMining::where(['member_id' => $income_detail['member_id'], 'currency_id' => $income_detail['currency_id']])->find();
                if($pid_fil_mining) {
                    $income_limit = FilMining::getNewStaticLimit($pid_fil_mining,$this->fil_mining_config);

                    //添加奖励
                    $income_sum = min($income_sum,$income_limit);

                    // 默认全部到账
                    $award_lock_num = 0; // 75%
                    if($this->fil_mining_config['release_lock_percent']>0) {
                        // 产出数量要75%部分锁仓
                        $award_lock_num = keepPoint($income_sum * $this->fil_mining_config['release_lock_percent'] / 100,6);
                        $income_sum = keepPoint($income_sum - $award_lock_num,6);
                    }

//                    if($income_sum>=0.000001) {
                    if($income_sum>=0.000001 || $award_lock_num>=0.000001) {
                        FilMiningIncome::award($pid_fil_mining,$income_detail['member_id'],$income_detail['currency_id'],15,$income_sum, 0,0,0,0,$this->today_config['today_start'],0,0,0,$award_lock_num);
                    }
                }
            }
        }
    }

    /**
     * 注册锁仓释放
     * 本人 注册账户 就送 100 USDT（锁仓），直推（一代）10人有购买 算力值或 满存算力，满足条件，就一次性将100 USDT释放到他的可用账户
     * @return false
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function register_lock() {
        $config = (new Config())->byField();
        if(empty($config)) {
            Log::write($this->name."注册释放 | 获取配置错误");
            return false;
        }
        Log::write($this->name." | 注册释放开始");
        $last_id = 0;
        while (true){
            // 判断记录
            $currency_lock_book = CurrencyLockBook::where(['id'=>['>',$last_id],'field'=>'register_lock','type'=>'register_handsel',
                'currency_id'=>$config['register_handsel_currency_id'],'status'=>0])->order(['id'=>'asc'])->find();
            if(empty($currency_lock_book)) {
                Log::write($this->name." | 注册释放结束");
                break;
            }

            $last_id = $currency_lock_book['id'];
            echo "注册锁仓释放 {$last_id}\r\n";

            // 判断账户余额
            $currency_user = CurrencyUser::where(['member_id'=>$currency_lock_book['user_id'],'currency_id'=>$currency_lock_book['currency_id'],'register_lock'=>['>','0']])->find();
            if(empty($currency_user)) {
                Log::write($this->name." | 注册释放 | 找不到用户记录 | 用户ID：".$currency_lock_book['user_id']);
                continue;
            }
            if($currency_lock_book['number'] != $currency_user['register_lock']) {
                Log::write($this->name." | 注册释放记录金额不一致");
                continue;
            }

            // 20210318 用户30天内，没有完成条件，就失效
            $register_handsel_time = $config['register_handsel_time'] * 86400;
            $register_handsel_time = $currency_lock_book['create_time'] + $register_handsel_time;
            if($register_handsel_time < time()) {
                $flag_CurrencyLockBook = CurrencyLockBook::where(['id'=>$currency_lock_book['id'],'status'=>0])->setField('status',1);
                $flag_CurrencyUser = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'register_lock'=>$currency_user['register_lock']])->setDec('register_lock' ,$currency_user['register_lock']);
                if(!$flag_CurrencyLockBook || !$flag_CurrencyUser) {
                    Log::write($this->name." | 注册释放 30天过期 数据保存失败");
                }
                Log::write($this->name." | 注册释放 30天过期 用户ID ".$last_id);
                continue;
            }

            // 查询直推人数 > 10
            $currency_pid_count = Member::where(['pid'=>$currency_user['member_id']])->column('member_id');
            if(count($currency_pid_count) < $config['register_handsel_people']) {
                Log::write($this->name." | 注册释放 | 直推人数小于 | ".$config['register_handsel_people']);
                continue;
            }

            // 查询算力值
            $FilMining_count = FilMining::where('member_id','in',$currency_pid_count)->count();

            // 查询满存算力
            $CommonMiningPay = CommonMiningMember::where(['member_id'=>$currency_user['member_id']])->find();
            $CommonMiningPay = $CommonMiningPay ? $CommonMiningPay['one_team_count'] : 0;
            if(($FilMining_count + $CommonMiningPay) < $config['register_handsel_people']) {
                Log::write($this->name." | 注册释放 | 购买算力数量不足 | ".$config['register_handsel_people']);
                continue;
            }

            try{
                Db::startTrans();
                // 修改记录状态
                $flag = CurrencyLockBook::where(['field'=>'register_lock','user_id'=>$currency_user['member_id'],'type'=>'register_handsel','currency_id'=>$currency_user['currency_id'],'status'=>0])->setField('status',1);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加锁仓变动记录
                $flag = CurrencyLockBook::add_log('register_lock','release',$currency_user['member_id'],$currency_user['currency_id'],$currency_user['register_lock'],$currency_user['cu_id']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加账本
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6701,'registration','in',$currency_user['register_lock'],$currency_user['cu_id']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //扣除冻结资产 增加可用资产
                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'register_lock'=>$currency_user['register_lock']])->update([
                    'num' => ['inc',$currency_user['register_lock']],
                    'register_lock' => ['dec',$currency_user['register_lock']]
                ]);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                Db::commit();
            }catch (Exception $e) {
                Db::rollback();
                Log::write($this->name." | 注册释放 | 钱包ID".$currency_user['cu_id']." | 失败信息:".$e->getMessage());
                continue;
            }
        }
    }

    // 更新用户等级
    protected function levelUpdate() {
        foreach ($this->all_levels as $cur_level) {
            $last_id = 0;
            while (true) {
                $fil_mining = FilMining::where([
                    'id' => ['gt',$last_id],
                    'level' => $cur_level['level_id'] -1,
                ])->order('id asc')->find();
                if(empty($fil_mining)) {
                    Log::write($this->name." 升级已完成".$cur_level['level_id']);
                    break;
                }

                $last_id = $fil_mining['id'];
                echo $cur_level['level_id']."levelUpdate".$last_id." \r\n";

                // 自身入金考核
                if($cur_level['level_self_num']>0) {
                    if($fil_mining['pay_num']<$cur_level['level_self_num']){
                        continue;
                    }
                }

                // 小区业绩考核
                if($cur_level['level_child_num']>0) {
                    $big_num = FilMining::getBigTeamNum($fil_mining['member_id'],$fil_mining['currency_id']);
                    $small_num = $fil_mining['team_total'] - $big_num;
                    if($big_num<=0 || $small_num < $cur_level['level_child_num']) {
                        continue;
                    }
                }

                // 部门等级考核
                if($cur_level['level_child_count']>0 && $cur_level['level_child_level']>0) {
                    $level_child_count = FilMining::getTeamLevelCount($fil_mining['member_id'],$fil_mining['currency_id'],$cur_level['level_child_level']);
                    if($level_child_count<$cur_level['level_child_count']) {
                        continue;
                    }
                }

                Log::write($fil_mining['id']." 升级成功".$cur_level['level_id']);

                // 更新等级
                FilMining::where(['id'=>$fil_mining['id']])->setField('level',$cur_level['level_id']);
                // 更新团队最高等级
                FilMining::updateTeamMaxLevel($fil_mining['member_id'],$fil_mining['currency_id'],$cur_level['level_id']);

                // 增加升级记录
                Db::name('fil_mining_level_log')->insertGetId([
                    'third_id' => $fil_mining['member_id'],
                    'level' => $cur_level['level_id'],
                    'add_time' => $this->today_config['today_start'],
                ]);
            }
        }
    }

    // 等级级差奖励详情
    protected function level_income_detail() {
        Log::write($this->name." 级差奖励详情开始");
        if($this->fil_mining_config['level_income_open']!=1) {
            Log::write($this->name." 级差奖励详情关闭");
            return;
        }

        $last_id = FilMiningLevelIncomeDetail::where(['award_time'=>$this->today_config['today_start']])->max('third_id');
        $last_id = intval($last_id);
        while (true) {
            $pay_start_time = $this->today_config['today_start'] - 86400;
            $pay_stop_time = $this->today_config['yestday_stop'];
            $fil_mining_pay = FilMiningPay::where([
                'id'=>['gt',$last_id],
                'add_time' => ['between',[ $pay_start_time, $pay_stop_time] ],
                'is_award' => 0,
            ])->order('id asc')->find();
            if(empty($fil_mining_pay)) {
                Log::write($this->name." 级差奖励详情已完成");
                break;
            }
            $last_id = $fil_mining_pay['id'];
            echo 'level_income_detail:'.$last_id."\r\n";

            $flag = FilMiningPay::where(['id'=> $fil_mining_pay['id'], 'is_award' => 0])->setField('is_award',1);
            if(!$flag) {
                Log::write($this->name." 更新是否级差失败".$fil_mining_pay['id']);
                continue;
            }

            // 添加奖励详情
            $flag = FilMiningLevelIncomeDetail::award_detail($fil_mining_pay,$this->all_levels,$this->fil_mining_config,$this->today_config['today_start']);
        }
    }

    //等级级差奖励发放
    protected function level_income() {
        Log::write($this->name." 级差奖开始");
        if($this->fil_mining_config['level_income_open']!=1) {
            Log::write($this->name." 等级级差奖励关闭");
            return;
        }

        $last_id = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 4,
        ])->max('member_id');
        $last_id = intval($last_id);
        while (true) {
            $income_detail = FilMiningLevelIncomeDetail::where([
                'currency_id' => $this->fil_mining_config['pay_currency_id'],
                'award_time'=> $this->today_config['today_start'],
                'member_id'=>['gt',$last_id],
            ])->order('member_id asc')->find();
            if(empty($income_detail)) {
                Log::write($this->name." 级差奖已完成");
                break;
            }

            $last_id = $income_detail['member_id'];
            echo "level_income {$last_id}\r\n";

            $income_sum = FilMiningLevelIncomeDetail::where([
                'currency_id' => $this->fil_mining_config['pay_currency_id'],
                'award_time'=> $this->today_config['today_start'],
                'member_id'=> $income_detail['member_id'],
            ])->sum('num');

            $pid_fil_mining = FilMining::where(['member_id' => $income_detail['member_id'], 'currency_id' => $income_detail['currency_id']])->find();

            //20200118修改为推荐+级差 封顶9倍
            $max_award_num = FilMining::getNewHelpAwardLimit($pid_fil_mining,$this->fil_mining_config);
            if($income_sum>0 && $pid_fil_mining && $max_award_num>0) {
                //添加奖励
                $income_sum = min($income_sum,$max_award_num);
                FilMiningIncome::award($pid_fil_mining,$income_detail['member_id'],$income_detail['currency_id'],4,$income_sum,0,0,0,0,$this->today_config['today_start']);
            }
        }
    }

    // 等级10 加权平分奖励
    protected function level_income_global() {
        Log::write($this->name." 加权平分奖励开始");
        if($this->fil_mining_config['global_income_open']!=1) {
            Log::write($this->name." 加权平分奖励关闭");
            return;
        }

        $globalLevel = FilMiningLevel::getGlobalLevel();
        if(empty($globalLevel) || $globalLevel['level_id']<=0) {
            Log::write($this->name." 全球奖励已关闭");
            return;
        }

        $pay_start_time = $this->today_config['yestday_start'];
        $pay_stop_time = $this->today_config['yestday_stop'];
        if($this->fil_mining_config['global_level_start_time']>0) {
            $run_time = $this->today_config['today_start'] - $this->fil_mining_config['global_level_start_time'];
            if($run_time<=0 || $run_time % (86400*30) !=0 ) {
                Log::write($this->name."全球奖励未到结算时间");
                return;
            }
            $pay_start_time = $this->today_config['today_start'] - (30 * 86400);
            $pay_stop_time = $this->today_config['yestday_stop'];
            Log::write($this->name." 全球奖励区间".$pay_start_time." ".$pay_stop_time);
        }

        $all_fil_mining = FilMining::where(['level'=>$globalLevel['level_id']])->select();
        if(empty($all_fil_mining)) {
            Log::write($this->name." 全球奖励暂未达标用户");
            return;
        }

        $all_pay_num =  FilMiningPay::where(['add_time' => ['between',[ $pay_start_time,$pay_stop_time] ]])->sum('pay_num');
        if(empty($all_pay_num)) {
            Log::write($this->name." 全球奖励昨日未入金");
            return;
        }

        $all_pay_num = keepPoint($all_pay_num * $globalLevel['level_percent'] / 100,6);
        if($all_pay_num<=0) {
            Log::write($this->name." 全球奖励昨日未入金");
            return;
        }

        $global_count = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 5,
        ])->count();
        if($global_count) return;


        $all_team_num = 0;
        foreach ($all_fil_mining as $fil_mining) {
            $all_team_num = $all_team_num + $fil_mining['team_total'];
        }

        foreach ($all_fil_mining as $fil_mining) {
            $award_percent = $all_team_num>0 ? keepPoint($fil_mining['team_total'] / $all_team_num,6) : 0;
            $award_num = keepPoint($all_pay_num * $award_percent,6);

            $max_award_num = FilMining::getNewHelpAwardLimit($fil_mining,$this->fil_mining_config);
            $award_num = min($award_num,$max_award_num);
            if($award_num>0) {
                FilMiningIncome::award($fil_mining,$fil_mining['member_id'],$fil_mining['currency_id'],5,$award_num,0,$all_pay_num,0,keepPoint( $award_percent * 100,2),$this->today_config['today_start']);
            }
        }
        Log::write($this->name." 加权平分奖励已完成");
    }

    // 锁仓释放及清除
    protected function lock_release_num() {
        $last_id = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time'=> $this->today_config['today_start'],
            'type' => 16,
        ])->max('member_id');

        while (true) {
            $currency_user = CurrencyUser::where([
                'currency_id' => $this->fil_mining_config['pay_currency_id'],
                'lock_num'=> ['gt',0],
                'member_id'=>['gt',$last_id],
            ])->order('member_id asc')->find();
            if(empty($currency_user)) {
                Log::write($this->name." 锁仓释放已完成");
                break;
            }

            $last_id = $currency_user['member_id'];
            echo "lock_release_num {$last_id}\r\n";

            FilMiningPay::lock_release($currency_user,$this->fil_mining_config,$this->today_config['today_start']);
        }
    }

    private function summary() {
        $insert_data = [
            'today' => $this->today_config['today'],
            'pay_num' => 0,
            'release_num' => 0,
            'team1_num' => 0,
            'team2_num' => 0,
            'team3_num' => 0,
            'team4_num' => 0,
            'team5_num' => 0,

            'team11_num' => 0,
            'team12_num' => 0,
            'team13_num' => 0,
            'team15_num' => 0,
            'team16_num' => 0,
            'add_time' => time(),
        ];

        // 入金数量
        $insert_data['pay_num'] = FilMiningPay::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'add_time' => ['between',[$this->today_config['yestday_start'],$this->today_config['yestday_stop']] ],
        ])->sum('pay_num');

        $insert_data['release_num'] = FilMiningRelease::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'release_time' => $this->today_config['today_start'],
        ])->sum('num');

        $insert_data['team1_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 1,
        ])->sum('num');


        $insert_data['team2_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 2,
        ])->sum('num');

        $insert_data['team3_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 3,
        ])->sum('num');

        $insert_data['team4_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 4,
        ])->sum('num');

        $insert_data['team5_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 5,
        ])->sum('num');

        $insert_data['team11_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 11,
        ])->sum('num');


        $insert_data['team12_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 12,
        ])->sum('num');


        $insert_data['team13_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 13,
        ])->sum('num');

        $insert_data['team15_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 15,
        ])->sum('num');

        $insert_data['team16_num'] = FilMiningIncome::where([
            'currency_id' => $this->fil_mining_config['pay_currency_id'],
            'award_time' => $this->today_config['today_start'],
            'type' => 16,
        ])->sum('num');
        Db::name('fil_mining_summary')->insertGetId($insert_data);
    }
}
