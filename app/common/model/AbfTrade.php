<?php
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class AbfTrade extends Base
{
    //撮合订单
    static function trade($abf_orders) {
        if($abf_orders['type']=='buy') {
            $abf_sell_orders = AbfOrders::getOneOnlineByPrice($abf_orders['currency_id'],$abf_orders['currency_trade_id'],'sell',$abf_orders['price']);
            if(!$abf_sell_orders) return false;

            $abf_buy_orders = $abf_orders;
        } else {
            $abf_buy_orders = AbfOrders::getOneOnlineByPrice($abf_orders['currency_id'],$abf_orders['currency_trade_id'],'buy',$abf_orders['price']);
            if(!$abf_buy_orders) return false;

            $abf_sell_orders = $abf_orders;
        }

        $abf_buy_currency_user = CurrencyUser::getCurrencyUser($abf_buy_orders['member_id'],$abf_buy_orders['currency_id']);
        if(!$abf_buy_currency_user) {
            return false;
        }

        $abf_sell_currency_trade_user = CurrencyUser::getCurrencyUser($abf_sell_orders['member_id'],$abf_sell_orders['currency_trade_id']);
        if(!$abf_sell_currency_trade_user) {
            return false;
        }

        //获得交易数量
        $trade_num = min($abf_buy_orders['avail_num'],$abf_sell_orders['avail_num']);
        if($trade_num<=0) return false;

        Log::write("交易".$abf_buy_orders['orders_id'].":".$abf_buy_orders['avail_num']."  ".$abf_sell_orders['orders_id'].":".$abf_sell_orders['avail_num']." 交易数量".$trade_num);
        echo ("交易".$abf_buy_orders['orders_id'].":".$abf_buy_orders['avail_num']."  ".$abf_sell_orders['orders_id'].":".$abf_sell_orders['avail_num']." 交易数量".$trade_num);
        echo "\r\n";

        $trade_time = time();
        try {
            self::startTrans();

            //添加买单交易记录
            $buy_trade_id = self::addTrade($abf_buy_orders,$abf_sell_orders,$trade_num,$trade_time);
            if(!$buy_trade_id) throw new Exception("添加买单失败".$abf_buy_orders['orders_id']);

            //买单扣除剩余量
            $buy_update = [
                'avail_num' => ['dec',$trade_num],
                'trade_time' => $trade_time,
            ];
            if($trade_num==$abf_buy_orders['avail_num']) $buy_update['status'] = 1;
            if($trade_num>$abf_buy_orders['avail_num']) throw new Exception("买单数量错误".$abf_buy_orders['orders_id']);

            $flag = AbfOrders::where(['orders_id'=>$abf_buy_orders['orders_id'],'avail_num'=>$abf_buy_orders['avail_num'],'status'=>0])->update($buy_update);
            if(!$flag) throw new Exception("更新买挂单失败".$abf_buy_orders['orders_id']);

            //买单添加账本 增加资产 $trade_num currency
            $flag = AccountBook::add_accountbook($abf_buy_currency_user['member_id'],$abf_buy_currency_user['currency_id'],6201,'abf_trade_buy','in',$trade_num,$buy_trade_id,0);
            if(!$flag) throw new Exception("添加买账本失败".$abf_buy_orders['orders_id']);

            $flag = CurrencyUser::where(['cu_id'=>$abf_buy_currency_user['cu_id'],'num'=>$abf_buy_currency_user['num']])->setInc('num',$trade_num);
            if(!$flag) throw new Exception("更新买资产失败".$abf_buy_orders['orders_id']);


            //添加卖单交易记录
            $sell_trade_id = self::addTrade($abf_sell_orders,$abf_buy_orders,$trade_num,$trade_time);
            if(!$sell_trade_id) throw new Exception("添加卖单失败".$abf_sell_orders['orders_id']);

            //卖单扣除剩余量
            $sell_update = [
                'avail_num' => ['dec',$trade_num],
                'trade_time' => $trade_time,
            ];
            if($trade_num==$abf_sell_orders['avail_num']) $sell_update['status'] = 1;
            if($trade_num>$abf_sell_orders['avail_num']) throw new Exception("卖单数量错误".$abf_sell_orders['orders_id']);

            $flag = AbfOrders::where(['orders_id'=>$abf_sell_orders['orders_id'],'avail_num'=>$abf_sell_orders['avail_num'],'status'=>0])->update($sell_update);
            if(!$flag) throw new Exception("更新卖挂单失败".$abf_sell_orders['orders_id']);

            //卖单添加账本 增加资产 $price * $trade_num * (1-fee) currency_trade
            $sell_all_trade_num = keepPoint($abf_sell_orders['price'] * $trade_num,6);
            $sell_trade_num = keepPoint($sell_all_trade_num * (1 - $abf_sell_orders['fee']/100),6);
            if($sell_trade_num>=0.000001) {
                $flag = AccountBook::add_accountbook($abf_sell_currency_trade_user['member_id'],$abf_sell_currency_trade_user['currency_id'],6201,'abf_trade_sell','in',$sell_trade_num,$sell_trade_id,0);
                if(!$flag) throw new Exception("添加卖单账本失败".$abf_sell_orders['orders_id']);

                $flag = CurrencyUser::where(['cu_id'=>$abf_sell_currency_trade_user['cu_id'],'num'=>$abf_sell_currency_trade_user['num']])->setInc('num',$sell_trade_num);
                if(!$flag) throw new Exception("添加卖单资产失败".$abf_sell_orders['orders_id']);
            }

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("AbfTrade::trade".$e->getMessage());
        }
        return 2;
    }

    //添加交易记录
    static function addTrade($orders,$other_orders,$trade_num,$trade_time) {
        try {
            return self::insertGetId([
                'trade_no' => time().rand(1000,9999),
                'orders_id' => $orders['orders_id'],
                'member_id' => $orders['member_id'],
                'currency_id' => $orders['currency_id'],
                'currency_trade_id' => $orders['currency_trade_id'],
                'price' => $orders['price'],
                'num' => $trade_num,
                'fee' => $orders['fee'],
                'type' => $orders['type'],
                'add_time' => $trade_time,
                'other_member_id' => $other_orders['member_id'],
                'other_orders_id' => $other_orders['orders_id'],
                'status' => 1,
            ]);
        } catch (Exception $exception) {
            return false;
        }
    }

    //获取昨日收盘价
    static function getYestdayPriceByKline($currency_id,$currency_trade_id) {
        $abf_kline = AbfKline::getYestdayKline($currency_id,$currency_trade_id);
        if($abf_kline) return $abf_kline['close_price'];

        return 0;
    }

    static function getTodayPriceByKline($currency_id,$currency_trade_id) {
        $abf_kline = AbfKline::getTodayKline($currency_id,$currency_trade_id);
        if($abf_kline) return $abf_kline['close_price'];

        return 0;
    }

    //获取初始值
    static function getFirstPriceByKline($currency_id,$currency_trade_id) {
        $abf_kline = AbfKline::getFirstKline($currency_id,$currency_trade_id);
        if($abf_kline) return $abf_kline['close_price'];

        return 0;
    }

    static function getPriceRealMoney($trade_price,$currency_trade_id,$unit='USD') {
        $currency_trade_real_money = self::getCurrencyTradeRealMoney($currency_trade_id,$unit);
        return keepPoint($trade_price*$currency_trade_real_money,2);
    }

    //获取（币币对后币种）币种真实价值 CNY 或 USD
    static function getCurrencyTradeRealMoney($currency_trade_id,$unit) {
        $real_money = CurrencyPriceTemp::get_price_currency_id($currency_trade_id,$unit);
        if($real_money==0) {
            $cur_currency_id = 0;
            $cur_price = 0;
            foreach (AbfTradeCurrency::MOST_CURRENCY as $most_currency_id) {
                $most_price = self::getTodayPriceByKline($currency_trade_id,$most_currency_id);
                if($most_price) {
                    $cur_currency_id = $most_currency_id;
                    $cur_price = $most_price;
                    break;
                }
            }
            if(!$cur_price) return 0;

            $usdt_money = CurrencyPriceTemp::get_price_currency_id($cur_currency_id,$unit);
            return keepPoint($cur_price * $usdt_money,6);
        }
        return $real_money;
    }

    //订单购买列表
    static function orders_trade_list($member_id,$orders_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        if($page<1) $page = 1;

        $abf_orders = self::where(['orders_id' => $orders_id, 'member_id' => $member_id])->find();
        if(empty($abf_orders)) return $r;

        $where = [
            'a.orders_id' => $orders_id,
        ];

        $field = "a.trade_id,a.price,a.num,a.fee,a.type,a.add_time,b.currency_name,c.currency_name as currency_trade_name";
        $list = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.currency_trade_id=c.currency_id", "LEFT")
            ->page($page, $rows)->order('a.trade_id desc')->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date('m-d H:i', $value['add_time']);
                $value['fee_num'] = keepPoint($value['price']*$value['num']*$value['fee']/100,6);
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
    public function otherusers() {
        return $this->belongsTo('app\\common\\model\\Member', 'other_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function currencytrade() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_trade_id', 'currency_id')->field('currency_id,currency_name');
    }
}
