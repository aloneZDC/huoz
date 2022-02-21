<?php

namespace app\api\controller;

use OSS\Core\OssException;
use Think\Page;

class AccountManage extends OrderBase{
    protected $public_action = ["accent_list"]; //无需登录即可访问
    public function _initialize() {
        parent::_initialize();
    }

    /**
     * 当前委托
     */
    public function accent_list()
    {
        $currency_id = intval(input('post.currency_id'));
        $currency = db('Currency')->field("currency_id,currency_name,currency_mark,recharge_switch,take_switch,release_switch,release_switch_award,exchange_switch")->where(array('currency_id' => $currency_id, 'is_line' => 1))->find();
        $currency_user = db('Currency_user')->where(array('currency_id' => $currency_id, 'member_id' => $this->member_id))->find();

        if (!empty($currency_user)) {
            $currency_user['sum'] = number_format($currency_user['num'] + $currency_user['forzen_num'] + $currency_user['lock_num'] + $currency_user['exchange_num'], 6, '.', '');
        }
        //个人挂单记录
        $data = [
            'user_orders' => $this->getOrdersByUser(6, $currency['currency_id']),
            'currency' => $currency,
            'currency_user' => $currency_user,

        ];
        self::output(10000, '请求成功', $data);
    }
}