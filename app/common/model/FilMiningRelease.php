<?php
//涡轮增压释放
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class FilMiningRelease extends Base
{
    /**
     * @param $fil_mining
     * @param $release_percent 月释放比例
     * @param $real_currency_prices
     * @param $today_start
     * @return array
     */
    static function release($fil_mining,$release_percent,$real_currency_prices,$fil_mining_config,$today_start) {
        $res = [
            'release_id' => 0,
            'release_num' => 0,
        ];


        //实际释放数量 入金量 * 当前释放比例 //FilMiningLevel::MONTH_DAY
        $release_currency_num = keepPoint($fil_mining['pay_num'] * $release_percent / 100,6);

        // 手续费
        $fixed_release_percent_fee = keepPoint($release_currency_num * $fil_mining_config['new_fixed_release_percent_fee'] / 100,6);
        $release_currency_num = bcsub($release_currency_num,$fixed_release_percent_fee,6);

        $fil_mining_release_limit = FilMining::getNewStaticLimit($fil_mining,$fil_mining_config);
        $release_currency_num = min($release_currency_num,$fil_mining_release_limit);
        if($release_currency_num<0.000001) {
            return $res;
        }


        if($release_currency_num>=$fil_mining['release_num_avail']) {
            $release_currency_num = $fil_mining['release_num_avail'];
        }

        if(!isset($real_currency_prices[$fil_mining['real_currency_id']]) || !isset($real_currency_prices[$fil_mining['currency_id']])) {
            Log::write("涡轮增压释放任务:币种价格不存在".$fil_mining['id']);
            return $res;
        }

        if($fil_mining['real_currency_id'] == $fil_mining['currency_id']) {
            $real_currency_num = $release_currency_num;
        } else {
            $real_currency_num = keepPoint($release_currency_num * $real_currency_prices[$fil_mining['currency_id']] / $real_currency_prices[$fil_mining['real_currency_id']],6);
        }

        // 默认全部到账
        $award_lock_num = 0; // 75%
        if($fil_mining_config['release_lock_percent']>0) {
            // 产出数量要75%部分锁仓
            $award_lock_num = keepPoint($real_currency_num * $fil_mining_config['release_lock_percent'] / 100,6);
            $real_currency_num = keepPoint($real_currency_num - $award_lock_num,6);
        }
        if($real_currency_num<0.000001 && $award_lock_num<0.000001) {
            return $res;
        }

        $real_currency_user = CurrencyUser::getCurrencyUser($fil_mining['member_id'],$fil_mining['real_currency_id']);
        if(empty($real_currency_user)) {
            Log::write("涡轮增压释放任务:获取用户资产失败".$fil_mining['id']);
            return $res;
        }

        try {
            self::startTrans();

            $update_data = [
                'total_release' => ['inc',$release_currency_num],
                'release_num' => ['inc',$real_currency_num], // 25%
                'lock_num' => ['inc',$award_lock_num], // 75%
//                'release_num_avail' => ['dec',$release_currency_num],
            ];
            $flag = FilMining::where(['id'=>$fil_mining['id'],'release_num_avail'=>$fil_mining['release_num_avail']])->update($update_data);
            if(!$flag) throw new Exception("更新订单失败");

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $fil_mining['member_id'],
                'currency_id' => $fil_mining['currency_id'],
                'num' => $release_currency_num,
                'lock_num' => $award_lock_num, // 75%
                'fee_num' => $fixed_release_percent_fee,
                'release_time' => $today_start,
                'third_id' => $fil_mining['id'],
                'third_percent' => $release_percent,
                'third_num' => $fil_mining['pay_num'],
                'real_currency_id' => $fil_mining['real_currency_id'],
                'real_currency_num' => $real_currency_num, // 25%
                'add_time' => time(),
            ]);
            if(!$item_id) throw new Exception("添加释放记录失败");

            // 25% 可用
            if($real_currency_num>0.000001) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($real_currency_user['member_id'],$real_currency_user['currency_id'],6322,'fil_mining_release','in',$real_currency_num,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$real_currency_user['cu_id'],'num'=>$real_currency_user['num']])->setInc('num',$real_currency_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            // 75% 锁仓
            if($award_lock_num>0.000001) {
                // 增加锁仓记录 增加锁仓资产
                $flag = CurrencyLockBook::add_log('fil_lock_num','fil_mining_award_lock',$real_currency_user['member_id'],$real_currency_user['currency_id'], $award_lock_num,$item_id, $release_currency_num,$fil_mining_config['release_lock_percent'],0);
                if(!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id'=>$real_currency_user['cu_id'],'fil_lock_num'=>$real_currency_user['fil_lock_num']])->setInc('fil_lock_num',$award_lock_num);
                if(!$flag) throw new Exception("添加锁仓资产失败");
            }

            self::commit();

            $res['release_id'] = $item_id;
            $res['release_num'] = $release_currency_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压释放任务:失败".$e->getMessage());
        }
        return $res;
    }

    // 小区算力的15%
    static function release_award($fil_mining,$fil_mining_config,$release_res,$today_start) {
        $base_member_id = $fil_mining['member_id'];
        $award_count = 0;
        while (true) {
            $award_count++;

            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
            if(empty($member) || $member['pid']==0) {
                return;
            }

            $base_member_id = $member['pid'];

//            //直推人数 推荐1人拿1代
//            $recommand_count = FilMining::myOneTeamCount($base_member_id);
//            if($recommand_count < ($award_count+2)) {
//                continue;
//            }

            //没有入金 没有奖励
            $pid_fil_mining = FilMining::where(['member_id' => $base_member_id, 'currency_id' => $fil_mining['currency_id']])->find();
            if(!$pid_fil_mining || $pid_fil_mining['release_num_avail']<=0) {
                continue;
            }

            //去掉大区 如果是大区的 则拿不到
            $isThirdAward = FilMining::isThirdAward($base_member_id,$fil_mining['member_id'],$fil_mining['currency_id']);
            if($isThirdAward) {
                $award_percent = $fil_mining_config['team_percent15'];
                $award_num = keepPoint($release_res['release_num'] * $award_percent / 100,6);


                $award_num_limit = FilMining::getNewStaticLimit($pid_fil_mining,$fil_mining_config);
                $award_num = min($award_num,$award_num_limit);
                if($award_num>0.000001) {
                    //添加奖励详情
                    FilMiningIncomeDetail::award($base_member_id,$fil_mining['currency_id'],$award_num,$fil_mining['id'],$release_res['release_num'],$release_res['release_id'],$award_percent,$today_start,$fil_mining['member_id']);
                }
            }
        }
    }

