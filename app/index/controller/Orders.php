<?php

namespace app\index\controller;

class Orders extends OrderBase

{
    protected $public_action = ['index','exchange','index_bb','get_currency_data','getOrders','gettrade','getnewbuyprice','getOrders2'];
    public function _initialize()
    {
        parent::_initialize();
        error_reporting(0);
    }


    //交易大厅
    public function currency_trade()
    {
        $count = db('Currency')->where('is_line=1')->count();//根据分类查找数据数量
        $page = new \Think\Page($count, 10);//实例化分页类，传入总记录数和每页显示数
        $show = $page->show();//分页显示输出性
        //$currency = db('Currency')->where('is_line=1')->order('sort asc')->limit($page->firstRow.','.$page->listRows)->select();//时间降序排列，越接近当前时间越高
        $all_name = 'rs_all_currency_trade';
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            $currency = db('Currency')->where('is_line=1')->order('sort asc')->select();//时间降序排列，越接近当前时间越高
            foreach ($currency as $k => $v) {
                $list = parent::getCurrencyMessageById($v['currency_id']);
                $list2 = parent::getCurrencyMessageById2($v['currency_id']);
                $currency[$k] = array_merge($list, $currency[$k], $list2);
            }
            cache($all_name, $currency, 300);
        }
        $currency = cache($all_name);
        //$this->assign('page',$show);
        $this->assign('currency', $currency);
        return $this->fetch();
    }

    //获取挂单记录
    public function getOrders()
    {

        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
        $getOrders2 = $this->getOrdersByType($currency_id, input('post.type'), 7, 'asc', $trade_currency_id);
        //var_dump($getOrders2);
        $getOrders = array_reverse($getOrders2);
        $is_limit = input('post.is_limit');
        $limit_min_price = input('post.limit_min_price');
        $limit_max_price = input('post.limit_max_price');
        if (count($getOrders) < 5) {

        } else {
            //制造数据
            foreach ($getOrders as &$val) {
                //获取基数
                $all_price += $val['price'];
                $all_num += $val['num'] - $val['trade_num'];
                $i += 1;
                //最大价格最小价格
                if (empty($min_price)) {
                    $min_price = $val['price'];
                } else {
                    if ($val['price'] <= $min_price) {
                        $min_price = $val['price'];
                    }
                }
                if (empty($max_price)) {
                    $max_price = $val['price'];
                } else {
                    if ($val['price'] >= $max_price) {
                        $max_price = $val['price'];
                    }
                }
                //收集所有价格
                $in_price[$i] = $val['price'];
            }
            $getOrders_make = $this->get_make_getOrders_up($all_price, $all_num, $i, $min_price, $max_price, $in_price, $is_limit, $limit_min_price, $limit_max_price);
            $getOrders = array_merge($getOrders, $getOrders_make['data']);

            $a = array_map(function ($n){  return $n["price"]; }, $getOrders);
            array_multisort($a, SORT_DESC, $getOrders);
            //是否最低端数不动
            $number_rand = rand(0, 1);
            $getOrders = array_slice($getOrders, $getOrders_make['number'] + $number_rand, 7);
        }
        $sell_max = 0;
        foreach ($getOrders as &$val) {
            if ($val['num'] - $val['trade_num'] > $sell_max) {
                $sell_max = $val['num'] - $val['trade_num'];
            }
            //$val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
            $val['price'] = sprintf('%.6f', $val['price']);
        }
        @$_SESSION['num_max'] = $_SESSION['num_max'] > $sell_max ? $_SESSION['num_max'] : $sell_max;
        foreach ($getOrders as &$val) {
            @$val['num_max'] = $_SESSION['num_max'] > $val['cardinal_number'] ? $_SESSION['num_max'] : $val['cardinal_number'];//参考比例值
        }
        $this->mobileAjaxReturn($getOrders);

    }

    public function get_make_getOrders_up($base_price=0, $base_num, $base_i, $min_price, $max_price, $in_price, $is_limit, $limit_min_price, $limit_max_price)
    {

        $base_price = $base_price / $base_i;
        $base_num = $base_num / $base_i;
        $number = rand(2, 5);
        $max_price = $base_price + $base_price * 0.05;
        $max_num = $base_num + $base_num * 0.1;
        for ($x = 0; $x <= $number; $x++) {
            if ($max_price < 0.002) {
                $max_price = $max_price + $max_price * 0.5;
            }
            $base_price = randomFloat($min_price, $max_price);
            $data[$x]['price'] = format_price($base_price);
            //判断是不是在数组里面
            if (in_array($data[$x]['price'], $in_price) || in_array($data[$x]['price'], $new_in_price)) {
                $base_price = randomFloat($min_price, $max_price);
                $data[$x]['price'] = format_price($base_price);
                $new_in_price['price'] = $data[$x]['price'];
            }

            $base_num = randomFloat(0, $max_num);
            $data[$x]['num'] = $this->format_num_orders($base_num);
            $data[$x]['trade_num'] = 0;
            $data[$x]['new_bili'] = rand(1, 100);
        }

        $a = array('data' => $data, 'number' => $number);
        return $a;
        //var_dump($data);
    }


    public function format_num_orders($num)
    {
        switch ($num) {
            case $num >= 100000:
                $num = intval(($num));
                break;
            case $num >= 10000:
                $num = intval(($num));
                break;
            case $num >= 1000:
                $num = intval(($num));
                break;
            case $num >= 100:
                $num = intval($num);
                break;
            case $num >= 10:
                $num = sprintf("%.1f", $num);
                break;
            case $num >= 1:
                $num = sprintf("%.2f", $num);
                break;
            case $num < 1:
                $num = sprintf("%.3f", $num);
                break;
            default:
                $num;
        }
        return $num;
    }

    //获取挂单记录用于行情中心
    public function getOrdersh()
    {

        $getOrders = $this->getOrdersByType(input('post.currency_id'), input('post.type'), 20, 'asc');
        //$getOrders=array_reverse($getOrders2);
        $this->mobileAjaxReturn($getOrders);

    }

    //获取挂单记录2
    public function getOrders2()
    {
        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
        $getOrders2 = $this->getOrdersByType($currency_id, input('post.type'), 7, 'desc', $trade_currency_id);
        $is_limit = input('post.is_limit');
        $limit_min_price = input('post.limit_min_price');
        $limit_max_price = input('post.limit_max_price');


        $getOrders2=$this->get_make_getOrders_all_new($getOrders2,$is_limit,$limit_min_price,$limit_max_price,'buy');

        $buy_max = 0;
        foreach ($getOrders2 as &$val) {
            if ($val['num'] - $val['trade_num'] > $buy_max) {
                $buy_max = $val['num'] - $val['trade_num'];
            }
            $val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
            $val['price'] = sprintf('%.6f', $val['price']);
        }

        @$_SESSION['num_max'] = $_SESSION['num_max'] > $buy_max ? $_SESSION['num_max'] : $buy_max;
        foreach ($getOrders2 as &$val) {
            @$val['num_max'] = $_SESSION['num_max'] > $val['cardinal_number'] ? $_SESSION['num_max'] : $val['cardinal_number'];//参考比例值
        }
        $this->mobileAjaxReturn($getOrders2);
    }
    public function  get_make_getOrders_all_new($have_orders,$is_limit,$limit_min_price,$limit_max_price,$type='sell'){
        //判断大于几条
        $base_price = 0;
        $base_num = 0;
        $base_i = 0;
        $new_in_price = [];
        if(count($have_orders)<5){
            return  $have_orders;

        }else {


            foreach ($have_orders as &$val) {
                //获取基数
                $base_price+=$val['price'];
                $base_num+=$val['num']-$val['trade_num'];
                $base_i+=1;
                //最大价格最小价格
                if(empty($min_price)){
                    $min_price=$val['price'];
                }else {
                    if($val['price']<=$min_price){
                        $min_price=$val['price'];
                    }
                }
                if(empty($max_price)){
                    $max_price=$val['price'];
                }else {
                    if($val['price']>=$max_price){
                        $max_price=$val['price'];
                    }
                }
                //收集所有价格
                $in_price[$base_i]=$val['price'];
            }
            $base_price=$base_price/$base_i;
            $base_num=$base_num/$base_i;
            $number=rand(2,5);
            if($type=='sell'){
                $max_price=$base_price+$base_price*0.05;
            }else {
                $min_price=$base_price-$base_price*0.05;
            }

            $max_num=$base_num+$base_num*0.1;
            for ($x=0; $x<=$number; $x++) {

                if($type=='sell'){
                    if($max_price<0.002){
                        $max_price=$max_price+$max_price*0.5;
                    }
                }else {
                    if($min_price<0.002){
                        $min_price=$min_price-$min_price*0.5;
                    }
                }


                $base_price=randomFloat($min_price,$max_price);
                $price=format_price($base_price);
                //判断是不是在数组里面
                if(in_array($price,$in_price)||in_array($price,$new_in_price)){
                    $base_price=randomFloat($min_price,$max_price);
                    $price=format_price($base_price);
                    $new_in_price[$price]['price']=$price;
                }
                //判断时间限制

                //判断价格限制
                if($is_limit){

                    if($price<$limit_min_price||$limit_max_price<$price){

                    }else {
                        $data[$x]['price']=$price;
                        $base_num=randomFloat(0,$max_num);
                        $data[$x]['num']=$this->format_num_orders($base_num);
                        $data[$x]['trade_num']=0;
                        $data[$x]['new_bili']=rand(1,100);
                    }

                }else {
                    $data[$x]['price']=$price;
                    $base_num=randomFloat(0,$max_num);
                    $data[$x]['num']=$this->format_num_orders($base_num);
                    $data[$x]['trade_num']=0;
                    $data[$x]['new_bili']=rand(1,100);
                }
            }

            if(empty($data)){
                //没制造数据
                return  $have_orders;
            }else {

                //买卖 不同处理显示数据
                if($type=='sell'){
                    $getOrders=array_merge( $have_orders,$data);
                    $a = array_map(function($n){ return $n["price"]; }, $getOrders);
                    array_multisort($a,SORT_DESC,$getOrders );
                    //是否最低端数不动
                    $number_rand=rand(0,1);
                    $getOrders=array_slice($getOrders,$number+$number_rand,7);
                    return  $getOrders;
                }else {
                    $getOrders2=array_merge( $have_orders,$data);

                    $a = array_map(function($n){ return $n["price"]; }, $getOrders2);
                    array_multisort($a,SORT_DESC,$getOrders2 );
                    //是否最低端数不动
                    $number_rand=rand(0,1);
                    //var_dump($getOrders2);
                    $getOrders2=array_slice($getOrders2,$number_rand,7);
                    return  $getOrders2;
                }

            }

//         $a=array('data'=>$data,'number'=>$number);
//         return $a;
        }

    }


    public function get_make_getOrders_down($base_price, $base_num, $base_i, $min_price, $max_price, $in_price, $is_limit, $limit_min_price, $limit_max_price)
    {

        $base_price = $base_price / $base_i;
        $base_num = $base_num / $base_i;
        $number = rand(2, 5);
        $min_price = $base_price - $base_price * 0.05;
        $max_num = $base_num + $base_num * 0.1;
        for ($x = 0; $x <= $number; $x++) {
            if ($min_price < 0.002) {
                $min_price = $min_price - $min_price * 0.5;
            }
            $base_price = randomFloat($min_price, $max_price);
            $data[$x]['price'] = format_price($base_price);
            //判断是不是在数组里面
            if (in_array($data[$x]['price'], $in_price) || in_array($data[$x]['price'], $new_in_price)) {
                $base_price = randomFloat($min_price, $max_price);
                $data[$x]['price'] = format_price($base_price);
                $new_in_price['price'] = $data[$x]['price'];
            }

            $base_num = randomFloat(0, $max_num);
            $data[$x]['num'] = $this->format_num_orders($base_num);
            $data[$x]['trade_num'] = 0;
            $data[$x]['new_bili'] = rand(1, 100);
        }

        $a = array('data' => $data, 'number' => $number);
        return $a;
        //var_dump($data);
    }


    //获取用户挂单记录
    public function getuserOrders()
    {
        //个人挂单记录
        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
        $list = $this->getOrdersByUser(10, $currency_id, $trade_currency_id);
        foreach ($list as &$val) {
            $val['price_usd'] = format_price_usd($val['price'] / usd2cny());//对美元的价格
            $val['have_num'] = sprintf("%.6f", $val['num'] - $val['trade_num']);
            $val['price'] = sprintf("%.6f", $val['price']);
            $val['totalmoney'] = format_price($val['price'] * $val['num']);
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);

        }
        $this->mobileAjaxReturn($list);

    }

    //获取挂单记录trade
    public function gettrade()
    {
        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
        $gettrade = $this->getOrdersByStatus_all(2, 40, $currency_id, $trade_currency_id);
        foreach ($gettrade as &$val) {
            $val['price'] = sprintf('%.4f', $val['price']);
        }
        $this->mobileAjaxReturn($gettrade);
    }

    //自动获取最新买价
    public function getnewbuyprice()
    {
        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
//         $buy_record = $this->getOrdersByType($currency_id, 'buy', 20, 'desc', $trade_currency_id);
//         $newbuyprice['price'] = $buy_record[0]['price'] ? $buy_record[0]['price'] : '0.0000';
//         $currency_mark  = db('Currency')->where(['currency_id'=>$trade_currency_id])->getField('currency_mark');
//         $newbuyprice['price_usd'] = $newbuyprice['price'] ." {$currency_mark} ≈ $ " . sprintf("%.2f",round($newbuyprice['price'] / usd2cny(), 2));
//         $listCurrency = $this->bbExchange_index();
//        var_dump($listCurrency);
//         $listCurrency[$currency_id.'_'.$trade_currency_id]['0'];
        $getmessage = parent::getCurrencyMessageById($currency_id, $trade_currency_id);
        $newbuyprice['new_price'] = $getmessage['new_price'];
        ///$newbuyprice['new_price_usd']=sprintf("%.2f",round($newbuyprice['price'] / usd2cny(), 2));
        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];
        if ($this->currency_id_mark[$trade_currency_id] == 'XRP') {
            $newbuyprice['new_price_cny'] = format_price_usd($newbuyprice['new_price'] * $kok_price);
            $newbuyprice['new_price_usd'] =  format_price_usd(self::getCurrencyMessageById(8, 5, 1)['new_price'] * $newbuyprice['new_price']);
        } elseif ($this->currency_id_mark[$trade_currency_id] == 'USDT') {
            $USDT_KOK_price = self::getCurrencyMessageById(5, 8, 1)['new_price'];
            $newbuyprice['new_price_cny'] = format_price_usd($newbuyprice['new_price'] * $USDT_KOK_price * $kok_price);
            $newbuyprice['new_price_usd'] =  format_price_usd($newbuyprice['new_price']);
        } elseif ($this->currency_id_mark[$trade_currency_id] == 'BTC') {
            $BTC_KOK_price = self::getCurrencyMessageById(1, 8, 1)['new_price'];
            $newbuyprice['new_price_cny'] = format_price_usd(($BTC_KOK_price * $newbuyprice['new_price'] * $kok_price));
        } elseif ($this->currency_id_mark[$trade_currency_id] == 'UUC') {
            $ETH_KOK_price = self::getCurrencyMessageById(13, 8, 1)['new_price'];
            $newbuyprice['new_price_cny'] = format_price_usd(($ETH_KOK_price * $newbuyprice['new_price'] * $kok_price));
        } else {
            $newbuyprice['new_price_cny'] = format_price_usd($newbuyprice['new_price']);
        }

