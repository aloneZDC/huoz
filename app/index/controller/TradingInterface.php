<?php
/**
 * Created by PhpStorm.
 * User: Jackmao
 * Date: 2016/6/13
 * Time: 21:34
 */

namespace app\index\controller;



use app\common\model\Currency;
use think\Db;

class TradingInterface extends OrderBase
{
    protected $public_action = ['config','history','symbols','marks'];
    public function prokline()
    {
        $currency_id = input('currency');
        $lang = 'zh';
        if (cookie('think_language') == 'en-us') {
            $lang = 'en';
        }
        if (cookie('think_language') == 'zh-tw') {
            $lang = 'ch';
        }
        $this->assign('lang',$lang);
        $this->assign("market", $currency_id);
       return $this->fetch();
    }

    public function setTradeJson()
    {

    }

    public function getSpecialtyTrades()
    {

    }

    public function trend()
    {

    }

    public function getMarketSpecialtyJson()
    {

        if (empty($_GET['market'])) {
           return $this->fetch('Public:b_stop');
            return;
        }
        $currency_id = strval(input('market'));
        $currency = db('Currency')->where(array('currency_mark' => $currency_id ,'is_line' => 1))->find();
        if (empty($currency)) {
           return $this->fetch('Public:b_stop');
            return;
        }




        $step =intval( input("step", 5 * 60)); //计算成传入时间秒



        //缓存时间
      $chacetime1=60;
       $chacetime3=180;
       $chacetime5=300;
       $chacetime15=600;
       $chacetime30=1200;
       $chacetime60=2400;
       $chacetime120=3600;
       $chacetime240=7200;
       $chacetime360=14400;
       $chacetime720=14400;
       $chacetime1440=14400;
       $chacetime10080=14400;


       $Sname='lists'.$currency['currency_id'].'t'.$step;

       $data=cache($Sname);
       if(empty($data)){
           $data=$this->getKline($step / 60, $currency['currency_id'],$step);
           cache($Sname,$data,$step);

       }

        //$data=$this->getKline3($step / 60, $currency['currency_id'],$step);
        echo json_encode($data);

    }

