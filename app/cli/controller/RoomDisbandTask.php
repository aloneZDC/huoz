<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/20
 * Time: 19:23
 */

namespace app\cli\controller;
use app\common\model\RegTemp;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomUsersRecord;
use app\common\model\UsersRelationship;
use think\Db;
use think\Exception;
use Workerman\Worker;
use think\Log;
class Roomdisbandtask
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'RoomDisbandTask';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 房间自动结算，每分钟执行一次
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/6/25 14:54
     */
    protected function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        Log::write("房间解散:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $where = [
                //'rl_status'=>2,
                'rl_is_vip'=>1,//暂时只做VIP的解散
                'rl_disband_time'=>['elt', time()],
            ];
            $room = RoomList::where($where)->order('rl_room_id', 'asc')->find();
            if (empty($room)) {
                sleep(1);
                continue;
            }

            $r = RoomList::disband($room);
            if ($r['code'] != SUCCESS) {
                Log::write("房间解散异常:" . $r['message'], 'INFO');
            }
        }
        Log::write("房间解散:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        //$flag = Db::execute('optimize table ' . config('database.prefix') . 'reg_temp;');
        sleep(1);
    }
}