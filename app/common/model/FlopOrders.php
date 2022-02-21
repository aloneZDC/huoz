<?php
//翻牌币种
namespace app\common\model;
use think\Db;
use think\Exception;
use think\Model;

class FlopOrders extends Model
{
    const BUY_CURRENCY_MARK = 'XRP';
    const DEFAULT_HEAD = 'http://io-app.oss-cn-shanghai.aliyuncs.com/images/avatar.png';
    const KOIC_LOGO = 'http://io-app.oss-cn-shanghai.aliyuncs.com/currency/koic.png';
    //发布买单
    static function add_buy_orders($member_id,$currency_id,$price,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_close');
        $r['result'] = null;

        if(!is_numeric($price) || !is_numeric($num)) {
            $r['message'] = lang('parameter_error');
            return $r;
        }

        $flop_currency = FlopCurrency::getCurrencyOne($currency_id);
        if(empty($flop_currency)) return $r;

        $buy_currency = Currency::where(['currency_mark'=>self::BUY_CURRENCY_MARK])->field('currency_id,currency_name,currency_mark')->find();
        if(!$buy_currency) return $r;

        if ($price<0) {
            $r['message'] = lang('lan_price_must_gt_0');
            return $r;
        }
        if ($num<0) {
            $r['message'] = lang('lan_number_must_gt_0');
            return $r;
        }

        if($flop_currency['min_price']>0 && $price<$flop_currency['min_price']) {
            $r['message'] = lang('flop_min_price',['num'=>$flop_currency['min_price']]);
            return $r;
        }

        if($flop_currency['max_price']>0 && $price>$flop_currency['max_price']) {
            $r['message'] = lang('flop_max_price',['num'=>$flop_currency['max_price']]);
            return $r;
        }

        if($flop_currency['min_num']>0 && $num<$flop_currency['min_num']) {
            $r['message'] = lang('flop_min_num',['num'=>$flop_currency['min_num']]);
            return $r;
        }

        if($flop_currency['max_num']>0 && $num>$flop_currency['max_num']) {
            $r['message'] = lang('flop_max_num',['num'=>$flop_currency['max_num']]);
            return $r;
        }

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config)) return $r;

        $time = time();
        $open_time = strtotime($hongbao_config['flop_auto_open']);
        if($time<$open_time) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        //白名单不限制次数
        $is_white = FlopWhite::check($member_id);
        //每日广告限制 每人只能发布6次
        $day_total = self::where(['member_id'=>$member_id,'add_time'=>['gt',todayBeginTimestamp()]])->count();
        if(!$is_white && $flop_currency['day_total']>0) {
            if($day_total>=$flop_currency['day_total'])  {
                $r['message'] = lang('flop_day_limit',['num'=>$flop_currency['day_total']]);
                return $r;
            }
        }

        //总广告限制
        if(!$is_white && $flop_currency['orders_total']>0) {
            $flop_total = self::where(['member_id'=>$member_id,'status'=>0])->count();
            if($flop_total>=$flop_currency['orders_total']) {
                $r['message'] = lang('flop_total_limit',['num'=>$flop_currency['orders_total']]);
                return $r;
            }
        }


        $yestoday = date('Y-m-d',$time-86400);
        $child_num = FlopChildNum::getTodayTotalNum('flop',$member_id,$yestoday);
