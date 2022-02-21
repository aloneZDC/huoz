<?php
namespace app\cli\controller;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 新增收益更新
 */
class BounsWeekTask
{
	public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'BounsWeekTask';
        $this->worker->onWorkerStart = function($worker) {
        	$this->doRun($worker->id);
        };
        Worker::runAll();
    }

    public function everyDay($same_day) {
        $count = Db::name('boss_bouns_week')->where(['bonus_time'=>$same_day['yestoday_start']])->count();
        if($count>0) return true;

        $flag = Db::execute('truncate table '.config('database.prefix').'boss_bouns_week_cur');
        if($flag===false) return false;

        $flag = Db::execute('insert into '.config('database.prefix').'boss_bouns_week_cur select member_id, 0, 0, 0,'.$same_day['yestoday_start'].' from '.config('database.prefix').'boss_plan where status=3');
        if($flag===false) return false;


        //更新自己的
        $flag = Db::execute('update  '.config('database.prefix').'boss_bouns_week_cur  a set a.num = ( select  sum(pay_number) from '.config('database.prefix').'boss_plan_buy b where b.member_id= a.member_id and add_time BETWEEN '.$same_day['yestoday_start'].' and '.$same_day['yestoday_stop'].'  group by b.member_id)');
        if($flag===false) return false;


        //更新上级的
        $flag = Db::execute("CREATE TEMPORARY TABLE if not exists yang_boss_bouns_week_tmp (member_id int(10) unsigned NOT NULL DEFAULT '0',child_num double(20,6));");
        if($flag===false) return false;

        $flag = Db::execute("truncate table yang_boss_bouns_week_tmp;");
        if($flag===false) return false;

        $flag = Db::execute('insert into yang_boss_bouns_week_tmp (member_id, child_num)
select a.member_id, sum(b.pay_number)
from yang_boss_bouns_week_cur a, yang_boss_plan_buy b, yang_member_bind c
where a.member_id= c.member_id and  b.member_id=c.child_id  and b.add_time BETWEEN '.$same_day['yestoday_start'].' and '.$same_day['yestoday_stop'].'
group by a.member_id;');
        if($flag===false) return false;

        $flag = Db::execute('update yang_boss_bouns_week_cur a, yang_boss_bouns_week_tmp b set a.child_num =  b.child_num where a.member_id= b.member_id');
        if($flag===false) return false;


        // $last_id = 0;
        // while (true) {
        //     $weekInfo = Db::name('boss_bouns_week_cur')->where(['member_id'=>['gt',$last_id],'num'=>['gt',0]])->order('member_id asc')->find();
        //     if(!$weekInfo) {
        //         Log::write(date('Y-m-d',$same_day['yestoday_start'])."新增业绩已完成");
        //         break;
        //     }

        //     $flag = Db::execute('update '.config('database.prefix').'boss_bouns_week_cur set child_num=child_num+'.$weekInfo['num'].' where member_id in(select member_id from '.config('database.prefix').'member_bind where child_id='.$weekInfo['member_id'].')');

        //     $last_id = $weekInfo['member_id'];
        //     echo "BounsWeekTask:".$last_id."\r\n";
        // }

        //复制到
        $flag = Db::execute('insert into '.config('database.prefix').'boss_bouns_week(member_id,num,child_num,bonus_time,add_time) select member_id,num,child_num,bonus_time,now() from '.config('database.prefix').'boss_bouns_week_cur where num>0 or child_num>0');
        if($flag===false) return false;
        
        return true;
    }

    protected function doRun($worker_id=0){
        Log::write("function close:".date('Y-m-d H:i:s'));
        exit;

        ini_set("display_errors", 1);
		ini_set('memory_limit','-1');
		config('database.break_reconnect',true);

		Log::write("新增收益定时任务:".date('Y-m-d H:i:s'));

        $runNum = 0;
        while($runNum<1000) {
			$runNum++;
			$task_id = 0;
	        try {
				Db::startTrans();
	        	$task_info = Db::name('boss_level_task')->lock(true)->order('id asc')->find();
	        	if(!$task_info) throw new Exception('暂无任务'); //暂无任务

	        	$task_id = $task_info['id'];
	          	$result = Db::name('boss_level_task')->where(['id'=>$task_info['id']])->delete();
	          	if(!$result) throw new Exception('删除失败'.$task_info['id']);

                $boss_plan_info = $this->getMemberInfo($task_info['member_id']);
                if(!$boss_plan_info) throw new Exception('用户信息不存在');

	          	$week_start_end = $this->same_day($task_info['add_time']);
	          	$flag = $this->insertBounsWeekLog($task_info['member_id'],$task_info['money'],$week_start_end,true);
	          	if($flag===false) throw new Exception('新增自身收益出错'.$task_info['id']);

	          	$member_pid = $boss_plan_info['pid'];
	          	while (true) {
                    if($member_pid==0) break;

	          		$level = 1;
	          		$pidInfo = $this->getMemberInfo($member_pid);
	          		if($pidInfo===false) { //老板计划没有该用户信息,出错返回
	          			throw new Exception('新增收益定时任务:获取上级'.$level.'出错'.$task_info['id']);
	          		} else {
	          			$flag = $this->insertBounsWeekLog($pidInfo['member_id'],$task_info['money'],$week_start_end);
                        if($flag===false) throw new Exception('新增收益定时任务:新增上级'.$level.'收益出错'.$task_info['id']);

                        if($pidInfo['pid']==0) {
                            break;
                        }

                        $member_pid = $pidInfo['pid'];
	          		}
	          		$level++;
	          	}

                $flag = Db::name('boss_level_task_his')->insertGetId($task_info);
                if($flag===false) throw new Exception('新增自身收益插入历史出错'.$task_info['id']);

	          	Db::commit();	
	        } catch(Exception $e) {
	            @Db::rollback();
	            $msg = $e->getMessage();
	            Log::write("新增收益定时任务:".$msg);

	            if($msg=='暂无任务') sleep(2);
	        }
			echo "taskId:".$task_id.":".$runNum."\r\n";
		}
		exit;
    }

    protected function insertBounsWeekLog($member_id,$num,$week_start_end,$is_self=false) {
    	try{
    		$logInfo = Db::name('boss_bouns_week')->lock(true)->where(['member_id'=>$member_id,'bonus_time'=>$week_start_end['week_start']])->find();
    		$flag = false;
    		if(!$logInfo) {
    			$data = [
    				'member_id' => $member_id,
    				'add_time' => time(),
    				'bonus_time' => $week_start_end['week_start'],
    			];
    			if($is_self) {
    				$data['num'] = $num;
    			} else {
    				$data['child_num'] = $num;
    			}
    			$flag = Db::name('boss_bouns_week')->insertGetId($data);
    		} else {
    			if($is_self) {
    				$flag = Db::name('boss_bouns_week')->where(['id'=>$logInfo['id']])->setInc('num',$num);
    			} else {
    				$flag = Db::name('boss_bouns_week')->where(['id'=>$logInfo['id']])->setInc('child_num',$num);
    			}	
    		}

    		if(!$flag) return false;

    		return true;
    	} catch(Exception $e){
    		return false;
    	}
    }

    protected function getMemberInfo($member_id) {
    	try{
    		$info = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->find();
            if(!$info) return false;

    		return $info;
    	} catch(Exception $e){
    		return false;
    	}
    }

    public function same_day($today_unix){
    	// $today = date("Y-m-d",$today_unix);
     //    $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期 //当前日期
     //    $w=date('w',$today_unix); //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
     //    $week_start=strtotime("$today -".($w ? $w - $first : 6).' days'); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
     //    $week_end= $week_start + 86400*7 - 1; //本周结束日期
    //return ['week_start'=>$week_start,'week_end'=>$week_end];

        //改成每天跑一次
        $today = date('Y-m-d',$today_unix);
        $today_unix = strtotime($today);
        return ['week_start'=>$today_unix];
    }
}
