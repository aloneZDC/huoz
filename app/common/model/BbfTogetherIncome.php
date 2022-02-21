<?php
//四币连发配置表
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class BbfTogetherIncome extends Base
{
    static function award($member_id,$currency_id,$award_level,$award_num,$base_id,$base_num,$release_id,$base_percent,$today_start) {
        $account_book_content = 'bbf_together_release_award'.$award_level;
        if($award_level==1) {
            $account_book_id = 6303;
        } elseif ($award_level==2) {
            $account_book_id = 6304;
        } else {
            $account_book_id = 6305;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(empty($currency_user)) return false;

        try {
            self::startTrans();

            $flag = BbfTogetherMember::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->setInc('total_child'.$award_level,$award_num);
            if(!$flag) throw new Exception("更新奖励总量失败");

            //添加奖励记录
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'type' => $award_level,
                'num' => $award_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $base_percent,
                'third_num' => $base_num,
                'third_id' => $base_id,
                'release_id' => $release_id,
            ]);
            if(!$item_id) throw new Exception("添加释放记录失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],$account_book_id,$account_book_content,'in',$award_num,$item_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$award_num);
            if(!$flag) throw new Exception("添加资产失败");

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("四币连发释放任务:失败".$e->getMessage());
        }
        return false;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
