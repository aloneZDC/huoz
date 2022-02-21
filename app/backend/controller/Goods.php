<?php

namespace app\backend\controller;

use app\backend\model\BackendUsers;
use app\common\model\ExpressList;
use app\common\model\GoodsCategory;
use app\common\model\GoodsFormat;
use app\common\model\GoodsMainOrders;
use app\common\model\GoodsOrders;
use app\common\model\ShopConfig;
use app\common\model\ShopLogisticsList;
use app\common\model\ShopPayType;
use think\exception\DbException;
use think\Request;
use think\Exception;
use think\Db;

class Goods extends AdminQuick
{
    protected $public_action = ['upload_goods', 'format_add', 'format_edit', 'update_reward', 'update_price', 'upload_again', 'pick_reward', 'cancel_order'];

    //发货
    public function sub_deliver()
    {
        $gmo_id = input('gmo_id');

        $check = GoodsMainOrders::where(['gmo_id' => $gmo_id, 'gmo_status' => 1])->find();
        if (!$check) {
            return $this->successJson(ERROR1, "发货失败，该订单不是待发货状态", null);
        }
        $autoSureTime = ShopConfig::get_value('orders_auto_sure_time', 4320);
        $gmo_auto_sure_time = time() + ($autoSureTime * 60);
        $result = GoodsMainOrders::where(['gmo_id' => $gmo_id])->update(['gmo_status' => 3, 'gmo_auto_sure_time' => $gmo_auto_sure_time]);
        if ($result === false) {
            return $this->successJson(ERROR1, "发货失败", null);
        } else {
            return $this->successJson(SUCCESS, "发货成功", ['url' => url('')]);
        }
    }

    // 自提订单详情
    public function pick_details(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->post('gmo_id');
            $form = [];
            $form['gmo_receive_name'] = $request->post('gmo_receive_name');
            $form['gmo_mobile'] = $request->post('gmo_mobile');
            $form['gmo_address'] = $request->post('gmo_address');
            $form['gmo_remark'] = $request->post('gmo_remark');
            $result = GoodsMainOrders::where(['gmo_id' => $id])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $gmo_id = $request->param('gmo_id');
        $info = GoodsMainOrders::with(['paytype', 'payCurrency', 'equalCurrency', 'rebateParentCurrency', 'rebateSelfCurrency', 'giveCurrency'])
            ->where(['gmo_id' => $gmo_id])->find();
        if (empty($info)) $this->error('该订单不存在');
        $info['gmo_status'] = GoodsMainOrders::STATUS_ENUM[$info['gmo_status']];
        $info['gmo_status_refund'] = GoodsMainOrders::REFUND_ENUM[$info['gmo_status_refund']];

        return $this->fetch(null, compact('info'));
    }

    // 自提订单
    public function pick_list(Request $request)
    {
        $gmo_code = $request->param('gmo_code', '');
        $where = [];

        $statusList = GoodsMainOrders::STATUS_ENUM;
        $payTypeList = ShopPayType::getPayTypeList();
        $daochu = $request->param('daochu');
        if ($daochu == 1) {
            ini_set("display_errors", 1);
            ini_set('memory_limit', '-1');
            if ($gmo_code) {
                $where['b.gmo_code'] = ['like', "%{$gmo_code}%"];
            }
            $gmo_status = $request->param('gmo_status', 0);
            if (!empty($gmo_status)) {
                $where['b.gmo_status'] = $gmo_status;
                $request->get(['gmo_status' => $gmo_status]);
            }
            $gmo_user_id = $request->param('gmo_user_id');
            if ($gmo_user_id) {
                $where['b.gmo_user_id'] = $gmo_user_id;
            }
            $where['b.gmo_express_type'] = 3;
            $checkbox =  $request->param('checkboxid');
            if (!empty($checkbox)) {
                $explodeid = explode(',', $checkbox);
                $where['b.gmo_id'] = ['in', $explodeid];
            }

            $list = \app\common\model\GoodsOrders::alias('a')->where($where)
                ->join('goods_main_orders b', 'a.go_main_id=b.gmo_id')
                ->join('goods c', 'a.go_goods_id = c.goods_id')
                ->join("goods_format f", "f.id=a.go_format_id", "LEFT")
                ->field("a.*,b.*,c.*,f.name as goods_format")
                ->group('a.go_id')
                ->order('a.go_add_time', 'desc')->select();

            $list1 = [];
            if (!empty($list)) {
                foreach ($list as $value) {
                    $address = '';
                    if (!empty($value['gmo_receive_name'])) {
                        $address = "收货人: {$value['gmo_receive_name']}, 手机号码: {$value['gmo_mobile']}, 地址: {$value['gmo_address']}";
                    }
                    $pickedup_address = '';
                    if (!empty($value['gmo_pickedup_name'])) {
                        $pickedup_address = "提货人: {$value['gmo_pickedup_name']}, 手机号码: {$value['gmo_pickedup_mobile']}, 自提点: {$value['gmo_pickedup_address']}";
                    }
                    if (!empty($value['format_price'])) {
                        $goods_purch = $value['format_price'];
                    } else {
                        $goods_purch = $value['goods_purch'];
                    }

                    $list1[] = [
                        'gmo_id' => $value['gmo_id'],
                        'gmo_user_id' => $value['gmo_user_id'],
                        'gmo_code' => "\t" . $value['gmo_code'] . "\t",
                        'gmo_pay_time' => !empty($value['gmo_pay_time']) ? date('Y-m-d H:i:s', $value['gmo_pay_time']) : 0,
                        'goods_title' => $value['goods_title'],
                        'goods_format' => $value['goods_format'],
                        'gmo_num' => $value['go_num'],
                        'goods_purch' => floattostr($goods_purch),
                        'gmo_total_price' => floattostr($value['go_total_price']),
                        'gmo_pay_num' => floattostr($value['go_total_price']),
                        'gmo_pay_type' => $payTypeList[$value['gmo_pay_type']],
                        'gmo_receive_name' => $address,
                        'gmo_pickedup_name' => $pickedup_address,
                        'gmo_status' => $statusList[$value['gmo_status']],
                    ];
                }
            }
            $xlsCell = array(
                array('gmo_id', '订单ID'),
                array('gmo_user_id', '用户ID'),
                //array('goods_detail', '商品详情'),
                array('gmo_code', '订单编号', 20),
                array('gmo_pay_time', '支付时间', 20),
                array('goods_title', '商品名称', 40),
                array('goods_format', '商品规格', 40),
                array('gmo_num', '商品总数'),
                array('goods_purch', '单个结算成本价'),
                array('gmo_total_price', '订单总价'),
                array('gmo_pay_num', '实付款'),
                array('gmo_pay_type', '支付方式', 12),
                array('gmo_receive_name', '发货信息', 100),
                array('gmo_pickedup_name', '自提信息', 100),
                array('gmo_status', '订单状态'),
                //array('goods_img', '商品图片'),
            );
            $this->exportExcel("订单列表", $xlsCell, $list1);
            die();
        }
        if ($gmo_code) {
            $where['a.gmo_code'] = ['like', "%{$gmo_code}%"];
        }
        $gmo_status = $request->param('gmo_status', 0);
        if (!empty($gmo_status)) {
            $where['a.gmo_status'] = $gmo_status;
            $request->get(['gmo_status' => $gmo_status]);
        }
        $gmo_user_id = $request->param('gmo_user_id');
        if ($gmo_user_id) {
            $where['a.gmo_user_id'] = $gmo_user_id;
        }
        $goods_code = $request->param('goods_code');
        if ($goods_code) {
            $where['c.goods_code'] = $goods_code;
        }
        // 虚拟(自提区)
        $where['a.gmo_express_type'] = 3;
        $list = GoodsMainOrders::alias('a')->where($where)
            ->field("a.*,b.*,c.*,f.name as goods_format")
            ->join('goods_orders b', 'b.go_main_id = a.gmo_id', 'LEFT')
            ->join('goods c', 'c.goods_id IN (b.go_goods_id)', 'LEFT')
            ->join("goods_format f", "f.id=b.go_format_id", "LEFT")
            ->order('a.gmo_add_time', 'desc')->paginate(null, null, ["query" => $request->get()]);

        if ($list) {
            foreach ($list as &$item) {
                if ($item['gmo_express_type'] == 1) {
                    $item['type_name'] = '物流';
                } elseif ($item['gmo_express_type'] == 2) {
                    $item['type_name'] = '自提';
                } else {
                    $item['type_name'] = '虚拟';
                }

                if (!empty($item['gmo_pickedup_name']) && $item['gmo_express_type'] == 2) {
                    $item['gmo_receive_name'] = $item['gmo_pickedup_name'];
                    $item['gmo_mobile'] = $item['gmo_pickedup_mobile'];
                    $item['gmo_address'] = $item['gmo_pickedup_address'];
                }
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'statusList', 'payTypeList'));
    }

