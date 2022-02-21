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
class Roomresulttask
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'RoomResultTask';
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

        Log::write("房间结算:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $where = [
                'rl_status'=>2,
                'rl_is_del'=>1,
                'rl_result_time'=>['elt', time()],
            ];
            $room = RoomList::where($where)->order('rl_room_id', 'asc')->find();
            if (empty($room)) {
                sleep(1);
                continue;
            }

            $levelInfo = RoomLevelSetting::with(['currency'])->where('rls_level', $room['rl_level_id'])->find();
            $r = RoomUsersRecord::lottery($room, $levelInfo, 0);
            if ($r['code'] != SUCCESS) {
                Log::write("房间结算异常:" . $r['message'], 'INFO');
            }
            /*if ($room['rl_is_vip'] == 2) {//是否是VIP房间 1-VIP房间 2-普通房间
                //普通房间结算之后自动解散
                $r = RoomList::disband($room);
                if ($r['code'] != SUCCESS) {
                    Log::write("房间结算，普通房解散异常:" . $r['message'], 'INFO');
                }
            }*/
        }
        Log::write("房间结算:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        //$flag = Db::execute('optimize table ' . config('database.prefix') . 'reg_temp;');
        sleep(1);
    }
}