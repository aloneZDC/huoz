<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use app\common\model\CurrencyUser;
use app\common\model\ChiaMiningPay;
use app\common\model\AccountBook;
use app\common\model\ChiaMiningMember;
use app\common\model\ChiaMiningMemberSummary;
use app\common\model\ChiaMiningReward;

/**
 * CHIA(奇亚)云算力产币记录
 * Class ChiaMiningRelease
 * @package app\common\model
 */
class ChiaMiningRelease extends Model
{
    /**
     * 矿机产币
     * @param array $mining_pay 入金记录
     * @param array $mining_config 配置
     * @param int $release_num_per_tnum 获取chia24H平均价格
     * @param array $today_start 时间
     * @param int $type 1实付T数 2赠送T数
     * @return int[]
     * @throws \think\exception\PDOException
     */
    static function release($mining_pay, $mining_config, $release_num_per_tnum, $today_start, $type = 1)
    {
        $res = [
            'release_id' => 0,
            'release_num' => 0,
        ];

        // 应该释放数量
        $total_currency_num = keepPoint($mining_pay['max_tnum'] * $release_num_per_tnum, 6);

        $total_award_num = $total_currency_num;
        $fee_num = 0;
        $platform_num = 0;
        $config = \app\common\model\ChiaMiningTake::where(['member_id' => $mining_pay['member_id'], 'third_id' => $mining_pay['id']])->find();
        if (!empty($config['take_rate']) || $mining_config['release_platform_percent'] > 0) {//平台抽点 5%
            $take_rate = !empty($config['take_rate']) ? $config['take_rate'] : $mining_config['release_platform_percent'];
            $platform_num = keepPoint($total_currency_num * $take_rate / 100, 6);
        }
        if (!empty($config['service_rate']) || $mining_config['release_fee_percent'] > 0) {//技术服务费
            $service_rate = !empty($config['service_rate']) ? $config['service_rate'] : $mining_config['release_fee_percent'];
            $total_currency_num = keepPoint($total_currency_num - $platform_num, 6);
            $fee_num = keepPoint($total_currency_num * $service_rate / 100, 6);
        }
        
        // 实际到账
        if ($fee_num > 0 || $platform_num > 0) {
            $total_award_num = keepPoint($total_award_num - $fee_num - $platform_num, 6);
        }

        // 默认全部到账
        $award_num = $total_award_num;
        if ($award_num < 0.000001) {
            return $res;
        }

        $real_currency_user = CurrencyUser::getCurrencyUser($mining_pay['member_id'], $mining_config['release_currency_id']);
        if (empty($real_currency_user)) {
            return $res;
        }

        try {
            self::startTrans();

            if ($award_num > 0.000001) {
                $flag = ChiaMiningPay::where(['id' => $mining_pay['id'], 'total_release_num' => $mining_pay['total_release_num']])->update([
                    'last_release_day' => $today_start['today_start'],
                    'last_release_num' => $total_award_num,
                    'total_release_num' => ['inc', $award_num], //总释放到可用数量
                ]);
                if (!$flag) throw new Exception("更新订单失败");
            }

            // 添加订单
            $item_id = self::insertGetId([
                'member_id' => $real_currency_user['member_id'],
                'currency_id' => $real_currency_user['currency_id'],
                'num' => $award_num, //到可用数量
                'release_time' => $today_start['today_start'],
                'third_id' => $mining_pay['id'],
                'third_num' => $release_num_per_tnum,
                'fee_num' => $fee_num, //管理手续费数量
                'platform_num' => $platform_num,//平台抽点数量
                'add_time' => time(),
                'type' => 1
            ]);
            if (!$item_id) throw new Exception("添加产币记录失败");

            if ($award_num > 0.000001) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($real_currency_user['member_id'], $real_currency_user['currency_id'], 7006, 'chia_mining_release_income', 'in', $award_num, $item_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id' => $real_currency_user['cu_id'], 'num' => $real_currency_user['num']])->setInc('num', $award_num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();

            //更新个人挖矿收益
            ChiaMiningMember::where(['member_id' => $real_currency_user['member_id']])->setInc('release_num', $award_num);
            
            $res['release_id'] = $item_id;
            $res['release_num'] = $award_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write("奇亚矿机 - 产币任务:失败" . $e->getMessage());
        }
        return $res;
    }

    /**
     * 产币记录
     * @param int $member_id 用户ID
     * @param int $page 页码
     * @param int $product_id 订单id
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function release_list($member_id, $product_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null, 'total' => 0];

        $list = self::field('num,release_time')
            ->where(['member_id' => $member_id, 'third_id' => $product_id])
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['release_time'] = date('Y-m-d H:i', $item['release_time']);
            $item['release'] = '+' . $item['num'];
            $item['xch_name'] = 'XCH';
            $item['title'] = '产币收益';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['total'] = self::field('num,add_time')->where(['member_id' => $member_id])->order("id desc")->count();
        return $r;
    }
}