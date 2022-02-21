<?php

namespace app\admin\controller;

use app\common\model\Currency;
use app\common\model\Goods;
use app\common\model\ShopPayType;
use app\common\model\WarrantCategory;
use app\common\model\WarrantExchange;
use app\common\model\WarrantGoods;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;
use think\response\Json;

/**
 * 资产包 - 后台
 * Class Warrant
 * @package app\admin\controller
 * @author hl
 * @date 2021-03-01
 */
class Warrant extends Admin
{
    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function goods_list(Request $request)
    {
        $where = [];
        $title = $request->param('title');
        if (!empty($title)) {
            $where['g.title'] = ['like', "%{$title}%"];
        }
        $category_id = $request->param('category_id');
        if (!empty($category_id)) {
            $where['g.category_id'] = $category_id;
        }
        $list = WarrantGoods::alias("g")
            ->where($where)
            ->where("g.status", "in", [WarrantGoods::STATUS_UP, WarrantGoods::STATUS_DOWN])
            ->join(config("database.prefix") . "warrant_category c", "c.id=g.category_id", "LEFT")
            ->field(['g.*', 'c.name' => 'category_name'])
            ->order('id desc')->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        $category = WarrantCategory::where(['status' => 1])->field(['id', 'name'])->order('sort', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'category'));
    }

    /**
     * 添加商品
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->post('form/a');
            if (empty($form['title'])) return $this->successJson(ERROR1, "标题不能为空");
            if (empty($form['img'])) return $this->successJson(ERROR1, "主图不能为空");
            if (empty($form['banners'])) return $this->successJson(ERROR1, "轮播图不能为空");

            $form['banners'] = json_encode($form['banners']);
            $form['time'] = time();
            $result = WarrantGoods::save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:");
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $currency = Currency::where(['is_line' => 1, 'account_type' => 'wallet'])->field(['currency_id', 'currency_name'])->select();
        $category = WarrantCategory::where(['status' => 1])->field(['id', 'name'])->order('sort', 'asc')->select();
        return $this->fetch(null, compact('category', 'currency'));
    }

    /**
     * 修改商品
     * @param Request $request
     * @return mixed|Json|void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function goods_edit(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->post('id', '0', 'intval');
            $info = WarrantGoods::where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, '该记录不存在');

            $form = $request->post('form/a');
            if (empty($form['title'])) return $this->successJson(ERROR1, "标题不能为空");
            if (empty($form['img'])) return $this->successJson(ERROR1, "主图不能为空");
            if (empty($form['banners'])) return $this->successJson(ERROR1, "轮播图不能为空");

            $form['banners'] = json_encode($form['banners']);
            $form['update_time'] = time();
            $result = $info->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $info->getError());
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $goods = WarrantGoods::where('id', $request->get('id'))->find();
        if (empty($goods)) {
            $this->error("商品不存在!");
            return;
        }
        $goods['banners'] = json_decode($goods['banners'], true);

        $category = WarrantCategory::where(['status' => 1])->field(['id', 'name'])->order('sort', 'asc')->select();
        $currency = Currency::where(['is_line' => 1, 'account_type' => 'wallet'])->field(['currency_id', 'currency_name'])->select();
        return $this->fetch(null, compact('goods', 'category', 'currency'));
    }

    /**
     * 商品上架下架
     * @param Request $request
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_up_and_down(Request $request)
    {
        $id = $request->post('id', '0', 'intval');
        $info = WarrantGoods::where(['id' => $id])->find();
        if (empty($info)) return $this->successJson(ERROR1, '该记录不存在');

        $status = $request->param('status', '1', 'intval');
        $info['status'] = $status;
        $info['update_time'] = time();
        if (!$info->save()) {
            return $this->successJson(ERROR1, "操作失败:" . $info->getError());
        }
        return $this->successJson(SUCCESS, "操作成功");
    }

    /**
     * 承保记录
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function exchange_list(Request $request)
    {
        $where = [];
        $member_id = $request->param('member_id');
        if (!empty($member_id)) {
            $where['t1.member_id'] = $member_id;
        }

        $list = WarrantExchange::alias("t1")
            ->where($where)
            ->join(config("database.prefix") . "currency t2", "t2.currency_id=t1.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency t3", "t3.currency_id=t1.to_currency_id", "LEFT")
            ->join(config("database.prefix") . "member t4", "t4.member_id=t1.member_id", "LEFT")
            ->field(['t1.*', 't2.currency_name', 't3.currency_name' => 'to_currency_name', 't4.ename'])
            ->order('t1.id desc')->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}