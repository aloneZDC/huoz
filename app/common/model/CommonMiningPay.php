<?php
//传统矿机 支付订单
namespace app\common\model;

use OSS\Core\OssException;
use think\exception\PDOException;
use think\Log;
use think\Db;
use think\Exception;

class CommonMiningPay extends Base
{
    protected $resultSetType = 'collection';

    /**
     * 生成随机订单号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function rand_order_sn()
    {
        $add_time_count = self::where(['add_time' => ['gt', todayBeginTimestamp()]])->count(1);
        $out_trade_no = date('Ymd') . '-' . $add_time_count;
        $is_out_trade_no = self::where('mining_code', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::rand_order_sn();
    }

    /**
     * 生成随机订单号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function GetRandOrderSn()
    {
        $string = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        $cdkey = "";
        for ($i = 0; $i < 22; $i++) {
            $cdkey .= $string[rand(0, strlen($string) - 1)];
        }

        $out_trade_no = $cdkey . time();
        $is_out_trade_no = self::where('mining_code', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }

    /**
     * 购买商品
     * @param int $member_id 用户id
     * @param int $product_id 商品id
     * @param int $amount 数量
     * @param string $quan_id 优惠券id
     * @param int $start_time 时间
     * @return array
     * @throws PDOException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function product_buy($member_id, $product_id, $amount, $price_type, $pay_id, $quan_id, $start_time = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        if ($product_id <= 0 || $amount <= 0) return $r;

//        $common_mining_config = CommonMiningConfig::get_key_value();

        $product_info = CommonMiningProduct::getProduct($product_id);
        if (empty($product_info) || $product_info['amount'] <= 0) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        // 计算价格
        if ($product_info['price_type']) {
            $mining_price = CommonMiningProduct::mining_price(); // 获取1T的价格
            if ($mining_price === false) {
                $r['message'] = lang('price_error');
                return $r;
            }
            $product_info['price_usdt'] = $mining_price['mining_usdt_price'];

//            $mining_discount_price = CommonMiningConfig::getValue('mining_discount_price', 0); // 获取优惠价格
//            if ($product_info['tnum'] == 10) {
//                $product_info['price_usdt'] = intval($mining_price['mining_usdt_price'] * $product_info['tnum'] * $mining_discount_price / 100);
//                $product_info['price_cny'] = intval($mining_price['mining_cny_price'] * $product_info['tnum'] * $mining_discount_price / 100);
//            } else {
//                $product_info['price_usdt'] = $mining_price['mining_usdt_price'] * $product_info['tnum'];
//                $product_info['price_cny'] = $mining_price['mining_cny_price'] * $product_info['tnum'];
//            }
        }

//        if ($product_info['quota'] > 0 && $amount > $product_info['quota']) {
//            $r['message'] = lang('common_mining_quota_limit', ['num' => $product_info['quota']]);
//            return $r;
//        }

//        $quan_num = 0;
//        if ($quan_id > 0) {
//            $quan_info = Db::name('voucher_member')->where([
//                'id' => $quan_id,
//                'member_id' => $member_id,
//            ])->find();
//            if (empty($quan_info)) return $r;
//
//            if ($quan_info['status'] != 0 || $quan_info['expire_time'] < time()) {
//                $r['message'] = lang('voucher_member_can_not_use');
//                return $r;
//            }
//
//            if ($price_type == 'usdt') {
//                $usdt_price = CurrencyPriceTemp::get_price_currency_id($product_info['price_usdt_currency_id'], 'CNY');
//                $quan_num = keepPoint($quan_info['cny'] / $usdt_price, 6);
//            } else {
//                $quan_num = $quan_info['cny'];
//            }
//        }


        // 实际支付价值U
        $total_pay_num = keepPoint($product_info['price_usdt'] * $amount, 6);
        // 入金从可用钱包扣除   奖励到矿机账户
//        $pay_currency_id = $common_mining_config['pay_usdt_kj_currency_id']; // USDT对应的矿机账户

        //后台设置为USDT钱包账户支付
        $other_currency_num = 0;
//        if ($price_type == 'usdt') {
        // 组合支付
        $pay_type_info = Db::name('fil_mining_pay_type')->where(['id' => $pay_id, 'status' => 0])->field(['id', 'currency_id', 'other_currency_id'])->find();
        if (empty($pay_type_info)) return $r;

        // 判断组合支付
        if ($pay_type_info['other_currency_id'] > 0) {
            $other_currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['other_currency_id']);
            if (!empty($currency_user) || $other_currency_user['num'] > 0) {
                $other_currency_num = $other_currency_user['num'];
            }
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        $currency_user_num = bcadd($currency_user['num'], $other_currency_num, 6);
        $pay_num = $total_pay_num;
        // 如果是积分支付*2
//        if ($pay_id == 98) $pay_num = keepPoint($pay_num * 2, 6);

//        } else {
//            $r['message'] = lang('insufficient_balance');
//            return $r;
//            $currency_user = CurrencyUser::getCurrencyUser($member_id, $product_info['price_cny_currency_id']);
//            if (empty($currency_user)) {
//                $r['message'] = lang('insufficient_balance');
//                return $r;
//            }
//            $currency_user_num = $currency_user['num'];
//            $pay_num = $product_info['price_cny'] * $amount;
//        }

//        if ($quan_num > 0) {
//            $pay_num = keepPoint($pay_num - $quan_num, 6);
//        }

        if ($currency_user_num < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 方便测试
        if ($start_time == 0) {
            $add_time = time();
            $start_day = todayEndTimestamp() + $product_info['deliver_time'] * 86400;
        } else {
            $add_time = $start_time + 1;
            $start_day = $start_time + 86400;
        }

        $stop_day = $start_day + $product_info['cycle_time'] * 86400;
        try {
            self::startTrans();

//            if ($quan_id > 0) {
//                $flag = Db::name('voucher_member')->where(['id' => $quan_info['id'], 'status' => 0])->setField('status', 1);
//                if (!$flag) {
//                    $r['message'] = lang('operation_failed_try_again');
//                    return $r;
//                }
//            }

//            $pay_tnum = $product_info['tnum'] * $amount;
            $flag = CommonMiningMember::add_item($member_id, $amount, $total_pay_num);
            if ($flag === false) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            // 减少库存
            $flag = CommonMiningProduct::where(['id' => $product_info['id'], 'amount' => $product_info['amount']])->setDec('amount', $amount);
            if (!$flag) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            $order_data = [
                'member_id' => $currency_user['member_id'],
//                'real_pay_num' => $pay_num,
//                'real_pay_currency_id' => $currency_user['currency_id'],
//                'real_pay_num1' => $quan_num,
//                'tnum' => $product_info['tnum'] * $amount,
//                'tnum' => $pay_tnum,
                'tnum' => $amount,
//                'mining_currency_id' => $product_info['mining_currency_id'],
                'product_id' => $product_info['id'],
                'pay_num' => $total_pay_num,
                'pay_currency_id' => $product_info['price_usdt_currency_id'],
                'start_day' => $start_day,
                'treaty_day' => $stop_day,
//                'is_team' => 0,
//                'is_award' => 0,
//                'is_recommand' => 0,
                'add_time' => $add_time,
                'mining_code' => self::rand_order_sn()
            ];
            if ($other_currency_num > 0) {
                $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
                if ($other_currency_pay_num >= 0) {
                    $order_data['real_pay_integral'] = $other_currency_user['num'];
                    $order_data['real_pay_integral_currency_id'] = $other_currency_user['currency_id'];

                    $order_data['real_pay_num'] = $other_currency_pay_num;
                    $order_data['real_pay_currency_id'] = $currency_user['currency_id'];
                } else {
                    $order_data['real_pay_integral'] = $pay_num;
                    $order_data['real_pay_integral_currency_id'] = $other_currency_user['currency_id'];
                }
            } else {
                $order_data['real_pay_num'] = $pay_num;
                $order_data['real_pay_currency_id'] = $currency_user['currency_id'];
            }
            //插入订单
            $item_id = self::insertGetId($order_data);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣除资产
            if ($pay_num > 0) {
                if ($other_currency_num > 0) {
                    $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
                    if ($other_currency_pay_num >= 0) {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6505, 'fil_mining', 'out', $other_currency_user['num'], $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $other_currency_user['num']);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6505, 'fil_mining', 'out', $other_currency_pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $other_currency_pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    } else {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6505, 'fil_mining', 'out', $pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                } else {
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6505, 'common_mining_buy', 'out', $pay_num, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $item_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    /**
     * 我的购买列表
     * @param $member_id
     * @param int $page
     * @param int $rows
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buy_list($member_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];

        $list = self::alias('a')
            ->join('common_mining_product p', 'p.id = a.product_id', 'left')
//            ->join('currency c', 'c.currency_id = a.mining_currency_id', 'left')
            ->join('currency r', 'r.currency_id = a.real_pay_currency_id', 'left')
            ->join('currency i', 'i.currency_id = a.real_pay_integral_currency_id', 'left')
            ->field(['a.id', 'p.name' => 'product_name', 'a.mining_code', 'a.tnum',
                'a.pay_num', 'a.real_pay_num', 'r.currency_name' => 'real_pay_currency_name',
                'a.real_pay_integral', 'i.currency_name' => 'real_pay_integral_currency_name',
//                'a.real_pay_level', 'a.real_pay_discount', 'a.airdrop_num', 'a.share_num', 'a.reward_num',
                'a.last_release_day', 'a.last_release_num',
                'a.total_release_num', 'a.total_lock_num',
//                'c.currency_name' => 'mining_currency_name',
                'a.start_day', 'a.treaty_day',
//                'a.stop_day' => 'treaty_day',
                'a.add_time', 'a.member_id',
                'a.contract_status',
//                'give_status'
            ])
            ->where(['a.member_id' => $member_id])
            ->page($page, $rows)->order(['a.id' => 'asc'])->select();
        if (!$list) return $r;

        // 获取产币，币种名称
        $release_currency_id = CommonMiningConfig::get_value('release_currency_id', 0);
        $mining_currency_name = Currency::where(['currency_id' => $release_currency_id])->value('currency_name', 0);

        $today = todayBeginTimestamp();
        foreach ($list as &$item) {
            $item['mining_currency_name'] = $mining_currency_name;

            if ($item['real_pay_num'] <= 0
                && $item['real_pay_currency_name'] == null
            ) {
                $item['real_pay_num'] = $item['real_pay_integral'];
                $item['real_pay_currency_name'] = $item['real_pay_integral_currency_name'];
            }

            // 昨日产币数量
            $last_release_day = CommonMiningRelease::where(['member_id' => $item['member_id'], 'third_id' => $item['id'], 'release_time' => $today])->field(['num', 'lock_num'])->find();
            $item['last_release_num'] = empty($last_release_day) ? 0 : keepPoint($last_release_day['num'] + $last_release_day['lock_num'], 6);
            // if ($item['last_release_day'] != $today) $item['last_release_num'] = 0;

            // 总产币数量
            $item['total_release_num'] = keepPoint($item['total_release_num'] + $item['total_lock_num'], 6);

            // 赠送 0未 1已赠送
//            if ($item['give_status'] == 1) {
//                $status = 4;
//            }// 待签合同
//            else
            if ($item['contract_status'] == 0) {
                $status = 0;
            } // 待产出
            else if ($item['start_day'] > $today) {
                $status = 2;
            } // 产出中
            else if ($item['start_day'] <= $today
                && $item['treaty_day'] > $today
            ) {
                $status = 3;
            } // 已产完
            else {
                $status = 4;
            }
            $item['status'] = $status; // 0待签合同 1待封存 2待产出 3产出中 4已产完

            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            $item['start_day'] = date('Y-m-d H:i', $item['start_day']);
            $item['treaty_day'] = date('Y-m-d H:i', $item['treaty_day']);
            unset($item['last_release_day'], $item['total_lock_num'], $item['member_id'],
                $item['contract_status'], $item['real_pay_integral'], $item['real_pay_integral_currency_name']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * @param $member_id //用户ID
     * @param $product_id //商品ID
     * @param $price_type //支付类型 USDT CNY
     */
    static function buy($member_id, $product_id, $amount, $price_type, $pay_id, $quan_id, $start_time = 0)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('operation_failed_try_again');
        $r['result'] = null;
        if ($product_id <= 0 || $amount <= 0) {
            return $r;
        }

