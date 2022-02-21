<?php

namespace app\common\model;

use Alipay\Alipay;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;
use WeChat\WeChatPay;

class GoodsMainOrders extends Model
{

    const STATUS_PAID = 1;
    const STATUS_WAIT_PAYMENT = 2;
    const STATUS_SHIPPED = 3;
    const STATUS_COMPLETE = 4;
    const STATUS_CANCEL = 5;
    const STATUS_WAIT_USE = 6;
//    const STATUS_REFUND = 5;

    const STATUS_ENUM = [
        self::STATUS_PAID => "已付款",
        self::STATUS_WAIT_PAYMENT => "待付款",
        self::STATUS_SHIPPED => "已发货",
        self::STATUS_COMPLETE => "已完成",
        self::STATUS_CANCEL => "已取消",
        self::STATUS_WAIT_USE => '待提货'
//        self::STATUS_REFUND => '退款中'
    ];

    const REFUND_STATUS_NONE = 0;
    const REFUND_STATUS_ING = 1;
    const REFUND_STATUS_CANCEL = 2;
    const REFUND_STATUS_COMPLETE = 3;
    const REFUND_STATUS_REFUSE = 4;

    const REFUND_ENUM = [
        self::REFUND_STATUS_NONE => "无退款",
        self::REFUND_STATUS_ING => "退款中",
        self::REFUND_STATUS_CANCEL => "取消退款",
        self::REFUND_STATUS_COMPLETE => "退款完成",
        self::REFUND_STATUS_REFUSE => "拒绝退款"
    ];

