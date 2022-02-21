<?php
//理财包上级奖励记录

namespace app\common\model;


use think\Exception;
use think\Model;

class TransferFinancialAward extends Model
{
    static function add_item($user_id,$currency_id,$asset_type,$num,$time) {
        return self::insertGetId([
            'tta_user_id'  => $user_id,
            'tta_currency_id'  => $currency_id,
            'tta_num'  => $num,
            'tta_time'  => time(),
            'tta_asset_type'  => $asset_type,
            'tta_day' => $time,
        ]);
    }

    //奖励到理财包
    static function award($user_id,$currency_id,$asset_type,$num,$time) {
        try{
            self::startTrans();
            //添加奖励记录
            $log_id = self::add_item($user_id,$currency_id,$asset_type,$num,$time);
            if(!$log_id) throw new Exception("添加奖励记录失败");

            //增加理财包记录
            $log_id = TransferToFinancial::add_financial('award',$user_id,$currency_id,$num,0,0,$log_id,$asset_type);
            if(!$log_id) throw new Exception("添加理财包记录失败");

            $users_currency = CurrencyUser::getCurrencyUser($user_id,$currency_id);
            if(empty($users_currency)) throw new Exception("获取用户资产失败");

            $currency_field = TransferToAssetConfig::get_currency_field($asset_type,true);

            //增加理财包资产
            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],$currency_field=>$users_currency[$currency_field]])->setInc($currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
        } catch (Exception $e) {
            self::rollback();
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'tta_user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'tta_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}