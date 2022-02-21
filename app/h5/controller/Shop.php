<?php

namespace app\h5\controller;

use Alipay\Alipay;
use app\common\model\Config;
use app\common\model\CurrencyUser;
use app\common\model\Flash;
use app\common\model\GoldMainOrders;
use app\common\model\GoldOrders;
use app\common\model\Goods;
use app\common\model\GoodsCategory;
use app\common\model\GoodsFormat;
use app\common\model\GoodsMainOrders;
use app\common\model\OrdersRefunds;
use app\common\model\ShopAddress;
use app\common\model\ShopCart;
use app\common\model\Member;
use app\common\model\ShopConfig;
use app\common\model\ShopPayType;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;
use think\response\Json;

class Shop extends Base
{
    protected $public_action = ["get_goods_list", "get_goods_details", 'index', 'poll'];

    /**
     * 物流信息订阅
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function poll()
    {
        $postStr = file_get_contents("php://input");
        $postStr = urldecode($postStr);
        //$postStr = 'param={"message":"变化","comOld":"","status":"polling","lastResult":{"nu":"4310508235533","message":"ok","ischeck":"1","com":"yunda","condition":"D01","status":"200","state":"3","data":[{"time":"2020-11-28 14:32:37","status":"签收","context":"【深圳市】您的快件已签收,签收人：保安室21，如有疑问请电联快递员：刘东椿【16675541498】。","ftime":"2020-11-28 14:32:37"},{"time":"2020-11-28 13:23:57","areaName":"广东,深圳市,宝安区,沙井","status":"派件","areaCode":"CN440306005000","context":"【深圳市】广东深圳公司宝安区沙井共和分部 快递员 刘东椿16675541498 正在为您派件【95114/95121/95013/95546为韵达快递员外呼专属号码，请放心接听】","ftime":"2020-11-28 13:23:57"},{"time":"2020-11-28 12:49:43","areaName":"广东,深圳市","status":"在途","areaCode":"CN440300000000","context":"【深圳市】已到达 广东深圳公司宝安区沙井共和分部","ftime":"2020-11-28 12:49:43"},{"time":"2020-11-28 09:43:19","areaName":"广东,深圳市","status":"在途","areaCode":"CN440300000000","context":"【深圳市】已离开 广东深圳公司；发往 广东深圳公司宝安区沙井共和分部","ftime":"2020-11-28 09:43:19"},{"time":"2020-11-28 08:54:52","areaName":"广东,深圳市","status":"在途","areaCode":"CN440300000000","context":"【深圳市】已到达 广东深圳公司","ftime":"2020-11-28 08:54:52"},{"time":"2020-11-28 04:34:03","areaName":"广东,广州市","status":"在途","areaCode":"CN440100000000","context":"【广州市】已离开 广东广州分拨中心；发往 广东深圳公司","ftime":"2020-11-28 04:34:03"},{"time":"2020-11-28 04:30:06","status":"在途","context":"【广州市】已到达 广东广州分拨中心","ftime":"2020-11-28 04:30:06"},{"time":"2020-11-27 21:30:29","areaName":"江西,赣州市","status":"在途","areaCode":"CN360700000000","context":"【赣州市】已离开 江西赣州分拨中心；发往 广东广州分拨中心","ftime":"2020-11-27 21:30:29"},{"time":"2020-11-27 21:27:27","status":"在途","context":"【赣州市】已到达 江西赣州分拨中心","ftime":"2020-11-27 21:27:27"},{"time":"2020-11-27 17:53:20","areaName":"江西,赣州市,信丰县","status":"揽收","areaCode":"CN360722000000","context":"【赣州市】江西信丰县公司脐橙淘宝服务部 已揽收","ftime":"2020-11-27 17:53:20"}]},"comNew":"","billstatus":"change","autoCheck":"0"}';
        //$postStr = 'param={"status":"abort","billstatus":"","message":"3天查询无记录","autoCheck":"0","comOld":"","comNew":"","lastResult":{"message":"快递公司参数异常：单号不存在或者已经过期","nu":"4310508235533","ischeck":"0","condition":"","com":"yunda","status":"201","state":"0","data":[]}}';
        $str = substr($postStr, 6);
        $result = json_decode($str, true);
        //var_dump($result);
        //var_dump(json_encode($result['lastResult']));
        GoodsMainOrders::updateLogistics($result);
    }

    /**
     * 分享规则 $ . base64(shop@goodsId|user@$user_id) . ^
     * @param int $goodsId
     * @param int $user_id
     * @return string
     */
    public function getShareContent($goodsId = 0, $user_id = 0)
    {
        return "$" . base64_encode("shop@" . $goodsId . "@" . "user@" . $user_id) . "^";
    }

