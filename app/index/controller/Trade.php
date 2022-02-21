<?php
namespace app\index\controller;
use think\Db;

class Trade  extends TradeFather {
	protected  $currency;
	protected  $entrust;
	protected  $trade;
	public function _initialize(){
		parent::_initialize();

		$this->currency=db('Currency');
		$this->entrust=db('Entrust');
		$this->trade=db('Orders');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		return $this->fetch('Public:404');
	}
	
    
    //买入
    public function buy()
    {
        if(!$this->checkLogin()){
            $data['status']=0;
            $data['info']= lang('lan_trade_olease_log');
            $this->mobileAjaxReturn($data);
        }

        $pwd = input('pwd','','strval');
        if(empty($pwd)) {
            $data['status']=-1;
            $data['info']=lang('lan_Incorrect_transaction_password');
            $this->mobileAjaxReturn($data);
        }
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd);
        if(is_string($checkPwd)) {
            $data['status']=-1;
            $data['info']= $checkPwd;
            $this->mobileAjaxReturn($data);
        }

        $buyprice=floatval(input('post.buyprice'));
        $buynum=floatval(input('post.buynum'));
        $buypwd=strval(input('post.buypwd', '', 'strval'));
        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency_id'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);
        if (empty($getbuysellid)){
            $data['status']=15;
            $data['info']= '传入参数错误';
            $this->mobileAjaxReturn($data);
        }
        $buycurrency_id=$getbuysellid['currency_id'];
        //获取积分的相关信息
        $currency=$this->getCurrencyByCurrencyId($buycurrency_id);

        $is_free_fee = Db::name('orders_free_fee')->where(['member_id'=>$this->member_id])->find();
        if($is_free_fee) $currency['currency_buy_fee'] = 0;
        
        $currency['trade_currency_id']=$getbuysellid['currency_trade_id'];

        if ($currency['is_lock']){
            $data['status']=-5;
            $data['info']= '该积分类型暂时不允许交易';
            $this->mobileAjaxReturn($data);
        }
        if (empty($buyprice)||empty($buynum)||!is_numeric($buyprice)||!is_numeric($buynum)){
            $data['status']=0;
            $data['info']=lang('lan_trade_wrong');
            $this->mobileAjaxReturn($data);
        }
        if ($buynum<=0){
            $data['status']=-2;
            $data['info']=lang('lan_trade_change_quantity');
            $this->mobileAjaxReturn($data);
        }
        if ($buyprice*$buynum<0){
            $data['status']=0;
            $data['info']=lang('lan_trade_entrust_lowest');
            $this->mobileAjaxReturn($data);
        }
        /* if (!is_int($buynum)){
             $data['status']=-1;
             $data['info']='交易数量必须是整数';
             $this->mobileAjaxReturn($data);
         }*/

        //时间限制

        if($currency['is_time']){
            $newtime =time();
            !empty($currency['min_time'])?:($currency['min_time']="00:00:00");
            !empty($currency['max_time'])?:($currency['max_time']="00:00:00");
            $min_newtime = strtotime(date("Y-m-d ", time()).$currency['min_time']);
            $max_newtime = strtotime(date("Y-m-d ", time()).$currency['max_time']);
            if ($newtime < $min_newtime) {
                $data['info']=lang('lan_trade_no_time_to');
                $this->mobileAjaxReturn($data);
            }
            if ($newtime >= $max_newtime) {
                $data['info']=lang('lan_trade_over_time');
                $this->mobileAjaxReturn($data);
            }
        }
        //价格限制
        if($currency['is_limit']){
            $getlastmessage=$this->getlastmessage($buycurrency_id,$currency['trade_currency_id']);
            $newprice=$getlastmessage+($getlastmessage*$currency['max_limit'])/100;
            $newprice2=$getlastmessage-($getlastmessage*$currency['min_limit'])/100;
            if($getlastmessage){
                if($buyprice>$newprice){

                    $data['info']=lang('lan_Buy_price_bad_failure');
                    $this->mobileAjaxReturn($data);
                }
                if($buyprice<$newprice2){

                    $data['info']=lang('lan_Buy_price_bad_failure1');
                    $this->mobileAjaxReturn($data);
                }
            }
        }
        $member=$this->member;
//         if(md5($buypwd)!=$member['pwdtrade']){
//             $data['status']=-3;
//             $data['info']=lang('lan_Incorrect_transaction_password');
//             $this->mobileAjaxReturn($data);
//         }

