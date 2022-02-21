<?php

namespace app\api\controller;
use think\Exception;
use think\Db;

class Depute extends TradeFather
{
    //继承父类的方法
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *  撤销方法
     */
    public function cancel()
    {
        //声明本方法的请求方式(必须)
        parent::method_filter('post');

        $order_id = intval(input('post.order_id'));
        if (empty($order_id)) {
            self::output(10102, '请传入要撤销的订单号');
        }

        //获取人的一个订单
        $one_order = $this->getOneOrdersByMemberAndOrderId($this->member_id, $order_id, array(0, 1));
        if (empty($one_order)) {
            self::output(10101, '撤销完成（订单不存在）');
        }

        $oneOrder = $this->getOneOrders($one_order['type'], $one_order['currency_id'], 0, $one_order['currency_trade_id']);//买1、卖1
        if ($one_order['orders_id'] == $oneOrder['orders_id']) {
            self::output(10103, '稍后再试');
        }

        $info = $this->cancelOrdersByOrderId($one_order);
        if($info['status'] == 1){
            self::output(10000, $info['info']);
        }else{
            self::output(10004, $info['info']);
        }
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
        //$where['price']=array($gl,$price);
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order($order.',add_time asc')->find();
    }
}