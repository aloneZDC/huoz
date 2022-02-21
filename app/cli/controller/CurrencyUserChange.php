<?php
namespace app\cli\controller;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *用户资产变化表
 */
class CurrencyUserChange extends Command
{

    protected function configure()
    {
        $this->setName('CurrencyUserChange')->setDescription('This is a CurrencyUserChange task');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->doRun();
    }

    protected function doRun() {
        //获取忽略的用户
        $currency_user_robots = Config::get_value('currency_user_robots','');
        $currency_user_robots_array = explode(',',$currency_user_robots);

        $currency_list =  Currency::where(['is_line'=>1])->select();
        if(!$currency_list) return;

        foreach ($currency_list as $currency) {
            $sum = CurrencyUser::where(['currency_id'=>$currency['currency_id']])->field('sum(num) as num,sum(forzen_num) as forzen_num')->find();
            if(!$sum['num']) $sum['num'] = 0;
            if(!$sum['forzen_num']) $sum['forzen_num'] = 0;

            //获取要排除的机器人的数量
            if(!empty($currency_user_robots)) {
                $sum2 = CurrencyUser::where([
                        'currency_id'=>$currency['currency_id'],
                        'member_id' => ['in',$currency_user_robots_array]
                ])->field('sum(num) as num,sum(forzen_num) as forzen_num')->find();
                if($sum2 && $sum2['num']) $sum['num'] = keepPoint($sum['num']-$sum2['num']);
                if($sum2 && $sum2['forzen_num']) $sum['forzen_num'] = keepPoint($sum['forzen_num']-$sum2['forzen_num']);
            }
            Db::name('currency_user_change')->insertGetId([
                'currency_id' => $currency['currency_id'],
                'num' => $sum['num'],
                'forzen_num' => $sum['forzen_num'],
                'robot_id' => $currency_user_robots,
                'add_time' => time(),
            ]);
        }
    }
}