//        $newbuyprice['new_price_usd'] = format_price_usd($newbuyprice['new_price_cny'] / usd2cny());
        $newbuyprice['new_price_cny'] = $getmessage['new_price_cny'];
        if ($getmessage['new_price_status'] == 1) {
            $newbuyprice['change_24'] = '+' . $getmessage['24H_change'];
        } elseif ($getmessage['new_price_status'] == 2) {
            $newbuyprice['change_24'] = '-' . $getmessage['24H_change'];
        } else {
            $newbuyprice['change_24'] = $getmessage['24H_change'];
        }
        $newbuyprice['max_price'] = format_price($getmessage['max_price']);
        $newbuyprice['min_price'] = format_price($getmessage['min_price']);
        $newbuyprice['24H_done_num'] = $getmessage['24H_done_num'];
//          $this->mobileAjaxReturn($listCurrency[$currency_id.'_'.$trade_currency_id][$currency_id-1]);
        $this->mobileAjaxReturn($newbuyprice);
    }

    //自动获取最新卖价
    public function getnewsellprice()
    {
        list($currency_id, $trade_currency_id) = explode('_', input('post.currency_id'));
        $sell_record2 = $this->getOrdersByType($currency_id, 'sell', 20, 'asc', $trade_currency_id);
        $sell_record = array_reverse($sell_record2);
        $currency_mark = db('Currency')->where(['currency_id' => $trade_currency_id])->getField('currency_mark');
        $newsellprice['price'] = $sell_record[count($sell_record) - 1]['price'] ? $sell_record[count($sell_record) - 1]['price'] : '0.0000';
        $newsellprice['price_usd'] = $newsellprice['price'] . " {$currency_mark} ≈ $ " . sprintf("%.2f", round($newsellprice['price'] / usd2cny(), 2));
        $this->mobileAjaxReturn($newsellprice);
    }

    /**
     *  获取最新交易价格
     * @param unknown $type 积分类型id
     * @return unknown|number
     */
    public function getNewPriceByCurrencyid($currency_id)
    {
        $where['currency_id'] = $currency_id;
        $list = db('Orders')->where($where)->field('price')->order('add_time desc')->find();

        if (!empty($list)) {
            return $list['price'];
        } else {
            return 0;
        }
    }

    //获取K线
    public function getKline($base_time, $currency_id)
    {
        $time = time() - $base_time * 60 * 60;
        for ($i = 0; $i < 60; $i++) {
            $start = $time + $base_time * 60 * $i;
            $end = $start + $base_time * 60;
            //时间
            $item[$i][] = $start * 1000 + 8 * 3600 * 1000;
            $where['add_time'] = array('between', array($start, $end));
            $where['type'] = 'buy';
            $where['currency_id'] = $currency_id;
            //交易量
            $num = db('Trade')->where($where)->sum('num');
            $item[$i][] = !empty($num) ? floatval($num) : 0;
            //开盘
            $where_price['add_time'] = array('elt', $start);
            $where_price['type'] = 'buy';
            $where_price['currency_id'] = $currency_id;
            $order = db('Trade')->field('price')->where($where_price)->order('add_time desc')->find();

            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;
            //最高
            $max = db('Trade')->where($where)->max('price');
            $item[$i][] = !empty($max) ? floatval($max) : floatval($order['price']);
            //最低
            $min = db('Trade')->where($where)->min('price');
            $item[$i][] = !empty($min) ? floatval($min) : floatval($order['price']);
            //收盘

            $where_price2['add_time'] = array('elt', $end);
            $where_price2['type'] = 'buy';
            $where_price2['currency_id'] = $currency_id;
            $order = db('Trade')->field('price')->where($where_price2)->order('add_time desc')->find();

            $item[$i][] = !empty($order['price']) ? floatval($order['price']) : 0;


        }
        $item = json_encode($item, true);
        return $item;
    }

    public function currency2()
    {
        $list = db('Currency')->where('is_line=1 ')->select();
        $this->assign('currency2', $list);
        return $this->fetch();
    }

    public function specialty()
    {
        if (empty($_GET['market'])) {
            return $this->fetch('Public:b_stop');
            return;
        }
        return $this->fetch();
    }

    public function marketone()
    {
        if (empty($_GET['currency'])) {
            return $this->fetch('Public:b_stop');
            return;
        }
//        $currency_id = strval(input('currency')); 改之前
        list($currency_id, $trade_currency_id) = explode('_', strval(input('currency')));
        $listCurrency = $this->bbExchange();
        $this->assign('listCurrency', $listCurrency);
        if (empty($currency_id) || empty($trade_currency_id)) {

            reset($listCurrency);
            $currency_value0 = current($listCurrency);
            $currency_id = current($currency_value0)['currency_mark'];
            $trade_currency_id = current($currency_value0)['trade_currency_mark'];
        }
        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->find();
        $trade_currency = db('Currency')->where(array('currency_mark' => $trade_currency_id, 'is_line' => 1))->find();
        $this->assign('trade_currency', $trade_currency);
        if (empty($currency) || empty($trade_currency)) {
            return $this->fetch('Public:b_stop');
            return;
        }
        //K线
        /*    $this->assign('kline_5m',$this->getKline(5,$currency['currency_id']));
         $this->assign('kline_15m',$this->getKline(15,$currency['currency_id']));
         $this->assign('kline_1h',$this->getKline(60,$currency['currency_id']));
         $this->assign('kline_1d',$this->getKline(24*60,$currency['currency_id']));
         $this->assign('kline_30m',$this->getKline(30,$currency['currency_id']));
         $this->assign('kline_8h',$this->getKline(8*60,$currency['currency_id']));*/

        //查询其他交易积分  去掉当前 积分类型
//        $where['currency_id'] = array('NEQ', $currency['currency_id']);
//        $where['is_line'] = 1;
//        $listCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($where)->order('currency_id desc')->select();
//        foreach ($listCurrency as $k => $v) {
//            $listCurrency[$k]['newPrice'] = $this->getNewPriceByCurrencyid($v['currency_id']);
//        }

        //涨幅价格
        // $CurrencyMessage=parent::getCurrencyMessageById($currency['currency_id']);
        // $newprice=$CurrencyMessage['new_price']-($CurrencyMessage['new_price']*$currency['min_limit'])/100;
        //$newprice2=$CurrencyMessage['new_price']+($CurrencyMessage['new_price']*$currency['max_limit'])/100;

        //涨幅价格2

        $CurrencyMessageprice = $this->getlastmessage($currency['currency_id']);
        $newprice = $CurrencyMessageprice - ($CurrencyMessageprice * $currency['min_limit']) / 100;
        $newprice2 = $CurrencyMessageprice + ($CurrencyMessageprice * $currency['max_limit']) / 100;

        $currency['newprice'] = $newprice;
        $currency['newprice2'] = $newprice2;
        //显示委托记录
        $buy_record = $this->getOrdersByType($currency['currency_id'], 'buy', 20, 'desc');
        $buy_max = 0;
        foreach ($buy_record as &$val) {
            if ($val['num'] - $val['trade_num'] > $buy_max) {
                $buy_max = $val['num'] - $val['trade_num'];
            }
            $val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
        }
        $sell_record2 = $this->getOrdersByType($currency['currency_id'], 'sell', 20, 'asc');
        $sell_record = array_reverse($sell_record2);
        $sell_max = 0;
        foreach ($sell_record as &$val) {
            if ($val['num'] - $val['trade_num'] > $sell_max) {
                $sell_max = $val['num'] - $val['trade_num'];
            }
            $val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
        }
        $num_max = $buy_max > $sell_max ? $buy_max : $sell_max;
        @$num_max = $num_max > $currency['cardinal_number'] ? $num_max : $currency['cardinal_number'];//参考比例值
        $_SESSION['num_max'] = $num_max;

        $this->assign('buy_record', $buy_record);
        $this->assign('sell_record', $sell_record);
        $this->assign('num_max', $num_max);
        //格式化手续费
        $currency['currency_sell_fee'] = floatval($currency['currency_sell_fee']);
        $currency['currency_buy_fee'] = floatval($currency['currency_buy_fee']);
        //积分类型信息
        $currency_message = parent::getCurrencyMessageById($currency['currency_id']);
        $currency_message2 = parent::getCurrencyMessageById2($currency['currency_id']);
        $currency_message3 = array_merge($currency_message, $currency_message2);
        $currency_message3['new_price_usd'] = sprintf('%.2f', round($currency_message3['new_price'] / usd2cny(), 2));//对美元的价格

        $currency_trade = $this->getCurrencynameById($currency['trade_currency_id']);
        $this->assign('currency_message', $currency_message3);
        $this->assign('currency_trade', $currency_trade);
        //个人账户资产
        if (!empty(session('USER_KEY_ID'))) {
            $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
            $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
            $user_currency_money['currency_trade']['num'] = $this->getUserMoney($currency['trade_currency_id'], 'num');
            $user_currency_money['currency_trade']['forzen_num'] = $this->getUserMoney($currency['trade_currency_id'], 'forzen_num');
            if ($currency['trade_currency_id'] == 0) {
                $user_currency_money['currency_trade']['num'] = $this->member['rmb'];
                $user_currency_money['currency_trade']['forzen_num'] = $this->member['forzen_rmb'];
            }
            $this->assign('user_currency_money', $user_currency_money);
            //个人挂单记录
            $this->assign('user_orders', $this->getOrdersByUser(10, $currency['currency_id']));
            //最大可买
            if (!empty($sell_record)) {
                $buy_num = sprintf('%.4f', $user_currency_money['currency_trade']['num'] / $sell_record[0]['price']);
            } else {
                $buy_num = 0;
            }
            $this->assign('buy_num', $buy_num);
            //最大可卖
            $sell_num = sprintf('%.4f', $user_currency_money['currency']['num']);
            $this->assign('sell_num', $sell_num);
        }
        $this->assign('session', session('USER_KEY_ID'));
        $this->assign('currency', $currency);
        //成交记录
        $trade = $this->getOrdersByStatus_all(2, 40, $currency['currency_id']);
        $this->assign('trade', $trade);
        $this->assign('eng_mark', $currency_id);
        $this->assign('bi_name', $currency['currency_name']);
        return $this->fetch();
    }

    /**
     * @Desc:获取最新余额
     * @author: Administrator
     * @return array
     * @Date: 2018/12/28 0028 11:44
     */
    public function get_new_money()
    {
//        if (empty($this->member_id)){
//            $this->mobileAjaxReturn(['Code' => ERROR1, "Msg" => [lang("lan_modifymember_please_login_first")]]);
//        }
        list($currency_id, $trade_currency_id) = explode('_', strval(input('currency')));
        if (empty($currency_id) || empty($trade_currency_id)) {
            $currency_id = 'BTC';
            $trade_currency_id = 'XRP';
        }
        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->find();
        $trade_currency = db('Currency')->where(array('currency_mark' => $trade_currency_id, 'is_line' => 1))->find();
        if (!($currency && $trade_currency)) {
            $this->mobileAjaxReturn(['Code' => ERROR1, "Msg" => [lang("lan_modifymember_parameter_error")]]);
        }
        $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
        $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
        $user_currency_money['currency_trade']['num'] = $this->getUserMoney($trade_currency['currency_id'], 'num');
        $user_currency_money['currency_trade']['forzen_num'] = $this->getUserMoney($trade_currency['currency_id'], 'forzen_num');
        $this->mobileAjaxReturn(['Code' => SUCCESS, "Msg" => $user_currency_money]);

    }

    public function exchange()
    {
        list($currency_id, $trade_currency_id) = explode('_', strval(input('currency','BTC_USDT')));
        // $listCurrency = $this->bbExchange();
        //积分类型
        $all_name = 'rs_all_currency';
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            $currency_data = $this->bbExchange();
            cache($all_name, $currency_data, 210);
        }
        $listCurrency = $currency_data = cache($all_name);

        $this->assign('currency_data_all', $currency_data);
        $this->assign('listCurrency', $listCurrency);
        if (empty($currency_id) || empty($trade_currency_id)) {
//           $coin_list = $this->coin_list;
//           $coin_list_key0  = array_keys($coin_list)[0];
//           $currency = db('Currency')->where(array('is_line' => 1))->order('currency_id desc')->find();
//           $currency_id = $currency['currency_mark'];
            //默认处理第一个兑换币对交易
//            reset($listCurrency);
//            $currency_value0 = current($listCurrency);
//            $currency_id  = current($currency_value0)['currency_mark'];
//            $trade_currency_id  = current($currency_value0)['trade_currency_mark'];
            $currency_id = 'BTC';
            $trade_currency_id = 'XRP';
        }

        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->order('currency_id desc')->find();
        $trade_currency = db('Currency')->where(array('currency_mark' => $trade_currency_id, 'is_line' => 1))->order('currency_id desc')->find();

        $this->assign('trade_currency', $trade_currency);
        if (empty($currency) || empty($trade_currency)) {
            return $this->fetch('Public:b_stop');
            return;
        }

        $kline_config = kline_config($currency['currency_id'],$trade_currency['currency_id']);
        $this->assign('kline_config',$kline_config);
        $limit_price = '';
        if($currency['is_limit']){
            $getlastmessage=$this->getlastmessage($currency['currency_id'],$trade_currency['currency_id']);         
            $newprice= rtrim(rtrim(keepPoint($getlastmessage+($getlastmessage*$currency['max_limit'])/100,6),'0'),'.');
            $newprice2= rtrim(rtrim(keepPoint($getlastmessage-($getlastmessage*$currency['min_limit'])/100,6),'0'),'.');
            if($getlastmessage) $limit_price = ' ('.$newprice2.' ~ '. $newprice.')';
        }
        $this->assign('limit_price',$limit_price);

        //K线
        /*    $this->assign('kline_5m',$this->getKline(5,$currency['currency_id']));
            $this->assign('kline_15m',$this->getKline(15,$currency['currency_id']));
            $this->assign('kline_1h',$this->getKline(60,$currency['currency_id']));
            $this->assign('kline_1d',$this->getKline(24*60,$currency['currency_id']));
            $this->assign('kline_30m',$this->getKline(30,$currency['currency_id']));
            $this->assign('kline_8h',$this->getKline(8*60,$currency['currency_id']));*/

        //查询其他交易积分  去掉当前 积分类型
