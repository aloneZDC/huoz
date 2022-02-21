<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;
//OTC挂单
class OrdersOtc extends Base {
	//撤单
	public function cancel($member_id,$orders_id,$otc_cancel_limit) {
        $ordersInfo = Db::name('orders_otc')->where(['orders_id'=>$orders_id])->find();
        if(!$ordersInfo || $ordersInfo['member_id']!=$member_id)  return lang('lan_orders_not_exists');
        if($ordersInfo['status']==2) return lang("lan_otc_has_success");
        if($ordersInfo['status']==3) return lang("lan_otc_has_cancel");

        // $limit = time() - $otc_cancel_limit;
        // if($ordersInfo['add_time']>$limit) return lang('lan_order_otc_limit_time_delete');

        //有未完成订单
        $count = Db::name('trade_otc')->where(['sell_orders'=>$orders_id,'type'=>'buy','status'=>['lt',3]])->count();
        if($count>0) return lang('lan_order_otc_cannot_delete');

        Db::startTrans();
        try{
            //撤销广告直接扣除手续费
            if($ordersInfo['type']=='sell') {
                $avail = $ordersInfo['avail_num'];

                //5小时内手续费 0.1%
                $limit = time() - $otc_cancel_limit;
                $fee = 0;
                if($ordersInfo['add_time']>$limit) {
                    $cancel_fee =  Db::name('currency')->where(['currency_id'=>$ordersInfo['currency_id']])->value('currency_otc_cancel_fee');
                    if($cancel_fee && $cancel_fee>0) {
                        $cancel_fee = $cancel_fee/100;
                        if($cancel_fee>$ordersInfo['fee']) $cancel_fee = $ordersInfo['fee'];
                        $fee = keepPoint($avail * $cancel_fee,6);
                    }
                }

                if($fee>0) {
                    $flag = Db::name('currency_user')->where(['member_id'=>$ordersInfo['member_id'],'currency_id'=>$ordersInfo['currency_id']])->setDec('forzen_num',$fee);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                    $result = model('Finance')->addLog($ordersInfo['member_id'], 23, 'OTC撤銷廣告手续费', $fee, 2, $ordersInfo['currency_id'], $ordersInfo['orders_id']);
                    if(!$result) throw new Exception(lang('lan_network_busy_try_again'));
                }
                //返还手续费
                $fee_back = keepPoint($avail * $ordersInfo['fee']-$fee,6);
                //返还未成交数量
                $avail = keepPoint($avail+$fee_back,6);

                if($avail>0) {
                    //添加账本
                    $result = model('AccountBook')->addLog([
                        'member_id' => $ordersInfo['member_id'],
                        'currency_id' => $ordersInfo['currency_id'],
                        'type'=> 10,
                        'content' => 'lan_otc_cancel',
                        'number_type' => 1,
                        'number' => $avail,
                        'fee' => $fee_back,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $ordersInfo['orders_id'],
                    ]);
                    if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                    $flag = Db::name('currency_user')->where(['member_id'=>$ordersInfo['member_id'],'currency_id'=>$ordersInfo['currency_id']])->update([
                        'num' => ['inc',$avail],
                        'forzen_num'=> ['dec',$avail],
                    ]);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                }
            }

            $flag = Db::name('orders_otc')->where(['orders_id'=>$ordersInfo['orders_id']])->setField('status',3);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
	}

    //支持OTC的列表
    public function otc_list() {
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['is_otc' => 1])->order('sort_otc asc')->select();
        if($currency) {
            foreach ($currency as &$cur) {
                $cur['price'] = CurrencyPriceTemp::get_price_currency_id($cur['currency_id'],'USD');
            }
            return $currency;
        }

        return [];
    }

    public function buyCheckBefore($member_id,$currency_id,$buy_orders_otc=0) {
        //查询是否有存在的广告
        $orders = Db::name('orders_otc')->where(['member_id'=>$member_id,'currency_id'=>$currency_id,'status'=>['in','0,1'],'type'=>'buy'])->count();
        $currency_name= Db::name('currency')->where(['currency_id'=>$currency_id])->value('currency_name');
        if($orders > 0) return lang('lan_buy_order_message').$currency_name.lang('lan_buy_order_message1');

        //查詢當天是否達到撤銷次數
        $time = strtotime(date('Y-m-d'));
        $count = Db::name('orders_otc')->where(['member_id' => $member_id, 'status' => 3, 'add_time' => ['gt', $time]])->count();
        if($count>=$buy_orders_otc) return lang('lan_order_otc_buy_limit1');

        return ['flag'=>true];
    }

