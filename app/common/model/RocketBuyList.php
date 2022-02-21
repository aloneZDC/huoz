<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;
use think\Log;

class RocketBuyList extends Base
{
    const STATUS_FIRST = 0;
    const STATUS_SECOND = 1;
    const STATUS_THIRD = 2;

    const STATUS_ENUM = [
        self::STATUS_FIRST => "排队中",
        self::STATUS_SECOND => "排队成功",
        self::STATUS_THIRD => "排队失败",
    ];

    /**
     * 新增购买队列
     * @param int $member_id    用户ID
     * @param int $product_id  子闯关ID
     * @param int $num      支付金额
     * @param number $kmt_num  kmt燃料
     * @param number $type     类型 1参与闯关 2预约闯关 3复利 4预购抢单
     */
    static function add_list($member_id, $product_id, $num, $kmt_num, $type = 1) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $goods_info = RocketGoodsList::where(['id' => $product_id, 'status' => 1])->find();
        if (!$goods_info) return $r;

        $is_order = self::where(['member_id' => $member_id, 'goods_list_id' => $product_id, 'type' => $type])->find();
        if ($is_order) {
            $r['message'] = '不能重复参与此关';
            return $r;
        }
        $special_user_id = RocketConfig::getValue('special_user_id');//特殊账号
        if ($special_user_id) {
            $special_user_id = explode(',', $special_user_id);
        }
        $today = time();//当天
        $date = intval(date('H'));//当前小时
        //$start_date = date('Y-m-d', $goods_info['last_settlement_time']);
        if ($type == 1 || $type == 4) {//手动参与
            //判断闯关是否可参与
            if ($today < $goods_info['start_time']) {
                $r['message'] = $goods_info['name'] . '未开启';
                return $r;
            }
            if ($today > $goods_info['end_time']) {
                $r['message'] = $goods_info['name'] . '已结束';
                return $r;
            }
            if ($goods_info['rocket_status'] == 2) {
                $r['message'] = $goods_info['name'] . '已结束';
                return $r;
            }
            if ($goods_info['rocket_status'] == 3) {
                $r['message'] = $goods_info['name'] . '已结算';
                return $r;
            }
            if ($num <= 0) {
                $r['message'] = '请输入参与金额';
                return $r;
            }

            if ($type == 1) {
                if ($kmt_num <= 0 && $goods_info['kmt_rate'] > 0) {
                    $r['message'] = '燃料不能为空';
                    return $r;
                }
                $currency_user = CurrencyUser::getCurrencyUser($member_id, $goods_info['currency_id']);
            } else {
                $subscribe_currency_id = RocketConfig::getValue('subscribe_currency_id');
                $currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
            }
            if (!in_array($member_id, $special_user_id)) {//排除特殊账号
//                if ($num < $goods_info['min_payment']) {
//                    $r['message'] = '参与金额不能小于最小支付金额';
//                    return $r;
//                }
                if ($num > $goods_info['max_payment']) {
                    $r['message'] = '参与金额不能大于最大支付金额';
                    return $r;
                }
                if (sprintf('%.2f', $num + $goods_info['finish_money']) > sprintf('%.2f', $goods_info['price'])) {
                    $r['message'] = '参与金额不能大于闯关金额';
                    return $r;
                }
                if ($type == 1) {
                    $usdt_num = sprintf('%.6f', $num * ($goods_info['kmt_rate'] / 100));
                    $price = Trade::getLastTradePrice(99, 5);
                    //$price = Db::name('mtk_currency_price')->order('id desc')->value('price');
                    $mtk_num = $kmt_num;
                    if ($price > 0) {
                        //实际扣除 USDT / 每日MTK
                        $price = sprintf('%.6f', $price);
                        $mtk_num = sprintf('%.4f', $usdt_num / $price);
                    }
                    if (sprintf('%.2f', $kmt_num) != sprintf('%.2f', $mtk_num)) {
                        $r['message'] = '燃料有误，请重新参与';
                        return $r;
                    }
                }
            }

        } elseif ($type == 2 || $type == 3) {//预约排队、复利
            $subscribe_currency_id = RocketConfig::getValue('subscribe_currency_id');
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
        }

        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($type == 3) {//复利
            if ($currency_user['forzen_num'] < $num) {//冻结数量
                $r['code'] = SUCCESS;
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        } else {
            if ($currency_user['num'] < $num) {//可用数量
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        }

        //平台币账户
        $platform_currency_id = RocketConfig::getValue('platform_currency_id');
        $platform_currency_user = CurrencyUser::getCurrencyUser($member_id, $platform_currency_id);
        if (empty($platform_currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($platform_currency_user['num'] < $kmt_num) {
            $r['message'] = '燃料' . lang('insufficient_balance');
            return $r;
        }
        try {
            Db::startTrans();
            //添加队列
            $data = [
                'member_id' => $member_id,
                'goods_list_id' => $product_id,
                'num' => $num,
                'kmt_num' => $kmt_num,
                'add_time' => time(),
                'type' => $type
            ];
            $result = self::insertGetId($data);

            // 扣除资产
            if ($num > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7105, 'rocket_play_game', 'out', $num, $result, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                //复利的数量已冻结，添加排队是不需要重复冻结
                if ($type != 3) {
                    //扣除资产
                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    //冻结资产
                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'forzen_num' => $currency_user['forzen_num']])->setInc('forzen_num', $num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            // 扣除平台币资产
            if ($kmt_num > 0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($platform_currency_user['member_id'], $platform_currency_user['currency_id'], 7106, 'rocket_paly_mtk', 'out', $kmt_num, $result, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id'], 'num' => $platform_currency_user['num']])->setDec('num', $kmt_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                //冻结资产
                $flag = CurrencyUser::where(['cu_id' => $platform_currency_user['cu_id'], 'forzen_num' => $platform_currency_user['forzen_num']])->setInc('forzen_num', $kmt_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $result;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    /**
     * 新增订单
     * @param int $member_id    队列数据
     */
    static function handle_order($data) {
        foreach ($data as $key => $value) {
            $flag = RocketOrder::create_order($value['member_id'], $value['goods_list_id'], $value['num'], $value['kmt_num'], $value);
            if ($flag === false) {
                Log::write('用户生成订单失败：' . $value['member_id']);
                return false;
            }
        }
        return true;
    }

    /**
     * 预约记录
     * @param int $member_id    用户ID
     * @param int $page         页
     * @param int $rows         页数
     */
    static function get_subscribe_log($member_id, $page, $rows=15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $result = self::alias('a')
            ->join('rocket_goods_list b', 'a.goods_list_id=b.id')
            ->field('a.*,b.name,b.level')
            ->where(['member_id' => $member_id, 'type' => 2])
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if ($result) {
            $statusList = self::STATUS_ENUM;
            foreach ($result as $value) {
                $value['add_time'] = date('m/d H:i', $value['add_time']);
                $value['status_name'] = $statusList[$value['status']];
                if ($value['level'] == 1) {
                    $value['name'] = $value['name'] . ' 点火';
                } else {
                    $value['name'] = $value['name'] . ' 推进' . $value['level'];
                }
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }
}