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
 *币种价格更新定时任务
 */
class CurrencyPrice extends Command
{

    protected function configure()
    {
        $this->setName('CurrencyPrice')->setDescription('This is a CurrencyPrice task');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->doRun();
    }

    /**
     * 更新币种价格，每分钟执行一次
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * Create by: Red
     * Date: 2019/6/24 9:41
     */
    public function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        Log::write("CurrencyPrice start");

        $usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
        if($usdt_cny_price<=0) {
            //获取不到时尝试从 火币K线获取
            $usdt_cny_price = CurrencyPriceTemp::getUsdtCnyByHuobiOtc();
        }
        if($usdt_cny_price<=0) {
            Log::write("CurrencyPrice未获取到USDT价格");
            return false;
        }

        $list = CurrencyPriceTemp::where(['cpt_same_currency_id'=>0])->select();
        if(empty($list)) return false;

        $time = time();
        foreach ($list as $value) {
            $currency = Currency::where(['currency_id'=>$value['cpt_currency_id']])->find();
            if(empty($currency)) continue;

            $update_data = [];
            if($currency['currency_mark']=='USDT') {
                $update_data = [
                    'cpt_cny_price'=> $usdt_cny_price,
                    'cpt_usd_price' => 1,
                    'cpt_update_time' => $time
                ];
            } else {
                if($value['cpt_type']=='self') {
                    if($value['cpt_base_type']=='cny'){
                        //平台自设价格 人民币为主
                        $update_data = [
                            'cpt_cny_price'=> $value['cpt_cny_price'],
                            'cpt_usd_price' => keepPoint($value['cpt_cny_price']/$usdt_cny_price,6),
                            'cpt_update_time' => $time
                        ];
                    } else {
                        //平台自设价格 美元为主
                        $update_data = [
                            'cpt_cny_price'=> keepPoint($value['cpt_usd_price']*$usdt_cny_price,6),
                            'cpt_usd_price' => $value['cpt_usd_price'],
                            'cpt_update_time' => $time
                        ];
                    }
                }elseif($value['cpt_type'] == 'huobi'){
                    $currency_usdt =  CurrencyPriceTemp::getPriceUSD($currency['currency_mark']);
//                    if($currency_usdt<=0) {
//                        //获取不到时 尝试获取火币K线图的价格
//                        $last_kline = Db::name('kline')->where(['currency_id'=>$currency['cpt_currency_id'],'currency_trade_id'=>Currency::USDT_BB_ID])->order('id desc')->find();
//                        if(!empty($last_kline) && $last_kline['close_price']>0) {
//                            $currency_usdt = $last_kline['close_price'];
//                        }
//                    }

                    if($currency_usdt>0) {
                        $currency_cny = keepPoint($currency_usdt*$usdt_cny_price,6);
                        $update_data = [
                            'cpt_cny_price'=> $currency_cny,
                            'cpt_usd_price' => $currency_usdt,
                            'cpt_update_time' => $time
                        ];
                    }
                }elseif($value['cpt_type'] == 'exchange'){
                    //币币交易模块
                    $currency_usdt =  CurrencyPriceTemp::getByApi($value['cpt_api_url']);
                    if($currency_usdt>0) {
                        $currency_cny = keepPoint($currency_usdt*$usdt_cny_price,6);
                        $update_data = [
                            'cpt_cny_price'=> $currency_cny,
                            'cpt_usd_price' => $currency_usdt,
                            'cpt_update_time' => $time
                        ];
                    }
                }
            }

            if(!empty($update_data)) {
                CurrencyPriceTemp::whereOR(['cpt_currency_id'=>$value['cpt_currency_id']])->whereOR(['cpt_same_currency_id'=>$value['cpt_currency_id']])->update($update_data);
                Db::execute('update '.config('database.prefix').'currency_price_temp set cpt_cny_price=cpt_same_multiple*'.$update_data['cpt_cny_price'].',cpt_usd_price=cpt_same_multiple*'.$update_data['cpt_usd_price'].',cpt_update_time='.$time.' where cpt_same_currency_id='.$value['cpt_currency_id']);

                //OTC的涨跌幅限制
                if($value['cpt_otc_price_percent']>0 && $currency['is_otc']==1 && $currency['is_make_price']==1){
                    $currency_otc_update = [];
                    if($currency['make_max_price']>0) {
                        $currency_otc_update['make_max_price'] = keepPoint($update_data['cpt_cny_price']*(100+$value['cpt_otc_price_percent'])/100,2);
                    }
                    if($currency['make_min_price']>0) {
                        $currency_otc_update['make_min_price'] = keepPoint($update_data['cpt_cny_price']*(100-$value['cpt_otc_price_percent'])/100,2);
                    }

                    if(!empty($currency_otc_update)) {
                        Currency::where(['currency_id'=>$currency['currency_id']])->update($currency_otc_update);
                    }
                }
            }
        }
    }
}
