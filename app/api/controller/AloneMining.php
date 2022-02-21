<?php

namespace app\api\controller;

/**
 * 独享矿机
 * Class AloneMining
 * @package app\api\controller
 */
class AloneMining extends Base
{
    protected $public_action = ['product', 'product_info'];

    // 产品列表
    public function product()
    {
        $page = intval(input('page'));
        $res = \app\common\model\AloneMiningProduct::product_list($page);
        $this->output_new($res);
    }

    // 商品详情 -- 下单时获取
    public function product_info()
    {
        $product_id = intval(input('product_id'));
        $res = \app\common\model\AloneMiningProduct::product_info($product_id);
        $this->output_new($res);
    }

    // 购买硬件
    public function buy()
    {
        $product_id = intval(input('product_id'));
        $amount = intval(input('amount'));
        $pay_id = intval(input('pay_id', ''));
        $res = \app\common\model\AloneMiningPay::buy($this->member_id, $product_id, $amount, $pay_id);
        $this->output_new($res);
    }

    //数据概况
    public function data_overview() {
        $res = \app\common\model\AloneMiningPay::data_overview($this->member_id);
        $this->output_new($res);
    }

    // 我的购买列表
    public function buy_list()
    {
        $page = intval(input('page'));
        $res = \app\common\model\AloneMiningPay::buy_list($this->member_id, $page);
        $this->output_new($res);
    }

    // 获取支付信息
    public function pay_type()
    {
        $res = \app\common\model\AloneMiningArchive::pay_type($this->member_id);
        $this->output_new($res);
    }

    // 矿机封存
    public function archive()
    {
        $product_id = intval(input('product_id'));
        $amount = intval(input('amount'));
        //$res = \app\common\model\AloneMiningArchive::buy($this->member_id, $product_id, $amount);
        $res = \app\common\model\AloneMiningArchiveLog::addItem($this->member_id, $product_id, $amount);
        $this->output_new($res);
    }

    // 封存记录
    public function archive_log()
    {
        $product_id = intval(input('product_id'));
        $page = intval(input('page'));
        $res = \app\common\model\AloneMiningArchive::archive_list($this->member_id, $product_id, $page);
        $this->output_new($res);
    }

    // 封存质押统计
    public function archive_count()
    {
        $product_id = intval(input('product_id'));
        $res = \app\common\model\AloneMiningPay::archive_count($this->member_id, $product_id);
        $this->output_new($res);
    }

    // 产币记录
    public function release_log()
    {
        $product_id = intval(input('product_id'));
        $page = intval(input('page'));
        $res = \app\common\model\AloneMiningRelease::release_log($this->member_id, $product_id, $page);
        $this->output_new($res);
    }

    // 线性释放明细
    public function income_list()
    {
        $product_id = intval(input('product_id'));
        $page = intval(input('page'));
        $type = intval(input('type'));
//        if ($type == 1) {
//            // 推荐奖
//            $res = \app\common\model\AloneMiningIncome::get_list($this->member_id, [1, 2, 3], $page);
//        } else {
        $res = \app\common\model\AloneMiningIncome::get_list($this->member_id, [4, 5], $page, $product_id);
        //}
        $this->output_new($res);
    }

    // 奖励明细
    public function reward_list() {
        $page = intval(input('page'));
        $res = \app\common\model\AloneMiningIncome::reward_list($this->member_id, $page);
        $this->output_new($res);
    }

    //奖励状态
    public function reward_status() {
        $result = [];
        $chiaInfo = \app\common\model\ChiaMiningCommission::where(['member_id' => $this->member_id])->find();
        $ipfsInfo = \app\common\model\AloneMiningCommission::where(['member_id' => $this->member_id])->find();
        $result['chia_status'] = $chiaInfo ? 1 : 0;
        $result['ipfs_status'] = $ipfsInfo ? 1 : 0;
        $res = ['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result];

        $this->output_new($res);
    }
}