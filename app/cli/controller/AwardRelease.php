<?php
//定时任务
namespace app\cli\controller;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Exception;
use think\Log;

class AwardRelease extends Command
{
    protected function configure(){
        $this->setName('AwardRelease')->setDescription('This is a AwardRelease');
    }

    protected function execute(Input $input, Output $output){
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

        \think\Request::instance()->module('cli');

        $this->config = model('Config')->byField();
        if(empty($this->config) || empty($this->config['award_release_xrp_num'])) {
            Log::write("赠送释放:获取配置失败");
            return;
        }
        $this->config['award_release_xrp_num'] = floatval($this->config['award_release_xrp_num']);

        $price = model('AwardRelease')->getPrice();
        if(empty($price)) {
            Log::write("赠送释放:转换价格查询失败");
            return;
        }

        $currency = Db::name('currency')->field('currency_id,release_switch_award,award_release_rate')->where(['currency_mark'=> 'XRP'])->find();
        if(!$currency) {
            Log::write("赠送释放:币种XRP查询失败");
            return;
        }
        $currency_id = $currency['currency_id'];

        //关闭了释放通道
        if($currency['release_switch_award']!=1) {
        	Log::write("赠送释放:赠送释放后台设置关闭");
        	return;
        }

        if ($currency['award_release_rate'] <= 0 && $currency['award_release_rate'] > 100) {
        	Log::write("赠送释放:赠送释放后台设置错误");
        	return;
        }

        $today_begin = strtotime(date('Y-m-d'));
        $last_id = DB::name('currency_award_freed')->where('time>'.$today_begin)->max('cu_id');
        while (true) {
        	$flag = false;

        	try{
            	Db::startTrans();
        		$cUser = Db::name('currency_user')->lock(true)->where('cu_id>'.$last_id.' and currency_id='.$currency_id.' and num_award>0')->order('cu_id asc')->find();
        		if(!$cUser) {
        			$flag = true;
        			throw new Exception("任务结束");
        		}

                // //计算所有资产是否大于1BTC
                // $total = model('AwardRelease')->toBtc($cUser['member_id']);        
                // //如果总量不足1BTC不能释放
                // if($total<$this->config['award_release_btc_num']) {
                //     $last_id = $cUser['cu_id'];
                //     throw new Exception('');
                // }

                //所有资产大于5000XRP才能释放
                $total = model('AwardRelease')->toXrp($cUser['member_id']);        
                if($total<$this->config['award_release_xrp_num']) {
                    $last_id = $cUser['cu_id'];
                    throw new Exception('');
                }

            	$money = keepPoint($cUser['num_award'] * $currency['award_release_rate'] / 100, 6);
            	if($money<0.000001) {
                    $last_id = $cUser['cu_id'];
                    throw new Exception("数值小于0.000001,取消释放");
                }

            	//增加释放记录
	            $clf_id = Db::name('currency_award_freed')->insertGetId([
	                'member_id' => $cUser['member_id'],
	                'currency_id' => $cUser['currency_id'],
	                'money' => $money,
	                'total' => $cUser['num_award'],
	                'rate' => $currency['award_release_rate'],
	                'time' => time(),
	                'cu_id' => $cUser['cu_id'],
	            ]);
	            if(!$clf_id) throw new Exception("释放记录添加失败");

	            //添加账本
	            $result = model('AccountBook')->addLog([
	                'member_id' => $cUser['member_id'],
	                'currency_id' => $cUser['currency_id'],
	                'type'=> 21,
	                'content' => 'lan_award_release',
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
	            if(!$result) throw new Exception("资产变动失败");

	            $last_id = $cUser['cu_id'];

            	Db::commit();
	        } catch (Exception $e) {
	            Db::rollback();
                $msg = $e->getMessage();
                if(!empty($msg)) Log::write("赠送释放:".$msg);
	            if($flag) break;
	        }
	    }
    }
}