//        //每日广告发布限制
//        $day_total = self::where(['member_id'=>$member_id,'add_time'=>['gt',todayBeginTimestamp()]])->count();
//        $day_sum = FlopOrders::where(['member_id'=>$member_id,'add_time'=>['gt',todayBeginTimestamp()]])->sum('super_num');
//        if($day_sum) $day_total += $day_sum;
//        if(!$is_white && $flop_currency['day_total']>0) {
//            $flop_day_total = $flop_currency['day_total'] + $child_num;
//            if($day_total>=$flop_day_total) {
//                $r['message'] = lang('flop_day_limit',['num'=>$flop_day_total]);
//                return $r;
//            }
//
//            if($day_total >= $flop_currency['day_total']) {
//                $super_check = FlopOrders::super_check($member_id,$currency_id,$hongbao_config);
//                if($super_check) {
//                    $r['message'] = lang('please_user_super_flop');
//                    return $r;
//                }
//            }
//        }
//
//        //总广告限制
//        if(!$is_white && $flop_currency['orders_total']>0) {
//            $orders_total = $flop_currency['orders_total'] + $child_num;
//            $flop_total = self::where(['member_id'=>$member_id,'status'=>0])->count();
//            if($flop_total>=$orders_total) {
//                $r['message'] = lang('flop_total_limit',['num'=>$flop_currency['orders_total']]);
//                return $r;
//            }
//        }

        $total_money = $trade_money = keepPoint($num*$price,6);
        $fee = 0;
        if($flop_currency['buy_fee']>0) {
            $fee = keepPoint($trade_money*$flop_currency['buy_fee']/100,6);
            $total_money = keepPoint($trade_money + $fee,6);
        }

        $buy_currency_user = CurrencyUser::getCurrencyUser($member_id,$buy_currency['currency_id']);
        if(!$buy_currency_user || $buy_currency_user['num']<$total_money) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //检测是否需要支付DNC
        $checkFlopPay = FlopDncNum::checkFlopPay($member_id,$hongbao_config);

        try{
            self::startTrans();

            //扣除下级贡献数量
            if(!$is_white && $day_total>=$flop_currency['day_total']) {
                $flag = FlopChildNum::where(['type'=>'flop','user_id'=>$member_id,'today'=>$yestoday,'child_num'=>$child_num])->setDec('child_avail_num',1);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            $is_can_release = 1;
            if($checkFlopPay['new_start_pay']==1) {
                if($checkFlopPay['new_cost_currency_total']>=$checkFlopPay['new_cost_currency_num']) {
                    //添加今日扣除记录
                    $flag = FlopDncNum::addItem($member_id,'flop',date('Y-m-d'));
                    if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                    //添加DNC账本 扣除DNC资产
                    $flag = AccountBook::add_accountbook($checkFlopPay['new_cost_currency_user']['member_id'],$checkFlopPay['new_cost_currency_user']['currency_id'],1010,'flop_buy','out',$checkFlopPay['new_cost_currency_num'],0,0);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                    $flag = CurrencyUser::where(['cu_id'=>$checkFlopPay['new_cost_currency_user']['cu_id'],'num'=>$checkFlopPay['new_cost_currency_user']['num']])->setDec('num',$checkFlopPay['new_cost_currency_num']);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                } else {
                    $is_can_release = 0;
                }
            }

            //添加订单记录
            $orders_id = self::insertGetId([
                'type' => 'buy',
                'member_id' => $buy_currency_user['member_id'],
                'currency_id' => $currency_id,
                'pay_currency_id' => $buy_currency_user['currency_id'],
                'price' => $price,
                'num' => $num,
                'avail_num' => $num,
                'money' => $trade_money,
                'fee' => $flop_currency['buy_fee'],
                'fee_num' => $fee,
                'add_time' => $time,
                'status' => 0, //0是挂单  2成交 3撤销
                'is_can_release' => $is_can_release,
            ]);
            if(!$orders_id) throw new Exception(lang('lan_network_busy_try_again'));

            //添加账本 扣除资产 增加冻结资产
            $flag = AccountBook::add_accountbook($buy_currency_user['member_id'],$buy_currency_user['currency_id'],1000,'flop_add_orders','out',$total_money,$orders_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$buy_currency_user['cu_id'],'num'=>$buy_currency_user['num']])->update([
                'num' => ['dec',$total_money],
                'forzen_num'=> ['inc',$total_money],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = ['orders_id' => $orders_id];
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //更新可交易数量
    static function updateAvail($member_id,$orders_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('flop_orders_not_exists');
        $r['result'] = null;

        $ordersInfo = self::where(['orders_id'=>$orders_id])->find();
        if(!$ordersInfo || $ordersInfo['avail_num']<=0 || $ordersInfo['status']!=0) {
            $r['message'] = lang('flop_orders_not_exists');
            return $r;
        }
        //不可以买卖自己的广告
        if($ordersInfo['member_id']==$member_id) {
            $r['message'] = lang('flop_order_self');
            return $r;
        }

        $flop_currency = FlopCurrency::getCurrencyOne($ordersInfo['currency_id']);
        if(!$flop_currency) return $r;

        $buy_currency_user = CurrencyUser::getCurrencyUser($member_id,$ordersInfo['currency_id']);
        if(!$buy_currency_user) {
            $sell_num = 0;
        } else {
            $sell_num = $buy_currency_user['num'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'avail_num' => $ordersInfo['avail_num'], //订单剩余量
            'can_sell_num' => $sell_num, //用户持有量
        ];
        return $r;
    }

    //购买订单
    static function buy_orders_list($member_id,$currency_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('flop_not_data');
        $r['result'] = null;

        $where = [
            'a.type' => 'buy',
            'a.currency_id' => $currency_id,
            'a.status'=>0,
//            'a.member_id' => ['neq',$member_id],
            'a.avail_num'=>['gt',0],
        ];

        $time_check = FlopTimeSetting::check();
        $time_check_status = !empty($time_check) ? 1 : 2;
        $max_orders_id = !empty($time_check) ? $time_check['flop_queue_num'] : 3;
        if($max_orders_id>0) $max_orders_id = self::getMaxLimit($currency_id,$max_orders_id);

        $list = self::alias('a')->field('a.orders_id,a.price,a.avail_num,a.add_time,a.member_id,b.currency_name,bb.currency_name as pay_currency_name,m.phone,m.email,m.head')
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency bb", "a.pay_currency_id=bb.currency_id", "LEFT")
            ->where($where)
            ->limit(10)->order("a.orders_id asc")->select();
//            ->page($page, $rows)->order("a.orders_id asc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['is_wait'] = $time_check_status;
            $item['is_queue'] = ($max_orders_id>0 && $item['orders_id']>$max_orders_id) ? 1 : 2;
            $item['add_time'] = date('Y-m-d H:i:s',$item['add_time']);
            if($item['phone']) {
                $item['phone'] = substr($item['phone'],0,3).'****';
            } else {
                $item['phone'] = substr($item['email'],0,1).'****';
            }
            $item['is_my'] = $item['member_id']==$member_id ? 1 : 2;
            $item['head'] = $item['head'] ?: FlopOrders::DEFAULT_HEAD;
            unset($item['email']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getMaxLimit($currency_id,$limit=3) {
        $result = Db::query('select max(orders_id) as orders_id  from (SELECT orders_id FROM `yang_flop_orders` `a` WHERE `a`.`type`=\'buy\' AND `a`.`currency_id` = '.$currency_id.'  AND `a`.`status` = 0  AND `a`.`avail_num` > 0 ORDER BY `a`.`orders_id` ASC LIMIT '.$limit.') b');
        if(!empty($result) && is_array($result)) {
            if(isset($result[0]) && isset($result[0]['orders_id'])) return $result[0]['orders_id'];
        }
        return 0;
    }

    //广告详情
    static function orders_info($orders_id,$user_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_close');
        $r['result'] = null;

        $ordersInfo = FlopOrders::alias('a')->field('a.orders_id,a.currency_id,a.type,a.price,a.num,a.avail_num,a.money,a.fee_num,a.status,a.add_time,a.super_num,b.currency_name,bb.currency_name as pay_currency_name,m.phone,m.email,m.head')
            ->where(['a.orders_id'=>$orders_id])
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency bb", "a.pay_currency_id=bb.currency_id", "LEFT")
            ->find();
        if(!$ordersInfo) {
            $r['message'] = lang('flop_orders_not_exists');
            return $r;
        }
        if($ordersInfo['phone']) {
            $ordersInfo['phone'] = substr($ordersInfo['phone'],0,3).'****'.substr($ordersInfo['phone'],9,2);
        } else {
            $ordersInfo['phone'] = substr($ordersInfo['email'],0,3).'****'.substr($ordersInfo['email'],-7);
        }
        $ordersInfo['head'] = $ordersInfo['head'] ?: FlopOrders::DEFAULT_HEAD;
        unset($ordersInfo['email']);
        $ordersInfo['add_time'] = date('Y-m-d H:i:s',$ordersInfo['add_time']);
        $ordersInfo['sell_fee'] = 0;
        $ordersInfo['sell_num'] = keepPoint($ordersInfo['num']-$ordersInfo['avail_num'],6); //已出售数量
        $ordersInfo['total_money'] = keepPoint($ordersInfo['money']+$ordersInfo['fee_num'],6); //已出售数量

        $flop_currency = FlopCurrency::getCurrencyOne($ordersInfo['currency_id']);
        if($flop_currency)  $ordersInfo['sell_fee'] = $flop_currency['sell_fee'];
        $currency_user = CurrencyUser::getCurrencyUser($user_id,$ordersInfo['currency_id']);
        $ordersInfo['user_currency_num'] = $currency_user ? $currency_user['num'] : 0;
        if($ordersInfo['super_num']>0) $ordersInfo['num'] = keepPoint($ordersInfo['num'] * $ordersInfo['super_num'],6);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $ordersInfo;
        return $r;
    }

    //下架广告
    static function cancel_orders($member_id,$orders_id,$admin_id=0) {
        $r['code'] = ERROR1;
        $r['message'] = lang('flop_cannot_cancel');
        $r['result'] = null;

        $ordersInfo = FlopOrders::where(['orders_id'=>$orders_id,'member_id'=>$member_id])->find();
        if(!$ordersInfo || $ordersInfo['status']!=0) return $r;

        $flop_currency = FlopCurrency::getCurrencyOne($ordersInfo['currency_id']);
        if(!$flop_currency) return $r;

        $currency_user = CurrencyUser::getCurrencyUser($ordersInfo['member_id'],$ordersInfo['pay_currency_id']);
        if(!$currency_user) return $r;

        try{
            self::startTrans();

            //更改订单状态
            $flag = self::where(['orders_id'=>$orders_id,'status'=>0])->update(['status'=>3,'admin_id'=>$admin_id]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //返回未成交部分资产
            $back_num = keepPoint($ordersInfo['avail_num'] * $ordersInfo['price'],6);
            $back_fee = keepPoint($back_num * $ordersInfo['fee']/100,6);
            $forzen_total = $back_total = keepPoint($back_num+$back_fee,6);
            //撤销手续费
            if($flop_currency['cancel_fee']>0) $back_total = keepPoint($back_total * (100-$flop_currency['cancel_fee'])/100,6);

            if($back_total>=0.000001) {
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],1006,'flop_cancel','in',$back_total,$orders_id,0);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->update([
                    'num' => ['inc',$back_total],
                    'forzen_num'=> ['dec',$forzen_total],
                ]);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    static function my_orders($member_id,$status,$page=1,$rows=10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id' => $member_id,
        ];
        if ($status == 1) {
            $where['a.status'] = 0;//进行中
        } elseif ($status == 2) {
            $where['a.status'] = ['egt', 2];//已完成 包含完成 和 下架
        }
        $list = self::alias('a')->field('a.orders_id,a.price,a.avail_num,a.num,a.add_time,a.status,a.super_num,b.currency_name,bb.currency_name as pay_currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency bb", "a.pay_currency_id=bb.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.orders_id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('m-d H:i', $item['add_time']);
            if($item['super_num']>0) $item['num'] = keepPoint($item['num'] * $item['super_num'],6);
//            $item['num'] = $item['num'] * $item['super_num'];
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    //获取赠送的兑换数量 注意：只可赠送一次
    static function getExchangeAward($member_id,$config) {
        $award = 0;
        $count = self::where(['member_id'=>$member_id,'status'=>2])->count();
        if($count>=$config['node_level_exchange_flop_orders']) $award = $config['node_level_exchange_award'];
        return $award;
    }

    static function super_check($member_id,$currency_id,$config) {
//        if($config['flop_super_min_orders']>0) {
//            $count = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id,'status'=>2,'add_time'=>['egt',todayBeginTimestamp()]])->count();
//            if($count<$config['flop_super_min_orders']) return false;
//        }
//
//        if($config['flop_super_min_trade_num']>0) {
//            $sum = FlopTrade::where(['member_id'=>$member_id,'currency_id'=>$currency_id,'type'=>'buy','add_time'=>['egt',todayBeginTimestamp()]])->sum('num');
//            if($sum<$config['flop_super_min_trade_num']) return false;
//        }

        //交易成功大于6次方可开启超级红包 成功或撤销
        $count = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id,'status'=>['in',[2,3] ],'add_time'=>['egt',todayBeginTimestamp()]])->count();
        if($count<$config['flop_super_min_orders']) return false;

        return true;
    }

    //发布超级订单
    static function add_super_buy_orders($member_id,$currency_id,$price,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_close');
        $r['result'] = null;

        if(!is_numeric($price) || !is_numeric($num)) {
            $r['message'] = lang('parameter_error');
            return $r;
        }

        $flop_currency = FlopCurrency::getCurrencyOne($currency_id);
        if(empty($flop_currency)) return $r;

        $buy_currency = Currency::where(['currency_mark'=>self::BUY_CURRENCY_MARK])->field('currency_id,currency_name,currency_mark')->find();
        if(!$buy_currency) return $r;

        if ($price<0) {
            $r['message'] = lang('lan_price_must_gt_0');
            return $r;
        }
        if ($num<0) {
            $r['message'] = lang('lan_number_must_gt_0');
            return $r;
        }

        if($flop_currency['min_price']>0 && $price<$flop_currency['min_price']) {
            $r['message'] = lang('flop_min_price',['num'=>$flop_currency['min_price']]);
            return $r;
        }

        if($flop_currency['max_price']>0 && $price>$flop_currency['max_price']) {
            $r['message'] = lang('flop_max_price',['num'=>$flop_currency['max_price']]);
            return $r;
        }

        if($flop_currency['min_num']>0 && $num<$flop_currency['min_num']) {
            $r['message'] = lang('flop_min_num',['num'=>$flop_currency['min_num']]);
            return $r;
        }

        if($flop_currency['max_num']>0 && $num>$flop_currency['max_num']) {
            $r['message'] = lang('flop_max_num',['num'=>$flop_currency['max_num']]);
            return $r;
        }

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config)) return $r;

        if(!self::super_check($member_id,$currency_id,$hongbao_config)) {
            $r['message'] = lang('flop_super_limit');
            return $r;
        }

        $time = time();
        $open_time = strtotime($hongbao_config['flop_auto_open']);
        if($time<$open_time) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        $yestoday = date('Y-m-d',$time-86400);
        $child_num = FlopChildNum::getTodayNum('flop',$member_id,$yestoday);
        if($child_num<=0) {
            $r['message'] = lang('flop_super_day_limit');
            return $r;
        }

        $total_money = $trade_money = keepPoint($num*$price,6);
        $fee = 0;
        //超级发布不扣手续费
        $flop_currency['buy_fee'] = 0;
        if($flop_currency['buy_fee']>0) {
            $fee = keepPoint($trade_money*$flop_currency['buy_fee']/100,6);
            $total_money = keepPoint($trade_money + $fee,6);
        }

        $buy_currency_user = CurrencyUser::getCurrencyUser($member_id,$buy_currency['currency_id']);
        if(!$buy_currency_user || $buy_currency_user['num']<$total_money) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $flop_currency_user = CurrencyUser::getCurrencyUser($member_id,$flop_currency['currency_id']);
        if(!$flop_currency_user) {
            $r['message'] = lang('lan_network_busy_try_again');
            return $r;
        }

        $release_config = FlopTradeReleaseConfig::getUserConfig($flop_currency_user['keep_num']);
        $release_num = keepPoint($num * $child_num * ($release_config['super_percent']/100),6);
        $release_num = min($release_num,$flop_currency_user['keep_num']);//取最小值
        if($release_num<0.000001) $release_num = 0;


        $num_num = keepPoint($release_num * $release_config['num_percent']/100,6);
        $num_num = $num_num<0.000001 ? 0 : $num_num;
        //到云攒金数量
        $air_num = keepPoint($release_num-$num_num,6);
        $air_num = $air_num<0.000001 ? 0 : $air_num;

        try{
            self::startTrans();

            //扣除超级订单数量
            $flag = FlopChildNum::where(['type'=>'flop','user_id'=>$member_id,'today'=>$yestoday,'child_avail_num'=>$child_num])->setDec('child_avail_num',$child_num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //添加订单记录
            $orders_id = self::insertGetId([
                'type' => 'buy',
                'member_id' => $buy_currency_user['member_id'],
                'currency_id' => $currency_id,
                'pay_currency_id' => $buy_currency_user['currency_id'],
                'price' => $price,
                'num' => $num,
                'avail_num' => 0,
                'money' => $trade_money,
                'fee' => $flop_currency['buy_fee'],
                'fee_num' => $fee,
                'add_time' => $time,
                'status' => 2,
                'super_num' => $child_num,
            ]);
            if(!$orders_id) throw new Exception(lang('lan_network_busy_try_again'));

            //添加账本 扣除资产 增加冻结资产
            $flag = AccountBook::add_accountbook($buy_currency_user['member_id'],$buy_currency_user['currency_id'],1008,'flop_super_orders','out',$total_money,$orders_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$buy_currency_user['cu_id'],'num'=>$buy_currency_user['num']])->update([
                'num' => ['dec',$total_money],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));


            //添加购买订单 自己和自己成交
            $buy_trade_id = FlopTrade::insertGetId([
                'type' => 'buy',
                'trade_no' => "T{$time}".rand(1000,9999),
                'member_id'=> $buy_currency_user['member_id'],
                'currency_id' => $currency_id,
                'price' => $price,
                'num' => $num,
                'money' => $trade_money,
                'fee' => $fee,
                'add_time' => $time,
                'status' => 1,
                'only_number' => $time.rand(1000,9999),
                'sell_orders' => $orders_id,
                'other_member' => $buy_currency_user['member_id'],
                'pay_currency_id' => $buy_currency_user['currency_id'],
                'pay_money' => $total_money,
                'is_release' => 1,
                'release_num' => $release_num,
                'super_num' => $child_num,
            ]);
            if(!$buy_trade_id)  throw new Exception(lang('lan_network_busy_try_again'));

            //添加KOIC 减少记录
            if($release_num>0) {
                $flag = HongbaoKeepLog::add_log('release',$flop_currency_user['member_id'],$flop_currency_user['currency_id'],$release_num,$buy_trade_id,$num * $child_num,$release_config['super_percent']);
                if(!$flag) throw new Exception("添加KOIC释放记录失败");
            }


            //添加超级发布获得 账本
            $total_release_num = keepPoint($num + $num_num,6);
            $flag = AccountBook::add_accountbook($flop_currency_user['member_id'],$flop_currency_user['currency_id'],1009,'flop_super_orders_get','in',$total_release_num,$orders_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$flop_currency_user['cu_id'],'num'=>$flop_currency_user['num']])->update([
                'num' => ['inc',$total_release_num],
                'keep_num' => ['dec',$release_num],
                'air_num' => ['inc',$air_num],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            if($air_num>0) {
                $flag = HongbaoAirNumLog::add_log('flop',$flop_currency_user['member_id'],$flop_currency_user['currency_id'],$air_num,$buy_trade_id,$release_num,keepPoint(100-$release_config['num_percent'],2));
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = ['orders_id' => $orders_id];
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,phone,email');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function paycurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
