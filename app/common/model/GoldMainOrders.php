<?php


namespace app\common\model;


use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class GoldMainOrders extends Model
{

    const STATUS_PAID = 1;
    const STATUS_WAIT_PAYMENT = 2;
    const STATUS_SHIPPED = 3;
    const STATUS_COMPLETE = 4;
//    const STATUS_REFUND = 5;

    const STATUS_ENUM = [
        self::STATUS_PAID => "已付款",
        self::STATUS_WAIT_PAYMENT => "待付款",
        self::STATUS_SHIPPED => "已发货",
        self::STATUS_COMPLETE => "已收货",
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

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'gmo_pay_currency_id')->field('currency_id, currency_name');
    }

    public function otherCurrency()
    {
        return $this->belongsTo(Currency::class, 'gmo_other_pay_currency_id')->field('currency_id, currency_name');
    }


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
     * @param string $remark 订单备注
     * @return mixed
     * @throws
     */
    static function add_orders($user_id, $sc_ids, $sa_id, $pay_type, $remark = '')
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
            $cart_field = "*";
            $shop_cart_list = Db::name("shop_cart")->alias("sc")->field($cart_field)->where(['g.goods_status' => Goods::STATUS_UP])->where("sc_id", "in", $scid)->
            join(config("database.prefix") . "goods g", "g.goods_id=sc.sc_goods_id", "LEFT")
                ->select();
            try {
                if ($shop_cart_list) {
                    $payType = ShopPayType::where('id', $pay_type)->find();
                    $object_array = null;
                    $add_list = null;
                    $pay_currency_id = null;
                    $other_pay_currency_id = null;
                    $pay_currency_type = 'num';
                    $other_pay_currency_type = null;
                    foreach ($shop_cart_list as $value) {
//                        $pay_currency_id = $value['goods_currency_id']; // 使用支付方式表的配置
                        // $pay_currency_type = $value['goods_currency_type']; 默认 可用
//                        $other_pay_currency_id = $value['goods_currency_other_id'];
//                        $other_pay_currency_type = $value['goods_currency_other_type'];
                        $pay_currency_id = $payType['currency_id'];
                        $other_pay_currency_id = $payType['other_currency_id'];
                        $other_pay_currency_type = empty($payType['other_currency_type']) ? 'none' : $payType['other_currency_type'];
                        $data['go_user_id'] = $user_id;
                        $data['go_title'] = $value['goods_title'];
                        $data['go_goods_id'] = $value['goods_id'];
                        $data['go_num'] = $value['sc_num'];
                        $data['go_code'] = GoldOrders::create_code();
                        $data['go_add_time'] = time();
                        $data['go_total_price'] = bcmul($value['sc_num'], $value['goods_currency_num'], 6);
                        $data['go_price'] = $value['goods_currency_num'];
                        $data['go_img'] = $value['goods_img'];
                        $data['go_other_total_price'] = bcmul($value['sc_num'], $value['goods_currency_other_num'], 6);
                        $data['go_other_price'] = $value['goods_currency_other_num'];
                        $data['go_market_price'] = $value['goods_price'];
                        $data['go_postage'] = $value['goods_postage'];
                        $data['go_admin_id'] = $value['goods_admin_id'];
                        Goods::where('goods_id', $value['goods_id'])->setInc('goods_sale_number', $value['sc_num']);
                        $add_list[] = $data;
                    }
                    if (!empty($add_list)) {
                        //批量添加子订单
                        $child_order = new GoldOrders();
                        $add_result = $child_order->saveAll($add_list);
                        if (!empty($add_result)) {
                            $gmo_go_ids = null;
                            $go_num = 0;
                            $gmo_total_price = 0;
                            $gmo_other_total_price = 0;
                            $gmo_total_postage = 0;
                            foreach ($add_result as $item) {
                                $gmo_go_ids .= !empty($gmo_go_ids) ? "," . $item->go_id : $item->go_id;//子单多个用“,”隔开
                                $go_num += $item->go_num;
                                $gmo_total_price += bcmul($item->go_num, $item->go_price, 6);
                                $gmo_other_total_price += bcmul($item->go_num, $item->go_other_price, 6);
                                $gmo_total_postage += bcmul($item->go_num, $item->go_postage, 6);
                                if (!isset($gmo_admin_id)) {
                                    $gmo_admin_id = $item['go_admin_id'];
                                }
                            }
                            if (empty($gmo_go_ids) || $go_num <= 0 || $gmo_total_price < 0) {
                                $r['code'] = ERROR2;
                                throw new \Exception(lang("operation_failed_try_again"));
                            }

                            $gmo_data['gmo_user_id'] = $user_id;
                            $gmo_data['gmo_go_id'] = $gmo_go_ids;
                            $gmo_data['gmo_num'] = $go_num;
                            $gmo_data['gmo_code'] = self::create_code();
                            $gmo_data['gmo_add_time'] = time();
                            $gmo_data['gmo_total_price'] = $gmo_total_price;
                            $gmo_data['gmo_pay_currency_id'] = $pay_currency_id;
                            $gmo_data['gmo_pay_currency_type'] = $pay_currency_type; // 可用
                            $gmo_data['gmo_other_total_price'] = $gmo_other_total_price;
                            $gmo_data['gmo_other_pay_currency_id'] = $other_pay_currency_id;
                            $gmo_data['gmo_other_pay_currency_type'] = $other_pay_currency_type;
                            $gmo_data['gmo_market_price'] = $gmo_other_total_price + $gmo_total_price;
                            $gmo_data['gmo_pay_type'] = $payType['id'];
                            $gmo_data['gmo_remark'] = $remark;
                            $gmo_data['gmo_admin_id'] = isset($gmo_admin_id) ? $gmo_admin_id : 0;
                            /**
                             * 初始化待付款
                             */
                            $gmo_data['gmo_status'] = self::STATUS_WAIT_PAYMENT;
                            $gmo_data['gmo_payment_number'] = 0;
                            $gmo_data['gmo_other_payment_number'] = 0;
                            $gmo_data['gmo_payment_postage'] = $gmo_total_postage; // 待付邮费
                            /**
                             * @deprecated 废弃释放的字段
                             */
//                            $gmo_data['gmo_total_num'] = $gmo_total_price;
//                            $gmo_data['gmo_last_num'] = $gmo_data['gmo_total_num'];
                            $gmo_data['gmo_receive_name'] = $address->sa_name;
                            $gmo_data['gmo_mobile'] = $address->sa_mobile;
                            // $gmo_data['gmo_address'] = $address->sa_province . " " . $address->sa_city . " " . $address->sa_area . " " . $address->sa_address;
                            $gmo_data['gmo_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
                            $gmo_id = Db::name("gold_main_orders")->insertGetId($gmo_data);
                            if ($gmo_id > 0) {
                                //删除购物车数据
                                $delete = Db::name("shop_cart")->where("sc_id", "in", $scid)->delete();
                                if (!$delete) {
                                    $r['code'] = ERROR6;
                                    throw new \Exception(lang("operation_failed_try_again"));
                                }
                                $r['code'] = SUCCESS;
                                $r['message'] = lang("successful_operation");
                                $r['result'] = ['gmo_id' => intval($gmo_id), 'total_price' => $gmo_total_price];
                            } else {
                                $r['code'] = ERROR3;
                                throw new \Exception(lang("operation_failed_try_again"));
                            }
                        } else {
                            $r['code'] = ERROR4;
                            throw new \Exception(lang("operation_failed_try_again"));
                        }
                    } else {
                        $r['code'] = ERROR5;
                        throw new \Exception(lang("operation_failed_try_again"));
                    }
                }
            } catch (\Exception $exception) {
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
     * 支付主订单(调用此方法需要开启事务回滚)
     * @param int $user_id 用户id
     * @param int $gmo_id 订单id
     * @return mixed
     */
    static function pay_order($user_id, $gmo_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($gmo_id)) {
            try {
                $order = (new self)->with(['currency'])->where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_id, 'gmo_status' => self::STATUS_WAIT_PAYMENT])->find();
                if (!empty($order)) {
                    $other_user_currency = CurrencyUser::getCurrencyUser($user_id, $order['gmo_other_pay_currency_id']);
                    $other_number = isset($other_user_currency[$order['gmo_other_pay_currency_type']]) ? $other_user_currency[$order['gmo_other_pay_currency_type']] : 0; // 组合币种余额
                    if ((!is_null($other_user_currency) and 0 != $order['gmo_other_pay_currency_id']) and $other_number > 0 and $order['gmo_other_total_price'] > 0) {
                        $canOtherPaymentNumber = floatval($order['gmo_other_total_price']) - $other_number; // 其他币种可支付的余额
                        // 除组合币种 Start
                        if ($canOtherPaymentNumber > 0) {
                            // 未完全使用 other_currency 支付 待使用 首选币种支付
                            $otherNumber = $canOtherPaymentNumber;
                            $num = $otherPaymentNumber = $order['gmo_other_total_price'] - $otherNumber;
                        } else {
                            $otherNumber = 0;
                            $num = $otherPaymentNumber = $order['gmo_other_total_price'];
                        }
                        $result = false;
                        switch ($order['gmo_other_pay_currency_type']) {
                            case 'uc_card':
                                // I券
                                $result = StoresCardLog::add_log('shop', $user_id, $order['gmo_other_pay_currency_id'], $num, $order['gmo_id']);
                                break;
                            case 'uc_card_lock':
                                // O券
                                $result = StoresFinancialLog::add_log('shop', $user_id, $order['gmo_other_pay_currency_id'], $num, $order['gmo_id']);
                                break;
                            case 'game_lock':
                                // IO券
                                $result = GameLockLog::add_log('shop', $user_id, $num, $order['gmo_id']);
                                break;
                            case 'none':
                            default:
                                // 不使用组合币种:
                                $result = 'none';
                        }
                        if ($result === false) {
                            $r['message'] = lang('system_error_please_try_again_later');
                            return $r;
                        }
                        if ($result != 'none') {
                            $other_user_currency[$order['gmo_other_pay_currency_type']] -= $otherPaymentNumber;
                            if (!$other_user_currency->save()) {
                                $r['message'] = lang('system_error_please_try_again_later');
                                return $r;
                            }
                        }
                        // 扣除组合币种 End
                    } else {
                        $otherNumber = $order['gmo_other_total_price']; // 剩余的待扣除
                        $otherPaymentNumber = 0;
                    }
                    $user_currency = CurrencyUser::getCurrencyUser($user_id, $order['gmo_pay_currency_id']);
                    $realTotalPrice = bcadd($order['gmo_total_price'], $otherNumber, 6); // 加上组合币种的剩余待支付
                    $realPostageTotalPrice = bcadd($order['gmo_payment_postage'], $realTotalPrice, 6); // 加上邮费的价格
                    if (bccomp($user_currency[$order['gmo_pay_currency_type']], $realPostageTotalPrice, 6) >= 0) {
                        //添加帐本
                        $accountbook = AccountBook::add_accountbook($user_id, $order['gmo_pay_currency_id'], 115, "shopping", "out", $realPostageTotalPrice, $order['gmo_id']);
                        if (!$accountbook) {
                            throw new \Exception(lang("operation_failed"));
                        }
                        //减少资产
                        $user_currency[$order['gmo_pay_currency_type']] -= $realPostageTotalPrice;
                        if (!$user_currency->save()) {
                            throw new \Exception(lang("operation_failed"));
                        }
                        //修改支付状态为已支付
                        $update = Db::name("gold_main_orders")->where(['gmo_id' => $gmo_id])->update([
                            'gmo_status' => self::STATUS_PAID, // 1
                            'gmo_payment_number' => $realTotalPrice,
                            'gmo_other_payment_number' => $otherPaymentNumber,
                        ]);
                        if (!$update) {
                            throw new \Exception(lang("operation_failed"));
                        }
                        // 赠送积分
                        if (0 < $realTotalPrice) {
                            // 赠送 IO 积分
                            $user = Member::where('member_id', $user_id)->field('member_id, pid')->find();
                            if (!empty($user) and !empty($user['pid'])) { // 存在上级就赠送IO积分
                                $flag = (new self())->giveParentIOScore($user['pid'], floatval($realTotalPrice), $order['gmo_id']);
                                if (!$flag) {
                                    throw new Exception(lang('system_error_please_try_again_later'));
                                }
                            }
                        }
                        $r['code'] = SUCCESS;
                        $r['message'] = lang("payment_successful");
                        $r['result'] = [
                            'order_code' => $order['gmo_code'],
                            'payment_postage' => $order['gmo_payment_postage'], // 邮费
                            'payment_number' => floatval($realTotalPrice),
                            'other_payment_number' => floatval($otherPaymentNumber),
                            'currency_name' => $order['currency']['currency_name'],
                            'other_currency_name' => $order['gmo_other_pay_currency_type'] == 'none' ? null : ShopPayType::TYPE_ENUM[$order['gmo_other_pay_currency_type']],
                        ];
                    } else {
                        $r['message'] = lang("insufficient_balance");
                    }
                }
            } catch (\Exception $exception) {
                $r['message'] = $exception->getMessage();
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
                $ratio = floatval(Config::get_value('shop_give_io_'.$count)) * 0.01;
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
    private function giveIOScore($member_id, $giveNumber, $orderId) {
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
     * @param string $remark
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function buy_now_add_order($user_id, $goods_id, $sa_id, $pay_type, $num = 1, $remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($goods_id) && isInteger($sa_id) && isInteger($num)) {
            $address = ShopAddress::where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
            //收货地址
            if (empty($address)) {
                $r['message'] = lang("incorrect_delivery_address_information");
                return $r;
            }
            $goods = Db::name("goods")->where(['goods_id' => $goods_id, 'goods_status' => Goods::STATUS_UP])->find();
            try {
                if ($goods) {
                    $data['go_user_id'] = $user_id;
                    $data['go_title'] = $goods['goods_title'];
                    $data['go_goods_id'] = $goods['goods_id'];
                    $data['go_num'] = $num;
                    $data['go_code'] = GoldOrders::create_code();
                    $data['go_add_time'] = time();
                    $data['go_total_price'] = bcmul($num, $goods['goods_currency_num'], 6);
                    $data['go_price'] = $goods['goods_currency_num'];
                    $data['go_other_total_price'] = bcmul($num, $goods['goods_currency_other_num']);
                    $data['go_other_price'] = $goods['goods_currency_other_num'];
                    $data['go_market_price'] = $goods['goods_price'];
                    $data['go_img'] = $goods['goods_img'];
                    $data['go_postage'] = bcmul($num, $goods['goods_postage'], 6); // 邮费
                    Goods::where('goods_id', $goods['goods_id'])->setInc('goods_sale_number', $num); // 销量 +1
                    //批量添加子订单
                    $go_id = Db::name("gold_orders")->insertGetId($data);
                    if ($go_id) {
                        $payType = ShopPayType::where('id', $pay_type)->find();
                        $gmo_data['gmo_user_id'] = $user_id;
                        $gmo_data['gmo_go_id'] = $go_id;
                        $gmo_data['gmo_num'] = $num;
                        $gmo_data['gmo_code'] = self::create_code();
                        $gmo_data['gmo_add_time'] = time();
                        $gmo_data['gmo_total_price'] = $data['go_total_price'];// 需要支付的价格
                        $gmo_data['gmo_pay_currency_id'] = $payType['currency_id']; // 支付的首选币种
                        // ['gmo_pay_currency_type'] = $goods['goods_currency_type']; // 支付类型 默认可用
                        $gmo_data['gmo_other_total_price'] = $data['go_other_total_price'];
                        /**
                         * 使用 支付方式 的配置
                         */
//                        $gmo_data['gmo_other_pay_currency_id'] = $goods['goods_currency_other_id'];
                        $gmo_data['gmo_other_pay_currency_id'] = $payType['other_currency_id'];
//                        $gmo_data['gmo_other_pay_currency_type'] = $goods['goods_currency_other_type'];
                        $gmo_data['gmo_other_pay_currency_type'] = empty($payType['other_currency_type']) ? 'none' : $payType['other_currency_type'];
                        $gmo_data['gmo_market_price'] = $goods['goods_price']; // 市场价
                        $gmo_data['gmo_pay_type'] = $pay_type;
                        $gmo_data['gmo_remark'] = $remark;
                        /**
                         * 待付款
                         */
                        $gmo_data['gmo_status'] = self::STATUS_WAIT_PAYMENT;
                        $gmo_data['gmo_payment_number'] = 0;
                        $gmo_data['gmo_other_payment_number'] = 0;
                        $gmo_data['gmo_payment_postage'] = $data['go_postage']; // 需要付的邮费
                        // $gmo_data['gmo_total_num'] = bcmul($data['go_total_price'], $gold_shop_gain, 6);//放大的价格(后续释放) 无用字段
                        // $gmo_data['gmo_last_num'] = $gmo_data['gmo_total_num']; // 无用字段
                        $gmo_data['gmo_receive_name'] = $address->sa_name;
                        $gmo_data['gmo_mobile'] = $address->sa_mobile;
                        // $gmo_data['gmo_address'] = $address->sa_province . " " . $address->sa_city . " " . $address->sa_area . " " . $address->sa_address;
                        $gmo_data['gmo_address'] = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
                        $gmo_id = Db::name("gold_main_orders")->insertGetId($gmo_data);
                        if ($gmo_id > 0) {
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("successful_operation");
                            $r['result'] = ['gmo_id' => intval($gmo_id), 'total_price' => $gmo_data['gmo_total_price'], 'other_total_price' => $gmo_data['gmo_other_total_price'], 'market_price' => $gmo_data['gmo_market_price']];
                        } else {
                            $r['code'] = ERROR3;
                            throw new \Exception(lang("operation_failed_try_again"));
                        }
                    } else {
                        $r['code'] = ERROR4;
                        throw new \Exception(lang("operation_failed_try_again"));
                    }

                } else {
                    throw new \Exception(lang('goods_down_or_delete'));
                }
            } catch (\Exception $exception) {
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
     * @param string $remark 备注
     * @return mixed
     * @throws PDOException
     */
    static function shop_cart_pay($user_id, $sc_ids, $sa_id, $pay_type, $remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($sc_ids) && isInteger($sa_id)) {
            self::startTrans();
            try {
                $add_order = self::split_add_orders($user_id, $sc_ids, $sa_id, $pay_type, $remark);
//                $add_order = self::add_orders($user_id, $sc_ids, $sa_id, $pay_type, $remark);
                if ($add_order['code'] == SUCCESS) {
//                    $gmo_id = $add_order['result']['gmo_id'];
                    $gmo_ids = $add_order['result']['gmo_ids'];
                    $results = [];
                    foreach ($gmo_ids as $key => $gmo_id) {
                        $pay = self::pay_order($user_id, $gmo_id);
                        if ($pay['code'] != SUCCESS) {
                            throw new \Exception($pay['message']);
                        }
                        array_push($results, $pay['result']);
                    }
                    $responseResult = [];
                    $responseResult['order_code'] = implode(', ', array_column($results, 'order_code'));
                    $responseResult['payment_postage'] = array_sum(array_column($results, 'payment_postage'));
                    $responseResult['payment_number'] = array_sum(array_column($results, 'payment_number'));
                    $responseResult['other_payment_number'] = array_sum(array_column($results, 'other_payment_number'));
                    $responseResult['currency_name'] = $results[0]['currency_name'];
                    $responseResult['other_currency_name'] = $results[0]['other_currency_name'];
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("payment_successful");
                    $r['result'] = $responseResult;
                } else {
                    throw new \Exception($add_order['message']);
                }
                self::commit();
            } catch (\Exception $exception) {
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
     * @param string $remark 订单备注
     * @return mixed
     * @throws
     */
    static function buy_now_pay($user_id, $goods_id, $sa_id, $pay_type, $num = 1, $remark = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($goods_id) && isInteger($sa_id) && isInteger($num)) {
            Db::startTrans();
            try {
                $add_order = self::buy_now_add_order($user_id, $goods_id, $sa_id, $pay_type, $num, $remark);
                if ($add_order['code'] == SUCCESS) {
                    $gmo_id = $add_order['result']['gmo_id'];
                    $pay = self::pay_order($user_id, $gmo_id);
                    if ($pay['code'] == SUCCESS) {
                        $r = $pay;
                    } else {
                        throw new \Exception($pay['message']);
                    }
                } else {
                    throw new \Exception($add_order['message']);
                }
                Db::commit();
            } catch (\Exception $exception) {
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
            $field = "gmo_id,gmo_go_id,gmo_num,gmo_code,gmo_add_time,gmo_status,gmo_total_price,gmo_pay_currency_id,gmo_pay_currency_type,gmo_other_total_price, gmo_other_pay_currency_id, gmo_other_pay_currency_type, gmo_market_price, gmo_payment_number, gmo_other_payment_number,gmo_express_name,gmo_express_code,gmo_status_refund, gmo_payment_postage";
            // $query = Db::name("gold_main_orders")->alias("gmo")->field($field)->where(['gmo_user_id' => $user_id]);
            $where = [];
            if (in_array($status, [1, 2, 3, 4])) {
                $where['gmo_status'] = $status;
            }
            // $list = $query->order("gmo_id desc")->join(config("database.prefix") . "currency c", "c.currency_id=gmo.gmo_pay_currency_id", "LEFT")->page($page, $rows)->select();
            $list = GoldMainOrders::where($where)->where('gmo_user_id', $user_id)->field($field)->with(['currency', 'other_currency'])->page($page, $rows)->select();
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value = $value->toArray();
                    $value['gmo_total_price'] = floatval($value['gmo_total_price']);
                    $value['gmo_other_total_price'] = floatval($value['gmo_other_total_price']);
                    $value['gmo_market_price'] = floatval($value['gmo_market_price']);
                    $value['gmo_payment_number'] = floatval($value['gmo_payment_number']);
                    $value['gmo_other_payment_number'] = floatval($value['gmo_other_payment_number']);
                    $value['time'] = date("Y-m-d H:i:s", $value['gmo_add_time']);
                    $value['other_currency']['currency_name'] = ShopPayType::TYPE_ENUM[$value['gmo_other_pay_currency_type']];
//                    $goids = explode(",", $value['gmo_go_id']);
//                    $order = Db::name("gold_orders")->field("go_img")->where(['go_id' => $goids[0]])->find();
                    $orders = GoldOrders::where('go_id', 'in', $value['gmo_go_id'])->with('goodsCurrency')->field('go_title, go_goods_id, go_num, go_total_price, go_price, go_img, go_postage')->select();
                    foreach ($orders as &$order) {
                        $order['go_total_price'] = floatval($order['go_total_price']);
                        $order['go_price'] = floatval($order['go_price']);
                    }
                    $value['goods'] = $orders;
                    $value['gmo_add_time'] = date("Y-m-d H:i:s", $value['gmo_add_time']);
//                    $imgs = !empty($order['go_img']) ? explode(",", $order['go_img']) : null;
//                    $value['imgs'] = isset($imgs[0]) ? $imgs[0] : null;
                    // $value['ratio'] = bcmul(bcdiv($value['gmo_release_num'], $value['gmo_total_num'], 6), 100);//已经释放和总数量的对比比率
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
        $order = self::where([
            'gmo_id' => $gmo_id,
            'gmo_user_id' => $user_id
        ])->with(['currency', 'otherCurrency'])
            ->field('gmo_id, gmo_other_pay_currency_id, gmo_pay_currency_id, gmo_go_id, gmo_code, gmo_add_time, gmo_status, gmo_express_name, gmo_express_code, gmo_receive_name, gmo_mobile, gmo_address, gmo_sure_time, gmo_payment_number, gmo_other_payment_number, gmo_status_refund, gmo_es_time, gmo_other_pay_currency_type, gmo_payment_postage')
            ->find();
        $order = $order->toArray();
        $goIds = explode(',', $order['gmo_go_id']);
        $goods = GoldOrders::where('go_id', 'in', $goIds)->field('go_id, go_title, go_goods_id, go_num, go_img, go_postage')->with('goods')->select();
        $order['gmo_add_time'] = date("Y-m-d H:i:s", $order['gmo_add_time']);
        $order['gmo_payment_number'] = floatval($order['gmo_payment_number']);
        $order['gmo_other_payment_number'] = floatval($order['gmo_other_payment_number']);
        $order['other_currency']['currency_name'] = ShopPayType::TYPE_ENUM[$order['gmo_other_pay_currency_type']];
        foreach ($goods as &$good) {
            $good = $good->toArray();
            if (isset($good['goods'])) {
                $good['goods']['goods_price'] = floatval($good['goods']['goods_price']);
                $good['goods']['goods_currency_num'] = floatval($good['goods']['goods_currency_num']);
                $good['goods']['goods_currency_other_num'] = floatval($good['goods']['goods_currency_other_num']);
            }
        }

        return ['code' => SUCCESS, 'message' => lang('lan_data_success'), 'result' => [
            'order' => $order,
            'goods' => $goods
        ]];
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
            $order = Db::name("gold_main_orders")->where(['gmo_user_id' => $user_id, 'gmo_id' => $gmo_id])->find();
            //不是1已付款(待发货)，3已发货(待收货)状态不能操作
            if ($order['gmo_status'] == 1 || $order['gmo_status'] == 3) {
                $update = Db::name("gold_main_orders")->where(['gmo_id' => $gmo_id])->update(['gmo_status' => 4, 'gmo_sure_time' => time(), 'gmo_release_status' => 1]);
                if ($update) {
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
}