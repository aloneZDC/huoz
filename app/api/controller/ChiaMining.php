<?php

namespace app\api\controller;

/**
 * CHIA(奇亚)云算力
 * Class ChiaMining
 * @package app\api\controller
 */
class ChiaMining extends Base
{
    protected $public_action = ['product_list', 'product_info', 'share_list'];

    // 产品列表
    public function product_list()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ChiaMiningProduct::product_list($page);
        $this->output_new($res);
    }

    // 商品详情
    public function product_info()
    {
        $product_id = intval(input('product_id'));
        $res = \app\common\model\ChiaMiningProduct::product_info($product_id);
        $this->output_new($res);
    }

    // 购买硬件
    public function product_buy()
    {
        $product_id = intval(input('product_id'));
        $amount = intval(input('amount'));
        $res = \app\common\model\ChiaMiningPay::product_buy($this->member_id, $product_id, $amount);
        $this->output_new($res);
    }

    // 购买列表
    public function buy_list()
    {
        $page = intval(input('page'));
        $res = \app\common\model\ChiaMiningPay::buy_list($this->member_id, $page);
        $this->output_new($res);
    }

    // 产币记录
    public function release_list() {
        $page = intval(input('page'));
        $product_id = intval(input('product_id'));
        $res = \app\common\model\ChiaMiningRelease::release_list($this->member_id, $product_id, $page);
        $this->output_new($res);
    }

    // 奖励列表
    public function income_list() {
        $page = intval(input('page'));
        $res = \app\common\model\ChiaMiningIncome::income_list($this->member_id, $page);
        $this->output_new($res);
    }

    //分享收益
    public function share_list() {
        $page = intval(input('page'));
        $res = \app\common\model\ChiaMiningIncome::get_list($this->member_id, $page);
        $this->output_new($res);
    }

    //奖池收益
    public function award_list() {
        $res = \app\common\model\ChiaMiningMember::award_list($this->member_id);
        $this->output_new($res);
    }

    //每日业绩
    public function everyday_achievement() {
        $page = intval(input('page'));
        $type = intval(input('type'));
        $res = \app\common\model\ChiaMiningMemberSummary::achievement_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    //每日收益
    public function everyday_award() {
        $page = intval(input('page'));
        $type = intval(input('type'));
        $res = \app\common\model\ChiaMiningMemberSummary::award_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

    //累计奖池
    public function total_award() {
        $page = intval(input('page'));
        $type = intval(input('type'));
        $res = \app\common\model\ChiaMiningMemberSettlement::total_award_list($this->member_id, $type, $page);
        $this->output_new($res);
    }

}