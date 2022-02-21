<?php
namespace app\cli\controller;
use app\common\model\Configs;
use app\common\model\EmailDomain;
use app\common\model\EmailSystem;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 发送邮件后台任务
 */
class Emailtask
{
	public $config=[];
    public $domains = []; //存储邮件域上次发送服务器索引
    public $domains_history = []; //存储【邮件域 - 服务器域名域】上次发送时间

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'EmailTaskWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

//    private function testConfig($worker_id)  {
//        ini_set("display_errors", 1);
//        ini_set('memory_limit','-1');
//        config('database.break_reconnect',true);
//
//        $hosts = EmailSystem::get_list();
//        while (true) {
//            foreach (['22@qq.com','aaa@163.com'] as $email) {
//                $cur_host = $this->getConfig($email,$hosts);
//                echo $email.' '.$cur_host['es_id']."\r\n";
//            }
//        }
//    }

    /**
     * 发送邮件，每分钟执行一次
     * @param int $worker_id
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * Create by: Red
     * Date: 2019/6/25 17:40
     */
    protected function doRun($worker_id=0){
        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write("邮件定时任务:".date('Y-m-d H:i:s'), 'INFO');

		//特殊的邮件域指定邮件服务器发送
		$special_domain = EmailDomain::get_list();
		$special_domain = array_column($special_domain,'system_id','domain');

        $hosts = EmailSystem::get_list();
        $total_index = count($hosts);
        $hosts_index = array_column($hosts,null,'es_id');
        if(empty($hosts)) {
            sleep(1);
            return;
        }

		$emailClass = new \message\Email(SMS_NAME);
        $task_id = 0;
        $runNum = 1;
        $cur_index = 0;
        while($runNum<100) {
            $runNum++;
            $task_info = Db::name('email_task')->where('status=0')->order('id asc')->find();
            if(!$task_info) {
                sleep(1);
                echo "not task\r\n";
                continue;
            }

            $cur_host = $this->specialConfig($task_info['email'],$special_domain,$hosts_index);
            if(!$cur_host) $cur_host = $this->getConfig($task_info['email'],$hosts);
//            $count = 0;
//            while (true) {
//                $time = time();
//                $cur_index = $cur_index + 1;
//                if($cur_index>=$total_index) $cur_index = 0;
//
//                if(!isset($hosts[$cur_index]['last_time'])) {
//                    $hosts[$cur_index]['last_time'] = $time;
//                } elseif ($time-$hosts[$cur_index]['last_time']>=$hosts[$cur_index]['es_wait']) {
//                    $hosts[$cur_index]['last_time'] = $time;
//                    $cur_host = $hosts[$cur_index];
//                    break;
//                }
//
//                $count++;
//                //全部查找一遍后 没有查找到则休眠1秒
//                if($count>=$total_index) {
//                    echo "sleep\r\n";
//                    sleep(1);
//                }
//            }

            $task_id = $task_info['id'];
            $start_time = time();
	        try{
				Db::startTrans();
                $task_info['system_id'] = $cur_host['es_id'];
	          	$result = Db::name('email_task')->where(['id'=>$task_info['id'],'status'=>0])->update(['status'=>1,'system_id'=>$task_info['system_id'],'start_time'=>$start_time,'process_id'=>$worker_id]);
	          	if(!$result) throw new Exception('邮件任务更新失败:'.$task_info['id']);
	          	Db::commit();

	        	//发送邮件
	        	$send_status = $emailClass->send_email($cur_host,$task_info['email'],$task_info['type'],$task_info);
	        	if($send_status!==true) {
	        		$result = Db::name('email_task')->where(['id'=>$task_info['id'],'status'=>1])->update(['status'=>3,'stop_time'=>time(),'msg'=>strval($send_status)]);
	        	} else {
	        		$task_info['status'] = 2;$task_info['stop_time'] = time();$task_info['start_time'] = $start_time;
	        		unset($task_info['id'],$task_info['status'],$task_info['msg']);
	        		$task_info['task_id'] = $task_id;
	        		Db::name('email_task_history')->insertGetId($task_info);
	        		Db::name('email_task')->where(['id'=>$task_id])->delete();
	        	}
	        } catch(Exception $e) {
	            @Db::rollback();

                $msg = $e->getMessage();
                $result = Db::name('email_task')->where(['id'=>$task_id,'status'=>1])->update(['status'=>3,'stop_time'=>time(),'msg'=>'error:'.$msg]);
                Log::write("邮件定时任务:".$msg, 'INFO');
	        }
			echo "taskId:".$task_id.":".$runNum.':'.$cur_host['es_id']."\r\n";
		}
    }

    private function specialConfig($email,$special_domain,$hosts_index) {
        $domain = explode('@',$email);
        if(count($domain)!=2) return null;

        if(!isset($special_domain[$domain[1]]) || $special_domain[$domain[1]]==0) return null;
        return isset($hosts_index[$special_domain[$domain[1]]]) ? $hosts_index[$special_domain[$domain[1]]] : null;
    }

    private function getConfig($email,$hosts) {
        $email_domain = $this->getDomain($email);

        if(!isset($this->domains[$email_domain])) $this->domains[$email_domain] = -1;
        if(!isset($this->domains_history[$email_domain])) $this->domains_history[$email_domain] = [];

        $cur_index = $this->domains[$email_domain];
        if(!isset($hosts[$cur_index])) $cur_index = -1;

        $total_index = count($hosts);
        $cur_host = null;
        $count = 0;
        while (true) {
            $time = time();
            $cur_index = $cur_index + 1;
            if($cur_index>=$total_index) $cur_index = 0;

            $server_domain = $this->getDomain($hosts[$cur_index]['es_user']);
            if(!isset($this->domains_history[$email_domain][$server_domain])) {
                $this->domains_history[$email_domain][$server_domain] = 0;
            }

            if($time-$this->domains_history[$email_domain][$server_domain]>=$hosts[$cur_index]['es_wait']) {
                $cur_host = $hosts[$cur_index];

                $this->domains[$email_domain] = $cur_index;
                $this->domains_history[$email_domain][$server_domain] = $time;
                break;
            }

            $count++;
            //全部查找一遍后 没有查找到则休眠1秒
            if($count>=$total_index) {
                echo "sleep\r\n";
                sleep(1);
            }
        }
        return $cur_host;
    }

    private function getDomain($email) {
        $email_arr = explode('@',$email);
        if(count($email_arr)!=2) return '';
        return $email_arr[1];
    }
}
