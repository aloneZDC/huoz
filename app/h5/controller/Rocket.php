<?php

namespace app\h5\controller;

use think\Log;

class Rocket extends Base
{
    // 闯关首页
    public function index()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketGoods::get_index($this->member_id, $page);
        $this->output_new($res);
    }

    // 闯关列表
    public function get_list()
    {
        $goods_id = intval(input('goods_id'));
        $page = intval(input('page'));
        $res = \app\common\model\RocketGoodsList::get_list($this->member_id, $goods_id, $page);
        $this->output_new($res);
    }

    // 获取支付信息
    public function get_pay_info()
    {
        $product_id = intval(input('product_id'));
        $money = input('num', 0);
        $res = \app\common\model\RocketMember::get_pay_info($this->member_id, $product_id, $money);
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

        Log::info('参与闯关'.json_encode(input(), true));
        $subscribe_currency_id = \app\common\model\RocketConfig::getValue('subscribe_currency_id');
        if ($type == $subscribe_currency_id) {
            $type = 4;//预约账户抢单
            $kmt_num = 0;
        } else {
            $type = 1;//可用账户抢单
        }
        $res = \app\common\model\RocketBuyList::add_list($this->member_id, $product_id, $num, $kmt_num, $type);
        $this->output_new($res);
    }

    // 推进记录
    public function buy_list()
    {
        $page = intval(input('page'));
        $type = input('type', 1);
        $res = \app\common\model\RocketGoods::buy_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 推进明细
    public function buy_detail()
    {
        $type = intval(input('type'));
        $page = intval(input('page'));
        $res = \app\common\model\RocketOrder::buy_detail($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 推进社区
    public function community_info()
    {
        $res = \app\common\model\RocketMember::get_community_info($this->member_id);
        $this->output_new($res);
    }

    // 我的辅导
    public function my_info()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketMember::get_my_info($this->member_id, $page);
        $this->output_new($res);
    }

    // 助力燃料
    public function help_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketRewardLog::get_help_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 动力燃料
    public function power_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketRewardLog::get_power_log($this->member_id, $page);
        $this->output_new($res);
    }

    // kmt燃料记录
    public function kmt_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketKmtLog::get_kmt_log($this->member_id, $page);
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
        $res = \app\common\model\RocketMember::set_fire($this->member_id, $is_fire, $quota_type, $quota_price);
        $this->output_new($res);
    }

    // 自动点火信息
    public function fire_info()
    {
        $res = \app\common\model\RocketMember::get_fire_info($this->member_id);
        $this->output_new($res);
    }

    /**
     * 订单合同
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_contract()
    {
        $order_type = input('order_type', 0, 'intval');
        $order_id = input('order_id', 0, 'intval');
        $res = \app\common\model\RocketMember::order_contract($this->member_id, $order_type, $order_id);
        $this->output_new($res);
    }

    // 提交合同签名
    public function submit_autograph()
    {
        $order_type = input('order_type', 0, 'intval');
        $order_id = input('order_id', 0, 'intval');
        $autograph = input('autograph', '', 'strval');
        $res = \app\common\model\RocketMember::submit_autograph($this->member_id, $order_type, $order_id, $autograph);
        $this->output_new($res);
    }

    // 舱列表
    public function rocket_index()
    {
        $page = intval(input('page', 1));
        $type = intval(input('type', 1));
        $res = \app\common\model\RocketGoods::get_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 舱记录
    public function rocket_log()
    {
        $page = intval(input('page', 1));
        $goods_id = intval(input('goods_id'));
        $type = intval(input('type', 1));
        $res = \app\common\model\RocketGoods::get_log($this->member_id, $type, $goods_id, $page);
        $this->output_new($res);
    }

    // 订单记录
    public function order_index()
    {
        $goods_id = input('goods_id');
        $res = \app\common\model\RocketOrder::get_order_index($this->member_id, $goods_id);
        $this->output_new($res);
    }

    // 订单全部记录
    public function order_list()
    {
        $goods_id = input('goods_id');
        $page = intval(input('page', 1));
        $res = \app\common\model\RocketOrder::get_order_list($this->member_id, $goods_id, $page);
        $this->output_new($res);
    }

    // 划转记录
    public function transfer_log()
    {
        $page = intval(input('page', 1));
        $res = \app\common\model\RocketTransfer::get_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 划转账户
    public function transfer_info()
    {
        $res = \app\common\model\RocketTransfer::get_transfer_info($this->member_id);
        $this->output_new($res);
    }

    // 提交划转
    public function add_transfer()
    {
        $currency_id = input('currency_id');
        $out_currency_id = input('out_currency_id');
        $num = input('num');
        $res = \app\common\model\RocketTransfer::add_transfer($this->member_id, $currency_id, $out_currency_id, $num);
        $this->output_new($res);
    }

    // 预约闯关
    public function subscribe()
    {
        $product_id = intval(input('product_id'));
        $num = input('num');
        $kmt_num = input('kmt_num');

        $res = \app\common\model\RocketBuyList::add_list($this->member_id, $product_id, $num, $kmt_num, 2);
        $this->output_new($res);
    }

    // 预约记录
    public function subscribe_log()
    {
        $page = intval(input('page', 1));
        $res = \app\common\model\RocketBuyList::get_subscribe_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 取消复利
    public function sub_cancel()
    {
        $order_id = intval(input('order_id'));
        $res = \app\common\model\RocketOrder::sub_cancel($this->member_id, $order_id);
        $this->output_new($res);
    }

    // 游戏类型
    public function game_type() {
        $res = \app\common\model\RocketGoods::get_game_type($this->member_id);
        $this->output_new($res);
    }

    // 预约奖励信息
    public function subscribe_info()
    {
        $res = \app\common\model\RocketRewardLog::get_subscribe_info($this->member_id);
        $this->output_new($res);
    }

    // 预约助力燃料
    public function subscribe_help_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketRewardLog::get_subscribe_help_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 预约动力燃料
    public function subscribe_power_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketRewardLog::get_subscribe_power_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 预约服务津贴
    public function centre_log()
    {
        $page = intval(input('page'));
        $res = \app\common\model\RocketRewardLog::get_centre_log($this->member_id, $page);
        $this->output_new($res);
    }

    // 数据统计明细
    public function statistics_info()
    {
        $res = \app\common\model\RocketSubscribeTransfer::get_statistics_info($this->member_id);
        $this->output_new($res);
    }
}