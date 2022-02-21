<?php

namespace app\h5\controller;

use app\common\model\WarrantExchange;
use app\common\model\WarrantGoods;
use app\common\model\WarrantCategory;

/**
 * 资产包 - 接口
 * Class Warrant
 * @package app\h5\controller
 * @author hl
 * @date 2021-03-01
 */
class Warrant extends Base
{
    /**
     * 获取商品分类列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_category_list()
    {
        $r = ['code' => ERROR1, 'message' => lang("no_data"), 'result' => null];
        $result = $categories = WarrantCategory::get_category_list();
        if (empty($result)) {
            $this->output_new($r);
        }
        $this->output(SUCCESS, lang('data_success'), $result);
    }

    /**
     * 获取商品列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_goods_list()
    {
        $page = input("post.page", 1);
        $rows = input('post.rows', 10);
        $type = input("post.type", null);
        $result = WarrantGoods::get_goods_list($page, $rows, $type);
        $this->output_new($result);
    }

    /**
     * 商品详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_details()
    {
        $goods_id = input("post.goods_id");
        $result = WarrantGoods::goods_details($goods_id);
        $this->output_new($result);
    }

    /**
     * 承保(兑换)页面
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function exchange_index()
    {
        $goods_id = input("post.goods_id");
        $result = WarrantExchange::exchange_index($this->member_id, $goods_id);
        $this->output_new($result);
    }

    /**
     * 承保(兑换)
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exchange()
    {
        $goods_id = input("post.goods_id", 0);
        $number = input('post.number', 0);
        $result = WarrantExchange::exchange($this->member_id, $goods_id, $number);
        $this->output_new($result);
    }



    /**
     * 承保记录
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exchange_log()
    {
        $page = input("post.page", 1);
        $rows = input('post.rows', 10);
        $result = WarrantExchange::exchange_log($this->member_id, $page, $rows);
        $this->output_new($result);
    }

}