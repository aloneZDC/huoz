<?php

namespace app\backend\controller;

use app\common\model\RocketGoods;
use app\common\model\RocketGoodsList;
use app\common\model\RocketOrder;
use app\common\model\ShopPayType;
use think\Db;
use think\Exception;
use think\Request;
use think\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\model\Config;

class Shop extends AdminQuick
{
    protected $pid = 'id';
    protected $allow_switch_field = ['status'];
    protected $public_action = ['getarea', 'pickedup_add', 'pickedup_edit', 'refresh_token', 'goods_edit', 'goods_add'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('rocket_goods');
    }

    /**
     * 主商品列表
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(Request $request)
    {
        $where = [];
        $title = $this->request->param('name');
        if ($title) {
            $where['name'] = ['like', "%{$title}%"];
        }
        $currency_id = $this->request->param('currency_id');
        if (!empty($currency_id)) {
            $where['currency_id'] = $currency_id;
        }

        $RocketGoods = new RocketGoods();
        $list = $RocketGoods->alias("g")
            ->join(config("database.prefix") . "currency c", "g.currency_id=c.currency_id", "LEFT")
            ->field(['g.*', 'c.currency_name'])
            ->where($where)->order("g.id desc,g.status asc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        // 币种
        $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currency'));
    }

    /**
     * 添加商品
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function add(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $form['start_time'] = strtotime($form['start_time']);
            if ($form['start_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['end_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['add_time'] = time();
            $result = model('rocket_goods')->insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                \app\common\model\RocketGoodsList::add_goods($result);//创建子商品
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('currency'));
        }
    }

    /**
     * 修改商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function edit(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $id = intval($form['id']);
            unset($form['id']);
            $info = model('rocket_goods')->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['start_time'] = strtotime($form['start_time']);
            if ($form['start_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['end_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }

            $result = model('rocket_goods')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = model('rocket_goods')->where(['id' => $id])->find();
            if (empty($info)) $this->error('该记录不存在');

            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('info', 'currency'));
        }
    }

    /**
     * 子商品列表
     * @return mixed
     */
    public function goods_list()
    {
        $where = [];
        $title = $this->request->param('name');
        if ($title) {
            $where['name'] = ['like', "%{$title}%"];
        }
        $currency_id = $this->request->param('currency_id');
        if (!empty($currency_id)) {
            $where['currency_id'] = $currency_id;
        }
        $goods_id = $this->request->param('goods_id');
        if (!empty($goods_id)) {
            $where['goods_id'] = $goods_id;
        }

        $RocketGoods = new RocketGoodsList();
        $list = $RocketGoods->alias("g")
            ->join(config("database.prefix") . "currency c", "g.currency_id=c.currency_id", "LEFT")
            ->field(['g.*', 'c.currency_name'])
            ->where($where)->order("g.id desc,g.status asc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        // 币种
        $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
        // 主商品
        $goods = Db::name('rocket_goods')->where('status', 1)->field(['id', 'name'])->order('id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currency', 'goods'));
    }

    /**
     * 商品订单
     * @return mixed
     * @throws DbException
     */
    public function order_list()
    {
        $order_code = $this->request->param('order_code');
        $where = [];
        if ($order_code) {
            $where['a.order_code'] = ['like', "%{$order_code}%"];
        }
        $gmo_status = $this->request->param('status');
        if (!empty($gmo_status)) {
            $where['a.status'] = $gmo_status;
        }
        $member_id = $this->request->param('member_id');
        if ($member_id) {
            $where['a.member_id'] = $member_id;
        }

        $list = RocketOrder::alias('a')->where($where)
            ->field("a.*,b.name,c.currency_name,d.phone,d.email")
            ->join(config('database.prefix') . 'rocket_goods_list b', 'a.goods_list_id = b.id', 'LEFT')
            ->join(config('database.prefix') . 'currency c', 'b.currency_id = c.currency_id', 'LEFT')
            ->join(config('database.prefix') . 'member d', 'a.member_id = d.member_id', 'LEFT')
            ->order('a.id', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $share_reward = Db::name('rocket_reward_log')->where(['member_id' => $value['member_id'], 'type' => 2, 'third_id' => $value['id']])->sum('reward');
                $value['share_reward'] = $share_reward;//分享收益
                $team_reward = Db::name('rocket_reward_log')->where(['member_id' => $value['member_id'], 'type' => 3, 'third_id' => $value['id']])->sum('reward');
                $value['team_reward'] = $team_reward;//团队收益
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
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
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $gmo_id = $this->request->param('gmo_id');
            $info = GoodsMainOrders::with(['paytype', 'payCurrency', 'equalCurrency', 'rebateParentCurrency', 'rebateSelfCurrency', 'giveCurrency'])->where(['gmo_id' => $gmo_id])->find();
            if (empty($info)) $this->error('该订单不存在');
            $info['gmo_status'] = GoodsMainOrders::STATUS_ENUM[$info['gmo_status']];
            $info['gmo_status_refund'] = GoodsMainOrders::REFUND_ENUM[$info['gmo_status_refund']];

            return $this->fetch(null, compact('info'));
        }
    }

