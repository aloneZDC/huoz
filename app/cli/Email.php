<?php
namespace Cli\Controller;
use Think\Controller;
use Think\Exception;
use Think\Log;
use think\console\Command;



/**
 * 任务类，用来执行任务
 * Class TaskController
 * @package Api\Controller
 */
class Email extends Command
{
	public $config=[];

    protected function configure(){
        $this->setName('Email')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        $this->index();
    }

	public function index(){
		ini_set("display_errors", 1);
		ini_set('memory_limit','-1');

		Log::write("邮件定时任务:".date('Y-m-d H:i:s'), 'INFO');

		$list = db("Config")->select();
        foreach ($list as $k => $v) {
            $list[$v['key']] = $v['value'];
        }
        $this->config = $list;
        if(empty($this->config)) return;

		$pid_path = ROOT_PATH.'/Runtime/email_task.pid';
		if(file_exists($pid_path)) {
			$last = db('email_task')->where('status=0')->order('id asc')->find();
			$stop = time() - 300; //如果超過5分鐘,則重新開啟任務
			if(!empty($last) && $last['add_time']<$stop) {
				unlink($pid_path);
				Log::write("重启邮件发送任务", 'INFO');
			}
			return;
		}

		$emailClass = new \message\Email(Email_NAME,$this->config['EMAIL_HOST'],$this->config['EMAIL_PORT'],$this->config['EMAIL_USERNAME'],$this->config['EMAIL_PASSWORD']);

		touch($pid_path);
        $runNum = 1;
        while($runNum <= 100) {
        	if(!file_exists($pid_path)) return;

			$runNum++;
			$task_id = 0;
	        try{
				db()->startTrans();
	        	$reg_task = db('email_task');
	        	$reg_info = $reg_task->lock(true)->where('status=0')->order('id asc')->find();
	        	if(!$reg_info) {
	        		db()->rollback();
	        		sleep(1);
	                continue;
	        	}

	        	$task_id = $reg_info['id'];
	        	$start_time = time();
	          	$result = $reg_task->where(['id'=>$reg_info['id'],'status'=>0])->update(['status'=>1,'start_time'=>$start_time]);
	          	if(!$result){
	          		Log::write("邮件任务更新失败:".$reg_info['id'], 'INFO');
	          		db()->rollback();
	          		sleep(1);
	          		continue;
	          	}
	          	db()->commit();

	        	$task_id = $reg_info['id'];
	        	//发送邮件
	        	$send_status = $emailClass->send_email($reg_info['email'],$reg_info['type'],['code'=>$reg_info['code']]);
	        	if($send_status!==true) {
	        		$result = $reg_task->where(['id'=>$reg_info['id'],'status'=>1])->update(['status'=>3,'stop_time'=>time(),'msg'=>strval($send_status)]);
	        	} else {
	        		$reg_info['status'] = 2;$reg_info['stop_time'] = time();$reg_info['start_time'] = $start_time;
	        		unset($reg_info['id']);

	        		$reg_info['task_id'] = $task_id;
	        		db('email_task_history')->insert($reg_info);
	        		$reg_task->where(['id'=>$task_id])->delete();
	        	}
        		sleep($this->config['email_task_wait']);
	        } catch(Exception $e) {
	            db()->rollback();
	        }
			echo "taskId:".$task_id."\r\n";
		}
		unlink($pid_path);
	}

	//
	public function add() {
		$type_list = ['register','findpwd','bindemail',''];

		for ($i=0; $i < 30; $i++) {
			db('email_task')->insert([
				'email' => '110@qq.com',
				'code' => rand(100000,999999),
				'type' => $type_list[array_rand($type_list)],
				'add_time' => time(),
			]);
		}
	}
}