    public function sellCheck($type,$member_id,$currency_id,$price,$tradenum,&$min_money,&$max_money,$order_message,$sell_confirm_time=15){
        if(empty($order_message)) return lang('lan_trade_information_empty');

        if ($price<0) return lang('lan_price_must_gt_0');
        if ($tradenum<0) return lang('lan_number_must_gt_0');
        if ($tradenum*$price<100) return lang('lan_trade_entrust_lowest');
        if($tradenum<0.0001) return lang('lan_trade_entrust_lowest1');


        $currency = $this->otc_check($type,$member_id,$currency_id,true,$price,$tradenum);
        if(is_string($currency)) return $currency;

        $min_money = keepPoint($min_money,2);
        $max_money = keepPoint($max_money,2);

        if(empty($min_money) || $min_money<=0) $min_money = 100;
        $max = keepPoint($price * $tradenum,2);
        if($min_money>$max) return lang("lan_otc_max_money_gt_money");
        if($min_money<100) return lang("lan_min_money_entrust_lowest");
        if($max_money>$max) return lang("lan_otc_max_money_gt_money");
        if($max_money>0 && $min_money>$max_money) return lang("lan_otc_min_money_gt_max_money");
        if(empty($max_money) || $max_money<=0) $max_money = $max;

        //$min = 0.0001*$price; //最低0.0001
        if($min_money<0) $min_money = 0;
        if($min_money>$max_money) $min_money = $max_money;

        return $currency;
    }

