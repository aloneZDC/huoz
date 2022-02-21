<?php

namespace app\api\controller;

use app\common\model\MtkCurrencyPrice;
use app\common\model\InsideConfig;
use app\common\model\InsideOrder;

class InsideTrade extends Base
{
    // 自选交易 - 首页
    public function trade_index()
    {
        $type = input('type', 1, 'intval');
        $page = input('page', 1, 'intval');
        $res = InsideOrder::trade_index($type, $page);
        $this->output_new($res);
    }

    // 自选交易 - 详情
    public function trade_info()
    {
        $order_id = input('order_id', 0, 'intval');
        $res = InsideOrder::trade_info($this->member_id, $order_id);
        $this->output_new($res);
    }

    // 自选交易 - 买卖
    public function buy_sell()
    {
        $order_id = input('order_id', 0, 'intval');
        $trade_num = input('num', 0, 'floatval');
        $res = \app\common\model\InsideTrade::buy_sell($this->member_id, $order_id, $trade_num);
        $this->output_new($res);
    }

    // 自选交易 - 订单
    public function order_list()
    {
        $type = input('type', 1, 'intval');
        $page = input('page', 1, 'intval');
        $res = \app\common\model\InsideTrade::order_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 发布广告 - 配置
    public function trade_config()
    {
        $type = input('type', 1, 'intval');
        $res = InsideConfig::trade_config($type);
        $this->output_new($res);
    }

    // 发布广告 - 买卖
    public function trade_buy_sell()
    {
        $id = input('id', 0, 'intval');
        $price = input('price', 0, 'floatval');
        $trade_num = input('num', 0, 'floatval');
        $res = InsideOrder::trade_buy_sell($this->member_id, $id, $price, $trade_num);
        $this->output_new($res);
    }

    // 撤销广告
    public function trade_revoke()
    {
        $order_id = input('order_id', 0, 'intval');
        $res = InsideOrder::trade_revoke($this->member_id, $order_id);
        $this->output_new($res);
    }

    // 我的广告
    public function trade_order()
    {
        $status = input('type', 0, 'intval');
        $page = input('page', 1, 'intval');
        $res = InsideOrder::trade_order($this->member_id, $status, $page);
        $this->output_new($res);
    }

    // 广告详情
    public function order_info()
    {
        $order_id = input('order_id', 0, 'intval');
        $page = input('page', 1, 'intval');
        $res = InsideOrder::order_info($this->member_id, $order_id, $page);
        $this->output_new($res);
    }

}