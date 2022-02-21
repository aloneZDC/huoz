<?php
//太空计划 - 动力源
namespace app\common\model;

use think\Exception;
use think\Model;

class SpacePlanPower extends Model
{
    static function award($member_id,$currency_id,$sum,$space_plan_config,$currency_ratio) {
        //奖励数量
        $award_num = $sum;

        //到可用数量
        $num_num = keepPoint($award_num * $space_plan_config['space_pid_power_num_percent']/100,6);
        if($num_num<0.000001) $num_num = 0;
        //到云攒金数量
        $air_num = keepPoint($award_num-$num_num,6);
        if($air_num<0.000001) $air_num = 0;

        $air_real_num = keepPoint($air_num * $currency_ratio,6);
        $air_real_num = $air_real_num<0.000001 ? 0 : $air_real_num;

        if($num_num<=0 && $air_real_num<=0) return;

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(!$currency_user) return;

        $air_currency_user = CurrencyUser::getCurrencyUser($member_id,Currency::XRP_PLUS_ID);
        if(!$air_currency_user) return;

        $award_id = 0;
        try {
            self::startTrans();

            //动力源总奖励数量
            $flag = SpacePlanSummary::where(['member_id'=>$currency_user['member_id']])->setInc('total_power',$award_num);
            if(!$flag) throw new Exception("添加释放总量失败");

            $award_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $award_num,
                'num_num' => $num_num,
                'air_num' => $air_num,
                'air_real_num' => $air_real_num,
                'add_time' => time(),
            ]);
            if(!$award_id) throw new Exception("插入奖励记录失败");

            if($num_num>0) {
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2507,'space_power','in',$num_num,$award_id,0);
                if(!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num_num);
                if(!$flag) throw new Exception("增加资产失败");
            }

            if($air_real_num>0) {
                $flag = HongbaoAirNumLog::add_log('space_power',$air_currency_user['member_id'],$air_currency_user['currency_id'],$air_real_num,$award_id,$award_num,keepPoint(100-$space_plan_config['space_release_num_percent'],2));
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

    //所有的仓位
    static function getList($member_id,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($member_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['a.member_id' => $member_id];
            $field = "a.id,a.num,a.num_num,a.air_num,a.add_time,b.currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['title'] = lang('space_power');
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

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
