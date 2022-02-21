<?php
//ABF类型币币交易
namespace app\api\controller;

use app\common\model\AbfKline;
use app\common\model\AbfTradeCurrency;
use think\Db;
use think\Exception;
use think\Request;

class AbfOrders extends Base
{
    protected $public_action  = ['currencys','currency_info','currency_desc','buy_sell_list'];

    //币种列表
    public function currencys() {
        $res = [
            'code' => SUCCESS,
            'message' => lang('success_operation'),
            'result' =>  AbfTradeCurrency::getListApi(),
        ];
        return $this->output_new($res);
    }

    //币种详情
    public function currency_info() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $res = [
            'code' => SUCCESS,
            'message' => lang('success_operation'),
            'result' =>  AbfTradeCurrency::getAbfCurrencyApi($currency_id,$currency_trade_id),
        ];
        return $this->output_new($res);
    }

    //币种介绍
    public function currency_desc() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $res = [
            'code' => SUCCESS,
            'message' => lang('success_operation'),
            'result' =>  AbfTradeCurrency::getAbfCurrencyDescApi($currency_id,$currency_trade_id),
        ];
        return $this->output_new($res);
    }

    //K线图
    public function kline() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $page = intval(input('page')); //买卖盘数量
        $rows = intval(input('rows',100)); //买卖盘数量
        $res = AbfKline::kline($currency_id,$currency_trade_id,$page,$rows);
        return $this->output_new($res);
    }

    //买卖盘
    public function buy_sell_list() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $rows = intval(input('rows',10)); //买卖盘数量

        $res = \app\common\model\AbfOrders::buy_sell_list($this->member_id,$currency_id,$currency_trade_id,$rows);
        return $this->output_new($res);
    }

    //挂买单
    public function add_buy_orders() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $price = input('price'); //目前固定价格 可不传
        $num = input('num'); //数量

        $res = \app\common\model\AbfOrders::buy($this->member_id,$currency_id,$currency_trade_id,$price,$num);
        return $this->output_new($res);
    }

    //挂卖单
    public function add_sell_orders() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $price = input('price'); //目前固定价格 可不传
        $num = input('num'); //数量

        $res = \app\common\model\AbfOrders::sell($this->member_id,$currency_id,$currency_trade_id,$price,$num);
        return $this->output_new($res);
    }

    //撤销订单
    public function cancel_orders() {
        $orders_id = intval(input('orders_id'));
        $res = \app\common\model\AbfOrders::cancel($this->member_id,$orders_id);
        return $this->output_new($res);
    }

    //我的委托
    public function my_orders() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $page = intval(input('page'));
        $res = \app\common\model\AbfOrders::my_orders($this->member_id,$currency_id,$currency_trade_id,true,$page);
        return $this->output_new($res);
    }

    //我的历史委托
    public function my_history_orders() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $page = intval(input('page'));
        $res = \app\common\model\AbfOrders::my_orders($this->member_id,$currency_id,$currency_trade_id,false,$page);
        return $this->output_new($res);
    }

    public function orders_trade_list() {
        $orders_id = intval(input('orders_id'));
        $page = intval(input('page'));
        $res = \app\common\model\AbfTrade::orders_trade_list($this->member_id,$orders_id,$page);
        return $this->output_new($res);
    }
}
