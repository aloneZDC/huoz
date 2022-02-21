<?php

namespace app\common\model;

use think\model\relation\BelongsTo;

class BfwCurrencyConfig extends Base
{
    /**
     * 配置驗證
     * @param array $where 條件
     * @param float $number 數量
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function validateConfig($where, $number)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        $CurrencyAccount = self::where($where)->find();

        // 提幣通道關閉
        if (empty($CurrencyAccount) || 1 == $CurrencyAccount->status) {
            $r['message'] = lang('bfw_error_transfer');
            return $r;
        }

        // 判斷是否需要後端處理
        if (1 == $CurrencyAccount->is_handle) {
            $r['message'] = lang('bfw_error_transfer');
            return $r;
        }

        // 小於最小提幣數量
        if (bccomp($CurrencyAccount->min, $number, 8) > 0) {
            $r['message'] = lang('bfw_error_min', ['min' => $CurrencyAccount->min]);
            return $r;
        }

        // 超過最大提幣數量
        if ($CurrencyAccount->max > 0
            && bccomp($number, $CurrencyAccount->max, 8) > 0
        ) {
            $r['message'] = lang('bfw_error_max', ['min' => $CurrencyAccount->max]);
            return $r;
        }

        return ['code' => SUCCESS, 'result' => $CurrencyAccount];
    }

    /**
     * 關聯幣種表（一對一）
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
        return $this->belongsTo(Currency::class, 'to_currency_id', 'currency_id')->field('currency_id, currency_name, currency_mark, account_type, tcoin_fee, recharge_address');
    }

    /**
     * 關聯幣種表 （一對一）
     * @return BelongsTo
     */
    public function currencytype()
    {
        return $this->belongsTo(Currency::class, 'cu_type', 'currency_id')->field('currency_id, currency_name, currency_mark, account_type');
    }
}