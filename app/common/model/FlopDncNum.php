<?php
//
namespace app\common\model;
use think\Exception;
use think\Model;

class FlopDncNum extends Model
{
    const START_TIME = 1593705600; //20200628增加 买50单以后 每次要扣除1DNC

    //方舟扣除币种
    const FLOP_COST_CURRENCY_ID = 38;
    const FLOP_COST_CURRENCY_NAME = 'DNC';

    //红包扣除币种
    const HB_COST_CURRENCY_ID = 38;
    const HB_COST_CURRENCY_NAME = 'DNC';

    static function addItem($user_id,$type,$today) {
        try{
            return self::insertGetId([
                'type' => $type,
                'user_id' => $user_id,
                'today' => $today,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    //今日是否扣除
    static function isTodayCost($user_id,$type,$today) {
        $info =self::where([
            'type' => $type,
            'user_id' => $user_id,
            'today' => $today,
        ])->find();
        if($info) return true;

        return false;
    }


    static function checkFlopNotice($member_id,$hongbao_config) {
        $data = self::checkFlopPay($member_id,$hongbao_config);
        unset($data['new_cost_currency_user']);
        return $data;
    }

    //检测是否需要付款
    static function checkFlopPay($member_id,$hongbao_config) {
        $new_cost_currency_user = CurrencyUser::getCurrencyUser($member_id,self::FLOP_COST_CURRENCY_ID);
        $new_cost_currency_total = $new_cost_currency_user ? $new_cost_currency_user['num'] : 0;

        $today = date('Y-m-d');
        $new_start_total =  FlopOrders::where(['member_id'=>$member_id,'add_time'=>['gt',FlopDncNum::START_TIME] ])->count();

        $new_start_notice = 0;
        $new_start_pay = 0;
        if($new_start_total>=$hongbao_config['flop_orders_limit'] && !FlopDncNum::isTodayCost($member_id,'flop',$today)) {
            //如果今日没有支付 则需要支付
            $new_start_pay = 1;

            //余额不足时需要提醒
            if($new_cost_currency_total<$hongbao_config['flop_orders_dnc_num']) {
                $new_start_notice = 1;
            }
        }

        //第51次 必提醒
//        if($new_start_total==$hongbao_config['flop_orders_limit']) {
//            $new_start_notice = 1; //第51次时要提醒
//        }

        return [
            'new_start_total' => $new_start_total, //总订单数
            'new_start_notice' => $new_start_notice, //是否需要提醒
            'new_start_pay' => $new_start_pay, //是否需要
            'new_cost_currency_name' => self::FLOP_COST_CURRENCY_NAME, //需支付币种
            'new_cost_currency_num' => $hongbao_config['flop_orders_dnc_num'], //需支付数量
            'new_cost_currency_total' => $new_cost_currency_total, //需支付币种余额
            'new_cost_currency_user' => $new_cost_currency_user,
        ];
    }

    static function checkHongbaoNotice($member_id,$hongbao_config) {
        $data = self::checkHongbaoPay($member_id,$hongbao_config);
        unset($data['new_cost_currency_user']);
        return $data;
    }

    //检测是否需要付款
    static function checkHongbaoPay($member_id,$hongbao_config) {
        $new_cost_currency_user = CurrencyUser::getCurrencyUser($member_id,self::HB_COST_CURRENCY_ID);
        $new_cost_currency_total = $new_cost_currency_user ? $new_cost_currency_user['num'] : 0;

        $today = date('Y-m-d');
        $new_start_total =  HongbaoLog::where(['user_id'=>$member_id,'create_time'=>['gt',FlopDncNum::START_TIME],'super_num'=>0])->count();

        $new_start_notice = 0;
        $new_start_pay = 0;
        if($new_start_total>=$hongbao_config['hongbao_orders_limit'] && !FlopDncNum::isTodayCost($member_id,'hongbao',$today)) {
            //如果今日没有支付 则需要支付
            $new_start_pay = 1;

            //余额不足时需要提醒
            if($new_cost_currency_total<$hongbao_config['hongbao_orders_dnc_num']) {
                $new_start_notice = 1;
            }
        }

        //第51次 必提醒
//        if($new_start_total==$hongbao_config['hongbao_orders_limit']) {
//            $new_start_notice = 1; //第51次时要提醒
//        }

        return [
            'new_start_total' => $new_start_total, //总订单数
            'new_start_notice' => $new_start_notice, //是否需要提醒
            'new_start_pay' => $new_start_pay, //是否需要
            'new_cost_currency_name' => self::HB_COST_CURRENCY_NAME, //需支付币种
            'new_cost_currency_num' => $hongbao_config['hongbao_orders_dnc_num'], //需支付数量
            'new_cost_currency_total' => $new_cost_currency_total, //需支付币种余额
            'new_cost_currency_user' => $new_cost_currency_user,
        ];
    }
}
