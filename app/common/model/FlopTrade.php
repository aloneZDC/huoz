<?php
//翻牌币种
namespace app\common\model;
use think\Exception;
use think\Model;

class FlopTrade extends Model
{
    //出售给买单
    static function sell_to_orders($member_id,$orders_id,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang('flop_orders_not_exists');
        $r['result'] = null;

        if($num<=0){
            $r['message'] = lang('parameter_error');
            return $r;
        }

        $time_check = FlopTimeSetting::check();
        if(empty($time_check)) {
            $r['message'] = lang('flop_trade_wait');
            return $r;
        }

        $ordersInfo = FlopOrders::where(['orders_id'=>$orders_id])->find();
        if(!$ordersInfo || $ordersInfo['avail_num']<=0 || $ordersInfo['status']!=0) {
            $r['message'] = lang('flop_orders_not_exists');
            return $r;
        }

        $hongbao_config = HongbaoConfig::get_key_value();

        $time = time();
        $open_time = strtotime($hongbao_config['flop_auto_open']);
        if($time<$open_time) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $max_orders_id = $time_check['flop_queue_num'];
        if($max_orders_id>0) $max_orders_id = FlopOrders::getMaxLimit($ordersInfo['currency_id'],$max_orders_id);
        if($max_orders_id>0 && $orders_id>$max_orders_id) {
            $r['message'] = lang('flop_orders_queue');
            return $r;
        }


        //不可以买卖自己的广告
        if($ordersInfo['member_id']==$member_id) {
            $r['message'] = lang('flop_order_self');
            return $r;
        }

        $flop_currency = FlopCurrency::getCurrencyOne($ordersInfo['currency_id']);
        if(!$flop_currency) return $r;

        if($flop_currency['sell_min_num']>0 && $num<$flop_currency['sell_min_num'] && $num!=$ordersInfo['avail_num']) {
            $r['message'] = lang('flop_sell_min_num',['num'=>$flop_currency['sell_min_num']]);
            return $r;
        }

        if($num>$ordersInfo['avail_num']) {
            $r['message'] = lang('flop_num_not_enough');
            return $r;
        }

        $sell_currency_user = CurrencyUser::getCurrencyUser($member_id,$ordersInfo['currency_id']);
        $sell_pay_currency_user = CurrencyUser::getCurrencyUser($member_id,$ordersInfo['pay_currency_id']);
        $buy_currency_user = CurrencyUser::getCurrencyUser($ordersInfo['member_id'],$ordersInfo['currency_id']);
        $buy_pay_currency_user = CurrencyUser::getCurrencyUser($ordersInfo['member_id'],$ordersInfo['pay_currency_id']);
        if(!$sell_currency_user || !$sell_pay_currency_user || !$buy_currency_user || !$buy_pay_currency_user) {
            $r['message'] = lang('lan_network_busy_try_again');
            return $r;
        }

        if($sell_currency_user['num']<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $time = time();$rand = rand(1000,9999);
        $money = keepPoint($ordersInfo['price'] * $num,6);
        $buy_fee = keepPoint($money*$ordersInfo['fee']/100,6); //买家手续费
        $buy_real = keepPoint($money+$buy_fee,6); //买家实际扣除USDT冻结
        $sell_fee = keepPoint($money*$flop_currency['sell_fee']/100,6); //卖家手续费
        $sell_real = keepPoint($money-$sell_fee,6); //卖家实际到账USDT可用
        try{
            self::startTrans();

            //减少广告可出售量
            if($num==$ordersInfo['avail_num']){
                $flag = FlopOrders::where(['orders_id'=>$ordersInfo['orders_id'],'avail_num'=>$ordersInfo['avail_num'],'status'=>0])->update([
                    'avail_num' => ['dec',$num],
                    'status' => 2,
                ]);
            } else {
                $flag = FlopOrders::where(['orders_id'=>$ordersInfo['orders_id'],'avail_num'=>$ordersInfo['avail_num'],'status'=>0])->setDec('avail_num',$num);
            }
            if(!$flag)  throw new Exception(lang('lan_network_busy_try_again'));

            //添加买家订单
            $buy_trade_id = self::insertGetId([
                'type' => 'buy',
                'trade_no' => "T{$time}".$rand,
                'member_id'=> $ordersInfo['member_id'],
                'currency_id' => $ordersInfo['currency_id'],
                'price' => $ordersInfo['price'],
                'num' => $num,
                'money' => $money,
                'fee' => $buy_fee,
                'add_time' => $time,
                'status' => 1,
                'only_number' => $time.rand(1000,9999),
                'sell_orders' => $ordersInfo['orders_id'],
                'other_member' => $member_id,
                'pay_currency_id' => $ordersInfo['pay_currency_id'],
                'pay_money' => $buy_real,
                'is_can_release' => $ordersInfo['is_can_release'],
            ]);
            if(!$buy_trade_id)  throw new Exception(lang('lan_network_busy_try_again'));

            //添加卖家订单
            $sell_trade_id = self::insertGetId([
                'type' => 'sell',
                'trade_no' => "T{$time}".$rand,
                'member_id'=> $member_id,
                'currency_id' => $ordersInfo['currency_id'],
                'price' => $ordersInfo['price'],
                'num' => $num,
                'money' => $money,
                'fee' => $sell_fee,
                'add_time' => $time,
                'status' => 1,
                'only_number' => $time.rand(1000,9999),
                'sell_orders' => $ordersInfo['orders_id'],
                'other_member' => $ordersInfo['member_id'],
                'pay_currency_id' => $ordersInfo['pay_currency_id'],
                'pay_money' => $sell_real,
            ]);
            if(!$sell_trade_id)  throw new Exception(lang('lan_network_busy_try_again'));

            //扣除卖家KOI
            $flag = AccountBook::add_accountbook($sell_currency_user['member_id'],$sell_currency_user['currency_id'],1001,'flop_sell','out',$num,$sell_trade_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$sell_currency_user['cu_id'],'num'=>$sell_currency_user['num']])->setDec('num',$num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //增加卖家USDT
            $flag = AccountBook::add_accountbook($sell_pay_currency_user['member_id'],$sell_pay_currency_user['currency_id'],1002,'flop_sell_get','in',$sell_real,$sell_trade_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$sell_pay_currency_user['cu_id'],'num'=>$sell_pay_currency_user['num']])->setInc('num',$sell_real);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //增加买家KOI
            $flag = AccountBook::add_accountbook($buy_currency_user['member_id'],$buy_currency_user['currency_id'],1003,'flop_buy_get','in',$num,$buy_trade_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$buy_currency_user['cu_id'],'num'=>$buy_currency_user['num']])->setInc('num',$num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //扣除买家USDT冻结
            $flag = CurrencyUser::where(['cu_id'=>$buy_pay_currency_user['cu_id'],'forzen_num'=>$buy_pay_currency_user['forzen_num']])->setDec('forzen_num',$buy_real);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //买家增加红包
            if($hongbao_config['flop_hongbao_max_percent']>0) {
                $flag = FlopHongbao::addHongbao($ordersInfo['member_id'],$buy_trade_id,$ordersInfo['currency_id'],$num,$hongbao_config['flop_hongbao_expire_time']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //上级没有赠送赠送金 且 交易已达标
            if(!HongbaoKeepLog::isPidRegAward($ordersInfo['member_id']) && HongbaoKeepLog::checkPidRegAward($ordersInfo['member_id'],$hongbao_config)){
                $flag = HongbaoKeepLog::pidRegAward($ordersInfo['member_id'],$hongbao_config);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = lang('operation_failed_try_again');
        }
        return $r;
    }

    //我的订单
    static function my_trades($member_id,$type,$page=1,$rows=10) {
        if(!in_array($type,['buy','sell'])) $type = '';

        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id'=> $member_id,
        ];
        if(!empty($type)) $where['a.type'] = $type;
        $list = self::alias('a')->field('a.trade_id,a.type,a.price,a.num,a.pay_money,a.is_release,a.release_num,a.add_time,a.super_num,b.currency_name,bb.currency_name as pay_currency_name,m.phone,m.email,m.head')
            ->join(config("database.prefix") . "member m", "a.other_member=m.member_id", "LEFT")
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency bb", "a.pay_currency_id=bb.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.trade_id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('m-d H:i',$item['add_time']);
            if($item['phone']) {
                $item['phone'] = substr($item['phone'],0,3).'****'.substr($item['phone'],9,2);
            } else {
                $item['phone'] = substr($item['email'],0,2).'****'.substr($item['email'],-7);
            }
            unset($item['email']);
            $item['head'] = $item['head'] ?: FlopOrders::DEFAULT_HEAD;
            if($item['is_release']!=1) $item['release_num'] = '--';
            if($item['super_num']>0) $item['num'] = keepPoint($item['num']*$item['super_num'],6);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    //广告订单列表
    static function orders_trade_list($member_id,$orders_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id'=> $member_id,
            'a.sell_orders' => $orders_id,
            'a.type' => 'buy',
        ];
        if(!empty($type)) $where['a.type'] = $type;
        $list = self::alias('a')->field('a.trade_id,a.type,a.price,a.num,a.pay_money,a.is_release,a.release_num,a.add_time,a.super_num,b.currency_name,bb.currency_name as pay_currency_name,m.phone,m.email')
            ->join(config("database.prefix") . "member m", "a.other_member=m.member_id", "LEFT")
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency bb", "a.pay_currency_id=bb.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.trade_id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('m-d H:i',$item['add_time']);
            if($item['phone']) {
                $item['phone'] = substr($item['phone'],0,3).'****'.substr($item['phone'],9,2);
            } else {
                $item['phone'] = substr($item['email'],0,2).'****'.substr($item['email'],-7);
            }
            unset($item['email']);
            if($item['is_release']!=1) $item['release_num'] = '--';
            if($item['super_num']>0) $item['num'] = keepPoint($item['num']*$item['super_num'],6);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
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

    public function hongbao() {
        return $this->belongsTo('app\\common\\model\\FlopHongbao', 'trade_id', 'flop_trade_id')->field('num');
    }
}
