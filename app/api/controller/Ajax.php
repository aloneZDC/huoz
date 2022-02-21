<?php
/**
 * Created by PhpStorm.
 * User: Jackmao
 * Date: 2016/6/13
 * Time: 21:34
 */

namespace  app\api\controller;


use app\common\model\Currency;
use app\common\model\Kline;
use think\Db;
use think\Exception;

class Ajax extends OrderBase
{
    protected $public_action = ['kline']; //无需登录即可访问
    public function kline() {
        //echo file_get_contents("http://test.bcbwallet.com/mock.json");
        //exit;
        $currency_name = input('market','');
        if(empty($currency_name)) self::output(10101, 'b_stop');

        $geteachothertrade_mark=$this->geteachothertrade_mark($currency_name);
        $where['currency_id']=$geteachothertrade_mark['currency_id'];
        //$where['is_line']=1;
        $where['trade_currency_id']=array('like','%'.$geteachothertrade_mark['currency_trade_id'].'%');
        $currency = db('Currency')->where($where)->find();
        $currency['currency_trade_id']=$geteachothertrade_mark['currency_trade_id'];
        if(!$currency) self::output(10101, 'b_stop');

        //是否开启机器人
        $is_robot = Db::name('currency_autotrade')->where(['currency_id'=>$geteachothertrade_mark['currency_id'],'trade_currency_id'=>$geteachothertrade_mark['currency_trade_id'],'is_autotrade'=>1])->find();
        if($is_robot && ($is_robot['kline_huobi'] || $is_robot['kline_trade']) ) {
            $range = input('range', 900000);
            $result = Kline::getKline($currency_name,$range);
            $this->output_new($result);
            die();
        }

        $step = substr(input('range', 900000), 0, -3); //默认15分钟

        $Sname = 'app_lists_pro' . $currency['currency_id'] . '_' . $currency['currency_trade_id'] . 't' . $step;
        $data = cache($Sname);

        if (empty($data)) {

            $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
            $where = [];
            $where['currency_id'] = $currency['currency_id'];
            $where['currency_trade_id'] = $currency['currency_trade_id'];
//            $where['trade_id'] = array('gt',10000);
            $where['type'] = 'buy';
            $data =  db('Trade')->field('trade_id,min(price) as min_price,max(price) as max_price,sum(num) as num,price as open_price,'.$areaKey.' as addtime2')->where($where)->group('addtime2')->order('addtime2 desc')->limit(1000)->select();
            if(!$data) self::output(10000, lang('lan_operation_success'),$data);

            $open_price = db('Trade')->field('trade_id,price as open_price,' . "$areaKey" . ' as min')
            ->join('(select min(trade_id) id from yang_trade where currency_id=' . $currency['currency_id'] . ' and currency_trade_id=' . $currency['currency_trade_id'] . ' and type=\'buy\' group by '.$areaKey.') b',"yang_trade.trade_id = b.id")
            ->where($where)
            ->order('min desc')
            ->limit(1000)->select();
            $end_price = db('Trade')->field('trade_id,price as end_price,' . "$areaKey" . ' as min')
                ->join('(select max(trade_id) id from yang_trade where currency_id=' . $currency['currency_id'] . ' and currency_trade_id=' . $currency['currency_trade_id'] . ' and type=\'buy\' group by '.$areaKey.') b',"yang_trade.trade_id = b.id")
                ->where($where)
                ->order('min desc')
                ->limit(1000)->select();

            $list = array();
            foreach ($data as $k => $v) {
                if(isset($end_price[$k]) && isset($open_price[$k]) && $end_price[$k]['min']==$v['addtime2']) {
                    //                        floatval($v['addtime2'] . '000'),
                    $list[] = array(
                        floatval($v['addtime2'].''),
                        number_format($open_price[$k]['open_price'],6,'.',''),
                        number_format($v['max_price'],6,'.',''),
                        number_format($v['min_price'],6,'.',''),
                        number_format($end_price[$k]['end_price'],6,'.',''),
                        floatval($v['num']),
                    );
                }
            }
            $data = array_reverse($list);

//            if($step>300){
//                $step=300;
//            }
            cache($Sname, $data, 2);
        }

        self::output(1000,'請求成功',$data);
    }
    public function kline_self() {
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

        $Sname = 'app_lists_pro' . $currency['currency_id'] . '_' . $currency['currency_trade_id'] . 't' . $step;
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

}