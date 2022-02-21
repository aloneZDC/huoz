<?php
//定时任务-GAC福利定时释放
namespace app\cli\controller;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Exception;
use think\Log;

class GacBalanceRelease extends Command
{
    protected function configure(){
        $this->setName('GacBalanceRelease')->setDescription('This is a GacBalanceRelease');
    }

    protected function execute(Input $input, Output $output){
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        \think\Request::instance()->module('cli');

        $this->config = [];

        //获取GAC释放比例小数
        $ratio=Db::name('boss_config')->where(['key'=>'xrp_exchange_gac'])->find();
        if($ratio) $this->config['gac_forzen_release'] = $ratio['value'];

        if(empty($this->config) || empty($this->config['gac_forzen_release'])) {
            Log::write("GAC福利释放:获取配置失败");
            return;
        }

        //获取GAC是否开关
        $swicth=Db::name('boss_config')->where(['key'=>'xrp_exchange_release_gac_switch'])->find();
        if(!$swicth || $swicth['value']!=1) {
            Log::write("GAC福利释放:后台关闭");
            return;
        }

        $currency = Db::name('currency')->field('currency_id,currency_mark,currency_name')->where(['currency_mark'=> 'GAC'])->find();
        if(!$currency) {
            Log::write("GAC福利释放:币种GAC查询失败");
            return;
        }
        $currency_id = $currency['currency_id'];
        if ($this->config['gac_forzen_release'] <= 0 || $this->config['gac_forzen_release'] > 1) {
            Log::write("GAC福利释放:释放后台设置错误");
            return;
        }

        $today_begin = strtotime(date('Y-m-d'));
        $last_id = DB::name('currency_gac_log')->where('add_time>'.$today_begin)->max('third_id');
        while (true) {
            $flag = false;

            try{
                Db::startTrans();
                $cUser = Db::name('currency_user')->lock(true)->where('cu_id>'.$last_id.' and currency_id='.$currency_id.' and remaining_principal>0')->order('cu_id asc')->find();
                if(!$cUser) {
                    $flag = true;
                    throw new Exception("GAC福利释放任务结束");
                }

                $money = keepPoint($cUser['remaining_principal'] * $this->config['gac_forzen_release'], 6);
                if($money<0.000001) {
                    $last_id = $cUser['cu_id'];
                    throw new Exception("数值小于0.000001,取消释放赠送GAC");
                }

                //增加释放记录
                $clf_id = Db::name('currency_gac_log')->insertGetId([
                    'member_id' => $cUser['member_id'],
                    'num' => $money,
                    'type' => 30,
                    'title' => 'lan_exchange_release_day',
                    'from_num' => $cUser['remaining_principal'],
                    'ratio' => ($this->config['gac_forzen_release']*100),
                    'add_time' => time(),
                    'third_id' => $cUser['cu_id'],
                ]);
                if(!$clf_id) throw new Exception("GAC福利释放记录添加失败");

                //添加账本
                $result = model('AccountBook')->addLog([
                    'member_id' => $cUser['member_id'],
                    'currency_id' => $cUser['currency_id'],
                    'type'=> 32,
                    'content' => 'lan_exchange_release_balance_day',
                    'number_type' => 1,
                    'number' => $money,
                    'fee' => 0,
                    'third_id' => $clf_id,
                ]);
                if(!$result) throw new Exception("账本记录添加失败");

                //资产变动
                $flag = Db::name('currency_user')->where(['member_id'=>$cUser['member_id'],'currency_id'=>$cUser['currency_id']])->update([
                    'remaining_principal' => ['dec',$money],
                    'num'=> ['inc',$money],   
                ]);
                if(!$result) throw new Exception("GAC资产变动失败");

                $last_id = $cUser['cu_id'];

                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $msg = $e->getMessage();
                if(!empty($msg)) Log::write("GAC福利释放:".$msg);
                if($flag) break;
            }
        }
    }
}
