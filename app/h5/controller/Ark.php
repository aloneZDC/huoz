<?php


namespace app\h5\controller;

use think\Log;

class Ark extends Base
{
    // 闯关首页
    public function index()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkGoods::get_index($this->member_id, $page);
        $this->output_new($res);
    }

    // 闯关列表
    public function get_list()
    {
        $goods_id = intval(input('goods_id'));
        $page = intval(input('page'));
        $res = \app\common\model\ArkGoodsList::get_list($this->member_id, $goods_id, $page);
        $this->output_new($res);
    }

    // 获取支付信息
    public function get_pay_info()
    {
        $product_id = intval(input('product_id'));
        $money = input('num', 0);
        $res = \app\common\model\ArkMember::get_pay_info($this->member_id, $product_id, $money);
        $this->output_new($res);
    }

    // 参与闯关
    public function pay()
    {
        // 防止高并发请求
        if (public_thread() === false) {
            $this->output(ERROR1, '请求太频繁，稍后再试');
        }

        $product_id = intval(input('product_id'));
        $num = input('num');
        $kmt_num = input('kmt_num');
        $type = input('type');

        //Log::info('参与闯关'.json_encode(input(), true));
        $subscribe_currency_id = \app\common\model\ArkConfig::getValue('subscribe_currency_id');
        if ($type == $subscribe_currency_id) {
            $type = 4;//预约账户抢单
            $kmt_num = 0;
        } else {
            $type = 1;//可用账户抢单
        }
        $res = \app\common\model\ArkBuyList::add_list($this->member_id, $product_id, $num, $kmt_num, $type);
        $this->output_new($res);
    }

    // 推进记录
    public function buy_list()
    {
        $page = intval(input('page'));
        $type = input('type', 1);
        $res = \app\common\model\ArkGoods::buy_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 推进明细
    public function buy_detail()
    {
        $type = intval(input('type'));
        $page = intval(input('page'));
        $res = \app\common\model\ArkOrder::buy_detail($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 推进社区
    public function community_info()
    {
        $res = \app\common\model\ArkMember::get_community_info($this->member_id);
        $this->output_new($res);
    }

    // 我的辅导
    public function my_info()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkMember::get_my_info($this->member_id, $page);
        $this->output_new($res);
    }

    // 助力燃料
    public function help_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkRewardLog::get_help_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 动力燃料
    public function power_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkRewardLog::get_power_log($this->member_id, $page);
        $this->output_new($res);
    }

    // kmt燃料记录
    public function kmt_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkKmtLog::get_kmt_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 账户信息
    public function user_info()
    {
        $currency_id = input('currency_id');
        $res = \app\common\model\CurrencyUser::top_info($this->member_id, $currency_id);
        $this->output_new($res);
    }

    // 自动点火
    public function set_fire()
    {
        $is_fire = intval(input('is_fire', 1));
        $quota_type = intval(input('quota_type', 1));
        $quota_price = input('quota_price', 0);
        $res = \app\common\model\ArkMember::set_fire($this->member_id, $is_fire, $quota_type, $quota_price);
        $this->output_new($res);
    }

    // 自动点火信息
    public function fire_info()
    {
        $res = \app\common\model\ArkMember::get_fire_info($this->member_id);
        $this->output_new($res);
    }

    // 舱列表
    public function rocket_index()
    {
        $page = intval(input('page', 1));
        $type = intval(input('type', 1));
        $res = \app\common\model\ArkGoods::get_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 舱记录
    public function rocket_log()
    {
        $page = intval(input('page', 1));
        $goods_id = intval(input('goods_id'));
        $type = intval(input('type', 1));
        $res = \app\common\model\ArkGoods::get_log($this->member_id, $type, $goods_id, $page);
        $this->output_new($res);
    }

    // 订单记录
    public function order_index()
    {
        $goods_id = input('goods_id');
        $res = \app\common\model\ArkOrder::get_order_index($this->member_id, $goods_id);
        $this->output_new($res);
    }

    // 订单全部记录
    public function order_list()
    {
        $goods_id = input('goods_id');
        $page = intval(input('page', 1));
        $res = \app\common\model\ArkOrder::get_order_list($this->member_id, $goods_id, $page);
        $this->output_new($res);
    }

    // 划转记录
    public function transfer_log()
    {
        $page = intval(input('page', 1));
        $res = \app\common\model\ArkTransfer::get_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 划转账户
    public function transfer_info()
    {
        $res = \app\common\model\ArkTransfer::get_transfer_info($this->member_id);
        $this->output_new($res);
    }

    // 提交划转
    public function add_transfer()
    {
        $currency_id = input('currency_id');
        $out_currency_id = input('out_currency_id');
        $num = input('num');
        $res = \app\common\model\ArkTransfer::add_transfer($this->member_id, $currency_id, $out_currency_id, $num);
        $this->output_new($res);
    }

    // 预约闯关
    public function subscribe()
    {
        $product_id = intval(input('product_id'));
        $num = input('num');
        $kmt_num = input('kmt_num');

        $res = \app\common\model\ArkBuyList::add_list($this->member_id, $product_id, $num, $kmt_num, 2);
        $this->output_new($res);
    }

    // 预约记录
    public function subscribe_log()
    {
        $page = intval(input('page', 1));
        $res = \app\common\model\ArkBuyList::get_subscribe_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 取消复利
    public function sub_cancel()
    {
        $order_id = intval(input('order_id'));
        $res = \app\common\model\ArkOrder::sub_cancel($this->member_id, $order_id);
        $this->output_new($res);
    }

    //获取预约池余额
    public function get_user_info() {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        $subscribe_currency_id = \app\common\model\ArkConfig::getValue('subscribe_currency_id');
        if (empty($subscribe_currency_id)) {
            $this->output_new($r);
        }

        $result = \app\common\model\Currency::where(['currency_id' => $subscribe_currency_id])->field(['currency_id', 'currency_name'])->find();
        if (empty($result)) {
            $r['message'] = lang('no_data');
            $this->output_new($r);
        }
        $CurrencyUser = \app\common\model\CurrencyUser::getCurrencyUser($this->member_id, $subscribe_currency_id);
        $result['num'] = $CurrencyUser['num'];
        $result['forzen_num'] = $CurrencyUser['forzen_num'];
        $usdt_CurrencyUser = \app\common\model\CurrencyUser::getCurrencyUser($this->member_id, 5);
        $result['usdt_num'] =$usdt_CurrencyUser['num'];
        $result['date'] = date('Y-m-d');
        $info = \app\common\model\ArkMember::get_subscribe_num($this->member_id);
        if ($info) {
            $result = array_merge_recursive(json_decode($result,true), $info);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $r['result'] = $result;
        $this->output_new($r);
    }

    // 提交充值
    public function add_subscribe_transfer() {
        // 防止高并发请求
        if (public_thread() === false) {
            $this->output(ERROR1, '请求太频繁，稍后再试');
        }
        $num = input('num');
        $mtk_num = input('mtk_num');
        $out_currency_id = \app\common\model\ArkConfig::getValue('transfer_out_id');
        $currency_id = \app\common\model\ArkConfig::getValue('subscribe_currency_id');
        $res = \app\common\model\ArkSubscribeTransfer::add_transfer($this->member_id,$currency_id, $out_currency_id, $num, $mtk_num);
        $this->output_new($res);
    }

    // 排队记录
    public function queue_log() {
        $page = intval(input('page', 1));
        $res = \app\common\model\ArkSubscribeTransfer::get_queue_log($this->member_id, $page);
        $this->output_new($res);
    }

    //余额明细
    public function balance_detail() {
        $page = intval(input('page', 1));
        $res = \app\common\model\ArkSubscribeTransfer::get_balance_detail($this->member_id, $page);
        $this->output_new($res);
    }

    //资产互转
    public function subscribe_transfer() {
        $currency_id = intval(input('currency_id',0));
        $target_user_id = intval(input('target_user_id',0));
        $target_account = strval(input('target_account',''));
        $type = strval(input('type','num'));
        $num = input('num',0);
        $memo = strval(input('memo',''));
        $phone_code = input("post.phone_code");
        $paypwd = input("post.paypwd");

        //验证短信验证的
        if ($phone_code) {
            $result = model('Sender')->auto_check($this->member_id, "modifypwd", $phone_code);
            if (is_string($result)) {
                $r['code'] = ERROR1;
                $r['result'] = null;
                $r['message'] = $result;
                $this->output_new($r);
            }
        }

        //验证支付密码
        $password = \app\common\model\Member::verifyPaypwd($this->member_id, $paypwd);
        if ($password['code'] == SUCCESS) {
            $result = \app\common\model\CurrencyUserTransfer::subscribe_transfer($this->member_id, $currency_id, $target_user_id, $target_account, $num, $type,$memo);
            $this->output_new($result);
        } else {
            $this->output_new($password);
        }
    }

    // 预约奖励信息
    public function subscribe_info()
    {
        $res = \app\common\model\ArkRewardLog::get_subscribe_info($this->member_id);
        $this->output_new($res);
    }

    // 预约助力燃料
    public function subscribe_help_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkRewardLog::get_subscribe_help_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 预约动力燃料
    public function subscribe_power_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkRewardLog::get_subscribe_power_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 预约服务津贴
    public function centre_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ArkRewardLog::get_centre_log($this->member_id, $page);
        $this->output_new($res);
    }
}