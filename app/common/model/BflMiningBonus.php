<?php
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;


class BflMiningBonus extends Model
{
    static function award($member_id,$currency_id,$award_num,$is_out,$mining,$today_config) {
        //到可用数量
        $num_num = $award_num;

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(empty($currency_user)) return 0;

        //释放本人
        try{
            self::startTrans();
            if($is_out) {
                $flag = BflMining::where(['id'=>$mining['id']])->setField('status',BflMining::STATUS_OUT);
                if($flag===false) throw new Exception("更新出局状态失败");
            }

            $flag = BflMining::where(['id'=>$mining['id']])->setInc('total_award',$num_num);
            if($flag===false) throw new Exception("增加总动态奖励失败");

            $insert_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $num_num,
                'add_time' => time(),
                'add_day' => $today_config['today_start'],
            ]);
            if(!$insert_id) throw new Exception("添加动态奖励记录失败");

            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],5104,'bfl_mining_bonus','in',$num_num,$insert_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num_num);
            if(!$flag) throw new Exception("增加资产失败");

            self::commit();
            return $num_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write($member_id."动态奖励错误:".$e->getMessage());
            return 0;
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
