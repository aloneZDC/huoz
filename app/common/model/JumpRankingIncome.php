<?php
//跳跃排名倒序加权算法 - 订单收益
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class JumpRankingIncome extends Model
{
    //添加收益记录
    static function addItem($member_id,$currency_id,$num,$ranking,$multiple,$add_time,$third_id,$currency_config) {
        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(!$currency_user) return;

        $fee = 0;
        if($currency_config['ranking_fee']>0) {
            $fee = keepPoint($num * $currency_config['ranking_fee'] / 100,6);
            $num = keepPoint($num-$fee,6);
        }

        try{
            self::startTrans();

            $insert_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'num' => $num,
                'fee' => $fee,
                'ranking' => $ranking,
                'multiple' => $multiple,
                'add_time' => $add_time,
                'third_id' => $third_id,
            ]);
            if(!$insert_id) throw new Exception("添加收益记录失败");

            //添加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2203,'jump_ranking_income','in',$num,$insert_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            JumpRankingMemberSummary::update_ranking($currency_user['member_id'],$currency_user['currency_id'],$ranking,$num,$add_time);

            self::commit();
        } catch (Exception $e) {
            self::rollback();
            Log::write("今日排名收入错误：".$e->getMessage());
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
