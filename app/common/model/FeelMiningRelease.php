<?php

namespace app\common\model;

use think\Exception;
use think\exception\PDOException;
use think\Log;


class FeelMiningRelease extends Base
{

    /**
     * 体验矿机释放
     * @param $real_detail
     * @param $is_out
     * @param $release_num_avail
     * @param $currency_ratio
     * @param $real_currency_num
     * @return array
     * @throws PDOException
     */
    public static function Release($real_detail, $is_out, $release_num_avail, $currency_ratio, $real_currency_num)
    {
        $r = ['code' => ERROR1, 'message' => lang('feel_release_failure'), 'result' => null];
        $currency_user = CurrencyUser::getCurrencyUser($real_detail['member_id'], $real_detail['real_currency_id']);
        if (empty($currency_user)) return $r;

        try {
            self::startTrans();
            if ($is_out) {
                $flag = FeelMining::where(['id' => $real_detail['id']])->setField('status', FeelMining::STATUS_OUT);
                if (!$flag) throw new Exception(lang('feel_release_failure'));
            }
            $flag = FeelMining::where(['id' => $real_detail['id'], 'release_num_avail' => $real_detail['release_num_avail']])
                ->exp('release_num', $release_num_avail)
                ->exp('release_time', time())->inc('release_num_avail', $release_num_avail)->update();
            if (!$flag) throw new Exception(lang('feel_release_failure'));

            if ($real_currency_num > 0.000001) {
                $insert_id = self::insertGetId([
                    'third_id' => $real_detail['id'],
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $real_detail['currency_id'],
                    'release_num' => $release_num_avail,
                    'release_time' => time(),
                    'third_percent' => $currency_ratio,
                    'real_currency_id' => $currency_user['currency_id'],
                    'real_currency_num' => $real_currency_num,
                ]);
                if (!$insert_id) throw new Exception(lang('feel_release_failure'));

                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6340, 'feel_mining_release', 'in', $real_currency_num, $insert_id, 0);
                if (!$flag) throw new Exception(lang('feel_release_failure'));

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $real_currency_num);
                if (!$flag) throw new Exception(lang('feel_release_failure'));
            }

            self::commit();

            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            return $r;
        } catch (Exception $e) {
            self::rollback();
//            Log::write($real_detail['member_id'] . "体验矿机释放任务错误:" . $e->getMessage());
            $r['message'] = lang('feel_release_failure');
            return $r;
        }
    }
}
