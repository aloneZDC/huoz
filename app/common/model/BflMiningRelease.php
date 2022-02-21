<?php
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;


class BflMiningRelease extends Model
{
    static function release($mining,$currency_config,$level_config,$today_config) {
        $release_num = keepPoint($mining['num'] * $level_config['percent']/100,6); //本人可释放量

        $is_out = false;
        $max_num = keepPoint($mining['out_num'] - $mining['total_release'] - $mining['total_award'],6);
        if($max_num<=0 || $release_num>=$max_num) $is_out = true;

        $release_num = min($release_num,$max_num);
        if($release_num<0.000001) $release_num = 0;

        //到可用数量
        $num_num = $release_num;

        $currency_user = CurrencyUser::getCurrencyUser($mining['member_id'],$mining['currency_id']);
        if(empty($currency_user)) return 0;

        //释放本人
        try{
            self::startTrans();

            if($is_out) {
                echo $mining['id']." 释放OUT".PHP_EOL;
                $flag = BflMining::where(['id'=>$mining['id']])->setField('status',BflMining::STATUS_OUT);
                if($flag===false) throw new Exception("更新出局状态失败");
            }

            if($release_num>0) {
                $flag = BflMining::where(['id'=>$mining['id']])->setInc('total_release',$release_num);
                if($flag===false) throw new Exception("增加总挖矿量失败");

                $insert_id = self::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $currency_user['currency_id'],
                    'num' => $release_num,
                    'add_time' => time(),
                    'third_id' => $mining['id'],
                    'percent' => $level_config['percent'],
                    'third_num' => $mining['num'],
                    'add_day' => $today_config['today_start'],
                ]);
                if(!$insert_id) throw new Exception("添加释放记录失败");

                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],5103,'bfl_mining_release','in',$num_num,$insert_id,0);
                if(!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num_num);
                if(!$flag) throw new Exception("增加资产失败");
            }
            self::commit();
            return $release_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write($mining['id']."释放错误:".$e->getMessage());
            return 0;
        }
    }

    //释放上级奖励
    static function release_award($release_num,$mining,$currency_config,$level_config,$today_config) {
        $member_id = $mining['member_id'];
        for ($count=1;$count<=2;$count++) {
            $pid_member = Member::where(['member_id'=>$member_id])->field('member_id,pid')->find();
            if(empty($pid_member) || $pid_member['pid']<=0) break;

            $member_id = $pid_member['pid'];

            if(!isset($level_config['pid_percent'.$count])) continue;
            if($level_config['pid_percent'.$count]<=0) continue;

            $bonus_num = keepPoint($release_num * $level_config['pid_percent'.$count] / 100,6);

            //3倍出局
            $pid_mining = BflMining::getOKMining($member_id,$mining['currency_id']);
            if(empty($pid_member)) continue;
            $max_num = keepPoint($pid_mining['num'] * $currency_config['out_mul'] - $pid_mining['total_release'] - $pid_mining['total_award'],6);
            $bonus_num = min($bonus_num,$max_num);

            if($bonus_num>=0.000001) {
                BflMiningBonusDetail::insertGetId([
                    'member_id' => $member_id,
                    'currency_id' => $mining['currency_id'],
                    'num' => $bonus_num,
                    'add_time' => time(),
                    'third_id' => $mining['id'],
                    'percent' => $level_config['pid_percent'.$count],
                    'third_num' => $release_num,
                    'add_day' => $today_config['today_start'],
                ]);
            }
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
