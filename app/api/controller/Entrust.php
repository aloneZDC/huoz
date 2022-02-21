<?php

namespace app\api\controller;


use app\common\model\Currency;
use app\common\model\CurrencyArea;
use think\Exception;
use think\Page,think\Db;

class Entrust extends OrderBase
{
    protected $public_action = ["icon_info",'getIndex']; //无需登录即可访问
    public function _initialize() {
        parent::_initialize();
    }

    public function prokline()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
           self::output(10100, lang('lan_modifymember_please_login_first'));
        }

        $currency_id = input('post.currency', '', 'strval');
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }

        $currency_info = Db::name('Currency')->field('currency_id,currency_mark')->where(array('currency_mark' => $currency_id))->find();

        if (empty($currency_info)) {
            self::output(10102, lang('lan_modifymember_parameter_error'));
        }

        $url = url("mobile/index/screen", ['currency_id' => $currency_info['currency_id'], 'currency' => $currency_info['currency_mark']], false, true);
        self::output(10000, lang('lan_user_request_successful'), $url);
    }

    public function tradall_buy()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        $currency_id = input('post.currency', '', 'strval');

        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }

        $currency =Db::name('Currency')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();
        //显示委托记录
        $buy_record = $this->getOrdersByType($currency['currency_id'], 'buy', 9, 'desc');
        $sell_record2 = $this->getOrdersByType($currency['currency_id'], 'sell', 9, 'asc');
        $sell_record = array_reverse($sell_record2);

        if ($currency['limit_repeal'] == null) {
            $currency['limit_repeal'] = '';
        }

        if(!empty($buy_record)){
            foreach ($buy_record as &$value){
                $value['price'] = format_price($value['price']);
                $value['num'] = format_num($value['num']);
            }
        }

        if(!empty($sell_record)){
            foreach ($sell_record as &$value){
                $value['price'] = format_price($value['price']);
                $value['num'] = format_num($value['num']);
            }
        }

        $data = [
            'buy_record' => $buy_record,
            'sell_record' => $sell_record,
            'currency' => $currency
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    public function tradall_buy_login()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $currency_id = intval(input('post.currency'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();

        //个人账户资产
        $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
        $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
        $usermb =Db::name('Member')->field('rmb,forzen_rmb')->where('member_id=' . $this->member_id)->find();

        //成交记录
        $trade = $this->getOrdersByStatus(2, 10, $currency['currency_id']);
        $user_total = $user_currency_money['currency']['num'] + $user_currency_money['currency']['forzen_num'];
        if(!empty($trade)){
            foreach ($trade as &$value) {
                $value['price'] = format_price($value['price']); //成交价格
                $value['num'] = format_num($value['num']); //数量
                $value['trade_num'] = format_num($value['trade_num']); //成交量
                $value['trade_total'] = format_price($value['price'] * $value['num']); //总计
            }
        }

        $data = [
            'user_total' => $user_total,
            'num' =>  $this->getUserMoney($currency['currency_id'], 'num'),
            'forzen_num' => $this->getUserMoney($currency['currency_id'], 'forzen_num')
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    /**
     *
     */
    public function tradall_sellang()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        $currency_id = intval(input('post.currency'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();
        //显示委托记录
        $buy_record = $this->getOrdersByType($currency['currency_id'], 'buy', 9, 'desc');
        $sell_record2 = $this->getOrdersByType($currency['currency_id'], 'sell', 9, 'asc');
        $sell_record = array_reverse($sell_record2);

        if ($currency['limit_repeal'] == null) {
            $currency['limit_repeal'] = '';
        }

        if(!empty($buy_record)){
            foreach ($buy_record as &$value){
                $value['price'] = format_price($value['price']);
                $value['num'] = format_num($value['num']);
            }
        }

        if(!empty($sell_record)){
            foreach ($sell_record as &$value){
                $value['price'] = format_price($value['price']);
                $value['num'] = format_num($value['num']);
            }
        }

        $data = [
            'buy_record' => $buy_record,
            'sell_record' => $sell_record,
            'currency' => $currency
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    public function tradall_sell_login()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $currency_id = intval(input('post.currency'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();

        //个人账户资产
        $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
        $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
        $usermb =Db::name('Member')->field('rmb,forzen_rmb')->where('member_id=' . $this->member_id)->find();
        //成交记录
        $trade = $this->getOrdersByStatus(2, 10, $currency['currency_id']);
        $user_total = $user_currency_money['currency']['num'] + $user_currency_money['currency']['forzen_num'];
        if(!empty($trade)){
            foreach ($trade as &$value) {
                $value['price'] = format_price($value['price']); //成交价格
                $value['num'] = format_num( $value['num']); //数量
                $value['trade_num'] = format_num($value['trade_num']); //成交量
                $value['trade_total'] = format_price( $value['price'] * $value['num']); //总计
            }
        }

        $data = [
            'user_total' => $user_total,
            'user_currency_money' => $user_currency_money,
            'usermb' => $usermb,
            'trade' => $trade,
            'session' => $this->member_id,
            'currency' => $currency
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    public function tradall_sell_login_app()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $currency_id = intval(input('post.currency'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();

        //个人账户资产
        $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
        $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
        $usermb =Db::name('Member')->field('rmb,forzen_rmb')->where('member_id=' . $this->member_id)->find();
        //成交记录
        $trade = $this->getOrdersByType($currency['currency_id'], 'buy', 5, 'desc');
        $user_total = $user_currency_money['currency']['num'] + $user_currency_money['currency']['forzen_num'];
        if(!empty($trade)){
            foreach ($trade as &$value) {
                $value['price'] = format_price( $value['price']); //成交价格
                $value['num'] = format_num($value['num']); //数量
                $value['trade_num'] = format_num( $value['trade_num']); //成交量
                $value['trade_total'] = format_price( $value['price'] * $value['num']); //总计
            }
        }

        $data = [
            'user_total' => $user_total,
            'user_currency_money' => $user_currency_money,
            'usermb' => $usermb,
            'trade' => $trade,
            'session' => $this->member_id,
            'currency' => $currency
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    public function kill_order()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);

        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_mark')->where(array('currency_id' => $getbuysellid['currency_id'], 'is_line' => 1))->find();
        $trade_currency =Db::name('Currency')->where(array('currency_id' => $getbuysellid['currency_trade_id'], 'is_line' => 1))->order('currency_id desc')->find();
        if (empty($currency)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        //个人挂单记录
        //dump($this->getOrdersByUser(6,$currency['currency_id']));exit;
        $orders = $this->getOrdersByUser(30, $currency['currency_id'], $trade_currency['currency_id']);
        $data = [
            'user_orders' => $orders,
            'currency' => $currency,
            'trade_currency' => $trade_currency
        ];
        self::output(10000, lang('lan_user_request_successful'), $orders);
    }

    //历史委托
    public function kill_order_app()
    {
        //声明本方法的请求方式(必须)
       parent::method_filter();

        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);

        empty($getbuysellid['currency_id']) ? : $currency =Db::name('Currency')->field('currency_id,currency_name,currency_mark')->where(array('currency_id' => $getbuysellid['currency_id'], 'is_line' => 1))->find();
        empty($getbuysellid['currency_trade_id']) ? :  $trade_currency =Db::name('Currency')->where(array('currency_id' => $getbuysellid['currency_trade_id'], 'is_line' => 1))->order('currency_id desc')->find();
//        if (empty($currency)) {
//            self::output(10101, lang('lan_modifymember_parameter_error'));
//        }
        //个人挂单记录
        //dump($this->getOrdersByUser(6,$currency['currency_id']));exit;

        $where['o.member_id'] =  $this->member_id;

        $host_type = intval(input('host_type'))?:1;
        ($host_type == 1) ?( $where['o.status'] = array('in', array(0, 1))): ($where['o.status'] = array('in', array(2, -1)));

       empty($currency['currency_id'])?: $where['o.currency_id'] = $currency['currency_id'];

        empty($trade_currency['currency_id'])?:$where['o.currency_trade_id'] = $trade_currency['currency_id'];

        $type = trim(strval(input('type')));
        empty($type)?: $where['o.type'] = $type;

        $post_currency_mark=strval(input('currency_mark'));
        $post_trade_currency_mark=strval(input('trade_currency_mark'));
        if($post_currency_mark &&  $post_trade_currency_mark){
            $flip_currency_id_mark = array_flip($this->currency_id_mark);
            $where['o.currency_id'] =    $flip_currency_id_mark[$post_currency_mark];
            $where['o.currency_trade_id'] =    $flip_currency_id_mark[$post_trade_currency_mark];
        }

        $pages = intval(input('post.page'))?:1;
        $rows = intval(input('post.rows'))?:10;
        $page = (($rows * ($pages - 1))?:0).','.$rows;
//        $list =Db::name('Orders')->field('o.fee,o.status,o.orders_id,o.add_time,o.trade_time,o.member_id,o.currency_id,o.currency_trade_id,o.price,o.num,o.trade_num,o.type,t.price  as cprice,t.num  as cnum')
        $list =Db::name('Orders')->field('o.fee,o.status,o.orders_id,o.add_time,o.trade_time,o.member_id,o.currency_id,o.currency_trade_id,o.price,o.num,o.trade_num,o.type')
            ->alias('o')
//            ->join('yang_trade t','o.orders_id = t.orders_id',' left')
            ->where($where)->order("o.add_time desc")->limit($page)->select();
        $retule_list = [];
        foreach ($list as $k => &$v) {
            $where2['currency_id'] =$v['currency_id'];
            $cardinal_number =Db::name('Currency')->field("cardinal_number")->where($where2)->value('cardinal_number');
            $v['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $v['cardinal_number'] = $cardinal_number;
            $v['type_name'] = fomatOrdersType($v['type']);
            $v['price'] = keepPoint($v['price'], 6);
            $v['price_usd'] = format_price_usd($v['price']/usd2cny());
//            $v['cprice'] = keepPoint($v['cprice'], 6)?:0;
//            $v['totalMeney'] = keepPoint(($v['cprice'] * $v['cnum']), 6)?:0;
//            $v['avgcPrice'] =  keepPoint($v['cprice'], 6)?:0;
//            $v['totalNum'] =   keepPoint($v['cnum'], 2)?:0;
            $v['trade_num'] =   keepPoint($v['trade_num'], 2)?:0;
            $v['num'] =   keepPoint($v['num'], 2)?:0;
            $v['currenc_mark'] =   $this->currency_id_name[$v['currency_id']];
            $v['trade_currency_mark'] =   $this->currency_id_name[$v['currency_trade_id']];
//            $v['totalfee'] = keepPoint(($v['cprice'] * $v['cnum']* $v['fee']), 6)?:0;
//            $v['add_time'] = date('H:i Y-m-d',$v['add_time']);
//            $v['trade_time'] = date('H:i Y-m-d',$v['trade_time']);

//            if(!empty($retule_list[$v['orders_id']])){
//                $retule_list[$v['orders_id']]['totalMeney'] +=  (keepPoint($v['totalMeney'], 6)?:0) ;
//                $retule_list[$v['orders_id']]['avgcPrice'] = keepPoint(($retule_list[$v['orders_id']]['avgcPrice'] + ($v['avgcPrice']?:0))/2, 6);
//                $retule_list[$v['orders_id']]['totalNum'] += ( keepPoint($v['totalNum'], 2)?:0) ;
//                $retule_list[$v['orders_id']]['totalfee'] +=  (keepPoint($v['totalfee'], 6)?:0) ;
//            }else{
                $retule_list[$v['orders_id']] = $v;
//            }
        }
        $data = [
            'user_orders' =>  array_values($retule_list),
            'coin_list' => $this->coin_list
        ];
        self::output(10000, lang('lan_user_request_successful'),$data) ;
    }



    public function kill_order_app_details()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);

        $orders_id = input('orders_id');

        if(empty($orders_id)){
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }

        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_mark')->where(array('currency_id' => $getbuysellid['currency_id'], 'is_line' => 1))->find();
        $trade_currency =Db::name('Currency')->where(array('currency_id' => $getbuysellid['currency_trade_id'], 'is_line' => 1))->order('currency_id desc')->find();
        if (empty($currency)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        //个人挂单记录
        //dump($this->getOrdersByUser(6,$currency['currency_id']));exit;

        $where2['currency_id'] = $currency['currency_id'];
        $cardinal_number2 =Db::name('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['o.member_id'] =  $this->member_id;


        $where['o.orders_id'] = $orders_id;


        $list =Db::name('Orders')->field('o.orders_id,o.add_time,o.member_id,o.currency_id,o.currency_trade_id,o.price,o.num,o.trade_num,o.type,t.price  as cprice,t.num  as cnum,t.fee  as cfee,t.add_time  as cadd_time')
            ->alias('o')
            ->join('yang_trade t ','o.orders_id = t.orders_id','left')->where($where)->order("o.add_time desc")->select();
        $retule_list = [];
        foreach ($list as $k => &$v) {
            $v['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $v['cardinal_number'] = $cardinal_number;
            $v['type_name'] = fomatOrdersType($v['type']);
            $v['price'] = format_price($v['price']);
            $v['price_usd'] = format_price_usd($v['price']/usd2cny());
            $v['cprice'] = $v['cprice']?:0;
            $v['totalMeney'] =format_price( ($v['cprice'] * $v['cnum']))?:0;
            $v['avgcPrice'] =  format_price($v['cprice'])?:0;
            $v['totalNum'] =   format_num($v['cnum'])?:0;
            $v['cfee'] =   $v['cfee']?:0;
            $v['currenc_mark'] =   $this->currency_id_mark[$v['currency_id']];
            $v['trade_currency_mark'] =   $this->currency_id_mark[$v['currency_trade_id']];
            if (!empty($retule_list[$v['orders_id']])){

                $retule_list[$v['orders_id']]['totalMeney'] = format_price(($retule_list[$v['orders_id']]['totalMeney'] + $v['totalMeney'])/2);
                $retule_list[$v['orders_id']]['avgcPrice'] = format_price(($retule_list[$v['orders_id']]['avgcPrice'] + $v['avgcPrice'])/2);
                $retule_list[$v['orders_id']]['cnum'] = format_num(($retule_list[$v['orders_id']]['cnum'] + $v['cnum'])/2);
                $retule_list[$v['orders_id']]['cfee'] = ($retule_list[$v['orders_id']]['cfee'] + $v['cfee'])/2;
                $retule_list[$v['orders_id']]['details'][] =   [
                    'cadd_time'=>$v['cadd_time'],
                    'cprice'=>$v['cprice'],
                    'cnum'=>$v['cnum'],
                    'cfee'=>$v['cfee'],
                    'currency_id'=> $v['currency_id'],
                    'currency_trade_id'=>$v['currency_trade_id'],
                    'currenc_mark'=> $this->currency_id_mark[$v['currency_id']],
                    'trade_currency_mark'=>$this->currency_id_mark[$v['currency_trade_id']]
                ];
            }else{
                $retule_list[$v['orders_id']] = [
                    'totalMeney'=> $v['totalMeney']?:0,
                    'avgcPrice'=> $v['cprice'],
                    'cnum'=> $v['cnum'],
                    'cfee'=> $v['cfee'],
                    'type'=> $v['type'],
                    'currency_id'=> $v['currency_id'],
                    'currency_trade_id'=>$v['currency_trade_id'],
                    'currenc_mark'=> $this->currency_id_mark[$v['currency_id']],
                    'trade_currency_mark'=>$this->currency_id_mark[$v['currency_trade_id']]
                ];

                $retule_list[$v['orders_id']]['details'][] =   [
                    'cadd_time'=>$v['cadd_time'],
                    'cprice'=>$v['cprice'],
                    'cnum'=>$v['cnum'],
                    'cfee'=>$v['cfee'],
                    'currency_id'=> $v['currency_id'],
                    'currency_trade_id'=>$v['currency_trade_id'],
                    'currenc_mark'=> $this->currency_id_mark[$v['currency_id']],
                    'trade_currency_mark'=>$this->currency_id_mark[$v['currency_trade_id']]
                ];
            }
        }
        $data = [
            'user_orders' =>  array_values($retule_list),
        ];
        self::output(10000, lang('lan_user_request_successful'),$data) ;
    }
    public function trade_history()
    {


        //声明本方法的请求方式(必须)
        parent::method_filter();
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $currency_id = intval(input('post.currency'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();
        $where['currency_id'] = $currency['currency_id'];
        $where['member_id'] = $this->member_id;
        $pages = intval(input('post.page'))?:1;
        $rows = intval(input('post.rows'))?:10;
        $page = (($rows * ($pages - 1))?:0).','.$rows;
        $list =Db::name('Trade')->field('type,price,num,add_time')->where($where)->limit($page)->order('add_time desc')->select();

        $data = [
            'currency' => $currency,
            'list' => $list
        ];
        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    public function icon_info()
    {
        $currency_currency=strval(input('post.currency'));
        $language = strval(input('language','tw'));
        $result = \app\common\model\Trade::icon_info($this->member_id,$currency_currency,$language);
        return $this->output_new($result);

        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency'));
        $language = strval(input('language','zh'));
        //获取买卖currency_id

        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);
        if (empty($getbuysellid)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_mark')->where(array('currency_id' => $getbuysellid['currency_id'], 'is_line' => 1))->find();
        $trade_currency =Db::name('Currency')->field('currency_id as trade_currency_id,currency_name as trade_currency_name,currency_mark as trade_currency_mark')->where(array('currency_id' => $getbuysellid['currency_trade_id'], 'is_line' => 1))->find();

        if (empty($currency)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }

        //积分类型信息
        $currency_message3 = parent::getCurrencyMessageById($getbuysellid['currency_id'],$getbuysellid['currency_trade_id']);
//         $currency_message2 = parent::getCurrencyMessageById2($getbuysellid['currency_id'],$getbuysellid['currency_trade_id']);
//         $currency_message3 = array_merge($currency_message, $currency_message2);

                                        // if($this->currency_id_mark[$getbuysellid['currency_trade_id']]=='KOK'){
                                        //     $currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price']/usd2cny()) ;
                                        // }elseif($this->currency_id_mark[$getbuysellid['currency_trade_id']]=='USDT' ) {
                                        //     $currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price']) ;
                                        // }elseif($this->currency_id_mark[$getbuysellid['currency_trade_id']]=='BTC' ) {
                                        //    // $BTC_KOK_price=self::getCurrencyMessageById(1,9)['new_price'];
                                        //     $currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price']/usd2cny()) ;//format_price_usd(($BTC_KOK_price*$currency_message3['new_price'])/usd2cny());
                                        // }elseif ($this->currency_id_mark[$getbuysellid['currency_trade_id']]=='ETH' ){
                                        //     //$ETH_KOK_price=self::getCurrencyMessageById(3,9)['new_price'];
                                        //    $currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price']/usd2cny()) ;//format_price_usd(($ETH_KOK_price*$currency_message3['new_price'])/usd2cny());
                                        // }else {
                                        //    $currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price']/usd2cny()) ;
                                        // }

        //$currency_message3['new_price_usd'] = format_price_usd($currency_message3['new_price'] / usd2cny());//对美元的价格
        $currency_message3['new_price'] =keepPointV2($currency_message3['new_price'], 6);
        $currency_message3['min_price'] =keepPointV2($currency_message3['min_price'], 6);
        $currency_message3['max_price'] =keepPointV2($currency_message3['max_price'], 6);
        $currency_message3['done_num_24H'] =keepPointV2($currency_message3['24H_done_num'], 2);
        $currency_message3['done_money_24H'] =keepPointV2($currency_message3['24H_done_money'], 6);
        $currency_message3['max_price'] =keepPointV2($currency_message3['max_price'], 6);

        $currency_message3['24H_done_num'] = keepPointV2($currency_message3['24H_done_num'], 2);
        if($this->exchange_rate_type=='CNY') {
            $currency_message3['new_price_usd'] = sprintf('%.2f',round($currency_message3['new_price_cny'],2));
        } else {
            $currency_message3['new_price_usd'] = sprintf('%.4f',round($currency_message3['new_price_cny'],2));
        }
        $currency_message3['new_price_unit'] = $this->exchange_rate_type;

        if($currency_message3['new_price_status']==1){
            $currency_message3['change_24H']= '+'. keepPointV2($currency_message3['24H_change'], 2);
        }elseif ($currency_message3['new_price_status']==2){
            $currency_message3['change_24H']= '-'. keepPointV2($currency_message3['24H_change'], 2);
        }

        //币详细信息
        //通过英文标识所对应的积分id查找关联表
        $cid = $currency['currency_id'];
        $cid_value = Db::name('Currency_introduce')
            ->where('yang_currency_introduce.currency_id=' . $cid)
            ->field("yang_currency_introduce.*")
            ->find();
        switch($language){
            case 'zh-tw':
                $introduce =Db::name('Currency_introduce_tc')->where('currency_id='.$cid)->find();
                break;
            case 'en-us':
                $introduce =Db::name('Currency_introduce_en')->where('currency_id='.$cid)->find();
                break;
            case 'th-th':
                $introduce =Db::name('Currency_introduce_th')->where('currency_id='.$cid)->find();
                break;
            default:
                $introduce =Db::name('Currency_introduce')->where('currency_id='.$cid)->find();
                break;
        }
        $cid_value['feature'] = html_entity_decode($introduce['feature']);
        $cid_value['short'] = html_entity_decode($introduce['short']);
        $cid_value['advantage'] = html_entity_decode($introduce['advantage']);
        //是否默认
        //收藏
        $member_id = $this->member_id;
        $is_defaule= 0;
        if (!empty($member_id)) {
            $currency_collect =db('currency_collect')->where(array('member_id' => $this->member_id, 'currency_id' => $getbuysellid['currency_id'],'trade_currency_id'=>$getbuysellid['currency_trade_id']))->find();
            if (!empty($currency_collect)) {
                $is_defaule=1;
            }
        }

        $special_area = CurrencyArea::area_info($currency['currency_id']);
        $data=[
            'is_defaule'=>$is_defaule,
            'currency_message'=>$currency_message3,
            'currency'=>array_merge($currency,$trade_currency),
            'cid_value'=>$cid_value,
            'special_area' => $special_area['result'],
        ];
        self::output(10000, lang('lan_user_request_successful'),$data);
    }


    public function trade_history_app()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter();

        if (!self::checkLogin()) {
           self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        list($currency_id,$trade_currency_id) = explode("_",input('post.currency','1_2'));
        if (empty($currency_id)) {
            self::output(10101, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();
        $trade_currency =Db::name('Currency')->field('currency_id,currency_name,currency_logo,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id')->where(array('currency_id' => $trade_currency_id, 'is_line' => 1))->find();
        $where['currency_id'] = $currency['currency_id'];
        empty($trade_currency['currency_id'])?:$where['trade_currency_id'] = $trade_currency['currency_id'];
        $where['member_id'] = $this->member_id;

        $pages = intval(input('post.page'))?:1;
        $rows = intval(input('post.rows'))?:10;
        $page = (($rows * ($pages - 1))?:0).','.$rows;

        $list =Db::name('Trade')->field('type,price,num,add_time')->where($where)->limit($page)->order('add_time desc')->select();

        $data = [
            'currency' => $currency,
            'list' => $list
        ];
        self::output(10000, lang('lan_user_request_successful'), $list);
    }

    /**
     *  获取 买卖盘
     * @param unknown str $type          type  买卖  sell   buy
     * @param unknown Int $currency_id   货币id
     */
    public function getSellBuyByCurrencyIdType($type, $currency_id)
    {
        $type = strtoupper($type);
        if ($type == 'BUY') {
            $sql = "SELECT SUdb(num)AS num,price,trade_num  from  yang_orders  where type = '" . $type . "'
    			and currency_id  =" . $currency_id . " GROUP BY  price desc";
        } else {
            $sql = "SELECT SUM (num)AS num,price,trade_num  from  yang_orders  where type = '" . $type . "'
    			and currency_id  =" . $currency_id . " GROUP BY  price";
        }
        $list =Db::query($sql);
        return $list;
    }

    public function trade_ajax()
    {
        $term = input('post.term');
        $all_name = 'rs_all_currency_trade_ajax';
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            $currency = $this->currency();
            foreach ($currency as $k => $v) {
                $list = $this->getCurrencyMessageById($v['currency_id']);
                $currency[$k] = array_merge($list, $currency[$k]);
                $currency[$k]['currency_all_money'] = floatval($v['currency_all_money']);
            }
            cache($all_name, $currency, 120);
        }
        $currency = cache($all_name);
        if ($term == 1) {
            foreach ($currency as $key => $value) {
                $price[$key] = $value['new_price'];
            }
            array_multisort($price, SORT_NUMERIC, SORT_DESC, $currency);
        } else if ($term == 2) {
            foreach ($currency as $key => $value) {
                $num[$key] = $value['24H_done_num'];
            }
            array_multisort($num, SORT_NUMERIC, SORT_DESC, $currency);
        } else if ($term == 3) {
            foreach ($currency as $key => $value) {
                $money[$key] = $value['24H_done_money'];
            }
            array_multisort($money, SORT_NUMERIC, SORT_DESC, $currency);
        } else {
            foreach ($currency as $key => $value) {
                $change[$key] = $value['24H_change'];
            }
            array_multisort($change, SORT_NUMERIC, SORT_DESC, $currency);
        }
        $this->ajaxReturn($currency);
    }

    //获取卖挂单记录
    public function getOrdersellang()
    {

        $getOrders2 = $this->getOrdersByType(input('post.currency_id'), input('post.type'), 9, 'asc');
        $getOrders = array_reverse($getOrders2);
        self::output(10000, lang('lan_user_request_successful'), $getOrders);
        // $this->ajaxReturn($getOrders);

    }

    //获取买挂单记录
    public function getOrderbuy()
    {

        $list = $this->getOrdersByType(input('post.currency_id'), input('post.type'), 9, 'desc');
        self::output(10000, lang('lan_user_request_successful'), $list);

    }

    //获取挂单记录trade
    public function gettrade()
    {
        $list = $this->getOrdersByStatus(2, 10, input('post.currency_id'));
        // $this->ajaxReturn($this->getOrdersByStatus(2, 10, input('post.currency_id')));
        self::output(10000, lang('lan_user_request_successful'), $list);


    }


    private function formatNumber(&$val, $currency_id)
    {
        $num = $val['num'] - $val['trade_num'];
        switch ($currency_id) {
            case Currency::BTC_ID:
                $val['num'] = keepPoint($num, 6);
                $val['price']= keepPoint($val['price'], 2);
                break;
            case Currency::ETH_ID:
                $val['num'] = keepPoint($num, 4);
                $val['price']= keepPoint($val['price'], 2);
                break;
            case Currency::XRP_ID:
            case Currency::IWC_ID:
                $val['num'] = keepPoint($num, 2);
                $val['price'] = keepPoint($val['price'], 5);
                break;
            default:
                $val['num'] = keepPoint($num, 6);
                $val['price'] = keepPoint($val['price'], 6);
        }
    }
    public function getIndex()
    {
        $currency_currency=strval(input('post.currency_id'));
        $number = intval(input('post.number', 5));
        $result = \app\common\model\Trade::getBuySellTrade($currency_currency,$number);
        return $this->output_new($result);


        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency_id'));
        if(empty($buycurrency_id) || strpos($buycurrency_id,'null')!==false) {
            $currency = Db::name('Currency')->where('is_lock',0)->where('trade_currency_id!=0')->find();
            if($currency) {
                $ttrade_currency_id = explode(',',$currency['trade_currency_id']);
                $buycurrency_id = $currency['currency_id'].'_'.$ttrade_currency_id[0];
            }
        }

        $number = intval(input('post.number', 5));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);
        if (empty($getbuysellid)){

            self::output(10115, lang('lan_modifymember_parameter_error'));
        }
        $currency =Db::name('Currency')->where(array('currency_id' => $getbuysellid['currency_id'], 'is_line' => 1))->order('currency_id desc')->find();
        $trade_currency =Db::name('Currency')->where(array('currency_id' => $getbuysellid['currency_trade_id'], 'is_line' => 1))->order('currency_id desc')->find();
        $getOrders2 = $this->getOrdersByType($currency['currency_id'], 'sell', $number, 'asc',$trade_currency['currency_id']);
        $sell_list = array_reverse($getOrders2);
        foreach ($sell_list as &$val) {
            $val['price_usd'] = format_price_usd($val['price'] / usd2cny());//对美元的价格
            $this->formatNumber($val, $currency['currency_id']);
            /*$val['num']= keepPointV2($val['num'] - $val['trade_num'], 4);
            $val['price']=keepPointV2($val['price'], 4);*/

            //防止买卖盘显示0
            if($val['trade_num']<=0) $val['trade_num'] = $val['num'];
        }
        $data['sell_list'] = $sell_list;

        $buy_list = $this->getOrdersByType($currency['currency_id'], 'buy', $number, 'desc',$trade_currency['currency_id']);
        foreach ($buy_list as &$val) {
            $this->formatNumber($val, $currency['currency_id']);
            /*$val['num']= keepPointV2($val['num'] - $val['trade_num'],4);
            $val['price']=keepPointV2($val['price'], 4);*/

            //防止买卖盘显示0
            if($val['trade_num']<=0) $val['trade_num'] = $val['num'];
        }
        $data['buy_list'] = $buy_list;

        $trade_list = $this->getOrdersByStatus_all(2, 10, $currency['currency_id'],$trade_currency['currency_id']);
        foreach ($trade_list as &$val) {
            $val['num']= format_num($val['num'] );
            //防止买卖盘显示0
            if($val['trade_num']<=0) {
                $val['trade_num'] = $val['num'];
            } else {
                $val['trade_num']= format_num( $val['trade_num']);
            }
            $val['price']=keepPoint($val['price'], 4);
        }

        $data['trade_list'] = $trade_list;

        self::output(10000, lang('lan_user_request_successful'), $data);
    }

    /**
     * C2C交易
     */
    public function Ctrade()
    {

        $cuid = input('post.cuid', '9', 'int');
        $c2c_config_all =Db::name('c2c_coin_config')->alias('c1')
            ->field('c1.*,c2.currency_mark,c3.num user_num,c3.forzen_num as user_forzen_num')
            ->join('yang_currency c2'," c1.currency_id = c2.currency_id")
            ->join("yang_currency_user c3"," c3.currency_id = c2.currency_id and c3.member_id='{$this->member_id}'","left")
            ->select();
//        $c2c_config =Db::name('c2c_coin_config')->alias('c1')
//            ->field('c1.*,c2.currency_mark')
//            ->join('yang_currency c2 on c1.currency_id = c2.currency_id')
//            ->where(['c1.currency_id'=>$cuid])
//            ->find();

//        $currency_user =Db::name('currency_user')->field('num,forzen_num')->where(array('member_id' => $this->member_id, 'currency_id' => $cuid))->find();
//        $currency_user['num'] = $currency_user['num']?:'0';
//        $currency_user['forzen_num'] = $currency_user['forzen_num']?:'0';
        foreach($c2c_config_all as &$v){
                if( $v==null){
                    $v = '';
                }

                $v['user_num'] = intval($v['user_num'])?:0;
                $v['user_forzen_num'] = intval($v['user_forzen_num'])?:0;
                $v['min_volume'] = intval($v['min_volume']);
                $v['max_volume'] = intval($v['max_volume']);

                $v['c2c_introduc'] = '1.'.lang('lan_sec_tips1').'
2.'.lang('lan_sec_tips2').'
3.'.lang('lan_sec_tips3').'
4.'.lang('lan_sec_tips4').'
5.'.lang('lan_sec_tips5').'
6.'.lang('lan_sec_tips6').'
7.'.lang('lan_sec_tips7');
        }

        $data=[
            'order_buy' => [],
            'order_sell'=> [],
            'c2c_config_all'=>$c2c_config_all,
        ];
        self::output(10000,lang('lan_user_request_successful'),$data);

    }

    //购买是选择支付方式
    public function get_bank()
    {
        $where = array('member_id' => $this->member_id,'status'=>1);
        $where2 = array('b1.member_id' => $this->member_id,'status'=>1);
        //$verify_file =Db::name('verify_file')->where($where)->find();
        $member_bank =Db::name('member_bank')->alias('b1')
            ->join( config("DB_PREFIX") . "banklist b2"," b2.id = b1.bankname ","left")->field("b1.*,b2.name")
            ->where($where2)->select();
        $member_alipay =Db::name('member_alipay')->where($where)->select();
        $member_wechat =Db::name('member_wechat')->where($where)->select();
        $data=[
            'member_bank'=>$member_bank,
            'member_alipay'=>$member_alipay,
            'member_wechat'=>$member_wechat,
            'member_id'=>$this->member_id
        ];
        self::output(10000,lang('lan_user_request_successful'),$data);
    }

    //我的支付方式
    public function admin_way()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }

        $where = array('member_id' => $this->member_id);
        $where2 = array('b1.member_id' => $this->member_id);
        $member_bank =Db::name('member_bank')->alias('b1')
            ->join( config("DB_PREFIX") . "banklist b2","b2.id = b1.bankname","left")->field("b1.*,b2.name")->where($where2)->select();
        foreach ($member_bank as $key => $val) {
            $member_bank[$key]['c_id'] = $val['id'] % 3 + 1;
        }
        $member_alipay =Db::name('member_alipay')->where($where)->select();
        $member_wechat =Db::name('member_wechat')->where($where)->select();


        $data=[
            'member_bank'=>$member_bank,
            'member_alipay'=>$member_alipay,
            'member_wechat'=>$member_wechat
        ];
        self::output(10000,lang('lan_user_request_successful'),$data);

    }

    /**
     * 添加支付方式
     */
    public function addPayment()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $_POST['member_id'] = $this->member_id;
        $_POST['add_time'] = time();

        if (input('get.type') == 1) {//添加银行卡
            $model = db('MemberBank');
        } elseif (input('get.type') == 2) {
            $model = db('MemberAlipay');
        } elseif (input('get.type') == 3) {
            $model = db('MemberWechat');
        }
        $count = $model->where(['member_id'=>$this->member_id])->count();
        if($count>=5){ //每种支付方式，最多只能设置5个账号
            self::output(10001, lang('lan_user_c2c_only_five_accounts'));
        }

        if (input('post.type') == 1) {//添加银行卡
            $model = db('MemberBank');
        } elseif (input('post.type') == 2) {
            if(empty($_POST['alipay_pic'])){
                self::output(10107, lang('lan_qrcode_not_empty'));
            }
            if(empty($_POST['alipay'])){
                self::output(10107, lang('lan_account_name_not_empty'));
            }
            $upload = $this->oss_base64_upload($_POST['alipay_pic'], 'images', 'images', 'Member/Ctrade/' . date('Y-m-d',time()) . '/');
            if ($upload['Code'] == 0) {
                self::output(10107, $upload['Msg']);
            }
            $data['alipay_pic']= $upload['Msg'][0];
            $data['alipay']= input('post.alipay');
            $data['member_id']= $this->member_id;
            $data['add_time']= time();
            $model = db('MemberAlipay');
            $result=$model->where(array('member_id'=>$this->member_id,'status'=>1))->select();
            if (!$result){
                $data['status']=1;
            }
            $re=$model->insert($data);
            if($re){
                self::output(10000,lang('lan_Add_success'));
            }else{
                self::output(10001,lang('lan_Add_failure'));
            }
        } elseif (input('post.type') == 3) {
            if(empty($_POST['wechat_pic'])){
                self::output(10107, lang('lan_qrcode_not_empty'));
            }
            if(empty($_POST['wechat'])){
                self::output(10107, lang('lan_account_name_not_empty'));
            }
            $upload = $this->oss_base64_upload($_POST['wechat_pic'], 'images', 'images', 'Member/Ctrade/' . date('Y-m-d',time()) . '/');
            if ($upload['Code'] == 0) {
                self::output(10107, $upload['Msg']);
            }
            $data['wechat_pic']= $upload['Msg'][0];
            $data['wechat']= input('post.wechat');
            $data['member_id']= $this->member_id;
            $data['add_time']= time();
            $model = db('MemberWechat');
            $result=$model->where(array('member_id'=>$this->member_id,'status'=>1))->select();
            if (!$result){
                $data['status']=1;
            }
            $re=$model->insert($data);
            if($re){
                self::output(10000,lang('lan_Add_success'));
            }else{
                self::output(10001,lang('lan_Add_failure'));
            }
        }
        $result=$model->where(array('member_id'=>$this->member_id,'status'=>1))->select();
        if (!$result){
            $_POST['status']=1;
        }
        if ($model->create()) {
            if ($model->insert()) {
                self::output(10000,lang('lan_Add_success'));
            } else {
                self::output(10001,lang('lan_Add_failure'));
            }
        } else {
            self::output(10002,getPayErrMsg($model->getError()));
        }
    }

    //设置默认支付方式
    public function update_payment()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $type=input('post.type');
        $id=input('post.id');
        if($type ==1){
            $model =Db::name('MemberBank');
        }else if($type ==2){
            $model =Db::name('MemberAlipay');
        }else{
            $model =Db::name('MemberWechat');
        }
        $data['status']=0;
        $model->where(array('status'=>1))->update($data);
        $re=$model->where(array('id'=>$id))->update(array('status'=>1));
        if($re){
            self::output(10000,lang('lan_login_modify_the_success'));
        }else{
            self::output(10000,lang('lan_safe_modify_the_failure'));
        }


    }
    /**
     * 获取银行
     */
    public function getBankList()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $bank_list =Db::name('banklist')->select();
        foreach ($bank_list as &$val) {
            $val['value'] = $val['name'];
            if (cookie('think_language') == 'en-us') {
                $val['value'] = $val['englishname'];
            }
        }
        self::output(10000,lang('lan_user_request_successful'),$bank_list);
    }

    /**
     * 获取省市
     */
    public function getCity()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $area =Db::name("Areas");
        $province = $area->field('area_id,area_name')->where(array('parent_id' => 1))->select();//查询所有省
        foreach ($province as $key => $val) {
            $areas = $area->field('area_id,area_name')->where(array('parent_id' => $val['area_id']))->select();//查询省下面所有的市
            $c = array();
            foreach ($areas as $k => $v) {
                $c[$k]['id'] = $v['area_id'];
                if (cookie('think_language') == 'en-us') {//英文转换为拼音
                    $v['area_name'] = pinyin($v['area_name']);
                }
                if (cookie('think_language') == 'zh-tw') {//转换为繁体
                    $v['area_name'] = c2t($v['area_name']);
                }
                $c[$k]['value'] = $v['area_name'];
            }
            $city[$key]['child'] = $c;
            if (cookie('think_language') == 'en-us') {//英文转换为拼音
                $val['area_name'] = pinyin($val['area_name']);
            }
            if (cookie('think_language') == 'zh-tw') {//转换为繁体
                $val['area_name'] = c2t($val['area_name']);
            }
            $city[$key]['value'] = $val['area_name'];
            $city[$key]['id'] = $val['area_id'];
        }
        self::output(10000,lang('lan_user_request_successful'),$city);
    }

    /**
     * 最近兑换记录
     */
    public function recent_record()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }

        $order =Db::name('c2c_order')->alias('o')
            ->join( config("DB_PREFIX") . "currency c","c.currency_id = o.currency_id","left")
            ->field("o.*,c.currency_mark")->where(array('o.member_id' => $this->member_id))->order('o.add_time desc')->limit(10)->select();
        $status = array('0' => lang('lan_processing'), '1' => lang('lan_payment_success'), '2' => lang('lan_remittance_success'), '-1' => lang('lan_canceled')); //'0' => '处理中', '1' => '支付成功', '2' => '打款成功', '-1' => '已取消'
        foreach ($order as $key => $val) {
            $order[$key]['_status'] = $status[$val['status']];

        }
        $data=[
            'order'=>$order,
        ];
        self::output(10000,lang('lan_user_request_successful'),$data);
    }

    //获取付款信息
    public function get_payment_info()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $id=input('id');
        $order =Db::name('c2c_order')->alias('o')->where(array('id' => $id))->order('add_time desc')->limit(10)->find();
        if ($order['type'] == 1 && $order['status'] == 0) {
            switch ($order['pay_type']) {
                case 1:
                    $pay =Db::name('admin_bank')->alias('b1')
                        ->field('b1.*,b2.name')
                        ->join('yang_banklist b2 '," b1.bankname = b2.id")
                        ->where(array('b1.id'=>$order['pay_id']))->find();
                    break;
                case 2:
                    $pay =Db::name('admin_payment')->where(array('type' => 2,'id'=>$order['pay_id']))->find();
                    break;
                case 3:
                    $pay =Db::name('admin_payment')->where(array('type' => 3,'id'=>$order['pay_id']))->find();
                    break;
            }
            $pay['order_id'] = $order['id'];
            $pay['pay_type'] = $order['pay_type'];
            $pay['money'] = $order['money'];
            $pay['order_sn'] = substr($order['order_sn'], 10, 6);
            self::output(10000,lang('lan_user_request_successful'),$pay);
        }

    }


    /**
     * C2C买入
     */
    public function addOrderBuy()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $buy_price = input('post.buyUnitPrice', '', 'float');
        $buy_number = input('post.buyNumber', '0', 'float');
        $cuid = input('post.cuid', '', 'int');
        $pay_id = input('post.payment');
        $pay_type = input('post.pay_type');
        $coin_config =Db::name('c2c_coin_config')->where(array('currency_id' => $cuid))->find();
        if (!$coin_config) {
            self::output(10001,lang('lan_modifymember_parameter_error'));
        }
        if (!input('post.buyNumber')) {
            self::output(10002,lang('lan_The_number_buying_empty'));//数量不能为空
        }
        if ($buy_number == '0') {
            self::output(10003,lang('lan_fill_numeric_type'));//数量只能为数字
        }
        if ($buy_number < $coin_config['min_volume']) {
            self::output(10004,lang('lan_num_not_less_than') . $coin_config['min_volume']);//数量最小值
        }
        if ($buy_number > $coin_config['max_volume']) {
            self::output(10005,lang('lan_num_not_greater_than') . $coin_config['max_volume']);//数量最大值
        }
        $verify_file =Db::name('verify_file')->field('verify_state')->where(array('member_id' => $this->member_id))->find();
        if ($verify_file['verify_state'] != 1) {
            self::output(10006,lang('lan_safe_financial_security'));//身份验证
        }
        if (!$pay_type || !$pay_id) {
            self::output(10007,lang('lan_please_select_payment_method'));//支付方式
        }

        $add_data = array(
            'order_sn' => tradeSn(),
            'member_id' => $this->member_id,
            'currency_id' => $cuid,
            'number' => $buy_number,
            'surplus_number' => $buy_number,
            'price' => $buy_price,
            'money' => $buy_number * $buy_price,
            'type' => 1,
            'add_time' => time(),
            'pay_id' => $pay_id,
            'pay_type' => $pay_type,
        );
        $order_id =Db::name('c2c_order')->insert($add_data);
        if ($order_id) {
            self::output(10000,lang('lan_operation_success'),['order_id'=>$order_id]);
        } else {
            self::output(10008,lang('lan_trade_the_operation_failurer'));
        }
    }

    public function getCPayInfo()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $order_id = input('id');
        self::output(10000, lang('lan_user_request_successful'), $this->get_ctrade_order_info($order_id));
    }

    /**
     * C2C获取买入订单付款信息
     */
    protected function get_ctrade_order_info($order_id)
    {
        $id = $order_id;
        $order =Db::name('c2c_order')->alias('o')->where(array('id' => $id))->order('add_time desc')->find();
        if ($order['type'] == 1 && $order['status'] == 0) {
            switch ($order['pay_type']) {
                case 1:
                    $pay =Db::name('admin_bank')->alias('b1')
                        ->field('b1.*,b2.name')
                        ->join('yang_banklist b2',"b1.bankname = b2.id")
                        ->where(array('b1.id'=>$order['pay_id']))->find();
                    break;
                case 2:
                    $pay =Db::name('admin_payment')->where(array('type' => 2,'id'=>$order['pay_id']))->find();
                    break;
                case 3:
                    $pay =Db::name('admin_payment')->where(array('type' => 3,'id'=>$order['pay_id']))->find();
                    break;
            }
            $pay['order_id'] = $order['id'];
            $pay['pay_type'] = $order['pay_type'];
            $pay['money'] = $order['money'];
            $pay['order_sn'] = substr($order['order_sn'], 10, 6);
            $data = [
                'help_center' => [
                    'tip_title' => lang('lan_Remittance_bill'),
                    'tip_1' => '1.'.lang('lan_Please_remit_remittance_message'),
                    'tip_2' => '2.'.lang('lan_any_questions_help_center2').'
3.'.lang('lan_any_questions_help_center3').'
4.'.lang('lan_any_questions_help_center4').'
5.'.lang('lan_any_questions_help_center5')
                ],
                'pay_info' => $pay
            ];
            return $data;
        }

    }

    /**
     * C2C卖出
     */
    public function addOrderSellang()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $sell_price = input('post.sellUnitPrice', '', 'float');
        $sell_number = input('post.sellNumber', '0', 'float');
        $cuid = input('post.cuid', '', 'int');
        $payment = input('post.payment');
        $pay_type = input('post.pay_type');
        $coin_config =Db::name('c2c_coin_config')->where(array('currency_id' => $cuid))->find();
        if (!$coin_config) {
            self::output(10001,lang('lan_modifymember_parameter_error'));
        }
        if (!input('post.sellNumber')) {
            self::output(10002,lang('lan_The_number_selling_empty'));//数量不能为空
        }
        if ($sell_number == '0') {
            self::output(10003,lang('lan_fill_numeric_type'));//数量只能为数字
        }
        if ($sell_number < $coin_config['min_volume']) {
            self::output(10004,lang('lan_num_not_less_than') . $coin_config['min_volume']);
        }
        if ($sell_number > $coin_config['max_volume']) {
            self::output(10005,lang('lan_num_not_greater_than') . $coin_config['max_volume']);//数量最大值
        }
        $verify_file =Db::name('verify_file')->field('verify_state')->where(array('member_id' => $this->member_id))->find();
        if ($verify_file['verify_state'] != 1) {
            self::output(10006,lang('lan_safe_financial_security')); //身份验证
        }
        if (!$payment) {
            //支付方式
            self::output(10007,lang('lan_please_select_payment_method'));
        }
        $currency_user =Db::name('currency_user')->where(array('member_id' => $this->member_id, 'currency_id' => $cuid))->find();
        if ($sell_number > $currency_user['num']) {
            //账户余额不足
            self::output(10008,lang('lan_trade_underbalance'));
        }
        try {
           Db::startTrans();
            $add_data = array(
                'order_sn' => tradeSn(),
                'member_id' => $this->member_id,
                'currency_id' => $cuid,
                'number' => $sell_number,
                'surplus_number' => $sell_number,
                'price' => $sell_price,
                'money' => $sell_number * $sell_price,
                'type' => 2,
                'add_time' => time(),
                'pay_id' => $payment,
                'pay_type' => $pay_type,
            );
            $add_order =Db::name('c2c_order')->insert($add_data);//添加到订单
            if (!$add_order) {
                throw new Exception(lang('lan_trade_the_operation_failurer'));
            }
            $update_data = array(
                'num' => $currency_user['num'] - $sell_number,
                'forzen_num' => $currency_user['forzen_num'] + $sell_number,
            );

//            db('CurrencyUserStream')->addStream($this->member_id, $cuid, 1, $sell_number, 2, 41, 0, 'C2C卖出冻结-增加冻结');
//            db('CurrencyUserStream')->addStream($this->member_id, $cuid, 2, $sell_number, 1, 41, 0, 'C2C卖出冻结-减除可用');
            $update_currency =Db::name('currency_user')->where(array('member_id' => $this->member_id, 'currency_id' => $cuid))->update($update_data);//更新用户资金
            if (!$update_currency) {
                throw new Exception(lang('lan_trade_the_operation_failurer'));
            }
           Db::commit();
            self::output(10000,lang('lan_operation_success'));
        } catch (Exception $e) {
           Db::rollback();
            self::output(10009,$e->getMessage());
        }
    }

    /**
     * C2C获取交易记录
     */
    public function getCOrderList()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }

        $where = ['member_id'=>$this->member_id, 'currency_id'=>9];
        $count =Db::name('c2c_order')->where($where)->count();

        $pages = intval(input('post.page'))?:1;
        $rows = intval(input('post.rows'))?:10;
        $page = (($rows * ($pages - 1))?:0).','.$rows;

        $list =Db::name('c2c_order')->where($where)->field(true)->order('add_time desc')->limit($page)->select();
        $status = array('0' => lang('lan_processing'), '1' => lang('lan_payment_success'), '2' => lang('lan_remittance_success'), '-1' => lang('lan_canceled'));//'0' => '处理中', '1' => '支付成功', '2' => '打款成功', '-1' => '已取消'
        foreach($list as &$val){
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            $val['_status'] = $status[$val['status']];
            $val['operation'] = lang('lan_Payment_information');
            foreach($val as &$value){
                if($value==null){
                    $value = '';
                }
            }
        }
        self::output(10000, lang('lan_user_request_successful'), $list);
    }

    /**
     * 删除支付方式
     */
    public function payDelang()
    {
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $id = input('post.id', '', 'intval');
        $type = input('post.type', '', 'intval');
        switch ($type) {
            case 1:
                $d_id =Db::name('member_bank')->where(array('id' => $id, 'member_id' => $this->member_id))->delete();
                break;
            case 2:
                $d_id =Db::name('member_alipay')->where(array('id' => $id, 'member_id' => $this->member_id))->delete();
                break;
            case 3:
                $d_id =Db::name('member_wechat')->where(array('id' => $id, 'member_id' => $this->member_id))->delete();
                break;
        }
        if ($d_id) {
            self::output(10000,lang('lan_uc_delete_success'));
        } else {
            self::output(10001,lang('lan_uc_delete_failed'));
        }
    }

    /**
     * 获取平台支付方式列表
     * @return array
     */
    public function getPaymentList(){
        if (!self::checkLogin()) {
            self::output(10100, lang('lan_modifymember_please_login_first'));
        }
        $field_bank = 'b1.id,b1.bankadd as inname,bankcard as cardnum,b1.status,b1.truename,b2.name as bname,b2.id as bank_id';
        $bank =Db::name('admin_bank')->alias('b1')
            ->join('yang_banklist b2',"b2.id = b1.bankname","left")
            ->field($field_bank)->select();
        $field_pay = 'id,username as cardnum,qrcode as img,type';
        $pay_list =Db::name('admin_payment')->field($field_pay)->select();
        $alipay = [];
        $wechat = [];
        foreach($pay_list as $value){
            if($value['type']==2){
                $alipay[] = $value;
            }else if($value['type']==3){
                $wechat[] = $value;
            }
        }
        $list = [
            'bank' => $bank,
            'alipay' => $alipay,
            'wechat' => $wechat
        ];
        self::output(10000, lang('lan_user_request_successful'), $list);
    }
}
