<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/20
 * Time: 19:23
 */

namespace app\cli\controller;
use app\common\model\GameConfig;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomList;
use app\common\model\RoomRobotList;
use app\common\model\RoomSeatList;
use app\common\model\RoomUsersList;
use app\common\model\RoomUsersRecord;
use think\Db;
use think\Exception;
use Workerman\Worker;
use think\Log;
class Roomrobottask
{
    public $config = [];

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'RoomRobotTask';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 房间自动加入机器人，每分钟执行一次
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

        Log::write("房间机器人:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;
            $where = [
                'rl_is_vip'=>2,//是否是VIP房间 1-VIP房间 2-普通房间
                'rl_status'=>1,
                //'rl_current_num'=>['lt', 'rl_open_num'],
                'rl_robot_num'=>['lt', 9],
                'rl_robot_time'=>['elt', time()],
            ];
            $room = RoomList::where($where)->order('rl_room_id', 'asc')->find();
            if (empty($room)) {
                sleep(1);
                continue;
            }
            if ($room['rl_robot_num'] >= $room['rl_open_num'] - 1) {
                Log::write("房间机器人加入失败，机器人数量已达上限", 'INFO');
                sleep(1);
                continue;
            }

            $seatList = RoomSeatList::where(['rsl_room_id'=>$room['rl_room_id'], 'rsl_member_id'=>['gt',0]])->order('rsl_seat_id', 'asc')->select();
            if (count($seatList) >= $room['rl_open_num']) {
                Log::write("房间机器人加入失败，房间人数已满", 'INFO');
                sleep(1);
                continue;
            }

            $robotNum = $room['rl_robot_num'];
            $userNum = $room['rl_ready_num'] - $robotNum;
            /*foreach ($seatList as $key => $value) {
                if ($value['rsl_member_id'] <= 0) {
                    continue;
                }
                $userFind = RoomUsersList::where(['rul_room_id' => $room['rl_room_id'], 'rul_member_id' => $value['rsl_member_id']])->find();
                if (!$userFind) {
                    continue;
                }
                if ($userFind['rul_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    $robotNum++;
                } else {
                    $userNum++;
                }
            }*/
            if ($userNum > 0) {//加入机器人数量，最多开奖人数-1个机器人，如10人开奖，只能加入9个机器人，必须等一个真实玩家才能开奖
                $num = $room['rl_open_num'] - $userNum - $robotNum;
            }
            else {
                $num = $room['rl_open_num'] - 1 - $robotNum;
            }
            if ($num > 0) {
                $robotList = RoomRobotList::select();
                for ($i = 1; $i <= $num; $i++) {
                    $robotKey = array_rand($robotList);
                    $robot = $robotList[$robotKey];
                    $userFind = RoomUsersList::where(['rul_room_id'=>$room['rl_room_id'], 'rul_member_id'=>$robot['rrl_id']])->find();
                    while ($userFind) {
                        unset($robotList[$robotKey]);
                        $robotKey = array_rand($robotList);
                        $robot = $robotList[$robotKey];
                        $userFind = RoomUsersList::where(['rul_room_id'=>$room['rl_room_id'], 'rul_member_id'=>$robot['rrl_id']])->find();
                    }
                    $r = RoomUsersList::join_room($room, $robot['rrl_id'], true);
                    if ($r['code'] != SUCCESS) {
                        Log::write("房间机器人加入异常:" . $r['message'], 'INFO');
                        break;
                    }
                    else {
                        unset($robotList[$robotKey]);
                    }
                }
                //更新房间数据
                $update = [
                    'rl_real_robot_time' => time(),
                ];
                $roomSave = RoomList::where(['rl_room_id'=>$room['rl_room_id']])->update($update);
                if(!$roomSave) {
                    //throw new Exception("更新失败-1");
                    Log::write("房间机器人:更新失败-1", 'INFO');
                }
            }
        }
        Log::write("房间机器人:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        //$flag = Db::execute('optimize table ' . config('database.prefix') . 'reg_temp;');
        sleep(1);
    }
}