    public function getKline2($base_time, $currency_id,$step)
    {

         $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % 200)";
        $where['currency_id']= 25;
        $where['type']= 'buy';
        $max_price=$data->field('max(price) as max_price')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();


        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % 200)";
        $where['currency_id']= 25;
        $where['type']= 'buy';
        $min_price=$data->field('min(price) as min_price')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % 200)";
        $where['currency_id']= 25;
        $where['type']= 'buy';
        $sum_price=$data->field('sum(num) as num,add_time')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % 200)";
        $where['currency_id']= 25;
        $where['type']= 'buy';
        $open_price=$data->field('price as open_price')
        ->join('(select max(trade_id) id from yang_trade where currency_id=25 group by floor(yang_trade.add_time - yang_trade.add_time % 200)) b ','yang_trade.trade_id = b.id')
        ->where($where)->group($areaKey)
        ->order('add_time desc')
        ->limit(1000)->select();

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % 200)";
        $where['currency_id']= 25;
        $where['type']= 'buy';
        $end_price=$data->field('price as end_price')
        ->join('(select min(trade_id) id from yang_trade where currency_id=25 group by floor(yang_trade.add_time - yang_trade.add_time % 200)) b ','yang_trade.trade_id = b.id')
        ->where($where)->group($areaKey)
        ->order('add_time desc')
        ->limit(1000)->select();
        //dump($open_price);
        $data = array();
        foreach ($max_price as $k=>$v) {
            $data[$k] = array_merge($max_price[$k], $min_price[$k],$sum_price[$k],$open_price[$k],$end_price[$k]);
        }
        $list= array();

          for($i= 0;$i<100;$i++ ){
              if($data[$i]['open_price']!=null  ){
              $list[$i][]=$data[$i]['add_time'];
              $list[$i][]=0;
              $list[$i][]=0;
              $list[$i][]=$data[$i]['open_price'];
              $list[$i][]=$data[$i]['end_price'];
              $list[$i][]=$data[$i]['max_price'];
              $list[$i][]=$data[$i]['min_price'];
              $list[$i][]=$data[$i]['num'];
              }
          }



        return $list;
    }

    public function getKline($base_time, $currency_id,$step)
    {

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % ".$step.")";
        $where['currency_id']= $currency_id;
        $where['type']= 'buy';
        $max_price=$data->field('max(price) as max_price,'."$areaKey".' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();


        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % ".$step.")";
        $where['currency_id']= $currency_id;
        $where['type']= 'buy';
        $min_price=$data->field('min(price) as min_price,'."$areaKey".' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % ".$step.")";
        $where['currency_id']= $currency_id;
        $where['type']= 'buy';
        $sum_price=$data->field('sum(num) as num, '."$areaKey".' as add_time')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();

        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % ".$step.")";
        $where['currency_id']= $currency_id;
        $where['type']= 'buy';
        $open_price=$data->field('price as open_price,'."$areaKey".' min')
        ->join('(select min(trade_id) id from yang_trade where currency_id='."$currency_id".' and type='.'\'buy\''.' group by floor(yang_trade.add_time - yang_trade.add_time % '."$step".')) b ', 'yang_trade.trade_id = b.id')
        ->where($where)
        ->order('min desc')
        ->limit(1000)->select();


        $data=db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % ".$step.")";
        $where['currency_id']= $currency_id;
        $where['type']= 'buy';
        $end_price=$data->field('price as end_price,'."$areaKey".' min')
        ->join('(select max(trade_id) id from yang_trade where currency_id='."$currency_id".' and type='.'\'buy\''.' group by floor(yang_trade.add_time - yang_trade.add_time % '."$step".')) b ','yang_trade.trade_id = b.id')
        ->where($where)
        ->order('min desc')
        ->limit(1000)->select();

        $data = array();
        foreach ($max_price as $k=>$v) {
            $data[$k] = array_merge($max_price[$k], $min_price[$k],$sum_price[$k],$open_price[$k],$end_price[$k]);
        }
        $list= array();

        for($i= 0;$i<1000;$i++ ){
            if($data[$i]['open_price']!=null  ){
                $list[$i][]=$data[$i]['add_time'];
                $list[$i][]=0;
                $list[$i][]=0;
                $list[$i][]=$data[$i]['open_price'];
                $list[$i][]=$data[$i]['end_price'];
                $list[$i][]=$data[$i]['max_price'];
                $list[$i][]=$data[$i]['min_price'];
                $list[$i][]=$data[$i]['num'];
            }
        }

        $list2=array_reverse($list);


        return $list2;
    }

    public function getKline3($base_time, $currency_id,$step)
    {

        $data=db('Trade');


        $time = time() - $base_time * 60 * 90;
        for ($i = 0; $i < 90; $i++) {
            $start = $time + $base_time * 60 * $i;
            $end = $start + $base_time * 60;
            //时间
            $item[$i][] = $start;
            $item[$i][] = 0;
            $item[$i][] = 0;
            $where['add_time'] = array('between', array($start, $end));
            $where['type'] = 'buy';
            $where['currency_id'] = $currency_id;

            //开盘
            $where_price['add_time'] = array('elt', $start);
            $where_price['type'] = 'buy';
            $where_price['currency_id'] = $currency_id;
            $order = $data->field('price')->where($where_price)->order('add_time desc')->limit(1)->find();
            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;
            //收盘
            $where_price2['add_time']=array('elt',$end);
            $where_price2['type']='buy';
            $where_price2['currency_id']=$currency_id;
            $order = $data->field('price')->where($where_price2)->order('add_time desc')->limit(1)->find();
            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;
            //最高

           $max=$data->field('price')->where($where)->max('price');
           $item[$i][] = !empty($max) ? floatval($max) : floatval($order['price']);
           //最低
           $min=$data->field('price')->where($where)->min('price');
           $item[$i][] = !empty($min) ? floatval($min) : floatval($order['price']);
            //交易量
            $num = $data->field('num')->where($where)->sum('num');
            $item[$i][] = !empty($num) ? floatval($num) : 0;
        }
        //$item = json_encode($item, true);

        return $item;
    }
