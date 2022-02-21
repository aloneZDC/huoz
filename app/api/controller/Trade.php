<?php



namespace app\api\controller;
use app\common\model\Config;
use app\common\model\CurrencyInternationalQuotation;
use app\common\model\CurrencyPriceTemp;
use app\common\model\Kline;
use think\Page,think\Db;
use app\common\model\Transfer;
class Trade extends OrderBase
{
    protected $public_action = ['quotation1','kline','get_trade_price','international_quotation']; //无需登录即可访问
    public function _initialize() {
        parent::_initialize();
    }
    /**
     * @Desc:BB列表
     * @author: Administrator
     * @return array
     * @Date: 2018/12/20 0020 10:57
     */
//    public function quotation1()
//    {
//
//        //积分类型
//        $all_name = 'rs_all_currency_quotation1';
//        $srot = ['USDT'=>2,'XRP'=>3,'NC'=>4];
//        $currency_data  = $currency_data1 = cache($all_name);    // 修复慢的问题 2018-10-18
//
//        if (empty($currency_data)) {
//            $currency_data = $this->bbExchange();
//            $currency_id_mark = array_flip($this->currency_id_mark);
//            foreach ($currency_data as $k =>$v){
//                $currency_data1[] =  [
//                    'name' =>$k,
//                    'id' =>$currency_id_mark[$k],
//                    'data_list' =>$v,
//                ];
//            }
//
//            $usdt_cny = usd2cny();
//            foreach ($currency_data1 as $k=>$v){
//                foreach ($currency_data1[$k]['data_list'] as $key=>$value){
//                    $value1['change_price_24'] = !empty($value['24H_change_price'])?$value['24H_change_price']:0;
//                    $value1['change_24'] = !empty($value['24H_change'])?$value['24H_change']:0;
//                    $value1['done_num_24H'] = !empty($value['24H_done_num'])?$value['24H_done_num']:0;
//                    $value1['currency_id'] = !empty($value['currency_id'])?$value['currency_id']:0;
//                    $value1['trade_currency_id'] = !empty($value['trade_currency_id'])?$value['trade_currency_id']:0;
//                    $value1['currency_mark'] = !empty($value['currency_mark'])?$value['currency_mark']:0;
//                    $value1['trade_currency_mark'] = !empty($value['trade_currency_mark'])?$value['trade_currency_mark']:0;
//                    $value1['currency_buy_fee'] = !empty($value['currency_buy_fee'])?$value['currency_buy_fee']:0;
//                    $value1['currency_sell_fee'] = !empty($value['currency_sell_fee'])?$value['currency_sell_fee']:0;
//                    $value1['new_price'] = !empty($value['new_price'])?format_price($value['new_price']):0;
//                    $value1['new_price_status'] = !empty($value['new_price_status'])?$value['new_price_status']:0;
//                    $value1['new_cny'] = $value['new_price_cny'];
//                    if(NEW_PRICE_UNIT=='CNY') {
//                       $value1['new_price_usd'] = !empty($value['new_price_cny'])?format_price($value['new_price_cny']):0;
//                    } else {
//                       $value1['new_price_usd'] = !empty($value['new_price_usd'])?format_price($value['new_price_usd']):0;
//                    }
//                    $value1['new_price_unit'] = NEW_PRICE_UNIT;
//
//                    if($value1['new_price_status']==1){
//                        $value1['change_24']= '+'.$value['24H_change'];
//                    }elseif ($value1['new_price_status']==2){
//                        $value1['change_24']= '-'.$value['24H_change'];
//                    }
//                    $currency_data1[$k]['data_list'][$key]=$value1;
//                }
//                $currency_data1[$k]['data_list'] = array_values($currency_data1[$k]['data_list']);
//            }
//
//
//            foreach ($currency_data1 as $kk=>$vv){
//                $new_srot[$kk] = $srot[$vv['name']];
//            }
//
//            array_multisort($new_srot,$currency_data1);
//
//            cache($all_name, $currency_data1,60);
//        }
//        $currency_data_all = $currency_data1;
//        self::output(10000, '請求成功', $currency_data_all);
//    }
    /**
     * @Desc:BB列表
     * @author: Administrator
     * @return array
     * @Date: 2018/12/20 0020 10:57
     */
    public function quotation1()
    {

        $result =  \app\common\model\Trade::getAllQuotation();
        return $this->output_new($result);

        $page=input("post.page");
        $rows=input("post.rows");
        $type=input("post.types");
        //积分类型
        $all_name = 'rs_all_currency_quotation1_'.$this->exchange_rate_type;
        $srot = ['USDT'=>2,'XRP'=>3,'NC'=>4,'IOSCORE'=>5];
        $array= $currency_data  = cache($all_name);    // 修复慢的问题 2018-10-18
        if (empty($currency_data)) {
            $currency_data1 = [];
            $currency_data = $this->bbExchange();
            $currency_id_mark = array_flip($this->currency_id_mark);
            foreach ($currency_data as $k =>$v){
                $currency_data1[] =  [
                    'name' =>$k,
                    'id' =>$currency_id_mark[$k],
                    'data_list' =>$v,
                ];
            }

//            $usdt_cny = usd2cny();

            foreach ($currency_data1 as $k=>$v){
                foreach ($currency_data1[$k]['data_list'] as $key=>$value){
                    $is_special_area = \app\common\model\CurrencyArea::check($value['currency_id'],'currency_id,status');
                    if($is_special_area) {
                        $value1['is_special_area'] = 1;
                    } else {
                        $value1['is_special_area'] = 2;
                    }
                    $value1['change_price_24'] = !empty($value['24H_change_price'])?$value['24H_change_price']:0;
                    $value1['change_24'] = !empty($value['24H_change'])?$value['24H_change']:0;
                    $value1['done_num_24H'] = !empty($value['24H_done_num'])?keepPointV2($value['24H_done_num'], 2):0;
                    $value1['currency_id'] = !empty($value['currency_id'])?$value['currency_id']:0;
                    $value1['trade_currency_id'] = !empty($value['trade_currency_id'])?$value['trade_currency_id']:0;
                    $value1['currency_mark'] = !empty($value['currency_mark'])?$value['currency_mark']:0;
                    $value1['currency_name'] = $this->currency_id_name[$value1['currency_id']] ? $this->currency_id_name[$value1['currency_id']]: '';
                    $value1['trade_currency_mark'] = !empty($value['trade_currency_mark'])?$value['trade_currency_mark']:0;
                    $value1['trade_currency_name'] = $this->currency_id_name[$value1['trade_currency_id']] ? $this->currency_id_name[$value1['trade_currency_id']]: '';
                    $value1['currency_buy_fee'] = !empty($value['currency_buy_fee'])?$value['currency_buy_fee']:0;
                    $value1['currency_sell_fee'] = !empty($value['currency_sell_fee'])?$value['currency_sell_fee']:0;
                    $value1['new_price'] = !empty($value['new_price'])?keepPointV2($value['new_price'], 6):0;
                    $value1['new_price_status'] = !empty($value['new_price_status'])?$value['new_price_status']:0;
                    $value1['new_cny'] = keepPointV2($value['new_price_cny'], 4);
                    if($this->exchange_rate_type=='CNY') {
                        $value1['new_price_usd'] = !empty($value['new_price_cny'])?keepPointV2($value['new_price_cny'], 4):0;
                    } else {
                        $value1['new_price_usd'] = !empty($value['new_price_usd'])?keepPointV2($value['new_price_usd'], 4):0;
                    }
                    $value1['new_price_unit'] = $this->exchange_rate_type;

                    if($value1['new_price_status']==1){
                        $value1['change_24']= '+'.$value['24H_change'];
                    }elseif ($value1['new_price_status']==2){
                        $value1['change_24']= '-'.$value['24H_change'];
                    }
                    if($value1['currency_id']==3){
                        $value1['change_24']=1;
                    }
                    $array[]=$value1;
                    $currency_data1[$k]['data_list'][$key]=$value1;
                }
                $currency_data1[$k]['data_list'] = array_values($currency_data1[$k]['data_list']);
            }

//            foreach ($currency_data1 as $kk=>$vv){
//                $new_srot[$kk] = $srot[$vv['name']];
//            }
            cache($all_name, $array,60);
           // array_multisort($new_srot,$currency_data1);

        }
        if(!empty($array)&&is_numeric($page)&&$page>0&&is_numeric($rows)){
            if(count($array)>=$page*$rows){
                $arr=null;
                foreach ($array as $k=>$value){
                    if($k>=($page*$rows-$rows)&&$k<($page*$rows)){
                        $arr[]=$value;
                    }
                }
                $array=$arr;
                $array= sortByOneField($array,"change_24",SORT_DESC);
            }else{
                $array=null;
            }
        }elseif ($type=="home"){
            if($array){
                $array= sortByOneField($array,"change_24",SORT_DESC);
                $rows=5;
                $arr=null;
                foreach ($array as $k=>$value){
                    if($k<$rows){
                        $arr[]=$value;
                    }
                }
                $array=$arr;
            }
        }
//        $currency_data_all = $currency_data1;
        if(!empty($array)){
            self::output(10000, lang("data_success"), $array);
        }else{
            self::output(10001, lang("not_data"), $array);
        }
    }