//       $where['currency_id'] = array('NEQ', $currency['currency_id']);
//       $where['is_line'] = 1;
//       $listCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($where)->order('currency_id desc')->select();
//       foreach ($listCurrency as $k => $v) {
//           $listCurrency[$k]['newPrice'] = $this->getNewPriceByCurrencyid($v['currency_id']);
//       }

        //涨幅价格
        // $CurrencyMessage=parent::getCurrencyMessageById($currency['currency_id']);
        // $newprice=$CurrencyMessage['new_price']-($CurrencyMessage['new_price']*$currency['min_limit'])/100;
        //$newprice2=$CurrencyMessage['new_price']+($CurrencyMessage['new_price']*$currency['max_limit'])/100;

        //涨幅价格2

        $CurrencyMessageprice = $this->getlastmessage($currency['currency_id'], $trade_currency['currency_id']);
        $newprice = $CurrencyMessageprice - ($CurrencyMessageprice * $currency['min_limit']) / 100;
        $newprice2 = $CurrencyMessageprice + ($CurrencyMessageprice * $currency['max_limit']) / 100;

        $currency['newprice'] = number_format($newprice, 6, '.', '');
        $currency['newprice2'] = number_format($newprice2, 6, '.', '');
        //显示委托记录
//        $buy_record = $this->getOrdersByType($currency['currency_id'], 'buy', 20, 'desc',$trade_currency['currency_id']);
//        foreach ($buy_record as &$val) {
//            $val['price_usd'] = sprintf('%.2f',round($val['price'] / usd2cny(), 2));//对美元的价格
//        }
//        $sell_record2 = $this->getOrdersByType($currency['currency_id'], 'sell', 20, 'asc',$trade_currency['currency_id']);
//        $sell_record = array_reverse($sell_record2);
//        foreach ($sell_record as &$val) {
//            $val['price_usd'] = sprintf('%.2f',round($val['price'] / usd2cny(), 2));//对美元的价格
//        }
//        $this->assign('buy_record', $buy_record);
//        $this->assign('sell_record', $sell_record);
        //格式化手续费
        $currency['currency_sell_fee'] = floatval($currency['currency_sell_fee']);
        $currency['currency_buy_fee'] = floatval($currency['currency_buy_fee']);
        //积分类型信息
        $currency_message3 = parent::getCurrencyMessageById($currency['currency_id'], $trade_currency['currency_id']);