    //挂卖单
    public function addSell($member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$sell_confirm_time=15) {
        //敏感账户不能挂卖单
	    $member = Db::name('member')->field('is_sensitive')->where(['member_id'=>$member_id])->find();
	    if(!$member || $member['is_sensitive']==1) return lang('operation_deny');

//        $agent = Db::name('otc_agent')->where(['member_id'=>$member_id])->find();
//        if(!$agent || $agent['status']!=1) return lang('lan_otc_auth_apply_first');

        $currency = $this->sellCheck('sell',$member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$sell_confirm_time);
        if(is_string($currency)) return $currency;

        //银商不收手续费
//        $currency['currency_otc_sell_fee'] = 0;

        // if(empty($min_money) || $min_money<=0) $min_money = 100;
        // $max = $price * $tradenum;
        // if(empty($max_money)) $max_money = $max;

        //获取用户选择的支付方式
        $money_type = model('Bank')->getMemberChoose($member_id);
        if(is_string($money_type)) return $money_type;

        Db::startTrans();
        try{
            $user_num = model('CurrencyUser')->getNum($member_id, $currency_id, 'num', true);

            $fee = keepPoint($tradenum*($currency['currency_otc_sell_fee']/100),6);
            $all_num=  $tradenum + $fee;
            if($user_num<$all_num) throw new Exception(lang('lan_insufficient_balance'));

            $time = time();
            $data=[
                'member_id'=> $member_id,
                'currency_id'=> $currency_id,
                'price'=> $price,
                'num'=> $tradenum,
                'avail_num'=> $tradenum,
                'fee'=> $currency['currency_otc_sell_fee']/100,
                'type'=>'sell',
                'order_message'=> $order_message,
                'limit_time'=> $sell_confirm_time,
                'min_money' => $min_money,
                'max_money' => $max_money,
                'status' => 0,
                'add_time' => $time,
                'trade_time' => $time,
            ];
            $data = array_merge($data,$money_type);
            $orders_id = Db::name('orders_otc')->insertGetId($data);
            if(!$orders_id) throw new Exception(lang('lan_network_busy_try_again'));

            //添加账本信息
            $result = Model('AccountBook')->addLog([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'type'=> 16,
                'content' => 'lan_otc_sell_ad',
                'number_type' => 2,
                'number' => $all_num,
                'fee' => $fee,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $orders_id,
            ]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency_id])->update([
                'num' => ['dec',$all_num],
                'forzen_num'=> ['inc',$all_num],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['orders_id'=>$orders_id];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //挂买单
    public function addBuy($member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$buy_confirm_time=15) {
//        $agent = Db::name('otc_agent')->where(['member_id'=>$member_id])->find();
//        if(!$agent || $agent['status']!=1) return lang('lan_otc_auth_apply_first');

        $currency = $this->sellCheck('buy',$member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$buy_confirm_time);
        if(is_string($currency)) return $currency;

        //银商不收手续费
//        $currency['currency_otc_buy_fee'] = 0;

        // if(empty($min_money) || $min_money<=0) $min_money = 100;
        // $max = $price * $tradenum;
        // if(empty($max_money)) $max_money = $max;

        //获取用户选择的支付方式
        $money_type = model('Bank')->getMemberChoose($member_id);
        if(is_string($money_type)) return $money_type;

        $user_num = model('CurrencyUser')->getCurrencyUser($member_id, $currency_id, 'num');
        if(!$user_num) return lang('lan_network_busy_try_again');

        $time = time();
        $data = [
            'member_id'=> $member_id,
            'currency_id'=> $currency_id,
            'price'=>$price,
            'num'=>$tradenum,
            'avail_num' => $tradenum,
            'fee'=> $currency['currency_otc_buy_fee']/100,
            'type'=>'buy',
            'order_message'=>$order_message,
            'limit_time'=> $buy_confirm_time,
            'min_money' => $min_money,
            'max_money' => $max_money,
            'status' => 0,
            'add_time' => $time,
            'trade_time' => $time,
        ];
        $data = array_merge($data,$money_type);
        $orders_id = Db::name('orders_otc')->insertGetId($data);
        if(!$orders_id) return lang('lan_network_busy_try_again');

        return ['orders_id'=>$orders_id];
    }

    public function getList($where=[],$order='',$repeal_time,$page,$page_size,&$count=false,$is_max=false) {
	    if($page<=0) $page = 1;
	    if($page>100) $page = 100;
	    if($page_size>=10) $page_size = 10;

        if($count) {
            $count = Db::name('orders_otc')->alias('a')
                ->field('a.type,a.bank,a.alipay,a.wechat,a.orders_id,a.member_id,a.price,a.num,a.avail_num as avail,a.min_money,a.max_money,a.order_message,a.add_time,a.status,c.currency_name,c.currency_logo,b.email,b.ename as name,b.trade_allnum,b.fail_allnum')
                ->join('__MEMBER__ b','a.member_id=b.member_id','left')
                ->join('__CURRENCY__ c','a.currency_id=c.currency_id','left')
                ->where($where)->count();
        }

        $list = Db::name('orders_otc')->alias('a')
                ->field('a.type,a.bank,a.alipay,a.wechat,a.orders_id,a.member_id,a.price,a.num,a.avail_num as avail,a.min_money,a.max_money,a.order_message,a.add_time,a.status,a.limit_time,c.currency_name,c.currency_logo,b.email,b.ename as name,b.trade_allnum,b.fail_allnum')
                ->join('__MEMBER__ b','a.member_id=b.member_id','left')
                ->join('__CURRENCY__ c','a.currency_id=c.currency_id','left')
                ->where($where)->limit(($page - 1) * $page_size, $page_size)->order($order)->select();
        if(!$list) return [];


        foreach ($list as $k => $v) {
            $v['trade_num'] = keepPoint($v['num'] - $v['avail'],6);
            $v['repeal_time'] = $repeal_time;
            $v['evaluate_num'] = 0;
            if($v['trade_allnum']>0) $v['evaluate_num'] = intval(($v['trade_allnum'] - $v['fail_allnum']) / $v['trade_allnum'] * 100);

            $v['money'] = keepPoint($v['num'] * $v['price'],2); //总金额
            $v['trade_money'] = keepPoint($v['trade_num'] * $v['price'],2); //总金额
            $v['avail_money'] = keepPoint($v['avail'] * $v['price'],2); //剩余金额
            $v['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
            $v['name'] = substr_replace($v['name'],'****',1);

            $bank_list = [];
            foreach (['bank','wechat','alipay'] as $type) {
                if(!empty($v[$type])) $bank_list[] = $type;
            }
            $v['money_type'] = $bank_list;

            if($count) {
                $v['head'] = msubstr($v['name'],0,1);
                $max_money = keepPoint($v['price']*$v['avail'],2);
                $max_num = keepPoint($v['max_money'] / $v['price'],6);
                if($max_num<$v['avail'] && $max_money!=$v['max_money']) {
                    $v['max_num'] = $max_num;
                } else {
                    $v['max_num'] = $v['avail'];
                    if($is_max) $v['max_money'] = keepPoint($v['avail'] * $v['price'],2); //剩余金额
                }

                if($v['min_money']==0) {
                    $v['min_num'] = 0.0001;
                } else {
                    $min_num = keepPoint($v['min_money']/$v['price'],6);
                    if($v['avail']<$min_num) {
                        $v['min_num'] = $v['avail'];
                    } else {
                        $v['min_num'] = $min_num;
                    }
                }
                if($is_max && $v['min_money']>$v['max_money']) $v['min_money'] = $v['max_money'];
            }

            $list[$k] = $v;
        }

        return $list;
    }

    //卖家详情
    public function seller_info($member_id) {
        if(empty($member_id)) return lang('lan_login_Users_dont_exist');

        $member = Db::name('member')->field('email,trade_allnum,fail_allnum,fang_time,phone,ename as name,idcard,reg_time,appeal_allnum,appeal_succnum')->where(['member_id'=>$member_id])->find();
        if(!$member) return lang('lan_login_Users_dont_exist');

        $member['evaluate_num'] = 0;
        if($member['trade_allnum']>0) $member['evaluate_num'] = intval(($member['trade_allnum'] - $member['fail_allnum']) / $member['trade_allnum'] * 100);

        if(!empty($member['phone'])) {
            $member['phone'] = 1;
        } else {
            $member['phone'] = 0;
        }

        if(!empty($member['email'])) {
            $member['email'] = 1;
        } else {
            $member['email'] = 0;
        }

        if(!empty($member['idcard'])) {
            $member['idcard'] = 1;
        } else {
            $member['idcard'] = 0;
        }
        $member['name'] = substr_replace($member['name'],'****',1);
        if($member['fang_time']>0) {
            $minute = $member['fang_time']/60;
            $second = ($member['fang_time']%60)/60;
            $member['fang_time'] = keepPoint($minute+$second,2);
        }
        $member['fang_time'] = strval($member['fang_time']);
        $member['reg_time'] = date('Y-m-d',$member['reg_time']);

        $member['total_order'] = Db::name('trade_otc')->where(['member_id'=>$member_id,'type'=>'sell','status'=>3])->count();
        $stop = time() - 30*86400;
        $member['total_order_30'] = Db::name('trade_otc')->where(['member_id'=>$member_id,'type'=>'sell','status'=>3,'add_time'=>['gt',$stop]])->count();

        return $member;
    }

    public function orders_info($member_id,$orders_id,$lang='tc',$is_pc=false) {
        $ordersInfo = Db::name('orders_otc')->alias('a')
                        ->field('a.orders_id,a.member_id,a.currency_id,a.type,a.price,a.num,a.avail_num as avail,a.bank,a.alipay,a.wechat,a.add_time,a.fee,a.min_money,a.max_money,a.status,b.currency_name')
                        ->join('__CURRENCY__ b','a.currency_id=b.currency_id','LEFT')
                        ->where(['a.orders_id'=>$orders_id])->find();
        if(!$ordersInfo || $ordersInfo['member_id']!=$member_id) return lang('lan_orders_not_exists');

        $bank_list = [];
        foreach (['bank','wechat','alipay'] as $type) {
            if(!empty($ordersInfo[$type])) {
                if($is_pc) {
                    $bank_list[] = model('Bank')->getInfoByType($ordersInfo[$type],$type,$lang);
                } else {
                   $bank_list[] = $type;
                }
            }
        }
        $ordersInfo['money_type'] = $bank_list;
        $ordersInfo['add_time'] = date('Y-m-d H:i:s',$ordersInfo['add_time']);
        $ordersInfo['trade_num'] = keepPoint($ordersInfo['num'] - $ordersInfo['avail'],6); //已交易数量
        $ordersInfo['money'] = keepPoint($ordersInfo['num'] * $ordersInfo['price'],2); //总金额
        $ordersInfo['trade_money'] = keepPoint($ordersInfo['trade_num'] * $ordersInfo['price'],2); //总金额
        $ordersInfo['avail_money'] = keepPoint($ordersInfo['avail'] * $ordersInfo['price'],2); //剩余金额
        $ordersInfo['all_num'] = $ordersInfo['num'] + keepPoint($ordersInfo['num']*$ordersInfo['fee'],6);

        return $ordersInfo;
    }


    //OTC币种详情
    public function otc_info($currency_id,$otc_cancel_limit=0) {
        $currency = Db::name('currency')->field('currency_id,currency_name,currency_logo,is_make_price,make_min_price,make_max_price,is_limit,first_price,min_limit,max_limit,currency_otc_buy_fee,currency_otc_sell_fee,currency_otc_cancel_fee,make_max_num,make_min_num,sell_max_price,sell_min_price')->where(['currency_id'=>$currency_id,'is_otc'=>1])->find();
        if(!$currency) return lang('lan_otc_currency_cannot');

        $currency['newprice'] = $currency['newprice2'] = 0;
        if ($currency['is_make_price']) {
            $currency['newprice'] = $currency['make_min_price'];
            $currency['newprice2'] = $currency['make_max_price'];
        } else {
            /*
            if ($currency['is_limit']) {
                //返回昨天最后价格
                $before_trade = Db::name('Trade_otc')->field('price')->where("currency_id=31 and type='buy' and add_time<".strtotime(date("Y-m-d")))->order('add_time desc')->find();
                if($before_trade) {
                    $CurrencyMessageprice = $before_trade['price'];
                } else {
                    $CurrencyMessageprice = $currency['first_price'];
                }
                $currency['newprice'] = $CurrencyMessageprice - ($CurrencyMessageprice * $currency['min_limit']) / 100;
                $currency['newprice2'] = $CurrencyMessageprice + ($CurrencyMessageprice * $currency['max_limit']) / 100;
            }
            */
        }

        $currency['otc_cancel_limit'] = intval($otc_cancel_limit/3600);
        $currency['newprice'] = keepPoint($currency['newprice'],4);
        $currency['newprice2'] = keepPoint($currency['newprice2'],4);
        $currency['sell_max_price'] = keepPoint($currency['sell_max_price'],4);
        $currency['sell_min_price'] = keepPoint($currency['sell_min_price'],4);
        unset($currency['is_make_price'],$currency['is_limit'],$currency['min_limit'],$currency['max_limit'],$currency['make_min_price'],$currency['make_max_price'],$currency['first_price']);

        //发布广告的银商不收手续费
//        $currency['currency_otc_buy_fee'] = $currency['currency_otc_sell_fee'] = 0;
        return $currency;
    }

    //otc模块币种检测
    public function otc_check($type,$member_id,$currency_id,$make_time,$price=0,$tradenum=0) {
        $currency = model('Currency')->common_check($currency_id,'otc');
        if(is_string($currency)) return $currency;
        if(!$currency['is_otc']) return lang('lan_otc_currency_cannot');

        //控制当前交易中的订单量
        if($make_time && $currency['make_time']) {
            $count = Db::name('orders_otc')->where(array('member_id'=>$member_id,'status'=>array('lt',2),'currency_id'=>$currency_id))->count();
            if($count>$currency['make_time']) return lang('lan_order_otc_order_online').$currency['make_time'];
        }

        //挂单最小，最大数量
        if($tradenum>0){
            if($currency['make_min_num'] && $tradenum<$currency['make_min_num']) return lang('lan_order_otc_order_min_num').$currency['make_min_num'];
            if($currency['make_max_num'] && $tradenum>$currency['make_max_num']) return lang('lan_order_otc_order_max_num').$currency['make_max_num'];
        }

        //价格限制
        if($price>0) {
            //价格限制
            if($currency['is_make_price']){
                if($type=='buy') {
                    $newprice=$currency['make_max_price'];
                    if($newprice>0 && $price>$newprice) return lang('lan_trade_for_failure_one');

                    $newprice2=$currency['make_min_price'];
                    if($newprice2>0 && $price<$newprice2) return lang('lan_trade_for_failure_two');
                }else {
                    $newprice=$currency['sell_max_price'];
                    if($newprice>0 && $price>$newprice) return lang('lan_trade_for_failure_one');

                    $newprice2=$currency['sell_min_price'];
                    if($newprice2>0 && $price<$newprice2) return lang('lan_trade_for_failure_two');
                }
            }else {
                /*
                if($currency['is_limit']){
                    //昨天最后购买价格
                    $getlastmessage = Db::name('Trade_otc')->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$currency_id} and type='buy' ")->order('add_time desc')->limit(1)->find();
                    if(empty($getlastmessage)) {
                        $getlastmessage = $currency['first_price'];
                    } else {
                        $getlastmessage = $currency['price'];
                    }

                    if($getlastmessage){
                        $newprice=$getlastmessage+($getlastmessage*$currency['max_limit'])/100;
                        if($price>$newprice) self::output(10101, lang('lan_trade_for_failure_one'));

                        $newprice2=$getlastmessage-($getlastmessage*$currency['min_limit'])/100;
                        if($price<$newprice2) self::output(10101, lang('lan_trade_for_failure_two'));
                    }
                }
                */
            }
        }

        return $currency;
    }
}
