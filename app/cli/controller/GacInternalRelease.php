<?php
//定时任务-GAC内购定时释放
namespace app\cli\controller;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Exception;
use think\Log;

class GacInternalRelease extends Command
{
    protected function configure(){
        $this->setName('GacInternalRelease')->setDescription('This is a GacInternalRelease');
    }

    protected function execute(Input $input, Output $output){
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

        \think\Request::instance()->module('cli');

        $this->config = model('Config')->byField();
        if(empty($this->config) || empty($this->config['gac_internal_buy_release'])) {
            Log::write("GAC内购释放:获取配置失败");
            return;
        }

        if(empty($this->config['is_gac_internal_buy_release'])) {
            Log::write("GAC内购释放:后台关闭");
            return;
        }

        $currency = Db::name('currency')->field('currency_id,currency_mark,currency_name')->where(['currency_mark'=> 'GAC'])->find();
        if(!$currency) {
            Log::write("GAC内购释放:币种GAC查询失败");
            return;
        }
        $currency_id = $currency['currency_id'];
        if ($this->config['gac_internal_buy_release'] <= 0 || $this->config['gac_internal_buy_release'] > 1) {
        	Log::write("GAC内购释放:内购释放后台设置错误");
        	return;
        }

        $today_begin = strtotime(date('Y-m-d'));
        $last_id = DB::name('currency_gac_internal_buy')->where('add_time>'.$today_begin)->max('third_id');
        while (true) {
        	$flag = false;

        	try{
            	Db::startTrans();
        		$cUser = Db::name('currency_user')->lock(true)->where('cu_id>'.$last_id.' and currency_id='.$currency_id.' and internal_buy>0')->order('cu_id asc')->find();
        		if(!$cUser) {
        			$flag = true;
        			throw new Exception("GAC内购释放任务结束");
        		}

            	$money = keepPoint($cUser['internal_buy'] * $this->config['gac_internal_buy_release'], 6);
            	if($money<0.000001) {
                    $last_id = $cUser['cu_id'];
                    throw new Exception("数值小于0.000001,取消释放内购GAC");
                }

            	//增加释放记录
	            $clf_id = Db::name('currency_gac_internal_buy')->insertGetId([
	                'member_id' => $cUser['member_id'],
	                'num' => $money,
	                'type' => 30,
                    'title' => 'lan_exchange_release_day',
                    'from_num' => $cUser['internal_buy'],
	                'ratio' => ($this->config['gac_internal_buy_release']*100),
	                'add_time' => time(),
	                'third_id' => $cUser['cu_id'],
	            ]);
	            if(!$clf_id) throw new Exception("GAC内购释放记录添加失败");

                //添加账本
                $result = model('AccountBook')->addLog([
                    'member_id' => $cUser['member_id'],
                    'currency_id' => $cUser['currency_id'],
                    'type'=> 30,
                    'content' => 'lan_internal_buy_release_day',
                    'number_type' => 1,
                    'number' => $money,
                    'fee' => 0,
                    'third_id' => $clf_id,
                ]);
                if(!$result) throw new Exception("账本记录添加失败");

	            //资产变动
	            $flag = Db::name('currency_user')->where(['member_id'=>$cUser['member_id'],'currency_id'=>$cUser['currency_id']])->update([
	                'internal_buy' => ['dec',$money],
	                'num'=> ['inc',$money],   
	            ]);
	            if(!$result) throw new Exception("GAC资产变动失败");

	            $last_id = $cUser['cu_id'];

            	Db::commit();
	        } catch (Exception $e) {
	            Db::rollback();
                $msg = $e->getMessage();
                if(!empty($msg)) Log::write("GAC内购释放:".$msg);
	            if($flag) break;
	        }
	    }
    }
}
