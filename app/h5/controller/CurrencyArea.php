<?php
//投票 俱乐部
namespace app\h5\controller;

use app\common\model\Config;
use app\common\model\CurrencyAreaOrders;
use app\common\model\Member;
use think\Db;
use think\Exception;
use think\Request;

class CurrencyArea extends Base
{
    protected $is_decrypt = false; //不验证签名

    //专区列表
    public function index() {
        $page = intval(input('page',1));
        $res = \app\common\model\CurrencyArea::area_list($page);
        $this->output_new($res);
    }

    //专区详情
    public function area_info() {
        $currency_id = intval(input('currency_id',0));
        $res = \app\common\model\CurrencyArea::area_info($currency_id);
        $this->output_new($res);
    }

    //立即兑换
    public function orders() {
        $currency_id = intval(input('post.currency_id'));
        $num = intval(input('post.num'));
        $sa_id = intval(input('post.sa_id')); //收货地址
        $self_mention = intval(input('post.self_mention')); //是否自提  1为自提
        $mobile = strval(input('post.mobile','')); //客户自提预留电话
        $pay_pwd = strval(input("post.pay_pwd",""));

        $chekc = Member::verifyPaypwd($this->member_id, $pay_pwd);
        if ($chekc['code'] != SUCCESS) {
            $this->output_new([
                'code' => ERROR1,
                'message' => $chekc['message'],
                'result' => null,
            ]);
        }

        $res = CurrencyAreaOrders::add_orders($this->member_id,$currency_id,$num,$sa_id,$self_mention,$mobile);
        $this->output_new($res);
    }

    //我的兑换列表
    public function orders_list() {
        $status = intval(input('post.status'));
        $page = intval(input('page',1));
        $res = CurrencyAreaOrders::orders_list($this->member_id,$status,$page);
        $this->output_new($res);
    }

    //兑换详情
    public function orders_info() {
        $cao_id = intval(input('post.cao_id'));
        $res = CurrencyAreaOrders::orders_info($this->member_id,$cao_id);
        $this->output_new($res);
    }

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

    public function introduce() {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = '';
        $introduce_config = Config::where(['key'=>'area_introduce'])->find();
        if($introduce_config) $r['result'] = $introduce_config['value'];
        $this->output_new($r);
    }

    public function confirm_order() {
        $cao_id = intval(input('post.cao_id'));
        $res = CurrencyAreaOrders::confirm_order($this->member_id,$cao_id);
        $this->output_new($res);
    }
}
