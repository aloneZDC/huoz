<?php
//四币连发
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class BbfTogether extends Base
{
    //入金
    static function buy($member_id,$bbf_currency_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        //获取配置
        $bbf_currency = BbfTogetherCurrency::getCurrency($bbf_currency_id,0);
        if(empty($bbf_currency)) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        //第一个支付币种 - 扣除
        $pay_currency_user = null;
        if($bbf_currency['pay_currency_percent']>0) {
            if($bbf_currency['pay_currency_num']<=0) return $r;
            $pay_currency_user = CurrencyUser::getCurrencyUser($member_id,$bbf_currency['pay_currency_id']);
            if(empty($pay_currency_user) || $pay_currency_user['num']<$bbf_currency['pay_currency_num']) {
                $r['message'] = $bbf_currency['pay_currency_name'].lang('insufficient_balance');
                return $r;
            }
        }

        //第二个支付币种 - 扣除
        $pay_other_currency_user = null;
        if($bbf_currency['pay_other_currency_percent']>0) {
            if($bbf_currency['pay_other_currency_num']<=0) return $r;

            $pay_other_currency_user  = CurrencyUser::getCurrencyUser($member_id,$bbf_currency['pay_other_currency_id']);
            if(empty($pay_other_currency_user) || $pay_other_currency_user['num']<$bbf_currency['pay_other_currency_num']) {
                $r['message'] = $bbf_currency['pay_other_currency_name'].lang('insufficient_balance');
                return $r;
            }
        }

        //质押币种 - 扣除
        $pledge_currency_user = null;
        if($bbf_currency['pledge_currency_percent']>0) {
            if($bbf_currency['pledge_currency_num']<=0) return $r;

            $pledge_currency_user  = CurrencyUser::getCurrencyUser($member_id,$bbf_currency['pledge_currency_id']);
            if(empty($pledge_currency_user) || $pledge_currency_user['num']<$bbf_currency['pledge_currency_num']) {
                $r['message'] = $bbf_currency['pledge_currency_name'].lang('insufficient_balance');
                return $r;
            }
        }

        //锁仓币种
        if($bbf_currency['lock_currency_percent']>0 && $bbf_currency['lock_currency_num']<=0) return $r;

        try {
            self::startTrans();

            //增加用户汇总
            $flag = BbfTogetherMember::addMember($member_id,$bbf_currency['release_currency_id']);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'release_currency_id' => $bbf_currency['release_currency_id'],
                'lock_currency_id' => $bbf_currency['lock_currency_id'],
                'lock_currency_num' => $bbf_currency['lock_currency_num'],
                'lock_currency_avail' => $bbf_currency['lock_currency_num'],
                'pledge_currency_id' => $bbf_currency['pledge_currency_id'],
                'pledge_currency_num' => $bbf_currency['pledge_currency_num'],
                'pledge_status' => 0,
                'pay_currency_id' => $bbf_currency['pay_currency_id'],
                'pay_currency_num' => $bbf_currency['pay_currency_num'],
                'pay_other_currency_id' => $bbf_currency['pay_other_currency_id'],
                'pay_other_currency_num' => $bbf_currency['pay_other_currency_num'],
                'pay_total_num' => $bbf_currency['pay_total_num'],
                'status' => 0,
                'add_time' => time(),
            ]);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            if($bbf_currency['pay_currency_num']>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($pay_currency_user['member_id'],$pay_currency_user['currency_id'],6301,'bbf_together','out',$bbf_currency['pay_currency_num'],$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$pay_currency_user['cu_id'],'num'=>$pay_currency_user['num']])->setDec('num',$bbf_currency['pay_currency_num']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            if($bbf_currency['pay_other_currency_num']>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($pay_other_currency_user['member_id'],$pay_other_currency_user['currency_id'],6301,'bbf_together','out',$bbf_currency['pay_other_currency_num'],$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$pay_other_currency_user['cu_id'],'num'=>$pay_other_currency_user['num']])->setDec('num',$bbf_currency['pay_other_currency_num']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            if($bbf_currency['pledge_currency_num']>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($pledge_currency_user['member_id'],$pledge_currency_user['currency_id'],6301,'bbf_together','out',$bbf_currency['pledge_currency_num'],$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$pledge_currency_user['cu_id'],'num'=>$pledge_currency_user['num']])->setDec('num',$bbf_currency['pledge_currency_num']);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //撤销抵押
    static function cancel_pledge($member_id,$bbf_together_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        $bbf_together = self::where(['id'=>$bbf_together_id,'member_id'=>$member_id])->find();
        if(empty($bbf_together)) return $r;

        if($bbf_together['pledge_status']!=0) return $r;

        //获取配置
        $bbf_currency_config = BbfTogetherCurrency::getCurrency($bbf_together['release_currency_id'],0);
        if(empty($bbf_currency_config)) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        //pledge_cancel_fee
        $pledge_currency_user  = CurrencyUser::getCurrencyUser($member_id,$bbf_together['pledge_currency_id']);
        if(empty($pledge_currency_user)) return $r;

        $pledge_num = $bbf_together['pledge_currency_num'];
        $pledge_fee = 0;
        if($bbf_currency_config['pledge_cancel_fee']>0) {
            $pledge_fee = keepPoint($pledge_num * $bbf_currency_config['pledge_cancel_fee'] / 100,6);
            $pledge_num = keepPoint($pledge_num-$pledge_fee,6);
        }

        try {
            self::startTrans();

            //更改为解除质押状态
            $flag = self::where(['id'=>$bbf_together_id, 'pledge_status' => 0])->setField('pledge_status',1);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($pledge_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($pledge_currency_user['member_id'],$pledge_currency_user['currency_id'],6306,'bbf_together_cancel_pledge','in',$pledge_num,$bbf_together['id'],0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$pledge_currency_user['cu_id'],'num'=>$pledge_currency_user['num']])->setInc('num',$pledge_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function releasecurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'release_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function lockcurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'lock_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function pledgecurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'pledge_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function paycurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function payothercurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'pay_other_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
