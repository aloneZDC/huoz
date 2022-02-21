<?php
namespace app\cli\controller;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 发送邮件后台任务
 */
class Email
{
	public $config=[];

    protected function configure(){
        $this->setName('Email')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        $this->doRun();
    }

    protected function doRun(){
        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write("邮件定时任务:".date('Y-m-d H:i:s'), 'INFO');

		$this->config = model('Config')->byField();
        if(empty($this->config)) return;

        $pid_path = WEB_PATH.'/runtime/email_task.pid';
		if(file_exists($pid_path)) {
			$last = Db::name('email_task')->where('status=0')->order('id asc')->find();
			$stop = time() - 300; //如果超過5分鐘,則重新開啟任務
			if(!empty($last) && $last['add_time']<$stop) {
				unlink($pid_path);
				Log::write("重启邮件发送任务", 'INFO');
			}
			return;
		}

		$emailClass = new \message\Email(SMS_NAME,$this->config['EMAIL_HOST'],$this->config['EMAIL_PORT'],$this->config['EMAIL_USERNAME'],$this->config['EMAIL_PASSWORD']);
		touch($pid_path);
        $runNum = 1;
        while($runNum <= 1000) {
        	if(!file_exists($pid_path)) return;

			$runNum++;
			$task_id = 0;
	        try{
				Db::startTrans();
	        	$task_info = Db::name('email_task')->lock(true)->where('status=0')->order('id asc')->find();
	        	if(!$task_info) throw new Exception(''); //暂无任务
	        	

	        	$task_id = $task_info['id'];
	        	$start_time = time();
	          	$result = Db::name('email_task')->where(['id'=>$task_info['id'],'status'=>0])->update(['status'=>1,'start_time'=>$start_time]);
	          	if(!$result) throw new Exception('邮件任务更新失败:'.$task_info['id']);

	          	Db::commit();

	        	//发送邮件
	        	$send_status = $emailClass->send_email($task_info['email'],$task_info['type'],['code'=>$task_info['code']]);
	        	if($send_status!==true) {
	        		$result = Db::name('email_task')->where(['id'=>$task_info['id'],'status'=>1])->update(['status'=>3,'stop_time'=>time(),'msg'=>strval($send_status)]);
	        	} else {
	        		$task_info['status'] = 2;$task_info['stop_time'] = time();$task_info['start_time'] = $start_time;
	        		unset($task_info['id']);

	        		$task_info['task_id'] = $task_id;
	        		Db::name('email_task_history')->insertGetId($task_info);

	        		Db::name('email_task')->where(['id'=>$task_id])->delete();
	        	}

        		if($this->config['email_task_wait']) sleep($this->config['email_task_wait']);  		
	        } catch(Exception $e) {
	            Db::rollback();

	            $msg = $e->getMessage();
	            if(!empty($msg)) {
	            	Log::write("邮件定时任务:".$e->getMessage(), 'INFO');
	            } else {
	            	sleep(1);
	            }
	        }

			echo "taskId:".$task_id."\r\n";
		}
		unlink($pid_path);
    }
}