    public function payCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_pay_currency_id')->field('currency_id, currency_name');
    }

    public function equalCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_equal_currency_id')->field('currency_id, currency_name');
    }

    public function rebateParentCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_rebate_parent_id')->field('currency_id, currency_name');
    }

    public function rebateSelfCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_rebate_self_id')->field('currency_id, currency_name');
    }

    public function giveCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_give_currency_id')->field('currency_id, currency_name');
    }

    public function paytype()
    {
        return $this->belongsTo(ShopPayType::class, 'gmo_pay_type')->field('id, mark, name');
    }

    //根据 admin_id  分片生成多订单
    public static function split_add_orders($user_id, $sc_ids, $sa_id, $pay_type, $remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (!isInteger($user_id) && empty($sc_ids) && !isInteger($sa_id)) {
            return $r;
        }
        // 根据 admin_id  分片生成多订单
        $sc_ids = str_replace("，", ",", $sc_ids);
        $scid = explode(",", $sc_ids);
        $shopCarts = ShopCart::where('sc_id', 'in', $scid)->where('sc_user_id', $user_id)->select();
        $splitShopCarts = [];
        foreach ($shopCarts as $key => $shopCart) {
            if (!isset($splitShopCarts[$shopCart['sc_admin_id']])) {
                $splitShopCarts[$shopCart['sc_admin_id']] = []; // Fix PHP^7 Warning
            }
            array_push($splitShopCarts[$shopCart['sc_admin_id']], $shopCart->toArray());
        }
        if (!count($splitShopCarts) > 0) {
            return $r;
        }
        $gmo_ids = [];
        foreach ($splitShopCarts as $key => $splitShopCart) {
            $new_sc_ids = implode(",", array_column($splitShopCart, 'sc_id'));
            $result = self::add_orders($user_id, $new_sc_ids, $sa_id, $pay_type, $remark);
            if (SUCCESS != $result['code']) {
                return $result;
            }
            array_push($gmo_ids, $result['result']['gmo_id']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['result'] = ['gmo_ids' => $gmo_ids];
        return $r;
    }

    /**
     * 批量创建订单(调用此方法需要开启事务回滚)
     * @param int $user_id 用户id
     * @param int $sc_ids 购物车表id，多个用","隔开
     * @param int $sa_id 收货地址表id
     * @param int $pay_type 支付方式
     * @param int $is_equal 是否抵扣 1-抵扣 0-不抵扣
     * @param string $remark 订单备注
     * @return mixed
     * @throws
     */
    static function add_orders_old($user_id, $sc_ids, $sa_id, $pay_type, $is_equal = 1, $remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($sc_ids) && isInteger($sa_id)) {
            $address = ShopAddress::where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
            //收货地址
            if (empty($address)) {
                $r['message'] = lang("incorrect_delivery_address_information");
                return $r;
            }

            $sc_ids = str_replace("，", ",", $sc_ids);
            $scid = explode(",", $sc_ids);
//            $cart_field = "sc_num,goods_title,goods_id,goods_title,goods_price,goods_currency_id,goods_img,goods_currency_type";
            $cart_field = "sc.*,g.*,f.goods_price as goods_format_price,f.goods_market as goods_format_market";
            $shop_cart_list = Db::name("shop_cart")->alias("sc")->field($cart_field)->where(['g.goods_status' => Goods::STATUS_UP])->where("sc_id", "in", $scid)->
            join(config("database.prefix") . "goods g", "g.goods_id=sc.sc_goods_id", "LEFT")->
            join(config("database.prefix") . "goods_format f", "f.id=sc.sc_format_id", "LEFT")
                ->order('sc_id', 'desc')
                ->select();
            try {
                if ($shop_cart_list) {
                    Db::startTrans();
                    $payType = ShopPayType::where('id', $pay_type)->find();
                    $object_array = null;
                    $add_list = null;
                    $pay_currency_id = null;
                    $other_pay_currency_id = null;
                    $other_pay_currency_type = null;
                    $gmo_ids = [];
                    $total_price = 0;
                    $djqMoneyNum = 0;
                    $djqCurrencyId = ShopConfig::get_value('equal_currency_id', Currency::where('currency_mark', 'DJQ')->value('currency_id') ?: 75);
                    $djqCurrencyUser = null;
                    if ($is_equal) {//代金券抵扣
                        //代金券
                        $djqCurrencyUser = CurrencyUser::getCurrencyUser($user_id, $djqCurrencyId);
                        $djqMoneyNum = $djqCurrencyUser['num'];
                    }
                    $closeTime = ShopConfig::get_value('orders_close_time', 30);
                    foreach ($shop_cart_list as $value) {

                        $pay_currency_id = $payType['currency_id'];
                        $data = [];
                        $data['go_user_id'] = $user_id;
                        $data['go_title'] = $value['goods_title'];
                        $data['go_goods_id'] = $value['goods_id'];
                        $data['go_format_id'] = $value['sc_format_id'];
                        $data['go_num'] = $value['sc_num'];
                        $data['go_code'] = GoodsOrders::create_code();
                        $data['go_add_time'] = time();
                        $data['go_total_price'] = bcmul($value['sc_num'], $value['goods_price'], 2);
                        $data['go_price'] = $value['goods_price'];
                        $data['go_market_price'] = $value['goods_market'];
                        if ($value['sc_format_id'] > 0) {
                            $data['go_total_price'] = bcmul($value['sc_num'], $value['goods_format_price'], 2);
                            $data['go_price'] = $value['goods_format_price'];
                            $data['go_market_price'] = $value['goods_format_market'];
                        }
                        $data['go_img'] = $value['goods_img'];
                        $data['go_postage'] = $value['goods_postage'];
                        //$data['go_admin_id'] = $value['goods_admin_id'];
                        Goods::where('goods_id', $value['goods_id'])->setInc('goods_sale_number', $value['sc_num']);
                        //$add_list[] = $data;

                        //添加子订单
                        $child_order = new GoodsOrders();
                        $add_result = $child_order->insertGetId($data);

                        if ($add_result) {
                            $gmo_go_ids = $add_result;
                            $go_num = $value['sc_num'];
                            $gmo_total_price = $data['go_total_price'];
                            $gmo_total_postage = $value['goods_postage'];
                            $gmo_total_equal = 0;
                            if ($is_equal && $djqMoneyNum > 0) {//代金券抵扣
                                $gmo_total_equal = keepPoint(min(bcmul($go_num, $value['goods_equal_num'], 2), $djqMoneyNum), 2);
                                if ($gmo_total_equal > 0) $djqMoneyNum -= $gmo_total_equal;
                            }
                            $pay_num = bcsub(bcadd($gmo_total_price, $gmo_total_postage, 2), $gmo_total_equal, 2);

                            $total_price += $pay_num;

                            if (empty($gmo_go_ids) || $go_num <= 0 || $gmo_total_price < 0) {
                                $r['code'] = ERROR2;
                                throw new Exception(lang("operation_failed_try_again"));
                            }

                            $gmo_data['gmo_user_id'] = $user_id;
                            $gmo_data['gmo_go_id'] = $gmo_go_ids;
                            $gmo_data['gmo_num'] = $go_num;
                            $gmo_data['gmo_code'] = self::create_code();
                            $gmo_data['gmo_add_time'] = time();
                            $gmo_data['gmo_close_time'] = $gmo_data['gmo_add_time'] + $closeTime * 60;
                            $gmo_data['gmo_total_price'] = $gmo_total_price;
                            $gmo_data['gmo_pay_currency_id'] = $pay_currency_id;
                            $gmo_data['gmo_equal_currency_id'] = $value['goods_equal_currency_id'];
                            $gmo_data['gmo_equal_num'] = $gmo_total_equal;
                            $gmo_data['gmo_rebate_parent_id'] = $value['goods_rebate_parent_id'];
                            $gmo_data['gmo_rebate_parent'] = bcmul($go_num, $value['goods_rebate_parent'], 2);
                            $gmo_data['gmo_rebate_self_id'] = $value['goods_rebate_self_id'];
                            $gmo_data['gmo_rebate_self'] = bcmul($go_num, $value['goods_rebate_self'], 2);
                            $gmo_data['gmo_give_currency_id'] = $value['goods_give_currency_id'];
                            $gmo_data['gmo_give_num'] = bcmul($go_num, $value['goods_give_num'], 2);
                            $gmo_data['gmo_market_price'] = $value['goods_market'];
                            $gmo_data['gmo_pay_type'] = $payType['id'];
                            $gmo_data['gmo_remark'] = $remark;
                            //$gmo_data['gmo_admin_id'] = isset($gmo_admin_id) ? $gmo_admin_id : 0;
                            /**
                             * 初始化待付款
                             */
                            $gmo_data['gmo_status'] = self::STATUS_WAIT_PAYMENT;//待发货
                            $gmo_data['gmo_pay_num'] = $pay_num;
                            $gmo_data['gmo_pay_postage'] = $gmo_total_postage; // 待付邮费

                            $gmo_data['gmo_receive_name'] = $address->sa_name;
                            $gmo_data['gmo_mobile'] = $address->sa_mobile;
                            // $gmo_data['gmo_address'] = $address->sa_province . " " . $address->sa_city . " " . $address->sa_area . " " . $address->sa_address;
                            $gmo_data['gmo_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
                            $gmo_id = self::insertGetId($gmo_data);
                            if ($gmo_id > 0) {
                                //删除购物车数据
                                //$delete = Db::name("shop_cart")->where("sc_id", "in", $scid)->delete();
                                $delete = Db::name("shop_cart")->where("sc_id", $value['sc_id'])->delete();
                                if (!$delete) {
                                    $r['code'] = ERROR6;
                                    throw new Exception(lang("operation_failed_try_again"));
                                }

                                //代金券
                                if ($gmo_total_equal > 0) {
                                    $djqCurrencyUser = CurrencyUser::getCurrencyUser($user_id, $djqCurrencyId);
                                    //添加帐本
                                    $flag = AccountBook::add_accountbook($user_id, $djqCurrencyId, 117, "shopping_equal", "out", $gmo_total_equal, $gmo_id);
                                    if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                                    //减少资产
                                    $flag = CurrencyUser::where(['cu_id' => $djqCurrencyUser['cu_id'], 'num' => $djqCurrencyUser['num']])->setDec('num', $gmo_total_equal);
                                    if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                                }

                                array_push($gmo_ids, intval($gmo_id));
                            } else {
                                $r['code'] = ERROR3;
                                throw new Exception(lang("operation_failed_try_again"));
                            }
                        } else {
                            $r['code'] = ERROR4;
                            throw new Exception(lang("operation_failed_try_again"));
                        }
                    }
                    Db::commit();
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("successful_operation");
                    $r['result'] = ['ids' => join(',', $gmo_ids), 'total_price' => $total_price];
                }
            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

    /**
     * 批量创建订单(调用此方法需要开启事务回滚)
     * @param int $user_id 用户id
     * @param int $sc_ids 购物车表id，多个用","隔开
     * @param int $sa_id 收货地址表id
     * @param int $pay_type 支付方式
     * @param int $is_equal 是否抵扣 1-抵扣 0-不抵扣
     * @param string $remark 订单备注
     * @return mixed
     * @throws
     */
    static function add_orders($user_id, $sc_ids, $sa_id, $pay_type, $is_equal = 1, $remark = '', $type, $receive_name = '', $mobile = '', $order_remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (isInteger($user_id) && !empty($sc_ids) && isInteger($sa_id)) {
            if (in_array($type, [1,3])) {
                $address = ShopAddress::where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
                //收货地址
                if (empty($address)) {
                    $r['message'] = lang("incorrect_delivery_address_information");
                    return $r;
                }
            } elseif ($type == 2) {
                $address = ShopPickedUp::where(['sa_id' => $sa_id])->find();
                //收货地址
                if (empty($address)) {
                    $r['message'] = lang("请选择自提点");
                    return $r;
                }
            }

            $sc_ids = str_replace("，", ",", $sc_ids);
            $scid = explode(",", $sc_ids);
            $cart_field = "sc.*,g.*,f.goods_price as goods_format_price,f.goods_market as goods_format_market";
            $shop_cart_list = Db::name("shop_cart")->alias("sc")->field($cart_field)
                ->where(['g.goods_status' => Goods::STATUS_UP])
                ->where("sc_id", "in", $scid)->join("goods g", "g.goods_id=sc.sc_goods_id", "LEFT")
                ->join("goods_format f", "f.id=sc.sc_format_id", "LEFT")
                ->order('sc_id', 'desc')
                ->select();
            if (!$shop_cart_list) return $r;

            try {
                Db::startTrans();
                $payType = ShopPayType::where('id', $pay_type)->find();
                $object_array = null;
                $add_list = null;

                $gmo_ids = [];
                $closeTime = ShopConfig::get_value('orders_close_time', 30);
                $go_num = 0;
                $gmo_total_price = 0;
                $gmo_total_postage = 0;
                $gmo_data['gmo_market_price'] = 0;
                $gmo_data['gmo_give_num'] = 0;
                foreach ($shop_cart_list as $value) {
                    $go_num += $value['sc_num'];
                    if ($value['sc_format_id'] > 0) {
                        $gmo_total_price += bcmul($value['sc_num'], $value['goods_format_price'], 2);
                        $gmo_data['gmo_market_price'] += $value['goods_format_market'];
                    } else {
                        $gmo_total_price += bcmul($value['sc_num'], $value['goods_price'], 2);
                        $gmo_data['gmo_market_price'] += bcmul($value['sc_num'], $value['goods_market'], 2);
                    }
                    if (in_array($type, [1,3])) {//物流
                        $gmo_total_postage = keepPoint($value['goods_postage'] * $value['sc_num'], 2);
                    }
                    $gmo_data['gmo_give_num'] += $value['goods_currency_give_num'];
                }
                if ($go_num <= 0 || $gmo_total_price < 0) {
                    $r['code'] = ERROR2;
                    throw new Exception(lang("operation_failed_try_again"));
                }

                $gmo_data['gmo_user_id'] = $user_id;
                $gmo_data['gmo_go_id'] = 0;
                $gmo_data['gmo_num'] = $go_num;
                $gmo_data['gmo_code'] = self::create_code();
                $gmo_data['gmo_add_time'] = time();
                $gmo_data['gmo_close_time'] = $gmo_data['gmo_add_time'] + $closeTime * 60;
                $gmo_data['gmo_total_price'] = $gmo_total_price;
                $gmo_data['gmo_pay_currency_id'] = $payType['currency_id']; // 支付的首选币种
                $gmo_data['gmo_give_currency_id'] = ShopConfig::get_value('give_currency_id', 0);
                $gmo_data['gmo_pay_type'] = $payType['id'];
                $gmo_data['gmo_remark'] = $order_remark;
                $gmo_data['gmo_pickedup_remark'] = $remark;
                $gmo_data['gmo_es_time'] = Config::get_value('arrival_time', 10);
                $gmo_data['is_upload'] = 1;//未上传星链
                /**
                 * 初始化待付款
                 */
                $gmo_data['gmo_status'] = self::STATUS_WAIT_PAYMENT;

                if (in_array($type, [1,3])) {//物流
                    $gmo_data['gmo_address_id'] = $sa_id;
                    $gmo_data['gmo_receive_name'] = $address->sa_name;
                    $gmo_data['gmo_mobile'] = $address->sa_mobile;
                    $gmo_data['gmo_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
                } elseif ($type == 2) {//自提
                    $gmo_data['gmo_pickedup_id'] = $sa_id;
                    $gmo_data['gmo_pickedup_title'] = $address->sa_name;
                    $gmo_data['gmo_pickedup_name'] = $receive_name;
                    $gmo_data['gmo_pickedup_mobile'] = $mobile;
                    $gmo_data['gmo_pickedup_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, 0) . $address->sa_address;
                }

                $gmo_data['gmo_pay_num'] = bcadd($gmo_total_price, $gmo_total_postage, 2);
                $gmo_data['gmo_pay_postage'] = $gmo_total_postage; // 待付邮费
                $gmo_data['gmo_express_type'] = $type;//1物流 2自提 3门店
                $gmo_id = self::insertGetId($gmo_data);

                if ($gmo_id > 0) {
                    foreach ($shop_cart_list as $value) {
                        $data = [];
                        $data['go_user_id'] = $user_id;
                        $data['go_main_id'] = $gmo_id;
                        $data['go_title'] = $value['goods_title'];
                        $data['go_goods_id'] = $value['goods_id'];
                        $data['go_format_id'] = $value['sc_format_id'];
                        $data['go_num'] = $value['sc_num'];
                        $data['go_code'] = GoodsOrders::create_code();
                        $data['go_add_time'] = time();
                        $data['go_total_price'] = bcmul($value['sc_num'], $value['goods_price'], 2);
                        $data['go_price'] = $value['goods_price'];
                        $data['go_market_price'] = $value['goods_market'];
                        if ($value['sc_format_id'] > 0) {
                            $data['go_total_price'] = bcmul($value['sc_num'], $value['goods_format_price'], 2);
                            $data['go_price'] = $value['goods_format_price'];
                            $data['go_market_price'] = $value['goods_format_market'];
                        }
                        $data['go_img'] = $value['goods_img'];
                        $data['go_postage'] = 0;
                        if (in_array($type, [1,3])) {//物流
                            $data['go_postage'] = keepPoint($value['goods_postage'] * $value['sc_num'], 2);
                        }

                        Goods::where('goods_id', $value['goods_id'])->setInc('goods_sale_number', $value['sc_num']);

                        //添加子订单
                        $child_order = new GoodsOrders();
                        $add_result = $child_order->insertGetId($data);
                        if (!$add_result) {
                            $r['code'] = ERROR4;
                            throw new Exception(lang("operation_failed_try_again"));
                        }

                        //删除购物车数据
                        $delete = Db::name("shop_cart")->where("sc_id", $value['sc_id'])->delete();
                        if (!$delete) {
                            $r['code'] = ERROR6;
                            throw new Exception(lang("operation_failed_try_again"));
                        }
                    }
                    array_push($gmo_ids, intval($gmo_id));
                } else {
                    $r['code'] = ERROR3;
                    throw new Exception(lang("operation_failed_try_again"));
                }
                Db::commit();
                $r['code'] = SUCCESS;
                $r['message'] = lang("successful_operation");
                $r['result'] = ['ids' => join(',', $gmo_ids), 'total_price' => $gmo_data['gmo_pay_num'], 'gmo_code' => $gmo_data['gmo_code']];

            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

    /**
     * 创建一个不存在数据库中的订单编号
     * @return string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function create_code()
    {
        $code = date("YmdHi") . randNum();
        $find = self::where(['gmo_code' => $code])->field("gmo_id")->find();
        if (empty($find)) {
            return $code;
        } else {
            return self::create_code();
        }
    }

    /**
     * 创建一个不存在数据库中的预约码
     * @return string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function create_subscribe_code()
    {
        $code = randNum(10);
        $find = self::where(['subscribe_code' => $code])->field("gmo_id")->find();
        if (empty($find)) {
            return $code;
        } else {
            return self::create_code();
        }
    }

    /**
     * 支付主订单(调用此方法需要开启事务回滚)
     * @param int $user_id 用户id
     * @param int $gmo_ids 订单id 多个中间有","隔开
     * @param int $pay_type 支付方式id
     * @return mixed
     */
    static function pay_order($user_id, $gmo_ids, $pay_type = 0)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $pay_type > 0) {
            $res = (new self)->where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_ids, 'gmo_status' => self::STATUS_WAIT_PAYMENT])->find();
            if (!$res) return $r;

            $payType = ShopPayType::where('id', $pay_type)->find();
            if ($payType) {
                // 支付方式不相同时，重新计算支付金额
                if ($res['gmo_pay_type'] != $pay_type) {
                    if (in_array($payType['mark'], ['hmpay', 'jfpay'])) {
                        $payNum = $res['gmo_total_price'] + $res['gmo_pay_postage'];
                        $hm_price = ShopConfig::get_value('hm_price', 6.1);
//                        $pay_num = keepPoint($payNum / $hm_price, 4);
                        $pay_num = sprintf("%.4f", $payNum / $hm_price);
                    } else {
                        $pay_num = $res['gmo_total_price'] + $res['gmo_pay_postage'];
                    }
                    $flag = self::where(['gmo_id' => $gmo_ids])->update(['gmo_pay_num' => $pay_num, 'gmo_pay_type' => $pay_type]);
                    if (!$flag) {
                        return $r;
                    }
                }
                $res = (new self)->where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_ids, 'gmo_status' => self::STATUS_WAIT_PAYMENT])->find();
                $mainOrders = (new self)->where(['gmo_user_id' => $user_id, 'gmo_id' => ['in', $gmo_ids], 'gmo_status' => self::STATUS_WAIT_PAYMENT])->select();
                $totalPayNum = 0;
                $totalPostage = 0;
                if ($payType['mark'] == 'wxpay') {
                    foreach ($mainOrders as $order) {
                        $totalPayNum += $order['gmo_total_price'];
                        $totalPostage += $order['gmo_pay_postage'];
                    }
                    // TODO 微信支付
                    $payApi = new \yunfastpay\UnifiedPayTrans();
                    $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'wxpay', $res['gmo_code']);
                    if (!$result) {
                        $r['message'] = '对不起请检查相关参数!';
                        return $r;
                    }
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("payment_successful");
                    $r['result'] = [
                        'ids' => $gmo_ids,
                        //'order_code' => $order['gmo_code'],
                        'order_code' => '',
                        'pay_postage' => floatval($totalPostage), // 邮费
                        'pay_number' => floatval($totalPayNum),
                        'wx_pay' => null,
                        'pay_url' => $result
                    ];
                } elseif ($payType['mark'] == 'zfbpay') {
                    foreach ($mainOrders as $order) {
                        $totalPayNum += $order['gmo_total_price'];
                        $totalPostage += $order['gmo_pay_postage'];
                    }
                    $gmo_code = self::create_code();
                    $flag = self::where(['gmo_id' => $res['gmo_id'], 'gmo_code' => $res['gmo_code']])->update(['gmo_code' => $gmo_code]);
                    if ($flag === false) {
                        $r['message'] = '重新生成订单号失败';
                        return $r;
                    }
                    $res['gmo_code'] = $gmo_code;
                    // TODO 支付宝支付
                    $payApi = new \yunfastpay\ScanPayTrans();
                    $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'alipay', $res['gmo_code']);
                    if (!$result) {
                        $r['message'] = '对不起请检查相关参数!';
                        return $r;
                    }
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("payment_successful");
                    $r['result'] = $r['result'] = [
                        'pay_postage' => floatval($totalPostage), // 邮费
                        'pay_number' => floatval($totalPayNum),
                        'wx_pay' => null,
                        'pay_url' => $result
                    ];
                } else {
                    try {
                        Db::startTrans();
                        if (count($mainOrders)) {
                            foreach ($mainOrders as $order) {
                                $payNum = $order['gmo_pay_num'];
                                $totalPayNum += $payNum;
                                $totalPostage += $order['gmo_pay_postage'];

                                //判断首选币种和组合币种相同
                                if ($payType['currency_id'] == $payType['other_currency_id']) {
                                    $currency_user = CurrencyUser::getCurrencyUser($user_id, $payType['currency_id']);
                                    $available_num = $currency_user['num'];
                                } else {
                                    $currency_user = CurrencyUser::getCurrencyUser($user_id, $payType['currency_id']);
                                    $other_currency_user = CurrencyUser::getCurrencyUser($user_id, $payType['other_currency_id']);
                                    $available_num = keepPoint($currency_user['num'] + $other_currency_user['num'], 2);
                                }
                                if (bccomp($available_num, $payNum, 4) < 0) {
                                    throw new Exception(lang("insufficient_balance"));
                                } else {
                                    //判断首选币种和组合币种相同
                                    if ($payType['currency_id'] == $payType['other_currency_id']) {
                                        //添加帐本
                                        $flag = AccountBook::add_accountbook($user_id, $payType['currency_id'], 115, "shopping", "out", $payNum, $order['gmo_id']);
                                        if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                                        //减少资产
                                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $payNum);
                                        if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                                    } else {
                                        //添加帐本（首选币种）
                                        if (sprintf('%.2f', $currency_user['num']) >= sprintf('%.2f', $payNum)) {
                                            $fristPayNum = $payNum;
                                            $secondPayNum = 0;
                                        } else {
                                            $fristPayNum = $currency_user['num'];
                                            $secondPayNum = keepPoint($payNum - $currency_user['num'], 2);
                                        }
                                        $flag = AccountBook::add_accountbook($user_id, $payType['currency_id'], 115, "shopping", "out", $fristPayNum, $order['gmo_id']);
                                        if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                                        //减少资产（首选币种）
                                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $fristPayNum);
                                        if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                                        if ($secondPayNum > 0) {
                                            //添加帐本（组合币种）
                                            $flag = AccountBook::add_accountbook($user_id, $payType['other_currency_id'], 115, "shopping", "out", $secondPayNum, $order['gmo_id']);
                                            if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                                            //减少资产（组合币种）
                                            $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $secondPayNum);
                                            if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                                        }
                                    }
                                    $gmo_status = self::STATUS_PAID;//已付款
                                    $gmo_auto_sure_time = 0;
                                    if ($order['gmo_express_type'] == 3) {
                                        //自提区，支付成功后，订单状态改成已发货
                                        $gmo_status = self::STATUS_SHIPPED;//已发货
                                        $auto_sure_time = ShopConfig::get_value('auto_sure_time', 10);
                                        $gmo_auto_sure_time = time() + $auto_sure_time * 86400;
                                    }
                                    //修改支付状态为已支付
                                    $update = [
                                        'gmo_status' => $gmo_status, // 订单状态
                                        'gmo_pay_type' => $pay_type,
                                        'gmo_pay_num' => $payNum,
                                        'gmo_pay_currency_id' => $payType['currency_id'],
                                        'gmo_pay_time' => time(),
                                        'gmo_auto_sure_time' => $gmo_auto_sure_time
                                    ];

                                    //更新订单
                                    $flag = self::where(['gmo_id' => $order['gmo_id']])->update($update);
                                    if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                                }
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("payment_successful");
                            $r['result'] = [
                                'ids' => $gmo_ids,
                                'order_code' => $order['gmo_code'],
                                'pay_postage' => floatval($totalPostage), // 邮费
                                'pay_number' => floatval($totalPayNum),
                            ];
                        }
                        Db::commit();
                    } catch (Exception $exception) {
                        Db::rollback();
                        $r['message'] = $exception->getMessage();
                    }
                }
            }
        }
        return $r;
    }

    /**
     * 微信支付(微信支付回调调用)
     * @param int $user_id 用户id
     * @param int $gmo_ids 订单id 多个中间勇","隔开
     * @param string $gmo_pay_code 支付订单编号(第三方支付)
     * @return mixed
     */
    static function wx_pay_order($user_id, $gmo_ids, $gmo_pay_code, $mark = 'wxpay')
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        if (isInteger($user_id) && !empty($gmo_ids) && !empty($gmo_pay_code)) {
            $mainOrders = (new self)->where(['gmo_user_id' => $user_id, 'gmo_id' => ['in', $gmo_ids], 'gmo_status' => self::STATUS_WAIT_PAYMENT])->select();
            if (count($mainOrders) <= 0) return $r;

            $payType = ShopPayType::where('mark', $mark)->find();
            if (!$payType) return $r;

            try {
                Db::startTrans();
                if (count($mainOrders)) {
                    $totalPayNum = 0;
                    $totalPostage = 0;
                    foreach ($mainOrders as $order) {
                        $payNum = $order['gmo_pay_num'];
                        $totalPayNum += $payNum;
                        $totalPostage += $order['gmo_pay_postage'];

                        //修改支付状态为已支付
                        $update = [
                            'gmo_status' => self::STATUS_PAID, // 1
                            'gmo_pay_type' => $payType['id'],
                            'gmo_pay_num' => $payNum,
                            'gmo_pay_currency_id' => $payType['currency_id'],
                            'gmo_pay_code' => $gmo_pay_code,
                            'gmo_pay_time' => time(),
                        ];
                        if ($order['gmo_express_type'] == 3) {
                            $update['gmo_status'] = self::STATUS_WAIT_USE;//未使用
                        }
                        //更新订单
                        $flag = self::where(['gmo_id' => $order['gmo_id']])->update($update);
                        if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);
                    }

                    $r['code'] = SUCCESS;
                    $r['message'] = lang("payment_successful");
                    $r['result'] = [
                        'ids' => $gmo_ids,
                        'order_code' => '',
                        'pay_postage' => floatval($totalPostage), // 邮费
                        'pay_number' => floatval($totalPayNum),
                    ];
                }
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }

        }
        return $r;
    }

    /**
     * 微信退款(微信退款回调调用)
     * @param int $user_id 用户id
     * @param int $gmo_id 订单id
     * @param int $total_fee 订单金额
     * @param int $refund_fee 退款金额
     * @param string $gmo_refund_code 退款编号
     * @return mixed
     */
    static function wx_refund_order($user_id, $gmo_id, $total_fee, $refund_fee, $gmo_refund_code)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($gmo_id) && $total_fee > 0 && $refund_fee > 0 && !empty($gmo_refund_code)) {
            $mainOrders = (new self)->where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_id, 'gmo_status' => ['in', [self::STATUS_PAID, self::STATUS_SHIPPED, self::STATUS_COMPLETE]]])->find();
            if (!$mainOrders) return $r;
            $orders = GoodsOrders::where('go_id', 'in', $mainOrders['gmo_go_id'])->field('go_goods_id,go_title,go_price,go_num,go_img')->select();
            $goodIds = [];
            $goods = [];
            foreach ($orders as $order1) {
                $goodIds[] = $order1['go_goods_id'];
                $goods[] = Goods::get($order1['go_goods_id']);
            }
            $goodsCategory = GoodsCategory::get($goods[0]['category_id']);
            if ($goodsCategory['type'] == GoodsCategory::INTEGRAL_TYPE) {
                return $r;
            }
            $payType = ShopPayType::where('mark', 'wxpay')->find();
            if ($payType) {
                try {
                    Db::startTrans();

                    $refundNum = $mainOrders['gmo_pay_num'];
                    //拼团专区
                    if ($goodsCategory['type'] == GoodsCategory::GROUP_TYPE) {//拼团专区
                        if ($total_fee != $refund_fee) {
                            $refundNum = $refund_fee;
                        }
                        $result = ShopGroupList::update_group($user_id, $mainOrders['gmo_group_id'], 4, $refundNum);
                        if ($result['code'] != SUCCESS) {
                            throw new Exception($result['message']);
                        }
                    } else {

                    }

                    //更新订单
                    $flag = self::where(['gmo_id' => $mainOrders['gmo_id']])->update(['gmo_status' => 5, 'gmo_cancel_time' => time(), 'gmo_refund_code' => $gmo_refund_code, 'gmo_refund_time' => time(), 'gmo_refund_num' => $refundNum]);
                    if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                    $r['code'] = SUCCESS;
                    $r['message'] = lang("payment_successful");
                    $r['result'] = [];
                    Db::commit();
                } catch (Exception $exception) {
                    Db::rollback();
                    $r['message'] = $exception->getMessage();
                }
            }
        }
        return $r;
    }

    const MAX_GIVE_COUNT = 3;

    /**
     * 下单递归赠送上级IO积分
     * @param int $pid 上级ID
     * @param double $number 购买数量
     * @param int $orderId 主订单ID
     * @param int $count 递归条件, 递归深度请修改 MAX_GIVE_COUNT 常量, 还需在配置表添加对应的配置比例
     * @return bool
     */
    private function giveParentIOScore($pid, $number, $orderId, $count = 1)
    {
        if (empty($pid) or empty($number) or empty($orderId)) {
            return false;
        }
        if ($count > self::MAX_GIVE_COUNT) {
            // 递归退出条件
            return true;
        }
        try {
            $user = Member::where('member_id', $pid)->field('member_id, pid')->find();
            if (!empty($user)) {
                // 赠送 IO 积分
                $ratio = floatval(Config::get_value('shop_give_io_' . $count)) * 0.01;
                if (0 < $ratio) {
                    $giveNumber = bcmul($number, $ratio, 6);
                    if (!$this->giveIOScore($user['member_id'], $giveNumber, $orderId)) {
                        return false;
                    }
                    // 如果还有上级
                    if (!empty($user['pid'])) {
                        return $this->giveParentIOScore($user['pid'], $number, $orderId, ++$count);
                    }
                }
            } else {
                return true;
            }
        } catch (Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * 赠送ID积分
     * @param int $member_id 用户ID
     * @param float $giveNumber 赠送数量
     * @param int $orderId 订单ID
     * @return bool
     */
    private function giveIOScore($member_id, $giveNumber, $orderId)
    {
        $flag = AccountBook::add_accountbook($member_id, Currency::IOSCORE_ID, 704, 'gift_for_sub_shopping', 'in', $giveNumber, $orderId);
        if (empty($flag)) {
            return false;
        }
        $currencyUser = CurrencyUser::getCurrencyUser($member_id, Currency::IOSCORE_ID);
        $currencyUser['num'] += $giveNumber;
        if (!$currencyUser->save()) {
            return false;
        }
        return true;
    }

    /**
     * 立即购买创建订单(调用此方法需要开启事务回滚)
     * @param int $user_id 用户id
     * @param int $goods_id 商品id
     * @param int $sa_id 收货地址表id
     * @param int $num 数量
     * @param int $pay_type 支付方式ID
     * @param int $is_equal 是否抵扣 1-抵扣 0-不抵扣
     * @param int $group_id 拼团id
     * @param int $format_id 规格id
     * @param string $remark
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function buy_now_add_order($user_id, $goods_id, $sa_id, $pay_type, $num = 1, $is_equal = 1, $group_id = 0, $format_id = 0, $remark = '', $type, $receive_name, $mobile, $order_remark)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($goods_id) && isInteger($sa_id) && isInteger($num)) {
            if ($type == 2) {//自提
                $address = \app\common\model\ShopPickedUp::where(['sa_id' => $sa_id])->find();
                //收货地址
                if (empty($address)) {
                    $r['message'] = lang("请选择自提网点");
                    return $r;
                }
            } elseif (in_array($type, [1,3])) {//物流
                $address = ShopAddress::where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
                //收货地址
                if (empty($address)) {
                    $r['message'] = lang("incorrect_delivery_address_information");
                    return $r;
                }
            }

            $formatInfo = null;
            if ($format_id <= 0) {
                $formatList = GoodsFormat::get_format_list($goods_id);
                if (count($formatList) > 0) {
                    $r['message'] = lang("请选择商品规格");
                    return $r;
                }
            } else {
                $formatInfo = GoodsFormat::where(['goods_id' => $goods_id, 'id' => $format_id])->find();
                if (!$formatInfo) {
                    $r['message'] = lang("商品规格错误，请重新选择");
                    return $r;
                }
            }
            $payType = ShopPayType::where('id', $pay_type)->find();
            $goods = Goods::where(['goods_id' => $goods_id, 'goods_status' => Goods::STATUS_UP])->find();

            try {
                Db::startTrans();
                if ($goods) {
                    if ($format_id > 0) {
                        $total_price = bcmul($num, $formatInfo['goods_price'], 2);
                    } else {
                        $total_price = bcmul($num, $goods['goods_price'], 2);
                    }
                    $closeTime = ShopConfig::get_value('orders_close_time', 30);
                    $gmo_data['gmo_user_id'] = $user_id;
                    $gmo_data['gmo_go_id'] = 0;
                    $gmo_data['gmo_num'] = $num;
                    $gmo_data['gmo_code'] = self::create_code();
                    $gmo_data['gmo_add_time'] = time();
                    $gmo_data['gmo_close_time'] = $gmo_data['gmo_add_time'] + $closeTime * 60;
                    $gmo_data['gmo_total_price'] = $total_price;// 需要支付的价格
                    $gmo_data['gmo_pay_currency_id'] = $payType['currency_id']; // 支付的首选币种
                    $gmo_data['gmo_give_currency_id'] = $goods['goods_currency_give_id'];
                    $gmo_data['gmo_give_num'] = keepPoint($goods['goods_currency_give_num'] * $num, 2);
                    $gmo_data['gmo_market_price'] = $goods['goods_market']; // 市场价
                    $gmo_data['gmo_pay_type'] = $pay_type;
                    $gmo_data['gmo_remark'] = $order_remark;
                    $gmo_data['gmo_pickedup_remark'] = $remark;
                    $gmo_data['gmo_group_id'] = $group_id;
                    $gmo_data['gmo_es_time'] = Config::get_value('arrival_time', 10);
                    /**
                     * 待付款
                     */
                    $gmo_data['gmo_status'] = self::STATUS_WAIT_PAYMENT;//待发货
                    $pay_num = $total_price;
                    if (in_array($type, [1,3])) {
                        $gmo_data['gmo_address_id'] = $sa_id;
                        $gmo_data['gmo_pay_postage'] = bcmul($goods['goods_postage'], $num, 2); // 需要付的邮费
                        $data['go_postage'] = $goods['goods_postage']; // 邮费
                        $gmo_data['gmo_receive_name'] = $address->sa_name;
                        $gmo_data['gmo_mobile'] = $address->sa_mobile;
                        $gmo_data['gmo_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
                        $pay_num = bcadd($total_price, $gmo_data['gmo_pay_postage'], 2);
//                        $gmo_data['gmo_total_price'] = $pay_num;// 需要支付的价格
                    } elseif ($type == 2) {
                        $gmo_data['gmo_pickedup_id'] = $sa_id;
                        $gmo_data['gmo_pickedup_title'] = $address->sa_name;
                        $gmo_data['gmo_pickedup_name'] = $receive_name;
                        $gmo_data['gmo_pickedup_mobile'] = $mobile;
                        $gmo_data['gmo_pickedup_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, 0) . $address->sa_address;
                    }
                    if ($goods['goods_type'] == 2) {//判断是否星链的商品
                        $gmo_data['is_upload'] = 1;//1未上传
                    }

                    $gmo_data['gmo_express_type'] = $type;//1物流 2自提 3无物流
                    if (in_array($payType['mark'], ['hmpay', 'jfpay'])) {
                        $hm_price = ShopConfig::get_value('hm_price', 6.1);
//                        $pay_num = keepPoint($pay_num / $hm_price, 4);
                        $pay_num = sprintf("%.4f", $pay_num / $hm_price);
                    }
                    $gmo_data['gmo_pay_num'] = $pay_num;
                    $gmo_id = self::insertGetId($gmo_data);
                    if ($gmo_id > 0) {
                        $data['go_main_id'] = $gmo_id;
                        $data['go_user_id'] = $user_id;
                        $data['go_title'] = $goods['goods_title'];
                        $data['go_goods_id'] = $goods['goods_id'];
                        $data['go_format_id'] = $format_id;
                        $data['go_num'] = $num;
                        $data['go_code'] = GoodsOrders::create_code();
                        $data['go_add_time'] = time();
                        $data['go_total_price'] = bcmul($num, $goods['goods_price'], 2);
                        $data['go_price'] = $goods['goods_price'];
                        $data['go_market_price'] = $goods['goods_market'];
                        if ($format_id > 0) {
                            $data['go_total_price'] = bcmul($num, $formatInfo['goods_price'], 2);
                            $data['go_price'] = $formatInfo['goods_price'];
                            $data['go_market_price'] = $formatInfo['goods_market'];
                        }
                        $data['go_img'] = $goods['goods_img'];

                        //批量添加子订单
                        $go_id = GoodsOrders::insertGetId($data);
                        if ($go_id) {
                            Goods::where('goods_id', $goods['goods_id'])->setInc('goods_sale_number', $num); // 销量 +1

                            $r['code'] = SUCCESS;
                            $r['message'] = lang("successful_operation");
                            $r['result'] = ['ids' => intval($gmo_id), 'total_price' => $pay_num, 'gmo_code' => $gmo_data['gmo_code']];
                        } else {
                            $r['code'] = ERROR3;
                            throw new Exception(lang("operation_failed_try_again"));
                        }

                        //更新新人专区购买数量
                        $goodsCategory = GoodsCategory::get($goods['category_id']);
                        if ($goodsCategory['pid'] == 17) {
                            $check = RocketMember::where(['member_id' => $user_id])->value('buy_shop_num');
                            if ($check >= 1) {
                                $flag = RocketMember::where(['member_id' => $user_id])->setDec('buy_shop_num', 1);
                            } else {
                                throw new Exception('新人专区购买次数用完，请去分享');
                            }
                        }

                    } else {
                        $r['code'] = ERROR4;
                        throw new Exception(lang("operation_failed_try_again"));
                    }
                } else {
                    throw new Exception(lang('goods_down_or_delete'));
                }
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

    /**
     * 购物车下单支付
     * @param int $user_id 用户id
     * @param int $sc_ids 购物车表id,多个中间用","隔开
     * @param int $sa_id 收货地址表id
     * @param int $pay_type 支付方式ID
     * @param int $is_equal 是否抵扣 1-抵扣 0-不抵扣
     * @param string $remark 备注
     * @return mixed
     * @throws PDOException
     */
    static function shop_cart_pay($user_id, $sc_ids, $sa_id, $pay_type, $is_equal = 1, $remark = '', $type = 1, $receive_name = '', $mobile = '', $order_remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($sc_ids) && isInteger($sa_id)) {
            self::startTrans();
            try {
                //$add_order = self::split_add_orders($user_id, $sc_ids, $sa_id, $pay_type, $remark);
                $add_order = self::add_orders($user_id, $sc_ids, $sa_id, $pay_type, $is_equal, $remark, $type, $receive_name, $mobile, $order_remark);
                if ($add_order['code'] == SUCCESS) {
//                    $gmo_id = $add_order['result']['gmo_id'];
                    //$gmo_ids = $add_order['result']['gmo_ids'];
                    //$results = [];
                    $payType = ShopPayType::where('id', $pay_type)->find();
                    $res = self::where(['gmo_id' => $add_order['result']['ids']])->find();
                    if ($payType['mark'] == 'wxpay') {
                        // TODO 微信支付
                        $payApi = new \yunfastpay\UnifiedPayTrans();
                        $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'wxpay', $res['gmo_code']);
                        if (!$result) {
                            $r['message'] = '对不起请检查相关参数!';
                            return $r;
                        }
                        $add_order['result']['pay_url'] = $result;
                    } elseif ($payType['mark'] == 'zfbpay') {
                        // TODO 支付宝支付
                        $payApi = new \yunfastpay\ScanPayTrans();
                        $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'alipay', $res['gmo_code']);
                        if (!$result) {
                            $r['message'] = '对不起请检查相关参数!';
                            return $r;
                        }
                        $add_order['result']['pay_url'] = $result;
                    }
                    /*foreach ($gmo_ids as $key => $gmo_id) {
                        $pay = self::pay_order($user_id, $gmo_id);
                        if ($pay['code'] != SUCCESS) {
                            throw new Exception($pay['message']);
                        }
                        array_push($results, $pay['result']);
                    }*/
                    $r = $add_order;
                } else {
                    throw new Exception($add_order['message']);
                }
                self::commit();
            } catch (Exception $exception) {
                $r['message'] = $exception->getMessage();
                self::rollback();
            }
        }
        return $r;
    }

    /**
     * 立即购买下单支付
     * @param int $user_id 用户id
     * @param int $goods_id 商品id
     * @param int $sa_id 收货地址表id
     * @param int $num 数量
     * @param int $pay_type 支付方式ID
     * @param int $is_equal 是否抵扣 1-抵扣 0-不抵扣
     * @param int $group_id 拼团id
     * @param int $format_id 规格id
     * @param string $remark 订单备注
     * @return mixed
     * @throws
     */
    static function buy_now_pay($user_id, $goods_id, $sa_id, $pay_type, $num = 1, $is_equal = 1, $group_id = 0, $format_id = 0, $remark = '', $type, $receive_name = '', $mobile = '', $order_remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($goods_id) && isInteger($sa_id) && isInteger($num)) {
            Db::startTrans();
            try {
                $add_order = self::buy_now_add_order($user_id, $goods_id, $sa_id, $pay_type, $num, $is_equal, $group_id, $format_id, $remark, $type, $receive_name, $mobile, $order_remark);
                if ($add_order['code'] == SUCCESS) {
                    $payType = ShopPayType::where('id', $pay_type)->find();
                    $res = self::where(['gmo_id' => $add_order['result']['ids']])->find();
                    if ($payType['mark'] == 'wxpay') {
                        // TODO 微信支付
                        $payApi = new \yunfastpay\UnifiedPayTrans();
                        $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'wxpay', $res['gmo_code']);
                        if (!$result) {
                            $r['message'] = '对不起请检查相关参数!';
                            return $r;
                        }
                        $add_order['result']['pay_url'] = $result;
                    } elseif ($payType['mark'] == 'zfbpay') {
                        // TODO 支付宝支付
                        $payApi = new \yunfastpay\ScanPayTrans();
                        $result = $payApi->wapPay($res['gmo_id'], $res['gmo_pay_num'], 'alipay', $res['gmo_code']);
                        if (!$result) {
                            $r['message'] = '对不起请检查相关参数!';
                            return $r;
                        }
                        $add_order['result']['pay_url'] = $result;
                    }
                    $r = $add_order;
                } else {
                    throw new Exception($add_order['message']);
                }
                Db::commit();
            } catch (Exception $exception) {
                $r['message'] = $exception->getMessage();
                Db::rollback();
            }
        }
        return $r;
    }

    /**
     * 获取订单列表
     * @param int $user_id 用户id
     * @param null|int $status 订单状态
     * @param int $page
     * @param int $rows
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_orders_list($user_id, $status = null, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($page) && $rows <= 50) {
            $field = "gmo_id,gmo_num,gmo_add_time,gmo_status,gmo_pay_num,gmo_total_price,gmo_pay_postage,gmo_give_num,gmo_give_currency_id";
            // gmo_group_id,gmo_equal_num as gmo_discount_num,gmo_go_id,gmo_express_type,gmo_total_price
            $where = [];
            if (in_array($status, [1, 2, 3, 4, 6])) {
                $where['gmo_status'] = $status;
            }
            $list = self::where($where)->where('gmo_user_id', $user_id)->field($field)
                ->with([
                    'giveCurrency',
//                    'payCurrency',
//                    'other_currency'
                ])
                ->page($page, $rows)->order(['gmo_add_time' => 'DESC', 'gmo_pay_time' => 'DESC'])->select();

            //查询用户等级优惠
            //加载等级配置
//            $member_level = MembersLevel::all();
//            $pson_list = OrderTotal::where(['member_id'=>$user_id])->find();
//            $prefer = MembersLevel::where(['level'=>$pson_list['member_level_id']])->find();
            $discount = 0;
            $profit_currency_id = RocketConfig::getValue('profit_currency_id');
            $giveCurrency = Currency::where(['currency_id' => $profit_currency_id])->field('currency_id,currency_name')->find();
            $add_num = 0;
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $add_num += $value['gmo_num'];
                    //$discount = \app\common\model\OrderTotal::level_discount($user_id, $value['gmo_num']);
                    //邮费
//                    $postages = ShopConfig::get_value("postage", 15);

                    $value = $value->toArray();
//                    $value['gmo_total_price'] = floattostr($value['gmo_total_price']);
                    $value['gmo_pay_num'] = sprintf("%.2f", $value['gmo_pay_num']);
//                    $value['gmo_discount_num'] = floattostr($value['gmo_discount_num']);
                    $orders = GoodsOrders::where('go_main_id', $value['gmo_id'])->with(['format'])->field('go_goods_id,go_title,go_price,go_num,go_img,go_format_id')->select();
                    //go_format_id
                    $goods = [];
                    foreach ($orders as &$order) {
                        //$order['go_total_price'] = floatval($order['go_total_price']);
                        $order['go_price'] = floattostr($order['go_price']);
                        $imgs = !empty($order['go_img']) ? explode(",", $order['go_img']) : null;
                        $order['go_img'] = isset($imgs[0]) ? $imgs[0] : null;
                        $goods[] = Goods::get($order['go_goods_id']);
                    }

                    $goods_category = GoodsCategory::get($goods[0]['category_id']);
                    if (!empty($goods_category)) {
                        $goods_category_pid = GoodsCategory::get($goods_category['pid']);
                        $value['category_pid'] = $goods_category_pid['id'];
                        $value['category_type'] = $goods_category_pid['name'];
                    }

                    $value['goods'] = $orders;
                    $value['gmo_add_time'] = date("Y-m-d H:i:s", $value['gmo_add_time']);

                    $hm_price = ShopConfig::get_value('hm_price', 6.1);
                    $value['hm_price'] = $hm_price;

                    $value['total_pay_cny_price'] = keepPoint($value['gmo_total_price'] + $value['gmo_pay_postage'], 2);
//                    $value['total_pay_huo_price'] = keepPoint($value['total_pay_cny_price'] / $hm_price, 4);
                    $value['total_pay_huo_price'] = sprintf("%.4f", $value['total_pay_cny_price'] / $hm_price);
                    if ($value['gmo_give_currency_id'] == 5) {
                        $value['give_currency'] = $giveCurrency;
                    }
//                    $group_data = [];
//                    if ($goodsCategory['type'] == GoodsCategory::GROUP_TYPE) {//拼团专区
//                        $group = ShopGroupList::get($value['gmo_group_id']);
//                        $group_data['status'] = $group ? $group['status'] : 0;
//                        $group_data['group_num'] = $group ? $group['group_num'] : 0;
//                        $user_list = [];
//                        if ($group) {
//                            if ($value['gmo_status'] == GoodsMainOrders::STATUS_CANCEL) $group_data['status'] = 3;
//                            $where1 = [
//                                'group_id' => $value['gmo_group_id'],
//                                'status' => ['in', [1, 2, 4, 5]],
//                            ];
//                            if ($group['user_id'] != $user_id) $where1['user_id'] = ['in', [$group['user_id'], $user_id]];
//                            $user_select = ShopGroupUser::where($where1)->with(['userInfo'])->order('add_time asc')->limit(2)->select();
//                            $default_head = model('Member')->default_head;
//                            foreach ($user_select as $key1 => $value1) {
//                                $user_list[] = [
//                                    'id' => $value1['id'],
//                                    'nick' => $value1['user_info']['nick'],
//                                    'head' => $value1['user_info']['head'] ?: $default_head,
//                                ];
//                            }
//                        }
//                        $group_data['user_list'] = $user_list;
//                    }
//                    $value['group_data'] = $group_data;
                }

                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 订单详情
     * @param int $gmo_id
     * @param int $user_id
     * @return array
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function order_detail($gmo_id, $user_id)
    {
        $field = "gmo_id,gmo_go_id,gmo_num,gmo_status,gmo_total_price,gmo_pay_num,gmo_pay_postage,gmo_equal_num,
        gmo_equal_currency_id,gmo_code,gmo_pay_code,gmo_express_name,gmo_express_company,gmo_express_code,gmo_receive_name,
        gmo_mobile,gmo_address,gmo_add_time,gmo_pay_time,gmo_cancel_time,gmo_ship_time,gmo_sure_time,gmo_auto_sure_time,gmo_close_time,
        gmo_status_refund,gmo_pay_currency_id,gmo_group_id,gmo_pickedup_address,gmo_pickedup_id,gmo_give_num,gmo_give_currency_id";
        $order = self::where([
            'gmo_id' => $gmo_id,
            'gmo_user_id' => $user_id
        ])->with([
            'payCurrency',
//'equalCurrency',
            'giveCurrency'])
            ->field($field)
            ->find();

        if (!$order) {
            return ['code' => ERROR1, 'message' => lang('lan_no_data'), 'result' => null];
        }

        //加载等级配置
        //$discount=\app\common\model\OrderTotal::level_discount($user_id,$order['gmo_num']);
//        $discount = $order['gmo_equal_num'];


        $order = $order->toArray();
//        $goIds = explode(',', $order['gmo_go_id']);
        $goods = GoodsOrders::where(['go_main_id' => $order['gmo_id']])
//        where('go_id', 'in', $goIds)
            ->field('go_goods_id,go_title,go_price,go_num,go_img,go_format_id')->with(['format'])
//            /*->with('goods')*/ ->with(['format'])
            ->select();
        $order['gmo_add_time'] = date("Y-m-d H:i:s", $order['gmo_add_time']);