    /**
     * @param Request $request
     * @return array|\think\response\Json
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function quick_switch()
    {
        $id = intval(input('id'));
        $field = input('field');
        $status = intval(input('status'));

        try {
            $info = \app\common\model\Goods::where(['goods_id' => $id])->find();
            if (!$info) throw new Exception("记录不存在,不能执行该操作");
            if (!isset($info[$field])) throw new Exception("非法字段,不能执行该操作");

            $flag = \app\common\model\Goods::where(['goods_id' => $id])->setField($field, $status);
            if ($flag === false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function goods_list(Request $request)
    {
        $where = [];
        $title = $request->param('goods_title');
        if ($title) {
            $where['goods_title'] = ['like', "%{$title}%"];
        }
        $category_id = $request->param('category_id');
        if (!empty($category_id)) {
            $where['category_id'] = $category_id;
        }
        $goods_is_hot = $request->param('goods_is_hot');
        if (!empty($goods_is_hot)) {
            $where['goods_is_hot'] = $goods_is_hot;
        }

        $list = \app\common\model\Goods::alias("g")
            ->join("goods_category c", "c.id=g.category_id", "left")
            ->field(['g.*', 'c.name' => 'category_name', 'c.pid' => 'category_type'])
            ->where($where)->order("goods_id desc")->paginate(null, null, ["query" => $request->get()]);
        foreach ($list as &$value) {
            $BackendUsers = BackendUsers::where(['id' => $value['admin_id']])->find();
            $value['admin_name'] = $BackendUsers['username'];
        }

        $page = $list->render();
        $count = $list->total();

        // 分类
//        $category = GoodsCategory::where(['status' => 1])->field(['id', 'name'])->order('sort', 'asc')->select();
        $category = GoodsCategory::where(['status' => 1, 'pid' => 0])->field(['id', 'name'])->order('sort', 'asc')->select();
        foreach ($category as &$item) {
            $GoodsCategory = GoodsCategory::where(['status' => 1, 'pid' => $item['id']])->field(['id', 'name'])->order('sort', 'asc')->select();
            $item['list'] = $GoodsCategory;
            if (empty($GoodsCategory)) {
                $item['list'] = [['id' => $item['id'], 'name' => '全部']];
            }
        }
        return $this->fetch(null, compact('list', 'page', 'count', 'category'));
    }

    /**
     * 添加商品
     * @param Request $request
     * @return array|mixed
     * @throws DbException
     */
    public function goods_create(Request $request)
    {
//        $equalCurrencyId = ShopConfig::get_value('equal_currency_id', 75);
//        $rebateCurrencyId = ShopConfig::get_value('rebate_currency_id', 74);
        $giveCurrencyId = ShopConfig::get_value('give_currency_id', 5);
        if ($request->isPost()) {
            $form = $request->param('form/a');

            if (empty($form['goods_title'])) return $this->successJson(ERROR1, "标题不能为空", null);
            if (empty($form['goods_img'])) return $this->successJson(ERROR1, "主图不能为空", null);
            if (empty($form['goods_banners'])) return $this->successJson(ERROR1, "轮播图不能为空", null);

//            $groupCategory = GoodsCategory::where(['status' => 1, 'type' => GoodsCategory::GROUP_TYPE])->order("sort asc")->column('id');
//            if (in_array($form['category_id'], $groupCategory)) {//拼团专区
//                if (empty($form['goods_group_num']) || $form['goods_group_num'] <= 1) {
//                    return $this->successJson(ERROR1, "拼团专区拼团人数不能为空或小于等于1", null);
//                }
//            }

            //$form['goods_equal_currency_id'] = $equalCurrencyId;
//            $form['goods_rebate_parent_id'] = $rebateCurrencyId;
//            $form['goods_rebate_self_id'] = $rebateCurrencyId;
            $form['goods_currency_give_id'] = $giveCurrencyId;
            $form['goods_banners'] = json_encode($form['goods_banners']);
            $form['goods_time'] = time();
            $form['admin_id'] = session('admin_id');
            $form['goods_status'] = 2;
            $result = \app\common\model\Goods::create($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
//        $category = GoodsCategory::where(['status' => 1])->field(['id', 'name'])->order('sort', 'asc')->select();
        $category = GoodsCategory::where(['status' => 1, 'pid' => 0])->field(['id', 'name'])->order('sort', 'asc')->select();
        foreach ($category as &$item) {
            $GoodsCategory = GoodsCategory::where(['status' => 1, 'pid' => $item['id']])->field(['id', 'name'])->order('sort', 'asc')->select();
            $item['list'] = $GoodsCategory;
            if (empty($GoodsCategory)) {
                $item['list'] = [['id' => $item['id'], 'name' => '全部']];
            }
        }
//            $equalCurrency = \app\common\model\Currency::get($equalCurrencyId);
//            $rebateCurrency = \app\common\model\Currency::get($rebateCurrencyId);
//            $giveCurrency = \app\common\model\Currency::get($giveCurrencyId);
        return $this->fetch(null, compact('category'
//            , 'equalCurrency', 'rebateCurrency', 'giveCurrency'
        ));

    }

    /**
     * 修改商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DbException
     */
    public function goods_update(Request $request)
    {
//        $equalCurrencyId = ShopConfig::get_value('equal_currency_id', 75);
//        $rebateCurrencyId = ShopConfig::get_value('rebate_currency_id', 74);
//        $giveCurrencyId = ShopConfig::get_value('give_currency_id', 76);
        if ($request->isPost()) {

            $form = $request->param('form/a');
            //$form['goods_equal_currency_id'] = $equalCurrencyId;
//            $form['goods_rebate_parent_id'] = $rebateCurrencyId;
//            $form['goods_rebate_self_id'] = $rebateCurrencyId;
//            $form['goods_give_currency_id'] = $giveCurrencyId;
//            $id = $request->param('id', 0, 'intval');
//            unset($form['id']);
            $info = \app\common\model\Goods::where(['goods_id' => $form['id']])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            unset($form['id']);
            if (empty($form['goods_title'])) return $this->successJson(ERROR1, "标题不能为空", null);
            if (empty($form['goods_img'])) return $this->successJson(ERROR1, "主图不能为空", null);
            if (empty($form['goods_banners'])) return $this->successJson(ERROR1, "轮播图不能为空", null);

//            $groupCategory = GoodsCategory::where(['status' => 1])->order("sort asc")->column('id');
//            if (in_array($form['category_id'], $groupCategory)) {//拼团专区
//                if (empty($form['goods_group_num']) || $form['goods_group_num'] <= 1) {
//                    return $this->successJson(ERROR1, "拼团专区拼团人数不能为空或小于等于1", null);
//                }
//            }

            $form['update_count'] = $form['update_count'] + 1;
            $form['update_time'] = time();

            $form['goods_banners'] = json_encode($form['goods_banners']);
            $result = \app\common\model\Goods::update($form, ['goods_id' => $info['goods_id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = \app\common\model\Goods::where(['goods_id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');

        $info['goods_banners'] = json_decode($info['goods_banners'], true);

        $category = GoodsCategory::where(['status' => 1, 'pid' => 0])->field(['id', 'name'])->order('sort', 'asc')->select();
        foreach ($category as &$item) {
            $GoodsCategory = GoodsCategory::where(['status' => 1, 'pid' => $item['id']])->field(['id', 'name'])->order('sort', 'asc')->select();
            $item['list'] = $GoodsCategory;
            if (empty($GoodsCategory)) {
                $item['list'] = [['id' => $item['id'], 'name' => '全部']];
            }
        }

//        $equalCurrency = \app\common\model\Currency::get($equalCurrencyId);
//        $rebateCurrency = \app\common\model\Currency::get($rebateCurrencyId);
//        $giveCurrency = \app\common\model\Currency::get($giveCurrencyId);
        return $this->fetch(null, compact('info', 'category'
//            , 'equalCurrency', 'rebateCurrency', 'giveCurrency'
        ));

    }

    /**
     * 删除商品
     * @param Request $request
     * @return array
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function goods_delete(Request $request)
    {

        $id = $request->param('id', 0, 'intval');
        $info = \app\common\model\Goods::where(['goods_id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');
        $result = \app\common\model\Goods::where(['goods_id' => $id])->delete();
        if ($result) {
            return $this->successJson(SUCCESS, "删除成功", ['url' => url('')]);
        }
        return $this->successJson(ERROR1, "删除失败", null);
    }

    /**
     * 商品详情
     * @return mixed
     * @throws DbException
     */
    public function goods_details(Request $request)
    {
        $id = $request->param('id');
        $info = \app\common\model\Goods::alias("g")
            ->join(config("database.prefix") . "goods_category c", "c.id=g.category_id", "LEFT")
            ->field(['g.*', 'c.name' => 'category_name'])->where(['goods_id' => $id])->find();
        if (empty($info)) $this->error('找不到相关数据');

        $info['goods_banners'] = json_decode($info['goods_banners'], true);
        $equalCurrencyId = ShopConfig::get_value('equal_currency_id', 75);
        $rebateCurrencyId = ShopConfig::get_value('rebate_currency_id', 74);
        $giveCurrencyId = ShopConfig::get_value('give_currency_id', 76);

        $equalCurrency = \app\common\model\Currency::get($equalCurrencyId);
        $rebateCurrency = \app\common\model\Currency::get($rebateCurrencyId);
        $giveCurrency = \app\common\model\Currency::get($giveCurrencyId);
        return $this->fetch(null, compact('info', 'equalCurrency', 'rebateCurrency', 'giveCurrency'));
    }

    /**
     * 商品规格列表
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function format_list(Request $request)
    {
        $where = [];
        $goods_id = $request->param('goods_id');
        if (!empty($goods_id)) {
            $where['goods_id'] = $goods_id;
        }

        $list = GoodsFormat::where($where)->order("status asc,sort desc")->paginate(null, null, ["query" => $request->get()]);
        $goodsInfo = \app\common\model\Goods::alias("g")
            ->join("goods_category c", "c.id=g.category_id", "LEFT")
            ->field(['g.*', 'c.name' => 'category_name'])->where(['goods_id' => $goods_id])->find();
        $page = $list->render();
        $count = $list->total();

        // 分类
        $category = GoodsCategory::where('status', 1)->field(['id', 'name'])->order('sort', 'asc')->select();
        $goodsList = \app\common\model\Goods::where('goods_status', 1)->field(['goods_id', 'goods_title'])->order('goods_sort', 'desc')->select();
        $statusList = ['1' => '可用', '2' => '删除'];
        return $this->fetch(null, compact('list', 'page', 'count', 'category', 'goodsList', 'statusList', 'goodsInfo'));
    }

    /**
     * 添加商品规格
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function format_add(Request $request)
    {
        $goods_id = $request->param('goods_id', 0, 'intval');
        $goodsInfo = \app\common\model\Goods::where(['goods_id' => $goods_id])->find();
        if (empty($goodsInfo)) $this->error('获取商品信息失败');
        $goodsCategory = GoodsCategory::get($goodsInfo['category_id']);
        if ($request->isPost()) {
            $form = $request->param('form/a');
            if (empty($form['name'])) return $this->successJson(ERROR1, "名称不能为空", null);
            if (empty($form['status'])) return $this->successJson(ERROR1, "请选择状态", null);

            if ($goodsCategory['pid'] == GoodsCategory::GROUP_TYPE) {//拼团专区
                return $this->successJson(ERROR1, "拼团专区暂不支持添加商品规格", null);
            }

            $form['goods_id'] = $goods_id;
            $form['add_time'] = time();
            $result = GoodsFormat::create($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $statusList = ['1' => '可用', '2' => '删除'];
        return $this->fetch(null, compact('goodsInfo', 'statusList'));
    }

    /**
     * 修改商品规格
     * @param Request $request
     * @return array|mixed
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function format_edit(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $info = GoodsFormat::where(['id' => $form['id']])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            if (empty($form['name'])) return $this->successJson(ERROR1, "名称不能为空", null);
            if (empty($form['status'])) return $this->successJson(ERROR1, "请选择状态", null);

            $result = GoodsFormat::update($form, ['id' => $form['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = GoodsFormat::where(['id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');

        $statusList = ['1' => '可用', '2' => '删除'];
        return $this->fetch(null, compact('info', 'statusList'));
    }

    /**
     * 删除商品规格
     * @param Request $request
     * @return array
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function format_del(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $info = GoodsFormat::where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $result = GoodsFormat::where(['id' => $id])->update(['status' => 2]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        return $this->successJson(ERROR1, "操作失败", null);
    }

    /**
     * 商品订单
     * @param Request $request
     * @return mixed
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function order_list(Request $request)
    {
        $gmo_code = $request->param('gmo_code', '');
        $where = [];

        $statusList = GoodsMainOrders::STATUS_ENUM;
        unset($statusList[6]);
        $payTypeList = ShopPayType::getPayTypeList();
        $daochu = $request->param('daochu');
        if ($daochu == 1) {
            ini_set("display_errors", 1);
            ini_set('memory_limit', '-1');
            if ($gmo_code) {
                $where['b.gmo_code'] = ['like', "%{$gmo_code}%"];
            }
            $gmo_status = $request->param('gmo_status', 0);
            if (!empty($gmo_status)) {
                $where['b.gmo_status'] = $gmo_status;
                $request->get(['gmo_status' => $gmo_status]);
            }
            $gmo_user_id = $request->param('gmo_user_id');
            if ($gmo_user_id) {
                $where['b.gmo_user_id'] = $gmo_user_id;
            }
            $where['b.gmo_express_type'] = ['neq', 3];
            $checkbox =  $request->param('checkboxid');
            if (!empty($checkbox)) {
                $explodeid = explode(',', $checkbox);
                $where['b.gmo_id'] = ['in', $explodeid];
            }

            $list = \app\common\model\GoodsOrders::alias('a')->where($where)
                ->join('goods_main_orders b', 'a.go_main_id=b.gmo_id')
                ->join('goods c', 'a.go_goods_id = c.goods_id')
                ->join("goods_format f", "f.id=a.go_format_id", "LEFT")
                ->field("a.*,b.*,c.*,f.name as goods_format,f.goods_market as format_price")
                ->group('a.go_id')
                ->order('a.go_add_time', 'desc')->select();

            $list1 = [];
            if (!empty($list)) {
                foreach ($list as $value) {
                    $address = '';
                    if (!empty($value['gmo_receive_name'])) {
                        $address = "收货人: {$value['gmo_receive_name']}, 手机号码: {$value['gmo_mobile']}, 地址: {$value['gmo_address']}";
                    }
                    $pickedup_address = '';
                    if (!empty($value['gmo_pickedup_name'])) {
                        $pickedup_address = "提货人: {$value['gmo_pickedup_name']}, 手机号码: {$value['gmo_pickedup_mobile']}, 自提点: {$value['gmo_pickedup_address']}";
                    }
                    if (!empty($value['format_price'])) {
                        $goods_purch = $value['format_price'];
                    } else {
                        $goods_purch = $value['goods_purch'];
                    }

                    $list1[] = [
                        'gmo_id' => $value['gmo_id'],
                        'gmo_user_id' => $value['gmo_user_id'],
                        'gmo_code' => "\t" . $value['gmo_code'] . "\t",
                        'gmo_pay_time' => !empty($value['gmo_pay_time']) ? date('Y-m-d H:i:s', $value['gmo_pay_time']) : 0,
                        'goods_title' => $value['goods_title'],
                        'goods_format' => $value['goods_format'],
                        'gmo_num' => $value['go_num'],
                        'goods_purch' => floattostr($goods_purch),
                        'gmo_total_price' => floattostr($value['go_total_price']),
                        'gmo_pay_num' => floattostr($value['go_total_price']),
                        'gmo_pay_type' => $payTypeList[$value['gmo_pay_type']],
                        'gmo_receive_name' => $address,
                        'gmo_pickedup_name' => $pickedup_address,
                        'gmo_status' => $statusList[$value['gmo_status']],
                    ];
                }
            }
            $xlsCell = array(
                array('gmo_id', '订单ID'),
                array('gmo_user_id', '用户ID'),
                //array('goods_detail', '商品详情'),
                array('gmo_code', '订单编号', 20),
                array('gmo_pay_time', '支付时间', 20),
                array('goods_title', '商品名称', 40),
                array('goods_format', '商品规格', 40),
                array('gmo_num', '商品总数'),
                array('goods_purch', '单个结算成本价'),
                array('gmo_total_price', '订单总价'),
                array('gmo_pay_num', '实付款'),
                array('gmo_pay_type', '支付方式', 12),
                array('gmo_receive_name', '发货信息', 100),
                array('gmo_pickedup_name', '自提信息', 100),
                array('gmo_status', '订单状态'),
                //array('goods_img', '商品图片'),
            );
            $this->exportExcel("订单列表", $xlsCell, $list1);
            die();
        }
        if ($gmo_code) {
            $where['a.gmo_code'] = ['like', "%{$gmo_code}%"];
        }
        $gmo_status = $request->param('gmo_status', 0);
        if (!empty($gmo_status)) {
            $where['a.gmo_status'] = $gmo_status;
            $request->get(['gmo_status' => $gmo_status]);
        }
        $gmo_user_id = $request->param('gmo_user_id');
        if ($gmo_user_id) {
            $where['a.gmo_user_id'] = $gmo_user_id;
        }
        $goods_code = $request->param('goods_code');
        if ($goods_code) {
            $where['c.goods_code'] = $goods_code;
        }
        $where['a.gmo_express_type'] = ['neq', 3];
        $list = GoodsMainOrders::alias('a')->where($where)
            ->field("a.*,b.*,c.*,f.name as goods_format")
            ->join('goods_orders b', 'b.go_main_id = a.gmo_id', 'LEFT')
            ->join('goods c', 'c.goods_id IN (b.go_goods_id)', 'LEFT')
            ->join("goods_format f", "f.id=b.go_format_id", "LEFT")
            ->order('a.gmo_add_time', 'desc')->paginate(null, null, ["query" => $request->get()]);

        if ($list) {
            foreach ($list as &$item) {
                if ($item['gmo_express_type'] == 1) {
                    $item['type_name'] = '物流';
                } elseif ($item['gmo_express_type'] == 2) {
                    $item['type_name'] = '自提';
                } elseif ($item['gmo_express_type'] == 3) {
                    $item['type_name'] = '虚拟（自提区）';
                } else {
                    $item['type_name'] = '其他';
                }

                // 一级分类
                $GoodsCategory = GoodsCategory::where(['id' => $item['category_id']])->find();
                if ($GoodsCategory['pid'] == 0) {
                    $category_name = $GoodsCategory['name'];
                } else {
                    $GoodsCategory = GoodsCategory::where(['id' => $GoodsCategory['pid']])->find();
                    $category_name = $GoodsCategory['name'];
                }
                $item['category_name'] = $category_name;

                if (!empty($item['gmo_pickedup_name']) && $item['gmo_express_type'] == 2) {
                    $item['gmo_receive_name'] = $item['gmo_pickedup_name'];
                    $item['gmo_mobile'] = $item['gmo_pickedup_mobile'];
                    $item['gmo_address'] = $item['gmo_pickedup_address'];
                }
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'statusList', 'payTypeList'));
    }

    /**
     * 订单详情
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function order_details(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->post('gmo_id');
            $form = [];
            $form['gmo_receive_name'] = $request->post('gmo_receive_name');
            $form['gmo_mobile'] = $request->post('gmo_mobile');
            $form['gmo_address'] = $request->post('gmo_address');
            $form['gmo_remark'] = $request->post('gmo_remark');
            $result = GoodsMainOrders::where(['gmo_id' => $id])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $gmo_id = $request->param('gmo_id');
        $info = GoodsMainOrders::with(['paytype', 'payCurrency', 'equalCurrency', 'rebateParentCurrency', 'rebateSelfCurrency', 'giveCurrency'])
            ->where(['gmo_id' => $gmo_id])->find();
        if (empty($info)) $this->error('该订单不存在');
        $info['gmo_status'] = GoodsMainOrders::STATUS_ENUM[$info['gmo_status']];
        $info['gmo_status_refund'] = GoodsMainOrders::REFUND_ENUM[$info['gmo_status_refund']];

        return $this->fetch(null, compact('info'));
    }

    /**
     * 子订单
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function order_child(Request $request)
    {
        $gmo_go_id = $request->param('gmo_id');
//        $explode = explode(',', $gmo_go_id);
//        $list = GoodsOrders::where(['go_id' => ['in', $explode]])
        $list = GoodsOrders::where(['go_main_id' => $gmo_go_id])
            ->order('go_add_time', 'desc')
            ->paginate(null, null, ["query" => $request->get()]);

        if (empty($list)) $this->error('找不到相关数据');
        foreach ($list as &$value) {
            $value['format_name'] = '';
            if ($value['go_format_id']) {
                $format = \app\common\model\GoodsFormat::where(['id' => $value['go_format_id']])->field('name,goods_img')->find();
                $value['format_name'] = !empty($format) ? $format['name'] : '';
                $value['go_img'] = !empty($format) ? $format['goods_img'] : '';
            }
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 确认发货
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function order_ship(Request $request)
    {
        if ($request->isPost()) {
            $saveData = $request->only(['gmo_id', 'gmo_status', 'gmo_user_id', 'form']);
            $saveData['form']['gmo_express_type'] = 1;
            // 配送方式 1物流 2自提 3无物流
            if ($saveData['form']['gmo_express_type'] == 2) {
                if ($saveData['gmo_status'] == 1) { // 1已付款
                    $result = GoodsMainOrders::confirm_order($saveData['gmo_user_id'], $saveData['gmo_id']);
                    if ($result['code'] != SUCCESS) {
                        return ['code' => SUCCESS, 'message' => ",商城订单处理异常:" . $result['message']];
                    }
                    return ['code' => SUCCESS, 'message' => '操作成功'];
                }
                return ['code' => ERROR1, 'message' => '此订单为付款，操作失败'];
            }

            if (empty($saveData['form']['gmo_express_company'])) {
                return ['code' => ERROR1, 'message' => '物流公司不能为空'];
            }
            if (empty($saveData['form']['gmo_express_code'])) {
                return ['code' => ERROR1, 'message' => '物流单号不能为空'];
            }

            $expressFind = ExpressList::where('code', $saveData['form']['gmo_express_company'])->where('status', 1)->find();
            if (!$expressFind) return ['code' => ERROR1, 'message' => '填写物流公司不存在或者暂不支持'];
            $saveData['form']['gmo_express_name'] = $expressFind['name'];
            $result = GoodsMainOrders::addLogistics($expressFind['code'], $expressFind['name'], $saveData['form']['gmo_express_code']);
            if ($result['code'] != SUCCESS) return ['code' => ERROR1, 'message' => $result['message']];
            //if ($saveData['gmo_status'] == 1) { // 确认发货
                $saveData['form']['gmo_ship_time'] = time(); // 发货时间
                $autoSureTime = ShopConfig::get_value('orders_auto_sure_time', 4320);
                $saveData['form']['gmo_auto_sure_time'] = time() + ($autoSureTime * 60); // 自动确认收货时间
                if ($saveData['gmo_status'] != GoodsMainOrders::REFUND_STATUS_REFUSE) {
                    $saveData['form']['gmo_status'] = 3; // 3已发货
                }
            //}
            unset($saveData['form']['gmo_express_type']);
            $isUpdate = GoodsMainOrders::where('gmo_id', $saveData['gmo_id'])
                ->update($saveData['form']);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '操作成功'];
            }
            return ['code' => ERROR1, 'message' => '操作失败'];
        }

        $gmo_id = $request->param('gmo_id', 0);
        $gmo_status = $request->param('gmo_status', 0);
        $info = GoodsMainOrders::where(['gmo_id' => $gmo_id, 'gmo_status' => $gmo_status])->find();
        if (empty($info)) $this->error('找不到相关数据');

        $expressList = ShopLogisticsList::getExpressList();

        return $this->fetch(null, compact('info', 'expressList'));
    }

    /**
     * 物流详情
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function order_logistics(Request $request)
    {
        $gmo_id = $request->param('gmo_id', 0);
        $info = GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
        if (empty($info)) $this->error('找不到相关数据');

        $logistics = ShopLogisticsList::where(['number' => $info['gmo_express_code']])->find();

        //var_dump(json_decode($logistics['last_result'], true));
        $logisticsInfo = json_decode($logistics['last_result'], true);
        //var_dump($logisticsInfo);
        $statusList = [
            '在途' => '运输中', '揽收' => '已揽件', '派件' => '派送中', '签收' => '已签收', '退签' => '已退签', '退回' => '退回中',
        ];

        return $this->fetch(null, compact('info', 'logisticsInfo', 'statusList'));
    }

    // 自提点管理列表
    public function pickedup_list(Request $request)
    {
        $title = input('title');
        $province = input('sa_province');
        $city = input('city');
        $where = [];
        if ($title) {
            $where['sa_name'] = $title;
        }
        if ($province) {
            $where['sa_province'] = $province;
        }
        if ($city) {
            $where['sa_city'] = $city;
        }
        $list = \app\common\model\ShopPickedUp::order('sa_add_time', 'desc')
            ->where($where)
            ->paginate(null, null, ["query" => $request->get()]);

        if (empty($list)) $this->error('找不到相关数据');

        $page = $list->render();
        $count = $list->total();
        $province = Db::name('areas')->where(['parent_id' => 0])->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'province'));
    }

    // 添加自提点
    public function pickedup_add(Request $request)
    {
        if ($request->isPost()) {
            $form['sa_name'] = $request->post('sa_name');
            $form['sa_mobile'] = $request->post('sa_mobile');
            $form['sa_address'] = $request->post('sa_address');
            $form['sa_default'] = $request->post('sa_default');
            $form['sa_province'] = $request->post('sa_province');
            $form['sa_city'] = $request->post('sa_city');
            $form['sa_add_time'] = time();
            $result = \app\common\model\ShopPickedUp::insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        $province = Db::name('areas')->where(['parent_id' => 0])->select();

        return $this->fetch(null, compact('province'));
    }

    //获取城市
    public function getArea(Request $request)
    {
        $pid = $request->post('pid');
        $list = Db::name('areas')->where(['parent_id' => $pid])->select();
        return $this->successJson(SUCCESS, "获取成功", $list);
    }

    //编辑自提点
    public function pickedup_edit(Request $request)
    {
        if ($request->isPost()) {
            $id = input('id');
            $form['sa_name'] = $request->post('sa_name');
            $form['sa_mobile'] = $request->post('sa_mobile');
            $form['sa_address'] = $request->post('sa_address');
            $form['sa_default'] = $request->post('sa_default');
            $form['sa_province'] = $request->post('sa_province');
            $form['sa_city'] = $request->post('sa_city');
            $result = \app\common\model\ShopPickedUp::where(['sa_id' => $id])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        $id = input('id');
        $info = \app\common\model\ShopPickedUp::where(['sa_id' => $id])->find();
        $province = Db::name('areas')->where(['parent_id' => 0])->select();
        $city = Db::name('areas')->where(['parent_id' => $info['sa_province']])->select();

        return $this->fetch(null, compact('info', 'province', 'city'));
    }

    //同步商品（聚水潭商品）
    public function synchro_goods(Request $request)
    {
        if ($request->isPost()) {
            $sku_ids = input('sku_id');

            $OrderApi = new \order\OrderApi();
            $params = [
                'sku_ids' => $sku_ids,//商品编码
            ];
            $result = $OrderApi->goods_query($params);//查询商品
            if (!empty($result) && $result['code'] === 0) {
                $goods = $result['datas'];
                if ($goods) {
                    foreach ($goods as $key => $value) {
                        $check = Db::name('goods')->where(['sku_id' => $value['sku_id']])->find();
                        $data = [
                            'goods_title' => $value['name'],//商品名称
                            'goods_time' => strtotime($value['created']),//创建时间
                            'update_time' => time(),//修改时间
                            'i_id' => $value['i_id'],//款式编码
                            'sku_id' => $value['sku_id'],//商品编码
                        ];
                        if (!empty($value['pic'])) {
                            $data['goods_img'] = $value['pic'];//图片
                        }
                        if ($value['sale_price'] > 0) {
                            $data['goods_price'] = $value['sale_price'];//市场价
                            $data['goods_market'] = $value['sale_price'];//折扣价
                        }
                        if ($check) {
                            $flag = Db::name('goods')->where(['sku_id' => $value['sku_id']])->update($data);
                        } else {
                            $data['goods_status'] = 0;//状态：下架（新增的商品需完善信息后才能上架）
                            $data['goods_content'] = '暂无';//商品详情
                            $data['goods_ship'] = '广东';//发货地
                            $data['category_id'] = 2;//分类ID
                            $data['is_upload'] = 1;
                            $data['goods_rebate_parent_id'] = $data['goods_rebate_self_id'] = $data['goods_give_currency_id'] = 75;
                            $flag = Db::name('goods')->insertGetId($data);
                        }
                    }
                }
                return $this->successJson(SUCCESS, "同步成功", ['url' => url('')]);
            } else {
                return $this->successJson(ERROR1, "同步失败，请检查商品编号是否正确", null);
            }
        }
        return $this->fetch(null);
    }

    //刷新聚水潭token
    public function refresh_token()
    {
        $OrderApi = new \order\OrderApi();
        $params = null;
        $result = $OrderApi->refresh_token($params);//刷新token
        if (!empty($result) && $result['code'] === 0) {
            return $this->successJson(SUCCESS, "刷新token成功", ['url' => url('')]);
        } else {
            return $this->successJson(ERROR1, "刷新token失败", null);
        }
    }

    //修改上传聚水潭
    public function order_upload(Request $request)
    {
        $gmo_id = input('gmo_id');
        $status = $request->param('status', 0, 'intval');
        if (!in_array($status, [0, 1, 2])) return $this->successJson(ERROR2, "非法操作", null);

        try {
            $info = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");

            $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->setField('is_upload', $status);
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    //商品分类
    public function category_list(Request $request)
    {
        $title = $request->param('title');
        $where = [];
        if ($title) {
            $where['name'] = ['like', '%' . $title . '%'];
        }
        $list = GoodsCategory::order('add_time', 'desc')
            ->where($where)
            ->order('sort asc,id asc')
            ->paginate(null, null, ["query" => $request->get()]);

        foreach ($list as &$item) {
            $item['pname'] = '顶级分类';
            if ($item['pid'] > 0) {
                $GoodsCategory = GoodsCategory::where(['id' => $item['pid']])->find();
                $item['pname'] = $GoodsCategory['name'];
            }
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch('category_list', compact('list', 'page', 'count'));
    }

    //商品分类-添加
    public function category_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            if ($form['name']) {
                $check = GoodsCategory::where(['name' => $form['name']])->find();
                if ($check) {
                    return $this->successJson(ERROR1, "商品分类已存在", null);
                }
            }
            $form['add_time'] = time();
            $result = GoodsCategory::insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);

        }
        $category = GoodsCategory::where(['pid' => 0])->select();
        return $this->fetch(null, compact('category'));
    }

    // 商品分类-编辑
    public function category_edit(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $id = $request->param('id', 0, 'intval');
            $info = GoodsCategory::where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $result = GoodsCategory::where(['id' => $info['id']])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = GoodsCategory::where(['id' => $id])->find();
        $category = GoodsCategory::where(['pid' => 0])->select();
        return $this->fetch(null, compact('info', 'category'));
    }

    //商品分类-更新状态
    public function category_quick_switch(Request $request)
    {
        $id = $request->param('id');
        $status = $request->param('status');
        $result = GoodsCategory::where(['id' => $id])->update(['status' => $status]);
        if ($result === false) {
            return $this->successJson(ERROR1, "更新失败", null);
        }
        return $this->successJson(SUCCESS, "更新成功", ['url' => url('')]);
    }

    //门店管理
    public function store_list(Request $request)
    {
        $title = input('title');
        $where = [];
        if ($title) {
            $where['name'] = ['like', '%' . $title . '%'];
        }
        $list = Db::name('shop_store')->order('add_time', 'desc')
            ->where($where)
            ->paginate(null, null, ["query" => $request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch('store_list', compact('list', 'page', 'count'));
    }

    //门店管理-添加
    public function store_add(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            if ($form['name']) {
                $check = Db::name('shop_store')->where(['name' => $form['name']])->find();
                if ($check) {
                    return $this->successJson(ERROR1, "门店名称已存在", null);
                }
            }
            $form['add_time'] = time();
            $result = Db::name('shop_store')->insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        return $this->fetch(null);
    }

    //门店管理-编辑
    public function store_edit(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            $id = intval($form['id']);
            unset($form['id']);
            $info = Db::name('shop_store')->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $result = Db::name('shop_store')->where(['id' => $info['id']])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = Db::name('shop_store')->where(['id' => $id])->find();

            return $this->fetch(null, compact('info'));
        }
    }

    //门店管理-更新状态
    public function store_quick_switch(Request $request)
    {
        $id = input('id');
        $status = input('status');
        $result = Db::name('shop_store')->where(['id' => $id])->update(['status' => $status]);
        if ($result === false) {
            return $this->successJson(ERROR1, "更新失败", null);
        } else {
            return $this->successJson(SUCCESS, "更新成功", ['url' => url('')]);
        }
    }

    //订单管理-核销到店
    public function store_approve(Request $request)
    {
        $id = input('gmo_id');
        $check = GoodsMainOrders::where(['gmo_id' => $id, 'gmo_status' => 6, 'store_use' => 0])->find();
        if (!$check) {
            return $this->successJson(ERROR1, "核销失败，该订单不能核销", null);
        }

        $result = Db::name('order_store')->where(['order_id' => $id, 'status' => 0])->update(['status' => 1]);
        if ($result === false) {
            return $this->successJson(ERROR1, "核销失败", null);
        } else {
            $flag = GoodsMainOrders::where(['gmo_id' => $id])->update(['gmo_status' => 4, 'store_use' => 1]);
            return $this->successJson(SUCCESS, "核销成功", ['url' => url('')]);
        }
    }

    //订单管理-预约记录
    public function store_log(Request $request)
    {
        $id = input('gmo_id');
        $list = Db::name('order_store')->where(['order_id' => $id])->order('id desc')->paginate(null, null, ["query" => $request->get()]);;

        $page = $list->render();
        $count = $list->total();
        return $this->fetch('store_log', compact('list', 'page', 'count'));
    }

    //确认自提
    public function picked_up()
    {
        $id = input('gmo_id');
        $check = GoodsMainOrders::where(['gmo_id' => $id, 'gmo_status' => 6])->find();
        if (!$check) {
            return $this->successJson(ERROR1, "确认自提失败，该订单不能已自提", null);
        }

        $result = GoodsMainOrders::where(['gmo_id' => $id])->update(['gmo_status' => 4]);
        if ($result === false) {
            return $this->successJson(ERROR1, "确认自提失败", null);
        } else {
            return $this->successJson(SUCCESS, "确认自提成功", ['url' => url('')]);
        }
    }

    //订单管理-预约记录
    public function subscribe_log(Request $request)
    {
        $member_id = input('member_id');
        $subscribe_code = input('subscribe_code');
        $store = input('store');
        $where = [];
        if ($member_id) {
            $where['a.member_id'] = $member_id;
        }
        if ($subscribe_code) {
            $where['b.subscribe_code'] = $subscribe_code;
        }
        if ($store) {
            $where['a.store_name'] = $store;
        }
        $where['status'] = 0;
        $list = Db::name('order_store')->alias('a')
            ->join('goods_main_orders b', 'a.order_id=b.gmo_id')
            ->join('goods_orders c', 'c.go_id in(b.gmo_go_id)')
            ->field('a.*,c.go_title')
            ->where($where)->order('a.id desc')
            ->paginate(null, null, ["query" => $request->get()]);

        $page = $list->render();
        $count = $list->total();
        $storeList = Db::name('shop_store')->field('id,name')->select();
        return $this->fetch('subscribe_log', compact('list', 'page', 'count', 'storeList'));
    }

    /**
     * 更新星链商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function upload_goods()
    {
        if ($this->request->isPost()) {
            $spuIds = input('spuIds');
            if (empty($spuIds)) {
                return $this->successJson(ERROR1, "操作失败:请输入星链商品编号", null);
            }
            ini_set("display_errors", 1);
            ini_set('memory_limit', '-1');
            $params['spuIdList'] = explode(',', preg_replace("/(\n)|(\s)|(\t)|(\')|(')|(，)/", ',', $spuIds));
            $OrderApi = new \xingl\OrderApi();
            $result = $OrderApi->getSpuBySpuIds($params);
            if (empty($result)) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            if (!empty($result) && $result['status'] != 200) {
                return $this->successJson(ERROR1, "操作失败:" . $result['msg'], null);
            }
//            $result = '{"data":[{"spuId":"1410800728046895105","name":"\u3010\u6d4b\u8bd5\u5546\u54c1\u3011\u6d4b\u8bd5\u591a\u89c4\u683c\u5546\u54c1\u3001\u5305\u90ae\u3001\u8d77\u552e\u6570\u91cf","propertys":[{"propertyName":"\u529f\u80fd","checkValue":["1395309170814619650"],"items":[{"propertyValueName":"\u53ef\u8c03\u538b\u529b","propertyValueId":"1395309170814619650"}],"propertyId":"1395309170797842433"},{"propertyName":"\u52a0\u70ed\u65b9\u5f0f","checkValue":["1395309170877534210"],"items":[{"propertyValueName":"\u5e95\u76d8\u52a0\u70ed","propertyValueId":"1395309170877534210"}],"propertyId":"1395309170860756994"},{"propertyName":"\u64cd\u63a7\u65b9\u5f0f","checkValue":["1395309170906894338"],"items":[{"propertyValueName":"\u673a\u68b0\u5f0f","propertyValueId":"1395309170906894338"}],"propertyId":"1395309170890117122"},{"propertyName":"\u5bb9\u91cf","checkValue":["1395309170932060162"],"items":[{"propertyValueName":"6L\u4ee5\u4e0a","propertyValueId":"1395309170932060162"}],"propertyId":"1395309170919477249"},{"propertyName":"\u9002\u7528\u4eba\u6570","checkValue":["1395309170961420289"],"items":[{"propertyValueName":"1-3\u4eba","propertyValueId":"1395309170961420289"}],"propertyId":"1395309170948837377"},{"propertyName":"\u5185\u80c6\u6570\u91cf","checkValue":["1395309170994974721"],"items":[{"propertyValueName":"\u53cc\u80c6","propertyValueId":"1395309170994974721"}],"propertyId":"1395309170982391810"},{"propertyName":"\u6392\u538b\u65b9\u5f0f","checkValue":["1395309171015946242"],"items":[{"propertyValueName":"\u81ea\u52a8\u6392\u538b","propertyValueId":"1395309171015946242"}],"propertyId":"1395309171003363330"}],"video":"https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/46b03903b06a4d2d9b50fb61c511e222.mp4","carouselImgList":["https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/ecfa9b1f6d25409eb54aef319aa5217f.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/2d684ab0eeda412faf76260e78b7b0ae.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/05ced09afd7f44fc947c78018ded2331.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/f1f961e0d08246b89973d279982a5d66.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/5b8e395848244631b04e0241176d0cc7.jpg"],"detailImgList":["https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/ac539a02e0bb4277b6ec0cd3bf15fba1.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/7f05657786244ac7a073259ec2c36233.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/d246d42488b94b4b92ed62581bbc7045.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/5888852e876749b4abdc73e778d86127.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/a4ef51f670f24325a213afa226dfc85e.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/da14011c65d44b1ba19af617f65b81bd.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/434de9a351a04a80b7d8c4811244138b.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/1efa77614dc34a78818a921dabf1fe67.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/2b5e0facf99b4ff38943df020bc37ea1.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/713b33793a0546f89dce74221c865559.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/89eb3856e9404b7891b809382ffbad4f.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/ed13850fa4904bbda888f90f57aacf01.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/ff369e26472948898ddf92ece28a2858.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/f6bb97408d2b4bceb93807eb9ccb183d.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/60c8f4a7aa974210a98ac0b3be384679.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/0fc5ce3f2c9943e485cb51754c9894c6.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/31172cb9b7e848968f69ee2b067431fc.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/56d816ce78634e2eb671c12cfaa19335.jpg","https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/938fc20b548540d48cd2fb9f01d30af3.jpg"],"isLimitArea":"N","limitAreaType":0,"limitArea":[],"isChina":"Y","brandId":"1410794983020138497","categoryId1":1395308760095789058,"categoryId2":1395308762381684738,"categoryId3":1395308762734006274,"spuType":"1","npartno":"3425235345","volume":null,"weight":null,"status":"0","description":null}],"success":true,"msg":null,"status":200}';
//            $result = json_decode($result, true);
            $goods = $result['data'];
            foreach ($goods as $key => $value) {
                if (empty($value['spuId'])) {
                    continue;
                }
                $content = '';
                $img = '';
                $banners = [];
                if (!empty($value['carouselImgList'])) {
                    $img = $value['carouselImgList'][0];
                    $banners = json_encode($value['carouselImgList']);
                }
                if (!empty($value['detailImgList'])) {
                    foreach ($value['detailImgList'] as $k => $v) {
                        $content .= '<img src="' . $v . '" alt="" />';
                    }
                }
                $giveCurrencyId = ShopConfig::get_value('give_currency_id', 5);
                $data = [
                    'spu_id' => $value['spuId'],
                    'goods_type' => 2,
                    'goods_title' => $value['name'],
                    'goods_content' => $content,
                    'goods_img' => $img,
                    'goods_banners' => $banners,
                    'limitAreaType' => $value['limitAreaType'],//限制类型 0 : 可销售地区，1: 不可销售地区
                    'isLimitArea' => $value['isLimitArea'],//是否限制地区 1是 0否
                    'limitArea' => !empty($value['limitArea']) ? implode(',', $value['limitArea']) : ''//限制地区
                ];
                $check = \app\common\model\Goods::where(['spu_id' => $value['spuId']])->find();
                if (!$check) {
                    $data['goods_price'] = 0;
                    $data['goods_market'] = 0;
                    $data['goods_stock'] = 0;
                    $data['goods_status'] = 2;
                    $data['goods_time'] = time();
                    $data['goods_currency_give_id'] = $giveCurrencyId;
                    $goods_id = \app\common\model\Goods::insertGetId($data);
                    if (false === $goods_id) {
                        return $this->successJson(ERROR1, "添加商品失败：" . $value['spuId'], null);
                    }
                } else {
                    $u_result = \app\common\model\Goods::where(['goods_id' => $check['goods_id']])->update($data);
                    if (false === $u_result) {
                        return $this->successJson(ERROR1, "更新商品失败：" . $value['spuId'], null);
                    }
                    $goods_id = $check['goods_id'];
                }

                //更新商品规格
                if ($goods_id) {
                    $params = ['spuId' => $value['spuId']];
                    $sk_result = $OrderApi->listSkuBySpuId($params);
                    if (empty($sk_result)) {
                        return $this->successJson(ERROR1, "操作失败", null);
                    }
                    if (!empty($sk_result) && $sk_result['status'] != 200) {
                        return $this->successJson(ERROR1, "操作失败:" . $sk_result['msg'], null);
                    }
//                    $sk_result = '{"data":[{"skuId":"1448553366762881026","skuPicUrl":"https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/0d42af6c52ca4ab18bc6e2cd9138b180.png","status":"0","buyStartQty":1,"stock":"110874","basePrice":"0.02","suggestPrice":"0.02","skuPropertyList":[{"specName":"\u5546\u54c1\u7f16\u53f7","specValueName":"QC-YL522D"}]},{"skuId":"1448553366762881027","skuPicUrl":"https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/c4563f111d284122bbffb3e62c8a30f4.png","status":"0","buyStartQty":2,"stock":"111065","basePrice":"0.03","suggestPrice":"0.03","skuPropertyList":[{"specName":"\u5546\u54c1\u7f16\u53f7","specValueName":"QC-YL623"}]},{"skuId":"1448553366762881028","skuPicUrl":"https:\/\/scce-cos-prd.obs.cn-south-1.myhuaweicloud.com:443\/3d8d50c93b48434f8005d0062ab519fa.png","status":"0","buyStartQty":3,"stock":"111078","basePrice":"0.04","suggestPrice":"0.04","skuPropertyList":[{"specName":"\u5546\u54c1\u7f16\u53f7","specValueName":"QC-YL624"}]}],"success":true,"msg":null,"status":200}';
//                    $sk_result = json_decode($sk_result, true);
                    $format = $sk_result['data'];
                    if ($format) {
                        $price = 0;
                        $base_price = 0;
                        $stock = 0;
                        foreach ($format as $fk => $fv) {
                            $name = '';
                            $status = 1;
                            if ($fv['skuPropertyList']) {
                                $skuPropertyList = $fv['skuPropertyList'];
                                foreach ($skuPropertyList as $sk => $sv) {
                                    $name = $name . $sv['specValueName'] . ';';
                                }
                            }
                            if ($fv['status'] == 1) {
                                $status = 2;
                            }
                            if ($fk == 0) {
                                $price = $fv['suggestPrice'];
                                $base_price = $fv['basePrice'];
                                $stock = $fv['stock'];
                            }
                            $sk_data = [
                                'name' => $name,
                                'goods_retail' => $fv['suggestPrice'],
                                'goods_market' => $fv['basePrice'],
                                'goods_stock' => $fv['stock'],
                                'goods_price' => $fv['suggestPrice'],
                                'goods_img' => $fv['skuPicUrl'],
                                'status' => $status
                            ];
                            $f_check = \app\common\model\GoodsFormat::where(['sku_id' => $fv['skuId']])->find();
                            if (!$f_check) {
                                $sk_data['goods_id'] = $goods_id;
                                $sk_data['sku_id'] = $fv['skuId'];
                                $sk_data['add_time'] = time();
                                $f_result = \app\common\model\GoodsFormat::insertGetId($sk_data);
                                if (false === $f_result) {
                                    return $this->successJson(ERROR1, "添加商品规格失败：" . $fv['skuId'], null);
                                }
                            } else {
                                $f_result = \app\common\model\GoodsFormat::where(['id' => $f_check['id']])->update($sk_data);
                                if (false === $f_result) {
                                    return $this->successJson(ERROR1, "更新商品规格失败：" . $fv['skuId'], null);
                                }
                            }
                        }
                        //更新商品价格
                        if ($price > 0) {
                            $reward_hm_rate = \app\common\model\ShopConfig::get_value('reward_hm_rate');
                            $market_price_rate = \app\common\model\ShopConfig::get_value('market_price_rate');
                            $hm_price = \app\common\model\ShopConfig::get_value('hm_price');
                            $market = sprintf('%.2f', $price * ($market_price_rate / 100));
                            $give_num = sprintf('%.2f', (($price - $base_price) * ($reward_hm_rate / 100)) / $hm_price);
                            $u_data = [
                                'goods_price' => $price,
                                'goods_market' => $market,
                                'goods_currency_give_num' => $give_num,
                                'goods_stock' => $stock
                            ];
                            $flag = \app\common\model\Goods::where(['goods_id' => $goods_id])->update($u_data);
                            if (false === $flag) {
                                return $this->successJson(ERROR1, "更新商品价格失败：" . $goods_id, null);
                            }
                        }
                    }
                }
            }
            return $this->successJson(SUCCESS, "同步成功", ['url' => url('')]);

        } else {
            return $this->fetch(null);
        }
    }

    /**
     * 更新赠送火米
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function update_reward(Request $request)
    {
        if ($this->request->isPost()) {
            $rate = input('reward_hm_rate');
            if (empty($rate)) {
                return $this->successJson(ERROR1, "操作失败:请输入火米赠送比例", null);
            }
            ini_set("display_errors", 1);
            ini_set('memory_limit', '-1');
            $flag = \app\common\model\ShopConfig::where(['sc_key' => 'reward_hm_rate'])->update(['sc_value' => $rate]);
            if ($flag === false) {
                return $this->successJson(ERROR1, "更新火米赠送比例失败", null);
            }
            $hm_price = \app\common\model\ShopConfig::get_value('hm_price');
            $pid = 3;//排除兑换区商品
            $category_id = \app\common\model\GoodsCategory::where(['pid' => $pid])->column('id');
            $res = \app\common\model\Goods::where(['goods_type' => 2])->whereNotIn('category_id', $category_id)->select();
            if ($res) {
                foreach ($res as $key => $value) {
                    //goods_currency_give_num
                    $market = \app\common\model\GoodsFormat::where(['goods_id' => $value['goods_id'], 'status' => 1])->order('id asc')->value('goods_market');
                    if ($value['goods_price'] > 0 && $market > 0) {
                        $give_num = sprintf('%.2f', (($value['goods_price'] - $market) * ($rate / 100)) / $hm_price);
                        if ($give_num >= 0) {
                            $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_currency_give_num' => $give_num]);
                        }
                    }
                }
            }

            return $this->successJson(SUCCESS, "更新成功", ['url' => url('')]);
        } else {
            $reward_hm_rate = \app\common\model\ShopConfig::get_value('reward_hm_rate');
            return $this->fetch(null, compact('reward_hm_rate'));
        }
    }

    /**
     * 更新置换价格
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function update_price(Request $request)
    {
        if ($this->request->isPost()) {
            $rate = input('jm_price_rate');
            if (empty($rate)) {
                return $this->successJson(ERROR1, "操作失败:请输入置换区价格优惠比例", null);
            }

            $flag = \app\common\model\ShopConfig::where(['sc_key' => 'jm_price_rate'])->update(['sc_value' => $rate]);
            if ($flag === false) {
                return $this->successJson(ERROR1, "更新置换区价格优惠比例失败", null);
            }
            $ids = \app\common\model\GoodsCategory::where(['pid' => 3])->column('id');
            $res = \app\common\model\Goods::where(['goods_type' => 2, 'category_id' => ['in', $ids]])->select();
            if ($res) {
                foreach ($res as $key => $value) {
                    //goods_currency_give_num
                    $format = \app\common\model\GoodsFormat::where(['goods_id' => $value['goods_id'], 'status' => 1])->order('id asc')->field('goods_market,goods_price')->find();
                    if (!empty($format)) {
                        $num = sprintf('%.2f', $format['goods_price'] - (($format['goods_price'] - $format['goods_market']) * ($rate / 100)));
                        if ($num >= 0) {
                            $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_price' => $num]);
                        }
                    }
                    $info = \app\common\model\GoodsFormat::where(['goods_id' => $value['goods_id']])->select();
                    if ($info) {
                        foreach ($info as $k => $v) {
                            $price = sprintf('%.2f', $v['goods_retail'] - (($v['goods_retail'] - $v['goods_market']) * ($rate / 100)));
                            if ($price >= 0) {
                                $flag = \app\common\model\GoodsFormat::where(['id' => $v['id']])->update(['goods_price' => $price]);
                            }
                        }
                    }
                }
            }

            return $this->successJson(SUCCESS, "更新成功", ['url' => url('')]);
        } else {
            $jm_price_rate = \app\common\model\ShopConfig::get_value('jm_price_rate');
            return $this->fetch(null, compact('jm_price_rate'));
        }
    }

    // 重新下单星链
    public function upload_again(Request $request)
    {
        $gmo_id = input('gmo_id');
        $res = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
        if (!$res) {
            return $this->successJson(ERROR1, "查询不到该订单", null);
        }

        //判断是否已上传星链
        $info = \app\common\model\GoodsXingl::where(['gmo_id' => $gmo_id])->find();
        if ($info) {
            return $this->successJson(ERROR1, "星链已下单，不能重新上传", null);
        } else {
            $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->update(['is_upload' => 1]);
            if ($flag === false) {
                return $this->successJson(ERROR1, "更新上传状态失败", null);
            }
        }
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        $OrderApi = new \xingl\OrderApi();
        $username = \app\common\model\Member::where(['member_id' => $res['gmo_user_id']])->value('ename');
        $str = $res['gmo_address'];//收货地址
        $state_arr = Db::name('areas')->where(['area_type' => 1])->column('area_name');//省数组
        $city_arr = Db::name('areas')->where(['area_type' => 2])->column('area_name');//市数组
        $district_arr = Db::name('areas')->where(['area_type' => 3])->column('area_name');//区数组
        $receiver_state = $OrderApi->get_address($state_arr, $str);//省
        $receiver_city = $OrderApi->get_address($city_arr, $str);//市
        $receiver_district = $OrderApi->get_address($district_arr, $str);//区、城镇
        $address_name = mb_substr($str, mb_strlen($receiver_state . $receiver_city . $receiver_district));//详细地址
        $state_code = Db::name('areas')->where(['area_type' => 1, 'area_name' => $receiver_state])->value('area_id');//省code
        $city_code = Db::name('areas')->where(['area_type' => 2, 'area_name' => $receiver_city])->value('area_id');//市code
        $district_code = Db::name('areas')->where(['area_type' => 3, 'area_name' => $receiver_district])->value('area_id');//区code
        $order_arr = \app\common\model\GoodsOrders::alias('a')->join('goods_format b', 'a.go_format_id=b.id')->where(['a.go_main_id' => $res['gmo_id']])->field('a.go_num as goodsQty,b.sku_id as skuId')->select();

        $params = [
            'orderSource' => 1,
            'goodsList' => $order_arr,
            'buyerName' => $username,
            'shipArea' => $receiver_state . ',' . $receiver_city . ',' . $receiver_district,
            'shipName' => $res['gmo_receive_name'],
            'shipAddress' => $address_name,
            'shipMobile' => $res['gmo_mobile'],
            'outOrderSn' => $res['gmo_code'],
            'shipAreaCode' => $state_code . ',' . $city_code . ',' . $district_code,
        ];
        \think\Log::write(json_encode($params));
        $result = $OrderApi->submitOrder($params);
        \think\Log::write(json_encode($result));
        if (empty($result)) {
            \think\Log::write('星链自动下单失败');
            return $this->successJson(ERROR1, "星链自动下单失败", null);
        }
        if (!empty($result) && $result['status'] != 200) {
            \think\Log::write('星链自动下单失败：' . $result['msg']);
            return $this->successJson(ERROR1, '星链自动下单失败：' . $result['msg'], null);
        }
        if (!empty($result['data']['orderList'])) {
            $orderList = $result['data']['orderList'];
            foreach ($orderList as $k => $v) {
                $data = [
                    'member_id' => $res['gmo_user_id'],
                    'gmo_id' => $res['gmo_id'],
                    'totalAmount' => $v['totalAmount'],
                    'payAmount' => $v['payAmount'],
                    'orderSn' => $v['orderSn'],
                    'batchOrderSn' => $v['batchOrderSn'],
                    'orderStatus' => $v['orderStatus'],
                    'add_time' => time()
                ];

                $flag = \app\common\model\GoodsXingl::insertGetId($data);
                if ($flag === false) {
                    \think\Log::write('添加星链下单记录失败：' . $res['gmo_id']);
                }
            }
        }

        $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $res['gmo_id'], 'is_upload' => 1])->update(['is_upload' => 2]);
        if ($flag === false) {
            \think\Log::write('更新星链上传状态失败：' . $res['gmo_id']);
        }
        if ($result === false) {
            return $this->successJson(ERROR1, "重新下单星链失败", null);
        } else {
            return $this->successJson(SUCCESS, "重新下单星链成功", ['url' => url('')]);
        }
    }

    /**
     * 待发货订单列表
     * @param Request $request
     * @return mixed
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function deliver_order_list(Request $request)
    {
        $gmo_code = $request->param('gmo_code', '');
        $where = [];
        if ($gmo_code) {
            $where['a.gmo_code'] = ['like', "%{$gmo_code}%"];
        }
        $gmo_user_id = $request->param('gmo_user_id');
        if ($gmo_user_id) {
            $where['a.gmo_user_id'] = $gmo_user_id;
        }
        $where['a.gmo_status'] = 1;//['in', [1,3]];
        $where['a.is_upload'] = ['gt', 0];
        $statusList = GoodsMainOrders::STATUS_ENUM;
        $payTypeList = ShopPayType::getPayTypeList();
        $list = GoodsMainOrders::alias('a')->where($where)
            ->field("a.*,b.*,c.*,f.name as goods_format")
            ->join('goods_orders b', 'b.go_main_id = a.gmo_id', 'LEFT')
            ->join('goods c', 'c.goods_id IN (b.go_goods_id)', 'LEFT')
            ->join("goods_format f", "f.id=b.go_format_id", "LEFT")
            ->order('a.gmo_add_time', 'desc')->paginate(null, null, ["query" => $request->get()]);

        if ($list) {
            foreach ($list as &$item) {
                if ($item['gmo_express_type'] == 1) {
                    $item['type_name'] = '物流';
                } elseif ($item['gmo_express_type'] == 2) {
                    $item['type_name'] = '自提';
                } else {
                    $item['type_name'] = '虚拟';
                }
            }
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'statusList', 'payTypeList'));
    }

    /**
     * 更新自提区赠送火米
     */
    public function pick_reward(Request $request)
    {
        if ($this->request->isPost()) {
            $rate = input('reward_hm_rate');
            if (empty($rate)) {
                return $this->successJson(ERROR1, "操作失败:请输入自提区赠送比例", null);
            }
            $hot_rate = input('reward_hot_rate');
            if (empty($hot_rate)) {
                return $this->successJson(ERROR1, "操作失败:请输入爆品区赠送比例", null);
            }

            ini_set("display_errors", 1);
            ini_set('memory_limit', '-1');
            $hm_price = \app\common\model\ShopConfig::get_value('hm_price');
            //自提区
            $flag = \app\common\model\ShopConfig::where(['sc_key' => 'reward_pick_rate'])->update(['sc_value' => $rate]);
            if ($flag === false) {
                return $this->successJson(ERROR1, "更新自提区赠送比例失败", null);
            } else {
                $child_id = \app\common\model\GoodsCategory::where(['pid' => 2])->column('id');
                if (empty($child_id)) {
                    $child_id = 2;
                }
                $res = \app\common\model\Goods::whereIn('category_id', $child_id)->select();
                if ($res) {
                    foreach ($res as $key => $value) {

                        //goods_currency_give_num
                        $market = \app\common\model\GoodsFormat::where(['goods_id' => $value['goods_id'], 'status' => 1])->order('id asc')->value('goods_market');
                        if ($value['goods_price'] > 0 && $market > 0) {
                            $give_num = sprintf('%.2f', (($value['goods_price'] - $market) * ($rate / 100)) / $hm_price);
                            if ($give_num >= 0) {
                                $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_currency_give_num' => $give_num]);
                            }
                        } else {
                            $give_num = sprintf('%.2f', (($value['goods_price'] - $value['goods_purch']) * ($rate / 100)) / $hm_price);
                            if ($give_num >= 0) {
                                $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_currency_give_num' => $give_num]);
                            }
                        }
                    }
                }
            }
            //爆品专区
            $flag = \app\common\model\ShopConfig::where(['sc_key' => 'reward_hot_rate'])->update(['sc_value' => $hot_rate]);
            if ($flag === false) {
                return $this->successJson(ERROR1, "更新爆品区赠送比例失败", null);
            } else {
                $child_id = \app\common\model\GoodsCategory::where(['pid' => 16])->column('id');
                if (empty($child_id)) {
                    $child_id = 16;
                }
                $res = \app\common\model\Goods::whereIn('category_id', $child_id)->select();
                if ($res) {
                    foreach ($res as $key => $value) {
                        //goods_currency_give_num
                        $market = \app\common\model\GoodsFormat::where(['goods_id' => $value['goods_id'], 'status' => 1])->order('id asc')->value('goods_market');
                        if ($value['goods_price'] > 0 && $market > 0) {
                            $give_num = sprintf('%.2f', (($value['goods_price'] - $market) * ($hot_rate / 100)) / $hm_price);
                            if ($give_num >= 0) {
                                $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_currency_give_num' => $give_num]);
                            }
                        } else {
                            $give_num = sprintf('%.2f', (($value['goods_price'] - $value['goods_purch']) * ($hot_rate / 100)) / $hm_price);
                            if ($give_num >= 0) {
                                $flag = \app\common\model\Goods::where(['goods_id' => $value['goods_id']])->update(['goods_currency_give_num' => $give_num]);
                            }
                        }
                    }
                }
            }

            return $this->successJson(SUCCESS, "更新成功", ['url' => url('')]);
        } else {
            $reward_hm_rate = \app\common\model\ShopConfig::get_value('reward_pick_rate');
            $reward_hot_rate = \app\common\model\ShopConfig::get_value('reward_hot_rate');
            return $this->fetch(null, compact('reward_hm_rate', 'reward_hot_rate'));
        }
    }

    //确认收货
    public function confirm_order() {
        $gmo_id = input('gmo_id');
        if (!$gmo_id) return $this->successJson(ERROR1, "订单id不能为空", null);
        $check = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
        if (!$check) return $this->successJson(ERROR1, "该订单不存在", null);

        $result = \app\common\model\GoodsMainOrders::confirm_order($check['gmo_user_id'], $gmo_id);
        if ($result === false) {
            return $this->successJson(ERROR1, "确认收货失败", null);
        } else {
            return $this->successJson(SUCCESS, "确认收货成功", ['url' => url('')]);
        }
    }

    //取消订单
    public function cancel_order() {
        $gmo_id = input('gmo_id');
        if (!$gmo_id) return $this->successJson(ERROR1, "订单id不能为空", null);
        $check = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
        if (!$check) return $this->successJson(ERROR1, "该订单不存在", null);

        $result = \app\common\model\GoodsMainOrders::where(['gmo_id' => $gmo_id])->update(['gmo_status' => 5]);
        if ($result === false) {
            return $this->successJson(ERROR1, "取消订单失败", null);
        } else {
            return $this->successJson(SUCCESS, "取消订单成功", ['url' => url('')]);
        }
    }
}