    public function getGoodsInfo(Request $request)
    {
        $goods_id = $request->post('goods_id', null);
        $sc_id = $request->post('sc_id', null);

        if (empty($goods_id) and empty($sc_id)) {
            return mobileAjaxReturn([], lang('lan_modifymember_parameter_error'), ERROR1);
        }
        $field = "goods_id, goods_title, goods_img, goods_price, goods_currency_num, goods_currency_other_num, goods_currency_id, goods_currency_other_id";
        $data = [];
        if ($goods_id) {
            $data = Goods::where('goods_id', $goods_id)->with(['currency'])->field($field)->select();
            foreach ($data as &$good) {
                Goods::formatGoods($good);
                $good['num'] = 1;
            }
        }
        if ($sc_id) {
            // 购物车数据
            $carts = ShopCart::where('sc_user_id', $this->member_id)->where('sc_id', 'in', $sc_id)->field('sc_id, sc_goods_id, sc_num')->select();
            $goodsIds = [];
            foreach ($carts as $cart) {
                array_push($goodsIds, $cart['sc_goods_id']);
            }
            $data = Goods::where('goods_id', 'in', $goodsIds)->field($field)->with(['currency'])->select();
            foreach ($data as &$good) {
                Goods::formatGoods($good);
                foreach ($carts as $cart) {
                    if ($good['goods_id'] == $cart['sc_goods_id']) {
                        $good['num'] = $cart['sc_num'];
                    }
                }
            }
        }

        return mobileAjaxReturn($data, lang('data_success'), SUCCESS);
    }


