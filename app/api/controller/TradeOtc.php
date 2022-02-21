<?php
namespace app\api\controller;
use think\Db;
use think\Exception;

class TradeOtc extends Base
{
    protected $is_method_filter = true;

    public function _initialize() {
        parent::_initialize();

        $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
        if(!$verify_info) $this->output(30100,lang('lan_user_authentication_first'));
        if($verify_info['verify_state']==2) $this->output(40100,lang('lan_user_authentication_first_wait'));
        if($verify_info['verify_state']!=1) $this->output(30100,lang('lan_user_authentication_first'));

        $nick = Db::name('member')->where(['member_id'=>$this->member_id])->value('nick');
        if(empty($nick)) $this->output(20100,lang('lan_nickname_first'));
    }

	//获取币种OTC详情及用户资产
    public function icon_info() {
        $currency_id = intval(input('post.currency_id'));

        $currency = model('OrdersOtc')->otc_info($currency_id,$this->config['otc_cancel_limit']);
        if(is_string($currency)) $this->output(10001,$currency);

        $user = [];
        $user['currency_num'] = model('CurrencyUser')->getNum($this->member_id,$currency_id);
        $user['currency_num'] = keepPoint($user['currency_num'],6);
        $user['sell_num'] = keepPoint($user['currency_num'] /(1 + $currency['currency_otc_sell_fee'] / 100),6);

        $this->output(10000,lang('lan_operation_success'),['currency'=>$currency,'user'=>$user]);
    }

    //用户的交易列表
    public function trade_list() {
       	$status = input('status','');
       	$page = input('page',1,'intval,filter_page');
       	$page_size = input('page_size',10,'intval,filter_page');

        $list = model('TradeOtc')->trade_list($this->member_id,$status,$page,$page_size);
       	if(is_string($list)) $this->output(10001,$list);

        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //获取交易订单详情
    public function trade_info() {
        $trade_id = input("trade_id",0,'intval');
        if(empty($trade_id)) $this->output(10001,lang('lan_operation_failure'));

        $tradeInfo = model('TradeOtc')->trade_info($this->member_id,$trade_id,$this->lang,$this->config['repeal_time']);
        if(is_string($tradeInfo)) $this->output(10001,$tradeInfo);

        $this->output(10000,lang('lan_operation_success'),$tradeInfo);
    }

    //挂卖单
    public function sell_num(){
//        $mark= input("validate");//图片验证码mark
//        $img_code= input("img_code");//图片验证码
//        if(!verify_code($mark,$img_code)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));

        $phone_code = strval(input('phone_code',''));
        $senderLog = model('Sender')->auto_check($this->member_id,'sell_num',$phone_code,false);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $orders_id = model('OrdersOtc')->addSell($this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$this->config['sell_confirm_time']);
        if(is_string($orders_id)) $this->output(10001,$orders_id);

        //设置验证码为已用
        model('Sender')->hasUsed($senderLog['id']);

        $this->output(10000,lang('lan_operation_success'),$orders_id);
    }

    public function sell_num_check() {
        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));
        $type=strval(input('type'));

        $sell_confirm_time = $this->config['sell_confirm_time'];
        if($type=='buy') {
            $sell_confirm_time =  $this->config['buy_confirm_time'];
            $result = model('OrdersOtc')->buyCheckBefore($this->member_id,$currency_id,$this->config['buy_orders_otc']);
            if(is_string($result)) $this->output(10001,$result);
        }

        $currency = model('OrdersOtc')->sellCheck($type,$this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$sell_confirm_time);
        if(is_string($currency)) $this->output(10001,$currency);

        $this->output(10000,lang('lan_operation_success'));
    }

    //挂买单
    public function buy_num() {
//        $mark= input("validate");//图片验证码mark
//        $img_code= input("img_code");//图片验证码
//        if(!verify_code($mark,$img_code)){
//            $this->output(ERROR2,lang('lan_Picture_verification_refresh'));
//        }

        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));
        $pwd = input('pwd','','strval');

        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $phone_code = strval(input('phone_code',''));
        $senderLog = model('Sender')->auto_check($this->member_id,'sell_num',$phone_code,true);
        if(is_string($senderLog)) $this->output(10001,$senderLog);



        $result = model('OrdersOtc')->buyCheckBefore($this->member_id,$currency_id,$this->config['buy_orders_otc']);
        if(is_string($result)) $this->output(10001,$result);

        $orders_id = model('OrdersOtc')->addBuy($this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$this->config['buy_confirm_time']);
        if(is_string($orders_id)) $this->output(10001,$orders_id);

        $this->output(10000,lang('lan_operation_success'),$orders_id);
    }

    //买入-某卖单
    public function buy(){
        $mark= input("validate");//图片验证码mark
        $img_code= input("img_code");//图片验证码
//        if(!verify_code($mark,$img_code)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $buynum= floatval(input('num'));
        $orders_id=intval(input('orders_id'));

        $result = model('TradeOtc')->buy($this->member_id,$orders_id,$buynum,$this->config['otc_day_cancel'],$this->config['otc_trade_online'],$this->config['buy_confirm_time'],$this->config['sell_confirm_time']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),$result);
    }

    //卖出-某买单
    public function sell() {
        $mark= input("validate");//图片验证码mark
        $img_code= input("img_code");//图片验证码
//        if(!verify_code($mark,$img_code)){
//            $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }

        $orders_id = intval(input('post.orders_id'));
        $tradenum = floatval(input('post.num'));
        $pwd = input('post.pwd','');
        $money_type = input('money_type','');

        $result = model('TradeOtc')->sell($this->member_id,$orders_id,$tradenum,$pwd,$money_type,$this->config['otc_trade_online'],$this->config['buy_confirm_time'],$this->config['sell_confirm_time']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),$result);
    }

    public function day_cancel_count() {
        $time = strtotime(date('Y-m-d'));
        $count = Db::name('trade_otc')->where(['member_id'=>$this->member_id,'status'=>4,'add_time'=>['gt',$time]])->count();
        $flag = $this->config['otc_day_cancel'] - $count;

        $this->output(10000,lang('lan_operation_success'),['flag'=>$flag]);
    }

    //点击取消 --只有买家才能进行操作
    public function cancel() {
        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->user_cancel($this->member_id,$trade_id,$this->config['otc_day_cancel']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //买家支付后取消
    public function cancel_payment() {
        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->user_pay_cancel($this->member_id,$trade_id,$this->config['otc_day_cancel']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //选择支付方式
    public function choose_bank(){
        $money_type = input('money_type','');
        $trade_id = input("trade_id",0,'intval');

        $result = model('TradeOtc')->choose_bank($this->member_id,$trade_id,$money_type);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //点击付款 -- 只有买家才能操作
    public function pay() {
        $trade_id = input("trade_id",0,'intval');

        $result = model('TradeOtc')->pay($this->member_id,$trade_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //放行 -- 只有卖家才能操作
    public function fangxing() {
        $mark= input("validate");//图片验证码mark
        $img_code= input("img_code");//图片验证码
//        if(!verify_code($mark,$img_code)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->seller_fangxing($this->member_id,$trade_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //申诉 已付款状态15分钟后
    public function appeal() {
        $trade_id = input("trade_id",0,'intval');
        $allege_type = input('type',0,'intval');
        $content = input('content','');

        $result = model('TradeOtc')->appeal($this->member_id,$trade_id,$allege_type,$content,$this->config['repeal_time']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //撤销申诉
    public function cancel_appeal() {
        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->cancel_appeal($this->member_id,$trade_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }
}
