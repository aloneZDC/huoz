<?php
//币币账户 钱包账户资产划转
namespace app\common\model;


use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use think\Model;

class CurrencyUserBbTransfer extends Model{
    static function getTransferConfig($member_id) {
        $currencylist = Currency::field('currency_id,currency_name,trade_transfer_currency')->where(['is_line'=>1,'is_trade_currency'=>1,'trade_transfer_currency'=>['gt',0] ])->select();
        if($currencylist) {
            $currency_user = CurrencyUser::field('currency_id,num')->where(['member_id' => $member_id])->select();
            if($currency_user) {
                $currency_user = array_column($currency_user->toArray(),null,'currency_id');
            } else {
                $currency_user = [];
            }

            foreach ($currencylist as &$item) {
                $item['is_open'] = Config::get_value('wallet_to_bb_is_open',0);
                $item['fee'] = Config::get_value('wallet_to_bb_fee',0);
                $item['num'] = isset($currency_user[$item['currency_id']]) ? $currency_user[$item['currency_id']]['num'] : 0;
                $item['trade_transfer_currency_num'] = isset($currency_user[$item['trade_transfer_currency']]) ? $currency_user[$item['trade_transfer_currency']]['num'] : 0;
            }
        } else {
            $currencylist = [];
        }
        return [
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $currencylist,
        ];
    }

    static function transfer($member_id,$currency_id,$num,$type) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        $is_open = Config::get_value('wallet_to_bb_is_open',0);
        if($is_open!=1) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        if($currency_id<=0 || !is_numeric($num) || $num<=0 || !in_array($type,['to_bb','to_wallet'])) {
            $r['message'] = lang('lan_quantity_cannot_less_equal');
            return $r;
        }

        $currency = Currency::field('currency_id,currency_name,trade_transfer_currency')->where(['currency_id'=>$currency_id,'is_line'=>1,'is_trade_currency'=>1,'trade_transfer_currency'=>['gt',0] ])->find();
        if(empty($currency)) return $r;

        $r['message'] = lang('lan_network_busy_try_again');
        //币币账户
        $bb_currency_user = CurrencyUser::getCurrencyUser($member_id,$currency['currency_id']);
        //钱包账户
        $wallet_currency_user = CurrencyUser::getCurrencyUser($member_id,$currency['trade_transfer_currency']);
        if(!$bb_currency_user || !$wallet_currency_user) return $r;

        if($type=='to_bb') {
            if($wallet_currency_user['num']<$num) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        } elseif ($type=='to_wallet') {
            if($bb_currency_user['num']<$num) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        } else {
            return $r;
        }

        $num = keepPoint($num,6);
        $fee = Config::get_value('wallet_to_bb_fee',0);
        $fee_num = 0;//手续费
        $actual_num = $num; //实际到账
        if($fee>0) {
            $fee_num = keepPoint($num * $fee/100,6);
            $actual_num = keepPoint($num-$fee_num,6);
        }

        try{
            self::startTrans();

            $log_id = self::insertGetId([
                'type' => $type,
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'num' => $num,
                'actual_num' => $actual_num,
                'fee' => $fee,
                'add_time' => time(),
            ]);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            if($type=='to_bb') {
                //币币账户增加
                $flag = AccountBook::add_accountbook($bb_currency_user['member_id'],$bb_currency_user['currency_id'],2103,'from_wallet','in',$actual_num,$log_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$bb_currency_user['cu_id'],'num'=>$bb_currency_user['num']])->setInc('num',$actual_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                // 钱包账户减少
                $flag = AccountBook::add_accountbook($wallet_currency_user['member_id'],$wallet_currency_user['currency_id'],2100,'to_bb','out',$num,$log_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$wallet_currency_user['cu_id'],'num'=>$wallet_currency_user['num']])->setDec('num',$num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            } elseif ($type=='to_wallet') {
                //币币账户减少
                $flag = AccountBook::add_accountbook($bb_currency_user['member_id'],$bb_currency_user['currency_id'],2101,'to_wallet','out',$num,$log_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$bb_currency_user['cu_id'],'num'=>$bb_currency_user['num']])->setDec('num',$num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                // 钱包账户增加
                $flag = AccountBook::add_accountbook($wallet_currency_user['member_id'],$wallet_currency_user['currency_id'],2102,'from_bb','in',$actual_num,$log_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$wallet_currency_user['cu_id'],'num'=>$wallet_currency_user['num']])->setInc('num',$actual_num);
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
}
