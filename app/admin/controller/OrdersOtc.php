<?php
namespace app\admin\controller;

use think\Db;
use think\Exception;

class OrdersOtc extends Admin {
    public function index(){
        $type=I('type');
        $currency_id=I('currency_id');
        $phone=I('phone');
        $member_id = I('member_id');
        $orders_id = I('orders_id');

        $datePicker=strtotime(I('datePicker'));
        $datePicker2=strtotime(I('datePicker2'));
        $where=null;
        if(!empty($type)){
            $where['a.type'] = $type;
        }
        if(!empty($currency_id)){
            $where['a.currency_id'] = $currency_id;
        }

        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        if(!empty($orders_id)){
            $where['a.orders_id'] = $orders_id;
        }
        if(!empty($datePicker) && !empty($datePicker2)  ){
            $where['a.add_time'] = array('between',array($datePicker,$datePicker2));
        }
        if(!empty($member_id)){
            $where['c.member_id'] = $member_id;
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $list = Db::name('Orders_otc')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix")."currency b","a.currency_id = b.currency_id","LEFT")
            ->join(config("database.prefix")."member c","a.member_id = c.member_id","LEFT")
            ->where($where)
            ->order("a.orders_id desc")
           ->paginate(25,null,['query'=>input()])->each(function ($item,$key){
                $item['type_name'] = getOrdersType($item['type']);
               return $item;
            });
        $show=$list->render();
        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->where(['is_otc'=>1])->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
       return $this->fetch();
    }

    //撤销订单
    public function cancel() {
        $orders_id=intval(I('orders_id'));
        $ordersInfo = Db::name('Orders_otc')->where(['orders_id'=>$orders_id])->find();
        if(empty($ordersInfo)) $this->ajaxReturn("","订单不存在",0);

        //管理员撤销没有5小时限制
        //$limit = time() - $this->config['otc_cancel_limit'];
        //if($ordersInfo['add_time']>$limit) self::output(0, L('lan_order_otc_limit_time_delete'));

        //有未完成订单
        $count = Db::name('trade_otc')->where(['sell_orders'=>$orders_id,'type'=>'buy','status'=>['lt',3]])->count();
        if($count>0)$this->ajaxReturn('',"有交易訂單,無法撤銷！",0);

        Db::startTrans();
        try{
            //撤销广告直接扣除手续费
            if($ordersInfo['type']=='sell') {
                $avail = $ordersInfo['avail_num'];

                //5小时内手续费 0.1%
                $limit = time() - $this->config['otc_cancel_limit'];
                $fee = 0;
                if($ordersInfo['add_time']>$limit) {
                    $cancel_fee =  Db::name('currency')->where(['currency_id'=>$ordersInfo['currency_id']])->value('currency_otc_cancel_fee');
                    if($cancel_fee>0){
                        $cancel_fee = $cancel_fee/100;
                        if($cancel_fee>$ordersInfo['fee']) $cancel_fee = $ordersInfo['fee'];
                        $fee = keepPoint($avail * $cancel_fee,6);
                    }
                }

                if($fee>0) {
                    $fee_ab = model('AccountBook')->addLog([
                        'member_id' => $ordersInfo['member_id'],
                        'currency_id' => $ordersInfo['currency_id'],
                        'type'=> 33,
                        'content' => 'lan_otc_cancels_advertising_fee',
                        'number_type' => 2,
                        'number' => $fee,
                        'fee' => 0,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $orders_id,
                    ]);
                    if(!$fee_ab){
                       Db::rollback();
                       $this->ajaxReturn("","添加手续费帐本失败",0);
                    }
                    $flag = Db::name('currency_user')->where(['member_id'=>$ordersInfo['member_id'],'currency_id'=>$ordersInfo['currency_id']])->setDec('forzen_num',$fee);
                    if(!$flag) {
                        Db::rollback();
                        $this->ajaxReturn("","扣除手续费资金失败",0);
                    }

                    $result = $this->addFinance($ordersInfo['member_id'], 23, 'OTC撤銷廣告手续费', $fee, 2, $ordersInfo['currency_id'], $ordersInfo['orders_id']);
                    if($result===false) {
                        Db::rollback();
                        $this->ajaxReturn("","添加财务记录失败",0);
                    }
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
                        'fee' => $fee,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $ordersInfo['orders_id'],
                    ]);
                    if(!$result) {
                        Db::rollback();
                        $this->ajaxReturn("","添加帐本失败",0);
                    }

                    $flag = Db::name('currency_user')->where(['member_id'=>$ordersInfo['member_id'],'currency_id'=>$ordersInfo['currency_id']])->update([
                        'num' => ['inc',$avail],
                        'forzen_num' =>  ['dec',$avail],
                    ]);
                    if(!$flag) {
                        Db::rollback();
                        $this->ajaxReturn("","返回资金失败",0);
                    }
                }
            }

            $flag = Db::name('Orders_otc')->where(['orders_id'=>$ordersInfo['orders_id']])->setField('status',3);
            if(!$flag) {
                Db::rollback();
                $this->ajaxReturn("","修改状态失败",0);
            }

            Db::commit();
            $this->ajaxReturn("","操作成功",1);
        } catch(Exception $e) {
            Db::rollback();
            $this->ajaxReturn("","操作失败",0);
        }
    }
}
