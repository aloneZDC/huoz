<?php
namespace app\cli\controller;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 用户上下级关系表
 */
class MemberBindTask
{
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'MemberBindTaskWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true) {
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    protected function doRun(){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        Log::write("用户关系层级:定时任务:".date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while($runNum<200) {
            $runNum++;

            $info = Db::name('member_bind_task')->order('id asc')->find();
            if(empty($info)) {
                sleep(1);
                continue;
            }

            try{
                Db::startTrans();

                //获取自身的上级
                $pidInfo = Db::name('member')->field('pid')->where(['member_id'=>$info['member_id']])->find();
                if(!$pidInfo) throw new Exception("获取失败");

                $insert_data =  $info;
                unset($insert_data['id']);
                $flag = Db::name('member_bind_task_his')->insertGetId($insert_data);
                if($flag===false) throw new Exception("更新失败");

                $flag = Db::name('member_bind_task')->where(['id'=>$info['id']])->delete();
                if($flag===false) throw new Exception("更新失败");

                $pid= $pidInfo['pid'];
                if($pid==0) {
                    Db::commit();
                    continue;
                }

                $log_id = Db::name('member_bind')->insertGetId([
                    'member_id' => $pid,
                    'child_id' => $info['member_id'],
                    'level' => 1,
                ]);
                if($log_id===false) throw new Exception("插入失败:".$info['member_id']);

                $flag = Db::execute('insert into `'.config('database.prefix').'member_bind`(member_id, child_id, level, child_level) select member_id,'.$info['member_id'].',level+1,0 from `'.config('database.prefix').'member_bind`  where child_id='.$pid);
                if($flag===false) throw new Exception("获取上级层级结构更新失败:".$info['member_id']);

                Db::commit();
            } catch(Exception $e){
                @Db::rollback();

                Log::write("用户关系层级:".$e->getMessage(), 'INFO');
                sleep(1);
            }
        }
        Log::write("用户关系层级:定时任务结束:".date('Y-m-d H:i:s'), 'INFO');

        $flag = Db::execute('optimize table '.config('database.prefix').'member_bind_task;');
    }
}
