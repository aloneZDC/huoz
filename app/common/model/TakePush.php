<?php


namespace app\common\model;


use think\Model;

class TakePush extends Model
{
    const WAIT_PUSH = 0;

    const ALREADY_PUSH = 1;

    // 1 btc 2 usdt 3 eth 4 xrp 5 eos 6 doge 7neo 8los
    const CURRENCY_TYPE_ENUM = [
        'btc' => 1,
        'usdt' => 2,
        'eth' => 3,
        'eth_token' => 3,
        'xrp' => 4,
        'eos' => 5,
        'doge' => 6,
        'neo' => 7,
        'los' => 8,
        'trx_token' => 9,
        'fil' => 10,
        Currency::PUBLIC_CHAIN_NAME => 8,
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_name, currency_id');
    }

    //获取公链标签
    static function getPublicChainTag($member_id) {
        return $member_id.self::CURRENCY_TYPE_ENUM[Currency::PUBLIC_CHAIN_NAME];
    }
    //根据标签获取用户ID
    static function getMemberIdByPublicChainTag($tag) {
        return intval(substr($tag,0,strlen($tag)-1));
    }

    public static function addData($currencyId, $userId, $from, $to, $amount, $tag = '')
    {
        // 查询币种信息
        $currency = Currency::where('currency_id', $currencyId)->field('currency_id, currency_name, currency_mark, currency_type, is_tag, token_address')->find();

        if (1 == $currency['is_tag']) {
            $token = $tag;
        } else {
            $token = $currency['token_address'];
        }

        $type = 0;
        $currencyTypeEnum = self::CURRENCY_TYPE_ENUM;
        if (isset($currencyTypeEnum[$currency['currency_type']])) {
            $type = $currencyTypeEnum[$currency['currency_type']];
        }

        return (new self)->insertGetId([
            'currency_id' => $currencyId,
            'user_id' => $userId,
            'type' => $type,
            'afrom' => $from,
            'ato' => $to,
            'token' => $token,
            'amount' => $amount,
            'is_push' => self::WAIT_PUSH,
            'push_time' => 0,
            'add_time' => time(),
        ]);
    }
}
