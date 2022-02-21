<?php
//四币连发配置表
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class BbfTogetherRelease extends Base
{
    static function release($bbf_together,$bbf_currency_config,$lock_currency_price,$release_currency_price,$today_start) {
        $res = [
            'release_id' => 0,
            'release_num' => 0,
        ];

        //锁仓释放比例
        $release_percent = $bbf_together['pledge_status'] ==0 ? $bbf_currency_config['release_percent'] : $bbf_currency_config['no_pledge_release_percent'];
        //锁仓释放数量
        $lock_release_num = keepPoint($bbf_together['lock_currency_num'] * $release_percent / 100,6);
        $is_release_all = false;
        if($lock_release_num>$bbf_together['lock_currency_avail']) {
            $is_release_all = true;
            $lock_release_num = $bbf_together['lock_currency_avail'];
        }

        //实际释放数量
        $release_currency_num = keepPoint($lock_release_num * $lock_currency_price/$release_currency_price,6);
        if($release_currency_num<0.000001) {
            return $res;
        }

        $release_currency_user = CurrencyUser::getCurrencyUser($bbf_together['member_id'],$bbf_together['release_currency_id']);
        if(empty($release_currency_user)) {
            Log::write("四币连发释放任务:获取用户资产失败".$bbf_together['id']);
            return $res;
        }

        try {
            self::startTrans();

            $update_data = [
                'lock_currency_avail' => ['dec',$lock_release_num],
            ];
            if($is_release_all) $update_data['status'] = 1;

            $flag = BbfTogether::where(['id'=>$bbf_together['id'],'lock_currency_avail'=>$bbf_together['lock_currency_avail']])->update($update_data);
            if(!$flag) throw new Exception("更新订单失败");

            $flag = BbfTogetherMember::where(['member_id'=>$release_currency_user['member_id'],'currency_id'=>$release_currency_user['currency_id']])->setInc('total_release',$release_currency_num);
            if(!$flag) throw new Exception("更新释放总量失败");

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $release_currency_user['member_id'],
                'release_currency_id' => $release_currency_user['currency_id'],
                'release_currency_num' => $release_currency_num,
                'release_time' => $today_start,
                'third_id' => $bbf_together['id'],
                'third_percent' => $release_percent,
                'third_num' => $lock_release_num,
                'add_time' => time(),
            ]);
            if(!$item_id) throw new Exception("添加释放记录失败");

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($release_currency_user['member_id'],$release_currency_user['currency_id'],6302,'bbf_together_release','in',$release_currency_num,$item_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$release_currency_user['cu_id'],'num'=>$release_currency_user['num']])->setInc('num',$release_currency_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();

            $res['release_id'] = $item_id;
            $res['release_num'] = $release_currency_num;
        } catch (Exception $e) {
            self::rollback();
            Log::write("四币连发释放任务:失败".$e->getMessage());
        }
        return $res;
    }

    static function release_award($bbf_together,$bbf_currency_config,$release_res,$today_start) {
        $base_member_id = $bbf_together['member_id'];
        //直推 间推奖励
        for ($count=1;$count<=2;$count++) {
            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
            if(empty($member) || $member['pid']==0) {
                return;
            }

            $base_member_id = $member['pid'];

            //没有入金 没有奖励
            $pid_bbf_together = BbfTogetherMember::where(['member_id'=>$base_member_id,'currency_id'=>$bbf_together['release_currency_id']])->find();
            if(empty($pid_bbf_together)) {
                continue;
            }

            $award_percent = $bbf_currency_config['team_percent'.$count];
            $award_num = keepPoint($release_res['release_num'] * $award_percent / 100,6);
            if($award_num>0.000001) {
                BbfTogetherIncome::award($base_member_id,$bbf_together['release_currency_id'],$count,$award_num,$bbf_together['id'],$release_res['release_num'],$release_res['release_id'],$award_percent,$today_start);
            }
        }

        $award_count = 0;
        while ($award_count<10) {
            $award_count++;

            $member = Member::where(['member_id'=>$base_member_id])->field('pid')->find();
            if(empty($member) || $member['pid']==0) {
                return;
            }

            $base_member_id = $member['pid'];

            //没有入金 没有奖励
            $pid_bbf_together = BbfTogetherMember::where(['member_id'=>$base_member_id,'currency_id'=>$bbf_together['release_currency_id']])->find();
            if(empty($pid_bbf_together)) {
                continue;
            }

            //去掉大区 如果是大区的 则拿不到
            $isThirdAward = BbfTogetherMember::isThirdAward($base_member_id,$bbf_together['member_id'],$bbf_together['release_currency_id']);
            if($isThirdAward) {
                $award_percent = $bbf_currency_config['team_percent3'];
                $award_num = keepPoint($release_res['release_num'] * $award_percent / 100,6);
                if($award_num>0.000001) {
                    //添加奖励详情
                    BbfTogetherIncomeDetail::award($base_member_id,$bbf_together['release_currency_id'],$award_num,$bbf_together['id'],$release_res['release_num'],$release_res['release_id'],$award_percent,$today_start);
                }
            }
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function releasecurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'release_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
