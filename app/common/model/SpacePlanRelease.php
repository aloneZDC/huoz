<?php
//跳跃排名倒序加权算法 - 订单收益
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class SpacePlanRelease extends Model
{
    static function release($space_plan,$release_percent,$space_plan_config,$today_config,$currency_ratio) {
        //本人释放到账
        $award_self = self::release_add($space_plan,$release_percent,$space_plan_config,$today_config,$currency_ratio);
        //生成动力源详情
        self::release_award_detail($space_plan,$award_self,$space_plan_config,$today_config);
    }

    //本人释放到账
    static function release_add($space_plan,$release_percent,$space_plan_config,$today_config,$currency_ratio) {
        //释放数量
        $award_num = keepPoint($space_plan['num'] * $release_percent / 100,6);

        //到可用数量
        $num_num = keepPoint($award_num * $space_plan_config['space_release_num_percent']/100,6);
        if($num_num<0.000001) $num_num = 0;
        //到云攒金数量
        $air_num = keepPoint($award_num-$num_num,6);
        if($air_num<0.000001) $air_num = 0;

        $air_real_num = keepPoint($air_num * $currency_ratio,6);
        $air_real_num = $air_real_num<0.000001 ? 0 : $air_real_num;

        if($num_num<=0 && $air_real_num<=0) return;

        $currency_user = CurrencyUser::getCurrencyUser($space_plan['member_id'],$space_plan['currency_id']);
        if(!$currency_user) return;

        $air_currency_user = CurrencyUser::getCurrencyUser($space_plan['member_id'],Currency::XRP_PLUS_ID);
        if(!$air_currency_user) return;

        $award_id = 0;
        try {
            self::startTrans();

            //添加今日释放数量
            $flag = SpacePlan::where(['id'=>$space_plan['id']])->update([
                'total_release' => ['inc',$award_num],
                'today_release' => $award_num,
                'today' => $today_config['today']
            ]);
            if(!$flag) throw new Exception("添加今日释放数量失败");

            //拿到的释放数量奖励
            $flag = SpacePlanSummary::where(['member_id'=>$currency_user['member_id']])->setInc('total_release',$award_num);
            if(!$flag) throw new Exception("添加释放总量失败");

            $award_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $award_num,
                'num_num' => $num_num,
                'air_num' => $air_num,
                'air_real_num' => $air_real_num,
                'add_time' => time(),
                'third_id' => $space_plan['id'],
                'third_num' => $space_plan['num'],
                'percent' => $release_percent,
            ]);
            if(!$award_id) throw new Exception("插入奖励记录失败");

            if($num_num>0) {
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2503,'space_release','in',$num_num,$award_id,0);
                if(!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num_num);
                if(!$flag) throw new Exception("增加资产失败");
            }

            if($air_real_num>0) {
                $flag = HongbaoAirNumLog::add_log('space_release',$air_currency_user['member_id'],$air_currency_user['currency_id'],$air_real_num,$award_id,$award_num,keepPoint(100-$space_plan_config['space_release_num_percent'],2));
                if(!$flag) throw new Exception("添加云攒金记录失败");

                $flag = CurrencyUser::where(['cu_id' => $air_currency_user['cu_id'], 'air_num' => $air_currency_user['air_num']])->setInc('air_num', $air_real_num);
                if (!$flag) throw new Exception("增加资产失败");
            }

            self::commit();
        } catch (Exception $e) {
            $award_id = 0;
            self::rollback();
        }
        return [
            'award_id' => $award_id,
            'award_num' => $award_num,
        ];
    }

    //添加动力源详情
    static function release_award_detail($space_plan,$award_self,$space_plan_config,$today_config) {
        $first_member_id = 0;
        if($space_plan['type']==SpacePlan::SLAVE) {
            //如果是子仓 直推是自己
            $first_member_id = $space_plan['member_id'];
        } else {
            $member = Member::where(['member_id'=>$space_plan['member_id']])->field('pid')->find();
            if($member) $first_member_id = $member['pid'];
        }

        $time = time();
        $award_num = $award_self['award_num'];
//        $percent = $space_plan_config['space_pid_power_percent'];
        $count = 1;
        while (true) {
            if($first_member_id<=0) break;

            if($count==1) {
                $percent = $space_plan_config['space_pid_power_percent'];
            } else {
                $percent = $space_plan_config['space_pid_power_percent2'];
            }

            $award_base_num = $award_num;
            $award_num = keepPoint($award_num * $percent /100,6);
            $award_num = self::modidyAwardNum($award_num);
            //奖励小于1时停止
            if($award_num<$space_plan_config['space_power_stop_num']) break;

            //需要有动力源时间  且  主舱正常
            $space_plan_master = SpacePlan::where(['member_id'=>$first_member_id,'type'=>SpacePlan::MASTER,'status'=>SpacePlan::STATUS_OK])->find();
            $space_plan_summary = SpacePlanSummary::where(['member_id'=>$first_member_id])->find();
            $space_plan_total = SpacePlan::getAvailSum($first_member_id);
            if($space_plan_summary && $space_plan_summary['power_stop_time']>=$today_config['today_start'] && $space_plan_master && $space_plan_total>=$space_plan_config['space_pid_add_min_num']) {
                $count++;
                SpacePlanPowerDetail::addDetail($first_member_id,$space_plan['currency_id'],$award_num,$award_self['award_id'],$award_base_num,$percent,$today_config['today_start']);
            } else {
                $award_num = $award_base_num;
            }

            $member = Member::where(['member_id'=>$first_member_id])->field('pid')->find();
            if(!$member) break;
            $first_member_id = $member['pid'];
        }
    }

    //所有的仓位
    static function getList($member_id,$space_id=0,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($member_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['a.member_id' => $member_id];
            if($space_id) $where['a.third_id'] = $space_id;
            $field = "a.id,a.num,a.num_num,a.air_num,a.add_time,b.currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['title'] = lang('space_release');
                    $value['add_time'] = date('m-d H:i', $value['add_time']);
                }

                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }

    //四舍五不入
    static function modidyAwardNum($award_num) {
//        $base_award_num = keepPoint($award_num ,1);
//        $int_award_num = intval($award_num);
//        if($base_award_num-$int_award_num>=0.5) return $base_award_num;
//        return $int_award_num;

        $length = 2;
        $base_award_num = keepPoint($award_num ,$length-1);
        $base_award_num2 = keepPoint($award_num ,$length);
        $base = 1 / pow(10,$length) * 5;
        if($base_award_num2-$base_award_num>=$base) return $base_award_num2;
        return $base_award_num;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
