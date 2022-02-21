<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 14:06
 */

namespace app\cli\controller;


use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Log;

class Statistics extends Command
{
    protected function configure()
    {
        $this->setName('Statistics')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        $this->runGo();
    }

    /**
     * 每日统计数据（一天运行一次,运行时间为凌晨00:01）
     * Created by Red.
     * Date: 2018/12/14 17:07
     */
    protected function runGo()
    {
        $currencyList = Db::name("currency")->field("currency_id")->select();
        foreach ($currencyList as $value) {
            //可用的
            $numSum = Db::name("currency_user")->where(['currency_id' => $value['currency_id']])->sum("num");
            if ($numSum > 0) {
                $this->addToal($value['currency_id'], $numSum, "num");
            }
            //冻结的
            $forzen_numSum = Db::name("currency_user")->where(['currency_id' => $value['currency_id']])->sum("forzen_num");
            if ($forzen_numSum > 0) {
                $this->addToal($value['currency_id'], $forzen_numSum, "forzen_num");
            }

            //赠送的
            $num_awardSum = Db::name("currency_user")->where(['currency_id' => $value['currency_id']])->sum("num_award");
            if ($num_awardSum > 0) {
                $this->addToal($value['currency_id'], $num_awardSum, "num_award");
            }
        }
        Log::write("每日总统计数据完成，完成时间：".date("Y-m-d H:i:s",time()));
    }

    /**
     * @param $currency_id
     * @param $total
     * @param $wet_type
     * @param null $time            时间戳
     * @return bool
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * Created by Red.
     * Date: 2019/1/18 11:20
     */
    protected function addToal($currency_id, $total, $wet_type, $time = null)
    {
        if (!empty($currency_id) && $total > 0 && !empty($wet_type)) {
            //当前的时间是统计成昨天的,需要要减86400
            if (!empty($time)) {
                $date = date("Y-m-d", ($time-86400));
            } else {
                $date = date("Y-m-d", (time()-86400));
            }
            $find = Db::name("wallet_everyday_total")->where(['wet_currency_id' => $currency_id, 'wet_time' => $date, 'wet_type' => $wet_type])->find();

            if (!empty($find)) {
                //一天统计一次，这里不能叠加
                $find['wet_total']= $total;
                $result = Db::name("wallet_everyday_total")->where(['wet_id' => $find['wet_id']])->update($find);
                return $result ? true : false;
            } else {
                $object['wet_currency_id'] = $currency_id;
                $object['wet_time'] = $date;
                $object['wet_total'] = $total;
                $object['wet_type'] = $wet_type;
                $save = Db::name("wallet_everyday_total")->insertGetId($object);
                return $save ? true : false;
            }
        }
        return false;
    }
}