    /**
     * 子订单
     * @return mixed
     * @throws DbException
     */
    public function order_child()
    {
        $gmo_go_id = $this->request->param('gmo_go_id');
        $explode = explode(',', $gmo_go_id);
        $list = Db::name('goods_orders')
            ->where(['go_id' => ['in', $explode]])
            ->order('go_add_time', 'desc')
            ->paginate(null, null, ["query" => $this->request->get()]);

        if (empty($list)) $this->error('找不到相关数据');

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
    public function order_ship()
    {
        if ($this->request->isPost()) {
            $saveData = $this->request->only(['gmo_id', 'gmo_status', 'form']);
            if (empty($saveData['form']['gmo_express_company'])) {
                return ['code' => ERROR1, 'message' => '物流公司不能为空'];
            }
            if (empty($saveData['form']['gmo_express_code'])) {
                return ['code' => ERROR1, 'message' => '物流单号不能为空'];
            }

            $expressFind = Db::name('express_list')->where('code', $saveData['form']['gmo_express_company'])->where('status', 1)->find();
            if (!$expressFind) return ['code' => ERROR1, 'message' => '填写物流公司不存在或者暂不支持'];
            $saveData['form']['gmo_express_name'] = $expressFind['name'];
//            $result = GoodsMainOrders::addLogistics($expressFind['code'], $expressFind['name'], $saveData['form']['gmo_express_code']);
//            if ($result['code'] != SUCCESS) return ['code' => ERROR1, 'message' => $result['message']];

            if ($saveData['gmo_status'] == 1) { // 确认发货
                $saveData['form']['gmo_ship_time'] = time(); // 发货时间
                $autoSureTime = ShopConfig::get_value('orders_auto_sure_time', 4320);
                $saveData['form']['gmo_auto_sure_time'] = $saveData['form']['gmo_ship_time'] + $autoSureTime * 60; // 自动确认收货时间
                $saveData['form']['gmo_status'] = 3; // 3已发货
            }

            $isUpdate = GoodsMainOrders::where('gmo_id', $saveData['gmo_id'])
                ->update($saveData['form']);
            if ($isUpdate) {
//                $so_id = GoodsMainOrders::where(['gmo_id' => $saveData['gmo_id']])->value('gmo_code');
//                $OrderApi = new \order\OrderApi();
//                $shop_id  = Config::get_value('shop_id', 0);
//                $orders_params = [
//                    'so_ids' => [$so_id],//线上单号
//                ];
//                $result = $OrderApi->orders_query($orders_params);//查询订单
//                if (!empty($result) && $result['code'] === 0) {
//                    $o_id = $result['orders'][0]['o_id'];
//                    $arr = [
//                        'shop_id' => (int)$shop_id,//店铺编号
//                        'o_id' => $o_id,//ERP内部订单号
//                        'so_id' => $so_id,//线上订单号
//                        'lc_name' => $saveData['form']['gmo_express_company'],//快递公司
//                        'l_id' => $saveData['form']['gmo_express_code'],//快递单号
//                        'lc_id' => $expressFind['lc_id'],//快递编码
//                        'modified' => date('Y-m-d H:i:s', time())//发货时间
//                    ];
//                    $params = [];
//                    $params[] = $arr;
//                    $result = $OrderApi->ordersent_upload($params);//订单发货
//                    if (!$result || !empty($result) && $result['code'] != 0) {
//                        return ['code' => ERROR1, 'message' => $result['msg']];
//                    }
//                }
                return ['code' => SUCCESS, 'message' => '操作成功'];
            }
            return ['code' => ERROR1, 'message' => '操作失败'];
        }

        $gmo_id = $this->request->param('gmo_id', 0);
        $gmo_status = $this->request->param('gmo_status', 0);
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
    public function order_logistics()
    {
        $gmo_id = $this->request->param('gmo_id', 0);
        $info = GoodsMainOrders::where(['gmo_id' => $gmo_id])->find();
        if (empty($info)) $this->error('找不到相关数据');

        $logistics = ShopLogisticsList::where(['company' => $info['gmo_express_company'], 'number' => $info['gmo_express_code']])->find();

        //var_dump(json_decode($logistics['last_result'], true));
        $logisticsInfo = json_decode($logistics['last_result'], true);
        //var_dump($logisticsInfo);

        $statusList = [
            '在途' => '运输中', '揽收' => '已揽件', '派件' => '派送中', '签收' => '已签收', '退签' => '已退签', '退回' => '退回中',
        ];

        return $this->fetch(null, compact('info', 'logisticsInfo', 'statusList'));
    }

    // 自提点管理列表
    public function pickedup_list()
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
            ->paginate(null, null, ["query" => $this->request->get()]);

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


    protected $flashType = [
//        0 => "首页幻灯片",
        2 => "手机Banner",
//        3 => "手机 APP Banner",
//        6 => "游戏Banner",
//        7 => "算能首页幻灯片",
//        8 => "商城幻灯片",
//        9 => '线下商城幻灯片',
//        10 => '矿池幻灯片',
//        11 => '发现幻灯片',
    ];

    /**
     * 轮播图列表
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function flash_list(Request $request)
    {
        $where['status'] = 1;
        $title = $request->get('title');
        if ($title) $where['title'] = ['like', "%{$title}%"];

        $list = \app\common\model\Flash::where($where)->order(['flash_id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        $type = $this->flashType;
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    /**
     * 轮播图添加
     * @param Request $request
     * @return array|mixed
     */
    public function flash_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['add_time'] = time();
            $result = \app\common\model\Flash::create($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $type = $this->flashType;
        return $this->fetch(null, compact('type'));
    }

    /**
     * 轮播图编辑
     * @param Request $reques
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function flash_edit(Request $reques)
    {
        if ($this->request->isPost()) {
            $id = $reques->param('id', 0, 'intval');
            $form = $reques->param('form/a');
            $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $result = \app\common\model\Flash::update($form, ['flash_id' => $info['flash_id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $reques->param('id', 0, 'intval');
        $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");
        $type = $this->flashType;
        return $this->fetch(null, compact('info', 'type'));
    }

    /**
     * 轮播图删除
     * @param Request $reques
     * @return array
     */
    public function flash_del(Request $reques)
    {
        $id = $reques->param('id', 0, 'intval');
        try {
            $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
            if (!$info) throw new Exception("记录不存在,不能执行该操作");

            $flag = \app\common\model\Flash::where(['flash_id' => $id])->delete();
            if ($flag === false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 配置管理
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            if (!empty($form)) {
                foreach ($form as $key => $item) {
                    \app\common\model\RocketConfig::where(['key' => $key])->update([
                        'value' => $item,
                    ]);
                }
            }
            return ['code' => SUCCESS, 'message' => '操作成功', 'data' => ['url' => url('')]];
        }
        $where = ['desc' => ['neq', '']];
        $list = \app\common\model\RocketConfig::where($where)->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 修改子商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_edit(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $id = intval($form['id']);
            unset($form['id']);
            $info = model('rocket_goods_list')->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            $form['start_time'] = strtotime($form['start_time']);
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['start_time'] >= $form['end_time']) {
                return $this->successJson(ERROR1, "开始时间或结束时间有误，请检查一下", null);
            }
            if ($form['start_time'] <= time()) {
                return $this->successJson(ERROR1, "开始时间需大于当前时间", null);
            }
            $result = model('rocket_goods_list')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = model('rocket_goods_list')->where(['id' => $id])->find();
            if (empty($info)) $this->error('该记录不存在');

            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('info', 'currency'));
        }
    }

    //预约排队
    public function queue_list() {
        $where = [];
        $level = intval($this->request->param('level'));
        if ($level) {
            $where['level'] = $level;
        }
        $goods_id = $this->request->param('goods_id');
        if (!empty($goods_id)) {
            $where['goods_id'] = $goods_id;
        }

        $RocketGoods = new RocketGoodsList();
        $list = $RocketGoods->where($where)->order("id desc,status asc")->paginate(null, null, ["query" => $this->request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $value['start_time'] = date('m-d H:i', $value['start_time']);
                $queue_num = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 2, 'status' => 1])->sum('actual_num');
                $value['queue_num'] = sprintf('%.2f', $queue_num);
                $people_num = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 2, 'status' => 1])->count('member_id');
                $value['people_num'] = sprintf('%.2f', $people_num);
                $auto_num = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 3, 'status' => 1])->sum('actual_num');
                $value['auto_num'] = sprintf('%.2f', $auto_num);
                $auto_people_num = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 3, 'status' => 1])->count('member_id');
                $value['auto_people_num'] = sprintf('%.2f', $auto_people_num);
                $buy_money = Db::name('rocket_order')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->sum('money');
                $value['buy_money'] = sprintf('%.2f', $buy_money);
                $buy_num = Db::name('rocket_order')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->count('id');
                $value['buy_num'] = $buy_num;
                $list_money = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->sum('num');
                $value['list_money'] = sprintf('%.2f', $list_money);
                $list_num = Db::name('rocket_buy_list')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->count('id');
                $value['list_num'] = $list_num;
            }
        }
        $page = $list->render();
        $count = $list->total();

        $info = [];
        $total_num = Db::name('rocket_subscribe_transfer')->sum('num');
        $info['total_num'] = sprintf('%.2f', $total_num);//预约池总额
        $surplus_num = Db::name('currency_user')->where(['currency_id' => 102])->sum('num');
        $info['surplus_num'] = sprintf('%.2f', $surplus_num);//剩余预约池余额
        $seniority = \app\common\model\RocketConfig::getValue('subscribe_seniority');
        $complete_num = Db::name('currency_user')->where(['currency_id' => 102, 'num' => ['egt', $seniority]])->sum('num');
        $info['complete_num'] = sprintf('%.2f', $complete_num);//剩余预约池余额（排除小于30u）
        // 主商品
        $goods = Db::name('rocket_goods')->where('status', 1)->field(['id', 'name'])->order('id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'goods', 'info'));
    }

    /**
     * 新增子商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_add(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $form['start_time'] = strtotime($form['start_time']);
            $form['end_time'] = strtotime($form['end_time']);
            $start_time = strtotime(date('Y-m-d', $form['start_time']));
            if ($start_time == strtotime(date('Y-m-d'))) {
                $today_start = time();
                $today_end = (time() + 3600);
                if ($form['start_time'] >= $today_start && $form['start_time'] <= $today_end) {
                    return $this->successJson(ERROR1, "开舱前一小时不能新增闯关，请修改闯关开始时间", null);
                }
            }
            $form['name'] = model('rocket_goods')->where(['id' => $form['goods_id']])->value('name');
            $form['add_time'] = time();
            $result = model('rocket_goods_list')->insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            //主闯关
            $goods = model('rocket_goods')->where(['status' => 1])->select();
            if (empty($goods)) $this->error('该记录不存在');

            // 币种
            $currency = \app\common\model\RocketConfig::getValue('reward_currency_id');
            return $this->fetch(null, compact('goods', 'currency'));
        }
    }
}