/*
public function getKline($base_time, $currency_id)
    {
        $time = time() - $base_time * 60 * 60;
        $item = array();
        for ($i = 0; $i < 60; $i++) {
            $start = $time + $base_time * 60 * $i;
            $end = $start + $base_time * 60;
            $where['add_time'] = array('between', array($start, $end));
            $where['type'] = 'buy';
            $where['currency_id'] = $currency_id;

            $num = db('Trade')->where($where)->sum('num');

            if(!$num){
                continue;
            }
            //时间
            $item[$i][] = $start + 8 * 3600;
            $item[$i][] = 0;
            $item[$i][] = 0;

            //开盘
            $where_price['add_time'] = array('elt', $end);
            $where_price['type'] = 'buy';
            $where_price['currency_id'] = $currency_id;
            $order = db('Trade')->field('price')->where($where_price)->order('add_time desc')->find();
            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;
            //收盘
            $order = db('Trade')->field('price')->where($where_price)->order('add_time asc')->find();
            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;
            //最高
            $max = db('Trade')->where($where)->max('price');
            $item[$i][] = !empty($max) ? floatval($max) : 0;
            //最低
            $min = db('Trade')->where($where)->min('price');
            $item[$i][] = !empty($min) ? floatval($min) : 0;
            //交易量
            $item[$i][] = !empty($num) ? floatval($num) : 0;
        }
        return $item;
    }
*/
    //K线
    public function polling()
    {
        $currency_id = input('currency');
        /*$lang = 'zh';
        if (cookie('think_language') == 'en-us') {
            $lang = 'en';
        }
        if (cookie('think_language') == 'zh-tw') {
            $lang = 'ch';
        }
        $this->assign('lang', $lang);*/
        $this->assign("market", $currency_id);
       return $this->fetch();
    }

    public function getMarketSpecialtyJson2()
    {

        if (empty($_GET['market'])) {
           return $this->fetch('Public:b_stop');
            return;
        }

        $currency_mark = strval(input('market'));
        $geteachothertrade_mark=$this->geteachothertrade_mark($currency_mark);

        $where['currency_id']=$geteachothertrade_mark['currency_id'];
        $where['is_line']=1;
        $where['currency_trade_id']=array('like','%'.$geteachothertrade_mark['currency_trade_id'].'%');
        $currency = db('Currency')->where($where)->find();
        $currency['currency_trade_id']=$geteachothertrade_mark['currency_trade_id'];
        if (empty($currency)) {
           return $this->fetch('Public:b_stop');
            return;
        }

        $step = substr(input('range', 3600), 0, -3);

        $Sname = 'lists_pro' . $currency['currency_id'] .'_'.$currency['currency_trade_id']. 't' . $step;

        $data = cache($Sname);
        if (empty($data)) {
            $data = $this->getKline4($step / 60, $currency['currency_id'],$currency['currency_trade_id'], $step);
            cache($Sname, $data, $step);

        }

        echo json_encode($data);

    }

    public function getKline4($base_time, $currency_id,$currency_trade_id, $step)
    {

        $data = db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['currency_trade_id'] = $currency_trade_id;
        $where['type'] = 'buy';
        $max_price = $data->field('max(price) as max_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();

        $data = db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $min_price = $data->field('min(price) as min_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();

        $data = db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $sum_price = $data->field('sum(num) as num, ' . "$areaKey" . ' as add_time')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();

        $data = db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $open_price = $data->field('price as open_price,' . "$areaKey" . ' min')
            ->join('(select min(trade_id) id from yang_trade where currency_id=' . "$currency_id" . ' and type=' . '\'buy\'' . ' group by floor(yang_trade.add_time - yang_trade.add_time % ' . "$step" . ')) b ','yang_trade.trade_id = b.id')
            ->where($where)
            ->order('min desc')
            ->limit(1000)->select();


        $data = db('Trade');
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $end_price = $data->field('price as end_price,' . "$areaKey" . ' min')
            ->join('(select max(trade_id) id from yang_trade where currency_id=' . "$currency_id" . ' and type=' . '\'buy\'' . ' group by floor(yang_trade.add_time - yang_trade.add_time % ' . "$step" . ')) b ',' yang_trade.trade_id = b.id')
            ->where($where)
            ->order('min desc')
            ->limit(1000)->select();

        $data = array();
        foreach ($max_price as $k => $v) {
            $data[$k] = array_merge($max_price[$k], $min_price[$k], $sum_price[$k], $open_price[$k], $end_price[$k]);
        }
        $list = array();

        for ($i = 0; $i < 1000; $i++) {
            if ($data[$i]['open_price'] != null) {
                $list[$i][] = floatval($data[$i]['add_time'] . '000');
                $list[$i][] = floatval($data[$i]['open_price']);
                $list[$i][] = floatval($data[$i]['max_price']);
                $list[$i][] = floatval($data[$i]['min_price']);
                $list[$i][] = floatval($data[$i]['end_price']);
                $list[$i][] = floatval($data[$i]['num']);
            }
        }
        $list2 = array_reverse($list);

        $return['success'] = true;
        $return['data']['lines'] = $list2;
        return $return;

    }

    public function config(){
        $file_c = file_get_contents("static/home/json/config.json" );
        echo $file_c;
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
        $symbol=strval(input('symbol'));
        $resolution=strval(input('resolution'));
        if(empty($symbol)||empty($resolution)){
            return $this->fetch('Public:b_stop');
        }
        $all_time=array('1'=>'60','5'=>'300','15'=>'900','30'=>'1800','60'=>'3600','1D'=>'86400','D'=>'86400','1W'=>'604800','1M'=>'2592000');
        $step=$all_time[$resolution];
        if(empty($step)){
            return $this->fetch('Public:b_stop');
            return;
        }

        $currency_name = explode("_",$symbol);
        if(count($currency_name)!=2) self::output(10101, 'b_stop');

        $currency = Currency::where(['currency_name'=>$currency_name[0]])->whereOr(['currency_mark'=>$currency_name[0]])->find();
        $other_currency = Currency::where(['currency_name'=>$currency_name[1]])->whereOr(['currency_mark'=>$currency_name[1]])->find();
        if(empty($currency) || empty($other_currency)) self::output(10101, 'b_stop');
        $currency['currency_trade_id'] = $other_currency['currency_id'];

        $Sname = 'kline_self' . $currency['currency_id'] .'_'.$currency['currency_trade_id']. 't' . $step;
        $data = cache($Sname);
        if (empty($data)) {
            $list = Db::name('kline')->where(['type'=>$step,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['currency_trade_id']])
                ->limit(1000)->order('add_time desc')->select();
            if($list) {
                foreach ($list as $k => $v) {
                    $list_t[$k] =  floatval($v['add_time'] );
                    $list_o[$k] =  floatval($v['open_price']);
                    $list_h[$k] =  floatval($v['hign_price']);
                    $list_l[$k] =  floatval($v['low_price']);
                    $list_c[$k] =  floatval($v['close_price']);
                    $list_v[$k] =  floatval($v['num']);
                }

                $data['t'] = array_reverse($list_t);
                $data['c'] = array_reverse($list_c);
                $data['o'] = array_reverse($list_o);
                $data['h'] = array_reverse($list_h);
                $data['l'] = array_reverse($list_l);
                $data['v'] = array_reverse($list_v);

                if($step>300){
                    $step=300;
                }
                cache($Sname, $data, $step);
            } else {
                $list_t = $list_c = $list_o = $list_h=$list_l = $list_v =  [];
                $data['t'] =   [];
                $data['c'] =  [];
                $data['h'] =  [];
                $data['l'] =  [];
                $data['v'] =  [];
            }
        }

        $isData=strval(input('isData'));
        if($isData==1){
            $data['s'] = "ok";
        }else {
            $data['s'] = "no_data";
        }

        $show_length = isset($show_length[$resolution]) ? $show_length[$resolution] : 100;
        $time_length = count($data['t']);
        $data['from'] = $time_length>$show_length ? $data['t'][$time_length-$show_length] : (isset($data['t'][0])? $data['t'][0] : 0);
        $data['from_time'] = date('Y-m-d H:i:s',$data['from']);
        $data['to'] =  isset($data['t'][$time_length-1]) ? $data['t'][$time_length-1] : 0;
        $data['to_time'] = date('Y-m-d H:i:s',$data['to']);
        $data['resolution']= $resolution;
        $data['count'] = input('count');
        echo json_encode($data);
        die();
    }
    public function history(){
        return $this->kline_self();
        $symbol=strval(input('symbol'));
        $resolution=strval(input('resolution'));
        if(empty($symbol)||empty($resolution)){

               return $this->fetch('Public:b_stop');
                return;
        }
        $all_time=array('1'=>'60','3'=>'180','5'=>'300','15'=>'900','30'=>'1800','60'=>'3600','240'=>'14400','1D'=>'86400','D'=>'86400','1W'=>'604800','1M'=>'2592000');
        $step=$all_time[$resolution];
        if(empty($step)){

           return $this->fetch('Public:b_stop');
            return;
        }
        $geteachothertrade_mark=$this->geteachothertrade_mark($symbol);
        $where['currency_id']=$geteachothertrade_mark['currency_id'];
        $where['is_line']=1;
        $where['trade_currency_id']=array('like','%'.$geteachothertrade_mark['currency_trade_id'].'%');

        $currency = db('Currency')->where($where)->find();
        $currency['currency_trade_id']=$geteachothertrade_mark['currency_trade_id'];
        if (empty($currency)) {
           return $this->fetch('Public:b_stop');
            return;
        }
        $Sname = 'Home_lists_pro' . $currency['currency_id'] .'_'.$currency['currency_trade_id']. 't' . $step;
        $data = cache($Sname);
        if (empty($data)) {
        $data = $this->getKline_new($step / 60, $currency['currency_id'],$currency['currency_trade_id'], $step);
        if($step>300){
            $step=300;
        }
        cache($Sname, $data, $step);
        }
        $isData=strval(input('isData'));
        //$_SESSION['resolution'];
//         if($_SESSION['resolution']!=$symbol.$resolution){
//             $data['s'] = "ok";
//             session('resolution',$symbol.$resolution);
//         }else {
//                if($_SESSION['rand']!=1){
//                    $data['s'] = "ok";
//                    session('rand',1);
//                }else {
//                    $data['s'] = "no_data";
//                    session('rand',2);
//                }


//         }
        if($isData==1){
            $data['s'] = "ok";
        }else {
            $data['s'] = "no_data";
        }
        echo json_encode($data);



//         $a=rand(1,5);
//         if($a>3){
//         $file_c = file_get_contents( 'static/home/json/history.json' );
//         echo $file_c;
//         }else {
//         $file_c2 = file_get_contents( 'static/home/json/history2.json' );
//         echo $file_c2;
//         }
    }
    public function marks(){
        $file_c = file_get_contents( 'static/home/json/marks.json' );
        echo $file_c;
    }
    public function search(){
        $file_c = file_get_contents( 'static/home/json/search.json' );
        echo $file_c;
    }
    public function symbols(){
        $symbol=strval(input('symbol'));
        if(empty($symbol)){

           return $this->fetch('Public:b_stop');
            return;
        }
        $return['name'] = $symbol;
        $return['exchange-traded'] = '';
        $return['exchange-listed'] = '';
        $return['timezone'] = $symbol;
        $return['minmov'] = 1;
        $return['minmov2'] = 0;
        $return['pricescale'] = 1000000;
        $return['pointvalue'] = 1;
        $return['session'] = '0930-1630';
        $return['pointvalue'] = '1';
        $return['has_intraday'] = true;
        $return['has_no_volume'] = false;
        $return['ticker'] = $symbol;
        $return['description'] = $symbol;
        $return['type'] = 'stock';
        $return['supported_resolutions'] = ["1", "2", "3", "5", "15", "30", "60", "90", "240", "D", "2D", "3D", "W", "3W", "M", "6M"];

        echo json_encode($return);
//         $file_c = file_get_contents( 'static/home/json/symbols.json' );
//         echo $file_c;
    }
//     public function symbol_info(){
//         $file_c = file_get_contents( 'static/home/json/symbols.json' );
//         echo $file_c;
//     }

    public function getKline_new($base_time, $currency_id,$currency_trade_id, $step)
    {

//         $data = db('Trade');
//         $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
//         $where['currency_id'] = $currency_id;
//         $where['currency_trade_id'] = $currency_trade_id;
//         $where['type'] = 'buy';
//         $max_price = $data->field('max(price) as max_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();

//         $data = db('Trade');
//         $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
//         $where['currency_id'] = $currency_id;
//         $where['type'] = 'buy';
//         $min_price = $data->field('min(price) as min_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min desc')->limit(1000)->select();

//         $data = db('Trade');
//         $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
//         $where['currency_id'] = $currency_id;
//         $where['type'] = 'buy';
//         $sum_price = $data->field('sum(num) as num, ' . "$areaKey" . ' as add_time')->where($where)->group($areaKey)->order('add_time desc')->limit(1000)->select();

//         $data = db('Trade');
//         $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
//         $where['currency_id'] = $currency_id;
//         $where['type'] = 'buy';
//         $open_price = $data->field('price as open_price,' . "$areaKey" . ' min')
//         ->join('(select min(trade_id) id from yang_trade where currency_id=' . "$currency_id" . ' and type=' . '\'buy\'' . ' group by floor(yang_trade.add_time - yang_trade.add_time % ' . "$step" . ')) b on yang_trade.trade_id = b.id')
//         ->where($where)
//         ->order('min desc')
//         ->limit(1000)->select();


//         $data = db('Trade');
//         $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
//         $where['currency_id'] = $currency_id;
//         $where['type'] = 'buy';
//         $end_price = $data->field('price as end_price,' . "$areaKey" . ' min')
//         ->join('(select max(trade_id) id from yang_trade where currency_id=' . "$currency_id" . ' and type=' . '\'buy\'' . ' group by floor(yang_trade.add_time - yang_trade.add_time % ' . "$step" . ')) b on yang_trade.trade_id = b.id')
//         ->where($where)
//         ->order('min desc')
//         ->limit(1000)->select();

//         $data = array();
//         foreach ($max_price as $k => $v) {
//             $data[$k] = array_merge($max_price[$k], $min_price[$k], $sum_price[$k], $open_price[$k], $end_price[$k]);
//         }
//         $list = array();

//         for ($i = 0; $i < 1000; $i++) {
//             if ($data[$i]['open_price'] != null) {
//                 $list_t[$i] = floatval($data[$i]['add_time'] . '000');
//                 $list_o[$i] = floatval($data[$i]['open_price']);
//                 $list_h[$i] = floatval($data[$i]['max_price']);
//                 $list_l[$i] = floatval($data[$i]['min_price']);
//                 $list_c[$i] = floatval($data[$i]['end_price']);
//                 $list_v[$i] = floatval($data[$i]['num']);
//             }
//         }
        $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
        $where['currency_id'] = $currency_id;
        $where['currency_trade_id'] = $currency_trade_id;
        $where['type'] = 'buy';
        //$where['trade_id'] = array('gt',10000);
        $data =  db('Trade')->field('trade_id,min(price) as min_price,max(price) as max_price,sum(num) as num,price as open_price,'.$areaKey.' as addtime2')->where($where)->group('addtime2')->order('addtime2 desc')->limit(500)->select();
        $open_price = db('Trade')->field('price as open_price,' . "$areaKey" . ' as min')
        ->join('(select min(trade_id) id from yang_trade where currency_id=' . $currency_id . ' and currency_trade_id=' . $currency_trade_id . ' and type=\'buy\' group by '.$areaKey.') b ',' yang_trade.trade_id = b.id')
        ->where($where)
        ->order('min desc')
        ->limit(200)->select();
        $end_price = db('Trade')->field('price as end_price,' . "$areaKey" . ' as min')
        ->join('(select max(trade_id) id from yang_trade where currency_id=' . $currency_id . ' and currency_trade_id=' . $currency_trade_id . ' and type=\'buy\' group by '.$areaKey.') b ',' yang_trade.trade_id = b.id')
        ->where($where)
        ->order('min desc')
        ->limit(200)->select();
        $list = array();
        foreach ($data as $k => $v) {

            if(isset($end_price[$k]) && $end_price[$k]['min']==$v['addtime2']) {
              if($open_price[$k]['open_price']){
                   $list_t[$k] =  floatval($v['addtime2'] );
                   $list_o[$k] =  floatval($open_price[$k]['open_price']);
                   $list_h[$k] =  floatval($v['max_price']);
                   $list_l[$k] =  floatval($v['min_price']);
                   $list_c[$k] =  floatval($end_price[$k]['end_price']);
                   $list_v[$k] =  floatval($v['num']);
             }
            }

        }



        $return['t'] = isset($list_t)?array_reverse($list_t):null;
        $return['c'] = isset($list_c)?array_reverse($list_c):null;
        $return['o'] =isset($list_o)?array_reverse($list_o):null;
        $return['h'] =isset($list_h)?array_reverse($list_h):null;
        $return['l'] = isset($list_l)?array_reverse($list_l):null;
        $return['v'] =isset($list_v)?array_reverse($list_v):null;

        return $return;

    }


}