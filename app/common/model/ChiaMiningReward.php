<?php

namespace app\common\model;

use think\Exception;
use think\Model;

/**
 * CHIA(奇亚)云算力
 * Class AloneMiningPay
 * @package app\common\model
 */
class ChiaMiningReward extends Model
{
	public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function thirdmember() {
        return $this->belongsTo('app\\common\\model\\Member', 'third_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function chiaminingpay() {
        return $this->belongsTo('app\\common\\model\\ChiaMiningPay', 'chia_mining_pay_id', 'id')->field('member_id,id,tnum,real_pay_num');
    }
}