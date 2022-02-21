<?php


namespace app\common\model;


use think\Model;

class UserAirDiffDay extends Model
{

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    public static function add_log($userId, $currencyId, $number, $fee)
    {
        $today = todayBeginTimestamp();
        $self = new self();
        $log = $self->where('user_id', $userId)->where('currency_id', $currencyId)->where('add_time', $today)->find();
        if ($log) {
            $log['number'] += $number;
            $log['fee'] += $fee;
            $res = $log->save();
            if (!$res) {
                return false;
            }
            return $log['id'];
        }

        return $self->insertGetId([
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'number' => $number,
            'fee' => $fee,
            'add_time' => $today
        ]);
    }
}