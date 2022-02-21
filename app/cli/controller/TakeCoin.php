<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 19:48
 */

namespace app\cli\controller;


use app\common\model\CurrencyTakeCoin;
use app\common\model\CurrencyTakeLog;
use app\common\model\CurrencyUser;
use app\common\model\Tibi;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\model\CurrencyLog;
use app\common\model\Currency;
use think\Exception;
use think\Log;

class TakeCoin extends Command
{
    protected function configure()
    {
        $this->setName('TakeCoin')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        \think\Request::instance()->module('cli');
        $this->takeCoin();
    }

    /**
     * 处理提币后操作
     * Created by Red.
     * Date: 2018/12/14 20:22
     */
    protected function takeCoin()
    {
        $list =CurrencyTakeLog::where(["status" => 0])->page(0, 1000)->select();
        //查询是否有未处理的数据
        if (!empty($list)) {
            $list = $list->toArray();
            foreach ($list as $k => $value) {
                $currencyLog = new CurrencyTakeLog();
                try {
                    $currencyTakeCoin=null;
                    $fee=0;
                    $txhash=null;
                    $currencyLog->startTrans();
                    $mark = "未知";
                    $sureTime = time();//确认时间
                    if ($value['types'] == 1) {
                        $mark = "BTC";
                        $jsonData = json_decode($value['trans'], true);
                        $sureTime = isset($jsonData['time']) ? $jsonData['time'] : $sureTime;
                        if(isset($jsonData['txid'])){
                            $curr = Currency::where(['currency_mark' => $mark])->field("currency_id,currency_type,currency_mark,tibi_address")->find();
                            if(!empty($curr)){
                                $txhash=$jsonData['txid'];
                                $currencyTakeCoin = CurrencyTakeCoin::where(["to_address" => $value['ato'], "status" => 1, "currency_id" => $curr->currency_id,"money"=>floatval(abs($value['amount']))])->find();
                            }
                        }

                    } elseif ($value['types'] == 2) {
//                        $mark = "USDT";
//                        $jsonData = json_decode($value['trans'], true);
//                        $sureTime = isset($jsonData['blocktime']) ? $jsonData['blocktime'] : $sureTime;
//                        //如果propertyid不是31，则跳过不处理该币
//                        if (!isset($jsonData['propertyid']) || $jsonData['propertyid'] != 31) {
//                            $clResult = CurrencyTakeLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
//                            if (!$clResult) {
//                                Log::write('提币自动更新交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
//                            }
//                            continue;
//                        }
                    }elseif ($value['types'] == 3){
//                        $jsonData = json_decode($value['trans'], true);
//                        //有token是ETH的代币
//                        if(isset($jsonData['token'])){
//                            //根据合约地址查询是哪个代币
//                            $curr=CurrencyTakeLog::where(['currency_mark' => $mark])->field("currency_id,currency_type,currency_mark")->find();
//                            if(!empty($curr)){
//                                $mark=$curr->currency_mark;
//                            }else{
//                                $clResult = CurrencyTakeLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
//                                if (!$clResult) {
//                                    Log::write('提币自动更新交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
//                                }
//                                continue;
//                            }
//                        }else{
//                            $mark="ETH";
//                        }

                    }elseif ($value['types'] == 4) {
                        //瑞波币
                        $mark = "XRP";
                        $jsonData = json_decode($value['trans'], true);
                        if(isset($jsonData['src_tag'])&&$jsonData['src_tag']>0){
                            $fee=$jsonData['fee'];
                            $txhash=strtoupper($jsonData['hash']);
                            $currencyTakeCoin=CurrencyTakeCoin::where(["id"=>$jsonData['src_tag']])->find();
                        }
                    }

                    if(!empty($currencyTakeCoin)){
                        $clResult = CurrencyTakeLog::where(['tx' => $value['tx']])->update(['status' => 3, 'update_time' => time()]);
                        if($clResult){
                            //自动处理提币后的操作
//                            $result=CurrencyTakeCoin::updateTakeCoinStatus($currencyTakeCoin->id,2,$txhash,$sureTime,$fee);
                            //处理提币后只补全currency_take_coin表的txhash字段
                            $result=CurrencyTakeCoin::updateTakeCoin($currencyTakeCoin->id,$txhash);
                            if ($result['code'] == SUCCESS) {
                                Log::write("提币处理成功：" . date("Y-m-d H:i:s", time()));
                            } else {
                                Log::write("提币处理失败，原因是：" . $result['message'], 'INFO');
                                throw new Exception();
                            }
                        }else{
                            Log::write('提币自动更新交易哈希：' . $value['tx'] . "修改状态为：3,时失败", 'INFO');
                            throw new Exception();
                        }
                    }else{
                        $clResult = CurrencyTakeLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                        if (!$clResult) {
                            Log::write('提币自动更新交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                        }
                    }
                    $currencyLog->commit();
                } catch (Exception $exception) {
                    $currencyLog->rollback();
                }
            }

        }

        Log::write('提币自动处理完成时间' . date("Y-m-d H:i:s"), 'INFO');

    }
}