        $common_mining_config = CommonMiningConfig::get_key_value();

        $product_info = CommonMiningProduct::getProduct($product_id);
        if (empty($product_info) || $product_info['amount'] <= 0) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        // 计算价格
        if ($product_info['price_type']) {
            $mining_price = CommonMiningProduct::mining_price(); // 获取1T的价格
            if ($mining_price === false) {
                $r['message'] = lang('price_error');
                return $r;
            }
            $mining_discount_price = CommonMiningConfig::getValue('mining_discount_price', 0); // 获取优惠价格
            if ($product_info['tnum'] == 10) {
                $product_info['price_usdt'] = intval($mining_price['mining_usdt_price'] * $product_info['tnum'] * $mining_discount_price / 100);
                $product_info['price_cny'] = intval($mining_price['mining_cny_price'] * $product_info['tnum'] * $mining_discount_price / 100);
            } else {
                $product_info['price_usdt'] = $mining_price['mining_usdt_price'] * $product_info['tnum'];
                $product_info['price_cny'] = $mining_price['mining_cny_price'] * $product_info['tnum'];
            }
        }

        if ($product_info['quota'] > 0 && $amount > $product_info['quota']) {
            $r['message'] = lang('common_mining_quota_limit', ['num' => $product_info['quota']]);
            return $r;
        }

