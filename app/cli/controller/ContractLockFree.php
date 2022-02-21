<?php

namespace app\cli\controller;

use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\ContractConfig;
use app\common\model\ContractIncomeLog;
use app\common\model\ContractLockLog;
use app\common\model\ContractOrder;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\MemberBind;
use think\console\Command;
use think\Log;
use think\Db;
use think\Exception;
use think\console\Input;
use think\console\Output;

/**
 * 合约锁仓释放
 */
class ContractLockFree extends Command
{
    protected function configure()
    {
        $this->setName('ContractLockFree')->setDescription('This is a ContractLockFree');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        $this->doRun('');
    }

    protected function doRun($today='')
    {
        set_time_limit(0);
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        if(empty($today)) $today = date('Y-m-d');

        $today_start = strtotime($today);
        $today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write("合约锁仓释放:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $configs = ContractConfig::get_configs();
        if(empty($configs) || $configs['contract_lock_free_switch'] != 1) {
            Log::write("合约锁仓释放,配置为空或者直推奖励开关未开启");
            return;
        }

        /*$currency = Currency::where(['currency_id'=>$configs['contract_currency_id']])->field('currency_id,currency_mark')->find();
        if(empty($currency)) {
            Log::write("合约锁仓释放失败,币种不存在");
            return;
        }*/
        $currencys = Currency::where(['currency_id'=>['in', $configs['contract_currency_list_1']]])->field('currency_id,currency_mark')->select();
        if(count($currencys) <= 0) {
            Log::write("合约锁仓释放失败,币种不存在");
            return;
        }
        $currencyList = [];
        foreach ($currencys as $key => $val) {
            $currencyList[] = $val['currency_id'];
        }

        $where = [
            //'currency_id'=>$currency['currency_id'],
            'currency_id'=>['in', $currencyList],
            'contract_lock'=>['gt',0],
        ];
        $select = Db::name('CurrencyUser')->field('member_id,contract_lock,currency_id')
            ->where($where)->select();
        Log::write("合约锁仓释放:sql:".Db::name('CurrencyUser')->getLastSql());
        if (count($select) > 0) {
            $free_rate = $configs['contract_lock_free_rate'];
            foreach ($select as $key => $value) {
                $member_id = $value['member_id'];
                $currency_id = $value['currency_id'];
                $contract_lock = $value['contract_lock'];
                $where1 = [
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'type' => 2,//类型 1-直推奖励 2-冻结释放
                    'today' => $today_config['today'],
                ];
                $income_find = ContractIncomeLog::where($where1)->find();
                if (!$income_find) {
                    $income = keepPoint($contract_lock * $free_rate / 100, 6);
                    $log = "合约锁仓释放:member_id:{$member_id},contract_lock:{$contract_lock},free_rate:{$free_rate},income:{$income}";
                    if ($income > 0) {

                        try {
                            Db::startTrans();

                            //保存收益记录
                            $log_id = ContractIncomeLog::insertGetId([
                                'member_id' => $member_id,
                                //'currency_id' => $configs['contract_currency_id'],
                                'currency_id' => $currency_id,
                                'type' => 2,//类型 1-直推奖励 2-冻结释放
                                'num' => $income,
                                'base_num' => $contract_lock,
                                'percent' => $free_rate,
                                'fee' => 0,
                                'create_time' => time(),
                                'today' => $today_config['today'],
                            ]);
                            if(!$log_id) throw new Exception("插入奖励记录失败");

                            // 保存用户资产
                            $usersCurrency = CurrencyUser::getCurrencyUser($member_id, $currency_id);
                            if(!$usersCurrency) throw new Exception("获取资产错误");

                            // 账本
                            $flag = AccountBook::add_accountbook($member_id, $currency_id, AccountBookType::CONTRACT_LOCK_FREE, 'contract_lock_free', 'in', $income, $log_id);
                            if ($flag === false) {
                                throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                            }

                            $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setInc('num',$income);
                            if ($flag === false) {
                                throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                            }

                            $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'contract_lock'=>$usersCurrency['contract_lock']])->setDec('contract_lock',$income);
                            if ($flag === false) {
                                throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                            }

                            //添加锁仓记录
                            $flag = ContractLockLog::add_log(2,$member_id,$currency_id,-$income);
                            if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                            $log .= ",成功";
                            Db::commit();
                        } catch (Exception $exception) {
                            Db::rollback();
                            $log .= ",异常:{$exception->getMessage()}";
                        }
                        Log::write($log, 'INFO');
                    }
                    else {
                        Log::write("合约锁仓释放,奖励为空");
                    }
                }
                else {
                    Log::write("合约锁仓释放,今日奖励已发放");
                }
            }
        }
        else {
            Log::write("合约锁仓释放,未找到符合条件(合约每天交易 {$configs['contract_zhitui_condition']} KOI)的用户,任务结算");
        }

        Log::write("合约锁仓释放:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
    }
}
