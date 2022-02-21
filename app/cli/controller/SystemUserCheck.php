<?php
namespace app\cli\controller;
use app\common\model\AccountBook;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use think\Db;
use think\Log;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 系统用户资产清查
 */
class SystemUserCheck extends Command
{
    public $name = '系统用户资产清查';
    protected $today_config = [];
    protected $config = [];

    protected function configure()
    {
        $this->setName('SystemUserCheck')->setDescription('This is a SystemUserCheck');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun() {
        Log::write($this->name." start");
        $currencyList = Currency::select();
        if(empty($currencyList)) return;

        foreach ($currencyList as $currency) {
            $last_id = 0;
            while (true) {
                $currencyUser = CurrencyUser::where([
                    'member_id' => ['gt',$last_id],
                    'currency_id' => $currency['currency_id'],
                ])->order('member_id asc')->find();
                if(empty($currencyUser)) break;

                $last_id = $currencyUser['member_id'];

                $in = Db::name('accountbook')->where(['member_id'=>$currencyUser['member_id'],'currency_id'=>$currencyUser['currency_id'],'number_type'=>1])->sum('number');
                $ou = Db::name('accountbook')->where(['member_id'=>$currencyUser['member_id'],'currency_id'=>$currencyUser['currency_id'],'number_type'=>2])->sum('number');

                //收入 - 支出 - 余额  一般情况下=0
                $balance = $in - $ou - $currencyUser['num'];
                if ( $balance<-1 || $balance>1 ) {
                    $error = "{$currencyUser['member_id']} : {$currencyUser['currency_id']} in:{$in} out:{$ou} num:{$currencyUser['num']}";
                    echo $error."\r\n";
                    Log::error($error);
                }
                echo $currency['currency_id']." ".$last_id."\r\n";
            }
        }
        Log::write($this->name." stop");
    }
}