    //买入
    public function buy()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter('post');

        //敏感账户不能出售
        $member = Db::name('member')->field('is_sensitive')->where(['member_id'=>$this->member_id])->find();
        if(!$member || $member['is_sensitive']==1) {
            self::output(ERROR1, lang('operation_deny'));
        }

        if (time()<1592409600){
            self::output(ERROR1, lang('bb_open_time'));
        }

//        $pwd = input('pwd','','strval');
//        if(empty($pwd)) self::output(10101, lang('lan_Incorrect_transaction_password'));
//        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
//        if(is_string($checkPwd)) self::output(10101, $checkPwd);

        $buyprice = floatval(input('post.buyprice'));
        $buynum = floatval(input('post.buynum'));
        $buypwd = input('post.buypwd', '', 'strval');
        //更换币币交易 获取xx_xx参数
        $buycurrency_id=strval(input('post.currency_id'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($buycurrency_id);
        if (empty($getbuysellid)){

            self::output(10115, '传入参数错误');
        }
        $buycurrency_id=$getbuysellid['currency_id'];
        //获取积分的相关信息
        $currency=$this->getCurrencyByCurrencyId($buycurrency_id);

        $is_free_fee = Db::name('orders_free_fee')->where(['member_id'=>$this->member_id])->find();
        if($is_free_fee) $currency['currency_buy_fee'] = 0;

        $currency['trade_currency_id']=$getbuysellid['currency_trade_id'];
        if ($currency['is_lock']) {
            self::output(10101, '该积分类型暂时不允许交易');
        }

        if (!is_numeric($buyprice) || !is_numeric($buynum)) {
            self::output(10102,lang('lan_trade_wrong'));
        }
        if ($buyprice * $buynum < 0) {
            self::output(10103,lang('lan_trade_entrust_lowest'));
        }
        /* if (!is_int($buynum)){
         $data['status']=-1;
         $data['info']='交易数量必须是整数';
         $this->ajaxReturn($data);
         }*/
        if ($buynum < 0) {
        self::output(10104,lang('lan_trade_change_quantity'));
    }

        //$min_num = Config::get_value('bb_trade_min_num',0);
        $min_num = $currency['trade_min_num'];
        if($min_num>0 && $buynum<$min_num) {
            self::output(10104,lang('lan_num_not_less_than').' '.$min_num);
        }

        //时间限制
        if ($currency['is_time']) {
            $newtime = date("H", time());
            $min_time = $currency['min_time'];
            if ($newtime < $min_time) {
                self::output(10105,lang('lan_trade_no_time_to'));
            }
            $max_time = $currency['max_time'] - 1;
            if ($newtime > $max_time) {
                self::output(10106,lang('lan_trade_over_time'));
            }
        }
        //价格限制
        if ($currency['is_limit']) {
            /*$getlastmessage = $this->getlastmessage($buycurrency_id,$currency['trade_currency_id']);
            $newprice = $getlastmessage + ($getlastmessage * $currency['max_limit']) / 100;
            $newprice2 = $getlastmessage - ($getlastmessage * $currency['min_limit']) / 100;
            if ($getlastmessage) {
                if ($buyprice > $newprice) {

                    self::output(10107,lang('lan_Buy_price_bad_failure'));
                }
                if ($buyprice < $newprice2) {

                    self::output(10108,lang('lan_Buy_price_bad_failure1'));
                }
            }*/
            $robotWhere = ['currency_id'=>$buycurrency_id,'trade_currency_id'=>$currency['trade_currency_id']];
            $robotFind = Db::name('OrdersRebotTrade')->where($robotWhere)->find();
            if ($robotFind) {
                $robotList = [];
                if ($robotFind['buy_rebot_user_id']) $robotList[] = $robotFind['buy_rebot_user_id'];
                if ($robotFind['sell_rebot_user_id']) $robotList[] = $robotFind['sell_rebot_user_id'];
                if (empty($robotList) || !in_array($this->member_id, $robotList)) {
                    $lastPrice = \app\common\model\Trade::getLastTradePrice($buycurrency_id,$currency['trade_currency_id']);
                    if ($lastPrice > 0) {
                        $maxPrice = $lastPrice + ($lastPrice * $currency['max_limit']) / 100;
                        if ($buyprice > $maxPrice) {
                            self::output(10107,lang('lan_Buy_price_bad_failure4'));
                        }
                    }
                }
            }
        }

        $purchase_limit = \app\common\model\Trade::purchase_limit('buy',$this->member_id,$currency['currency_id'],$currency['trade_currency_id'],$buyprice,$buynum);
        if($purchase_limit['code']!=SUCCESS){
            self::output($purchase_limit['code'],$purchase_limit['message']);
        }

         $where['member_id'] = $this->member_id;
         $member = db('member')->where($where)->field('pwdtrade,forzen_rmb')->find();
//        $checkpwd =  model("Member")->checkPassword($buypwd,$member['pwdtrade']);
//         if (!$checkpwd) {
//             self::output(10109,lang('lan_Incorrect_transaction_password'));
//         }

        if ($this->checkUserMoney($buynum, $buyprice, 'buy', $currency)) {
            self::output(10110,lang('lan_trade_underbalance'));
        }

         if ($member['forzen_rmb'] < -1) {
             $forzen_money = abs($member['forzen_rmb']);
             self::output(10111,lang('lan_trade_owe_the_platform') . $forzen_money .lang('lan_trade_value_amount'));
         }

        //限制星期6天交易
        $da = date("w");
        if ($da == '6') {
            if ($currency['trade_day6']) {
                self::output(10113,lang('lan_trade_six_today)'));
            }

        }
        if ($da == '0') {
            if ($currency['trade_day7']) {
                self::output(10114,lang('lan_trade_Sunday'));
            }
        }

        //开启事物
        Db::startTrans();
        //计算买入需要的金额
        $trade_money = round($buynum * $buyprice * (1 + ($currency['currency_buy_fee'] / 100)), 6);

        //挂单流程
        $r[] = $orders_id = $this->guadan($buynum, $buyprice, 'buy', $currency);

        model("AccountBook")->addLog([
            'member_id'=>$this->member_id,
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

        //操作账户
        $r[] = $this->setUserMoney($this->member_id, $currency['trade_currency_id'], $trade_money, 'dec', 'num');
        $r[] = $this->setUserMoney($this->member_id, $currency['trade_currency_id'], $trade_money, 'inc', 'forzen_num');

        //交易流程
        /*$r1[] = $this->trade($currency['currency_id'], 'buy', $buynum, $buyprice,$currency['trade_currency_id']);
        foreach ($r1 as $v) {
            $r[] = $v;
        }*/
        if (in_array(false, $r)) {
            Db::rollback();
            self::output(10112,lang('lan_trade_the_operation_failurer'));
        } else {
            Db::commit();
            if (!empty($this->member_id)) {
                $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
                $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
                $user_currency_money['currency_trade']['num'] = $this->getUserMoney($currency['trade_currency_id'], 'num');
                $user_currency_money['currency_trade']['forzen_num'] = $this->getUserMoney($currency['trade_currency_id'], 'forzen_num');
                $usermb = db('Member')->field('rmb,forzen_rmb')->where('member_id=' . $this->member_id)->find();
//                 if ($currency['trade_currency_id'] == 0) {
//                     $user_currency_money['currency_trade']['num'] = $this->member['rmb'];
//                     $user_currency_money['currency_trade']['forzen_num'] = $this->member['forzen_rmb'];
//                 }


                //最大可卖
                $sell_num = sprintf('%.6f', $user_currency_money['currency']['num']);

            }

            $data = [
                'from_over' => $user_currency_money['currency']['num'],
                'from_lock' => $user_currency_money['currency']['forzen_num'],
                'from_total' => $user_currency_money['currency']['num'] + $user_currency_money['currency']['forzen_num'],
                'from_rmb_over' => $usermb['rmb'],
                'to_over' => $user_currency_money['currency_trade']['num'],
                'to_lock' => $user_currency_money['currency_trade']['forzen_num'],
                'to_total' => $user_currency_money['currency_trade']['forzen_num'] + $user_currency_money['currency_trade']['num'],
                'coinsale_max' => $sell_num
            ];
            self::output(10000,lang('lan_operation_success'), $data);
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
        //声明本方法的请求方式(必须)
        parent::method_filter('post');

        //敏感账户不能出售
        $member = Db::name('member')->field('is_sensitive')->where(['member_id'=>$this->member_id])->find();
        if(!$member || $member['is_sensitive']==1) {
            self::output(ERROR1, lang('operation_deny'));
        }

        if (time()<1592409600){
            self::output(ERROR1, lang('bb_open_time'));
        }

//        $pwd = input('pwd','','strval');
//        if(empty($pwd)) self::output(10101, lang('lan_Incorrect_transaction_password'));
//        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
//        if(is_string($checkPwd)) self::output(10101, $checkPwd);

        $sellprice = floatval(input('post.sellprice'));
        $sellnum = floatval(input('post.sellnum'));
        $sellpwd = input('post.sellpwd', '', 'strval');
        //更换币币交易 获取xx_xx参数
        $currency_id=strval(input('post.currency_id'));
        //获取买卖currency_id
        $getbuysellid=$this->geteachothertrade_id($currency_id);
        if (!$getbuysellid){
            self::output(10115, '传入参数错误');
        }

        $currency_id=$getbuysellid['currency_id'];
        //获取积分类型信息
        $currency=$this->getCurrencyByCurrencyId($currency_id);

        $is_free_fee = Db::name('orders_free_fee')->where(['member_id'=>$this->member_id])->find();
        if($is_free_fee) $currency['currency_sell_fee'] = 0;

        $currency['trade_currency_id']=$getbuysellid['currency_trade_id'];
        //检查是否开启交易
        if ($currency['is_lock']) {
            self::output(10101, '该积分类型暂时不能交易');
        }

        if (empty($sellprice) || empty($sellnum)) {
            self::output(10102, '卖出价格或数量不能为空');
        }

        if ($sellnum < 0) {
            self::output(10114,lang('lan_trade_change_quantity'));
        }

        //$min_num = Config::get_value('bb_trade_min_num',0);
        $min_num = $currency['trade_min_num'];
        if($min_num>0 && $sellnum<$min_num) {
            self::output(10104,lang('lan_num_not_less_than').' '.$min_num);
        }

        if ($sellnum * $sellprice < 0) {
            self::output(10103,lang('lan_trade_entrust_lowest'));
        }

//         if (empty($sellpwd)) {
//             self::output(10104,lang('lan_user_Transaction_password_empty'));
//         }

        $where['member_id'] = $this->member_id;
         $member = db('member')->where($where)->field('pwdtrade')->find();

//        $checkpwd =  model("Member")->checkPassword($sellpwd,$member['pwdtrade']);
//         if (!$checkpwd) {
//             self::output(10105,lang('lan_Incorrect_transaction_password'));
//         }
        //时间限制
        if ($currency['is_time']) {
            $newtime = date("H", time());
            $min_time = $currency['min_time'];
            if ($newtime < $min_time) {
                self::output(10106,lang('lan_trade_no_time_to'));
            }
            $max_time = $currency['max_time'] - 1;
            if ($newtime > $max_time) {
                self::output(10107,lang('lan_trade_over_time'));
            }
        }

        //检查账户冻结是否负数
        $where_false['member_id'] = $this->member_id;
        $where_false['currency_id'] = $currency_id;
        $checkfalse = db('currency_user')->field('num,forzen_num,currency_id')->where($where_false)->find();
        if ($checkfalse['forzen_num'] < 0) {
            self::output(10108, '账户数据异常，请联系账号服务客服');

        }
        if ($checkfalse['num'] < 0) {
            self::output(10109, '账户数据异常，请联系账号服务客服');

        }
        //价格限制
        if ($currency['is_limit']) {
            /*$getlastmessage = $this->getlastmessage($currency_id,$currency['trade_currency_id']);
            $newprice = $getlastmessage + ($getlastmessage * $currency['max_limit']) / 100;
            $newprice2 = $getlastmessage - ($getlastmessage * $currency['min_limit']) / 100;
            if ($getlastmessage) {
                if ($sellprice > $newprice) {

                    self::output(10110,lang('lan_Buy_price_bad_failure2'));
                }
                if ($sellprice < $newprice2) {

                    self::output(10111,lang('lan_Buy_price_bad_failure3'));
                }
            }*/
            $robotWhere = ['currency_id'=>$currency_id,'trade_currency_id'=>$currency['trade_currency_id']];
            $robotFind = Db::name('OrdersRebotTrade')->where($robotWhere)->find();
            if ($robotFind) {
                $robotList = [];
                if ($robotFind['buy_rebot_user_id']) $robotList[] = $robotFind['buy_rebot_user_id'];
                if ($robotFind['sell_rebot_user_id']) $robotList[] = $robotFind['sell_rebot_user_id'];
                if (empty($robotList) || !in_array($this->member_id, $robotList)) {
                    $lastPrice = \app\common\model\Trade::getLastTradePrice($currency_id,$currency['trade_currency_id']);
                    if ($lastPrice > 0) {
                        $minPrice = $lastPrice - ($lastPrice * $currency['min_limit']) / 100;
                        if ($sellprice < $minPrice) {
                            self::output(10107,lang('lan_Buy_price_bad_failure5'));
                        }
                    }
                }
            }
        }

        $purchase_limit = \app\common\model\Trade::purchase_limit('sell',$this->member_id,$currency['currency_id'],$currency['trade_currency_id'],$sellprice,$sellnum);
        if($purchase_limit['code']!=SUCCESS){
            self::output($purchase_limit['code'],$purchase_limit['message']);
        }

        //检查账户是否有钱
        if ($this->checkUserMoney($sellnum, $sellprice, 'sell', $currency)) {
            self::output(10112,lang('lan_trade_underbalance'));
        }

        //限制星期6天交易
        $da = date("w");
        if ($da == '6') {
            if ($currency['trade_day6']) {
                self::output(10113,lang('lan_trade_six_today'));
            }

        }
        if ($da == '0') {
            if ($currency['trade_day7']) {
                self::output(10114,lang('lan_trade_Sunday'));
            }
        }

        //减可用钱 加冻结钱
        Db::startTrans();

        $r[] = $orders_id = $this->guadan($sellnum, $sellprice, 'sell', $currency);
        $r[] = model('AccountBook')->addLog([
            'member_id' => $this->member_id,
            'currency_id' => $currency['currency_id'],
            'type'=> 11,
            'content' => 'lan_trade',
            'number_type' => 2,
            'number' => $sellnum,
            'fee' => 0,
            'to_member_id' => 0,
            'to_currency_id' => 0,
            'third_id' => $orders_id,
        ]);


        $r[] = $this->setUserMoney($this->member_id, $currency['currency_id'], $sellnum, 'dec', 'num');
        $r[] = $this->setUserMoney($this->member_id, $currency['currency_id'], $sellnum, 'inc', 'forzen_num');
        //成交
        //$r[] = $this->trade($currency['currency_id'], 'sell', $sellnum, $sellprice,$currency['trade_currency_id']);

        if (in_array(false, $r)) {
            Db::rollback();
            self::output(10113,lang('lan_trade_the_operation_failurer'));
        } else {

            Db::commit();
            if (!empty($this->member_id)) {
                $user_currency_money['currency']['num'] = $this->getUserMoney($currency['currency_id'], 'num');
                $user_currency_money['currency']['forzen_num'] = $this->getUserMoney($currency['currency_id'], 'forzen_num');
                $usermb = db('Member')->field('rmb,forzen_rmb')->where('member_id=' . $this->member_id)->find();
                /* $user_currency_money['currency_trade']['num']=$this->getUserMoney($currency['trade_currency_id'], 'num');
                 $user_currency_money['currency_trade']['forzen_num']=$this->getUserMoney($currency['trade_currency_id'], 'forzen_num');
                 if($currency['trade_currency_id']==0){
                 $user_currency_money['currency_trade']['num']=$this->member['rmb'];
                 $user_currency_money['currency_trade']['forzen_num']=$this->member['forzen_rmb'];
                 }
                */

                //最大可卖
                $sell_num = sprintf('%.6f', $user_currency_money['currency']['num']);
                $to_over = $this->getmoney();
                $to_lock = $this->getmoney2();

                $data = [
                    'from_over' => $user_currency_money['currency']['num'],
                    'from_lock' => $user_currency_money['currency']['forzen_num'],
                    'from_total' => $user_currency_money['currency']['num'] + $user_currency_money['currency']['forzen_num'],
                    'from_rmb_over' => $usermb['rmb'],
                    'to_over' => $to_over,
                    'to_lock' => $to_lock,
                    //$msg['to_over']=$user_currency_money['currency_trade']['num'];
                    // $msg['to_lock']=$user_currency_money['currency_trade']['forzen_num'];
                    'to_total' => $to_over + $to_lock,
                    'coinsale_max' => $sell_num
                ];
                self::output(10000,lang('lan_operation_success'), $data);
            }

        }


    }
    /**
     *
     * @param int $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     * @param $currency_id 交易积分类型
     */
    private function checkUserMoney($num, $price, $type, $currency)
    {

        //获取交易积分类型信息
        if ($type == 'buy') {
            $trade_money = $num * $price * (1 + $currency['currency_buy_fee'] / 100);
            $currency_id = $currency['trade_currency_id'];
        } else {
            $trade_money = $num;
            $currency_id = $currency['currency_id'];
        }
        //和自己的账户做对比 获取账户信息
        $money = $this->getUserMoney($currency_id, 'num');
        if ($money < $trade_money) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @desc 转账
     */
    public function  accountQuery(){

        $model_transfer=new Transfer();
        $r=$model_transfer->currency_xrp($this->member_id);
        $this->output_new($r);



//        $info_data = db("currency")->alias('c')
//            ->join("currency_user u","c.currency_id=u.currency_id",'left')
//            ->where(['c.currency_mark'=>'XRP','u.member_id'=>$this->member_id])->field("c.currency_id,c.currency_mark,u.num,u.forzen_num,u.lock_num")->find();
//       $data  = [
//           'currency_id'=>$info_data['currency_id'],
//           'currency_mark'=>$info_data['currency_mark'],
//           'num'=>format_price(empty($info_data['num'])?0:$info_data['num']),
//           'forzen_num'=>format_price(empty($info_data['forzen_num'])?0:$info_data['forzen_num']),
//           'lock_num'=>format_price(empty($info_data['lock_num'])?0:$info_data['lock_num']),
//       ];
//        self::output(10000, '請求成功', $data?:[]);
    }


    /**
     * @Desc: 转账 操作
     * @author: Administrator
     * @return array
     * @Date: 2018/12/11 0011 14:58
     */
    public function accountOperation()
    {

        $type = input('post.type',0);
        $account = input('post.account','','trim');
        $phone_code = strval(input('phone_code',''));
        $to_member_id = input('post.to_member_id', 0, 'trim');
//        $currency_id = input('post.currency_id', 0, 'trim');
        $num = input('post.num', 0, 'trim');
        $pwd = input('post.pwd', '', 'trim');

        if($type==4 || $type==6|| $type==7) {
            $result = model('Exchange')->transfer($type,$account,$this->member_id,$to_member_id,$num,$pwd,$phone_code);
            if(is_string($result)) $this->output(10001,$result);

            $this->output(10000,lang('lan_operation_success'));
        } else {
            $model_transfer=new Transfer();
            $r=$model_transfer->transfer_accounts($type,$account,$this->member_id,$to_member_id,$num,$pwd,$phone_code);
            $this->output_new($r);
        }




//        $account = input('account','','trim');
//        if(!empty($account)){
//            $account_member_id = db('member')->where(['phone|email'=>$account])->value('member_id');
//        }
//        $to_member_id = input('to_member_id', '', 'trim');
//        if( empty($to_member_id) || empty($account_member_id) || $account_member_id != $to_member_id) {
//            self::output(12000, lang("lan_user_data_error") );//参数错误
//        }
//        $currency_id = input('currency_id', '', 'trim');
//        $num = (input('num', '', 'trim'));
//        $pwd = input('pwd', '', 'trim');
//        $fee = 0;
//        if (empty($to_member_id) || empty($currency_id) || empty($num) || empty($pwd)){
//            self::output(12000, lang("lan_modifymember_parameter_error") );//参数错误
//        }
//        $currency_user = db('currency_user');
//        $currency_user_num = $currency_user->where(['member_id'=>$this->member_id,'currency_id'=>$currency_id])->value('num');
//        if($currency_user_num < $num){
//            self::output(12000, lang("lan_your_credit_is_running_low") );//余额不足
//        }
//       $member_pwdtrade = db('member')->where(['member_id'=>$this->member_id])->value('pwdtrade');
//        $checkpwd = model("Member")->checkPassword($pwd,$member_pwdtrade);
//        if(!$checkpwd){
//            self::output(12000, lang("lan_Password_error") );//密码错误
//        }
//        Db::startTrans();
//        $feeNum = ($fee * $num);
//        //        model("CurrencyUserStream")->addStream($this->member_id, $currency_id, 1, $num, 1, 81, $currency_id, '转账转出');
//        model("AccountBook")->addLog([
//            'member_id'=>$this->member_id,
//            'currency_id'=>$currency_id,
//            'number_type'=>2,
//            'number'=>$num,
//            'type'=>18,
//            'content'=>"lan_Transfer_out_of_account",
//            'fee'=>0,
//            'to_member_id'=>$to_member_id,
//            'to_currency_id'=>0,
//            'third_id'=>0,
//        ]);
//       $operation1 = $currency_user->where(['member_id'=>$this->member_id,'currency_id'=>$currency_id])->setDec('num',$num);
////        model("CurrencyUserStream")->addStream($to_member_id, $currency_id, 1, $num-$feeNum, 2, 81, $currency_id, '转账转入');
//        model("AccountBook")->addLog([
//            'member_id'=>$to_member_id,
//            'currency_id'=>$currency_id,
//            'number_type'=>1,
//            'number'=>$num-$feeNum,
//            'type'=>18,
//            'content'=>"lan_Transfer_to_account",
//            'fee'=>$feeNum,
//            'to_member_id'=>$this->member_id,
//            'to_currency_id'=>0,
//            'third_id'=>0,
//        ]);
//       $operation1 = $currency_user->where(['member_id'=>$to_member_id,'currency_id'=>$currency_id])->setInc('num',$num-$feeNum);
//
//        if(empty($operation1) || empty($operation1)){
//           Db::rollback();
//           self::output(12000, lang("lan_trade_the_operation_failurer") );
//       }else{
//           Db::commit();
//           self::output(10000, lang("lan_operation_success") );
//       }
    }
    /**
     * 挂单
     * @param int $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     * @param $currency_id 交易积分类型
     */
    private function guadan($num, $price, $type, $currency)
    {
        //获取交易积分类型信息

        switch ($type) {
            case 'buy':
                $fee = $currency['currency_buy_fee'] / 100;
                $currency_trade_id = $currency['trade_currency_id'];
                break;
            case 'sell':
                $fee = $currency['currency_sell_fee'] / 100;
                $currency_trade_id = $currency['trade_currency_id'];
                break;
        }

        $data = array(
            'member_id' => $this->member_id,
            'currency_id' => $currency['currency_id'],
            'currency_trade_id' => $currency['trade_currency_id'],
            'price' => $price,
            'num' => $num,
            'trade_num' => 0,
            'fee' => $fee,
            'add_time' => time(),
            'type' => $type,
        );
        $msg = db('Orders')->insertGetId($data);
        if (empty($msg)) {
            $msg = 0;
        }

        return $msg;

    }

    private function trade($currencyId, $type, $num, $price,$trade_currency_id)
    {
        if ($type == 'buy') {
            $trade_type = 'sell';
        } else {
            $trade_type = 'buy';
        }
        $memberId = $this->member_id;
        //获取操作人一个订单
        $order=$this->getFirstOrdersByMember($memberId,$type ,$currencyId,$trade_currency_id);
        //获取对应交易的一个订单
        $trade_order=$this->getOneOrders($trade_type, $currencyId,$price,$trade_currency_id);
        //如果没有相匹配的订单，直接返回
        if (empty($trade_order) ) {
            $r[] = true;
            return $r;
        }

        //如果有就处理订单
        $trade_num = min($num, $trade_order['num'] - $trade_order['trade_num']);

        //增加本订单的已经交易的数量
        $r[] = db('Orders')->where("orders_id={$order['orders_id']}")->setInc('trade_num', $trade_num);

        $r[] = db('Orders')->where("orders_id={$order['orders_id']}")->setField('trade_time', time());
        //增加trade订单的已经交易的数量
        $r[] = db('Orders')->where("orders_id={$trade_order['orders_id']}")->setInc('trade_num', $trade_num);
        $r[] = db('Orders')->where("orders_id={$trade_order['orders_id']}")->setField('trade_time', time());

        //更新一下订单状态
        $r[] = db('Orders')->where("trade_num>0 and status=0")->setField('status', 1);
        $r[] = db('Orders')->where("num=trade_num")->setField('status', 2);

        //处理资金
        $trade_price = 0;
        switch ($type) {
            case 'buy':
                $order_money = sprintf('%.6f', $trade_num * $order['price'] * (1 + $order['fee']));
                $trade_order_money = $trade_num * $trade_order['price'] * (1 - $trade_order['fee']);
                $trade_price = min($order['price'], $trade_order['price']);
                //$trade_price=$order['price'];
             //   model('CurrencyUserStream')->addStream($this->member_id, $order['currency_id'], 1, $trade_num, 1, 22, 0, '币币交易增加可用');
                model("AccountBook")->addLog([
                    'member_id'=>$this->member_id,
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
                $r[] = $this->setUserMoney($this->member_id, $order['currency_id'], $trade_num, 'inc', 'num');

               // model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 2, $order_money, 2, 22, 0, '币币交易增加可用');
                $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $order_money, 'dec', 'forzen_num');
              //  model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_id'], 2, $trade_num, 2, 22, 0, '币币交易扣除冻结');
                $r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'dec', 'forzen_num');
//                model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_trade_id'], 1, $trade_order_money, 1, 22, 0, '币币交易增加可用');
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
                $r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'inc', 'num');


                $back_price = $order['price'] - $trade_price;
                if ($back_price > 0) {
                    if ($order['fee'] > 0) {
                        //返还未成交部分手续费
                        //   model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 1, $trade_num * $back_price * $order['fee'], 1, 25, 0, '返回手续费');
                        model("AccountBook")->addLog([
                            'member_id'=>$this->member_id,
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
                        $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                    }
                    //返还多扣除的挂单金额
                 //   model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 1, $trade_num * $back_price, 1, 23, 0, '返还多扣除的挂单金额');
                    model("AccountBook")->addLog([
                        'member_id'=>$this->member_id,
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
                    $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                }
                break;
            case 'sell':
                $order_money = $trade_num * $order['price'] * (1 - $order['fee']);
                $trade_order_money = sprintf('%.6f', $trade_num * $trade_order['price'] * (1 + $trade_order['fee']));
                $trade_price = max($order['price'], $trade_order['price']);

              //  model('CurrencyUserStream')->addStream($this->member_id, $order['currency_id'], 2, $trade_num, 2, 22, 0, '币币交易扣除冻结');
                $r[] = $this->setUserMoney($this->member_id, $order['currency_id'], $trade_num, 'dec', 'forzen_num');

               // model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 1, $order_money, 1, 22, 0, '币币交易增加可用');
                model("AccountBook")->addLog([
                  'member_id'=>$this->member_id,
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
                $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $order_money, 'inc', 'num');

              //  model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_id'], 1, $trade_num, 1, 22, 0, '币币交易增加可用');
                model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_id'],
                    'number_type'=>1,
                    'number'=> $trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> 0,
                    'to_member_id'=>$this->member_id,
                    'to_currency_id'=>$trade_order['currency_trade_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                $r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'inc', 'num');

              //  model('CurrencyUserStream')->addStream($trade_order['member_id'], $trade_order['currency_trade_id'], 2, $trade_order_money, 2, 22, 0, '币币交易扣除冻结');
                $r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'dec', 'forzen_num');
                $back_price=$trade_price-$order['price'];

                if ($back_price > 0) {

                    if ($order['fee'] > 0) {
                        //返还未成交部分手续费
                        //    model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 1, $trade_num * $back_price * $order['fee'], 1, 25, 0, '返回手续费');
                        model("AccountBook")->addLog([
                            'member_id'=>$this->member_id,
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
                        $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                    }
                    //返还多扣除的挂单金额

                //    model('CurrencyUserStream')->addStream($this->member_id, $order['currency_trade_id'], 1, $trade_num * $back_price, 1, 23, 0, '返还多扣除的挂单金额');
                    model("AccountBook")->addLog([
                        'member_id'=>$this->member_id,
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

                    $r[] = $this->setUserMoney($this->member_id, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                }
                break;
        }

        //写入成交表
        $r[] = $trade_id = $this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'], $trade_price, $trade_num, $order['type'], $order['fee'],$trade_order['orders_id'],$order['orders_id'],$trade_order['member_id']);
        $r[] = $trade_id2 = $this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'], $trade_order['fee'],$order['orders_id'],$trade_order['orders_id'],$order['member_id']);


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
            $r[] = db('mining_fee')->insert($add);

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

        $num = $num - $trade_num;
        if ($num > 0) {
            //递归
            $r[] = $this->trade($currencyId, $type, $num, $price,$trade_currency_id);
        }
        return $r;

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
        return db('Orders')->where($where)->order('add_time desc,orders_id desc')->find();
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


    public function getmoney()
    {
        $num = db('Member')->field('rmb')->where("member_id={$this->member_id}")->find();
        // print_r($num);

        $num2 = $num['rmb'];
        return $num2;
    }

    public function getmoney2()
    {
        $num = db('Member')->field('forzen_rmb')->where("member_id={$this->member_id}")->find();
        $num2 = $num['forzen_rmb'];
        return $num2;
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
    private function addTrade($member_id, $currency_id, $currency_trade_id, $price, $num, $type, $fee,$orders_id,$other_orders_id=0,$other_member_id=0)
    {
        $fee = $price * $num * $fee;
        $data = array(
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'other_orders_id' => $other_orders_id,
            'other_member_id' => $other_member_id,
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
//                D('CurrencyUserStream')->addStream($member_id, $currency_trade_id, 1, $fee, 2, 24, 0, '币币交易手续费');
                return $res;
            } else {
                return false;
            }
    }

    /**
     * 获取交易对的价格
     * @return \json
     * Create by: Red
     * Date: 2019/9/9 11:53
     */
    function get_trade_price(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id=input("post.currency_id");
        $currency_trade_id=input("post.currency_trade_id");
        if(is_numeric($currency_id)&&is_numeric($currency_trade_id)){
            $currency_price=$this->getPriceByCurrencyId($currency_id);
            $trade_price=$this->getPriceByCurrencyId($currency_trade_id);
            if(isset($currency_price['cny'])&&isset($trade_price['cny'])){
                $price=$trade_price['cny']>0 ? bcdiv($currency_price['cny'],$trade_price['cny'],4) : 0;
                $r['code']=SUCCESS;
                $r['message']=lang("data_success");
                $r['result']=$price;
            }
        }
        return ajaxReturn($r);
    }

    public function international_quotation() {
        $result = CurrencyInternationalQuotation::get_list();
        $this->output_new($result);
    }

    public function get_trade_currency_price() {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id=input("post.currency_id");
        $currency_trade_id=input("post.currency_trade_id");
        if(is_numeric($currency_id)&&is_numeric($currency_trade_id)){
            $trade_price = \app\common\model\Trade::getLastTradePrice($currency_id,$currency_trade_id);
            $autoTrade = Db::name('currency_autotrade')->where(['currency_id'=>$currency_id,'trade_currency_id'=>$currency_trade_id,'is_autotrade'=>1,'kline_huobi'=>1])->find();
            if ($autoTrade) {
                $where = [
                    'type'=>60,
                    'currency_id'=>$currency_id,
                    'currency_trade_id'=>$currency_trade_id,
                ];
                $kline = Kline::where($where)->order('add_time', 'DESC')->find();
                if ($kline) {
                    $trade_price = $kline['close_price']; //floattostr($kline['close_price'])
                }
            }
            $currency_trade_real_money = \app\common\model\Trade::getCurrencyTradeRealMoney($currency_trade_id,NEW_PRICE_UNIT);
            $r['code']=SUCCESS;
            $r['message']=lang("data_success");
            $r['result']= [
                'trade_price' => $trade_price,
                'trade_real_money' => keepPoint($trade_price*$currency_trade_real_money,2),
                'currency_trade_real_money' => $currency_trade_real_money
            ];
        }
        return ajaxReturn($r);
    }

    public function orders_trade_list() {
        $orders_id = intval(input('orders_id'));
        $page = intval(input('page'));
        $result = \app\common\model\Trade::orders_trade_list($this->member_id,$orders_id,$page);
        $this->output_new($result);
    }
}
