<?php

namespace app\common\model;

use think\Exception;
use think\exception\PDOException;
use think\model\relation\BelongsTo;

class BfwCurrencyTransfer extends Base
{
    /**
     * 劃轉/提現數據處理
     * @param array $data 基礎數據
     * @param object $CurrencyAccount 提幣配置
     * @return array
     * @throws PDOException
     */
    public static function transfer($data, $CurrencyAccount)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 當天提幣總量，不能超過最大提幣數量
        if ((double)$CurrencyAccount->max > 0) {
            $todaySum = self::where(['member_id' => $data['member_id'], 'currency_id' => $CurrencyAccount->currency_id,
                'to_currency_id' => $CurrencyAccount->to_currency_id, 'ct_type' => $CurrencyAccount->cc_type])
                ->where("add_time", "between", [todayBeginTimestamp(), todayEndTimestamp()])->sum("number");
            $todaySum = !empty($todaySum) ? $todaySum : 0;
            if (bccomp($data['number'], ($CurrencyAccount->daily - $todaySum), 8) > 0) {
                $r['message'] = lang('bfw_error_limit');
                return $r;
            }
        }

        $from = CurrencyUser::getCurrencyUser($data['member_id'], $data['currency_id']);
        $to = CurrencyUser::getCurrencyUser($data['member_id'], $data['to_currency_id']);

        try {
            self::startTrans();

            $log_id = self::insertGetId($data);
            if (!$log_id) throw new Exception(lang('operation_failed_try_again'));

            // 錢包賬戶減少
            $flag = AccountBook::add_accountbook($data['member_id'], $data['currency_id'], $CurrencyAccount->abt_id, $CurrencyAccount->cc_type, 'out', $data['deduct'], $log_id, $data['ct_fee']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 扣除数量及手续费
            $flag = CurrencyUser::where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id'], 'num' => $from->num])->setDec('num', $data['deduct']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 賬戶增加
            $flag = AccountBook::add_accountbook($data['member_id'], $data['to_currency_id'], $CurrencyAccount->abt_id, $CurrencyAccount->cc_type, 'in', $data['number'], $log_id, $data['ct_fee']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['member_id' => $data['member_id'], 'currency_id' => $data['to_currency_id'], 'num' => $to->num])->setInc('num', $data['number']);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * 關聯幣種表 （一對一）
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_id, currency_name, currency_mark, account_type');
    }

    /**
     * 關聯幣種表 （一對一）
     * @return BelongsTo
     */
    public function tocurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id', 'currency_id')->field('currency_id, currency_name, currency_mark, account_type');
    }

    /**
     * 關聯用戶表 （一對一）
     * @return BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id')->field(['member_id', 'ename', 'email']);
    }
}