<?php
//翻牌币种
namespace app\common\model;
use think\Model;

class FlopCurrency extends Model
{
    //翻牌币种
    static function getList($member_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $list = self::alias('a')->field('a.*,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->order('sort asc')->select();
        if(!$list) return $r;

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config) || !isset($hongbao_config['hongbao_currency_mark'])) return $r;

        $time = time();
        $yestoday = date('Y-m-d',$time-86400);
        $child_num = FlopChildNum::getTodayTotalNum('flop',$member_id,$yestoday);
        $real_day_total = $day_total = FlopOrders::where(['member_id'=>$member_id,'add_time'=>['gt',todayBeginTimestamp()],'super_num'=>0])->count();
        $day_sum = FlopOrders::where(['member_id'=>$member_id,'add_time'=>['gt',todayBeginTimestamp()]])->sum('super_num');
        if($day_sum) $day_total += $day_sum;

        $flop_total = FlopOrders::where(['member_id'=>$member_id,'status'=>0])->count();


        $pay_currency_name = '';
        $pay_currency_user_num = 0;
        $buy_currency = Currency::where(['currency_mark'=>FlopOrders::BUY_CURRENCY_MARK])->field('currency_id,currency_name,currency_mark')->find();
        if($buy_currency) {
            $pay_currency_name = $buy_currency['currency_name'];
            if($member_id) {
                $buy_currency_user = CurrencyUser::getCurrencyUser($member_id,$buy_currency['currency_id']);
                if($buy_currency_user) $pay_currency_user_num = $buy_currency_user['num'];
            }
        }

        foreach ($list as &$item) {
            $item['pay_currency_name'] = $pay_currency_name;
            $item['pay_currency_user_num'] = $pay_currency_user_num;

            $item['common_check'] = 1;
            if($real_day_total>=$item['day_total'] || $flop_total>=$item['orders_total']){
                $item['common_check'] = 2;
            }

            $item['day_total'] += $child_num;
            $item['orders_total'] += $child_num;
            $item['all_orders_limit'] = min($item['day_total'],$item['orders_total']); //总可发广告量
            $item['has_orders'] = $day_total; //今日已发量
            $item['avail_orders'] = $flop_total; //有效订单量
            $item['yu_orders'] = min($item['day_total']-$day_total,$item['orders_total']-$flop_total); //剩余可发量
            $item['yu_orders'] = $item['yu_orders']>=0 ? $item['yu_orders'] : 0;

            $item['super_check'] = 2;
            $item['super_desc'] = lang('flop_super_desc');

            if($item['yu_orders']>0) {
                $super_check = FlopOrders::super_check($member_id,$item['currency_id'],$hongbao_config);
                if($super_check) $item['super_check'] = 1;
            }

            //20200628增加 买50单以后 每次要扣除1DNC
            $item['new_cost'] = FlopDncNum::checkFlopNotice($member_id,$hongbao_config);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getCurrencyOne($currency_id) {
        return self::where(['currency_id'=>$currency_id])->find();
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
