<?php
namespace app\api\controller;

class CurrencyArea extends Base
{
    //实时用户排名
    public function get_user_currency_list() {
        $currency_id = intval(input('currency_id',0));
        $page = intval(input('page',0));

        $res = \app\common\model\CurrencyArea::get_user_currency_list($currency_id,$page);
        $this->output_new($res);
    }

    //实时兑换排名
    public function get_orders_list() {
        $currency_id = intval(input('currency_id',0));
        $page = intval(input('page',0));

        $res = \app\common\model\CurrencyArea::get_orders_list($currency_id,$page);
        $this->output_new($res);
    }
}
