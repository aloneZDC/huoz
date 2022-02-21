<?php
/**
 * Created by PhpStorm.
 * User: Jackmao
 * Date: 2016/6/13
 * Time: 21:34
 */

namespace  app\h5\controller;


use app\common\model\Currency;
use app\common\model\Kline;
use think\Controller;
use think\Db;
use think\Exception;

class Ajax extends Controller
{
    public function kline_self() {
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:*');
        header('Access-Control-Allow-Credentials:true');

        $show_length = [
            '1' => 150,
            '5' => 120,
            '15' =>90,
            '30' =>80,
            '60' =>70,
            '1D' =>50,
            'D' =>50,
            '1W' =>50,
            '1M' =>50,
        ];

        $currency_name = input('market','');
        if(empty($currency_name)) self::output(10101, 'b_stop');

        $step = substr(input('range', 60000), 0, -3); //默认1分钟
        $all_time=array('1'=>'60','5'=>'300','15'=>'900','30'=>'1800','60'=>'3600','1D'=>'86400','D'=>'86400','1W'=>'604800','1M'=>'2592000');
        $resolution = array_search($step,$all_time);
        if(!$resolution) self::output(10101, 'b_stop');

        $currency_name = explode("_",$currency_name);
        if(count($currency_name)!=2) self::output(10101, 'b_stop');

        $currency = Currency::where(['currency_mark'=>$currency_name[0]])->find();
        $other_currency = Currency::where(['currency_mark'=>$currency_name[1]])->find();
        if(empty($currency) || empty($other_currency)) self::output(10101, 'b_stop');
        $currency['currency_trade_id'] = $other_currency['currency_id'];

        $Sname = 'h5_kline_' . $currency['currency_id'] . '_' . $currency['currency_trade_id'] . 't' . $step;
        $data = cache($Sname);
        if (empty($data)) {
            $data = [];
            $list = Db::name('kline')->where(['type'=>$step,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['currency_trade_id']])
                ->limit(1000)->order('add_time desc')->select();
            if($list) {
                $kline_first = current($list);
                $newKline = \app\common\model\Trade::getNewKline($currency['currency_id'],$currency['currency_trade_id'],$kline_first['add_time'] + $step);
                if($newKline && $newKline['open_price']>0) {
                    array_unshift($list,$newKline);
                }

                $timeArr = array_column($list, 'add_time');
                array_multisort($timeArr, SORT_ASC, $list);
                foreach ($list as $k => $v) {
                    $list_t[$k] =  floatval($v['add_time'] );
                    $list_o[$k] =  floatval($v['open_price']);
                    $list_h[$k] =  floatval($v['hign_price']);
                    $list_l[$k] =  floatval($v['low_price']);
                    $list_c[$k] =  floatval($v['close_price']);
                    $list_v[$k] =  floatval($v['num']);

                    $data[] = [floatval($v['add_time']*1000),floatval($v['open_price']),floatval($v['hign_price']),floatval($v['low_price']),floatval($v['close_price']),floatval($v['num'])];
                }
                cache($Sname, $data, 2);
            } else {
                $data = [];
            }
        }

        self::output(1000,'請求成功',$data);
    }

    protected function output($code, $msg = '', $data = [])
    {
        header('Content-type: application/json;charset=utf-8');
        $data = ['code' => $code, 'message' => $msg, 'result' => $data];
        //不加密模式
        exit(json_encode($data));
    }

}
