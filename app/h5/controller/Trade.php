<?php
//币币交易
namespace app\h5\controller;

class Trade extends Base
{
    public $public_action = ['currency_price_usd','currency_trade_price_usd'];

    //获取币币对 前面币种 USD价格 - 昨日价格
    public function currency_price_usd() {
        $currency_id = intval(input('currency_id'));
        $currency_trade_id = intval(input('currency_trade_id'));
        $price = \app\common\model\Trade::getYestodayMaxPrice($currency_id,$currency_trade_id);

        //今日提币总数量：151.50000000 实际到账：150.000000
        $usd = \app\common\model\Trade::getCurrencyTradeRealMoney($currency_trade_id,'USD');
        return $this->output_new([
            'code' => SUCCESS,
            'msg' => 'success',
            'result' => keepPoint($price*$usd,6),
        ]);
    }

    //获取币币对 后面币种 USD价格 - 昨日价格
    public function currency_trade_price_usd() {
        $currency_trade_id = intval(input('currency_trade_id'));
        $usd = \app\common\model\Trade::getCurrencyTradeRealMoney($currency_trade_id,'USD');
        return $this->output_new([
            'code' => SUCCESS,
            'msg' => 'success',
            'result' => $usd,
        ]);
    }
}
