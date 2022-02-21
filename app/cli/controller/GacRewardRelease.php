<?php
//定时任务-GAC赠送定时释放
namespace app\cli\controller;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Exception;
use think\Log;

class GacRewardRelease extends Command
{
    protected function configure(){
        $this->setName('GacRewardRelease')->setDescription('This is a AwardRelease');
    }

    protected function execute(Input $input, Output $output){
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

        \think\Request::instance()->module('cli');

        $this->config = model('Config')->byField();
        if(empty($this->config) || empty($this->config['gac_reward_forzen_release'])) {
            Log::write("GAC赠送释放:获取配置失败");
            return;
        }

        if(empty($this->config['is_gac_reward__release'])) {
            Log::write("GAC赠送释放:后台关闭");
            return;
        }

        $currency = Db::name('currency')->field('currency_id,currency_mark,currency_name')->where(['currency_mark'=> 'GAC'])->find();
        if(!$currency) {
            Log::write("GAC赠送释放:币种GAC查询失败");
            return;
        }
        $currency_id = $currency['currency_id'];
        if ($this->config['gac_reward_forzen_release'] <= 0 || $this->config['gac_reward_forzen_release'] > 1) {
        	Log::write("GAC赠送释放:赠送释放后台设置错误");
        	return;
        }

        $today_begin = strtotime(date('Y-m-d'));
        $last_id = DB::name('currency_gac_reward_forzen')->where('add_time>'.$today_begin)->max('third_id');
        while (true) {
        	$flag = false;

        	try{
            	Db::startTrans();
        		$cUser = Db::name('currency_user')->lock(true)->where('cu_id>'.$last_id.' and currency_id='.$currency_id.' and num_award>0')->order('cu_id asc')->find();
        		if(!$cUser) {
        			$flag = true;
        			throw new Exception("GAC赠送释放任务结束");
        		}

            	$money = keepPoint($cUser['num_award'] * $this->config['gac_reward_forzen_release'], 6);
            	if($money<0.000001) {
                    $last_id = $cUser['cu_id'];
                    throw new Exception("数值小于0.000001,取消释放赠送GAC");
                }

            	//增加释放记录
	            $clf_id = Db::name('currency_gac_reward_forzen')->insertGetId([
	                'member_id' => $cUser['member_id'],
	                'num' => $money,
	                'type' => 30,
                    'title' => 'lan_exchange_release_day',
                    'from_num' => $cUser['num_award'],
	                'ratio' => ($this->config['gac_reward_forzen_release']*100),
	                'add_time' => time(),
	                'third_id' => $cUser['cu_id'],
	            ]);
	            if(!$clf_id) throw new Exception("GAC赠送释放记录添加失败");

                //添加账本
                $result = model('AccountBook')->addLog([
                    'member_id' => $cUser['member_id'],
                    'currency_id' => $cUser['currency_id'],
                    'type'=> 27,
                    'content' => 'lan_exchange_release_reward_day',
                    'number_type' => 1,
                    'number' => $money,
                    'fee' => 0,
                    'third_id' => $clf_id,
                ]);
                if(!$result) throw new Exception("账本记录添加失败");

	            //资产变动
	            $flag = Db::name('currency_user')->where(['member_id'=>$cUser['member_id'],'currency_id'=>$cUser['currency_id']])->update([
	                'num_award' => ['dec',$money],
	                'num'=> ['inc',$money],   
	            ]);
	            if(!$result) throw new Exception("GAC资产变动失败");

	            $last_id = $cUser['cu_id'];

            	Db::commit();
	        } catch (Exception $e) {
	            Db::rollback();
                $msg = $e->getMessage();
                if(!empty($msg)) Log::write("GAC赠送释放:".$msg);
	            if($flag) break;
	        }
	    }
    }
}