        $quan_num = 0;
        if ($quan_id > 0) {
            $quan_info = Db::name('voucher_member')->where([
                'id' => $quan_id,
                'member_id' => $member_id,
            ])->find();
            if (empty($quan_info)) return $r;

            if ($quan_info['status'] != 0 || $quan_info['expire_time'] < time()) {
                $r['message'] = lang('voucher_member_can_not_use');
                return $r;
            }

            if ($price_type == 'usdt') {
                $usdt_price = CurrencyPriceTemp::get_price_currency_id($product_info['price_usdt_currency_id'], 'CNY');
                $quan_num = keepPoint($quan_info['cny'] / $usdt_price, 6);
            } else {
                $quan_num = $quan_info['cny'];
            }
        }


        // 实际支付价值U
        $total_pay_num = $product_info['price_usdt'] * $amount;
        // 入金从可用钱包扣除   奖励到矿机账户
        $pay_currency_id = $common_mining_config['pay_usdt_kj_currency_id']; // USDT对应的矿机账户

        //后台设置为USDT钱包账户支付
        $other_currency_num = 0;
        if ($price_type == 'usdt') {
            // 组合支付
            $pay_type_info = Db::name('fil_mining_pay_type')->where(['status' => 0, 'id' => $pay_id])->field(['id', 'currency_id', 'other_currency_id'])->find();
            if (empty($pay_type_info)) return $r;

            // 判断组合支付
            if ($pay_type_info['other_currency_id'] > 0) {
                $other_currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['other_currency_id']);
                if (!empty($currency_user) || $other_currency_user['num'] > 0) {
                    $other_currency_num = $other_currency_user['num'];
                }
            }

