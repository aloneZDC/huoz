<?php


namespace app\cli\controller;


use app\common\model\Config;
use app\common\model\DcPrice as DCPrice;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use Workerman\Worker;

class DCPriceLine extends Command
{
    public $name = 'DNC价格定时任务';

    protected function configure()
    {
        $this->setName('DCPriceLine')->setDescription('This is a DCPriceLine');
    }


    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }



//    public function index()
//    {
//        $this->worker = new Worker();
//        $this->worker->count = 1;// 设置进程数
//        $this->worker->name = 'DCPriceLine';
//        $this->worker->onWorkerStart = function ($worker) {
//            while (true) {
//                $this->doRun();
//            }
//        };
//        Worker::runAll();
//    }

    public function doRun(/*$worker_id = 0*/)
    {
//        ini_set("display_errors", 1);
//        ini_set('memory_limit', '-1');
//        config('database.break_reconnect', true);

        Log::write("DC价格定时任务：开始" . date("Y-m-d H:i:s"), "INFO");
        $isInit = Config::get_value('dc_is_init');
        $priceLineCurrency = Config::get_value('price_line_currency');

        if ($isInit == 0) {
            // 没有初始化就进行初始化
            $initPrice = Config::get_value('dc_init_price');
            $initTimestamp = Config::get_value('dc_init_timestamp');
            $res = $this->init($priceLineCurrency, $initPrice, $initTimestamp);
            if (!$res) {
                Log::write("DC定时任务：初始化失败，部分数据未初始化." . date("Y-m-d H:i:s"), "INFO");
            }
            $flag = Config::where('key', 'dc_is_init')->find();
            $flag['value'] = 1;
            if (!$flag->save()) {
                Log::write("DC定时任务：修改配置表失败" . date("Y-m-d H:i:s"));
                die();
            }
            Log::write("DC定时任务：初始化价格表完成" . date("Y-m-d H:i:s")) ;
            exit();
        }
        $todayTimestamp = todayBeginTimestamp();
        $todayPrice = DCPrice::where('currency_id', $priceLineCurrency)->where('add_time', $todayTimestamp)->find();
        if ($todayPrice) {
            Log::write("DC价格定时任务：今日数据已存在退出运行 " . date("Y-m-d H:i:s"), "INFO");
            exit();
        }
        $yesterdayTimestamp = $todayTimestamp - 86400;
        $yesterdayPrice = DCPrice::where('currency_id', $priceLineCurrency)->where('add_time', $yesterdayTimestamp)->find();
        if (empty($yesterdayPrice)) {
            Log::write("DC价格定时任务：不存在昨日价格异常退出!" . date("Y-m-d H:i:s"), "INFO");
            exit();
        }
        // 每日按比例上浮价格
        $price = $this->getNextPrice($yesterdayPrice['price']);
        $res = DCPrice::create([
            'currency_id' => $priceLineCurrency,
            'price' => $price,
            'add_time' => $todayTimestamp
        ]);
        if (empty(!$res)) {
            Log::write("DC价格定时任务：系统错误处理失败!" . date("Y-m-d H:i:s"), "INFO");
            exit();
        }
        Log::write("DC价格定时任务完成" . date("Y-m-d H:i:s"), "INFO");
        sleep(1);
        exit();
    }

    protected function init($currencyId, $initPrice, $initTimestamp)
    {
        if ($initTimestamp == todayBeginTimestamp()) {
            return true;
        }
        $flag = DCPrice::where('currency_id', $currencyId)->where('add_time', $initTimestamp)->find();
        if (!$flag) { // 这一天不存在才添加数据
            $res = DCPrice::create([
                'currency_id' => $currencyId,
                'price' => $initPrice,
                'add_time' => $initTimestamp
            ]);
            if (!$res) {
                return false;
            }
        }

        $nextPrice = $this->getNextPrice($initPrice);
        return $this->init($currencyId, $nextPrice, $initTimestamp + 86400);
    }

    /**
     * 获取随机上浮价格
     * @param double $price
     * @return string
     */
    protected function getNextPrice($price)
    {

        // 比例待配置. 今日价格 = $price + ($price * (rand($minRatio, $upRatio) * 0.01)). 随机上浮N个点
        $upRatio = Config::get_value('dc_up_ratio'); // 最高上浮比例
        $minRatio = Config::get_value('dc_min_ratio'); // 最低上浮比例

        return keepPoint($price + ($price * (mt_rand($minRatio, $upRatio) * 0.01)), 6);
    }
}