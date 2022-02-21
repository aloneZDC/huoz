<?php
//投票 俱乐部
namespace app\api\controller;

use app\common\model\FlopCurrency;
use app\common\model\FlopHongbao;
use app\common\model\FlopOrders;
use app\common\model\FlopTrade;
use app\common\model\HongbaoKeepLog;
use app\common\model\Member;
use app\common\model\Sender;

class Flop extends Base
{
    //币种列表
    public function currency() {
        $result = FlopCurrency::getList($this->member_id);
        $this->output_new($result);
    }

    //发布买单广告
    public function add_buy_orders() {
        $currency_id = intval(input('currency_id'));
//        $price = input('price');
        $price = 1;
        $num = input('num');
        $result = FlopOrders::add_buy_orders($this->member_id,$currency_id,$price,$num);
        $this->output_new($result);
    }

    //超级发布买单广告
    public function add_super_buy_orders() {
        $currency_id = intval(input('currency_id'));
//        $price = input('price');

//        验证支付密码
        $pwd = input('post.pwd');
        $password = Member::verifyPaypwd($this->member_id, $pwd);
        if ($password['code'] != SUCCESS) return $this->output_new($password);

        $price = 1;
        $num = input('num');
        $result = FlopOrders::add_super_buy_orders($this->member_id,$currency_id,$price,$num);
        $this->output_new($result);
    }

    //买单列表
    public function buy_orders_list() {
        $currency_id = intval(input("post.currency_id"));
        $page = intval(input("post.page"));
        $result = FlopOrders::buy_orders_list($this->member_id,$currency_id,$page);
        $this->output_new($result);
    }

    //更新可交易数量
    public function update_avail() {
        $orders_id = intval(input("post.orders_id"));
        $result = FlopOrders::updateAvail($this->member_id,$orders_id);
        $this->output_new($result);
    }

    //出售给买单
    public function sell_to_orders() {
        $orders_id = intval(input("post.orders_id"));
        $num = input("post.num");

        //验证支付密码
//        $pwd = input('post.pwd');
//        $password = Member::verifyPaypwd($this->member_id, $pwd);
//        if ($password['code'] != SUCCESS) return $this->output_new($password);

        //验证手机密码
//        $code = input('post.code');
//        $sender = new Sender();
//        $check = $sender->auto_check($this->member_id,'sell',$code);
//        if(is_string($check)) return $this->output_new([
//            'code' => ERROR1,
//            'message' => $check,
//            'result' => null,
//        ]);

        $result = FlopTrade::sell_to_orders($this->member_id,$orders_id,$num);
        $this->output_new($result);
    }

    //我的订单
    public function my_trades() {
        $type = strval(input("post.type"));
        $page = intval(input("post.page"));
        $result = FlopTrade::my_trades($this->member_id,$type,$page);
        $this->output_new($result);
    }

    //我的广告
    public function my_orders() {
        $status = intval(input("post.status"));
        $page = intval(input("post.page"));
        $result = FlopOrders::my_orders($this->member_id,$status,$page);
        $this->output_new($result);
    }

    //广告详情
    public function orders_info() {
        $orders_id = intval(input("post.orders_id"));
        $result = FlopOrders::orders_info($orders_id,$this->member_id);
        $this->output_new($result);
    }
    //广告订单列表
    public function orders_trade_list() {
        $orders_id = intval(input("post.orders_id"));
        $page = intval(input("post.page"));
        $result = FlopTrade::orders_trade_list($this->member_id,$orders_id,$page);
        $this->output_new($result);
    }

    //撤销广告
    public function cancel_orders() {
        $orders_id = intval(input("post.orders_id"));
        $result = FlopOrders::cancel_orders($this->member_id,$orders_id);
        $this->output_new($result);
    }

    //方舟可拆红包列表
    public function hongbao() {
        $result = FlopHongbao::getHongbaoList($this->member_id);
        $this->output_new($result);
    }

    //方舟拆红包
    public function open_hongbao() {
        $flop_hongbao_id = intval(input('post.id'));
        $result = FlopHongbao::openHongbao($this->member_id,$flop_hongbao_id);
        $this->output_new($result);
    }

    //方舟已拆红包列表
    public function hongbao_list() {
        $page = intval(input("post.page"));
        $result = FlopHongbao::getList($this->member_id,$page);
        $this->output_new($result);
    }

    public function keep_log_num() {
        $res1 = HongbaoKeepLog::num($this->member_id);
        $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $res1
        ]);
    }
}
