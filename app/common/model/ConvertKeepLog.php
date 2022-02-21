<?php
//兑换到赠送金

namespace app\common\model;


use app\common\model\AccountBook as AccountBookModel;
use think\Exception;
use think\Model;
use think\model\relation\BelongsTo;

class ConvertKeepLog extends Model
{
    static function convert($member_id,$id,$number)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (!is_numeric($number) || !is_numeric($id) || $id<0) {
            return $r;
        }

        if ($number <= 0) {
            $r['message'] = lang('token_price_error');
            return $r;
        }

        $config = ConvertKeepConfig::where('id', $id)->find();
        if (empty($config)) {
            $r['message'] = lang('unopened_exchange');
            return $r;
        }

        $toNumber = keepPoint($number * $config['ratio'], 6);
        $toDayNum = self::where([
            'user_id' => $member_id,
            'cc_id' => $config['id'],
        ])->whereTime('create_time', 'today')->sum('number'); // 今日兑换数量
        $config['day_max_num'] -= $toDayNum - $number;

        if ($config['day_max_num'] <= 0) {
            $r['message'] = lang('maximum_convertibility_today');
            return $r;
        }

        $usersCurrency = CurrencyUser::getCurrencyUser($member_id, $config['currency_id']);
        $fee = 0;
        //先扣
        if($config['fee']>0) {
            $fee = keepPoint($number * ((double) $config['fee'] * 0.01),6);
            $number = keepPoint($number + $fee,6);
        }

        //实际到账数量
        $actualNumber = $toNumber;
        if (!$usersCurrency || $usersCurrency['num'] < $number) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $toUsersCurrency = CurrencyUser::getCurrencyUser($member_id, $config['to_currency_id']);
        if(!$toUsersCurrency) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $r;
        }

        try {
            self::startTrans();

            // 纪录
            $convertLog = ConvertLog::create([
                'currency_id' => $config['currency_id'],
                'to_currency_id' => $config['to_currency_id'],
                'user_id' => $member_id,
                'cc_id' => $config['id'],
                'number' => $number,
                'to_number' => $actualNumber,
                'status' => 1,
                'fee' => $config['fee'],
                'create_time' => time(),
            ]);
            //添加扣除账本
            $res = AccountBookModel::add_accountbook($member_id, $config['currency_id'], 38, 'asset_in_keep_log', 'out', $number, $convertLog['id'], $fee, $config['currency_id']);
            if(!$res) throw new Exception(lang('system_error_please_try_again_later'));

            //扣除资产
            $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setDec('num',$number);
            if(!$flag) throw new Exception(lang('system_error_please_try_again_later'));

            //添加冻结记录
            $flag = HongbaoKeepLog::add_log('dnc_convert',$member_id,$toUsersCurrency['currency_id'],$actualNumber,$convertLog['id'],0,0);
            if(!$flag) throw new Exception(lang('system_error_please_try_again_later'));

            //增加冻结资产
            $flag = CurrencyUser::where(['cu_id'=>$toUsersCurrency['cu_id'],'keep_num'=>$toUsersCurrency['keep_num']])->setInc('keep_num',$actualNumber);
            if(!$flag) throw new Exception(lang('system_error_please_try_again_later'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang("success_operation");
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_id, currency_name as currency_mark');
    }

    /**
     * @return BelongsTo
     */
    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id','currency_id')->field('currency_id, currency_name as currency_mark');
    }
}
