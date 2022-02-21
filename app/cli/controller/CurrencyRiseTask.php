<?php
/**
 * 处理币种每天上涨的需求
 */
namespace app\cli\controller;

use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\DcPrice as DCPrice;
use think\Db;
use think\Exception;
use think\Log;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Request;

class CurrencyRiseTask extends Command
{
    public $config = [];

    protected function configure()
    {
        $this->setName('CurrencyRiseTask')->setDescription('This is a CurrencyRiseTask');
    }

    protected function execute(Input $input, Output $output)
    {
        Request::instance()->module('cli');

        $this->doRun();
    }

    /**
     * 处理币种每天上涨
     */
    public function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $today = date('Y-m-d');
        $currency_list = Currency::field('currency_id,currency_name')->select();
        foreach ($currency_list as $currency) {
            $task = Db::name('currency_rise_task')->where(['currency_id'=>$currency['currency_id'],'last_day'=>['egt',$today]])->order('last_day asc')->find();
            if(empty($task)) continue;

            //当前价格
            $cur_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
            $inc_price = $task['max_price'] - $cur_price;
            if($inc_price<=0) continue;

            if($today==$task['last_day']){
                //如果是最后一天 直接涨到最高价格
                $price = $task['max_price'];
            } else {
                //剩余天数
                $inc_day = (strtotime($task['last_day']) - strtotime($today))/86400;
                $price = keepPoint($cur_price + ($inc_price/$inc_day) * randomFloat(0.8,1.2),2); //每天平均上涨价格 浮动20%
            }
            if($price>$task['max_price']) $price = $task['max_price'];

            CurrencyPriceTemp::where(['cpt_currency_id'=>$currency['currency_id']])->setField('cpt_cny_price',$price);

            $todayTimestamp = todayBeginTimestamp();
            $todayPrice = DCPrice::where('currency_id', $currency['currency_id'])->where('add_time', $todayTimestamp)->find();

            if(class_exists('\\app\\common\\model\\DcPrice') and empty($todayPrice)){
                $flag  = ('\\app\\common\\model\\DcPrice')::insertGetId([
                    'currency_id' => $currency['currency_id'],
                    'price' => $price,
                    'add_time' => todayBeginTimestamp(),
                ]);
            }
        }
    }
}
