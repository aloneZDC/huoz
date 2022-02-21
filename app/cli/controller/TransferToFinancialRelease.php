<?php
/**
 * 理财包释放
 */

namespace app\cli\controller;

use app\common\model\Configs;
use app\common\model\CurrencyUser;
use app\common\model\TransferFinancialAward;
use app\common\model\TransferFinancialAwardDetail;
use app\common\model\TransferToAsset;
use app\common\model\TransferToAssetConfig;
use app\common\model\TransferToFinancial;
use app\common\model\UsersCurrency;
use app\common\model\UsersLineIncomeLock;
use think\Db;
use think\Log;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class TransferToFinancialRelease extends Command
{

    protected function configure()
    {
        $this->setName('TransferToFinancialRelease')->setDescription('This is a TransferToFinancialRelease');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        set_time_limit(0);
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);


        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_stop' => $today_start + 86400 - 1,
        ];

        Log::write("理财包释放开始:".date('Y-m-d H:i:s'), 'INFO');
        $config_list = [];
        $asset_config = TransferToAssetConfig::select();
        foreach ($asset_config as $value) {
            if($value['to_is_financial_release']==1 && $value['to_is_financial_percent']>0) {
                $config_list[] = $value;
            }
        }
        if(empty($config_list)) {
            Log::write("理财包释放: 释放关闭");
            return;
        }

        $award_config = Db::name('transfer_financial_award_config')->select();
        $award_config_max = ['max_total'=>0,'max_level'=>0];
        if($award_config) {
            $award_config = array_column($award_config,null,'total');
            foreach ($award_config as $config) {
                if($config['total']>$award_config_max['max_total']) $award_config_max['max_total'] = $config['total'];
                if($config['level']>$award_config_max['max_level']) $award_config_max['max_level'] = $config['level'];
            }
        }

        foreach ($config_list as $config) {
            $currency_id = $config['to_currency_id'];
            $currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],true);

            $last_id = TransferToFinancial::where(['ttf_asset_type'=> $config['asset_type'] ,'ttf_time'=>['between',[ $today_config['today_start'],$today_config['today_stop'] ]],'ttf_type'=>'out','ttf_currency_id'=>$currency_id])->max('ttf_third_id');
            while (true) {
                echo $currency_id.' release:'.$last_id."\r\n";
                $users_currency = CurrencyUser::where(['cu_id'=>['gt',$last_id],$currency_field=>['gt',0] ])->order('cu_id asc')->find();
                if(empty($users_currency)) {
                    Log::write("理财包释放任务执行完毕:".date('Y-m-d H:i:s'), 'INFO');
                    break;
                }
                //理财包释放 并添加上级奖励明细
                $flag = TransferToFinancial::release($users_currency,$config,$award_config,$award_config_max,$today_config);
                $last_id = $users_currency['cu_id'];
            }
        }

        //根据奖励明细 发放奖励
        foreach ($config_list as $config) {
            $currency_id = $config['to_currency_id'];

            //是否开启赠送
            if($config['is_static_award']==1) {
                $last_id = TransferFinancialAward::where(['tta_day'=>$today_config['today_start'],'tta_asset_type'=>$config['asset_type'],'tta_currency_id'=>$currency_id ])->max('tta_user_id');
                while (true){
                    echo $currency_id.' award:'.$last_id."\r\n";
                    $info = TransferFinancialAwardDetail::where([
                        'tta_user_id'=>['gt',$last_id],
                        'tta_day'=>$today_config['today_start'],
                        'tta_asset_type'=>$config['asset_type'],
                        'tta_currency_id'=>$currency_id])->order('tta_user_id asc')->find();
                    if(empty($info)) {
                        Log::write("理财包奖励任务执行完毕:".date('Y-m-d H:i:s'), 'INFO');
                        break;
                    }
                    //总奖励
                    $sum = TransferFinancialAwardDetail::where(['tta_day'=>$today_config['today_start'], 'tta_asset_type'=>$config['asset_type'],'tta_user_id'=>$info['tta_user_id'], 'tta_currency_id'=>$currency_id])->sum('tta_num');
                    if($sum>=0.000001){
                        //发放奖励
                        TransferFinancialAward::award($info['tta_user_id'],$currency_id,$config['asset_type'],$sum,$today_config['today_start']);
                    }
                    $last_id = $info['tta_user_id'];
                }
            }
        }
    }
}