//        $order['gmo_discount'] = $discount;
        $order['gmo_pay_time'] = empty($order['gmo_pay_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_pay_time']);
        $order['gmo_cancel_time'] = empty($order['gmo_cancel_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_cancel_time']);
        $order['gmo_ship_time'] = empty($order['gmo_ship_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_ship_time']);
        $order['gmo_sure_time'] = empty($order['gmo_sure_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_sure_time']);

        $hm_price = ShopConfig::get_value('hm_price', 6.1);
        $order['hm_price'] = $hm_price;

        $order['total_pay_cny_price'] = keepPoint($order['gmo_total_price'] + $order['gmo_pay_postage'], 2);
//        $order['total_pay_huo_price'] = keepPoint($order['total_pay_cny_price'] / $hm_price, 4);
        $order['total_pay_huo_price'] =  sprintf("%.4f", $order['total_pay_cny_price'] / $hm_price);

        if ($order['gmo_status'] == 2) {//2待付款
            $order['gmo_close_time'] = $order['gmo_close_time'] - time();
            if ($order['gmo_close_time'] <= 0) {//订单关闭
                $order['gmo_close_time'] = 0;
                //取消订单
                $result = self::cancel_order($user_id, $gmo_id);
                if ($result['code'] == SUCCESS) {
                    $order['gmo_status'] = 5;
                    $order['gmo_cancel_time'] = date("Y-m-d H:i:s");
                }
            }
        } else if ($order['gmo_status'] == 3) {//3已发货
            $order['gmo_close_time'] = $order['gmo_auto_sure_time'] - time();
            if ($order['gmo_close_time'] <= 0) {//订单自动确认收货
                $order['gmo_close_time'] = 0;
                //确认收货
                $result = self::confirm_order($user_id, $gmo_id);
                if ($result['code'] == SUCCESS) {
                    $order['gmo_status'] = 4;
                    $order['gmo_sure_time'] = date("Y-m-d H:i:s");
                }
            }
        } else {
            $order['gmo_close_time'] = 0;
        }
        $order['gmo_express_name'] = $order['gmo_express_name'] ?: '';
        $order['gmo_express_code'] = $order['gmo_express_code'] ?: '';
        $order['gmo_total_price'] = floattostr($order['gmo_total_price']);