//        $currency_message2 = parent::getCurrencyMessageById2($currency['currency_id'],$trade_currency['currency_id']);
//        $currency_message3 = array_merge($currency_message, $currency_message2);
        $currency_message3['new_price_usd'] = sprintf('%.2f', round($currency_message3['new_price'], 2));//对美元的价格

//        $currency_trade = $this->getCurrencynameById($trade_currency['currency_id']);
        $this->assign('currency_message', $currency_message3);
//        $this->assign('currency_trade', $currency_trade);
        //个人账户资产
        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];
        if (!empty(session('USER_KEY_ID'))) {
            $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
            $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
            $user_currency_money['currency_trade']['num'] = $this->getUserMoney($trade_currency['currency_id'], 'num');
            $user_currency_money['currency_trade']['forzen_num'] = $this->getUserMoney($trade_currency['currency_id'], 'forzen_num');
//            if ($currency['trade_currency_id'] == 0) {
//                $user_currency_money['currency_trade']['num'] = $this->member['rmb'];
//                $user_currency_money['currency_trade']['forzen_num'] = $this->member['forzen_rmb'];
//            }
            $this->assign('user_currency_money', $user_currency_money);
            //个人挂单记录
//            $this->assign('user_orders', $this->getOrdersByUser(10, $currency['currency_id'],$trade_currency['currency_id']));
            //最大可买
            if (!empty($sell_record)) {
                $buy_num = sprintf('%.6f', $user_currency_money['currency_trade']['num'] / $sell_record[0]['price']);
            } else {
                $buy_num = 0;
            }
            $this->assign('buy_num', $buy_num);
            //最大可卖
            $sell_num = sprintf('%.6f', $user_currency_money['currency']['num']);
            $this->assign('sell_num', $sell_num);
            //获取所有账号总额
            $where_user['member_id'] = session('USER_KEY_ID');

            $currency_user = db('currency_user')->where($where_user)->select();
            $allmoneys = 0;
            $all_name = 'rs_all_currency_user';
            $rs1 = cache($all_name);
            if (empty($rs1)) {
                foreach ($currency_user as $k => $v) {
                    $Currency_message[$v['currency_id']] = parent::getCurrencyMessageById($v['currency_id'], 8);
                }
                $Currency_message =  empty($Currency_message)?[]:$Currency_message;
                cache($all_name, $Currency_message, 300);
            }
            $Currency_message = cache($all_name);

            foreach ($currency_user as $k => $v) {
                // $Currency_message = parent::getCurrencyMessageById($v['currency_id']);

                if ($v['currency_id'] == "8") {//查询手续费奖励和红包利息
//                    $allmoney = ($v['num'] + $v['lock_num'] + $v['exchange_num'] + $v['forzen_num'] + $v['num_award']);
                    $allmoney = $v['num'] ;
                } else {
//                    $allmoney = ($v['num'] + $v['lock_num'] + $v['exchange_num'] + $v['forzen_num'] + $v['num_award']) * $Currency_message[$v['currency_id']]['new_price'];
                    if(isset($Currency_message[$v['currency_id']])) {
                        $allmoney = $v['num']  * $Currency_message[$v['currency_id']]['new_price'];
                    } else {
                        $allmoney = 0;
                    }
                }

                $currency_user[$k]['now_price'] = number_format($allmoney, 2);
                $allmoneys += $allmoney;
            }

            $currency_message = parent::getCurrencyMessageById(8, 5);
            $usd2cny = $currency_message['new_price'];
            $this->assign('allmoneys', number_format($allmoneys, 4));

            $allmoneys_cny = number_format($allmoneys * $kok_price, 2);
            if(NEW_PRICE_UNIT=='CNY') {
                $allmoneys_usd = number_format($allmoneys * $currency_message['new_price_cny'], 2);
            } else {
                $allmoneys_usd = number_format($allmoneys * $currency_message['new_price_usd'], 2);
            }
            $this->assign('allmoneys_usd', $allmoneys_usd);
            $this->assign('allmoneys_cny', $allmoneys_cny);

        }else{
            $this->assign('allmoneys', number_format(0, 4));
            $this->assign('allmoneys_usd', number_format(0, 2));
            $this->assign('allmoneys_cny', number_format(0, 2));
        }
        //委托历史
