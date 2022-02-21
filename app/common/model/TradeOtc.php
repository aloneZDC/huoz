<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Db;
use think\Exception;

//OTC交易
class TradeOtc extends Base {
    /**
     *购买某卖单
	 *@param member_id 用户ID
	 *@param orders_id 广告ID
	 *@param num 购买数量
	 *@param otc_day_cancel 每天可撤销次数
     *@param otc_trade_online 交易中的订单数量限制
	 *@param buy_confirm_time 购买确认时间
	 *@param sell_confirm_time 出售确认时间
     */
    public function buy($member_id,$orders_id,$num,$otc_day_cancel,$otc_trade_online,$buy_confirm_time=0,$sell_confirm_time=0){
    	$time = strtotime(date('Y-m-d'));
        $count = Db::name('TradeOtc')->where(['member_id'=>$member_id,'status'=>4,'add_time'=>['gt',$time]])->count();
        if($otc_day_cancel>0 && $count>=$otc_day_cancel) return lang('lan_order_otc_buy_limit');

        $count = Db::name('TradeOtc')->where(['member_id'=>$member_id,'status'=>['lt',3]])->count();
        if($otc_trade_online>0 && $count>=$otc_trade_online) return lang('lan_order_otc_buy_limit2');

        $buynum=keepPoint($num,6);

        $checkorders= Db::name('OrdersOtc')->where(['orders_id'=>$orders_id])->find();
        if(empty($checkorders)) return lang('lan_orders_not_exists');
        if($checkorders['status']==3 || $checkorders['status']==2) return lang('lan_orders_not_exists');
        if ($checkorders['type']=='buy') return lang('lan_Illegal_operation');
        if($checkorders['member_id']==$member_id) return lang('lan_order_otc_order_self');

        $buyprice=$checkorders['price'];
        if ($buyprice*$buynum<100) return lang('lan_trade_entrust_lowest');
        if ($buynum<0)  return lang('lan_number_must_gt_0');
        if ($buynum<0.0001) return lang('lan_trade_entrust_lowest1');

        if($buynum>$checkorders['avail_num']) return lang('lan_trade_number_wrong');

        //如果有最低限额,全部购买可通过,否则不通过
        $buy_money = keepPoint($buyprice * $buynum,2);
        if($checkorders['min_money']>0){
            if($buynum!=$checkorders['avail_num'] && $buy_money < $checkorders['min_money']) return lang('lan_money_less_than').$checkorders['min_money'];
        }
        if($checkorders['max_money']>0 && $buy_money > $checkorders['max_money']) return lang('lan_money_more_than').$checkorders['max_money'];

        //货币设置常规检查
        $currency = model('Currency')->common_check($checkorders['currency_id'],'otc');
        if(is_string($currency)) return $currency;

        //扣除对应的手续费
        $fee = keepPoint($buynum*$currency['currency_otc_buy_fee']/100,6);

        Db::startTrans();
        try{
            $checkorders= Db::name('OrdersOtc')->lock(true)->where(['orders_id'=>$orders_id])->find();
            if($buynum>$checkorders['avail_num']) throw new Exception(lang('lan_network_busy_try_again'));

            $time = time();$rand = rand(1000,9999);
            $flag = Db::name('OrdersOtc')->where(['orders_id'=>$orders_id])->update([
                'avail_num' => ['dec',$buynum],
                'trade_time'=> $time,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //更新一下订单状态
            Db::name('OrdersOtc')->where("avail_num<num and status=0")->setField('status',1);

            $pay_number = rand(1000000,9999999);
            $money = keepPoint($buyprice * $buynum,2);
            //插入买家记录
            $data = array(
                'member_id' => $member_id,
                'currency_id' => $checkorders['currency_id'],
                'price' => $buyprice,
                'num' => $buynum,
                'fee' => $fee,
                'money' => $money,
                'type' => 'buy',
                'add_time' => $time,
                'trade_no' => "T{$time}".$rand,
                'other_member' => $checkorders['member_id'],
                'only_number' => rand(10000,99999).time(),
                'pay_number' => $pay_number,
                'limit_time' => ($time + $buy_confirm_time*60),
                'sell_orders' => $checkorders['orders_id'],
                'other_trade_id' => 0,
                'status' => 0,
            );
            $trade_id1 = Db::name('TradeOtc')->insertGetId($data);
            if(!$trade_id1) throw new Exception(lang('lan_network_busy_try_again'));

            //插入卖家记录
            $data = array(
                'member_id' => $checkorders['member_id'],
                'currency_id' => $checkorders['currency_id'],
                'price' => $buyprice,
                'num' => $buynum,
                'fee' => keepPoint($buynum*$checkorders['fee'],6),
                'money' => $money,
                'type' => 'sell',
                'add_time' => $time,
                'trade_no' => "T{$time}".$rand,
                'other_member' => $member_id,
                'only_number' => rand(10000,99999).time(),
                'pay_number' => $pay_number,
                'limit_time' => ($time + $sell_confirm_time*60),
                'sell_orders' => $checkorders['orders_id'],
                'other_trade_id' => $trade_id1,
                'status' => 0,
            );
            $trade_id2 = Db::name('TradeOtc')->insertGetId($data);
            if(!$trade_id2) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('TradeOtc')->where(['trade_id'=>$trade_id1])->setField('other_trade_id',$trade_id2);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();

            //添加系统消息提醒
            $flag = model('Jim')->sys_message('kd_'.$trade_id2.'_'.$trade_id1,'lan_otc_buyer_buy');

            return ['trade_id'=>$trade_id1];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //选择支付方式
    /**
     *@param trade_id 记录ID
     *@param member_id 用户ID
     *@param money_type wechat:id
     */
    public function choose_bank($member_id,$trade_id,$money_type) {
        if(empty($trade_id)) return lang('lan_Illegal_operation');
        if(empty($money_type)) return lang('lan_please_select_payment_method');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(!$tradeInfo || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']!=0) return lang('lan_Illegal_operation');

        $money_types = Db::name('orders_otc')->field('bank,alipay,wechat,type')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();
        if(!$money_types) return lang('lan_operation_failure');

        //买广告,不需要买家选择银行卡
        if($money_types['type']=='buy') {
            $tradeInfo['is_orders_buy'] = 1;
            return $tradeInfo;
        }

        $money_type = explode(":", $money_type);
        if(count($money_type)!=2 || !in_array($money_type[0], ['bank','wechat','alipay'])) return lang('lan_please_select_payment_method');

        $choose = "";
        foreach (['bank','wechat','alipay'] as $type) {
            if($tradeInfo['type']=='buy') {
                if($money_type[0]==$type && !empty($money_types[$type])) {
                    $choose = $type.':'.$money_types[$type];
                    break;
                }
            } else {
                if($money_type[0]==$type && !empty($money_types[$type])) {
                    //卖家根据买家的支持的方式提供支付信息
                    $bankInfo = Db::name('member_'.$type)->where(['id'=>intval($money_type[1]),'member_id'=>$tradeInfo['member_id']])->find();
                    if($bankInfo) $choose = $type.':'.$money_type[1];
                }
            }
        }
        if(empty($choose)) return lang('lan_please_select_payment_method');

        Db::startTrans();
        try{
            $flag = Db::name('trade_otc')->where(['other_trade_id'=>$trade_id])->setField('money_type',$choose);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->setField('money_type',$choose);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return $tradeInfo;
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //我已付款
    /**
     *@param trade_id 记录ID
     *@param member_id 用户ID
     */
    public function pay($member_id,$trade_id) {
        if(empty($trade_id)) return lang('lan_Illegal_operation');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(!$tradeInfo || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']!=0) return lang('lan_trade_otc_status'.$tradeInfo['status']).','.lang('lan_trade_otc_cannot_pay');
        if($tradeInfo['type']!='buy' || empty($tradeInfo['money_type']) ) return lang('lan_Illegal_operation');

        $time = time();
        Db::startTrans();
        try{
            $flag = Db::name('trade_otc')->where(['trade_id'=>$trade_id,'status'=>0])->update(['status'=>1,'update_time'=>$time]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id'],'status'=>0])->setField('status',1);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }

        $flag = model('CurrencyUser')->getCurrencyUser($tradeInfo['member_id'],$tradeInfo['currency_id'],'num'); //用户币种资产不存在时,创建

        $flag = model('Jim')->sys_message('kd_'.$tradeInfo['other_trade_id'].'_'.$tradeInfo['trade_id'],'lan_otc_buyer_pay');

        //发送短信通知 卖家
        $member = Db::name('member')->field('country_code,phone,email')->where(['member_id'=>$tradeInfo['other_member']])->find();
        if($member) {
            $time = date('Y-m-d H:i',$time);
            $only_number = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->value('only_number');

            if(!empty($member['country_code']) && !empty($member['phone'])) {
                model('sender')->addLog(1,$member['phone'],$member['country_code'],'otc_pay',['code'=>$only_number.'::'.$time]);
            }elseif(!empty($member['email'])) {
                model('sender')->addLog(2,$member['email'],0,'otc_pay',['code'=>$tradeInfo['sell_orders'].'::'.$only_number.'::'.$time]);
            }
        }

        return ['flag'=>true];
    }

    //管理员申诉取消
    public function admin_cancel() {

    }

    //买家取消
    /**
     *@param trade_id 记录ID
     *@param member_id 用户ID
     *@param otc_day_cancel 设置每天可取消订单数
     */
    public function user_cancel($member_id,$trade_id,$otc_day_cancel) {
        if(empty($trade_id)) return lang('lan_Illegal_operation');

        $time = strtotime(date('Y-m-d'));
        $count = Db::name('trade_otc')->where(['member_id'=>$member_id,'status'=>4,'add_time'=>['gt',$time]])->count();
        if($count>=$otc_day_cancel) return lang('lan_order_otc_cancel_limit');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(!$tradeInfo || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']==4) return ['flag'=>true];
        if($tradeInfo['status']!=0 || $tradeInfo['type']!='buy') return lang('lan_trade_otc_status'.$tradeInfo['status']).','.lang('lan_trade_otc_cannot_cancel');

        //卖家订单
        $other_tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
        if(!$other_tradeInfo) return lang('lan_Illegal_operation');

        $flag = $this->cancel($tradeInfo,$other_tradeInfo);

        return $flag;
    }

    //买家支付后取消
    /**
     *@param trade_id 记录ID
     *@param member_id 用户ID
     *@param otc_day_cancel 设置每天可取消订单数
     */
    public function user_pay_cancel($member_id,$trade_id,$otc_day_cancel) {
        if(empty($trade_id)) return lang('lan_Illegal_operation');

        $time = strtotime(date('Y-m-d'));
        $count = Db::name('trade_otc')->where(['member_id'=>$member_id,'status'=>4,'add_time'=>['gt',$time]])->count();
        if($count>=$otc_day_cancel) return lang('lan_order_otc_cancel_limit');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(!$tradeInfo || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']==4) return ['flag'=>true];
        if($tradeInfo['status']!=1 || $tradeInfo['type']!='buy') return lang('lan_trade_otc_status'.$tradeInfo['status']).','.lang('lan_trade_otc_cannot_cancel');

        //卖家订单
        $other_tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
        if(!$other_tradeInfo) return lang('lan_Illegal_operation');

        $flag = $this->cancel($tradeInfo,$other_tradeInfo,false,true);

        return $flag;
    }

    //取消订单
    /**
     *@param tradeInfo 买家记录
     *@param other_tradeInfo 卖家记录
     *@param is_allege 是否后台申诉取消
     *@param is_pay_cancel 是否是支付后撤销
     */
    public function cancel($tradeInfo,$other_tradeInfo,$is_allege=false,$is_pay_cancel=false) {
        Db::startTrans();
        try{
            $update = [
                'status' => 4,
                'update_time' => time(),
            ];

            $where = [
                'trade_id' => $tradeInfo['trade_id'],
                'status' => 0,
            ];
            if($is_pay_cancel) $where['status'] = 1;

            if($is_allege) {
                $where['status'] = 2;
                $update['allege_status'] = 0; //买家败诉
            }
            $flag = Db::name('trade_otc')->where($where)->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //更改卖家订单状态
            if($is_allege) $update['allege_status'] = 1; //卖家胜诉

            $where['trade_id'] = $other_tradeInfo['trade_id'];
            $flag = Db::name('trade_otc')->where($where)->setField($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //减去已交易量
            $flag = Db::name('orders_otc')->where(['orders_id'=>$tradeInfo['sell_orders']])->setInc('avail_num',$tradeInfo['num']);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $order_otc = Db::name('orders_otc')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();
            if($order_otc['type'] == 'buy'){
                //如果是买单 要返回给卖家资产
                $all_num = keepPoint($other_tradeInfo['num'] + $other_tradeInfo['fee'],6);
                $result = model('AccountBook')->addLog([
                    'member_id' => $other_tradeInfo['member_id'],
                    'currency_id' => $other_tradeInfo['currency_id'],
                    'type'=> 9,
                    'content' => 'lan_otc_sell_to_buy_cancel',
                    'number_type' => 1,
                    'number' => $all_num,
                    'fee' => $other_tradeInfo['fee'],
                    'to_member_id' => 0,
                    'to_currency_id' => 0,
                    'third_id' => $other_tradeInfo['trade_id'],
                ]);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = Db::name('currency_user')->where(['member_id'=>$other_tradeInfo['member_id'],'currency_id'=>$other_tradeInfo['currency_id']])->update([
                    'num' => ['inc',$all_num],
                    'forzen_num'=> ['dec',$all_num],
                ]);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            if($is_allege) {
                $flag = Db::name('member')->where(['member_id'=>$other_tradeInfo['member_id']])->setInc('appeal_succnum');
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = model('Jim')->sys_message('kd_'.$tradeInfo['other_trade_id'].'_'.$tradeInfo['trade_id'],'lan_otc_seller_win');
            } else {
                $flag = model('Jim')->sys_message('kd_'.$tradeInfo['other_trade_id'].'_'.$tradeInfo['trade_id'],'lan_otc_buyer_cancel');
            }

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //卖家主动放行
    public function seller_fangxing($member_id,$trade_id) {
        if(empty($trade_id)) return lang('lan_Illegal_operation');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(!$tradeInfo || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']!=1 || $tradeInfo['type']!='sell') return lang('lan_trade_otc_not_pay');

        //买家订单
        $other_tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
        if(!$other_tradeInfo) return lang('lan_Illegal_operation');

        $result = $this->fangxing($tradeInfo,$other_tradeInfo);
        return $result;
    }

    //管理员申诉放行
    public function admin_fangxing() {

    }

    /**
     *@param tradeInfo 卖家记录
     *@param other_tradeInfo 买家记录
     *@param is_allege 是否是后台申诉放行
     */
    private function fangxing($tradeInfo,$other_tradeInfo,$is_allege=false) {
        $time = time();
        Db::startTrans();
        try{
            if($tradeInfo['fee']>0) {
                $result = model('Finance')->addLog($tradeInfo['member_id'], 24, 'OTC交易手续费', $tradeInfo['fee'], 2, $tradeInfo['currency_id'], $tradeInfo['trade_id']);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));
            }

            //减少数量及手续费
            $num = $tradeInfo['num'] + $tradeInfo['fee'];
            $flag = Db::name('currency_user')->where(['member_id'=>$tradeInfo['member_id'],'currency_id'=>$tradeInfo['currency_id']])->setDec('forzen_num',$num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            if($other_tradeInfo['fee']>0) {
                $result = model('Finance')->addLog($other_tradeInfo['member_id'], 24, 'OTC交易手续费', $other_tradeInfo['fee'], 2, $other_tradeInfo['currency_id'], $other_tradeInfo['trade_id']);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));
            }

            //买家加币,后扣费 减去手续费
            $num = keepPoint($other_tradeInfo['num'] - $other_tradeInfo['fee'],6);
            //添加账本信息
            $result = model('AccountBook')->addLog([
                'member_id' => $other_tradeInfo['member_id'],
                'currency_id' => $other_tradeInfo['currency_id'],
                'type'=> 9,
                'content' => 'lan_otc_buy',
                'number_type' => 1,
                'number' => $num,
                'fee' => $other_tradeInfo['fee'],
                'to_member_id' => $tradeInfo['member_id'],
                'to_currency_id' => $other_tradeInfo['currency_id'],
                'third_id' => $other_tradeInfo['trade_id'],
            ]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('currency_user')->where(['member_id'=>$other_tradeInfo['member_id'],'currency_id'=>$other_tradeInfo['currency_id']])->setInc('num',$num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $update = [
                'status' => 3,
                'update_time' => $time,
            ];
            $where = [
                'trade_id' => $tradeInfo['trade_id'],
                'status' => 1,
            ];
            if($is_allege) {
                $update['allege_status'] = 0; //卖家败诉
                $where['status'] = 2;
            }
            $flag = Db::name('trade_otc')->where($where)->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            if($is_allege) $update['allege_status'] = 1; //买家胜诉

            $where['trade_id'] = $other_tradeInfo['trade_id'];
            $flag = Db::name('trade_otc')->where($where)->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = false;
            if($is_allege) {
                //后台申诉放行
                $flag = Db::name('member')->where(['member_id'=>$tradeInfo['member_id']])->save([
                    'trade_allnum' => ['inc',1],
                    'fail_allnum'=> ['inc',1],
                ]);

                $result = model('Jim')->sys_message('kd_'.$tradeInfo['trade_id'].'_'.$tradeInfo['other_trade_id'],'lan_otc_buyer_win');
            } else {
                //卖家主动放行 增加卖家交易总量及放行时间
                $time = $time - $other_tradeInfo['update_time'];
                $member_info = Db::name('member')->field("trade_allnum,fang_time")->where(['member_id'=>$tradeInfo['member_id']])->find();
                $member_info['trade_allnum']+=1;
                $member_info['fang_time']=($member_info['fang_time']+$time)/2;
                $flag=Db::name('member')->where(['member_id'=>$tradeInfo['member_id']])->update($member_info);
                $result = model('Jim')->sys_message('kd_'.$tradeInfo['trade_id'].'_'.$tradeInfo['other_trade_id'],'lan_otc_seller_fangxing');
            }
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //设置广告为已完成
            $ordersInfo = Db::name('orders_otc')->lock(true)->field('avail_num,status')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();
            if($ordersInfo && $ordersInfo['avail_num']==0 && $ordersInfo['status']<2) {
                //未完成订单数量
                $count = Db::name('trade_otc')->lock(true)->where(['sell_orders'=>$tradeInfo['sell_orders'],'type'=>'buy','status'=>['lt',3]])->count();
                if($count<=0) {
                    $flag =  Db::name('orders_otc')->where("orders_id=".$tradeInfo['sell_orders'].' and avail_num=0 and status<2')->setField('status',2);
                    if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
                }
            }

            //发送短信通知买家
            $member = Db::name('member')->field('country_code,phone,email')->where(['member_id'=>$other_tradeInfo['member_id']])->find();
            if($member) {
                $time = date('Y-m-d H:i',time());
                $only_number = Db::name('trade_otc')->where(['trade_id'=>$other_tradeInfo['trade_id']])->value('only_number');

                if(!empty($member['country_code']) && !empty($member['phone'])) {
                    model('sender')->addLog(1,$member['phone'],$member['country_code'],'otc_fangxing',['code'=>$only_number.'::'.$time]);
                }elseif(!empty($member['email'])) {
                    model('sender')->addLog(2,$member['email'],0,'otc_fangxing',['code'=>$other_tradeInfo['sell_orders'].'::'.$only_number.'::'.$time]);
                }
            }

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //撤销申诉 必须有申诉发起人撤销
    public function cancel_appeal($member_id,$trade_id) {
        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(empty($tradeInfo) || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');

        $other_tradeInfo =  $this->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
        if(empty($other_tradeInfo)) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']!=2 && $tradeInfo['allege_id']!=$member_id) return lang('lan_orders_illegal_request');

        $update = [
            'status' => 1,
        ];
        Db::startTrans();
        try{
            $flag = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //减少卖家申诉记录 添加系统消息
            if($tradeInfo['type']=='buy') {
                Db::name('member')->where(['member_id'=>$other_tradeInfo['member_id']])->setDec('appeal_allnum');
            } else {
                Db::name('member')->where(['member_id'=>$tradeInfo['member_id']])->setDec('appeal_allnum');
            }
            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }

    //申诉
	/**
	 *@param trade_id 记录ID
	 *@param member_id 用户ID
	 *@param allege_type 申诉类型
	 *@param content 申诉理由
	 *@param repeal_time 申诉时间限制(分)
     *@param trade_pic 申诉图片
	 */
    public function appeal($member_id,$trade_id,$allege_type,$content,$repeal_time=0,$trade_pic='') {
        if(empty($allege_type)) return lang('lan_shensu_type');
        if(empty($content)) return lang('lan_shensu_why');

        $tradeInfo = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->find();
        if(empty($tradeInfo) || $tradeInfo['member_id']!=$member_id) return lang('lan_orders_illegal_request');

    	$other_tradeInfo =  $this->where(['trade_id'=>$tradeInfo['other_trade_id']])->find();
        if(empty($other_tradeInfo)) return lang('lan_orders_illegal_request');
        if($tradeInfo['status']==2) return ['flag'=>true];
        if($tradeInfo['status']!=1) return lang('lan_trade_otc_not_pay');

        $limit = time() - $repeal_time* 60;
        if($tradeInfo['type']=='buy') {
        	if($tradeInfo['update_time']>$limit) return lang('lan_pay').$repeal_time.lang('lan_shensu_notice');
        } elseif($tradeInfo['type']=='sell') {
        	if($other_tradeInfo['update_time']>$limit) return lang('lan_pay').$repeal_time.lang('lan_shensu_notice');
        }

        $update = [
            'status' => 2,
//            'update_time' => time(),
            'allege_id' => $member_id,
            'allege_type' => $allege_type,
            'allege_content' => $content,
            'trade_status' => 1,
            'trade_pic' => $trade_pic,
        ];

        Db::startTrans();
        try{
            $flag = Db::name('trade_otc')->where(['trade_id'=>$trade_id])->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->update($update);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //增加卖家申诉记录 添加系统消息
            if($tradeInfo['type']=='buy') {
                Db::name('member')->where(['member_id'=>$other_tradeInfo['member_id']])->setInc('appeal_allnum');
                $result = model('Jim')->sys_message('kd_'.$tradeInfo['other_trade_id'].'_'.$tradeInfo['trade_id'],'lan_otc_buyer_appeal');
            } else {
                Db::name('member')->where(['member_id'=>$tradeInfo['member_id']])->setInc('appeal_allnum');
                $result = model('Jim')->sys_message('kd_'.$tradeInfo['trade_id'].'_'.$tradeInfo['other_trade_id'],'lan_otc_seller_appeal');
            }

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }

    public function order_trade_list($ordersInfo,$complete,$page,$page_size,&$count=false){
        $where['a.sell_orders'] = $ordersInfo['orders_id'];
        $where['a.type'] = $ordersInfo['type'];

        $complete = intval(input('complete'));
        if($complete){
            $where['a.status'] = ['egt',3];
        } else {
            $where['a.status'] = ['elt',2];
        }

        if($count) {
            $count = Db::name('trade_otc')->alias('a')
                ->field('a.trade_id,a.type,a.member_id,a.other_member,a.price,a.num,a.pay_number,a.add_time,a.status,b.email,b.nick as name')
                ->join('__MEMBER__ b','a.other_member=b.member_id','left')
                ->where($where)->count();
        }

        $list = Db::name('trade_otc')->alias('a')
                ->field('a.trade_id,a.only_number,a.type,a.member_id,a.other_member,a.price,a.num,a.pay_number,a.add_time,a.status,a.allege_id,a.allege_status,b.email,b.nick as name')
                ->join('__MEMBER__ b','a.other_member=b.member_id','left')
                ->where($where)->limit(($page - 1) * $page_size, $page_size)->order('trade_id desc')->select();
        if($list) {
            foreach ($list as $key => $value) {
                $value['add_time'] = date('Y/m/d',$value['add_time']);
                $value['status_txt'] = lang('lan_trade_otc_status'.$value['status']);
                if($count) $value = $this->getStatusTxt($value,$ordersInfo['member_id']);

                $value['currency_name'] = $ordersInfo['currency_name'];
                $value['money'] = keepPoint($value['price'] * $value['num'],2);
                $list[$key] = $value;
            }
        } else {
            $list = [];
        }

        return $list;
    }

    //交易列表
    public function trade_list($member_id,$status,$page,$page_size,$orders_id=0,&$count=false) {
        $where = [];
        $where['a.member_id'] = $member_id;
        // $where['a.type'] = 'buy';
        if(is_numeric($status)) $where['a.status'] = $status;
        if(!empty($orders_id)) $where['a.only_number'] = $orders_id;

        if($count) {
            $count = Db::name('trade_otc')->alias('a')
                ->join('__MEMBER__ b','a.other_member=b.member_id','left')
                ->join('__CURRENCY__ c','a.currency_id=c.currency_id','left')
                ->where($where)->count();
        }

        $list = Db::name('trade_otc')->alias('a')
                ->field('a.trade_id,a.type,a.add_time,a.status,a.money,a.price,a.num,a.allege_id,a.allege_status,a.only_number,a.fee,c.currency_name,c.currency_id,b.ename as username,b.phone,b.email')
                ->join('__MEMBER__ b','a.other_member=b.member_id','left')
                ->join('__CURRENCY__ c','a.currency_id=c.currency_id','left')
                ->where($where)->limit(($page - 1) * $page_size, $page_size)->order("trade_id desc")->select();
        if($list) {
            foreach ($list as $key => $value) {
                if(empty($value['username'])){
                    $value['username'] = ' ';
                } else {
                    $value['username'] = substr_replace($value['username'],'****',1);
                }
                $value['add_time'] = date('Y/m/d H:i',$value['add_time']);
                $value['status_txt'] = lang('lan_trade_otc_status'.$value['status']);

//                if (empty($value['phone'])) {
//                    $value['phone'] = substr($value['email'], 0, 3) . '****' . substr($value['email'], -5);
//                } else {
//                    $value['phone'] = substr($value['phone'], 0, 3) . '****' . substr($value['phone'], 9, 2);
//                }
//                unset($value['email']);

                //pc
                if($count) $value = $this->getStatusTxt($value,$member_id);

                $list[$key] = $value;
            }
        } else {
            $list = [];
        }

        return $list;
    }

    /**
     *
     */
    public function trade_info($member_id,$trade_id,$lang='tc',$repeal_time=15,$is_pc=false) {
        $tradeInfo = Db::name('trade_otc')->alias('a')
                    ->field('a.currency_id,a.type,a.member_id,b.ename as username,b.phone,b.email,a.money,a.price,a.num,a.fee,a.pay_number,a.only_number,a.add_time,a.money_type,a.sell_orders,a.limit_time,a.other_member,a.status,a.other_trade_id,a.update_time,a.trade_id,a.allege_id,a.allege_status,b.trade_allnum,c.currency_name,c.currency_logo')
                    ->join('__MEMBER__ b','a.other_member=b.member_id','LEFT')
                    ->join('__CURRENCY__ c','a.currency_id=c.currency_id','LEFT')
                    ->where(['trade_id'=>$trade_id])->find();
        if(empty($tradeInfo) || $tradeInfo['member_id']!=$member_id) return lang('lan_Illegal_operation');

        $tradeInfo['status_txt'] = lang('lan_trade_otc_status'.$tradeInfo['status']);
        if($is_pc){
            $ordersInfo = Db::name('Orders_otc')->field('bank,alipay,wechat,type')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();
            if($tradeInfo['status']==0) {
                $bank_list = [];
                if($ordersInfo['type']=='buy') {
                    //如果是买单,获取卖家的银行卡号码
                    $has_choose = $tradeInfo['money_type'];
                    $has_choose = explode(":", $has_choose);
                    if(count($has_choose)==2) {
                        $bank_list[] = model('Bank')->getInfoByType($has_choose[1],$has_choose[0],$lang);
                    }
                } else {
                    foreach (['bank', 'wechat', 'alipay'] as $type) {
                        if (!empty($ordersInfo[$type])) {
                            $bank_list[] = model('Bank')->getInfoByType($ordersInfo[$type],$type,$lang);
                        }
                    }
                }
                $tradeInfo['money_type'] = '';
                $tradeInfo['bank_list'] = $bank_list;//支付方式列表
                $tradeInfo['bank'] = null;
            } else {
                $tradeInfo['bank_list'] = [];
                $money_type = explode(":", $tradeInfo['money_type']);
                $tradeInfo['money_type'] = $money_type[0];
                if(!empty($trade_info['money_type'])) {
                    $tradeInfo['bank'] = model('Bank')->getInfoByType($money_type[1],$money_type[0],$lang);
                } else {
                    $tradeInfo['bank'] = null;
                }
            }

            $tradeInfo = $this->getStatusTxt($tradeInfo,$member_id);
            $tradeInfo['allege_id_status'] = empty($tradeInfo['allege_id'] == $tradeInfo['member_id']) ? 3 : 4;
        } else {
            if(empty($tradeInfo['money_type'])) {
                $bank_list = [];
                $ordersInfo = Db::name('Orders_otc')->field('bank,alipay,wechat')->where(['orders_id'=>$tradeInfo['sell_orders']])->find();

                $bank_list = [];
                foreach (['bank','wechat','alipay'] as $type) {
                    if(!empty($ordersInfo[$type])) $bank_list[] = $type.":".$ordersInfo[$type];
                }
                $tradeInfo['money_type'] = '';
                $tradeInfo['bank_list'] = $bank_list;
                $tradeInfo['bank'] = null;
            } else {
                $tradeInfo['bank_list'] = [];
                $money_type = explode(":", $tradeInfo['money_type']);
                $tradeInfo['money_type'] = $money_type[0];

                if($tradeInfo['status']==0 && $tradeInfo['type']=='buy') {
                    $tradeInfo['bank'] = model('Bank')->getInfoByType($money_type[1],$money_type[0],$lang);
                } else {
                    $tradeInfo['bank'] = ['bankname'=>$money_type[0]];
                }
            }
        }

        $limit_time = 0;
        if($tradeInfo['status']==0) {
            $tradeInfo['limit_time'] = $tradeInfo['limit_time'] - time();
            if($tradeInfo['limit_time']<=0) $tradeInfo['limit_time'] =0;
        }

        $tradeInfo['add_time'] = date('Y/m/d H:i:s',$tradeInfo['add_time']);
        $appeal_wait = 0; //可申诉倒计时
        if($tradeInfo['type']=='sell') {
            $tradeInfo['real_num'] = keepPoint($tradeInfo['num'] + $tradeInfo['fee'],6); //实际扣除

            if($tradeInfo['status']==1) {
                $pay_time =  Db::name('trade_otc')->where(['trade_id'=>$tradeInfo['other_trade_id']])->value('update_time');
                $appeal_wait = $pay_time + $repeal_time *60 - time();
            }
        } else {
            $tradeInfo['real_num'] = keepPoint($tradeInfo['num'] - $tradeInfo['fee'],6); //实际到账
            if($tradeInfo['status']==1) $appeal_wait = $tradeInfo['update_time'] + $repeal_time *60 - time();
        }

        if($appeal_wait<0) $appeal_wait = 0;
        $tradeInfo['appeal_wait'] = $appeal_wait;
        $tradeInfo['appeal_minute'] = $repeal_time;
//        $tradeInfo['username'] = $tradeInfo['phone'] ? $tradeInfo['phone'] : $tradeInfo['email'];

        $tradeInfo['currency_otc_buy_fee'] = $tradeInfo['currency_otc_sell_fee'] = 0;
        $currency = Db::name('currency')->field('currency_otc_buy_fee,currency_otc_sell_fee')->where(['currency_id'=>$tradeInfo['currency_id']])->find();
        if($currency) {
            $tradeInfo['currency_otc_buy_fee'] = $currency['currency_otc_buy_fee'];
            $tradeInfo['currency_otc_sell_fee'] = $currency['currency_otc_sell_fee'];
        }
        $tradeInfo['username'] = substr_replace($tradeInfo['username'], '****', 1);
        return $tradeInfo;
    }

    /**
     *卖给某买单 暂无上线
     *@param member_id 用户ID
     *@param orders_id 广告ID
     *@param num 购买数量
     *@param pwd 交易密码
     *@param otc_trade_online 交易中的订单数量限制
     *@param buy_confirm_time 购买确认时间
     *@param sell_confirm_time 出售确认时间
     */
    public function sell($member_id,$orders_id,$num,$pwd,$money_type,$otc_trade_online,$buy_confirm_time=0,$sell_confirm_time=0) {
        //敏感账户不能出售
        $member = Db::name('member')->field('is_sensitive')->where(['member_id'=>$member_id])->find();
        if(!$member || $member['is_sensitive']==1) return lang('operation_deny');

        $count = Db::name('TradeOtc')->where(['member_id'=>$member_id,'status'=>['lt',3]])->count();
        if($otc_trade_online>0 && $count>=$otc_trade_online) return lang('lan_order_otc_buy_limit2');

        if(empty($pwd)) return lang('lan_user_Transaction_password_empty');

        $checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd,true);
        if(is_string($checkPwd)) return $checkPwd;

        $orders_id = intval($orders_id);
        $tradenum = keepPoint($num,6);

        $checkorders = Db::name('orders_otc')->where(['orders_id'=>$orders_id])->find();
        if(!$checkorders) return lang('lan_orders_not_exists');
        if($checkorders['status']==3 || $checkorders['status']==2) return lang('lan_orders_not_exists');
        if ($checkorders['type']=='sell') return lang('lan_Illegal_operation');
        if ($checkorders['member_id'] == $member_id)  return lang('lan_order_otc_order_self');

        $sellprice = $checkorders['price'];
        if ($tradenum < 0) return lang('lan_number_must_gt_0');
        if ($tradenum * $sellprice < 100)  return lang('lan_trade_entrust_lowest');
        if($tradenum<0.0001) return lang('lan_trade_entrust_lowest1');

        $checkorders_num = $checkorders['avail_num'];
        if ($tradenum > $checkorders_num) return lang('lan_trade_number_wrong');

        //如果有最低限额,全部出售可通过,否则不通过
        $money = keepPoint($sellprice * $tradenum,2);
        if($checkorders['min_money']>0) {
            if($tradenum!=$checkorders_num  && $money < $checkorders['min_money']) return lang('lan_money_less_than').$checkorders['min_money'];
        }

        if($checkorders['max_money']>0 && $money > $checkorders['max_money']) return lang('lan_money_more_than').$checkorders['max_money'];

        $currency = model('Currency')->common_check($checkorders['currency_id'],'otc');
        if(is_string($currency)) return $currency;

        $money_type = explode(":", $money_type);
        if(count($money_type)!=2 || !in_array($money_type[0], ['bank','wechat','alipay'])) return lang('lan_please_select_payment_method');
        $choose = "";
        foreach (['bank','wechat','alipay'] as $type) {
            if($money_type[0]==$type && !empty($checkorders[$type])) {
                //卖家根据买家的支持的方式提供支付信息
                $bankInfo = Db::name('member_'.$type)->where(['id'=>intval($money_type[1]),'member_id'=>$member_id])->find();
                if($bankInfo) $choose = $type.':'.$money_type[1];
            }
        }
        if(empty($choose)) return lang('lan_please_select_payment_method');

        $fee = keepPoint($tradenum*($currency['currency_otc_sell_fee']/100),6);

        //减可用钱 加冻结钱
        Db::startTrans();
        try{
            $checkorders= Db::name('OrdersOtc')->lock(true)->where(['orders_id'=>$orders_id])->find();
            if($tradenum>$checkorders['avail_num']) throw new Exception(lang('lan_network_busy_try_again'));

            $time = time();$rand = rand(1000,9999);
            $flag = Db::name('OrdersOtc')->where(['orders_id'=>$orders_id])->update([
                'avail_num' => ['dec',$tradenum],
                'trade_time'=> $time,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //更新一下订单状态
            Db::name('OrdersOtc')->where("avail_num<num and status=0")->setField('status',1);

            $user_num = model('CurrencyUser')->getNum($member_id, $checkorders['currency_id'], 'num', true);
            //是否有余额扣。
            $all_num = $tradenum + $fee;
            if($user_num<$all_num) throw new Exception(lang('lan_insufficient_balance'));

            //添加账本信息
            $result = model('AccountBook')->addLog([
                'member_id' => $member_id,
                'currency_id' => $checkorders['currency_id'],
                'type'=> 31,
                'content' => 'lan_otc_sell_to_buy',
                'number_type' => 2,
                'number' => $all_num,
                'fee' => $fee,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $checkorders['orders_id'],
            ]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$checkorders['currency_id']])->update([
                'num' => ['dec',$all_num],
                'forzen_num'=> ['inc',$all_num],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $pay_number = rand(1000000,9999999);
            $money = keepPoint($sellprice * $tradenum,2);
            //插入买家记录
            $data = array(
                'member_id' => $member_id,
                'currency_id' => $checkorders['currency_id'],
                'price' => $sellprice,
                'num' => $tradenum,
                'fee' => $fee,
                'money' => $money,
                'type' => 'sell',
                'add_time' => $time,
                'trade_no' => "T{$time}".$rand,
                'other_member' => $checkorders['member_id'],
                'only_number' => rand(10000,99999).time(),
                'pay_number' => $pay_number,
                'limit_time' => ($time + $sell_confirm_time*60),
                'sell_orders' => $checkorders['orders_id'],
                'other_trade_id' => 0,
                'money_type' => $choose,
            );
            $trade_id1 = Db::name('trade_otc')->insertGetId($data);
            if(!$trade_id1) throw new Exception(lang('lan_network_busy_try_again'));

            //插入卖家记录
            $only_number = rand(10000,99999).time();
            $data = array(
                'member_id' => $checkorders['member_id'],
                'currency_id' => $checkorders['currency_id'],
                'price' => $sellprice,
                'num' => $tradenum,
                'fee' => keepPoint($tradenum * $checkorders['fee'],6),
                'money' => $money,
                'type' => 'buy',
                'add_time' => $time,
                'trade_no' => "T{$time}".$rand,
                'other_member' => $member_id,
                'only_number' => $only_number,
                'pay_number' => $pay_number,
                'limit_time' => ($time + $buy_confirm_time*60),
                'sell_orders' => $checkorders['orders_id'],
                'other_trade_id' => $trade_id1,
                'money_type' => $choose,
            );
            $trade_id2 = Db::name('trade_otc')->insertGetId($data);
            if(!$trade_id2) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('trade_otc')->where(['trade_id'=>$trade_id1])->setField('other_trade_id',$trade_id2);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();

            //添加系统消息提醒
            $flag = model('Jim')->sys_message('kd_'.$trade_id1.'_'.$trade_id2,'lan_otc_buyer_buy');

            //发送短信通知 卖家
            $member = Db::name('member')->field('country_code,phone,email')->where(['member_id'=>$checkorders['member_id']])->find();
            if($member) {
                if(!empty($member['country_code']) && !empty($member['phone'])) {
                    model('sender')->addLog(1,$member['phone'],$member['country_code'],'otc_sell',['code'=>$only_number]);
                }elseif(!empty($member['email'])) {
                    model('sender')->addLog(2,$member['email'],0,'otc_sell',['code'=>$only_number]);
                }
            }

            return ['trade_id'=>$trade_id1];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    protected function getStatusTxt($tradeInfo,$member_id) {
        //申诉中
        if($tradeInfo['status']==2) {
            if($tradeInfo['allege_id']==$member_id) {
                $tradeInfo['status_txt'] = lang('lan_trade_otc_status2_1').lang('lan_trade_otc_status2_txt');
            } else {
                $tradeInfo['status_txt'] = lang('lan_trade_otc_status2_2').lang('lan_trade_otc_status2_txt');
            }
        } elseif(!empty($tradeInfo['allege_id'])) {
            if($tradeInfo['allege_id']==$member_id) {
                $tradeInfo['status_txt'] = lang('lan_trade_otc_status2_1');
            } else {
                $tradeInfo['status_txt'] = lang('lan_trade_otc_status2_2');
            }

            if($tradeInfo['allege_status']==1){
                $tradeInfo['status_txt'] .= lang('lan_trade_otc_status_2_success');
            } else {
                $tradeInfo['status_txt'] .= lang('lan_trade_otc_status_2_fail');
            }
        } else {
            $tradeInfo['status_txt'] = lang('lan_trade_otc_status'.$tradeInfo['status']);
        }

        return $tradeInfo;
    }
}
