<?php


namespace app\admin\controller;

use app\common\model\Currency;
use app\common\model\GoldMainOrders;
use app\common\model\Goods;
use app\common\model\ShopPayType;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;
use think\response\Json;

class Shop extends Admin
{
    protected $not_allow_shop = false;

    /**
     * 当前角色是否是"shop"
     * @return bool
     */
    protected function isShop()
    {
        return $this->admin['rule'] == "shop";
    }

    /**
     * 商品 admin_id 查询条件
     * @return array
     */
    protected function getShopRuleWhere()
    {
        $where = [];
        if ($this->isShop()) {
            $where['goods_admin_id'] = $this->admin['admin_id'];
        }
        return $where;
    }

    /**
     * 订单 admin_id 查询条件
     * @return array
     */
    protected function getMainOrdersShopRuleWhere()
    {
        $where = [];
        if ($this->isShop()) {
            $where['gmo_admin_id'] = $this->admin['admin_id'];
        }
        return $where;
    }

    /**
     * 子订单 admin_id 查询条件
     * @return array
     */
    public function getOrderShopRuleWhere()
    {
        $where = [];
        if ($this->isShop()) {
            $where['go_admin_id'] = $this->admin['admin_id'];
        }
        return $where;
    }

    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     * @throws Exception
     * @throws DbException
     */
    public function index(Request $request)
    {
        $count = Goods::where("goods_status", "in", [Goods::STATUS_UP, Goods::STATUS_DOWN])->where($this->getShopRuleWhere())->count('goods_id');
        $list = Goods::where("goods_status", "in", [Goods::STATUS_UP, Goods::STATUS_DOWN])->where($this->getShopRuleWhere())->with(['currency', 'otherCurrency'])->order('goods_id desc')->paginate(null, $count, ['query' => $request->get()]);
        foreach ($list as &$item) {
            if (Currency::IOSCORE_ID == $item['goods_currency_other_id'] and 'game_lock' == $item['goods_currency_other_type']) {
                $item['otherCurrency']['currency_name'] = 'io券';
            }
        }
        $page = $list->render();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * @param Request $request
     * @return mixed|void
     * @throws Exception
     * @throws DbException
     * @throws PDOException
     */
    public function index_image(Request $request)
    {
        if ($this->isShop()) {
            $this->error("暂无权限");
            return;
        }
        if ($request->isPost()) {

            foreach ($_FILES as $key => $FILE) {
                if (empty($FILE['tmp_name'])) {
                    unset($_FILES[$key]);
                }
            }

            if (!empty($_FILES["shop_hot_image"]["tmp_name"]) && $_FILES["shop_hot_image"]["tmp_name"]) {
                $_POST['shop_hot_image'] = $this->upload($_FILES["shop_hot_image"]);

                if (!$_POST['shop_hot_image']) {
                    $this->error('非法上传');
                }
            }

            if (!empty($_FILES["shop_set_in_image"]["tmp_name"]) && $_FILES["shop_set_in_image"]["tmp_name"]) {
                $_POST['shop_set_in_image'] = $this->upload($_FILES["shop_set_in_image"]);
                if (!$_POST['shop_set_in_image']) {
                    $this->error('非法上传');
                }
            }
            $rs = null;
            foreach ($_POST as $k => $v) {
                $rs[] = Db::name("Config")->where(['key' => $k])->update(['value' => $v]);
            }
            if ($rs) {
                $this->success('配置修改成功');
                return;
            } else {
                $this->error('配置修改失败');
                return;
            }
        }
        return $this->fetch(null);
    }

    /**
     * 添加商品
     * @param Request $request
     * @return mixed|Json|void
     * @throws DbException
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $path = 'goods_pics';
            if ($_FILES['art_pic']['size'] > 0) {
                $art_pic = ['art_pic' => $_FILES['art_pic']];
                unset($_FILES['art_pic']);
                $upload = $this->oss_upload($art_pic, $path);
                if (empty($upload)) {
                    $this->error("上传图片失败");
                    return;
                }
                $data['goods_img'] = trim($upload['art_pic']);
            } else {
                $this->error("请上传图片");
                return;
            }

            // 上传轮播图
            if ($_FILES['goods_banners']['size'] < 0) {
                $this->error('请上传轮播图');
                return;
            }
            $goodsBanners = ['art_pic' => $_FILES['goods_banners']];
            unset($_FILES['goods_banners']);
            $up = $this->multiple_upload($goodsBanners, $path);
            if (empty($up)) {
                $this->error('图片上传失败');
                return false;
            }
            $data['goods_banners'] = json_encode($up);
            $data['goods_status'] = Goods::STATUS_UP;
            $data['goods_time'] = time();
            $data['goods_price'] = $data['goods_currency_num'] + $data['goods_currency_other_num'];
            $data['goods_currency_other_id'] = Currency::IOSCORE_ID;
            $data['goods_admin_id'] = $this->admin['admin_id'];
            /**
             * @deprecated 几乎废弃的字段 使用支付方式确定 type
             */
            $res = Goods::create($data);
            if (!$res) {
                $this->error("系统错误添加失败");
                return;
            }

            $this->success("添加成功");
        }
        /*$config = UsersVotesConfig::get_key_value();
        $currency = Currency::where(['currency_mark' => $config['votes_score存_currency_mark']])->find();*/
        /**
         * 积分币种 (首选币种)
         */
        $currency = Currency::where('currency_id', Currency::IOSCORE_ID)->field('currency_id, currency_mark, currency_name')->select();
        /**
         * 组合币种
         */
        $composeCurrency = Currency::getShopComposeCurrency();
        foreach ($composeCurrency as &$item) {
            if (Currency::IOSCORE_ID == $item['currency_id']) {
                $item['currency_name'] = "io券";
            }
        }
        $pay_type = ShopPayType::TYPE_ENUM;
        return $this->fetch('shop/add', ['currency' => $currency, 'composeCurrency' => $composeCurrency, 'pay_type' => $pay_type]);
    }

    /**
     * 删除商品
     * @param Request $request
     * @return Json
     * @throws Exception
     */
    public function delete(Request $request)
    {
        $id = $request->post('id');
        $result = Db::name("goods")->where($this->getShopRuleWhere())->where(['goods_id' => $id])->update(['goods_status' => Goods::STATUS_DEL]);
        if (!$result) {
            return successJson(ERROR1, "系统错误, 删除失败!", null);
        }

        return successJson(SUCCESS, "删除商品成功!");
    }

    /**
     * 上下架商品
     * @param Request $request
     * @return Json
     * @throws Exception
     * @throws PDOException
     */
    public function up_and_down(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        $result = Db::name("goods")->where(['goods_id' => $id])->where($this->getShopRuleWhere())->update(['goods_status' => $status]);
        if (!$result) {
            return successJson(ERROR1, "系统错误, 操作失败!", null);
        }

        return successJson(SUCCESS, "操作成功!");
    }

    // 设置爆款
    public function set_hot(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        $result = Db::name("goods")->where(['goods_id' => $id])->where($this->getShopRuleWhere())->update(['goods_is_hot' => $status]);
        if (!$result) {
            return successJson(ERROR1, "系统错误, 操作失败!", null);
        }

        return successJson(SUCCESS, "操作成功!");
    }

    /**
     * 修改商品
     * @param Request $request
     * @return mixed|Json|void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $path = 'goods_pics';
            if ($_FILES['art_pic']['size'] > 0) {
                $art_pic = ['art_pic' => $_FILES['art_pic']];
                unset($_FILES['art_pic']);
                $upload = $this->oss_upload($art_pic, $path);
                if (empty($upload)) {
                    $this->error("上传图片失败");
                    return;
                }
                $data['goods_img'] = trim($upload['art_pic']);
            }

            // 上传轮播图
            if ($_FILES['goods_banners']['size'][0] > 0) {
                $goodsBanners = ['art_pic' => $_FILES['goods_banners']];
                unset($_FILES['goods_banners']);
                $up = $this->multiple_upload($goodsBanners, $path);
                if (empty($up)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['goods_banners'] = json_encode($up);
            }
            $data['goods_price'] = $data['goods_currency_num'] + $data['goods_currency_other_num'];
            $data['goods_currency_other_id'] = Currency::IOSCORE_ID;
            // $data['goods_currency_other_type'] = Currency::SHOP_COMPOSE_CURRENCY_USER_TYPE_ENUM[$data['goods_currency_other_id']];
            $res = Goods::where($this->getShopRuleWhere())->update($data);
            if (!$res) {
                $this->error("系统错误更新失败");
                return;
            }
            $this->success("修改成功");
        }

        $goods = Goods::where('goods_id', $request->get('id'))->where($this->getShopRuleWhere())->find();
        if (empty($goods)) {
            $this->error("商品不存在!");
            return;
        }
        // $goods['goods_img'] = json_decode($article['article_image']);
        /**
         * 积分币种 (首选币种)
         */
        $currency = Currency::where('currency_id', Currency::IOSCORE_ID)->field('currency_id, currency_mark, currency_name')->select();
        /**
         * 组合币种
         */
        $composeCurrency = Currency::getShopComposeCurrency();
        foreach ($composeCurrency as &$item) {
            if (Currency::IOSCORE_ID == $item['currency_id']) {
                $item['currency_name'] = "io券";
            }
        }
        $pay_type = ShopPayType::TYPE_ENUM;

        return $this->fetch(null, ['goods' => $goods, 'currency' => $currency, 'composeCurrency' => $composeCurrency, 'pay_type' => $pay_type]);
    }

    /**
     * 订单列表
     * @param Request $request
     * @return mixed
     * @throws Exception
     * @throws DbException
     * Create by: Red
     * Date: 2019/10/19 15:17
     */
    function orders_list(Request $request)
    {
        $where = null;
        $get = $request->get();
        $query = Db::name("gold_main_orders");

        //订单状态
        if (isset($get['gmo_status']) && $get['gmo_status'] > 0) {
            $where['gmo_status'] = $get['gmo_status'];
        }

        //释放状态
        /*if (isset($get['gmo_release_status']) && $get['gmo_release_status'] > 0) {
            $where['gmo_release_status'] = $get['gmo_release_status'];
        }*/
        if (isset($get['user_id']) && $get['user_id'] > 0) {
            $where['gmo_user_id'] = $get['user_id'];
        }
        if (isset($get['user_nickname']) && !empty($get['user_nickname'])) {
            $where['user_nickname'] = $get['user_nickname'];
        }
        if (isset($get['gmo_code']) && !empty($get['gmo_code'])) {
            $where['gmo_code'] = $get['gmo_code'];
        }
        if (isset($get['start']) && !empty($get['start'])) {
            $endtime = date("Y-m-d", time());
            if (isset($get['end']) && !empty($get['end'])) {
                $endtime = $get['end'];
            }
            $where['gmo_add_time'] = array('between', array(strtotime($get['start']), strtotime($endtime) + 86400));
        }
        $list = GoldMainOrders::with(['currency', 'otherCurrency'])
            ->where($where)
            ->where($this->getMainOrdersShopRuleWhere())
            ->alias('g')
            ->order('gmo_id desc')
            ->join(config("database.prefix") . "member u", "u.member_id=g.gmo_user_id", "LEFT")
            ->paginate(null, false, ['query' => $get]);
        $page = $list->render();
        $count = $list->total();
        /*$count = $query->alias("g")->where($where)->
        join(config("database.prefix") . "member u", "u.member_id=g.gmo_user_id", "LEFT")->
        join(config("database.prefix") . "currency c", "c.currency_id=g.gmo_pay_currency_id", "LEFT")
            ->count("gmo_id");
        $field = "gmo_id,gmo_user_id,gmo_go_id,gmo_num,gmo_code,gmo_add_time,gmo_status,gmo_total_price,gmo_total_num,gmo_last_num,
        gmo_release_num,gmo_express_name,gmo_express_code,gmo_receive_name,gmo_mobile,gmo_address,gmo_sure_time,gmo_release_status,
        u.nick,currency_name";
        $list = $query->alias("g")->where($where)->field($field)->
        join(config("database.prefix") . "member u", "u.member_id=g.gmo_user_id", "LEFT")->
        join(config("database.prefix") . "currency c", "c.currency_id=g.gmo_pay_currency_id", "LEFT")->
        order("gmo_id desc")->paginate(20, $count, ['query' => $get]);
        $page = $list->render();*/
        return $this->fetch(null, compact('list', 'page', 'count', 'get'));
    }

    /**
     * 发货操作
     * @param Request $request
     * @return Json
     * @throws Exception
     */
    function deliver_goods(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
//            $data['gmo_release_status'] = 1;

            $find = GoldMainOrders::where(['gmo_id' => $data['gmo_id']])->where($this->getMainOrdersShopRuleWhere())->find();
            if (empty($find)) {
                return successJson(ERROR1, '订单不存在', null);
            }
            if ($find->gmo_status == 2) {
                return successJson(ERROR1, '该订单还未付款', null);
            }
            if ($find->gmo_status == 1) {
                $find->gmo_status = 3;
            }
//            $find->gmo_release_status = 1;
            $find->gmo_express_name = $data['gmo_express_name'];
            $find->gmo_express_code = $data['gmo_express_code'];
            if (!$find->save()) {
                return successJson(ERROR1, '发货失败!', null);
            }
            return successJson(SUCCESS, '发货成功!', null);
        }
        return $this->fetch(null, ['gmo_id' => $request->get('gmo_id')]);
    }

    /**
     *  订单详情
     * @param Request $request
     * @return mixed|void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function order_detail(Request $request)
    {
        $gmo_code = $request->get("gmo_code");
        $mo = null;
        $ol = null;
        if (!empty($gmo_code)) {
            //主订单详情
            /*$field = "gmo_id,gmo_user_id,gmo_go_id,gmo_num,gmo_code,gmo_add_time,gmo_status,gmo_total_price,gmo_total_num,gmo_last_num,
        gmo_release_num,gmo_express_name,gmo_express_code,gmo_receive_name,gmo_mobile,gmo_address,gmo_sure_time,gmo_release_status,
        user_nickname,currency_name";*/
            $mo = GoldMainOrders::alias("g")/*->field($field)*/
            ->where(['gmo_code' => $gmo_code])
                ->where($this->getMainOrdersShopRuleWhere())
                ->with(['currency', 'otherCurrency'])
                ->join(config("database.prefix") . "member u", "u.member_id=g.gmo_user_id", "LEFT")
                ->find();
            if (!empty($mo)) {
                //子订单详情
                $ol = Db::name("gold_orders")->where($this->getOrderShopRuleWhere())->where("go_id", "in", $mo['gmo_go_id'])->select();
                if (!empty($ol)) {
                    foreach ($ol as &$value) {
                        $img = explode(",", $value['go_img']);
                        $value['img'] = isset($img[0]) ? $img[0] : null;
                    }
                }
            } else {
                $this->error("订单不存在");
                return;
            }
        }
        return $this->fetch(null, compact('mo', 'ol'));
    }

    /**
     * 富文本上传图片
     * Create by: Red
     * Date: 2019/10/23 14:01
     */
    function oss_file_upload()
    {
        $oss = new OssObject();
        $upload = $oss->oss_upload($file = [], $path = 'shop');
        if (!empty($upload['imgFile'])) {
            echo json_encode(['error' => 0, 'url' => $upload['imgFile']]);
        } else {
            echo json_encode(['error' => 0, 'message' => '上传失败']);
        }
        exit;
    }
}