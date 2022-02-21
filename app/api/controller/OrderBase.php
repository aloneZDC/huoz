<?php
namespace app\api\controller;
use think\Exception;
use think\Db;

class OrderBase extends Base{
	protected $currency_id_mark;
    protected $currency;
    protected $currency_id_value;
    protected $currency_id_name;

	public function _initialize() {
		parent::_initialize();

        //积分类型信息
        $this->currency = self::currency();
        $this->currency_id_mark = array_column($this->currency, 'currency_mark', 'currency_id');
        $this->currency_id_name = array_column($this->currency, 'currency_name', 'currency_id');
        foreach ($this->currency as $k => $value) {
            $this->currency_id_value[$value['currency_id']] = $value;
        }
        $coin_list = array_values($this->coin_list);
        $currency_list = db("currency")->where(['currency_mark' => ['in', $coin_list]])->field('currency_id,currency_mark')->select();
        $this->coin_list = array_column($currency_list, 'currency_mark', 'currency_id');
    }

    /**
     * 返回指定状态的挂单记录
     * @param int $status -1 0 1 2
     * @param int $num 数量
     * @param int $currency_id 积分类型id
     */
    protected function getOrdersByStatus($status, $num, $currency_id,$toId='')
    {

        $where['currency_id'] = $currency_id;
        $where['status'] = $status;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $where['type'] = array('neq', 'onebuy');
        return db('Orders')->where($where)->limit($num)->order('trade_time desc')->select();
    }