//        $where2['status'] = array('in','-1,2');
//        $where2['member_id'] = session('USER_KEY_ID');
//        $where2['currency_id']=$currency['currency_id'];
//        $where2['currency_trade_id']=$trade_currency['currency_id'];
//        $history_list = db('Orders')->where($where2)->order('add_time desc')->limit(6)->select();

        $this->assign('session', session('USER_KEY_ID'));
        $this->assign('currency', $currency);
        //成交记录
//        $trade = $this->getOrdersByStatus_all(2, 40, $currency['currency_id'],$trade_currency['currency_id']);


//        $this->assign('trade', $trade);
        $this->assign('eng_mark', $currency['currency_mark'] . '_' . $trade_currency['currency_mark']);
        $this->assign('bi_name', $currency['currency_name']);
//        $this->assign('history_list', $history_list);

        if ($trade_currency_id == 'XRP') {
            //$usd2cny = format_price_usd($newbuyprice['new_price']) ;
            $USDT_KOK_price = self::getCurrencyMessageById(8, 5, 1);
            $usd2cny = format_price_usd($USDT_KOK_price['new_price']);
        } elseif ($trade_currency_id == 'USDT') {
            //$usd2cny = format_price_usd($newbuyprice['new_price']*usd2cny()) ;
            $usd2cny = 1;
        } else {
            $newbuyprice=self::getCurrencyMessageById($trade_currency['currency_id'], 5, 1);
            $usd2cny = format_price_usd($newbuyprice['new_price'] * $kok_price);
        }

        @$usd2cny = ($usd2cny==0)?$usd2cny:(1 / $usd2cny);

        //判断那种汇率
        $unit = cookie('think_unit');
        $unit = strtolower($unit);
        if (empty($unit)) $unit = 'unit_'.strtolower(NEW_PRICE_UNIT);

        switch ($unit) {
            case 'unit_cny':
                $usd2cny = $usd2cny / usd2cny();
                break;
            case 'unit_usd':
                //$usd2cny = $usd2cny * usd2cny();

                break;

            default:

                break;
        }
        $this->assign('usd2cny', $usd2cny);
        //公告
        //$lang = $this->getLangNamePc();
        $lang = $this->getLang();
        $art_model = db('Article');
        if (empty($lang)) {
            $field = 'article_id,title,art_pic,content,add_time';
        } else {
            $field = 'article_id,' . $lang . '_title as title,' . $lang . '_content as content,art_pic,add_time';
        }
        $info_red1 = db('article')->field($field)->where('position_id=1 and sign=1')->order('add_time desc')->limit(2)->select();
        $info_red1 = $this->filterArticle($info_red1);
        $this->assign('info_red1', $info_red1);
        //币详细信息
        //通过英文标识所对应的积分id查找关联表
        $cid = $currency['currency_id'];
        $cid_value = db('Currency_introduce')
            ->where('yang_currency_introduce.currency_id=' . $cid)
            ->field("yang_currency_introduce.*")
            ->find();
        $language = cookie('think_language');
        $language = strtolower($language);
        if (empty($language) || strpos(config('LANG_LIST'), $language) === false) $language = config('DEFAULT_LANG');

        switch ($language) {
            case 'zh-tw':
                $introduce = db('Currency_introduce_tc')->where('currency_id=' . $cid)->find();
                break;
            case 'en-us':
                $introduce = db('Currency_introduce_en')->where('currency_id=' . $cid)->find();
                break;
            case 'th-th':
                $introduce = db('Currency_introduce_th')->where('currency_id=' . $cid)->find();
                break;
            default:
                $introduce = db('Currency_introduce')->where('currency_id=' . $cid)->find();
                break;
        }
        $cid_value['feature'] = html_entity_decode($introduce['feature']);
        $cid_value['short'] = html_entity_decode($introduce['short']);
        $cid_value['advantage'] = html_entity_decode($introduce['advantage']);
        $this->assign('cid_value', $cid_value);
        $this->assign('new_price_unit', NEW_PRICE_UNIT);
        return $this->fetch();
    }

    private function filterArticle($zixun)
    {
        if (empty($zixun)) return null;

        foreach ($zixun as $key => $value) {
            $lenth = strlen($value['title']);
            if ($lenth >= 15) {
                $value['title'] = msubstr(trim(strip_tags(html_entity_decode($value['title']))), 0, 15) . "..";
            } else {
                $value['title'] = trim(strip_tags(html_entity_decode($value['title'])));
            }
            $lenth2 = strlen($value['content']);
            if ($lenth2 >= 200) {
                $value['content'] = cutArticle(trim(strip_tags(html_entity_decode($value['content']))), 200) . "..";
            } else {
                $value['content'] = trim(strip_tags(html_entity_decode($value['content'])));
            }
            $value['add_time'] = date('Y-m-d', $value['add_time']);
            $zixun[$key] = $value;
        }

        return $zixun;
    }

    public function marketTwo()
    {
        list($currency_id, $trade_currency_id) = explode('_', strval(input('currency')));
        $listCurrency = $this->bbExchange();
        $this->assign('listCurrency', $listCurrency);
        if (empty($currency_id) || empty($trade_currency_id)) {
//           $coin_list = $this->coin_list;
//           $coin_list_key0  = array_keys($coin_list)[0];
//           $currency = db('Currency')->where(array('is_line' => 1))->order('currency_id desc')->find();
//           $currency_id = $currency['currency_mark'];
            //默认处理第一个兑换币对交易
            reset($listCurrency);
            $currency_value0 = current($listCurrency);
            $currency_id = current($currency_value0)['currency_mark'];
            $trade_currency_id = current($currency_value0)['trade_currency_mark'];
        }

        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->order('currency_id desc')->find();
        $trade_currency = db('Currency')->where(array('currency_mark' => $trade_currency_id, 'is_line' => 1))->order('currency_id desc')->find();
        $this->assign('trade_currency', $trade_currency);
        if (empty($currency) || empty($trade_currency)) {
            return $this->fetch('Public:b_stop');
            return;
        }

        //K线
        /*    $this->assign('kline_5m',$this->getKline(5,$currency['currency_id']));
            $this->assign('kline_15m',$this->getKline(15,$currency['currency_id']));
            $this->assign('kline_1h',$this->getKline(60,$currency['currency_id']));
            $this->assign('kline_1d',$this->getKline(24*60,$currency['currency_id']));
            $this->assign('kline_30m',$this->getKline(30,$currency['currency_id']));
            $this->assign('kline_8h',$this->getKline(8*60,$currency['currency_id']));*/

        //查询其他交易积分  去掉当前 积分类型
//       $where['currency_id'] = array('NEQ', $currency['currency_id']);
//       $where['is_line'] = 1;
//       $listCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($where)->order('currency_id desc')->select();
//       foreach ($listCurrency as $k => $v) {
//           $listCurrency[$k]['newPrice'] = $this->getNewPriceByCurrencyid($v['currency_id']);
//       }

        //涨幅价格
        // $CurrencyMessage=parent::getCurrencyMessageById($currency['currency_id']);
        // $newprice=$CurrencyMessage['new_price']-($CurrencyMessage['new_price']*$currency['min_limit'])/100;
        //$newprice2=$CurrencyMessage['new_price']+($CurrencyMessage['new_price']*$currency['max_limit'])/100;

        //涨幅价格2

        $CurrencyMessageprice = $this->getlastmessage($currency['currency_id'], $trade_currency['currency_id']);
        $newprice = $CurrencyMessageprice - ($CurrencyMessageprice * $currency['min_limit']) / 100;
        $newprice2 = $CurrencyMessageprice + ($CurrencyMessageprice * $currency['max_limit']) / 100;

        $currency['newprice'] = number_format($newprice, 6, '.', '');
        $currency['newprice2'] = number_format($newprice2, 6, '.', '');
        //显示委托记录
        $buy_record = $this->getOrdersByType($currency['currency_id'], 'buy', 20, 'desc', $trade_currency['currency_id']);
        foreach ($buy_record as &$val) {
            $val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
        }
        $sell_record2 = $this->getOrdersByType($currency['currency_id'], 'sell', 20, 'asc', $trade_currency['currency_id']);
        $sell_record = array_reverse($sell_record2);
        foreach ($sell_record as &$val) {
            $val['price_usd'] = sprintf('%.2f', round($val['price'] / usd2cny(), 2));//对美元的价格
        }
        $this->assign('buy_record', $buy_record);
        $this->assign('sell_record', $sell_record);
        //格式化手续费
        $currency['currency_sell_fee'] = floatval($currency['currency_sell_fee']);
        $currency['currency_buy_fee'] = floatval($currency['currency_buy_fee']);
        //积分类型信息
        $currency_message = parent::getCurrencyMessageById($currency['currency_id'], $trade_currency['currency_id']);
        $currency_message2 = parent::getCurrencyMessageById2($currency['currency_id'], $trade_currency['currency_id']);
        $currency_message3 = array_merge($currency_message, $currency_message2);
        $currency_message3['new_price_usd'] = sprintf('%.2f', round($currency_message3['new_price'] / usd2cny(), 2));//对美元的价格

        $currency_trade = $this->getCurrencynameById($trade_currency['currency_id']);
        $this->assign('currency_message', $currency_message3);
        $this->assign('currency_trade', $currency_trade);
        //个人账户资产
        if (!empty(session('USER_KEY_ID'))) {
            $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
            $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
            $user_currency_money['currency_trade']['num'] = $this->getUserMoney($trade_currency['currency_id'], 'num');
            $user_currency_money['currency_trade']['forzen_num'] = $this->getUserMoney($trade_currency['currency_id'], 'forzen_num');
//            if ($currency['trade_currency_id'] == 0) {
//                $user_currency_money['currency_trade']['num'] = $this->member['rmb'];
//                $user_currency_money['currency_trade']['forzen_num'] = $this->member['forzen_rmb'];
//            }
            $this->assign('user_currency_money', $user_currency_money);
            //个人挂单记录
            $this->assign('user_orders', $this->getOrdersByUser(10, $currency['currency_id'], $trade_currency['currency_id']));
            //最大可买
            if (!empty($sell_record)) {
                $buy_num = sprintf('%.4f', $user_currency_money['currency_trade']['num'] / $sell_record[0]['price']);
            } else {
                $buy_num = 0;
            }
            $this->assign('buy_num', $buy_num);
            //最大可卖
            $sell_num = sprintf('%.4f', $user_currency_money['currency']['num']);
            $this->assign('sell_num', $sell_num);
        }
        //委托历史
        $where2['status'] = array('in', '-1,2');
        $where2['member_id'] = session('USER_KEY_ID');
        $where2['currency_id'] = $currency['currency_id'];
        $where2['currency_trade_id'] = $trade_currency['currency_id'];
        $history_list = db('Orders')->where($where2)->order('add_time desc')->limit(6)->select();

        $this->assign('session', session('USER_KEY_ID'));
        $this->assign('currency', $currency);
        //成交记录
        $trade = $this->getOrdersByStatus_all(2, 40, $currency['currency_id'], $trade_currency['currency_id']);
        $this->assign('trade', $trade);
        $this->assign('eng_mark', $currency['currency_mark'] . '_' . $trade_currency['currency_mark']);
        $this->assign('bi_name', $currency['currency_name']);
        $this->assign('history_list', $history_list);
        $this->assign('usd2cny', usd2cny());
        return $this->fetch();
    }

    //选项卡了解积分类型页
    public function knowThree()
    {
        if (empty($_GET['currency'])) {
            return $this->fetch('Public:b_stop');
            return;
        }
        $currency_id = strval(input('currency'));
        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->order('currency_id desc')->find();
        if (empty($currency)) {
            return $this->fetch('Public:b_stop');
            return;
        }

        //通过英文标识所对应的积分id查找关联表
        $cid = $currency['currency_id'];
        //$cid_value=db('currency_introduce')->where('currency_id='.$cid)->find();
        $cid_value = db('Currency_introduce')->where('yang_currency_introduce.currency_id=' . $cid)
            ->field("yang_currency_introduce.*,yang_currency_introduce_tc.feature as tc_feature,yang_currency_introduce_tc.short as tc_short,yang_currency_introduce_tc.advantage as tc_advantage,yang_currency_introduce_en.feature as en_feature,yang_currency_introduce_en.short as en_short,yang_currency_introduce_en.advantage as en_advantage,
            yang_currency_introduce_th.feature as th_feature,yang_currency_introduce_th.short as th_short,yang_currency_introduce_th.advantage as th_advantage")
            ->join('left join yang_currency_introduce_tc ON yang_currency_introduce.currency_id = yang_currency_introduce_tc.currency_id')
            ->join('left join yang_currency_introduce_en ON yang_currency_introduce.currency_id = yang_currency_introduce_en.currency_id')
            ->join('left join yang_currency_introduce_th ON yang_currency_introduce.currency_id = yang_currency_introduce_th.currency_id')->find();
        $cid_value['advantage'] = html_entity_decode($cid_value['advantage']);
        $cid_value['feature'] = html_entity_decode($cid_value['feature']);
        $cid_value['short'] = html_entity_decode($cid_value['short']);
        $cid_value['en_advantage'] = html_entity_decode($cid_value['en_advantage']);
        $cid_value['en_feature'] = html_entity_decode($cid_value['en_feature']);
        $cid_value['en_short'] = html_entity_decode($cid_value['en_short']);
        $cid_value['tc_advantage'] = html_entity_decode($cid_value['tc_advantage']);
        $cid_value['tc_feature'] = html_entity_decode($cid_value['tc_feature']);
        $cid_value['tc_short'] = html_entity_decode($cid_value['tc_short']);
        $cid_value['th_advantage'] = html_entity_decode($cid_value['th_advantage']);
        $cid_value['th_feature'] = html_entity_decode($cid_value['th_feature']);
        $cid_value['th_short'] = html_entity_decode($cid_value['th_short']);

        $cid_url = db('currency_introduce_url')->where('currency_id=' . $cid)->select();

        //查询其他交易积分  去掉当前 积分类型
        $where['currency_id'] = array('NEQ', $currency['currency_id']);
        $where['is_line'] = 1;
        $listCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($where)->order('currency_id desc')->select();
        foreach ($listCurrency as $k => $v) {
            $listCurrency[$k]['newPrice'] = $this->getNewPriceByCurrencyid($v['currency_id']);
        }
        $this->assign('listCurrency', $listCurrency);


        //积分类型信息
        $currency_message = parent::getCurrencyMessageById($currency['currency_id']);
        $currency_message['new_price_usd'] = sprintf('%.2f', round($currency_message['new_price'] / usd2cny(), 2));//对美元的价格
        $currency_trade = $this->getCurrencynameById($trade_currency['currency_id']);
        $this->assign('currency_message', $currency_message);
        $this->assign('currency_trade', $currency_trade);

        $this->assign('session', session('USER_KEY_ID'));
        $this->assign('currency', $currency);            //货币主表
        //成交记录
        $trade = $this->getOrdersByStatus(2, 40, $currency['currency_id']);
        $this->assign('trade', $trade);
        $this->assign('cid_value', $cid_value);            //货币介绍详细参数
        $this->assign('cid_url', $cid_url);                //货币介绍链接
        $this->assign('eng_mark', $currency_id);            //英文标识
        $this->assign('bi_name', $currency['currency_name']);        //货币名称
        return $this->fetch();
    }

    public function marketFour()
    {
        if (empty($_GET['currency'])) {
            return $this->fetch('Public:b_stop');
            return;
        }
        $currency_id = input('currency');
        $currency = db('Currency')->where(array('currency_mark' => $currency_id, 'is_line' => 1))->find();
        if (empty($currency)) {
            return $this->fetch('Public:b_stop');
            return;
        }
        //查询其他交易积分  去掉当前 积分类型
        $where['currency_id'] = array('NEQ', $currency['currency_id']);
        $where['is_line'] = 1;
        $listCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($where)->select();
        foreach ($listCurrency as $k => $v) {
            $listCurrency[$k]['newPrice'] = $this->getNewPriceByCurrencyid($v['currency_id']);
        }
        $this->assign('listCurrency', $listCurrency);

        //积分类型信息
        $currency_message = parent::getCurrencyMessageById($currency['currency_id']);
        $currency_trade = $this->getCurrencynameById($currency['trade_currency_id']);
        $this->assign('currency_message', $currency_message);
        $this->assign('currency_trade', $currency_trade);

        $this->assign('session', session('USER_KEY_ID'));
        $this->assign('currency', $currency);            //货币主表
        //成交记录
        $trade = $this->getOrdersByStatus(2, 40, $currency['currency_id']);
        $this->assign('trade', $trade);
        $this->assign('eng_mark', $currency_id);            //英文标识
        $this->assign('bi_name', $currency['currency_name']);        //货币名称
        //行情
        if (!empty($_GET['currency'])) {
            $whereMark['currency_mark'] = input('currency');
        }

        $whereMark['is_line'] = 1;
        $liCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($whereMark)->find();

        $wheretrade['currency_id'] = $liCurrency['currency_id'];
        //成交盘
        $Deal = db('Trade')->where($wheretrade)->order('add_time desc')->limit(30)->select();

        //买卖盘   买
        $sell = $this->getSellBuyByCurrencyIdType('sell', $liCurrency['currency_id']);
        // 页面显示 成交量背景 比例
        foreach ($sell as $k => $v) {
            $sell[$k]['bili'] = 100 - intval(($v['trade_num'] / $v['num']) * 100) . "%";
        }

        //买卖盘   卖
        $buy = $this->getSellBuyByCurrencyIdType('buy', $liCurrency['currency_id']);
        // 页面显示 成交量背景 比例
        foreach ($buy as $k => $v) {
            $buy[$k]['bili'] = 100 - intval(($v['trade_num'] / $v['num']) * 100) . "%";
        }

        $this->assign('count', max(count($sell), count($buy)));
        $this->assign('deal', $Deal);
        $this->assign('sell', $sell);
        $this->assign('buy', $buy);
        $this->assign('liCurrency', $liCurrency);
        return $this->fetch();
    }

    /**
     * ajax动态取数据
     */
//    public function marketFour_ajax(){
//    	$liCurrency = db('Currency')->field('currency_id,currency_name,currency_logo,currency_mark')->where($whereMark)->find();
//
//    	//初始化
//    	$data['sell'] = [];
//    	$data['buy'] = [];
//
//    	//买卖盘   买
//    	$sql = "SELECT SUM(num)AS num,price,trade_num  from  yang_orders  where type = 'sell'
//    			and currency_id  = '{$liCurrency['currency_id']}' and orders_id >= (SELECT FLOOR( MAX(orders_id) * RAND()) FROM yang_orders ) GROUP BY  price ORDER BY orders_id LIMIT 1";
//    	$sell = db()->query($sql);
//    	// 页面显示 成交量背景 比例
//    	if(!empty($sell)){
//    		foreach ($sell as $k=>$v){
//    			$sell[$k]['bili']=100-intval(($v['trade_num']/$v['num'])*100)."%";
//    		}
//    	}
//
//    	//买卖盘   卖
//    	$sql = "SELECT SUM(num)AS num,price,trade_num  from  yang_orders  where type = 'buy'
//    			and currency_id  = '{$liCurrency['currency_id']}' and orders_id >= (SELECT FLOOR( MAX(orders_id) * RAND()) FROM yang_orders ) GROUP BY  price desc ORDER BY orders_id LIMIT 1";
//    	$buy = db()->query($sql);
//    	// 页面显示 成交量背景 比例
//    	if(!empty($buy)){
//    		foreach ($buy as $k=>$v){
//    			$buy[$k]['bili']=100-intval(($v['trade_num']/$v['num'])*100)."%";
//    		}
//    	}
//
//    	$data = [
//    		'sell' => $sell,
//    		'buy' => $buy
//    	];
//
//    	if(!empty($sell) && !empty($buy)){
//    		$this->mobileAjaxReturn(['Code' => 1, 'Msg' => $data]);
//    	}
//
//    	$this->mobileAjaxReturn(['Code' => 0, 'Msg' => []]);
//    }

    /**
     *  获取 买卖盘
     * @param unknown str $type          type  买卖  sell   buy
     * @param unknown Int $currency_id   货币id
     */
    private function getSellBuyByCurrencyIdType($type, $currency_id)
    {
        $type = strtoupper($type);
        if ($type == 'BUY') {
            $sql = "SELECT SUM(num)AS num,price,trade_num  from  yang_orders  where type = '" . $type . "'
    			and currency_id  =" . $currency_id . " GROUP BY  price desc";
        } else {
            $sql = "SELECT SUM(num)AS num,price,trade_num  from  yang_orders  where type = '" . $type . "'
    			and currency_id  =" . $currency_id . " GROUP BY  price";
        }
        $list = db()->query($sql);
        return $list;
    }

    //ajax
    public function buySelpan()
    {
        $currencyId = input('currency_id');
        //买卖盘   卖

        $sell = $this->getSellBuyByCurrencyIdType('sell', $currencyId);
        // 页面显示 成交量背景 比例
        foreach ($sell as $k => $v) {
            $sell[$k]['bili'] = 100 - intval(($v['trade_num'] / $v['num']) * 100) . "%";
        }

        //买卖盘   买
        $buy = $this->getSellBuyByCurrencyIdType('buy', $currencyId);
        // 页面显示 成交量背景 比例
        foreach ($buy as $k => $v) {
            $buy[$k]['bili'] = 100 - intval(($v['trade_num'] / $v['num']) * 100) . "%";
        }
        $v = '';
        $count = max(count($sell), count($buy));

        for ($i = 0; $i < $count; $i++) {
            $v .= '<tr><td>' . ($i + 1) . '</td></tr>';
        }
        $v .= '|';
        for ($i = 0; $i < $count; $i++) {
            $v .= '<tr>
  		<td><span>' . $buy[$i]['price'] * $buy[$i]['num'] .
                '<i style="font-style:normal;color:#999;"></i>
  		</span></td>
  		<td><span style="color:#e55600;">' . $buy[$i]['num'] . '</span></td>
  		<td> <span>' . $buy[$i]['price'] . '</span><span data-count="504.461" class="deepbar buy" style="margin-top:-10px; width: ' . $buy[$i]['bili'] . ';"></span>
  		</td>
  		</tr>';
        }
        $v .= '|';
        for ($i = 0; $i < $count; $i++) {
            $v .= '<tr>
  		<td><span>' . $sell[$i]['price'] .
                '</span></td><td><span style="color:#690;">' . $sell[$i]['num'] . '</span></td>
  		<td> <span>' . $sell[$i]['price'] * $sell[$i]['num'] . '<i style="font-style:normal;color:#999;"></i></span><span data-count="1000" class="deepbar sell" style="margin-top:-10px; width: ' . $sell[$i]['bili'] . ';"></span>
  		</td>
  		</tr>';
        }
        $v .= '|';
        //for($i=0;$i<$count;$i++){
        $v .= json_encode($sell);
        //}
        $this->mobileAjaxReturn($v);
    }

    public function get_currency_data()
    {
        $my = [];
        $currency_make = input('currency_mark');
        $all_name = 'rs_all_currency_new';
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            $currency_data = $this->bbExchange();
            cache($all_name, $currency_data, 10);
        }
        $currency_data = cache($all_name);
        //添加自选 全部
//         foreach ($currency_data as $K =>$v){
//             foreach ($currency_data[$k] as $K2 =>$v2){

//                 $currency_data[$k]['kkk']=6;
//             }

//         }
        //获取所有自选
        $get_my = db('currency_collect')->where(array('member_id' => $this->member_id))->select();
        if (!empty($get_my)) {
            foreach ($get_my as $k => $value) {
                $my[$value['currency_id']][$value['trade_currency_id']] = 1;
            }
        }

        if (!empty($currency_make)) {
            if ($currency_make == 'myself') {
                if ($get_my) {
                    //所有自选循环取
                    foreach ($get_my as $k_my => $val_my) {
                        if (empty($get_date[$val_my['trade_currency_id']])) {
                            $get_date[$val_my['trade_currency_id']] = $currency_data[$this->currency_id_mark[$val_my['trade_currency_id']]];
                        }
                        if (!empty($get_date[$val_my['trade_currency_id']])) {
                            foreach ($get_date[$val_my['trade_currency_id']] as $k => $val) {
                                //获取里面相对应值进数组
                                if ($val['currency_id'] == $val_my['currency_id'] && $val_my['trade_currency_id'] == $val['currency_id']) {
                                    $currency_data2[$k_my]['currency_mark'] = $val['currency_mark'];
                                    $currency_data2[$k_my]['24H_change'] = $val['24H_change'];
                                    $currency_data2[$k_my]['trade_currency_mark'] = $val['trade_currency_mark'];
                                    $currency_data2[$k_my]['new_price'] = $val['new_price'];
                                    $currency_data2[$k_my]['currency_id'] = $val['currency_id'] . '_ '. $val['trade_currency_id'];
                                }
                            }
                        }

                    }
                    $currency_data = $currency_data2;

                }

            } else {
                $currency_data2 = [];
                $currency_data = array_merge($currency_data[$currency_make]);
                foreach ($currency_data as $k => $val) {
                    $currency_data2[$k]['currency_mark'] = $val['currency_mark'];
                    $currency_data2[$k]['24H_change'] = $val['24H_change'];
                    $currency_data2[$k]['trade_currency_mark'] = $val['trade_currency_mark'];
                    $currency_data2[$k]['new_price'] = $val['new_price'];
                    $currency_data2[$k]['new_price_usd'] = $val['new_price_usd'];
                    $currency_data2[$k]['new_price_cny'] = $val['new_price_cny'];

                    if (!empty($my[$val['currency_id']][$val['trade_currency_id']])) {
                        $currency_data2[$k]['is_choose'] = 1;
                    } else {
                        $currency_data2[$k]['is_choose'] = 0;
                    }
                    $currency_data2[$k]['currency_id'] = $val['currency_id'] . '_' . $val['trade_currency_id'];

                }

                $currency_data = $currency_data2;
            }
        }
        if (empty($currency_data)) {
            $currency_data = array();
        }
        mobileAjaxReturn($currency_data);
    }

    /**
     * 收藏
     */
    public function collect()
    {
        
        //更换币币交易 获取xx_xx参数
        $buycurrency_id = strval(input('post.currency_id'));
        //获取买卖currency_id
        $getbuysellid = $this->geteachothertrade_id($buycurrency_id);
        $currency_id = $getbuysellid['currency_id'];
        $trade_currency_id = $getbuysellid['currency_trade_id'];
        $currency_collect = db('currency_collect')->where(array('member_id' => $this->member_id, 'currency_id' => $currency_id, 'trade_currency_id' => $trade_currency_id))->find();

        if ($currency_collect) {
            if (db('currency_collect')->where(array('currency_id' => $currency_id, 'trade_currency_id' => $trade_currency_id, 'member_id' => $this->member_id))->delete()) {

                $data['info'] = lang('lan_canceled');
                $this->mobileAjaxReturn($data);
            }
        } else {
            $data = array('currency_id' => $currency_id, 'trade_currency_id' => $trade_currency_id, 'member_id' => $this->member_id, 'add_time' => time());
            if (db('currency_collect')->insertGetId($data)) {

                $data['info'] = lang('lan_Add_success');
                $this->mobileAjaxReturn($data);
            }
        }

    }


    //获取用户挂单记录
    public function getuserOrders_history()
    {
        //历史挂单记录

        list($currency_id, $trade_currency_id) = explode('_', input('currency_id'));
       // $list = $this->getOrdersByUser_history(10, $currency_id, $trade_currency_id);
        $where['o.member_id'] = $this->member_id;
        $where['o.currency_id'] =    $currency_id;
        $where['o.currency_trade_id'] =    $trade_currency_id;
        $list = db('Orders')->field('o.fee,o.status,o.orders_id,o.add_time,o.member_id,o.currency_id,o.currency_trade_id,o.price,o.num,o.trade_num,o.type,t.price  as cprice,t.num  as cnum')->alias('o')
            ->join('yang_trade t ','o.orders_id = t.orders_id ')->where($where)->order("o.add_time desc")->limit(10)->select();


        foreach ($list as &$val) {
            $val['price_usd'] = format_price_usd($val['price'] / usd2cny());//对美元的价格
            $val['have_num'] = sprintf("%.6f", $val['num'] - $val['trade_num']);
            $val['price'] = sprintf("%.6f", $val['price']);
            $val['type_name'] = fomatOrdersType($val['type']);
            $val['totalprice'] = format_price($val['cprice']);
            $val['totalmoney'] = format_price($val['price'] * $val['num']);
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);

        }

        $this->mobileAjaxReturn($list);
    }
    public  function  index_bb(){
        $currency_makr =  input('currency','USDT','strtoupper');
        if(!in_array($currency_makr,['XRP','USDT'])){
            $currency_makr = 'USDT';
        }
        $data = self::bbExchange($currency_makr)[$currency_makr];
        foreach ($data as $k=>&$v){
           if ( $v['24H_change'] >0 ){
               $v['24H_change'] = '+'. $v['24H_change'];
           }
            !empty($v['trends']) ?:($v['trends'] = 0);
        }
        $this->mobileAjaxReturn(array_values($data));
    }

}