<?php
namespace app\cli\controller;
use app\common\model\Configs;
use app\common\model\EmailDomain;
use app\common\model\EmailSystem;
use app\common\model\Sender;
use message\Qianxun;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 系统监控定时任务
 */
class SystemMonitoring
{
    public $name = "系统监控：";
	public $config=[];
    public $domains = []; //存储邮件域上次发送服务器索引
    public $domains_history = []; //存储【邮件域 - 服务器域名域】上次发送时间

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'SystemMonitoringWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    //异常监控
    protected function doRun($worker_id=0){
        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write($this->name."开始:".date('Y-m-d H:i:s'), 'INFO');
		$task = Db::name('system_monitoring')->where('status',1)->select();
		if($task) {
		    foreach ($task as $t) {
                if(!method_exists($t['class'],$t['method'])) {
                    echo $t['class'].':'.$t['method'].'continue'.PHP_EOL;
                    continue;
                }

		        if($t['class']=='\app\cli\controller\SystemMonitoring') {
                    $class = $this;
                } else {
                    $class = new $t['class']();
                }
                $result = call_user_func_array([$this,$t['method']],[]);
                echo $t['class'].':'.$t['method'].$result.PHP_EOL;

		        $is_notice = false;
		        if($result!==false) {
		            switch ($t['op']) {
                        case 'eq':
                            $is_notice = $result==$t['op_value'];
                            break;
                        case 'egt':
                            $is_notice = $result>=$t['op_value'];
                            break;
                        case 'elt':
                            $is_notice = $result<=$t['op_value'];
                            break;
                    }
                }

		        $time = time();
		        if($is_notice && $time-$t['notice_time']>$t['notice_rate']) {
                    Db::name('phone_task')->insertGetId([
                        'country_code' => '86',
                        'phone' => $t['notice_user'],
                        'code' => $result,
                        'type' => 'register',
                        'add_time' => time(),
                        'status' => 0,
                    ]);

                    Db::name('system_monitoring')->where('id',$t['id'])->update([
                        'notice_time' => $time,
                        'result'=>$result,
                    ]);
                } else {
		            Db::name('system_monitoring')->where('id',$t['id'])->setField('result',$result);
                }
            }
        }
		sleep(60);
    }

    public function QianxunSms() {
        $phone_system = Db::name('send_system')->where('uc_keyword','s_qx')->where('s_status',1)->find();
        if(!$phone_system) return false;

        $cms =new Qianxun($phone_system);
        return $cms->getBalance();
    }

    public function phoneTask() {
        $task_count = Db::name('phone_task')->where('status',0)->count();
        if(!$task_count) return 0;
        return $task_count;
    }

    //提币提醒服务
    public function tibiNotice() {
        $cache_key = 'tibiNotice';
        $id = intval(cache($cache_key));
        $tibi = Db::name('tibi')->where("id>".$id." and transfer_type='1' and status=-1")->order('id desc')->find();
        if($tibi) {
            Log::write("tibiNotice".$tibi['id']);
            $notice_user = Db::name('system_monitoring')->where(['class' => '\app\cli\controller\SystemMonitoring', 'status' => 1])->value('notice_user');
            $email_list = explode(',', $notice_user);//['xiaren338@163.com'];
            $sender = new Sender();
            foreach ($email_list as $email) {
                $sender->addLog(2,$email,0,'tibiNotice',['code'=>'您有新的提币,请查收']);
            }
            cache($cache_key,$tibi['id']);
        }
        return 0;
    }

    /**
     * 配置自动更新
     * {
     *      'table' => 'config', //配置表 带前缀的全称
     *      'key_field' => 'key', //查询字段名称
     *      'key'   => 'key', //查询字段值
     *      'value_field' => 'value', //更新字段名称
     *      'value' => 'value', //更新字段值
     * }
     */
    public function configUpdate() {
        $task = Db::name('system_task_quene')->where(['type'=>'config_update','exec_status'=>0,'exec_time'=>['elt',time()] ])->select();
        if(!$task) return 0;

        foreach ($task as $item) {
            try{
                if(empty($item['param'])) continue;

                $param = json_decode($item['param'],true);
                if(!isset($param['table']) || !isset($param['key']) || !isset($param['key_field']) || !isset($param['value_field']) || !isset($param['value'])) continue;
                //为了安全考虑 只能更新名称包含config的表
                if(strpos($param['table'],'config')===false) continue;

                $res = Db::table($param['table'])->where([
                    $param['key_field'] => $param['key'],
                ])->setField($param['value_field'],$param['value']);

                Db::name('system_task_quene')->where('id',$item['id'])->update([
                    'exec_result' => strval($res),
                    'exec_status' => 1,
                ]);
            } catch (Exception $e) {
                Log::write("配置自动更新错误:".$e->getMessage());
            }
        }
        return 0;
    }
}
