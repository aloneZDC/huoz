<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;
use think\console\Command;
use app\common\model\ChiaMiningPay;
use Workerman\Worker;
use app\common\model\ChiaMiningReward;

class ChiaHandle {
	public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'ChiaHandle';
        $this->worker->onWorkerStart = function($worker) {
        	while (true){
                $this->doRun($worker->id);
                sleep(2);
            }
        };
        Worker::runAll();
    }

	public function doRun() {
		Log::write('ChiaHandle start');
		$res = ChiaMiningPay::where(['tnum' => ['>=', 10]])->order('add_time ASC')->group('member_id')->limit(200)->column('member_id');
		if (empty($res)) return;
		try {
			foreach($res as $key => $value) {
				$pay_numbers = ChiaMiningPay::where(['member_id' => $value, 'is_give' => 0, 'tnum' => ['>=', 10]])->field('tnum,mining_code,real_pay_num,real_pay_currency_id,product_id,start_day,treaty_day,id')->select();
				if (empty($pay_numbers)) {
					continue;
				}
				//一次性每买10T, 就送 1 T
				//前200名账户，是指不同账户的前200名！
				//同一个账户，可以反复购买多次，只要满足一次性买10T,就送1T
				foreach ($pay_numbers as $k => $v) {
					$tnum = intval($v['tnum'] / 10);
					Db::startTrans();
					//创建赠送记录
	                ChiaMiningReward::insert([
	                    'member_id' => $value,
	                    'chia_mining_pay_id' => $v['id'],
	                    'real_pay_num' => 0,
	                    'real_pay_currency_id' => $v['real_pay_currency_id'],
	                    'tnum' => $tnum,
	                    'product_id' => $v['product_id'],
	                    'start_day' => $v['start_day'], // 产币时间
	                    'treaty_day' => $v['treaty_day'], // 合约时间
	                    'add_time' => time(),
	                    'mining_code' => $v['mining_code']
	                ]);

	                //更新是否已赠送
	                ChiaMiningPay::where('id', $v['id'])->update(['is_give' => 1]);
	                Db::commit();
				}
			}	
		} catch (\Exception $e) {
			Db::rollback();
			Log::write($e->getMessage());
		}
		
	}
}