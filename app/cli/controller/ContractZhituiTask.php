<?php

namespace app\cli\controller;

use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\ContractConfig;
use app\common\model\ContractIncomeLog;
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
 * 合约直推奖励发放
 */
class ContractZhituiTask extends Command
{
    protected function configure()
    {
        $this->setName('ContractZhituiTask')->setDescription('This is a ContractZhituiTask');
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

        Log::write("合约直推奖励:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $configs = ContractConfig::get_configs();
        if(empty($configs) || $configs['contract_zhitui_switch'] != 1) {
            Log::write("合约直推奖励失败,配置为空或者直推奖励开关未开启");
            return;
        }

        /*$currency = Currency::where(['currency_id'=>$configs['contract_currency_id']])->field('currency_id,currency_mark')->find();
        if(empty($currency)) {
            Log::write("合约直推奖励失败,币种不存在");
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
            'money_type'=>1,
            //'money_currency_id'=>$configs['contract_currency_id'],
            'money_currency_id'=>['in', $currencyList],
            'add_time'=>['between', [$today_config['yestday_start'], $today_config['yestday_stop']]],
            'status'=>['in','3,4,5'],
        ];
        $select = Db::name('ContractOrder')->field('member_id,SUM(money_currency_num) as total')
            ->where($where)->group('member_id')
            ->having('total>='.$configs['contract_zhitui_condition'])->select();
        Log::write("合约直推奖励:sql:".Db::name('ContractOrder')->getLastSql());
        if (count($select) > 0) {
            foreach ($select as $key => $value) {
                $member_id = $value['member_id'];
                $currency_id = $value['money_currency_id'];
                $where1 = [
                    'member_id' => $member_id,
                    //'currency_id' => $configs['contract_currency_id'],
                    'currency_id' => $currency_id,
                    'type' => 1,//类型 1-直推奖励
                    'today' => $today_config['today'],
                ];
                $income_find = ContractIncomeLog::where($where1)->find();
                if (!$income_find) {
                    $total = $value['total'];
                    $log = "合约直推奖励:member_id:{$member_id},total:{$total}";
                    $this->contract_zhitui($member_id, $configs, $currency_id, $today_config, $log);
                }
                else {
                    Log::write("合约直推奖励,今日奖励已发放");
                }
            }
        }
        else {
            Log::write("合约直推奖励,未找到符合条件(合约每天交易 {$configs['contract_zhitui_condition']} KOI)的用户,任务结算");
        }

        Log::write("合约订单平仓处理:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
    }

    private function contract_zhitui($member_id, $configs, $currency_id, $today_config, &$log) {

        try {
            Db::startTrans();

            $zhitui_num = $this->get_zhitui_num($member_id);
            if ($zhitui_num > 0) {
                $log .= ",zhitui_num:{$zhitui_num}";
                //$level_num = $zhitui_num > 10 ? 10 : $zhitui_num;
                $level_num = $zhitui_num > 3 ? 3 : $zhitui_num;
                $bind_list = $this->get_bind_list($member_id, $level_num, $today_config);
                if (count($bind_list) > 0) {
                    //$total = 0;
                    $income_total = 0;
                    foreach ($bind_list as $key => $value) {
                        $level = $value['level'];
                        $child_list = $value['child_list'];
                        if (count($child_list) > 0) {
                            $yestday_total = $value['yestday_total'];
                            //if ($yestday_total > 0) $total += $yestday_total;
                            $child_list1 = implode(',', $child_list);
                            $config_key = 'contract_zhitui_rate_'.$level;
                            $rate = $configs[$config_key];
                            $income = 0;
                            if ($rate > 0) $income = keepPoint($yestday_total * $rate / 100, 6);
                            if ($income > 0) $income_total += $income;
                            //$log .= ",total:{$total},rate:{$rate}%,income:{$income}";
                            $log .= ",第{$level}层,yestday_total:{$yestday_total},income:{$income},child_list:({$child_list1})";
                        }
                        else {
                            $log .= ",第{$level}层下级为空";
                        }
                    }
                    /*$rate = $configs['contract_zhitui_rate'];
                    $income = keepPoint($total * $rate / 100, 6);
                    $log .= ",total:{$total},rate:{$rate}%,income:{$income}";*/
                    $log .= ",income_total:{$income_total}";
                    //if ($income > 0) {
                    if ($income_total > 0) {
                        //保存收益记录
                        $log_id = ContractIncomeLog::insertGetId([
                            'member_id' => $member_id,
                            //'currency_id' => $configs['contract_currency_id'],
                            'currency_id' => $currency_id,
                            'type' => 1,//类型 1-直推奖励
                            //'num' => $income,
                            'num' => $income_total,
                            //'base_num' => $total,
                            //'percent' => $rate,
                            'fee' => 0,
                            'create_time' => time(),
                            'today' => $today_config['today'],
                        ]);
                        if(!$log_id) throw new Exception("插入奖励记录失败");

                        // 保存用户资产
                        //$usersCurrency = CurrencyUser::getCurrencyUser($member_id, $configs['contract_currency_id']);
                        $usersCurrency = CurrencyUser::getCurrencyUser($member_id, $currency_id);
                        if(!$usersCurrency) throw new Exception("获取资产错误");

                        // 账本
                        //$flag = AccountBook::add_accountbook($member_id, $configs['contract_currency_id'], AccountBookType::CONTRACT_ZHITUI, 'contract_zhitui', 'in', $income, $log_id);
                        $flag = AccountBook::add_accountbook($member_id, $currency_id, AccountBookType::CONTRACT_ZHITUI, 'contract_zhitui', 'in', $income, $log_id);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }

                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setInc('num',$income);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }

                        $log .= ",成功";
                        Db::commit();
                    }
                    else {
                        $log .= ",收益=0";
                    }
                }
                else {
                    $log .= ",下级列表为空,没有奖励";
                }
            }
            else {
                $log .= ",直推数量=0,没有奖励";
            }
        } catch (Exception $exception) {
            Db::rollback();
            $log .= ",异常:{$exception->getMessage()}";
        }
        Log::write($log, 'INFO');
        return true;
    }

    private function get_zhitui_num($member_id) {

        $where = [
            'member_id'=>$member_id,
            'level'=>1,
        ];
        $zhitui_num = MemberBind::where($where)->count('child_id') ? : 0;
        return $zhitui_num;
    }

    private function get_bind_list($member_id, $level_num, $today_config) {

        $bind_list = [];
        for ($i = 1; $i <= $level_num; $i++) {
            $where = [
                'member_id'=>$member_id,
                'level'=>$i,
            ];
            $child_list = MemberBind::where($where)->column('child_id');
            if (count($child_list) > 0) {
                $where1 = [
                    'member_id'=>['in',$child_list],
                    'status'=>['in','3,4,5'],
                    'add_time'=>['between', [$today_config['yestday_start'], $today_config['yestday_stop']]],
                ];
                //$yestday_total = ContractOrder::where($where1)->sum('money_currency_num') ? : 0;
                $yestday_total = ContractOrder::where($where1)->sum('fee_value') ? : 0;
                $bind_list[] = [
                    'level'=>$i,
                    'child_list'=>$child_list,
                    'yestday_total'=>$yestday_total,
                ];
            }
        }
        return $bind_list;
    }

    private function get_yestday_total($member_id, $today_config) {
        $where = [
            'member_id'=>$member_id,
            'status'=>['in','3,4,5'],
            'add_time'=>['between', [$today_config['yestday_start'], $today_config['yestday_stop']]],
        ];
        //$total = ContractOrder::where($where)->sum('money_currency_num') ? : 0;
        $total = ContractOrder::where($where)->sum('fee_value') ? : 0;
        return $total;
    }
}
