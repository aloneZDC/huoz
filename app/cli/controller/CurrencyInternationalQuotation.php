<?php
namespace app\cli\controller;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *国际行情
 */
class CurrencyInternationalQuotation extends Command
{

    protected function configure()
    {
        $this->setName('CurrencyInternationalQuotation')->setDescription('This is a CurrencyInternationalQuotation task');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->doRun();
    }

    /**
     * 更新国际行情，每分钟执行一次
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * Create by: Red
     * Date: 2019/6/24 9:41
     */
    protected function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $list = \app\common\model\CurrencyInternationalQuotation::select();
        if(!empty($list)) {
            foreach ($list as $quotation){
                \app\common\model\CurrencyInternationalQuotation::update_quotation($quotation);
                sleep(2);
            }
        }
    }
}