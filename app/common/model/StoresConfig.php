<?php
//投票 俱乐部 配置
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class StoresConfig extends Model
{
    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];
        return array_column($list, 'uvs_value', 'uvs_key');
    }

    //资产数量
    static function card_num($member_id,$type) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $config = self::get_key_value();
        if(empty($config['stores_iqun_currency_mark'])) return $r;

        $currency = Currency::where(['currency_mark'=>$config['stores_iqun_currency_mark']])->field('currency_id,currency_name')->find();
        $total = 0;
        if($currency) {
            $users_currency = CurrencyUser::getCurrencyUser($member_id,$currency['currency_id']);
            if($users_currency) {
                if($type=='num') {
                    $total = $users_currency[StoresConvertConfig::NUM_FIELD];
                } elseif($type=='uc_card') {
                    $total = $users_currency[StoresConvertConfig::CARD_FIELD];
                } elseif($type=='uc_card_lock') {
                    $total = $users_currency[StoresConvertConfig::FINANCIAL_FIELD];
                }
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $total;
        return $r;
    }

    //卡包列表
    static function card_index($member_id){
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $config = self::get_key_value();
        if(empty($config['stores_iqun_currency_mark'])) return $r;

        $currency = Currency::where(['currency_mark'=>$config['stores_iqun_currency_mark']])->field('currency_id,currency_name,exchange_switch')->find();
        $total = 0;
        if($currency) {
            $users_currency = CurrencyUser::getCurrencyUser($member_id,$currency['currency_id']);
            if($users_currency) {
                $total = keepPoint($users_currency[StoresConvertConfig::CARD_FIELD] + $users_currency[StoresConvertConfig::FINANCIAL_FIELD],6);
            }
        }

        //是否可以申请赠送
        $convert_iqun = StoresConvertConfig::where(['currency_id'=>$currency['currency_id'],'to_currency_id'=>$currency['currency_id'],'currency_field'=>'num','to_currency_field'=>'card'])->find();
        $transfer_financial = StoresConvertConfig::where(['currency_id'=>$currency['currency_id'],'to_currency_id'=>$currency['currency_id'],'currency_field'=>'card','to_currency_field'=>'financial'])->find();

        //是否可以互转
        $is_transfer_other_iqun = $is_transfer_other_oqun = 2;
        if($currency['exchange_switch']==1) {
            $is_transfer_other = CurrencyUserTransferConfig::where(['currency_id'=>$currency['currency_id'],'type'=>'uc_card'])->find();
            if($is_transfer_other && $is_transfer_other['is_open']==1) $is_transfer_other_iqun = 1;
            $is_transfer_other = CurrencyUserTransferConfig::where(['currency_id'=>$currency['currency_id'],'type'=>'uc_card_lock'])->find();
            if($is_transfer_other && $is_transfer_other['is_open']==1) $is_transfer_other_oqun = 1;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'total' => $total,
            'currency' => [
                [
                    'currency_id' => $currency['currency_id'],
                    'currency_name' => lang('uc_card'),
                    'num' => $users_currency ? $users_currency[StoresConvertConfig::CARD_FIELD] : 0,
                    'is_apply_award' => $convert_iqun ? 1 : 2, //是否可以申请赠送
                    'is_transfer' => $transfer_financial ? 1 : 2, //是否可以划转
                    'is_transfer_other' => $is_transfer_other_iqun, //是否可以互转
                ],
                [
                    'currency_id' => $currency['currency_id'],
                    'currency_name' => lang('uc_card_lock'),
                    'num' => $users_currency ? $users_currency[StoresConvertConfig::FINANCIAL_FIELD] : 0,
                    'is_apply_award' => 2, //是否可以申请赠送
                    'is_transfer' => 2, //不能划转
                    'is_transfer_other' => $is_transfer_other_oqun, //是否可以互转
                ],
            ],
        ];
        return $r;
    }
}