//        $order['gmo_pay_num'] = bcadd(bcsub($order['gmo_total_price'], $discount, 2), $order['gmo_pay_postage'], 2);
        $order['gmo_pay_postage'] = floattostr($order['gmo_pay_postage']);
        $order['gmo_equal_num'] = floattostr($order['gmo_equal_num']);
        $goods1 = [];
        foreach ($goods as &$good) {
            $good = $good->toArray();
            if (isset($good['goods'])) {
                $good['goods_price'] = floattostr($good['goods_price']);
                $imgs = !empty($good['go_img']) ? explode(",", $good['go_img']) : null;
                $good['go_img'] = isset($imgs[0]) ? $imgs[0] : null;
            }
            $goods1[] = Goods::get($good['go_goods_id']);
        }

        $goodsCategory = GoodsCategory::get($goods1[0]['category_id']);
        if (!empty($goodsCategory)) {
            $goods_category_pid = GoodsCategory::get($goodsCategory['pid']);
            $order['category_pid'] = $goods_category_pid['id'];
            $order['category_type'] = $goods_category_pid['name'];
            if ($goods_category_pid['id'] == 3) {
                $order['gmo_give_num'] = 0;
            }
        }

        $profit_currency_id = RocketConfig::getValue('profit_currency_id');
        $giveCurrency = Currency::where(['currency_id' => $profit_currency_id])->field('currency_id,currency_name')->find();
        if ($order['gmo_give_currency_id'] == 5) {
            $order['give_currency'] = $giveCurrency;
        }

        $order['goods'] = $goods;
        if (empty($order['gmo_address']) && !empty($order['gmo_pickedup_address'])) {
            $order['gmo_address'] = $order['gmo_pickedup_address'];
        }

        //自提点信息
        if (!empty($order['gmo_pickedup_id'])) {
            $pickedup = Db::name('shop_picked_up')->where(['sa_id' => $order['gmo_pickedup_id']])->find();
            $order['gmo_pickedup_address'] = !empty($pickedup) ? Areas::check_pca_address($pickedup['sa_province'], $pickedup['sa_city'], 0) . $pickedup['sa_address'] : '';
            $order['gmo_pickedup_title'] = !empty($pickedup) ? $pickedup['sa_name'] : '';
            $order['gmo_pickedup_tel'] = !empty($pickedup) ? $pickedup['sa_mobile'] : '';
        }

        //发货通知 详情
        $logistics = ShopLogisticsList::where(['company' => $order['gmo_express_company'], 'number' => $order['gmo_express_code']])->find();
        if ($logistics) {
            $last_result = json_decode($logistics['last_result'], true);
            if ($last_result['data']) {
                $order['logistics'] = [
                    "context" => $last_result['data'][0]['context'],
                    "time" => $last_result['data'][0]['time'],
                ];
            } else {
                $order['logistics'] = [
                    "context" => $last_result['message'],
                    "time" => empty($order['gmo_ship_time']) ? date('Y-m-d H:i:s') : $order['gmo_ship_time'],
                ];
            }
        } else {
            $order['logistics'] = [
                "context" => "商家已发货，正在等待揽件",
                "time" => empty($order['gmo_ship_time']) ? date('Y-m-d H:i:s') : $order['gmo_ship_time'],
            ];
        }

        return ['code' => SUCCESS, 'message' => lang('lan_data_success'), 'result' => $order];
    }

    /**
     * 取消订单
     * @param int $user_id 用户id
     * @param int $gmo_id 订单表id
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    static function cancel_order($user_id, $gmo_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($gmo_id)) {
            $order = self::where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_id])->find();
            //不是2待付款状态不能操作
            if ($order['gmo_status'] == 2) {
                $update = self::where(['gmo_id' => $gmo_id])->update(['gmo_status' => 5, 'gmo_cancel_time' => time()]);
                if ($update) {
                    //代金券
                    /*
                    if ($order['gmo_equal_num'] > 0) {
                        $djqCurrencyUser = CurrencyUser::getCurrencyUser($user_id,$order['gmo_equal_currency_id']);
                        //添加帐本
                        $flag = AccountBook::add_accountbook($user_id, $order['gmo_equal_currency_id'], 118, "shopping_equal_refunded", "in", $order['gmo_equal_num'], $gmo_id);
                        if (!$flag) throw new Exception(lang("operation_failed_try_again").'-'.__LINE__);
                        //增加资产
                        $flag = CurrencyUser::where(['cu_id'=>$djqCurrencyUser['cu_id'],'num'=>$djqCurrencyUser['num']])->setInc('num',$order['gmo_equal_num']);
                        if(!$flag) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
                    }
                    */
                    //拼团专区
                    /*
                    $orders = GoodsOrders::where('go_id', 'in', $order['gmo_go_id'])->field('go_goods_id,go_title,go_price,go_num,go_img')->select();
                    $goods = [];
                    foreach ($orders as $order1) {
                        $goods[] = Goods::get($order1['go_goods_id']);
                    }
                    $goodsCategory = GoodsCategory::get($goods[0]['category_id']);
                    if ($goodsCategory['type'] == GoodsCategory::GROUP_TYPE) {//拼团专区
                        if ($order['gmo_group_id'] > 0) {//加入拼团
                            $result = ShopGroupList::update_group($user_id, $order['gmo_group_id'], 3);
                            if($result['code'] != SUCCESS) throw new Exception(lang('operation_failed_try_again').'-'.__LINE__);
                        }
                    }
                    */

                    $orders = GoodsOrders::where('go_main_id', $order['gmo_id'])->field('go_goods_id,go_title,go_price,go_num,go_img')->select();
                    foreach ($orders as $order1) {
                        $update = Goods::where(['goods_id' => $order1['go_goods_id']])->update([
                            'goods_sale_number' => ['dec', $order1['go_num']],
//                            'goods_stock' => ['inc', $order1['go_num']]
                        ]);
                    }

                    $r['message'] = lang("successful_operation");
                    $r['code'] = SUCCESS;
                } else {
                    $r['code'] = ERROR2;
                    $r['message'] = lang("operation_failed_try_again");
                }
            } else {
                $r['message'] = lang("operation_failed_try_again");
            }
        }
        return $r;
    }

    /**
     * 确认收货操作
     * @param int $user_id 用户id
     * @param int $gmo_id 订单表id
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    static function confirm_order($user_id, $gmo_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($gmo_id)) {
            $order = self::where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_id])->find();

            //不是1已付款(待发货)，3已发货(待收货)状态不能操作
            if ($order['gmo_status'] == 1 || $order['gmo_status'] == 3 || $order['gmo_status'] == 6) {
                try {
                    Db::startTrans();
                    $update = self::where(['gmo_id' => $gmo_id])->update(['gmo_status' => 4, 'gmo_sure_time' => time()]);
                    if (!$update) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                    // 赠送
                    if ($order['gmo_give_currency_id'] > 0 && $order['gmo_give_num'] > 0) {
                        $giveCurrencyId = ShopConfig::get_value('give_currency_id');
                        $giveNum = $order['gmo_give_num'];

                        $giveCurrencyUser = CurrencyUser::getCurrencyUser($user_id, $giveCurrencyId);
                        //添加帐本
                        $flag = CurrencyLockBook::add_log('num_award', 'award', $user_id, $giveCurrencyId, $giveNum, $gmo_id);
                        if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                        //增加资产
                        $flag = CurrencyUser::where(['cu_id' => $giveCurrencyUser['cu_id'], 'num_award' => $giveCurrencyUser['num_award']])->setInc('num_award', $giveNum);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                    }

                    // 推荐奖
                    $recommend = ShopConfig::get_value('recommend', 0);
                    if ($recommend > 0) {
                        $GoodsOrders = GoodsOrders::where(['a.go_main_id' => $order['gmo_id']])->alias('a')
                            ->join("goods g", "g.goods_id = a.go_goods_id", "left")
                            ->field("a.go_main_id,g.category_id")->find();
                        if (!empty($GoodsOrders) && $GoodsOrders['category_id'] > 0) {
                            $goodsCategory = GoodsCategory::get($GoodsOrders['category_id']);
                            //优选区和爆品专区，给予一代推荐奖
                            if (!empty($goodsCategory) && in_array($goodsCategory['pid'], [1, 16])) {
                                if ($order['gmo_give_currency_id'] > 0 && $order['gmo_give_num'] > 0) {
                                    $member = Member::where(['member_id' => $user_id])->field('pid')->find();
                                    if (!empty($member) && $member['pid'] > 0) {
                                        $proceeds = keepPoint($order['gmo_give_num'] * $recommend / 100, 6);
                                        if ($proceeds > 0.0001) {
                                            $CurrencyUser = CurrencyUser::getCurrencyUser($member['pid'], $order['gmo_give_currency_id']);
                                            //添加帐本
                                            $flag = AccountBook::add_accountbook($CurrencyUser['member_id'], $CurrencyUser['currency_id'], 124, 'proceeds', 'in', $proceeds, 0);
                                            if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                                            //增加资产
                                            $flag = CurrencyUser::where(['cu_id' => $CurrencyUser['cu_id'], 'num' => $CurrencyUser['num']])->setInc('num', $proceeds);
                                            if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    //星链-确认收货
                    if ($order['is_upload'] == 2) {
                        $OrderApi = new \xingl\OrderApi();
                        $orderSn = GoodsXingl::where(['member_id' => $user_id, 'gmo_id' => $order['gmo_id']])->value('orderSn');
                        $params = ['orderSn' => $orderSn, 'operator' => '系统'];
                        $result = $OrderApi->confirmReceive($params);
//                        if (empty($result)) {
//                            throw new Exception('确认收货失败' . '-' . __LINE__);
//                        }
//                        if (!empty($result) && $result['status'] != 200) {
//                            throw new Exception('确认收货失败' . '-' . __LINE__);
//                        }
                    }

                    $r['message'] = lang("successful_operation");
                    $r['code'] = SUCCESS;
                    Db::commit();
                } catch (Exception $exception) {
                    $r['message'] = $exception->getMessage();
                    Db::rollback();
                }
            } else {
                $r['code'] = ERROR2;
                $r['message'] = lang("operation_failed_try_again");
            }
        } else {
            $r['message'] = lang("operation_failed_try_again");
        }
//        }
        return $r;
    }

    /**
     * 获取商品订单信息
     * @param int $member_id 用户ID
     * @param array $order_number 订单号
     * @return array|bool|\PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getGoodOrder($member_id, $order_number)
    {
        $result = self::where(['gmo_user_id' => $member_id, 'gmo_id' => ['in', $order_number]])->field(['gmo_id', 'gmo_user_id', 'gmo_total_price', 'gmo_pay_num'])->select();
        if (empty($result)) return [];

        $order_id = '';
        $total_price = 0;
        foreach ($result as $value) {
            $order_id .= ',' . $value['gmo_id'];
            $total_price += $value['gmo_pay_num'];
        }

        return ['member_id' => $member_id, 'order_id' => substr($order_id, 1), 'total_price' => $total_price];
    }

    /**
     * 添加物流
     * @param string $com 快递公司编码
     * @param string $com_name 快递公司名称
     * @param string $number 快递单号
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function addLogistics($com, $com_name, $number)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        $find = ShopLogisticsList::where(['company' => $com, 'number' => $number])->find();
        if ($find) {
            $r['code'] = SUCCESS;
            $r['message'] = lang("successful_operation");
            return $r;
        }

        $getLogistics = self::getLogistics($com, $number);
        if (!$getLogistics) {
            $r['message'] = lang("获取物流信息失败");
            return $r;
        }
        if ((isset($getLogistics['result']) && $getLogistics['result'] == false) || (isset($getLogistics['returnCode']) && $getLogistics['returnCode'] != '200')) {
            $r['message'] = lang("快递100获取物流返回错误信息:") . $getLogistics ['message'];
            return $r;
        }

        $now = time();
        $status = '';//监控状态:polling:监控中，shutdown:结束，abort:中止，updateall：重新推送。
        if ($getLogistics['state'] == 3) {//签收
            $status = 'shutdown';
        } else {
            $subLogistics = self::subLogistics($com, $number);
            if (!$subLogistics) {
                $r['message'] = lang("订阅物流信息失败");
                return $r;
            }
            if (isset($subLogistics['result']) && $subLogistics['result'] == false) {
                if ($subLogistics['message'] != 'POLL:重复订阅') {
                    $r['message'] = lang("快递100订阅物流返回错误信息:") . $subLogistics ['message'];
                    return $r;
                } else {
                    $status = 'polling';
                }
            }
            if ($subLogistics['result'] == true) {//true表示成功，false表示失败
                $status = 'polling';
            }
        }
        $insert = [
            'company' => $com,
            'company_name' => $com_name,
            'number' => $number,
            'status' => $status,
            'last_result' => json_encode($getLogistics),
            'update_time' => $now,
            'add_time' => $now,
        ];
        //$insert['status'] = $status;

        $flag = ShopLogisticsList::insertGetId($insert);
        if (!$flag) {
            $r['message'] = lang("operation_failed_try_again");
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("successful_operation");
        return $r;
    }

    /**
     * 更新物流信息
     * @param array $data 推送数据
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function updateLogistics($data)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        $now = time();
        $com = $data['lastResult']['com'];
        $number = $data['lastResult']['nu'];
        $find = ShopLogisticsList::where(['company' => $com, 'number' => $number])->find();
        $com_name = Db::name('express_list')->where('code', $com)->value('name');
        if (!$find) {
            $insert = [
                'company' => $com,
                'company_name' => $com_name,
                'number' => $number,
                'status' => $data['status'],
                'message' => $data['message'],
                //'auto_check'=>$data['autoCheck'],
                //'com_old'=>$data['comOld'],
                //'com_new'=>$data['comNew'],
                'state' => $data['lastResult']['state'],
                'ischeck' => $data['lastResult']['ischeck'],
                'last_result' => json_encode($data['lastResult']),
                'update_time' => $now,
                'add_time' => $now,
            ];
            if (isset($data['autoCheck'])) {
                $insert['auto_check'] = $data['autoCheck'];
            }
            if (isset($data['com_old'])) {
                $insert['com_old'] = $data['comOld'];
            }
            if (isset($data['com_new'])) {
                $insert['com_new'] = $data['comNew'];
            }

            $flag = ShopLogisticsList::insertGetId($insert);
            if (!$flag) {
                return mobileAjaxReturn(['result' => false, 'returnCode' => '500', 'message' => '添加数据失败']);
            }
            return mobileAjaxReturn(['result' => true, 'returnCode' => '200', 'message' => '成功']);
        }

        $insert1 = [
            'company' => $com,
            'company_name' => $com_name,
            'number' => $number,
            'status' => $data['status'],
            'message' => $data['message'],
            'last_result' => json_encode($data['lastResult']),
            'add_time' => $now,
        ];
        if (isset($data['autoCheck'])) {
            $insert1['auto_check'] = $data['autoCheck'];
        }
        if (isset($data['com_old'])) {
            $insert1['com_old'] = $data['comOld'];
        }
        if (isset($data['com_new'])) {
            $insert1['com_new'] = $data['comNew'];
        }
        $flag1 = ShopLogisticsPollHistory::insertGetId($insert1);

        $update = [
            'status' => $data['status'],
            'message' => $data['message'],
            //'auto_check'=>$data['autoCheck'],
            //'com_old'=>$data['comOld'],
            //'com_new'=>$data['comNew'],
            'state' => $data['lastResult']['state'],
            'ischeck' => $data['lastResult']['ischeck'],
            'last_result' => json_encode($data['lastResult']),
            'update_time' => $now,
        ];
        if (isset($data['autoCheck'])) {
            $update['auto_check'] = $data['autoCheck'];
        }
        if (isset($data['com_old'])) {
            $update['com_old'] = $data['comOld'];
        }
        if (isset($data['com_new'])) {
            $update['com_new'] = $data['comNew'];
        }

        $flag = ShopLogisticsList::where(['id' => $find['id']])->update($update);
        if (!$flag) {
            return mobileAjaxReturn(['result' => false, 'returnCode' => '500', 'message' => '更新数据失败']);
        }
        return mobileAjaxReturn(['result' => true, 'returnCode' => '200', 'message' => '成功']);
    }

    /**
     * 获取物流信息
     * @param string $com 快递公司编码
     * @param string $number 快递单号
     * @return array|bool|\PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getLogistics($com, $number)
    {
        $config = config('kuaidi100');
        //var_dump($config);
        $key = $config['key'];//客户授权key
        $customer = $config['customer'];//查询公司编号

        $param = array(
            'com' => $com,//快递公司编码
            'num' => $number,//快递单号
            /*'phone' => '',//手机号
            'from' => '',//出发地城市
            'to' => '',//目的地城市*/
            'resultv2' => '1'//开启行政区域解析
        );

        //请求参数
        $post_data = array();
        $post_data["customer"] = $customer;
        $post_data["param"] = json_encode($param);
        $sign = md5($post_data["param"] . $key . $post_data["customer"]);
        $post_data["sign"] = strtoupper($sign);

        $params = "";
        foreach ($post_data as $k => $v) {
            $params .= "$k=" . urlencode($v) . "&";              //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);

        $url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
        $res = _curl($url, $post_data);
        $result = json_decode($res, true);
        //var_dump($res);
        return $result;
    }

    /**
     * 订阅物流信息
     * @param string $com 快递公司编码
     * @param string $number 快递单号
     * @return array|bool|\PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function subLogistics($com, $number)
    {
        $config = config('kuaidi100');
        //var_dump($config);
        $key = $config['key'];//客户授权key
        $customer = $config['customer'];//查询公司编号
        $callbackurl = $config['callback'];

        $param = array(
            'company' => $com,//快递公司编码
            'number' => $number,//快递单号
            /*'from' => '',                     //出发地城市
            'to' => '',                       //目的地城市*/
            'key' => $key,                    //客户授权key
            'parameters' => array(
                'callbackurl' => $callbackurl,          //回调地址
                //'salt' => '',                 //加密串
                'resultv2' => '1',            //行政区域解析
                /*'autoCom' => '0',             //单号智能识别
                'interCom' => '0',            //开启国际版
                'departureCountry' => '',     //出发国
                'departureCom' => '',         //出发国快递公司编码
                'destinationCountry' => '',   //目的国
                'destinationCom' => '',       //目的国快递公司编码
                'phone' => ''                 //手机号*/
            )
        );

        //请求参数
        $post_data = array();
        $post_data["schema"] = 'json';
        $post_data["param"] = json_encode($param);

        $params = "";
        foreach ($post_data as $k => $v) {
            $params .= "$k=" . urlencode($v) . "&";              //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);

        $url = 'http://poll.kuaidi100.com/poll';    //实时查询请求地址
        $res = _curl($url, $post_data);
        $result = json_decode($res, true);
        //var_dump($res);
        return $result;
    }

    /**
     * 订单物流详情
     * @param int $gmo_id
     * @param int $user_id
     * @return array
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function ordersLogistics($gmo_id, $user_id)
    {
        $field = "gmo_status,gmo_express_name,gmo_express_company,gmo_express_code,gmo_receive_name,gmo_mobile,gmo_address,gmo_ship_time";
        $order = self::where([
            'gmo_id' => $gmo_id,
            'gmo_user_id' => $user_id
        ])->field($field)->find();
        if (!$order) {
            return ['code' => ERROR1, 'message' => lang('lan_no_data'), 'result' => null];
        }
        $order = $order->toArray();
        $order['gmo_ship_time'] = empty($order['gmo_ship_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_ship_time']);
        $order['gmo_express_name'] = $order['gmo_express_name'] ?: '';
        $order['gmo_express_code'] = $order['gmo_express_code'] ?: '';
        $logistics = ShopLogisticsList::where(['company' => $order['gmo_express_company'], 'number' => $order['gmo_express_code']])->find();
        if ($logistics) {
            $last_result = json_decode($logistics['last_result'], true);
            $statusList = [
                '在途' => '运输中', '揽收' => '已揽件', '派件' => '派送中', '签收' => '已签收', '退签' => '已退签', '退回' => '退回中',
            ];
            if ($last_result['data']) {
                foreach ($last_result['data'] as &$value) {
                    if (array_key_exists($value['status'], $statusList)) {
                        $value['status'] = $statusList[$value['status']];
                    }
                    //$value['time'] = date('Y-m-d H:i:s', $value['time']);
                }
                $last_result['data'][] = [
                    "context" => "商家已发货，正在等待揽件",
                    "status" => "已发货",
                    "time" => empty($order['gmo_ship_time']) ? date('Y-m-d H:i:s') : $order['gmo_ship_time'],
                ];
                $order['logistics'] = $last_result['data'];
            } else {
                $order['logistics'][] = [
                    "context" => $last_result['message'],
                    "status" => "疑难件",
                    "time" => empty($order['gmo_ship_time']) ? date('Y-m-d H:i:s') : $order['gmo_ship_time'],
                ];
            }
        } else {
            if ($order['gmo_status'] == self::STATUS_SHIPPED) {
                $order['logistics'][] = [
                    "context" => "商家已发货，正在等待揽件",
                    "status" => "已发货",
                    "time" => empty($order['gmo_ship_time']) ? date('Y-m-d H:i:s') : $order['gmo_ship_time'],
                ];
            } else if ($order['gmo_status'] == self::STATUS_PAID) {
                $order['logistics'][] = [
                    "context" => "商家暂未发货，请耐心",
                    "status" => "暂未发货",
                    "time" => date('Y-m-d H:i:s'),
                ];
            } else {
                $order['logistics'][] = [
                    "context" => "暂无物流信息，请联系商家或客服",
                    "status" => "暂无物流信息",
                    "time" => date('Y-m-d H:i:s'),
                ];
            }
        }

        return ['code' => SUCCESS, 'message' => lang('lan_data_success'), 'result' => $order];
    }

    /**
     * 到店订单详情
     * @param int $member_id 用户ID
     * @param int $order_id 订单id
     */
    static function store_order_detail($member_id, $order_id)
    {
        $field = "gmo_id,gmo_go_id,gmo_num,gmo_status,gmo_total_price,gmo_pay_num,gmo_pay_postage,gmo_equal_num,
        gmo_equal_currency_id,gmo_code,gmo_pay_code,gmo_express_name,gmo_express_company,gmo_express_code,
        gmo_receive_name,gmo_mobile,gmo_address,gmo_add_time,gmo_pay_time,gmo_cancel_time,gmo_ship_time,gmo_sure_time,
        gmo_auto_sure_time,gmo_close_time,gmo_status_refund,gmo_pay_currency_id,gmo_group_id,gmo_express_type,gmo_give_num,gmo_give_currency_id";
        //is_subscribe,subscribe_code,store_use
        $order = self::where([
            'gmo_id' => $order_id,
            'gmo_user_id' => $member_id
        ])->with(['payCurrency', 'equalCurrency', 'giveCurrency'])
            ->field($field)
            ->find();

        if (!$order) {
            return ['code' => ERROR1, 'message' => lang('lan_no_data'), 'result' => null];
        }

        //加载等级配置
        //$discount=\app\common\model\OrderTotal::level_discount($user_id,$order['gmo_num']);
        $discount = $order['gmo_equal_num'];

        $order = $order->toArray();
//        $goIds = explode(',', $order['gmo_go_id']);
//        $goods = GoodsOrders::where('go_id', 'in', $goIds)
        $goods = GoodsOrders::where('go_main_id', $order['gmo_id'])
            ->field('go_goods_id,go_title,go_price,go_num,go_img,go_format_id')
            /*->with('goods')*/ ->with(['format'])->select();
        $order['gmo_add_time'] = date("Y-m-d H:i:s", $order['gmo_add_time']);
        $order['gmo_discount'] = $discount;
        $order['gmo_pay_time'] = empty($order['gmo_pay_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_pay_time']);
        $order['gmo_cancel_time'] = empty($order['gmo_cancel_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_cancel_time']);
        $order['gmo_ship_time'] = empty($order['gmo_ship_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_ship_time']);
        $order['gmo_sure_time'] = empty($order['gmo_sure_time']) ? '' : date("Y-m-d H:i:s", $order['gmo_sure_time']);

        $hm_price = ShopConfig::get_value('hm_price', 6.1);
        $order['hm_price'] = $hm_price;

        $order['total_pay_cny_price'] = keepPoint($order['gmo_total_price'] + $order['gmo_pay_postage'], 2);
        $order['total_pay_huo_price'] = keepPoint($order['total_pay_cny_price'] / $hm_price, 4);

        if ($order['gmo_status'] == 2) {//2待付款
            $order['gmo_close_time'] = $order['gmo_close_time'] - time();
            if ($order['gmo_close_time'] <= 0) {//订单关闭
                $order['gmo_close_time'] = 0;
                //取消订单
                $result = self::cancel_order($member_id, $order_id);
                if ($result['code'] == SUCCESS) {
                    $order['gmo_status'] = 5;
                    $order['gmo_cancel_time'] = date("Y-m-d H:i:s");
                }
            }
        } else if ($order['gmo_status'] == 3) {//3已发货
            $order['gmo_close_time'] = $order['gmo_auto_sure_time'] - time();
            if ($order['gmo_close_time'] <= 0) {//订单自动确认收货
                $order['gmo_close_time'] = 0;
                //确认收货
                $result = self::confirm_order($member_id, $order_id);
                if ($result['code'] == SUCCESS) {
                    $order['gmo_status'] = 4;
                    $order['gmo_sure_time'] = date("Y-m-d H:i:s");
                }
            }
        } else {
            $order['gmo_close_time'] = 0;
        }
        $order['gmo_express_name'] = $order['gmo_express_name'] ?: '';
        $order['gmo_express_code'] = $order['gmo_express_code'] ?: '';
        $order['gmo_total_price'] = floattostr($order['gmo_total_price']);
//        $order['gmo_pay_num'] = bcadd(bcsub($order['gmo_total_price'], $discount, 2), $order['gmo_pay_postage'], 2);
        $order['gmo_pay_postage'] = floattostr($order['gmo_pay_postage']);
        $order['gmo_equal_num'] = floattostr($order['gmo_equal_num']);
        $goods1 = [];
        foreach ($goods as &$good) {
            $good = $good->toArray();
            if (isset($good['goods'])) {
                $good['goods_price'] = floattostr($good['goods_price']);
                $imgs = !empty($good['go_img']) ? explode(",", $good['go_img']) : null;
                $good['go_img'] = isset($imgs[0]) ? $imgs[0] : null;
            }
            $goods1[] = Goods::get($good['go_goods_id']);
        }

//        $goodsCategory = GoodsCategory::get($goods1[0]['category_id']);
//        $order['category_pid'] = $goods1[0]['category_id'];
//        $order['category_type'] = $goodsCategory['type'];

        $goodsCategory = GoodsCategory::get($goods1[0]['category_id']);
        if (!empty($goodsCategory)) {
            $goods_category_pid = GoodsCategory::get($goodsCategory['pid']);
            $order['category_pid'] = $goods_category_pid['id'];
            $order['category_type'] = $goods_category_pid['name'];
        }

        $order['refund_num'] = 0;
        $order['goods'] = $goods;
        //门店信息
//        $store_list = [];
//        if (!empty($order['gmo_express_type'])) {
//            $store_list = Db::name('shop_store')->field('name,address,phone')->where(['status' => 1])->select();
//        }
//        $order['store_list'] = $store_list;

        return ['code' => SUCCESS, 'message' => lang('lan_data_success'), 'result' => $order];
    }

    /**
     * 一键退款
     * @param int $member_id 用户ID
     * @param int $gmo_id 订单ID
     */
    static function sub_refund($member_id, $gmo_id)
    {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_failure'), 'result' => null];
        $order_info = self::where(['gmo_id' => $gmo_id])->find();
        if (!$order_info) return $r;
        if (!in_array($order_info['gmo_status'], [self::STATUS_PAID, self::STATUS_WAIT_USE])) {
            $r['message'] = '该订单已发货或已使用，不能退款';
            return $r;
        }
        $res = Db::name('accountbook')->where(['member_id' => $member_id, 'type' => 115, 'number_type' => 2, 'third_id' => $gmo_id])->select();
        if (!$res) return $r;
        foreach ($res as $key => $value) {
            $currency_id = $value['currency_id'];
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6611, 'shop_refund', 'in', $value['number'], $value['third_id']);
            if ($flag === false) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $value['number']);
            if (!$flag) throw new Exception("添加资产失败");
        }

        $flag = self::where(['gmo_id' => $gmo_id])->update(['gmo_status' => 5]);
        if ($flag === false) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['result'] = $gmo_id;
        return $r;
    }
}