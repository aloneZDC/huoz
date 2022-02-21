<?php

namespace app\common\model;

use think\Exception;
use think\Log;

/**
 * 承包兑换记录
 * Class WarrantExchange
 * @package app\common\model
 */
class WarrantExchange extends Base
{

    /**
     * 承保(兑换)页面
     * @param $member_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function exchange_index($member_id, $goods_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($goods_id)) return $r;

        // 判断配置
        $config = WarrantConfig::get_configs();
        if (empty($config)) {
            $r['message'] = lang("no_data");
            return $r;
        }

        // 判断商品是否存在
        $goods = WarrantGoods::where(['id' => $goods_id, 'status' => WarrantGoods::STATUS_UP])->find();
        if (empty($goods)) {
            $r['message'] = lang('no_data');
            return $r;
        }

        $warrant_currency_name = Currency::where(['currency_id' => $config['warrant_currency_id']])->value('currency_name');
        $miner_currency_name = Currency::where(['currency_id' => $goods['currency_id']])->value('currency_name');

        $warrant_currency = CurrencyUser::getCurrencyUser($member_id, $config['warrant_currency_id']);
//        $miner_currency = CurrencyUser::getCurrencyUser($member_id, $goods['currency_id']);
        $r['result'] = [
            'warrant_currency_id' => $warrant_currency['currency_id'],
            'warrant_currency_name' => $warrant_currency_name,
            'warrant_currency_num' => $warrant_currency['num'],
//            'miner_currency_id' => $miner_currency['currency_id'],
            'miner_currency_name' => $miner_currency_name,
//            'miner_currency_num' => $miner_currency['num'],
            'warrant_term' => $config['warrant_term'],
            'warrant_ratio' => $config['warrant_ratio'],
            'exchange_min' => $config['exchange_min'],
        ];
        $r['message'] = lang("data_success");
        $r['code'] = SUCCESS;
        return $r;
    }

    /**
     * 承保兑换
     * @param $member_id
     * @param $goods_id
     * @param $number
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function exchange($member_id, $goods_id, $number)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($goods_id) || empty($number)) return $r;
        $goods = WarrantGoods::where(['status' => WarrantGoods::STATUS_UP, 'id' => $goods_id])->find();
        if (empty($goods)) {
            $r['message'] = lang('goods_down_or_delete');
            return $r;
        }
        // 超出商品总价格
        if ($number > $goods['price']) {
            $r['message'] = lang('lan_internal_buy_gt_limit2');
            return $r;
        }

        $config = WarrantConfig::get_configs();
        if (empty($config)) {
            $r['message'] = lang('lan_reg_the_network_busy');
            return $r;
        }

        // 判断最小兑换
        if($config['exchange_min'] > 0
            && $number < $config['exchange_min']) {
            $r['message'] = lang('flop_min_num',['num'=>$config['exchange_min']]);
            return $r;
        }

        // 判断余额
        $CurrencyUser = CurrencyUser::getCurrencyUser($member_id, $config['warrant_currency_id']);
        if (!$CurrencyUser) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        if ($number > $CurrencyUser['num']) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 查询或创建 MTK
        $MtkUser = CurrencyUser::getCurrencyUser($member_id, $goods['currency_id']);
        if (!$MtkUser) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 承保期180天
        $today_start = strtotime(date('Y-m-d'));
        $warrant_term_time = $today_start + ($config['warrant_term'] * 86399);
//        $warrant_term_time = time() + 600; // 测试设置10分钟

        // 到账MTK
        $mtk_num = bcmul($number, $config['warrant_ratio'], 6);

        // 处理数据
        try {
            self::startTrans();

            $exchange = self::create([
                'member_id' => $member_id,
                'goods_id' => $goods['id'],
                'currency_id' => $config['warrant_currency_id'],
                'to_currency_id' => $goods['currency_id'],
                'from_num' => $number,
                'mtk_num' => $mtk_num,
                'add_time' => time(),
                'end_time' => $warrant_term_time,
            ]);

            // 扣USDT账本
            $flag = AccountBook::add_accountbook($member_id, $config['warrant_currency_id'], 6601, 'warrant_exchange', 'out', $number, $exchange['id']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            // 扣USDT资产
            $flag = CurrencyUser::where(['cu_id' => $CurrencyUser['cu_id'], 'num' => $CurrencyUser['num']])->setDec('num', $number);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 加MTK账本
            $flag = AccountBook::add_accountbook($member_id, $goods['currency_id'], 6601, 'warrant_exchange', 'in', $mtk_num, $exchange['id']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            // 加MTK资产
            $flag = CurrencyUser::where(['cu_id' => $MtkUser['cu_id'], 'num' => $MtkUser['num']])->setInc('num', $mtk_num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 更新商品资产
            $flag = WarrantGoods::where(['id' => $goods['id'], 'market' => $goods['market']])
                ->dec('price', $mtk_num)->inc('market', $mtk_num)
                ->exp('update_time', time())->update();
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang("success_operation");
            return $r;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
            return $r;
        }
    }

    /**
     * 承保记录
     * @param $member_id
     * @param $page
     * @param $rows
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function exchange_log($member_id, $page, $rows)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (!isInteger($page) || $rows > 50 || empty($member_id)) return $r;
        $list = self::alias("t1")
            ->where(['t1.member_id' => $member_id])
            ->join(config("database.prefix") . "currency t2", "t2.currency_id=t1.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency t3", "t3.currency_id=t1.to_currency_id", "LEFT")
            ->join(config("database.prefix") . "warrant_goods t4", "t4.id=t1.goods_id", "LEFT")
            ->field(['t1.id', 't1.from_num', 't1.mtk_num', 't1.add_time', 't1.end_time'])
            ->field(['t2.currency_name', 't3.currency_name' => 'to_currency_name', 't4.title'])
            ->page($page, $rows)->order(['t1.id' => 'desc'])->select();
        if (empty($list)) {
            $r['message'] = lang("no_data");
            return $r;
        }

        foreach ($list as &$value) {
            $end_time = $value['end_time'] - time();
            $value['end_time'] = $end_time > 0 ? $end_time : 0;
            $value['add_time'] = date('Y-m-d H:i:s');
            $value['status'] = $end_time > 0 ? 1 : 2;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $list;
        return $r;
    }

    /**
     * 承保到期处理
     * @param $warrant_exchange
     * @return array
     * @throws \think\exception\PDOException
     */
    public static function exchange_expire($warrant_exchange)
    {
        $r = ['code' => ERROR1, 'message' => '操作失败', 'result' => []];
        try {
            self::startTrans();

            // 更新状态
            $flag = self::where(['id' => $warrant_exchange['id'], 'status' => 0])->setField('status', 1);
            if (!$flag) throw new Exception('更新状态失败');

            // 更新商品剩余数量
            $flag = WarrantGoods::where(['id' => $warrant_exchange['goods_id']])
                ->inc('price', $warrant_exchange['mtk_num'])
                ->inc('return_num', $warrant_exchange['mtk_num'])
                ->exp('update_time', time())
                ->update();
            if (!$flag) throw new Exception('更新商品剩余数量失败');

            // 扣除MTK记录
            $flag = AccountBook::add_accountbook($warrant_exchange['member_id'], $warrant_exchange['to_currency_id'], 6602, 'exchange_expire', 'out', $warrant_exchange['mtk_num'], $warrant_exchange['id']);
            if (!$flag) throw new Exception("添加账本失败");

            // 扣除MTK余额
            $currency_user = CurrencyUser::getCurrencyUser($warrant_exchange['member_id'], $warrant_exchange['to_currency_id']);
            if (!$currency_user) throw new Exception("获取资产错误");
            $currency_num = 0;
            if ($currency_user['num'] > 0
                && $currency_user['num'] >= $warrant_exchange['mtk_num']) {
                $currency_num = bcsub($currency_user['num'], $warrant_exchange['mtk_num'], 6);
            }
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setField('num', $currency_num);
            if (!$flag) throw new Exception("扣除资产失败");

            self::commit();

            $r['code'] = SUCCESS;
            $r['message'] = '操作成功';
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }
}