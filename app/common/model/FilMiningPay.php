<?php
//Fil项目 用户记录
namespace app\common\model;

use think\exception\PDOException;
use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class FilMiningPay extends Base
{
    static function addPay($member_id,$currency_id,$pay_num,$add_time,$third_id) {
        return self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'pay_num' => $pay_num,
            'add_time' => $add_time,
            'third_id' => $third_id,
            'is_team' => 0,
            'is_award' => 0,
        ]);
    }

    // 推荐奖励发放
    static function recommand_award($fil_mining_pay,$fil_mining_config,$today_start) {
        $base_member_id = $fil_mining_pay['member_id'];
        //直推 间推奖励
        for ($count=1;$count<=3;$count++) {
            $award_level = 10 + $count; //奖励类型

            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
            if(empty($member) || $member['pid']==0) {
                return;
            }

            $base_member_id = $member['pid'];

            //直推人数 推荐1人拿1代
            $recommand_count = FilMining::myOneTeamCount($base_member_id);

            //没有入金 没有奖励
            $pid_fil_mining = FilMining::where(['member_id' => $base_member_id, 'currency_id' => $fil_mining_pay['currency_id']])->find();
            //最大封顶9倍
            $max_award_num = FilMining::getNewHelpAwardLimit($pid_fil_mining,$fil_mining_config);
            if($pid_fil_mining && $max_award_num>0 && $recommand_count>=$count) {
                $award_all_percent = $fil_mining_config['recommand_'.$count];
                $award_lock_percent = $fil_mining_config['recommand_lock'.$count];
                $award_num_percent = $award_all_percent - $award_lock_percent;

                // 可用数量
                $award_num = keepPoint($fil_mining_pay['pay_num'] * $award_num_percent / 100,6);
                $award_num = min($award_num,$max_award_num);
                $award_num = $award_num>=0.000001 ? $award_num : 0;

                // 锁仓数量
                $award_lock_num = keepPoint($fil_mining_pay['pay_num'] * $award_lock_percent / 100,6);
                $award_lock_num = $award_lock_num>=0.000001 ? $award_lock_num : 0;
                if($award_num>0 || $award_lock_num>0) {
                    FilMiningIncome::award($pid_fil_mining, $base_member_id, $fil_mining_pay['currency_id'], $award_level, $award_num, $fil_mining_pay['id'], $fil_mining_pay['pay_num'], 0, $award_num_percent, $today_start, $fil_mining_pay['member_id'], $award_lock_num, $award_lock_percent);
                }
            }
        }
    }

    // 推荐奖励发放
    static function lock_release($currency_user,$fil_mining_config,$today_start) {
        $award_percent = $fil_mining_config['lock_release_percent'];
        $award_num = keepPoint($currency_user['lock_num'] * $award_percent / 100,6);
        if($award_num<0.000001) return false;

        $fil_mining = FilMining::where(['member_id'=>$currency_user['member_id'],'currency_id'=>$currency_user['currency_id']])->find();
        if(empty($fil_mining)) return false;

        $yu_award_num = FilMining::getNewHelpAwardLimit($fil_mining,$fil_mining_config);

        // 出局后清空锁仓资产
        $out_num = 0;
        if($yu_award_num<=0) {
            //直接出局 不释放
            $award_num = 0;
            $out_num = $currency_user['lock_num'];
        } else {
            // 释放后出局
            if($award_num>$yu_award_num) {
                $award_num = min($award_num,$yu_award_num);
                $out_num = keepPoint($currency_user['lock_num'] - $award_num,6);
            }
        }

        try {
            self::startTrans();

            if($award_num>0) {
//                $release_num_avail = min($award_num,$fil_mining['release_num_avail']);
                $flag = FilMining::where(['id'=>$fil_mining['id'],'release_num_avail'=>$fil_mining['release_num_avail']])->update([
//                    'release_num_avail' => ['dec',$release_num_avail],
                    'total_child16' => ['inc',$award_num],
                ]);
                if(!$flag) throw new Exception("更新奖励总量失败");

                // 添加奖励记录
                $item_id = FilMiningIncome::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $currency_user['currency_id'],
                    'type' => 16,
                    'num' => $award_num,
                    'lock_num' => 0,
                    'add_time' => time(),
                    'award_time' => $today_start,
                    'third_percent' => $award_percent,
                    'third_lock_percent' => 0,
                    'third_num' => $currency_user['lock_num'],
                    'third_id' => $fil_mining['id'],
                    'third_member_id' => $fil_mining['member_id'],
                    'release_id' => 0,
                ]);
                if(!$item_id) throw new Exception("添加奖励记录");

                // 增加锁仓减少记录
                $flag = CurrencyLockBook::add_log('lock_num','release',$currency_user['member_id'],$currency_user['currency_id'],$award_num,$currency_user['cu_id'],$currency_user['lock_num'],$award_percent,$currency_user['member_id']);
                if(!$flag) throw new Exception("减少锁仓账本失败");


                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6332,'fil_mining_release_award16','in',$award_num,$item_id,0);
                if(!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->update([
                    'num' => ['inc',$award_num],
                    'lock_num' => ['dec',$award_num],
                ]);
                if(!$flag) throw new Exception("添加资产失败");
            }

            if($out_num>=0.000001) {
                $flag = CurrencyLockBook::add_log('lock_num','release_out',$currency_user['member_id'],$currency_user['currency_id'],$out_num,0,0,0,0);
                if(!$flag) throw new Exception("减少锁仓账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id']])->setDec('lock_num',$out_num);
                if(!$flag) throw new Exception("减少锁仓资产失败");
            }

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败".$e->getMessage());
        }
        return false;
    }

    static function getList($member_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id'=> $member_id,
        ];

        $list = self::alias('a')->field('a.id,a.currency_id,a.pay_num,a.add_time,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('m-d H:i',$item['add_time']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
