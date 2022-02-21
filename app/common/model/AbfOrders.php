<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class AbfOrders extends Base
{
    //交易检测
    static function trade_check($member_id,$currency_id,$currency_trade_id,$type,$price,$num) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if(!is_numeric($price) || !is_numeric($num) || $price<=0 || $num<=0) return $r;

        $abf_trade_currency = AbfTradeCurrency::getOne($currency_id,$currency_trade_id);
        if(!$abf_trade_currency) return $r;

        if( ($type=='sell' && $abf_trade_currency['is_sell']!=1) ||  ($type=='buy' && $abf_trade_currency['is_buy']!=1) ) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        //涨跌幅限制
        if($abf_trade_currency['price_percent']>0) {
            //获取昨日收盘价 没有则使用发行价
            $yestday_price = AbfTrade::getYestdayPriceByKline($currency_id,$currency_trade_id);
            if($yestday_price) {
                $limit_price = $yestday_price;
            } else {
                $limit_price = $abf_trade_currency['open_price'];
            }

            $min_limit_price = keepPoint($limit_price*(1-$abf_trade_currency['price_percent']/100),6);
            $max_limit_price = keepPoint($limit_price*(1+$abf_trade_currency['price_percent']/100),6);

            if( $price< $min_limit_price || $price>$max_limit_price) {
                $r['message'] = lang('price_between',['num1'=>$min_limit_price,'num2'=>$max_limit_price]);
                return $r;
            }
        }

        if($type=='buy') {
            if($abf_trade_currency['min_price']>0 && $price<$abf_trade_currency['min_price']) {
                $r['message'] = lang('purchase_limit_min_price',['num1'=>$abf_trade_currency['min_price'],'num2'=>$abf_trade_currency['max_price'] ]);
                return $r;
            }
            if($abf_trade_currency['max_price']>0 && $price>$abf_trade_currency['max_price']) {
                $r['message'] = lang('purchase_limit_max_price',['num1'=>$abf_trade_currency['min_price'],'num2'=>$abf_trade_currency['max_price'] ]);
                return $r;
            }

            if($abf_trade_currency['min_num']>0 && $num<$abf_trade_currency['min_num']){
                $r['message'] = lang('purchase_limit_min_num',['num1'=>$abf_trade_currency['min_num'],'num2'=>$abf_trade_currency['max_num'] ]);
                return $r;
            }
            if($abf_trade_currency['max_num']>0 && $num>$abf_trade_currency['max_num']){
                $r['message'] = lang('purchase_limit_max_num',['num1'=>$abf_trade_currency['min_num'],'num2'=>$abf_trade_currency['max_num'] ]);
                return $r;
            }
        } else {
            if($abf_trade_currency['sell_min_price']>0 && $price<$abf_trade_currency['sell_min_price']) {
                $r['message'] = lang('purchase_limit_min_price',['num1'=>$abf_trade_currency['sell_min_price'],'num2'=>$abf_trade_currency['sell_max_price'] ]);
                return $r;
            }
            if($abf_trade_currency['sell_max_price']>0 && $price>$abf_trade_currency['sell_max_price']) {
                $r['message'] = lang('purchase_limit_max_price',['num1'=>$abf_trade_currency['sell_min_price'],'num2'=>$abf_trade_currency['sell_max_price'] ]);
                return $r;
            }

            if($abf_trade_currency['sell_min_num']>0 && $num<$abf_trade_currency['sell_min_num']){
                $r['message'] = lang('purchase_limit_min_num',['num1'=>$abf_trade_currency['sell_min_num'],'num2'=>$abf_trade_currency['sell_max_num'] ]);
                return $r;
            }
            if($abf_trade_currency['sell_max_num']>0 && $num>$abf_trade_currency['sell_max_num']){
                $r['message'] = lang('purchase_limit_max_num',['num1'=>$abf_trade_currency['sell_min_num'],'num2'=>$abf_trade_currency['sell_max_num'] ]);
                return $r;
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '';
        $r['result'] = $abf_trade_currency;
        return $r;
    }

    //购买
    static function buy($member_id,$currency_id,$currency_trade_id,$price,$num) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        //固定价格
        $price = AbfTrade::getTodayPriceByKline($currency_id,$currency_trade_id);
        if(!$price) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        $trade_check = self::trade_check($member_id,$currency_id,$currency_trade_id,'buy',$price,$num);
        if($trade_check['code']!=SUCCESS) {
            $r['message'] = $trade_check['message'];
            return $r;
        }

        $abf_trade_currency = $trade_check['result'];

        $currency_trade_user = CurrencyUser::getCurrencyUser($member_id,$currency_trade_id);
        if(!$currency_trade_user) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        //购买扣除的是后面的币种
        $trade_num = $price * $num;
        if($abf_trade_currency['buy_fee']>0) $trade_num = $trade_num * ( 1 + $abf_trade_currency['buy_fee']/100);
        $trade_num = keepPoint($trade_num,6);
        if($currency_trade_user['num']<$trade_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        try {
            self::startTrans();

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
                'price' => $price,
                'num' => $num,
                'avail_num' => $num,
                'fee' => $abf_trade_currency['buy_fee'],
                'type' => 'buy',
                'add_time' => time(),
                'trade_time' => time(),
                'status' => 0,
            ]);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            if($trade_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_trade_user['member_id'],$currency_trade_user['currency_id'],6200,'abf_orders_buy','out',$trade_num,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_trade_user['cu_id'],'num'=>$currency_trade_user['num']])->setDec('num',$trade_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //出售
    static function sell($member_id,$currency_id,$currency_trade_id,$price,$num) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        //固定价格
        $price = AbfTrade::getTodayPriceByKline($currency_id,$currency_trade_id);
        if(!$price) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        $trade_check = self::trade_check($member_id,$currency_id,$currency_trade_id,'sell',$price,$num);
        if($trade_check['code']!=SUCCESS) {
            $r['message'] = $trade_check['message'];
            return $r;
        }

        $abf_trade_currency = $trade_check['result'];

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(!$currency_user) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        //出售扣除的是前面的币种
        $trade_num = $num;
        $trade_num = keepPoint($trade_num,6);
        if($currency_user['num']<$trade_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        try {
            self::startTrans();

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
                'price' => $price,
                'num' => $num,
                'avail_num' => $num,
                'fee' => $abf_trade_currency['sell_fee'],
                'type' => 'sell',
                'add_time' => time(),
                'trade_time' => time(),
                'status' => 0,
            ]);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            if($trade_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6200,'abf_orders_sell','out',$trade_num,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$trade_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //撤销挂单
    static function cancel($member_id,$orders_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_operation_failure'),
            'result' => null
        ];

        $abf_orders = self::where(['orders_id' => $orders_id, 'member_id' => $member_id])->find();
        if(empty($abf_orders)) return $r;

        if($abf_orders['status']!=0) return $r;

        if($abf_orders['type']=='buy') {
            $currency_user = CurrencyUser::getCurrencyUser($abf_orders['member_id'],$abf_orders['currency_trade_id']);
            $all_trade_num = keepPoint($abf_orders['price'] * $abf_orders['avail_num'],6);
            $cancel_num = keepPoint($all_trade_num *  ( 1 + $abf_orders['fee']/100),6);
        } else {
            $currency_user = CurrencyUser::getCurrencyUser($abf_orders['member_id'],$abf_orders['currency_id']);
            $cancel_num = $abf_orders['avail_num'];
        }
        if(!$currency_user) return $r;


        try {
            self::startTrans();

            //撤销订单
            $flag = AbfOrders::where(['orders_id'=>$abf_orders['orders_id'],'avail_num'=>$abf_orders['avail_num'],'status'=>0])->update([
                'status' => 2,
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($cancel_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6203,'abf_orders_cancel','in',$cancel_num,$abf_orders['orders_id'],0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$cancel_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    static function getOneOnlineByPrice($currency_id,$currency_trade_id,$type,$price) {
        return self::where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => $type,
            'price' => $price,
            'status' => 0,
            'avail_num' => ['gt',0],
        ])->order('orders_id asc')->find();
    }

    //买卖盘
    static function buy_sell_list($member_id,$currency_id,$currency_trade_id,$rows) {
        $cache_key = 'AbfOrders_buy_sell_list'.$currency_id.'_'.$currency_trade_id;
        $data = cache($cache_key);

        if(empty($data)) {
            $buy_list = self::buy_list(0,$currency_id,$currency_trade_id,true,1,$rows);
            $sell_list = self::sell_list(0,$currency_id,$currency_trade_id,true,1,$rows);
            $currency_info = AbfTradeCurrency::getAbfCurrencyApi($currency_id,$currency_trade_id);

            $data = [
                'buy_list' => $buy_list['code']==SUCCESS ? $buy_list['result'] : [],
                'sell_list' => $sell_list['code']==SUCCESS ? $sell_list['result'] : [],
                'currency_info' => $currency_info,
            ];
            cache($cache_key,$data,1);
        }

        if($member_id>0) {
            $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
            $currency_trade_user = CurrencyUser::getCurrencyUser($member_id,$currency_trade_id);
            $currency_num = [
                'currency_id' => $currency_user ? $currency_user['num'] : 0,
                'currency_trade_id' => $currency_trade_user ? $currency_trade_user['num'] : 0,
            ];
        } else {
            $currency_num = [
                'currency_id' => 0,
                'currency_trade_id' => 0,
            ];
        }
        $data['currency_num'] = $currency_num;


        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $data;
        return $r;
    }

    //购买列表
    static function buy_list($member_id,$currency_id,$currency_trade_id,$isOnline=true,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        if($page<1) $page = 1;

        $where = [
            'a.currency_id' => $currency_id,
            'a.currency_trade_id' => $currency_trade_id,
            'a.type' => 'buy',
        ];
        if($member_id) $where['a.member_id'] = $member_id;
        if($isOnline) $where['a.status'] = 0;

        $field = "a.orders_id,a.price,a.num,a.avail_num,a.add_time,a.status,b.currency_name,c.currency_name as currency_trade_name";
        $list = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->page($page, $rows)->order("a.orders_id asc")->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date('m-d H:i', $value['add_time']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = $list;
        } else {
            $r['message'] = lang("lan_No_data");
        }

        return $r;
    }

    //购买列表
    static function sell_list($member_id,$currency_id,$currency_trade_id,$isOnline=true,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if($page<1) $page = 1;

        $where = [
            'a.currency_id' => $currency_id,
            'a.currency_trade_id' => $currency_trade_id,
            'a.type' => 'sell',
        ];
        if($member_id) $where['a.member_id'] = $member_id;
        if($isOnline) $where['a.status'] = 0;

        $field = "a.orders_id,a.price,a.num,a.avail_num,a.add_time,a.status,b.currency_name,c.currency_name as currency_trade_name";
        $list = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->page($page, $rows)->order("a.orders_id asc")->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date('m-d H:i', $value['add_time']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = $list;
        } else {
            $r['message'] = lang("lan_No_data");
        }

        return $r;
    }

    //购买列表
    static function my_orders($member_id,$currency_id,$currency_trade_id,$isOnline=true,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        if($page<1) $page = 1;

        if($currency_id) $where['a.currency_id'] = $currency_id;
        if($currency_trade_id) $where['a.currency_trade_id'] = $currency_trade_id;

        $where['a.member_id'] = $member_id;
        if($isOnline) {
            $where['a.status'] = 0;
            $order = "a.orders_id asc";
        } else {
            $where['a.status'] = ['gt',0];
            $order = "a.orders_id desc";
        }

        $field = "a.orders_id,a.price,a.num,a.avail_num,a.type,a.add_time,a.status,a.trade_time,a.fee,b.currency_name,c.currency_name as currency_trade_name";
        $list = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->page($page, $rows)->order($order)->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date('m-d H:i', $value['add_time']);
                $value['trade_time'] = date('m-d H:i', $value['trade_time']);
                $value['trade_num'] = keepPoint($value['num']-$value['avail_num'],6);
                $value['fee_num'] = keepPoint($value['price']*$value['trade_num']*$value['fee']/100,6);
                $value['total_price'] = keepPoint($value['price']*$value['trade_num'],6); //已成交总额
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = $list;
        } else {
            $r['message'] = lang("lan_No_data");
        }

        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function currencytrade() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_trade_id', 'currency_id')->field('currency_id,currency_name');
    }
}
