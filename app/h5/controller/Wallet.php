<?php

namespace app\h5\controller;

use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\RocketMember;
use think\Db;

/**
 * 账户资产 - 接口
 * Class Wallet
 * @package app\h5\controller
 */
class Wallet extends Base
{
    /**
     * 获取资产
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_assets()
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        $currency_id = input("post.currency_id");
        if (empty($currency_id)) {
            $this->output_new($r);
        }

        $result = Currency::where(['currency_id' => $currency_id])->field(['currency_id', 'currency_name'])->find();
        if (empty($result)) {
            $r['message'] = lang('no_data');
            $this->output_new($r);
        }

        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id);
        $result['num'] = $CurrencyUser['num'];

        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $r['result'] = $result;
        $this->output_new($r);
    }

    //获取预约池余额
    public function get_user_info() {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        $subscribe_currency_id = \app\common\model\RocketConfig::getValue('subscribe_currency_id');
        if (empty($subscribe_currency_id)) {
            $this->output_new($r);
        }

        $result = Currency::where(['currency_id' => $subscribe_currency_id])->field(['currency_id', 'currency_name'])->find();
        if (empty($result)) {
            $r['message'] = lang('no_data');
            $this->output_new($r);
        }
        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $subscribe_currency_id);
        $result['num'] = $CurrencyUser['num'];
        $result['forzen_num'] = $CurrencyUser['forzen_num'];
        $usdt_CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, 5);
        $result['usdt_num'] =$usdt_CurrencyUser['num'];
        $result['date'] = date('Y-m-d');
        $info = RocketMember::get_subscribe_num($this->member_id);
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
        $out_currency_id = \app\common\model\RocketConfig::getValue('transfer_out_id');
        $currency_id = \app\common\model\RocketConfig::getValue('subscribe_currency_id');
        $res = \app\common\model\RocketSubscribeTransfer::add_transfer($this->member_id,$currency_id, $out_currency_id, $num, $mtk_num);
        $this->output_new($res);
    }

    // 排队记录
    public function queue_log() {
        $page = intval(input('page', 1));
        $res = \app\common\model\RocketSubscribeTransfer::get_queue_log($this->member_id, $page);
        $this->output_new($res);
    }

    //余额明细
    public function balance_detail() {
        $page = intval(input('page', 1));
        $res = \app\common\model\RocketSubscribeTransfer::get_balance_detail($this->member_id, $page);
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
}