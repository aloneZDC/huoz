<?php
namespace app\cli\controller;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 发送验证码后台任务
 */
class PhoneTask
{
	public $config=[];
	public $limit_time = 600; //10分钟通知一次
    public $last_time = 0;

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 3;// 设置进程数
        $this->worker->name = 'PhoneTaskWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    protected function doRun($worker_id=0) {
        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write("手机定时任务:".date('Y-m-d H:i:s'), 'INFO');

        $systemList = Db::name('send_system')->where(['s_status'=>1])->select();
        if(empty($systemList)) {
            sleep(5);
            Log::write("手机定时任务:无可用配置", 'INFO');
            return;
        }
        $systemList = array_column($systemList,null,'s_id');

        $runNum = 1;
        while($runNum<100) {
			$runNum++;
			$task_id = 0;$cost = 0;$system_id = 0;
            $third_id = '';

            $task_info = Db::name('phone_task')->where('status=0')->order('id asc')->find();
            if(!$task_info) {
                sleep(rand(1,3));
                echo "not task\r\n";
                continue;
            }

	        try{
				Db::startTrans();
	        	$task_id = $task_info['id'];
	        	$start_time = time();
	        	$result = Db::name('phone_task')->where(['id'=>$task_info['id'],'status'=>0])->update(['status'=>1,'start_time'=>$start_time,'process_id'=>$worker_id]);
                Db::commit();
                if(!$result) {
                    Log::write('手机定时任务更新失败:'.$task_info['id']);
                    continue;
                }

                $task_info['country_code'] = strval($task_info['country_code']);

	        	//添加后240秒还没执行则被舍弃
	        	if($task_info['add_time']<($start_time-240)) throw new Exception('add time out');

	        	if(!empty($task_info['system_id']) && isset($systemList[$task_info['system_id']]) ){
                    $cur_system = $systemList[$task_info['system_id']];
                } else {
                    $cur_system = $systemList[array_rand($systemList)];
                }
	        	$cur_system_type = $cur_system['uc_keyword'];
//	        	if($task_info['country_code']!='86')  $cur_system_type = 's_yun';

	          	$class = null;
	            switch ($cur_system_type) {
	                case 's_bao':
	                    $class = new \message\Smsbao($cur_system);
	                    break;
	                case 's_yun':
	                    $class = new \message\Ucpaas($cur_system);
	                    break;
	                case 's_tong':
	                    $class = new \message\Communication($cur_system);
	                    break;
                    case 's_lan':
                        $class = new \message\Chuanglan($cur_system);
                        break;
                    case 's_qx':
                        $class = new \message\Qianxun($cur_system);
                        break;
	            }
                if(!$class) throw new Exception('手机定时任务失败:不支持改类型');

	            $system_id = $cur_system['s_id'];
	            Db::name('phone_task')->where(['id'=>$task_info['id'],'status'=>1])->update(['system_id'=>$system_id]);

	            $t1 = microtime(true);
            	$send_status = $class->send($task_info['phone'], $task_info['code'], $task_info['type'],$task_info['country_code']);
            	$t2 = microtime(true);
            	$cost = round($t2-$t1,3);

            	$third_id = '';
            	if(is_array($send_status)) {
                    $third_id = isset($send_status['third_id']) ?  $send_status['third_id']  : '';
                    $send_status = isset($send_status['send_status']) ?  $send_status['send_status']  : false;
                }

	        	if($send_status!==true) {
	        		Db::name('phone_task')->where(['id'=>$task_info['id'],'status'=>1])->update(['status'=>3,'stop_time'=>time(),'system_id'=>$system_id,'cost_time'=>$cost,'msg'=>'error1:'.strval($send_status)]);
	        	} else {
	        		$task_info['system_id'] = $system_id;
	        		$task_info['status'] = 2;
	        		$task_info['stop_time'] = time();
	        		$task_info['start_time'] = $start_time;
	        		$task_info['cost_time'] = $cost;
	        		unset($task_info['id'],$task_info['status'],$task_info['msg']);

	        		$task_info['task_id'] = $task_id;
	        		$task_info['third_id'] = $third_id;
	        		Db::name('phone_task_history')->insertGetId($task_info);
	        		Db::name('phone_task')->where(['id'=>$task_id])->delete();
	        	}

//        		if($this->config['phone_task_wait']) sleep($this->config['phone_task_wait']);
	        } catch(Exception $e) {
	            $msg = $e->getMessage();
                Db::name('phone_task')->where(['id'=>$task_id,'status'=>1])->update(['status'=>3,'stop_time'=>time(),'system_id'=>$system_id,'cost_time'=>$cost,'msg'=>'error2:'.strval($msg)]);
                Log::write("手机定时任务错误:".$msg, 'INFO');
	        }
			echo "taskId:".$task_id.":".$runNum." ".$third_id."\r\n";
		}
    }
}
