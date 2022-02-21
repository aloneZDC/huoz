<?php

namespace app\api\controller;

use app\common\model\YunMiningIncome;
use app\common\model\YunMiningPay;

class YunMining extends Base
{
    // 加速器
    public function machine_index()
    {
        $res = YunMiningPay::machine_index($this->member_id);
        $this->output_new($res);
    }

    // 质押
    public function machine_buy()
    {
        $amount = input('amount', 0, 'intval');
        $res = YunMiningPay::machine_buy($this->member_id, $amount);
        $this->output_new($res);
    }

    // 质押记录
    public function machine_list()
    {
        $page = input('page', 1, 'intval');
        $type = input('type', 0, 'intval');
        $res = YunMiningPay::machine_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    // 提取收益
    public function machine_extract()
    {
        $id = input('id', 0, 'intval');
        $amount = input('amount', 0);
        $res = YunMiningPay::machine_extract($this->member_id, $id, $amount);
        $this->output_new($res);
    }

    // 收益记录
    public function machine_income()
    {
        $id = input('id', 0, 'intval');
        $page = input('page', 0, 'intval');
        $res = YunMiningIncome::machine_income($this->member_id, $id, $page);
        $this->output_new($res);
    }

    // 解仓
    public function machine_cancel()
    {
        $id = input('id', 0, 'intval');
        $res = YunMiningPay::machine_cancel($this->member_id, $id);
        $this->output_new($res);
    }
}