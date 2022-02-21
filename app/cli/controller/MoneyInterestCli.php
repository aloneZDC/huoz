<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 14:06
 */

namespace app\cli\controller;


use app\common\model\MoneyInterestDaily;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\common\model\MoneyInterest;
use think\Log;

class MoneyInterestCli extends Command
{
    protected function configure()
    {
        $this->setName('MoneyInterestCli')->setDescription('This is a test');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws \Exception
     */
    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        $this->dealWithMoneyInterest();
        // $this->dealWithEverDayMoneyInterest();
    }

    /**
     * 持币生息每天生成生息记录
     * @throws \think\Exception
     * Created by Red.
     * Date: 2018/12/25 11:11
     */
//    protected function addMoneyInterestDaily()
//    {
//        $count = MoneyInterest::where(['status' => 0])->where("add_time", "<", todayBeginTimestamp())
//            ->where("end_time", ">", todayBeginTimestamp())->count("id");
//        if ($count > 0) {
//            $rows = 1000;
//            $page = ceil($count / $rows);
//            for ($i = 1; $i <= $page; $i++) {
//               $result= MoneyInterestDaily::addMoneyInterestDaily($page, $rows);
//               if($result['code']==SUCCESS){
//                   Log::write('时间：' . date("Y-m-d H:i:s")."  处理生息:".$result['message'], 'INFO');
//               }else{
//                   Log::write('时间：' . date("Y-m-d H:i:s")."  处理生息:".$result['message'], 'INFO');
//               }
//            }
//        }
//        Log::write('时间：' . date("Y-m-d H:i:s")."  处理生息全部完成", 'INFO');
//    }

    /**
     * 处理到期的生息记录(0点处理，则当天0:00-23:59:59也一并处理了)
     * @throws \think\Exception
     * Created by Red.
     * Date: 2019/1/5 16:16
     */
    protected function dealWithMoneyInterest()
    {
        $count = MoneyInterest::where(['status' => 0, 'type' => 1])->where("end_time", "<", todayEndTimestamp())->count("id");
        if ($count > 0) {
            $rows = 1000;
            $page = ceil($count / $rows);
            for ($i = 1; $i <= $page; $i++) {
                $result = MoneyInterestDaily::dealWithMoneyInterest($page, $rows);
                if ($result['code'] == SUCCESS) {
                    Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息:" . $result['message'], 'INFO');
                } else {
                    Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息:" . $result['message'], 'INFO');
                }
            }
        }
        Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息全部完成", 'INFO');
    }

    /*protected function dealWithEverDayMoneyInterest()
    {
        // 还在天数内的记录
        $count = MoneyInterest::where(['status' => 0, 'type' => 2])->where("end_time", ">", todayEndTimestamp())->where('profit_time', '<', todayBeginTimestamp())->count('id');
        if ($count > 0) {
            // TODO: 处理 type为2 的生息记录
            $rows = 1000;
            $page = ceil($count / $rows);
            for ($i = 1; $i <= $page; $i ++) {
                $result = MoneyInterestDaily::dealWithEverDayMoneyInterest($page, $rows);
                if (SUCCESS == $result['code']) {
                    Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息2:" . $result['message'], 'INFO');
                } else {
                    Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息2:" . $result['message'], 'INFO');
                }
            }
        }

        Log::write('时间：' . date("Y-m-d H:i:s") . "  处理生息2全部完成", 'INFO');

    }*/
}