            $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['currency_id']);
            if (empty($currency_user)) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
            $currency_user_num = bcadd($currency_user['num'], $other_currency_num, 6);
            $pay_num = $product_info['price_usdt'] * $amount;
        } else {
            $r['message'] = lang('insufficient_balance');
            return $r;
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $product_info['price_cny_currency_id']);
            if (empty($currency_user)) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
            $currency_user_num = $currency_user['num'];
            $pay_num = $product_info['price_cny'] * $amount;
        }

        if ($quan_num > 0) {
            $pay_num = keepPoint($pay_num - $quan_num, 6);
        }

        if ($currency_user_num < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 方便测试
        if ($start_time == 0) {
            $add_time = time();
            $start_time = todayBeginTimestamp() + 86400;
        } else {
            $add_time = $start_time + 1;
            $start_time = $start_time + 86400;
        }

        $start_day = $start_time;
        $stop_day = $start_day + $product_info['days'] * 86400;
        try {
            self::startTrans();

            if ($quan_id > 0) {
                $flag = Db::name('voucher_member')->where(['id' => $quan_info['id'], 'status' => 0])->setField('status', 1);
                if (!$flag) {
                    $r['message'] = lang('operation_failed_try_again');
                    return $r;
                }
            }

            $pay_tnum = $product_info['tnum'] * $amount;
            $flag = CommonMiningMember::addItem($member_id, $pay_tnum, $total_pay_num);
            if (!$flag) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            // 减少库存
            $flag = CommonMiningProduct::where(['id' => $product_info['id'], 'amount' => $product_info['amount']])->setDec('amount', $amount);
            if (!$flag) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            //插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'real_pay_num' => $pay_num,
                'real_pay_currency_id' => $currency_user['currency_id'],
                'real_pay_num1' => $quan_num,
//                'tnum' => $product_info['tnum'] * $amount,
                'tnum' => $pay_tnum,
                'mining_currency_id' => $product_info['mining_currency_id'],
                'product_id' => $product_info['id'],
                'pay_num' => $total_pay_num,
                'pay_currency_id' => $pay_currency_id, //$product_info['price_usdt_currency_id'],
                'start_day' => $start_day,
                'stop_day' => $stop_day,
                'is_team' => 0,
                'is_award' => 0,
                'is_recommand' => 0,
                'add_time' => $add_time,
                'mining_code' => self::GetRandOrderSn()
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣除资产
            if ($pay_num > 0) {

                if ($other_currency_num > 0) {
                    $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
                    if ($other_currency_pay_num >= 0) {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6505, 'fil_mining', 'out', $other_currency_user['num'], $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $other_currency_user['num']);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6505, 'fil_mining', 'out', $other_currency_pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $other_currency_pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    } else {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6505, 'fil_mining', 'out', $pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                } else {
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6505, 'common_mining_buy', 'out', $pay_num, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $item_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }


    // 推荐奖励发放
    public static function recommand_award($common_mining_pay, $common_mining_config, $today_start)
    {
        $base_member_id = $common_mining_pay['member_id'];

        //直推 间推奖励
        for ($count = 1; $count <= 2; $count++) {
            $award_level = $count; //奖励类型

            $member = Member::where(['member_id' => $base_member_id])->field('pid')->find();
            if (empty($member) || $member['pid'] == 0) {
                return;
            }

            $base_member_id = $member['pid'];

            if ($common_mining_config['is_recommand_limit'] == 1) {
                //直推人数 推荐1人拿1代
                $recommand_count = CommonMiningMember::myOneTeamCount($base_member_id);
                if ($recommand_count < $count) {
                    continue;
                }
            }

            // 20210316 修改本人没有买满存算力，只能拿 一代推荐奖；只有 个人有买矿机后，才能同时获得 一代、二代的推荐奖
//            if($count > 1) {
//                $CommonMiningPay = CommonMiningPay::where(['member_id' => $base_member_id])->find();
//                if (empty($CommonMiningPay)) {
//                    continue;
//                }
//            }

            // 推荐好友购买独享矿机或满存算力 拿2代奖励 20210408
            $AloneMiningPay = AloneMiningPay::where(['member_id' => $base_member_id])->find(); // 独享矿机
            $CommonMiningPay = CommonMiningPay::where(['member_id' => $base_member_id])->find(); // 传统矿机
            if (empty($CommonMiningPay) && empty($AloneMiningPay)) {
                continue;
            }


            // 增加用户汇总记录
            CommonMiningMember::addItem($base_member_id, 0);

            //没有入金 没有奖励
//            $pid_common_mining_member = CommonMiningMember::where(['member_id' => $base_member_id])->find();
//            if($pid_common_mining_member) {
            $award_all_percent = $common_mining_config['recommand_' . $count];
            $award_lock_percent = $common_mining_config['recommand_lock' . $count];
            $award_num_percent = $award_all_percent - $award_lock_percent;

            // 可用数量
            $base_num = $common_mining_pay['pay_num'];
            if ($common_mining_config['income_type'] == 'fixed') {
                // 固定奖励 /T
                $fixed_num = $common_mining_pay['tnum'] * $common_mining_config['fixed_recommand'];
                $award_num = min($base_num, $fixed_num);
            } else {
                $award_num = keepPoint($base_num * $award_num_percent / 100, 6);
            }

            $award_num = $award_num >= 0.000001 ? $award_num : 0;

            // 锁仓数量
            $award_lock_num = keepPoint($common_mining_pay['pay_num'] * $award_lock_percent / 100, 6);
            $award_lock_num = $award_lock_num >= 0.000001 ? $award_lock_num : 0;
            if ($award_num > 0 || $award_lock_num > 0) {
                CommonMiningIncome::award($base_member_id, $common_mining_pay['pay_currency_id'], $award_level, $award_num, $common_mining_pay['id'], $base_num, 0, $award_num_percent, $today_start, $common_mining_pay['member_id'], $award_lock_num, $award_lock_percent);
            }
//            }
        }
    }

    // 推荐奖励锁仓释放
    static function lock_release($currency_user, $common_mining_config, $today_start)
    {
        $award_percent = $common_mining_config['lock_release_percent'];
        $award_num = keepPoint($currency_user['common_lock_num'] * $award_percent / 100, 6);
        if ($award_num < 0.000001) return false;

        try {
            self::startTrans();

            if ($award_num > 0) {
                $flag = CommonMiningMember::where(['member_id' => $currency_user['member_id']])->update([
                    'total_child5' => ['inc', $award_num],
                ]);
                if (!$flag) throw new Exception("更新奖励总量失败");

                // 添加奖励记录
                $item_id = CommonMiningIncome::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $currency_user['currency_id'],
                    'type' => 5,
                    'num' => $award_num,
                    'lock_num' => 0,
                    'add_time' => time(),
                    'award_time' => $today_start,
                    'third_percent' => $award_percent,
                    'third_lock_percent' => 0,
                    'third_num' => $currency_user['common_lock_num'],
                    'third_id' => $currency_user['cu_id'],
                    'third_member_id' => $currency_user['member_id'],
                    'release_id' => 0,
                ]);
                if (!$item_id) throw new Exception("添加奖励记录");

                // 增加锁仓减少记录
                $flag = CurrencyLockBook::add_log('common_lock_num', 'release', $currency_user['member_id'], $currency_user['currency_id'], $award_num, $currency_user['cu_id'], $currency_user['common_lock_num'], $award_percent, $currency_user['member_id']);
                if (!$flag) throw new Exception("减少锁仓账本失败");

                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6506, 'common_mining_award5', 'in', $award_num, $item_id, 0);
                if (!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->update([
                    'num' => ['inc', $award_num],
                    'common_lock_num' => ['dec', $award_num],
                ]);
                if (!$flag) throw new Exception("添加资产失败");
            }

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 查询今日T数
     * @param array $today_config 时间配置
     * @return string
     */
    static function today_tnum($today_config)
    {
        // 新增业绩 - 满存算力
        $common_today_tnum = self::where([
            'tnum' => ['egt', 1],
            'add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]],
        ])->sum('tnum');

        // 新增业绩 - 独享矿机
        $alone_today_tnum = AloneMiningArchive::where([
            'tnum' => ['egt', 1],
            'add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]],
        ])->sum('tnum');
        return bcadd($common_today_tnum, $alone_today_tnum, 6);
    }

    static function getList($member_id, $lang, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id' => $member_id,
        ];

        $list = self::alias('a')->with(['product', 'miningcurrency', 'paycurrency'])->field('a.id,a.product_id,a.mining_currency_id,a.real_pay_currency_id,a.real_pay_num,a.add_time,a.total_release_num,a.last_release_day,a.last_release_num,a.start_day,a.stop_day,a.total_lock_num,a.total_lock_yu,a.tnum,a.mining_code')
            ->where($where)
            ->page($page, $rows)->order("a.id asc")->select();
        if (!$list) return $r;

        $percent = CommonMiningConfig::getValue('release_manager_fee_percent', 0);
        $cur_today = $today = todayBeginTimestamp();
        foreach ($list as &$item) {
            $item['show540'] = $item['tnum'] >= 1 ? 1 : 0; // 1显示，0隐藏
            $item['release_percent'] = 100 - $percent;
            $item['total_lock_release'] = keepPoint($item['total_lock_num'] - $item['total_lock_yu'], 6);
            if ($item['last_release_day'] != $today) {
                $item['last_release_num'] = 0;
            }
            $item['total_release_num'] = keepPoint($item['total_release_num'] + $item['total_lock_num'], 6);

            $status = 0;
            if ($item['start_day'] > $today) {
                $cur_today = $item['start_day'];
                $status = 0;
            } else {
                if ($item['stop_day'] < $today) {
                    $status = 2;
                } else {
                    $status = 1;
                }
            }
            $item['status'] = $status; //0主网待挖 1主网在挖 2已挖完
            $item['days'] = intval(($item['stop_day'] - $cur_today) / 86400);

            $item['progress'] = 100;
            if ($status == 2) $item['progress'] = 0;
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            $item['start_day'] = date('Y-m-d H:i', $item['start_day']);
            $item['stop_day'] = date('Y-m-d H:i', $item['stop_day']);
            if (!empty($item['product']) && $lang == 'en') {
                $item['product']['name'] = $item['product']['name_en'];
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function summary($member_id)
    {
        $result = [
            'level' => 'F0',
            'tnum' => 0,
            'total_release_num' => 0,
            'total_lock_num' => 0,
            'total_lock_yu' => 0,
            'total_lock_release' => 0,
            'yestoday_release_num' => 0,
        ];
        $summary = self::where('member_id', $member_id)->field('sum(tnum) as tnum,sum(total_release_num) as total_release_num,sum(total_lock_num) as total_lock_num,sum(total_lock_yu) as total_lock_yu')->find();
        if ($summary && $summary['tnum']) {
            $result = $summary;
            $result['total_release_num'] = keepPoint($summary['total_release_num'], 6);
            $result['total_lock_num'] = keepPoint($summary['total_lock_num'], 6);
            $result['total_lock_yu'] = keepPoint($summary['total_lock_yu'], 6);
            $result['total_lock_release'] = keepPoint($result['total_lock_num'] - $result['total_lock_yu'], 6);
        }

        $yestoday_release_num = self::where('member_id', $member_id)->where('last_release_day', todayBeginTimestamp())->sum('last_release_num');
        if (!$yestoday_release_num) $result['yestoday_release_num'] = keepPoint($yestoday_release_num, 6);

        $member = CommonMiningMember::where(['member_id' => $member_id])->find();
        if ($member) {
            $result['level'] = 'F' . $member['level'];
        }

        $result['currency_name'] = 'FIL';
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 订单头部统计
     * @param int $member_id 用户id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function release_head($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if (empty($member_id)) return $r;
        // 满存算力
        $common_result = CommonMiningPay::where(['member_id' => $member_id])
            ->field(['sum(total_release_num)' => 'total_release_num', 'sum(total_lock_num)' => 'total_lock_num'])
            ->find();

        // 获取释放币种
        $release_currency_id = CommonMiningConfig::get_value('release_currency_id', 0);
        $currency_name = Currency::where(['currency_id' => $release_currency_id])->value('currency_name', 'FIL');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'total_lock_yu' => keepPoint($common_result['total_release_num'] + $common_result['total_lock_num'], 6),
            'total_release_num' => $common_result['total_release_num'] ?: '0.000000',
            'currency_name' => $currency_name,
        ];
        return $r;
    }

    /**
     * 产币 统计
     * @param int $member_id 用户id
     * @param int $product_id 订单id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function release_count($member_id, $product_id = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if (empty($member_id)) return $r;

        $where = ['member_id' => $member_id];
        if ($product_id) $where['id'] = $product_id;

        $result = self::where($where)->field(['total_lock_num', 'total_lock_yu', 'total_release_num'])->find();
//        if (empty($result)) return $r;

        // 获取配置
        $mining_config = CommonMiningConfig::get_key_value();

        // 获取释放币种
        $currency_name = Currency::where(['currency_id' => $mining_config['release_currency_id']])->value('currency_name', 'FIL');

        $common_member = CommonMiningMember::where(['member_id' => $member_id])->field(['total_child6', 'total_child7'])->find();

        // 今日锁仓统计
        $last_lock_num = CommonMiningIncome::where([
            'member_id' => $member_id,
            'type' => 4,
            'currency_id' => $mining_config['release_currency_id'],
            'third_id' => $product_id,
            'award_time' => todayBeginTimestamp(),
        ])->find();

        // 今日释放统计
        $last_release_num = CommonMiningIncome::where([
            'member_id' => $member_id,
            'type' => 5,
            'currency_id' => $mining_config['release_currency_id'],
            'third_id' => $product_id,
            'award_time' => todayBeginTimestamp(),
        ])->find();

        // 累计锁仓
        $community_total_lock_num = CommonMiningIncome::where(['member_id' => $member_id, 'type' => 6])->field(['sum(lock_num)' => 'total_lock_num'])->find();
        // 今日锁仓
        $community_lock_num = CommonMiningIncome::where(['member_id' => $member_id, 'type' => 6, 'award_time' => todayBeginTimestamp()])->sum('lock_num');
        // 今日释放
        $community_num = CommonMiningIncome::where(['member_id' => $member_id, 'type' => 7, 'award_time' => todayBeginTimestamp()])->sum('num');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            // 产币 数据统计头
            'release_count' => [
                'total_release_num' => keepPoint($result['total_release_num'] + $result['total_lock_num'], 6),
                'release_percent' => floatval(100 - $mining_config['release_lock_percent']),
                'release_lock_percent' => $mining_config['release_lock_percent'],
                'lock_release_days' => $mining_config['lock_release_days'],
            ],
            // 产币 线性释放明细 - 数据统计头
            'release_lock_count' => [
                'total_lock_yu' => $result['total_lock_yu'] ?: '0.000000', // 剩余锁仓
                'total_lock_count' => $result['total_lock_num'] ?: '0.000000', // 累计锁仓
                'last_lock_num' => $last_lock_num['num'] ?: '0.000000',  // 今日锁仓
                'total_lock_num' => keepPoint($result['total_lock_num'] - $result['total_lock_yu'], 6),// 累计释放
                'last_release_num' => $last_release_num['num'] ?: '0.000000', // 今日释放
            ],
            // 算力社区 线性释放明细 - 数据统计头
            'community_head' => [
                'total_lock_yu' => $common_member['total_child6'], // 剩余锁仓
                'total_lock_count' => $community_total_lock_num['total_lock_num'] ?: '0.000000', // 累计锁仓
                'last_lock_num' => keepPoint($community_lock_num, 6) ?: '0.000000',  // 今日锁仓
                'total_lock_num' => $common_member['total_child7'],// 累计释放
                'last_release_num' => keepPoint($community_num, 6) ?: '0.000000', // 今日释放
            ],
            'currency_name' => $currency_name
        ];
        return $r;
    }

    /**
     * 订单合同
     * @param int $member_id 用户id
     * @param int $order_type 类型 1满存 2独享
     * @param int $order_id 订单id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function order_contract($member_id, $order_type, $order_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_type) || empty($order_id))
            return $r;

        if (!in_array($order_type, [1, 2])) return $r;

        $result = [
            'contract_list' => [
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/template/1.png',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/template/2.png',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/template/3.png',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/template/4.png',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/template/5.png',
            ],
            'contract_status' => 0// 状态 0未签 1已签
        ];

        // 满存
        if ($order_type == 1) {
            $mining_pay = self::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']
                && !empty($mining_pay['contract_text'])
            ) {
                $result_json = json_decode($mining_pay['contract_text']);
                $result['contract_list'][1] = $result_json[1];
                $result['contract_list'][4] = $result_json[2];
                $result['contract_status'] = 1;
            }
        } // 独享
        elseif ($order_type == 2) {
            $mining_pay = AloneMiningPay::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']
                && !empty($mining_pay['contract_text'])
            ) {
                $result_json = json_decode($mining_pay['contract_text']);
                $result['contract_list'][1] = $result_json[1];
                $result['contract_list'][4] = $result_json[2];
                $result['contract_status'] = 1;
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 提交合同签名
     * @param int $member_id 用户id
     * @param int $order_type 类型 1满存 2独享
     * @param int $order_id 订单id
     * @param string $autograph 签名
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function submit_autograph($member_id, $order_type, $order_id, $autograph)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($order_type) || empty($order_id) || empty($autograph))
            return $r;

        if (!in_array($order_type, [1, 2])) return $r;

        // 满存
        if ($order_type == 1) {
            $mining_pay = self::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']) {
                $r['message'] = lang('no_signed');
                return $r;
            }

            // 生成签名文件
            $result = self::generate_contract($autograph, $order_type, $mining_pay['mining_code']);
            if ($result === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }

            // 更新状态
            $res = self::where(['id' => $mining_pay['id']])->update([
                'contract_status' => 1,
                'contract_text' => json_encode($result),
                'contract_time' => time()
            ]);
            if ($res === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }
        } // 独享
        elseif ($order_type == 2) {
            $mining_pay = AloneMiningPay::where(['id' => $order_id, 'member_id' => $member_id])->find();
            if (empty($mining_pay)) return $r;

            // 判断是否签名
            if ($mining_pay['contract_status']) {
                $r['message'] = lang('no_signed');
                return $r;
            }

            // 生成签名文件
            $result = self::generate_contract($autograph, $order_type, $mining_pay['mining_code']);
            if ($result === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }

            // 更新状态
            $res = AloneMiningPay::where(['id' => $mining_pay['id']])->update([
                'contract_status' => 1,
                'contract_text' => json_encode($result),
                'contract_time' => time()
            ]);
            if ($res === false) {
                $r['message'] = lang('operation_failed');
                return $r;
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        return $r;
    }

    /**
     * 生成合同
     * @param string $watermark_path 签名图片
     * @param int $order_type 类型
     * @param string $mining_code 合同编号
     * @return array|false
     * @throws OssException
     */
    public static function generate_contract($watermark_path, $order_type, $mining_code)
    {
        if (empty($watermark_path)) return false;
        // 目录
        $catalogue = 'common_mining';
        if ($order_type == 2) $catalogue = 'alone_mining';

        if (preg_match("/^(data:\s*image\/(\w+);base64,)/", $watermark_path, $result)) {
            $image_type = strtolower($result[2]);
            if (in_array($image_type, ['jpeg', 'jpg', 'png'])) {
                $mkdir_catalogue = ROOT_PATH . '/public/mining_img/' . $catalogue . '/';
                if (!is_dir($mkdir_catalogue))
                    mkdir($mkdir_catalogue, 0777, true);
                $new_file_path = $mkdir_catalogue . $mining_code . '.' . $image_type;
                $upload_result = file_put_contents($new_file_path, base64_decode(str_replace($result[1], '', $watermark_path)));
                if (empty($upload_result)) return false;
                $result_image_watermark = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '.' . $image_type, $mkdir_catalogue . $mining_code . '.' . $image_type);
            } else {
                return false;
            }
        } else {
            return false;
        }

        //合同模板
        $template_image_2 = imagecreatefrompng(ROOT_PATH . '/public/mining_img/template/2.png');
        $template_image_5 = imagecreatefrompng(ROOT_PATH . '/public/mining_img/template/5.png');

        //插入合同编号
        $font_path = ROOT_PATH . '/public/static/font/hagin.otf';
        $black = imagecolorallocate($template_image_2, 15, 23, 25);//字体颜色
        imageTtfText($template_image_2, 20, 0, 980, 210, $black, $font_path, $mining_code);

        // 签字日期
        imageTtfText($template_image_5, 20, 0, 735, 230, $black, $font_path, date('Y'));
        imageTtfText($template_image_5, 20, 0, 870, 230, $black, $font_path, date('m'));
        imageTtfText($template_image_5, 20, 0, 960, 230, $black, $font_path, date('d'));

        // 盖章日期
        imageTtfText($template_image_5, 20, 0, 235, 230, $black, $font_path, date('Y'));
        imageTtfText($template_image_5, 20, 0, 365, 230, $black, $font_path, date('m'));
        imageTtfText($template_image_5, 20, 0, 455, 230, $black, $font_path, date('d'));

        //签名图像
        $watermark_image = imagecreatefrompng($new_file_path);

        //合并合同图片
        imagecopy($template_image_2, $watermark_image, 360, 300, 0, 0, imagesx($watermark_image), imagesy($watermark_image));
        imagecopy($template_image_5, $watermark_image, 580, 90, 0, 0, imagesx($watermark_image), imagesy($watermark_image));

        //输出合并后合同图片
        imagepng($template_image_2, $mkdir_catalogue . $mining_code . '_2.png');
        $result_image_2 = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '_2.png', $mkdir_catalogue . $mining_code . '_2.png');
        imagepng($template_image_5, $mkdir_catalogue . $mining_code . '_5.png');
        $result_image_5 = self::oss_upload('contract/' . $catalogue . '/' . $mining_code . '_5.png', $mkdir_catalogue . $mining_code . '_5.png');

        if (empty($result_image_2)
            || empty($result_image_5)
        ) return false;
        return [$result_image_watermark, $result_image_2, $result_image_5];
    }

    /**
     * 图片推送oss
     * @param string $object oss路径
     * @param string $content 图片路径
     * @return false|string
     * @throws OssException
     */
    public static function oss_upload($object, $content)
    {
        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, false);
        try {
            if (!empty($object)
                && !empty($content)
            ) {
                $getOssInfo = $ossClient->uploadFile($bucket, $object, $content);
                $scheme = (!isset($_SERVER['HTTPS']) ||
                    $_SERVER['HTTPS'] == 'off' ||
                    $_SERVER['HTTPS'] == '') ? 'http' : 'https';
                return $getOssInfo['info']['url'] ?: $scheme . '://' . $bucket . '.' . $endpoint . '/' . $object;
            }
        } catch (OssException $e) {
            Log::write("合同上传:失败" . $e->getMessage());
            return false;
        }
    }

    public function product()
    {
        return $this->belongsTo('app\\common\\model\\CommonMiningProduct', 'product_id', 'id')->field('id,name,name_en,node_name,days');
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function miningcurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'mining_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function paycurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
