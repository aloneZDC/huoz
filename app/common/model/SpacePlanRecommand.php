<?php
//跳跃排名倒序加权算法配置
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class SpacePlanRecommand extends Model
{
    static function recommand_award($space_plan,$space_plan_config,$currency_ratio){
        if($space_plan['is_award']==1) return;

        $first_member_id =0;
        if($space_plan['type']==SpacePlan::SLAVE) {
            //如果是子仓 直推是自己
            $first_member_id = $space_plan['member_id'];
        } else {
            $member = Member::where(['member_id'=>$space_plan['member_id']])->field('pid')->find();
            if($member) $first_member_id = $member['pid'];
        }

        $flag = SpacePlanPay::where(['id'=>$space_plan['id'],'is_award'=>0])->setField('is_award',1);
        if(!$flag) return;

        for ($count=1;$count<=2;$count++) {
            if($first_member_id<=0) break;

            $percent = $space_plan_config['space_recommand_percent'.$count];
            self::award($first_member_id,$percent,$space_plan,$space_plan_config,$currency_ratio);

            $member = Member::where(['member_id'=>$first_member_id])->field('pid')->find();
            if(!$member) break;

            $first_member_id = $member['pid'];
        }
    }

    static function award($first_member_id,$percent,$space_plan,$space_plan_config,$currency_ratio) {
        if($first_member_id<=0) return;

        $space_plan_total = SpacePlan::getAvailSum($first_member_id);
        $award_base_num = min($space_plan['num'],$space_plan_total);
        $award_num = keepPoint($award_base_num * $percent / 100,6);

        //到可用数量
        $num_num = keepPoint($award_num * $space_plan_config['space_recommand_num_percent']/100,6);
        $num_num = $num_num<0.000001 ? 0 : $num_num;
        //到云攒金数量
        $air_num = keepPoint($award_num-$num_num,6);
        $air_num = $air_num<0.000001 ? 0 : $air_num;

        $air_real_num = keepPoint($air_num * $currency_ratio,6);
        $air_real_num = $air_real_num<0.000001 ? 0 : $air_real_num;

        $currency_user = CurrencyUser::getCurrencyUser($first_member_id,$space_plan['currency_id']);
        if(!$currency_user) return;

        $air_currency_user = CurrencyUser::getCurrencyUser($first_member_id,Currency::XRP_PLUS_ID);
        if(!$air_currency_user) return;

        try{
            self::startTrans();

            $flag = SpacePlanSummary::where(['member_id'=>$first_member_id])->setInc('total_recommand',$award_num);
            if(!$flag) throw new Exception("添加推荐奖励总量失败");

            if($award_num>0) {
                $insert_id = self::insertGetId([
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $currency_user['currency_id'],
                    'num' => $award_num,
                    'num_num' => $num_num,
                    'air_num' => $air_num,
                    'air_real_num' => $air_real_num,
                    'add_time' => time(),
                    'third_id' => $space_plan['id'],
                    'third_num' => $space_plan['num'],
                    'percent' => $percent,
                ]);
                if(!$insert_id) throw new Exception("添加释放记录失败");

                if($num_num>0) {
                    $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2506,'space_recommand','in',$num_num,$insert_id,0);
                    if(!$flag) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$num_num);
                    if(!$flag) throw new Exception("增加资产失败");
                }

                if($air_real_num>0) {
                    $flag = HongbaoAirNumLog::add_log('space_recommand', $air_currency_user['member_id'], $air_currency_user['currency_id'], $air_real_num, $insert_id, $award_num, keepPoint(100 - $space_plan_config['space_recommand_num_percent'], 2));
                    if (!$flag) throw new Exception("添加云攒金记录失败");

                    $flag = CurrencyUser::where(['cu_id' => $air_currency_user['cu_id'], 'air_num' => $air_currency_user['air_num']])->setInc('air_num', $air_real_num);
                    if (!$flag) throw new Exception("增加资产失败");
                }
            }

            self::commit();
        }catch (Exception $e) {
            self::rollback();
            Log::write($space_plan['id']." 推荐奖励失败:".$e->getMessage());
            return false;
        }
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
                    $value['title'] = lang('space_recommand');
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