        if ($this->checkUserMoney($buynum, $buyprice, 'buy', $currency)){
            $data['status']=-4;
            $data['info']= lang('lan_trade_underbalance');
            $this->mobileAjaxReturn($data);
        }

        //限制星期6天交易
        $da = date("w");
        if ($da == '6') {
            if($currency['trade_day6']){
                $data['status']=18;
                $data['info']=lang('lan_trade_six_today');
                $this->mobileAjaxReturn($data);
            }

        }
        if ($da == '0') {

            if($currency['trade_day7']){
                $data['status']=17;
                $data['info']=lang('lan_trade_Sunday');
                $this->mobileAjaxReturn($data);
            }
        }


        //开启事物
        Db::startTrans();
        //计算买入需要的金额
        $trade_money=round($buynum*$buyprice*(1+($currency['currency_buy_fee']/100)),6);
        //操作账户
        $r[]=$this->setUserMoney(session('USER_KEY_ID'),$currency['trade_currency_id'], $trade_money,'dec', 'num');
        model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $currency['trade_currency_id'], 1, $trade_money, 2, 22, 0, '币币交易扣除可用');
        $r[]=$this->setUserMoney(session('USER_KEY_ID'),$currency['trade_currency_id'], $trade_money, 'inc','forzen_num');
        model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $currency['trade_currency_id'], 2, $trade_money, 1, 22, 0, '币币交易增加冻结');

        //挂单流程
        $r[] =  $orders_id = $this->guadan($buynum, $buyprice, 'buy', $currency);

        model("AccountBook")->addLog([
            'member_id'=>session('USER_KEY_ID'),
            'currency_id'=> $currency['trade_currency_id'],
            'number_type'=>2,
            'number'=>$trade_money,
            'type'=>11,
            'content'=>"lan_trade",
            'fee'=> round($buynum * $buyprice * ($currency['currency_buy_fee'] / 100),6),
            'to_member_id'=>0,
            'to_currency_id'=>0,
            'third_id'=> $orders_id,
        ]);
        //交易流程
        $r1[]=$this->trade($currency['currency_id'], 'buy', $buynum, $buyprice,$currency['trade_currency_id']);
        foreach ($r1 as $v){
            $r[]=$v;
        }
        if (in_array(false, $r)){
            Db::rollback();
            $msg['status']=-7;
            $msg['info']= lang('lan_trade_the_operation_failurer');
            $this->mobileAjaxReturn($msg);
        }else {
            Db::commit();
            $msg['status']=1;
            $msg['info']= lang('lan_operation_success');
            if (!empty(session('USER_KEY_ID'))){
                $user_currency_money['currency']['num']=$this->getUserMoney($currency['currency_id'], 'num');
                $user_currency_money['currency']['forzen_num']=$this->getUserMoney($currency['currency_id'], 'forzen_num');
                $user_currency_money['currency_trade']['num']=$this->getUserMoney($currency['trade_currency_id'], 'num');
                $user_currency_money['currency_trade']['forzen_num']=$this->getUserMoney($currency['trade_currency_id'], 'forzen_num');
                /* if($currency['trade_currency_id']==0){
                     $user_currency_money['currency_trade']['num']=$this->member['rmb'];
                     $user_currency_money['currency_trade']['forzen_num']=$this->member['forzen_rmb'];
                 }*/


                //最大可卖
                $sell_num=sprintf('%.6f',$user_currency_money['currency']['num']);

            }
            $msg['from_over']=$user_currency_money['currency']['num'];
            $msg['from_lock']=$user_currency_money['currency']['forzen_num'];
            $msg['from_total']=$user_currency_money['currency']['num']+$user_currency_money['currency']['forzen_num'];

            $msg['to_over']=$user_currency_money['currency_trade']['num'];
            $msg['to_lock']=$user_currency_money['currency_trade']['forzen_num'];
            $msg['to_total']=$user_currency_money['currency_trade']['forzen_num']+$user_currency_money['currency_trade']['num'];
            $msg['coinsale_max']=$sell_num;
            $this->mobileAjaxReturn($msg);
        }

    }


	/*卖出
	 * 
	 * 1.是否登录
	 * 1.5 是否开启交易
	 * 2.准备数据
	 * 3.判断数据
	 * 4.检查账户
	 * 5.操作个人账户
	 * 6.写入数据库
	 * 
	 * 
	 * 
	 */

    public function sell()
    {
        if(!$this->checkLogin()){
            $data['status']=-1;
            $data['info']=lang('lan_trade_olease_log');
            $this->mobileAjaxReturn($data);
        }

        $pwd = input('pwd','','strval');
        if(empty($pwd)) {
            $data['status']=-1;
            $data['info']=lang('lan_Incorrect_transaction_password');
            $this->mobileAjaxReturn($data);
        }
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd);
        if(is_string($checkPwd)) {
            $data['status']=-1;
            $data['info']= $checkPwd;
            $this->mobileAjaxReturn($data);
        }

        $sellprice=floatval(input('post.sellprice'));
        $sellnum=floatval(input('post.sellnum'));
        $sellpwd=input('post.sellpwd');
        //更换币币交易 获取xx_xx参数
        $currency_id=strval(input('post.currency_id'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($currency_id);
        if (empty($getbuysellid)){
            $data['status']=15;
            $data['info']= '传入参数错误';
            $this->mobileAjaxReturn($data);
        }
        $currency_id=$getbuysellid['currency_id'];
        //获取积分类型信息
        $currency=$this->getCurrencyByCurrencyId($currency_id);
        
        $is_free_fee = Db::name('orders_free_fee')->where(['member_id'=>$this->member_id])->find();
        if($is_free_fee) $currency['currency_sell_fee'] = 0;

        $currency['trade_currency_id']=$getbuysellid['currency_trade_id'];
        //检查是否开启交易
        if ($currency['is_lock']){
            $msg['status']=-2;
            $msg['info']='该积分类型暂时不能交易';
            $this->mobileAjaxReturn($msg);
        }

        if (empty($sellprice)||empty($sellnum)||!is_numeric($sellprice)||!is_numeric($sellnum)){
            $msg['status']=-3;
            $msg['info']=lang('lan_trade_wrong');
            $this->mobileAjaxReturn($msg);
        }
        if ($sellnum<=0){
            $data['status']=-2;
            $data['info']=lang('lan_trade_change_quantity');
            $this->mobileAjaxReturn($data);
        }

        if ($sellnum*$sellprice<0){
            $data['status']=0;
            $data['info']=lang('lan_trade_entrust_lowest');
            $this->mobileAjaxReturn($data);
        }

// 		if (empty($sellpwd)){
// 		    $msg['status']=-4;
// 		    $msg['info']=lang('lan_user_Transaction_password_empty');
// 		    $this->mobileAjaxReturn($msg);
// 		}
// 		if ($this->member['pwdtrade']!=md5($sellpwd)){
// 		    $msg['status']=-5;
// 		    $msg['info']=lang('lan_Incorrect_transaction_password');
// 		    $this->mobileAjaxReturn($msg);
// 		}
        //时间限制
        if($currency['is_time']){
            $newtime =time();
            !empty($currency['min_time'])?:($currency['min_time']="00:00:00");
            !empty($currency['max_time'])?:($currency['max_time']="00:00:00");
            $min_newtime = strtotime(date("Y-m-d ", time()).$currency['min_time']);
            $max_newtime = strtotime(date("Y-m-d ", time()).$currency['max_time']);
            if ($newtime < $min_newtime) {
                $data['info']=lang('lan_trade_no_time_to');
                $this->mobileAjaxReturn($data);
            }
            if ($newtime >= $max_newtime) {
                $data['info']=lang('lan_trade_over_time');
                $this->mobileAjaxReturn($data);
            }
        }

        //价格限制
        if($currency['is_limit']){
            $getlastmessage=$this->getlastmessage($currency_id,$currency['trade_currency_id']);
            $newprice=$getlastmessage+($getlastmessage*$currency['max_limit'])/100;
            $newprice2=$getlastmessage-($getlastmessage*$currency['min_limit'])/100;
            if($getlastmessage){
                if($sellprice>$newprice){

                    $data['info']=lang('lan_Buy_price_bad_failure2');
                    $this->mobileAjaxReturn($data);
                }
                if($sellprice<$newprice2){

                    $data['info']=lang('lan_Buy_price_bad_failure3');
                    $this->mobileAjaxReturn($data);
                }
            }
        }
        //检查账户是否有钱
        if ($this->checkUserMoney($sellnum, $sellprice, 'sell', $currency)){
            $msg['status']=-6;
            $msg['info']= lang('lan_trade_underbalance');
            $this->mobileAjaxReturn($msg);
        }
        //检查账户冻结是否负数
        $where_false['member_id']=session('USER_KEY_ID');
        $where_false['currency_id']=$currency_id;
        $checkfalse=db('currency_user')->field('num,forzen_num,currency_id')->where($where_false)->find();
        if ($checkfalse['forzen_num']<0){
            $msg['status'] = 15;
            $msg['info'] = "账户数据异常，请联系账号服务客服";
            $this->mobileAjaxReturn($msg);


        }
        if ($checkfalse['num']<0){
            $msg['status'] = 15;
            $msg['info'] = "账户数据异常，请联系账号服务客服";
            $this->mobileAjaxReturn($msg);


        }
        //限制星期6天交易
        $da = date("w");
        if ($da == '6') {
            if($currency['trade_day6']){
                $data['status']=18;
                $data['info']=lang('lan_trade_six_today');
                $this->mobileAjaxReturn($data);
            }

        }
        if ($da == '0') {

            if($currency['trade_day7']){
                $data['status']=17;
                $data['info']=lang('lan_trade_Sunday');
                $this->mobileAjaxReturn($data);
            }
        }

        //减可用钱 加冻结钱
        Db::startTrans();
        $r[] = $this->setUserMoney(session('USER_KEY_ID'),$currency['currency_id'], $sellnum, 'dec', 'num');

        model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $currency['currency_id'], 1, $sellnum, 2, 22, 0, '币币交易扣除可用');
        $r[]=$this->setUserMoney(session('USER_KEY_ID'),$currency['currency_id'], $sellnum,'inc','forzen_num');
        model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $currency['currency_id'], 2, $sellnum,1 , 22, 0, '币币交易增加冻结');
        //写入数据库
        $r[]=$orders_id=$this->guadan($sellnum, $sellprice, 'sell', $currency);
        $r[] = model('AccountBook')->addLog([
            'member_id' => $this->member_id,
            'currency_id' => $currency['currency_id'],
            'type'=> 11,
            'content' => 'lan_trade',
            'number_type' => 2,
            'number' => number_format($sellnum, 6, '.', ''),
            'fee' => 0,
            'to_member_id' => 0,
            'to_currency_id' => 0,
            'third_id' => $orders_id,
        ]);
        //成交
        $r[]=$this->trade($currency['currency_id'], 'sell', $sellnum, $sellprice,$currency['trade_currency_id']);
        if (in_array(false, $r)){
            Db::rollback();
            $msg['status']=-7;
            $msg['info']= lang('lan_trade_the_operation_failurer');
            $this->mobileAjaxReturn($msg);
        }else {

            Db::commit();
            $msg['status']=1;
            $msg['info']= lang('lan_operation_success');
            // $this->mobileAjaxReturn($msg);
        }
        if (!empty(session('USER_KEY_ID'))){
            $user_currency_money['currency']['num']=$this->getUserMoney($currency['currency_id'], 'num');
            $user_currency_money['currency']['forzen_num']=$this->getUserMoney($currency['currency_id'], 'forzen_num');
            $user_currency_money['currency_trade']['num']=$this->getUserMoney($currency['trade_currency_id'], 'num');
            $user_currency_money['currency_trade']['forzen_num']=$this->getUserMoney($currency['trade_currency_id'], 'forzen_num');
            /*  if($currency['trade_currency_id']==0){
                 $user_currency_money['currency_trade']['num']=$this->member['rmb'];
                 $user_currency_money['currency_trade']['forzen_num']=$this->member['forzen_rmb'];
             }
              */

            //最大可卖
            $sell_num=sprintf('%.6f',$user_currency_money['currency']['num']);
            $to_over=$this->getmoney();
            $to_lock=$this->getmoney2();
            $msg['from_over']=$user_currency_money['currency']['num'];
            $msg['from_lock']=$user_currency_money['currency']['forzen_num'];
            $msg['from_total']=$user_currency_money['currency']['num']+$user_currency_money['currency']['forzen_num'];
            $msg['to_over']=$to_over;
            $msg['to_lock']=$to_lock;
            $msg['to_over']=$user_currency_money['currency_trade']['num'];
            $msg['to_lock']=$user_currency_money['currency_trade']['forzen_num'];
            $msg['to_total']=$to_over+$to_lock;
            $msg['coinsale_max']=$sell_num;
            $this->mobileAjaxReturn($msg);


        }

    }

    public function getmoney(){
        $num=db('Member')->field('rmb')->where("member_id={session('USER_KEY_ID')}")->find();
       // print_r($num);
        
        $num2=$num['rmb'];
        return $num2;
    }
    public function getmoney2(){
        $num=db('Member')->field('forzen_rmb')->where("member_id={session('USER_KEY_ID')}")->find();
        $num2=$num['forzen_rmb'];
        return $num2;
    }

	//我的成交
	public function myDeal(){
	    // if (!$this->checkLogin()){
	    //     $this->redirect(U('Index/index','',false));
	    // }
	  //获取主积分类型
		$currency=$this->currency();
		$this->assign('culist',$currency);
        $currency_trade_list = ['XRP','USDT','ETH','BTC'];
        $culist_trade = [];
        foreach($currency as $key=>$value){
            if(in_array($value['currency_mark'],$currency_trade_list)){
                $culist_trade[] = $value;
            }
        }
		$this->assign('culist_trade',$culist_trade);
		$currencytype = intval(input('currency'));
		$currency_trade = intval(input('currency_trade'));
        $search['currency'] =  $currencytype;
        $search['currency_trade'] =  $currency_trade;

		if(!empty($currencytype)){
			$where['currency_id'] =$currencytype;
		}
        if(!empty($currency_trade)){
            $where['currency_trade_id'] =$currency_trade;
        }
		$where['member_id'] = session('USER_KEY_ID');
	    
	    $count      = db('Trade')->where($where)->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数

	    //给分页传参数
	    setPageParameter($Page, array('currency'=>$currencytype, 'currency_trade'=>$currency_trade));
	    
	    $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = db('Trade')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('add_time desc')->select();
	    $this->assign('page',$show);// 赋值分页输出
	    $this->assign('list',$list);
	    $this->assign('search',$search);
	    return $this->fetch();
	}
	

	
    /**
     *
     * @param int  $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     * @param $currency_id 交易积分类型
     */
    private function  checkUserMoney($num,$price,$type,$currency){
        //获取交易积分类型信息
        if ($type=='buy'){
            $trade_money=round($num*$price*(1+$currency['currency_buy_fee']/100),6);
            $currency_id=$currency['trade_currency_id'];
        }else {
            $trade_money=$num;
            $currency_id=$currency['currency_id'];
        }
        //和自己的账户做对比 获取账户信息
        $money=$this->getUserMoney($currency_id, 'num');
        if ($money<$trade_money){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 挂单
     * @param int  $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     * @param $currency_id 交易积分类型
     */
    private function guadan($num,$price,$type,$currency){
        //获取交易积分类型信息
        switch ($type){
            case 'buy':
                    $fee=$currency['currency_buy_fee']/100;
                    break;
            case 'sell':
                $fee=$currency['currency_sell_fee']/100;
                break;
        }

        $data=array(
            'member_id'=>session('USER_KEY_ID'),
            'currency_id'=>$currency['currency_id'],
            'currency_trade_id'=>$currency['trade_currency_id'],
            'price'=>$price,
            'num'=>$num,
            'trade_num'=>0,
            'fee'=>$fee,
            'type'=>$type,
            'add_time'=>time(),
        );
        if ( $msg=db('Orders')->insertGetId($data)){

        }else {
            $msg=0;
        }

        return $msg;
        
    }

    private function trade($currencyId,$type,$num,$price,$trade_currency_id)
    {
        if ($type=='buy'){
            $trade_type='sell';
        }else {
            $trade_type='buy';
        }
        $memberId=session('USER_KEY_ID');
        //获取操作人一个订单
        $order=$this->getFirstOrdersByMember($memberId,$type ,$currencyId,$trade_currency_id);
        //获取对应交易的一个订单
        $trade_order=$this->getOneOrders($trade_type, $currencyId,$price,$trade_currency_id);
        //如果没有相匹配的订单，直接返回
        if (empty($trade_order)){
            $r[]=true;
            return $r;
        }
        //如果有就处理订单
        $trade_num=min($num,$trade_order['num']-$trade_order['trade_num']);
        //增加本订单的已经交易的数量
        $r[]=db('Orders')->where("orders_id={$order['orders_id']}")->setInc('trade_num',$trade_num);
        $r[]=db('Orders')->where("orders_id={$order['orders_id']}")->setField('trade_time',time());
        //增加trade订单的已经交易的数量
        $r[]=db('Orders')->where("orders_id={$trade_order['orders_id']}")->setInc('trade_num',$trade_num);
        $r[]=db('Orders')->where("orders_id={$trade_order['orders_id']}")->setField('trade_time',time());

        //更新一下订单状态
        $r[]=db('Orders')->where("trade_num>0 and status=0")->setField('status',1);
        $r[]=db('Orders')->where("num=trade_num")->setField('status',2);


        //处理资金
        $trade_price = 0;
        switch ($type){
            case 'buy':
                $order_money=sprintf('%.6f',$trade_num*$order['price']*(1+$order['fee']));
                $trade_order_money= $trade_num*$trade_order['price']*(1-$trade_order['fee']);
                $trade_price=min($order['price'],$trade_order['price']);
                //$trade_price=$order['price'];

                model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 2, $order_money, 2, 22, 0, '币币交易增加可用');

                model("AccountBook")->addLog([
                    'member_id'=>session('USER_KEY_ID'),
                    'currency_id'=>$order['currency_id'],
                    'number_type'=>1,
                    'number'=>$trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=>0,
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_trade_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'], $order_money, 'dec', 'forzen_num');
                model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_id'], 1, $trade_num, 1, 22, 0, '币币交易增加可用');
                $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_id'], $trade_num, 'inc', 'num');



                model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_id'], 2, $trade_num, 2, 22, 0, '币币交易扣除冻结');
                $r[]=$this->setUserMoney($trade_order['member_id'],$trade_order['currency_id'],  $trade_num, 'dec', 'forzen_num');
                model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_trade_id'], 1, $trade_order_money, 1, 22, 0, '币币交易增加可用');

                model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=>$trade_order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=>$trade_num * $trade_order['price'] * ($trade_order['fee']),
                    'to_member_id'=>$order['member_id'],
                    'to_currency_id'=>$trade_order['currency_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                $r[]=$this->setUserMoney($trade_order['member_id'],$trade_order['currency_trade_id'], $trade_order_money, 'inc', 'num');


                //$r[]= $this->addFinance(session('USER_KEY_ID'), 13, "+" .$currency_name , $trade_num, 1, $order['currency_id']);
                //$r[] = $this->addFinance(session('USER_KEY_ID'), 13, "-" .$currency_trade_name , $order_money, 2, $order['currency_trade_id']);

                $back_price=$order['price']-$trade_price;
                if ($back_price>0){
                    //返还未成交部分手续费
                    model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 1, $trade_num * $back_price * $order['fee'], 1, 23, 0, '返回手续费');

                    model("AccountBook")->addLog([
                        'member_id'=>session('USER_KEY_ID'),
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price * $order['fee'],
                        'type'=>11,
                        'content'=>"lan_Return_charges",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);
                    $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'],$trade_num*$back_price*$order['fee'], 'inc', 'num');
                    //返还多扣除的挂单金额
                    model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 1, $trade_num * $back_price, 1, 23, 0, '返还多扣除的挂单金额');

                    model("AccountBook")->addLog([
                        'member_id'=>session('USER_KEY_ID'),
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price,
                        'type'=>11,
                        'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);
                    $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'],$trade_num*$back_price, 'inc', 'num');
                }
                break;
            case 'sell':
                $order_money= $trade_num*$order['price']*(1-$order['fee']);
                $trade_order_money= sprintf('%.6f',$trade_num*$trade_order['price']*(1+$trade_order['fee']));
                $trade_price=max($order['price'],$trade_order['price']);

                model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_id'], 2, $trade_num, 2, 22, 0, '币币交易扣除冻结');
                $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_id'], $trade_num, 'dec', 'forzen_num');
                model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 1, $order_money, 1, 22, 0, '币币交易增加可用');
                model("AccountBook")->addLog([
                    'member_id'=>session('USER_KEY_ID'),
                    'currency_id'=>$order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=> $order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> $trade_num * $order['price'] * ($order['fee']),
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'], $order_money, 'inc', 'num');

                model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_id'], 1, $trade_num, 1, 22, 0, '币币交易增加可用');
                model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_id'],
                    'number_type'=>1,
                    'number'=> $trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> 0,
                    'to_member_id'=>session('USER_KEY_ID'),
                    'to_currency_id'=>$trade_order['currency_trade_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                $r[]=$this->setUserMoney($trade_order['member_id'],$trade_order['currency_id'], $trade_num, 'inc', 'num');
                model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_trade_id'], 2, $trade_order_money, 2, 22, 0, '币币交易扣除冻结');
                $r[]=$this->setUserMoney($trade_order['member_id'],$trade_order['currency_trade_id'],$trade_order_money, 'dec', 'forzen_num');

//                $r[]= $this->addFinance(session('USER_KEY_ID'), 13, "+" .$currency_name , $trade_num, 1, $order['currency_id']);
//                $r[] = $this->addFinance(session('USER_KEY_ID'), 13, "-" .$currency_trade_name , $order_money, 2, $order['currency_trade_id']);

                $back_price=$trade_price-$order['price'];
                if ($back_price>0){
                    //返还未成交部分手续费
                    model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 1, $trade_num * $back_price * $order['fee'], 1, 25, 0, '返回手续费');
                    model("AccountBook")->addLog([
                        'member_id'=>session('USER_KEY_ID'),
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price * $order['fee'],
                        'type'=>11,
                        'content'=>"lan_Return_charges",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);
                    $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'],$trade_num*$back_price*$order['fee'], 'inc', 'num');
                    //返还多扣除的挂单金额
                    model('CurrencyUserStream')->addStream(session('USER_KEY_ID'), $order['currency_trade_id'], 1, $trade_num * $back_price, 1, 23, 0, '返还多扣除的挂单金额');
                    model("AccountBook")->addLog([
                        'member_id'=>session('USER_KEY_ID'),
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price,
                        'type'=>11,
                        'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);

                    $r[]=$this->setUserMoney(session('USER_KEY_ID'),$order['currency_trade_id'],$trade_num*$back_price, 'inc', 'num');
                }
                break;
        }
        //写入成交表
        $r[] = $trade_id=$this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'],$trade_price, $trade_num, $order['type'],$order['fee'],$trade_order['orders_id']);
        $r[] = $trade_id2=$this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'],$trade_order['fee'],$order['orders_id']);


        //手续费
        $time = time();
        $order_fee = ($trade_num * $trade_price) * $order['fee'];
        $trade_order_fee = ($trade_num * $trade_price) * $trade_order['fee'];
        if ($order_fee > 0) {
            $r[] = $this->addFinance($order['member_id'], 11, lang('lan_trade_exchange_charge'), $order_fee, 2, $order['currency_id'],$trade_id);
            //写入手续费表
            $add = [
                'member_id' => $order['member_id'],
                'fee' => $order_fee,
                'currency_id' => $order['currency_id'],
                'currency_trade_id' => $order['currency_trade_id'],
                'type' => $order['type'],
                'add_time' => $time
            ];
            $r[] = db('mining_fee')->insertGetId($add);
        }
        if ($trade_order_fee > 0) {
            $r[] = $this->addFinance($trade_order['member_id'], 11, lang('lan_trade_exchange_charge'), $trade_order_fee, 2, $trade_order['currency_id'],$trade_id2);
            //写入手续费表
            $add2 = [
                'member_id' => $trade_order['member_id'],
                'fee' => $trade_order_fee,
                'currency_id' => $trade_order['currency_id'],
                'currency_trade_id' => $trade_order['currency_trade_id'],
                'type' => $trade_order['type'],
                'add_time' => $time
            ];
            $r[] = db('mining_fee')->insertGetId($add2);
        }

        $num =$num- $trade_num;
        if ($num>0){
            //递归
            $r[]= $this->trade($currencyId, $type, $num, $price,$trade_currency_id);
        }
        return $r;

    }

    /**
     * 返回一条挂单记录
     * @param int $currencyId 积分类型id
     * @param float $price 交易价格
     * @return array 挂单记录
     */
    private function getOneOrders($type,$currencyId,$price,$trade_currency_id){
        switch ($type){
            case 'buy':$gl='egt';$order='price desc'; break;
            case 'sell':$gl='elt'; $order='price asc';break;
        }
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        $where['price']=array($gl,$price);
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order($order.',add_time asc')->find();
    }
    /**
     * 返回用户第一条未成交的挂单
     * @param int $memberId 用户id
     * @param int $currencyId 积分类型id
     * @return array 挂单记录
     */
    private function getFirstOrdersByMember($memberId,$type,$currencyId,$trade_currency_id){
        $where['member_id']=$memberId;
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order('add_time desc')->find();
    }
    /**
     *  返回指定价格排序的订单
     * @param char $type  buy sell
     * @param float $price   交易价格
     * @param char $order 排序方式
     */
    private function getOrdersByPrice($currencyId,$type,$price,$order){
        switch ($type){
            case 'buy': $gl='elt';break;
            case 'sell': $gl='egt';break;
        }
        $where['currency_id']=$currencyId;
        $where['price']=array($gl,$price);
        $where['status']=array('in',array(0,1));
        return  db('Orders')->where($where)->order("price  $order")->select();
    }


    /**
     * 增加交易记录
     * @param unknown $member_id
     * @param unknown $currency_id
     * @param unknown $currency_trade_id
     * @param unknown $price
     * @param unknown $num
     * @param unknown $type
     * @return boolean
     */
    private function addTrade($member_id, $currency_id, $currency_trade_id, $price, $num, $type, $fee,$orders_id)
    {
        $fee = $price * $num * $fee;
        $data = array(
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'price' => $price,
            'num' => $num,
            'fee' => $fee,
            'money' => $price * $num,
            'type' => $type,
            'orders_id' => $orders_id,
            'add_time' => time(),
            'trade_no' => 'T'.time()
        );
        if ($res = db('Trade')->insertGetId($data)) {

                return $res;

        } else {
            return false;
        }
    }

    /**
     * 返回用户第一条交易记录(已弃用）
     * @param $memberId
     * @param $type
     * @param $currencyId
     * @return array|mixed|null|string
     */
    private function getFirstTradeByMember($memberId, $type, $currencyId)
    {
        $where['member_id'] = $memberId;
        $where['currency_id'] = $currencyId;
        $where['type'] = $type;
        return db('Trade')->where($where)->order('add_time desc')->find();
    }

    /**
     * 手续费奖励计算(已弃用）
     * @param $fee 手续费
     * @param $fee_rate 奖励比率
     * @param $pid 会员id
     * @param $trade 交易订单
     * @param $level 级别
     * @param $mid 子会员id
     * @return bool
     */
    private function setTradeFeeAward($fee, $fee_rate, $pid, $trade, $level, $mid)
    {
        if ($trade_fee = db('trade_fee')->where(array('member_id' => $pid))->find()) {
            $r[] = db('trade_fee')->where(array('member_id' => $pid))->setInc('freeze_money', $fee);
            $add = array(
                'freeze_money' => $trade_fee['freeze_money'] + $fee,
                'member_id' => $pid,
                'trade_id' => $trade['trade_id'],
                'money' => $fee,
                'rate' => $fee_rate * 100,
                'type' => 1,
                'add_time' => time(),
                'level' => $level,
                'sid' => $mid
            );
            $r[] = db('trade_fee_log')->insertGetId($add);
        } else {
            $add = array(
                'member_id' => $pid,
                'freeze_money' => $fee
            );
            $r[] = db('trade_fee')->insertGetId($add);
            $add2 = array(
                'freeze_money' => $fee,
                'member_id' => $pid,
                'trade_id' => $trade['trade_id'],
                'money' => $fee,
                'rate' => $fee_rate * 100,
                'type' => 1,
                'add_time' => time(),
                'level' => $level,
                'sid' => $mid
            );
            $r[] = db('trade_fee_log')->insertGetId($add2);
        }
        if (in_array(false, $r)) {
            return false;
        } else {
            return true;
        }
    }
}