//    static function release_award($fil_mining,$fil_mining_config,$release_res,$today_start) {
//        $base_member_id = $fil_mining['member_id'];
//        //直推 间推奖励
//        for ($count=1;$count<=2;$count++) {
//            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
//            if(empty($member) || $member['pid']==0) {
//                return;
//            }
//
//            $base_member_id = $member['pid'];
//
//            //直推人数 推荐1人拿1代
//            $recommand_count = FilMining::myOneTeamCount($base_member_id);
//
//            //没有入金 没有奖励
//            $pid_fil_mining = FilMining::where(['member_id' => $base_member_id, 'currency_id' => $fil_mining['currency_id']])->find();
//            if($pid_fil_mining && $pid_fil_mining['release_num_avail']>0 && $recommand_count>=$count) {
//                $award_percent = $fil_mining_config['team_percent'.$count];
//                $award_num = keepPoint($release_res['release_num'] * $award_percent / 100,6);
//                $award_num = min($award_num,$pid_fil_mining['release_num_avail']);
//                if($award_num>0.000001) {
//                    FilMiningIncome::award($pid_fil_mining,$base_member_id,$fil_mining['currency_id'],$count,$award_num,$fil_mining['id'],$release_res['release_num'],$release_res['release_id'],$award_percent,$today_start,$fil_mining['member_id']);
//                }
//            }
//        }
//
//        $award_count = 0;
//        while ($award_count<10) {
//            $award_count++;
//
//            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
//            if(empty($member) || $member['pid']==0) {
//                return;
//            }
//
//            $base_member_id = $member['pid'];
//
//            //直推人数 推荐1人拿1代
//            $recommand_count = FilMining::myOneTeamCount($base_member_id);
//            if($recommand_count < ($award_count+2)) {
//                continue;
//            }
//
//            //没有入金 没有奖励
//            $pid_fil_mining = FilMining::where(['member_id' => $base_member_id, 'currency_id' => $fil_mining['currency_id']])->find();
//            if(!$pid_fil_mining || $pid_fil_mining['release_num_avail']<=0) {
//                continue;
//            }
//
//            //去掉大区 如果是大区的 则拿不到
//            $isThirdAward = FilMining::isThirdAward($base_member_id,$fil_mining['member_id'],$fil_mining['currency_id']);
//            if($isThirdAward) {
//                $award_percent = $fil_mining_config['team_percent3'];
//                $award_num = keepPoint($release_res['release_num'] * $award_percent / 100,6);
//                $award_num = min($award_num,$pid_fil_mining['release_num_avail']);
//                if($award_num>0.000001) {
//                    //添加奖励详情
//                    FilMiningIncomeDetail::award($base_member_id,$fil_mining['currency_id'],$award_num,$fil_mining['id'],$release_res['release_num'],$release_res['release_id'],$award_percent,$today_start,$fil_mining['member_id']);
//                }
//            }
//        }
//    }

    static function getList($member_id,$fil_mining_id=0,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id'=> $member_id,
        ];
        if($fil_mining_id) {
            $where['a.third_id'] = $fil_mining_id;
        }

        $list = self::alias('a')->field('a.id,a.real_currency_id,a.real_currency_num,a.add_time,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.real_currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['title'] = lang('fil_mining_release_yestoday');
            $item['add_time'] = date('m-d H:i',$item['add_time']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    // 产币线性释放 75%
    static function release_lock_release($fil_mining,$fil_mining_config,$today_start) {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($fil_mining['lock_num'] / $fil_mining_config['release_lock_days'],6);
        $fee_num = 0;

        $currency_user = CurrencyUser::getCurrencyUser($fil_mining['member_id'],$fil_mining['real_currency_id']);
        if(empty($currency_user)) return false;

        $award_num = min($award_num,$currency_user['fil_lock_num']);
        if($award_num<0.000001) return false;

        try {
            self::startTrans();

            $flag = FilMining::where(['member_id'=>$currency_user['member_id'],'currency_id'=>$fil_mining['currency_id']])->update([
                'lock_num' => ['dec',$award_num],
                'total_lock_num' => ['inc',$award_num],
            ]);
            if(!$flag) throw new Exception("减少线性释放失败");

            // 添加奖励记录
            $item_id = FilMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 21,
                'num' => $award_num,
                'lock_num' => 0,
//                'fee_num' => $fee_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $fil_mining_config['release_lock_days'],
                'third_lock_percent' => 0,
                'third_num' => $fil_mining['lock_num'],
                'third_id' => $fil_mining['id'],
                'third_member_id' => $currency_user['member_id'],
                'release_id' => 0,
            ]);
            if(!$item_id) throw new Exception("添加奖励记录");

            // 增加锁仓减少记录
            $flag = CurrencyLockBook::add_log('fil_lock_num','release',$currency_user['member_id'],$currency_user['currency_id'],$award_num,$fil_mining['id'],$fil_mining['lock_num'],$fil_mining_config['release_lock_days'],$currency_user['member_id']);
            if(!$flag) throw new Exception("减少锁仓账本失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6511,'fil_mining_award_lock','in',$award_num,$item_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->update([
                'num' => ['inc',$award_num],
                'fil_lock_num' => ['dec',$award_num],
            ]);
            if(!$flag) throw new Exception("添加资产失败");

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败".$e->getMessage());
        }
        return false;
    }

    // 小区线性释放 75%
    static function release_third_release_num($fil_mining,$fil_mining_config,$today_start) {
        // 锁仓释放的余额 / 180天
        $award_num = keepPoint($fil_mining['total_release15'] / $fil_mining_config['release_lock_days'],6);

        $currency_user = CurrencyUser::getCurrencyUser($fil_mining['member_id'],$fil_mining['currency_id']);
        if(empty($currency_user)) return false;

        $award_num = min($award_num,$currency_user['fil_area_lock_num']);
        if($award_num<0.000001) return false;

        try {
            self::startTrans();

            $flag = FilMining::where(['member_id'=>$currency_user['member_id'],'currency_id'=>$currency_user['currency_id']])->update([
                'total_release15' => ['dec',$award_num],
                'total_thaw15' => ['inc',$award_num],
            ]);
            if(!$flag) throw new Exception("减少线性释放失败");

            // 添加奖励记录
            $item_id = FilMiningIncome::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => 22,
                'num' => $award_num,
                'lock_num' => 0,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $fil_mining_config['release_lock_days'],
                'third_lock_percent' => 0,
                'third_num' => $fil_mining['total_release15'],
                'third_id' => $fil_mining['id'],
                'third_member_id' => $currency_user['member_id'],
                'release_id' => 0,
            ]);
            if(!$item_id) throw new Exception("添加奖励记录");

            // 增加锁仓减少记录
            $flag = CurrencyLockBook::add_log('fil_area_lock_num','release',$currency_user['member_id'],$currency_user['currency_id'],$award_num,$fil_mining['id'],$fil_mining['lock_num'],$fil_mining_config['release_lock_days'],$currency_user['member_id']);
            if(!$flag) throw new Exception("减少锁仓账本失败");

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],6512,'fil_mining_area_award_lock','in',$award_num,$item_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->update([
                'num' => ['inc',$award_num],
                'fil_area_lock_num' => ['dec',$award_num],
            ]);
            if(!$flag) throw new Exception("添加资产失败");

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:锁仓释放失败".$e->getMessage());
        }
        return false;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function realcurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