    /**
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function share(Request $request)
    {
        $goodsId = $request->post('goods_id', null);
        if (is_null($goodsId)) {
            $content = lang('shop_share2', [$this->getShareContent(0, $this->member_id), lang('shop_desc')]);
        } else {
            $goods = (new Goods)->where('goods_id', $goodsId)->field('goods_id, goods_title, goods_status')->find();
            if (empty($goods)) {
                return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('goods_down_or_delete'), 'result' => null]);
            }
            $errStatus = [Goods::STATUS_DOWN, Goods::STATUS_DEL];
            // 状态判断
            if (in_array($goods['goods_status'], $errStatus)) {
                return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('goods_down_or_delete'), 'result' => null]);
            }
            $shop = $this->getShareContent($goodsId, $this->member_id);
            $content = lang('shop_share1', [$shop, $goods['goods_title']]);
        }
        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('successful_operation'), 'result' => $content]);
    }

    public function logistics(Request $request)
    {
        $code = $request->post('code');
        $company = $request->post('company');
        $data = [
            [
                "context" => "上海分拨中心/装件入车扫描 ",
                "time" => "2012-08-28 16:33:19",
                "ftime" => "2012-08-28 16:33:19",
            ],
            [
                "context" => "上海分拨中心/下车扫描 ",
                "time" => "2012-08-27 23:22:42",
                "ftime" => "2012-08-27 23:22:42",
            ]
        ];
        /*[
            '包裹已签收, 感谢您使用' . $company . '服务, 期待下次为您服务',
            '[深圳市] 广东省深圳市宝安区机场派件员: *** 电话: 10086 当前正在为您派件',
            '快件到达 深圳市宝安机场 公司',
            '配送已接单',
            '包裹正在等待收揽',
            '包裹已出仓',
            '打包完成',
            '仓库已接单'
        ]*/
        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $data]);
    }

    public function index(Request $request)
    {
        $banners = Flash::field('flash_id,pic,jump_url')->order('sort asc')->where('type=2')->where('lang', $this->lang)->limit(8)->select();

//        $categories = [
//            [
//                'id' => 1,
//                'image' => 'https://lanhu.oss-cn-beijing.aliyuncs.com/pse1b3ba3e832be526-48ad-46a5-859b-300019a4d952',
//                'name' => '手机'
//            ], [
//                'id' => 2,
//                'image' => 'https://lanhu.oss-cn-beijing.aliyuncs.com/ps59436486340d6e35-d8fd-4af6-9c3c-c2dd8d2f97fe',
//                'name' => '珠宝手表'
//            ], [
//                'id' => 3,
//                'image' => 'https://lanhu.oss-cn-beijing.aliyuncs.com/ps7ad6ceb507870029-4677-4955-be10-8b76c84adf99',
//                'name' => '汽车配件'
//            ], [
//                'id' => 4,
//                'image' => 'https://lanhu.oss-cn-beijing.aliyuncs.com/ps9b1f4c1cc461e80a-8e92-46dc-b007-e478718b16c2',
//                'name' => '家居'
//            ], [
//                'id' => 5,
//                'image' => 'https://lanhu.oss-cn-beijing.aliyuncs.com/ps255ddee2f7da32b0-448d-4699-95dd-bd25a2b24360',
//                'name' => '食品'
//            ]
//        ];

        $field1 = 'article_id,title';
        $newsList = Db::name('article')->field($field1)->where(['position_id' => 12])->order('add_time desc')->limit(0, 10)->select();

        // 菜单栏
        $categories = GoodsCategory::get_category_list(0);
        foreach ($categories as &$item) {
            $item['children'] = GoodsCategory::get_category_list($item['id']);
        }

//        $field = "goods_id,goods_title,goods_img,goods_price,goods_currency_id, goods_currency_num, goods_currency_type, goods_currency_other_id, goods_currency_other_num, goods_currency_other_type, goods_postage, goods_market";
//        $hots = Goods::where([
//            'goods_is_hot' => Goods::HOT_GOODS,
//            'goods_status' => Goods::STATUS_UP
//        ])->with(['currency', 'otherCurrency'])->order('goods_sort asc')->field($field)->limit(3)->select();
//        foreach ($hots as &$hot) {
//            Goods::formatGoods($hot);
//        }
//        $hot_image = Config::get_value('shop_hot_image');
//        $setin_image = Config::get_value('shop_set_in_image');
        $result = [
            'banners' => $banners,
            'categories' => $categories,
            'new_list' => $newsList,
//            'hots' => $hots,
//            'other_images' => [
//                'hot_images' => $hot_image,
//                'setin_image' => $setin_image
//            ]
        ];
        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result]);
    }

    /**
     * 获取商品列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_goods_list()
    {
        $page = input("post.page", 1);
        $rows = input('post.rows', 10);
        $type = input("post.type", 0);
        $children_id = input("post.children_id", 0);
        $list = Goods::get_goods_list($page, $rows, $type, $children_id);
        return mobileAjaxReturn($list);
    }

    /**
     * 添加购物车
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function add_shop_cart()
    {
        $goods_id = input("post.goods_id");
        $format_id = input('post.format_id', 0);
        $num = input("post.num");
        $result = ShopCart::add_shop_cart($this->member_id, $goods_id, $num, $format_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 获取用户购物车列表数据
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_shop_cart()
    {
        $goods_id = input("post.goods_id");  //商品id
        if (!empty($goods_id)) {
            $num = input("post.num", 1);  //数量
            $format_id = input("post.format_id", 0);  //规格
            $formatInfo = null;

            if ($format_id <= 0) {
                $formatList = GoodsFormat::get_format_list($goods_id);
                if (count($formatList) > 0) {
                    return mobileAjaxReturn(null, lang("请选择商品规格"), ERROR1);
                }
            } else {
                $formatInfo = GoodsFormat::where(['goods_id' => $goods_id, 'id' => $format_id])->field('id,name,goods_price,goods_market,goods_img')->find();
                if (!$formatInfo) {
                    return mobileAjaxReturn(null, lang("商品规格错误，请重新选择"), ERROR1);
                }
            }

            //           $discount = 0;//\app\common\model\OrderTotal::level_discount($this->member_id,$num);
//            $postages_total = ShopConfig::get_value("postage",15);
//            $pay_num = ShopConfig::get_value("goods_pay_num",5);
//            $estimate_rate = ShopConfig::get_value("estimate_rate",1.5);
//            $assessment_num = ShopConfig::get_value("assessment_num",520);
//            $level = \app\common\model\OrderTotal::where(['member_id' => $this->member_id])->value('member_level_id');

            //立即购买返回的商品数据
            $field = "goods_id,goods_title,goods_img,goods_price,goods_postage,category_id,goods_market";
            // goods_discount,goods_market,goods_desc,goods_equal_currency_id,goods_equal_num,goods_rebate_parent_id,goods_rebate_parent,goods_rebate_self_id,goods_rebate_self,,goods_currency_give_id,goods_currency_give_num
            $goods = Goods::where(['goods_id' => $goods_id, 'goods_status' => Goods::STATUS_UP])
//                ->with(['giveCurrency'])
                ->field($field)->find();

            if (!empty($goods)) {
                $temp = $goods;
                unset($goods, $temp['goods_currency_give_id']);
                $img = !empty($temp['goods_img']) ? explode(",", $temp['goods_img']) : null;
                $temp['goods_img'] = !empty($img) ? $img[0] : null;
                if ($format_id > 0) {
                    $temp['goods_price'] = floattostr($formatInfo['goods_price']);
                    $temp['goods_market'] = floattostr($formatInfo['goods_market']);
                    $temp['format'] = $formatInfo;
                } else {
                    $temp['goods_price'] = floattostr($temp['goods_price']);
                    //$temp['goods_price'] = sprintf('%.2f', $temp['goods_price']);
                    $temp['goods_market'] = floattostr($temp['goods_market']);
                }

                $temp['goods_postage'] = floattostr($temp['goods_postage']);
                $goods['goods'] = $temp;
                //$num = 1;
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
//                $giftGoodsId = ShopConfig::get_value('new_gift_goods_id', 1);
//                if ($goods_id == $giftGoodsId) $num = 1;
                $data['num'] = $num;
                $goods['sc_id'] = null;
                $goods['sc_num'] = $num;
//                $data['postage_total'] = $num < $pay_num ? floatval($temp['goods_postage']) : 0;

                $data['postage_total'] = sprintf('%.2f', $temp['goods_postage'] * $num);

                $goods_category = GoodsCategory::where(['id' => $temp['category_id']])->find();
                if (!empty($goods_category)) {
                    $goods_category_pid = GoodsCategory::get($goods_category['pid']);
                    $goods['goods']['category_pid'] = $goods_category_pid['id'];
                    $goods['goods']['category_type'] = $goods_category_pid['name'];
                    if ($goods_category_pid['id'] == 2) {
                        $data['postage_total'] = 0;
                    }
                }
                unset($temp['category_id']);

                //$data['equal_total'] = bcmul($num, $temp['goods_equal_num'], 2);
//                $data['equal_total'] = 0;//2021-01-20修改，去掉代金券抵扣
//                $data['discount'] = $discount;
                $data['total'] = bcmul($temp['goods_price'], $num, 2);
//                $data['goods_total'] = bcsub(bcadd(bcmul($num, $temp['goods_price'], 2), $data['postage_total'], 2), $data['discount'], 2);
                $data['goods_total'] = bcadd($data['total'], $data['postage_total'], 2);
//                $estimate_num = 0;//赠送积分
                //等级大于游客或者消费满520，可获得赠送积分
//                if ($level > 0 || sprintf('%.2f', $data['total']) > sprintf('%.2f', $assessment_num)) {
//                    $estimate_num = keepPoint($data['total'] * $estimate_rate, 2);
//                }
                $data['hm_price'] = ShopConfig::get_value('hm_price', 6.1);
//                $data['estimate_num'] = $estimate_num;
                $data['list'][] = $goods;
                $r['result'] = $data;
                return mobileAjaxReturn($r);
            } else {
                return mobileAjaxReturn(null, lang("goods_have_been_removed"), ERROR1);
            }
        }
        $sc_ids = input("post.sc_id");
        $list = ShopCart::get_shop_cart($this->member_id, $sc_ids);
        return mobileAjaxReturn($list);
    }

    /**
     * 添加一条收货地址
     * @return mixed
     * @throws Exception
     */
    function add_address()
    {
        $name = input("post.name");
        $mobile = input("post.mobile");
        $province = input("post.province");
        $city = input("post.city");
        $area = input("post.area");
        $address = input("post.address");
        $default = input("post.default");
        $result = ShopAddress::add_address($this->member_id, $name, $mobile, $province, $city, $area, $address, $default);
        return mobileAjaxReturn($result);
    }

    public function test()
    {
//        $data = Areas::where('area_type', 1)->field('area_name as text, area_id as value')->select();
//        $cityList = [];
//        foreach ($data as $area) {
//            $citys = Areas::where('area_type', 2)->where('parent_id', $area['value'])->field('area_name as text, area_id as value')->select();
//            foreach ($citys as &$city) {
//                $city['province'] = $area['text'];
//            }
//            $cityList[$area['value']] = $citys;
////            array_push($cityList, [
////                $area['value'] => $citys
////            ]);
//        }

//        $citys = Areas::where('area_type', 2)->field('area_name as text, area_id as value')->select();
//        $areaList = [];
//        foreach ($citys as $city) {
//            $areas = Areas::where('area_type', 3)->where('parent_id', $city['value'])->field('area_name as text, area_id as value')->select();
//            foreach ($areas as $area) {
//                $area['city'] = $city['text'];
//            }
//            $areaList[$city['value']] = $areas;
//        }
//        return mobileAjaxReturn($areaList);
    }

    /**
     * 删除一条收货地址
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    function delete_address()
    {
        $sa_id = input("post.sa_id");
        $result = ShopAddress::delete_address($this->member_id, $sa_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 获取用户的收货地址列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_address_list()
    {
        $result = ShopAddress::get_address_list($this->member_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 修改收货地址
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    function update_address()
    {
        $sa_id = input("post.sa_id");
        $name = input("post.name");
        $mobile = input("post.mobile");
        $province = input("post.province");
        $city = input("post.city");
        $area = input("post.area");
        $address = input("post.address");
        $default = input("post.default");
        $result = ShopAddress::update_address($sa_id, $this->member_id, $name, $mobile, $province, $city, $area, $address, $default);
        return mobileAjaxReturn($result);
    }

    /**
     * 删除一条或者多条购物车数据
     * @return mixed
     */
    function delete_shop_cart()
    {
        $sc_id = input("post.sc_id");
        $result = ShopCart::delete_shop_cart($sc_id, $this->member_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 修改购物车数量
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    function update_shop_cart()
    {
        $sc_id = input("post.sc_id");
        $num = input("post.num");
        $result = ShopCart::update_shop_cart($this->member_id, $sc_id, $num);
        return mobileAjaxReturn($result);
    }

    /**
     * 获取商品详情
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_goods_details()
    {
        $goods_id = input("post.goods_id");
        $retult = Goods::get_goods_details($goods_id, $this->member_id);
        return mobileAjaxReturn($retult);
    }

    /**
     * 获取收货地址详情
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_address_details()
    {
        $sa_id = input("sa_id");
        $result = ShopAddress::get_address_details($this->member_id, $sa_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 设置默认地址
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    function set_default()
    {
        $sa_id = input("sa_id");
        $result = ShopAddress::set_default($this->member_id, $sa_id);

        return mobileAjaxReturn($result);
    }

    /**
     * 获取默认收货地址
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_default()
    {
        $result = ShopAddress::get_default($this->member_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 提交订单
     * @return \json
     * @throws PDOException
     */
    function submit_order()
    {
        $goods_id = input("post.goods_id");
        $num = input("post.num", 1);
        $sc_ids = input("post.sc_id");
        $sa_id = input("post.sa_id", 1);
        $pay_type = input("post.pay_type");
        $group_id = input("post.group_id", 0);
        $format_id = input("post.format_id", 0);
        $remark = input('post.remark');
        $type = input('post.type', 1);
        $receive_name = input('post.receive_name');
        $mobile = input('post.mobile');
        $order_remark = input('post.order_remark');
        if ($type == 2) {
            $r['code'] = ERROR1;
            $r['message'] = '请填写提货人信息';
            $r['result'] = null;
            if (empty($receive_name)) return mobileAjaxReturn($r);
            if (empty($mobile)) return mobileAjaxReturn($r);
        }

        $is_equal = 0;//2021-01-20修改，去掉代金券抵扣
        if (!empty($sc_ids)) {
            //购物车商品支付
            $result = GoodsMainOrders::shop_cart_pay($this->member_id, $sc_ids, $sa_id, $pay_type, $is_equal, $remark, $type, $receive_name, $mobile, $order_remark);
            return mobileAjaxReturn($result);
        }

        //立即购买商品支付
        $result = GoodsMainOrders::buy_now_pay($this->member_id, $goods_id, $sa_id, $pay_type, $num, $is_equal, $group_id, $format_id, $remark, $type, $receive_name, $mobile, $order_remark);
        return mobileAjaxReturn($result);
    }

    /**
     * 支付订单
     * @return mixed
     * @throws PDOException
     */
    function pay_orders()
    {
        $result = [];
        $msg = lang('lan_modifymember_parameter_error');
        $code = ERROR1;

        $gmo_ids = input("post.gmo_id", '');
        $pay_pwd = input("post.pay_pwd");
        $pay_type = input("post.pay_type", 0);
        if (empty($gmo_ids) || empty($pay_type)) {
            return mobileAjaxReturn($result, $msg, $code);
        }
        $payType = ShopPayType::where('id', $pay_type)->find();
        if (in_array($payType['mark'], ['wxpay', 'zfbpay'])) {
            // TODO 微信支付
        } else {
            if (empty($pay_pwd)) {
                return mobileAjaxReturn($result, $msg, $code);
            }
            $chek = Member::verifyPaypwd($this->member_id, $pay_pwd);
            if ($chek['code'] != SUCCESS) {
                return mobileAjaxReturn(null, $chek['message'], ERROR1);
            }
        }
        //订单支付
        return mobileAjaxReturn(GoodsMainOrders::pay_order($this->member_id, $gmo_ids, $pay_type));
    }

    /**
     *  获取订单列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_orders_list()
    {
        $page = input("post.page", 1);
        $rows = input("post.rows", 10);
        $status = input("post.status");
        $result = GoodsMainOrders::get_orders_list($this->member_id, $status, $page, $rows);
        return mobileAjaxReturn($result);
    }

    /**
     * 订单详情
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function orders_detail()
    {
        $gmo_id = input('post.gmo_id');
        if (empty($gmo_id)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => null]);
        }

        return mobileAjaxReturn(GoodsMainOrders::order_detail($gmo_id, $this->member_id));
    }

    /**
     * 虚拟订单商品详情
     * @return \json
     */
    public function store_orders_detail()
    {
        $gmo_id = input('post.gmo_id');
        if (empty($gmo_id)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => null]);
        }

        return mobileAjaxReturn(GoodsMainOrders::store_order_detail($this->member_id, $gmo_id));
    }

    /**
     * 获取支付方式
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    function pay_type()
    {
        $type = input("post.type", 0);//类型 1-普通 2-积分
        if ($type == 1) {// 4种 火米、微信、支付
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => ['in', 'hmpay,wxpay,zfbpay'], 'status' => 1])->order('id asc')->select();
        } else if ($type == 2) {// 1种 金米
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => 'jfpay', 'status' => 1])->order('id asc')->select();
        } else if ($type == 99) {
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => 'xfjf', 'status' => 1])->order('id asc')->select();
        } else if ($type == 16) {
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => ['in', 'hmpay,wxpay,zfbpay'], 'status' => 1])->order('id asc')->select();
        } else if ($type == 17) {
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => ['in', 'wxpay,zfbpay'], 'status' => 1])->order('id asc')->select();
        } else {
            $payTypes = (new ShopPayType)->with(['currency'])->where(['mark' => ['in', 'wxpay,zfbpay,jfpay'], 'status' => 1])->order('id asc')->select();
        }
        $result = [];
        foreach ($payTypes as $payType) {
            $data = [
                'id' => $payType['id'],
                'name' => $payType['name'],
                'is_recommend' => 0,//是否是推荐 1-是 0-否
            ];
            if ($payType['mark'] == 'wxpay') {
                $data['is_recommend'] = 1;
            } else {
                if ($payType['currency_id'] == $payType['other_currency_id']) {
                    $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $payType['currency_id']);
                    $data['money'] = floattostr($currencyUser['num']);
                } else {
                    $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $payType['currency_id']);
                    $othercurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $payType['other_currency_id']);
                    $data['money'] = floattostr($currencyUser['num'] + $othercurrencyUser['num']);
                }
            }

            $result['pay_type'][] = $data;
        }

        $result['djq_money'] = 0;
        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result]);
    }

    /**
     * 确认收货操作
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    function confirm_order()
    {
        $gmo_id = input("post.gmo_id");
        $result = GoodsMainOrders::confirm_order($this->member_id, $gmo_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 取消订单
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    function cancel_order()
    {
        $gmo_id = input("post.gmo_id");
        $result = GoodsMainOrders::cancel_order($this->member_id, $gmo_id);
        return mobileAjaxReturn($result);
    }

    /**
     * 获取用户的购物释放列表记录
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function get_release_list()
    {
        $gmo_id = input("post.gmo_id");
        $page = input("post.page", 1);
        $rows = input("post.rows", 10);
        $result = ShopRelease::get_release_list($this->$this->member_id, $gmo_id, $page, $rows);
        return mobileAjaxReturn($result);
    }

    /**
     * 获取购物车数量
     * @return mixed
     * @throws Exception
     */
    function get_cart_count()
    {
        $count = Db::name("shop_cart")->alias("sc")->where(['sc_user_id' => $this->member_id, 'goods_status' => Goods::STATUS_UP])->
        join(config("database.prefix") . "goods g", "g.goods_id=sc.sc_goods_id", "LEFT")->count("sc_id");
        return mobileAjaxReturn($count, lang("data_success"), SUCCESS);

    }

    /**
     * 申请退款
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refund(Request $request)
    {
        $data = [
            'gmo_id' => $request->post('gmo_id', ''),
            'reason' => $request->post('reason', ''),
            'desc' => $request->post('desc', ''),
            'images' => $request->post('images', '')
        ];

        if (empty($data['gmo_id']) or empty($data['reason'])) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error')]);
        }
        $order = (new GoldMainOrders)->where([
            'gmo_user_id' => $this->member_id,
            'gmo_id' => $data['gmo_id']
        ])->find();
        if (empty($order)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('order_not_exist')]);
        }
        $refundFlag = OrdersRefunds::where(['gmo_id' => $data['gmo_id'], 'user_id' => $this->member_id])->value('id');
        if (!empty($refundFlag)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('order_requested_refund')]);
        }
        // 上传图片
        $images = [];
//        $data['images'] = $request->post('images') ? $request->post('images') : [];
        if (!empty($data['images']) and is_array($data['images'])) {
            $up = $this->oss_base64_upload($data['images'], 'refund');
            if (0 == $up['Code']) {
                return mobileAjaxReturn(['code' => ERROR2, 'message' => lang('system_error_please_try_again_later')]);
            }
            $images = $up['Msg'];
        }
        try {
            // 入库
            $data['number'] = "R" . date("YmdHi") . randNum();
            $data['images'] = json_encode($images);
            $data['user_id'] = $this->member_id;
            $data['create_time'] = time();
            $res = OrdersRefunds::add($data);
            if (!$res) {
                throw new Exception(lang('system_error_please_try_again_later'));
                // return mobileAjaxReturn(['code' => ERROR3, 'message' => lang('system_error_please_try_again_later')]);
            }
            $order['gmo_status_refund'] = GoldMainOrders::REFUND_STATUS_ING; // 设置状态为退款中
            $order->save();
        } catch (Exception $exception) {
            return mobileAjaxReturn(['code' => ERROR5, 'message' => $exception->getMessage(), 'result' => null]);
        }

        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('lan_operation_success'), 'result' => null]);
    }

    /**
     * 退款信息
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refund_goods_info(Request $request)
    {
        $gmo_id = $request->post('gmo_id');
        if (empty($gmo_id)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error')]);
        }
        $order = (new GoldMainOrders)->where([
            'gmo_user_id' => $this->member_id,
            'gmo_id' => $gmo_id
        ])->find();
        if (empty($order)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('order_not_exist')]);
        }
        // 查询订单信息
        $orders = GoldOrders::where('go_id', 'in', $order['gmo_go_id'])->field('go_id, go_title, go_num, go_img')->where('go_user_id', $this->member_id)->select();
        $data = [
            'code' => $order['gmo_code'],
            'add_time' => date("Y-m-d H:i:s", $order['gmo_add_time']),
            'goods' => $orders
        ];

        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'data' => $data]);
    }

    /**
     * 退款详情
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refund_detail(Request $request)
    {
        $gmo_id = $request->post('gmo_id');
        if (empty($gmo_id)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error')]);
        }
        $order = (new GoldMainOrders)->with(['currency', 'otherCurrency'])->where(['gmo_id' => $gmo_id, 'gmo_user_id' => $this->member_id])->find();
        if (empty($order)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('order_not_exist'), 'result' => null]);
        }

        $refundInfo = (new OrdersRefunds)->where(['gmo_id' => $gmo_id, 'user_id' => $this->member_id])
            ->field('id, number, reason, desc, images, rejects, express_code, express_company, express_phone, refund_phone, refund_address, refund_name, create_time')
            ->find();
        if (empty($refundInfo)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('refund_info_not_exist'), 'result' => null]);
        }
        $refundInfo['create_time'] = date("Y-m-d H:i:s", $refundInfo['create_time']);
        $refundInfo['images'] = json_decode($refundInfo['images']);
        $goodsInfo = (new GoldOrders)->where('go_id', 'in', $order['gmo_go_id'])->field('go_id, go_title, go_num, go_img')->where('go_user_id', $this->member_id)->select();

        $data = [
            'refund_info' => $refundInfo,
            'goods_info' => $goodsInfo,
            'order_info' => [
                'refund_payment_number' => $order['gmo_payment_number'],
                'refund_other_payment_number' => $order['gmo_other_payment_number'],
                'currency_name' => $order['currency']['currency_name'],
                'other_currency_name' => $order['otherCurrency']['currency_name'],
                'order_code' => $order['gmo_code'],
                'order_time' => date("Y-m-d H:i:s", $order['gmo_add_time']),
                'order_status' => $order['gmo_status'],
                'order_status_refund' => $order['gmo_status_refund']
            ]
        ];

        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'data' => $data]);
    }

    /**
     * 填写物流退款信息
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function refund_logistics(Request $request)
    {
        $gmo_id = $request->post('gmo_id');
        $expressCode = $request->post('express_code');
        $expressCompany = $request->post('express_company');
        $phone = $request->post('phone');
        $response = ['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => null];

        if (empty($gmo_id) or empty($expressCode) or empty($expressCompany) or empty($phone)) {
            return mobileAjaxReturn($response);
        }
        $order = (new GoldMainOrders)->with(['currency', 'otherCurrency'])->where(['gmo_id' => $gmo_id, 'gmo_user_id' => $this->member_id])->find();
        if (empty($order)) {
            $response['message'] = lang('order_not_exist');
            return mobileAjaxReturn($response);
        }
        if (GoldMainOrders::REFUND_STATUS_COMPLETE != $order['gmo_status_refund']) {
            $response['message'] = lang('goods_status_not_complete');
            return mobileAjaxReturn($response);
        }
        $result = OrdersRefunds::where([
            'gmo_id' => $gmo_id,
            'user_id' => $this->member_id
        ])->update([
            'express_code' => $expressCode,
            'express_company' => $expressCompany,
            'express_phone' => $phone
        ]);
        if ($result === false) {
            $r['message'] = lang('system_error_please_try_again_later');
            return mobileAjaxReturn($response);
        }
        $response['code'] = SUCCESS;
        $response['message'] = lang('lan_operation_success');
        return mobileAjaxReturn($response);
    }

    /**
     * 火米数量
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    function get_integral(Request $request)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $give_currency_id = ShopConfig::get_value('give_currency_id', 5);
        if (!$give_currency_id) $this->output_new($r);
        $currency_user = CurrencyUser::getCurrencyUser($this->member_id, $give_currency_id);
        if (empty($currency_user)) $this->output_new($r);
        $res_huo = CurrencyUser::alias('a')->join('currency b', 'a.currency_id=b.currency_id')
            ->field('a.num_award as num, b.currency_id,b.currency_name')
            ->where(['a.currency_id' => $give_currency_id, 'member_id' => $this->member_id])->find();
        if (empty($res_huo)) $this->output_new($r);

        $r['code'] = SUCCESS;
        $r['result'] = $res_huo;
        $r['message'] = lang('data_success');
        $this->output_new($r);
    }

    /**
     * 申请信用额度
     * @param Request $request
     * @throws PDOException
     */
    function huomi_apply(Request $request)
    {
        $num = $request->post('num');
        $res = Goods::huo_mi_apply($this->member_id, $num);
        $this->output_new($res);
    }

    /**
     * 火米明细记录
     * @param Request $request
     */
    function huomi_log(Request $request)
    {
        $page = $request->post('page');
        $res = \app\common\model\CurrencyLockBook::get_list($this->member_id, 'num_award', '', '', $page);
        $this->output_new($res);
    }

    /**
     * 公告列表
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function new_list(Request $request)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_not_data");
        $r['result'] = [];
        $page = input('post.page', 1, 'intval');
        $rows = input('post.rows', 10, 'intval');

        $field = 'article_id,title,content,art_pic,add_time';
        $type = 12;
        $article = Db::name('article')->field($field)->where(['position_id' => $type])->order('add_time desc')->limit(($page - 1) * $rows, $rows)->select();
        if ($article) {
            foreach ($article as $key => $val) {
                $lenth = strlen($val['title']);
                if ($lenth >= 15) {
                    $article[$key]['title'] = strip_tags(html_entity_decode($val['title']));
                } else {
                    $article[$key]['title'] = trim(strip_tags(html_entity_decode($val['title'])));
                }
                $article[$key]['add_time'] = empty($val['add_time']) ? "" : date('Y-m-d H:i:s', $val['add_time']);
            }
            $r['result'] = $article;
            $r['message'] = lang("lan_data_success");
            $r['code'] = SUCCESS;
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 订单物流详情
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function orders_logistics(Request $request)
    {
        $gmo_id = input('post.gmo_id');
        if (empty($gmo_id)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => null]);
        }

        return mobileAjaxReturn(GoodsMainOrders::ordersLogistics($gmo_id, $this->member_id));
    }

    //获取商城分类
    public function get_category(Request $request) {
        $type = input('type');
        // 菜单栏
        $categories = GoodsCategory::get_category_list($type);
        if (empty($categories)) {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('not_data'), 'result' => null]);
        }

        return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $categories]);
    }
}