    /**
     * 币标签ID转成2个currency_ID
     * @param string $buysell_id
     * @return bool|array
     */
    public function geteachothertrade_id($buysell_id){
        $buysell_id=explode('_', $buysell_id);
        if(empty($buysell_id['1'])||empty($buysell_id['0'])){
            return false;
        }
        if($buysell_id['1']==$buysell_id['0']){
            return false;
        }

        $where_currency_id['currency_id']=$buysell_id['0'];
        $currency_id=db('Currency')->where($where_currency_id)->find()['currency_id'];
        $where_currency_trade_id['currency_id']= $buysell_id['0'];
        $where_currency_trade_id['trade_currency_id']= array('like','%'.$buysell_id['1'].'%');
        $currency_trade_id=db('Currency')->where($where_currency_trade_id)->find();
        if(empty($currency_id)||empty($currency_trade_id)){
            return false;

        }else {
            $data['currency_id']=$currency_id;
            $data['currency_trade_id']=$buysell_id['1'];
            return $data;
        }
    }
    /**
     * 获取指定数量个人挂单记录
     * @param int $num 数量
     */
    protected function getOrdersByUser($num, $currency_id,$toId='')
    {
        $where2['currency_id'] = $currency_id;
        $cardinal_number2 = db('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['member_id'] = !empty(session('USER_KEY_ID')) ? session('USER_KEY_ID') : $this->member_id;
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = db('Orders')->where($where)->order("add_time desc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
            $list[$k]['type_name'] = fomatOrdersType($v['type']);
            $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
            $list[$k]['price_usd'] = sprintf('%.2f',round($v['price']/usd2cny(),2));
        }
        return $list;

    }

	/***
     * 获取BB兑换信息
     * @param $type all 全部
     * @from 0-api, 1-cron
     */
    public  function  bbExchange($type='all', $from=0){

        $coin_list = $this->coin_list ;
        if ($type != 'all' && in_array($type,$coin_list)){
            $coin_type = [];
            foreach ($coin_list as $coin_k=>$coin_v){
                if(strpos($type,$coin_v) !== false){
                    $coin_type[$coin_k] =$coin_v;
                }
            }
        }else{
            $coin_type = $coin_list;
        }
        $cahe_name = 'cahe_'.$type;

        $currency_data =  ($from==1) ? [] :  cache($cahe_name);
        if(empty($currency_data)){
            $currency=$this->currency;
            $currency_data = [];
        }else{
            $currency = [];
        }

        foreach ($currency as $k => $v) {
            if (!empty($v['trade_currency_id'])){
                $trade_currency_id = explode(',',$v['trade_currency_id']);
                foreach ($trade_currency_id as $vtId){
                    if(!empty( $coin_type[$vtId])){
                        $list = self::getCurrencyMessageById($v['currency_id'],$vtId,$from);
                        $list2=[];//self::getCurrencyMessageById2($v['currency_id'],$vtId);
                       // $trends = self::getTrends($v['currency_id'],$vtId);//获取走势图
                        unset($v['qianbao_key'],$v['qianbao_key1'],$v['rpc_user'],$v['rpc_user1'],$v['rpc_pwd'],$v['rpc_pwd1'],$v['rpc_url'],$v['rpc_url1'],$v['port_number'],$v['port_number1'],$v['summary_fee_address'],$v['summary_fee_pwd'],$v['qianbao_address'],$v['tibi_address'],$v['token_address']);
                        $currency_data[$coin_type[$vtId]][$k] = array_merge($list,$list2, $v);
                        $currency_data[$coin_type[$vtId]][$k]['currency_all_money'] = floatval($v['currency_all_money']);
                        $currency_data[$coin_type[$vtId]][$k]['currency_buy_fee'] = floatval($v['currency_buy_fee']);
                        $currency_data[$coin_type[$vtId]][$k]['currency_sell_fee'] = floatval($v['currency_sell_fee']);
                        $currency_data[$coin_type[$vtId]][$k]['trade_currency_mark'] = $this->currency_id_mark[$vtId];//db('Currency')->where(array('currency_id' => $vtId))->find()['currency_mark'];//交易的币英文名
                        $currency_data[$coin_type[$vtId]][$k]['trends'] = !empty($trends)?$trends:[];
                        $currency_data[$coin_type[$vtId]][$k]['trade_currency_id'] = $vtId;
                        $currency_data[$coin_type[$vtId]][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
                        // if($this->currency_id_mark[$vtId]=='KOK'){
                        //     $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                        // }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
                        //     $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
                        // }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
                        //     $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                        // }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
                        //     $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                        // }else {
                        //     $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                        // }
                        //添加交易兑取值
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);

//                                 if($this->currency_id_mark[$vtId]=='KOK'){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
//                                 }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
//                                 }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($BTC_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
//                                 }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($ETH_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
//                                 }else {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
//                                 }
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['max_price'] =  $currency_data[$coin_type[$vtId]][$k]['max_price'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['min_price'] =  $currency_data[$coin_type[$vtId]][$k]['min_price'];

//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_num'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_num'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_money'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_money'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_status'] =  $currency_data[$coin_type[$vtId]][$k]['new_price_status'];
//                                 if($currency_data[$coin_type[$vtId]][$k]['new_price_status']==1){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '+'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }elseif ($currency_data[$coin_type[$vtId]][$k]['new_price_status']==2){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '-'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }else {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= $currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }

                    }
                }
            }
        }
        if(!empty($currency)) cache($cahe_name,$currency_data,20);
        return $currency_data;
    }
    /**
     * 获取走势图
     * @param $currency_id
     * @return string
     *    * 新增加  $toId 目标兑换币
     */
    public function getTrends($currency_id,$toId='',$form=0)
    {
        //$trade = db()->query("SELECT price from yang_trade WHERE add_time>UNIX_TIMESTAMP(DATE_SUB(NOW(),INTERVAL  6 HOUR)) and currency_id = $currency_id and type = 'buy' order by add_time limit 100");
        //$trade = db()->query("SELECT price from yang_trade WHERE currency_id = $currency_id and type = 'sell' order by add_time limit 100");
        /*$where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $time = time();
        $trade = db('Trade')->field('price as max_price')->where($where)->where("add_time>$time-60*60*24")->order('add_time desc')->limit(35)->select();*/
        $s_name =  'ceche_getTrends_'.$currency_id.'_'.$toId;
        $return =  $retult = ($form ==1) ? [] :cache($s_name);
        if(empty($retult)){

            $model = db('Trade');
            $step = 3600;
            $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
            $where['currency_id'] = $currency_id;
            empty($toId)?:$where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $max_price = $model->field('max(price) as max_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min asc')->limit(35)->select();

            $model = db('Trade');
            $step = 3600;
            $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
            $where['currency_id'] = $currency_id;
            $where['type'] = 'buy';
            $min_price = $model->field('min(price) as min_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min asc')->limit(35)->select();

            foreach ($max_price as $k => $v) {
                $trade[$k] = array_merge($max_price[$k], $min_price[$k]);
            }

            $list = array();
            if (!empty($trade)) {
                for ($i = 0; $i < 35; $i++) {
                    if ($trade[$i]['max_price'] != null) {
                        if(isset($trade[$i])){
                            $list[$i] = floatval(($trade[$i]['max_price']+$trade[$i]['min_price'])/2);
                        } else {
                            $list[$i] = 0;
                        }

                        //$list[$i] = floatval($trade[$i]['max_price']);
                    }
                }
                //$max = max($list);
                $min = min($list);
                for ($i = 0; $i < count($list); $i++) {
                    //$list[$i] = (100 / count($list)) * $i . "," . (30 - (30 / (max($list)+min($list)) * $list[$i]));
                    //$list[$i] = (100 / count($list)) * $i . "," . (30-30*$list[$i]/$max);
                    $list[$i] =  ($list[$i]-$min)/33;
                }
                //$return = implode(" ", $list);
                $return = json_encode($list);
            } else {
                //$return = "0,29 100,29";
                $return = "0";
            }
            cache($s_name,$return,210);
        }
        return $return;
    }
    /**
     * 返回指定数量排序的挂单记录
     * @param char $type buy sell
     * @param int $num 数量
     * @param char $order 排序 desc asc
     * @param  int $toId 对币ID
     */
    protected function getOrdersByType($currencyid, $type, $num, $order,$toId='')
    {
        $where2['currency_id'] = $currencyid;
        $cardinal_number2 = db('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['type'] = array('eq', $type);
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currencyid;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = db('Orders')->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
            ->where($where)->group('price')->order("price $order, add_time asc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
//             $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
        }
        return $list;
    }

    //获取全部积分类型信息
    public static function currency()
    {
        return db('Currency')->order('sort ASC')->select();
        //return db('Currency')->order('sort ASC')->select();
    }
    /**
     * 获取当前积分类型的信息
     * @param int $id 积分类型id
     * @return 24H成交量 24H_done_num  24H成交额 24H_done_money 24H涨跌 24H_change 7D涨跌  7D_change
     * @return 最新价格 new_price 买一价 buy_one_price 卖一价 sell_one_price 最高价 max_price 最低价 min_price
     *
     * 新增加  $toId 目标兑换币
     */
    public  function getCurrencyMessageById($id,$toId='',$from=0)
    {
        static  $usd2cny  = 0;
        static $static_new_price = [];
        $usd2cny =  $usd2cny ?:  usd2cny();
        $all_name = 'rs_all' . $id.'_'.$toId;
        $data = ($from==1) ? [] : cache($all_name);
        $currency_mark_id = array_flip($this->currency_id_mark);
        $usdt_id = $currency_mark_id["USDT"];

        if (empty($data)) {
            $where['currency_id'] = $id;
            empty($toId)?: $where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $Currency_model = db('Currency');
            $trade_model = db('trade');

            $list = ($this->currency_id_value[$id])? :($Currency_model->where(array('currency_id' => $id,'is_line' => 1))->cache()->find());

            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新交易价格
            $data['new_price'] = \app\common\model\Trade::getLastTradePrice($id,$toId);
            //交易对 后面币种人民币价格
            $currency_trade_real_money = \app\common\model\Trade::getCurrencyTradeRealMoney($toId,NEW_PRICE_UNIT);
            //交易对 前面币种人民币价格
            $data['new_price_usd'] = $data['new_price_cny'] = keepPoint($data['new_price']*$currency_trade_real_money,2);

            //最新价格 20200612 改写 325-342
//            $order = 'add_time desc';
//            $rs = $trade_model->where($where)->order($order)->cache(true,60)->find();

//            if($toId == $usdt_id){
//                    $data['new_price'] = sprintf('%.8f',$rs['price']);
//                     $new_price = 1;
//                }else{
//                    if( isset($static_new_price[$toId."_".$usdt_id]) && !empty($static_new_price[$toId."_".$usdt_id])){
//                        $new_price = $static_new_price[$toId."_".$usdt_id];
//                    }else{
//                        $usdt_new_price =  self::getCurrencyMessageById($toId,$usdt_id);
//                        $new_price = $static_new_price[$toId."_".$usdt_id] = $usdt_new_price['new_price'];
//                    }
//                $new_price = empty($new_price)?1:$new_price;
//                $data['new_price'] = sprintf('%.8f',$rs['price']);
//            }
//            $data['new_price_usd'] = sprintf('%.4f',round($rs['price']*$new_price,2));//对应美元价格
//            $data['new_price_cny'] = sprintf('%.4f',round($rs['price']*$new_price*$usd2cny/1,2));//对应人民币价格

            //判断价格是升是降
            //$re = $trade_model->where($where)->where("add_time<$old_time")->order($order)->find();
            // $lastdate = $trade_model->field('price')->where("add_time <= 60*60*24 and currency_id={$id} ".($toId?"and currency_trade_id='{$toId}'":"")." and type='sell' ")->order('add_time desc')->limit(1)->find();
//            if ($lastdate['price'] > $rs['price']) {
//                //说明价格下降
//                $data['new_price_status'] = 0;
//            } else {
//                $data['new_price_status'] = 1;
//            }

            //24H涨跌
//            $lastdate = $trade_model->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$id} ".($toId?"and currency_trade_id='{$toId}'":"")." and type='sell' ")->order('add_time desc')->limit(1)->find();

            $where2['currency_id'] = $id;
            empty($toId)?: $where2['currency_trade_id'] = $toId;
            $where2['type'] = 'sell';
            //3D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*3")->order($order)->find();
//             $data['3D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['3D_change'] == 0) {
//                 $data['3D_change'] = '0.00';
//             }

//             //7D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*7")->order($order)->field('price')->find();
//             $data['7D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['7D_change'] == 0) {
//                 $data['7D_change'] = '0.00';
//             }

//             //买一价
//             $where['type'] = 'buy';
//             $rs = $trade_model->field('price,add_time')->where($where)->order($order)->find();
//             $data['buy_one_price'] = $rs['price'];

//             //卖一价
//             $where['type'] = 'sell';
//             $rs = $trade_model->field('price,add_time')->where($where)->order("price")->find();
//             $data['sell_one_price'] = $rs['price'];


            //24H成交量
            $rs = $trade_model->field("SUM(num) as num ,SUM(num * price) as numPrice")->where($where)->where("add_time>$time-60*60*24")->find();

            if ($rs['num'] == 0) {
                $data['24H_done_num'] = '0.00';
            }else {

                $data['24H_done_num'] =  round($rs['num'] * 2 + $list['num_number'],6);
            }

            //24H成交额
//            $rs = $trade_model->field('num*price')->where($where)->where("add_time>$time-60*60*24")->sum('num*price');
            if ($rs['numPrice'] == 0) {
                $data['24H_done_money'] = '0.00';
            } else {

                $data['24H_done_money'] =   round($rs['numPrice'] * 2 + $list['num_number'] * $data['new_price'],6);
            }

            //24H最低价
            $sql_time = $time - 60 * 60 * 24 ;
            $rs = $trade_model->field('price,min(price) as minprice,max(price) as maxprice')->where($where)->where("add_time>$sql_time")->find();
            $data['min_price'] = $rs['minprice'];
            if ($data['min_price'] == 0) {
                $data['min_price'] = '0.00';
            }

            //24H最高价
            $data['max_price'] = $rs['maxprice'];
            if ($data['max_price'] == 0) {
                $data['max_price'] = '0.00';
            }

            if ($rs['price'] > $data['new_price'] ) {
                //说明价格下降
                $data['new_price_status'] = 0;
            } else {
                $data['new_price_status'] = 1;
            }
            $data['24H_change'] = !empty( $rs['price'])? sprintf("%.2f", ($data['new_price']  - $rs['price']) / $rs['price'] * 100):0;
            $data['24H_change_price'] = sprintf("%.2f", ($data['new_price']  - $rs['price']));//24H价格变化值
            if ($data['24H_change'] == 0) {
                $data['24H_change'] = '0.00';
            }

            cache($all_name, $data, 15);
        }

        //返回
        return $data;
    }
    /**
     * 返回指定状态的挂单记录
     * @param int $status -1 0 1 2
     * @param int $num 数量
     * @param int $currency_id 积分类型id
     */
    protected function getOrdersByStatus_all($status, $num, $currency_id,$toId)
    {
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $where['status'] = array('in','2,5');;
        $where['type'] = array('neq', 'onebuy');
        $where['trade_time']=array('elt',time());
        return db('Orders')->where($where)->limit($num)->order('trade_time desc')->select();
    }
    /**
     * 币标签转成2个currency_ID
     * @param string $buysell_name
     * @return bool|array
     */
    public function geteachothertrade_mark($buysell_name){
        $buysell_name=explode('_', $buysell_name);
        if($buysell_name['1']==$buysell_name['0']){
            return false;
        }
        if(empty($buysell_name['1'])||empty($buysell_name['0'])){
            return false;
        }
        $where_currency_trade_id['currency_mark']=$buysell_name['1'];
        $currency_trade_id=db('Currency')->where($where_currency_trade_id)->find();
        $where_currency_id['trade_currency_id']= array('like','%'.$currency_trade_id['currency_id'].'%');
        $where_currency_id['currency_mark']=$buysell_name['0'];
        $currency_id=db('Currency')->where($where_currency_id)->find();
        if(empty($currency_id)||empty($currency_trade_id)){
            return false;

        }else {
            $data['currency_id']=$currency_id['currency_id'];
            $data['currency_trade_id']=$currency_trade_id['currency_id'];
            return $data;
        }
    }

    /**
     *
     * @param int $currency_id 积分类型id
     * @return array 积分类型结果集
     */
    protected function getCurrencyByCurrencyId($currency_id = 0)
    {
        if (empty($currency_id)) {
            $where['currency_id'] = array('gt', $currency_id);
        } else {
            $where['currency_id'] = array('eq', $currency_id);
        }
        //获取交易积分类型信息
        $list = db('Currency')->field("currency_id,currency_name,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in,tcoin_fee,trade_day6,trade_day7,currency_type,token_address,tibi_address,currency_min_tibi,trade_min_num")->where($where)->select();
        if (!empty($currency_id)) {
            return $list[0];
        } else {
            return $list;
        }
    }

    /**
     *
     * @param int $currency_id 积分类型id
     * @return array 积分类型结果集
     */
    protected function getCurrencyByCurrencyId2($currency_id = 0)
    {
        if (empty($currency_id)) {
            $where['currency_id'] = array('gt', $currency_id);
        } else {
            $where['currency_id'] = array('eq', $currency_id);
        }
        //获取交易积分类型信息
        $list = db('Currency2')->field("currency_id,currency_name,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in")->where($where)->select();
        if (!empty($currency_id)) {
            return $list[0];
        } else {
            return $list;
        }
    }

    /**
     * 返回昨天最后价格
     * @param int $currency_id 数量
     * @param char $order 排序 desc asc
     * @param int $toId 对币ID
     */
    protected function getlastmessage($currency_id,$toId='')
    {
        $lastdate = db('Trade')->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$currency_id} ".($toId? " and currency_trade_id = '{$toId}'" : "")." and type='buy' ")->order('add_time desc')->limit(1)->find();
        $lastdate = $lastdate['price'];
        if(empty($lastdate)){
            if($toId==9){

                $lastdate=db('Currency')->field('first_price')->where("currency_id={$currency_id}")->find();
                $lastdate = $lastdate['first_price'];
                if(empty($lastdate)){
                    $lastdate='0';
                }
            }

        }
        return $lastdate;
    }

    /**
     * 设置账户资金
     * @param int $currency_id 积分类型ID
     * @param int $num 交易数量
     * @param char $inc_dec setDec setInc 是加钱还是减去
     * @param char forzen_num num
     */
    protected function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
    {
        $inc_dec = strtolower($inc_dec);
        $field = strtolower($field);
        //允许传入的字段
        if (!in_array($field, array('num', 'forzen_num'))) {
            return false;
        }
        //如果是RMB
        if ($currency_id == 0) {
            //修正字段
            switch ($field) {
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                case 'num':
                    $field = 'rmb';
                    break;
            }
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        }
    }
    /**
     * 获取指定数量个人历史挂单记录
     * @param int $num 数量
     */
    protected function getOrdersByUser_history($num, $currency_id,$toId='')
    {
        $where2['currency_id'] = $currency_id;
        $cardinal_number2 = db('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['member_id'] = !empty(session('USER_KEY_ID')) ? session('USER_KEY_ID') : $this->member_id;
        $where['status'] = array('in', array(-1, 2));
        //一周时间限制
        $where['add_time']=array('egt',time()-60*60*24*7);
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = db('Orders')->where($where)->order("add_time desc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
            $list[$k]['type_name'] = fomatOrdersType($v['type']);
            $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
            $list[$k]['price_usd'] = sprintf('%.2f',round($v['price']/usd2cny(),2));
        }
        return $list;

    }


}
