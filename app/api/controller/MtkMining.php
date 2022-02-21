<?php

namespace app\api\controller;

use app\common\model\MtkMiningIncome;
use app\common\model\MtkMiningMember;
use app\common\model\MtkMiningOrder;
use app\common\model\MtkMiningPay;
use app\common\model\MtkMiningProduct;
use app\common\model\MtkMiningRelease;

class MtkMining extends Base
{
    protected $public_action = ['product_list'];

    // 产品列表
    public function product_list()
    {
        $page = input('page', 1, 'intval');
        $res = MtkMiningProduct::product_list($this->member_id, $page);
        $this->output_new($res);
    }

    // 商品购买
    public function product_buy()
    {
        $product_id = input('product_id', 0, 'intval');
        $amount = input('amount', 0, 'floatval');
        $pay_id = input('pay_id', '', 'intval');
        $res = MtkMiningPay::product_buy($this->member_id, $product_id, $amount, $pay_id);
        $this->output_new($res);
    }

    // 购买列表
    public function order_list()
    {
        $page = input('page', 1, 'intval');
        $res = MtkMiningOrder::order_list($this->member_id, $page);
        $this->output_new($res);
    }

    // 算力社区
    public function community_index()
    {
        $res = MtkMiningMember::community_index($this->member_id);
        $this->output_new($res);
    }

    // 产出明细
    public function release_list()
    {
        $page = input('page', 1, 'intval');
        $product_id = input('product_id', 0, 'intval');
        $res = MtkMiningRelease::release_list($this->member_id, $product_id, $page);
        $this->output_new($res);
    }

    // 获取奖励记录
    public function income_list()
    {
        $page = input('page', 1, 'intval');
        $type = input('type', 0, 'intval');
        // 产币线性释放
        if ($type == 4) {
            $res = MtkMiningIncome::income_list($this->member_id, [4, 5], $page);
        } // 加速服务明细
        elseif ($type == 6) {
            $res = MtkMiningIncome::income_list($this->member_id, [6], $page);
        } // 线性释放明细
        elseif ($type == 7) {
            $res = MtkMiningIncome::income_list($this->member_id, 7, $page);
        } else {
            $res = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        }
        $this->output_new($res);
    }

    // 产币 统计
    public function release_count()
    {
        $product_id = input('product_id', 0, 'intval');
        $res = MtkMiningRelease::release_count($this->member_id, $product_id);
        $this->output_new($res);
    }
}