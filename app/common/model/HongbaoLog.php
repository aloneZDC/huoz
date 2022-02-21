<?php
//红包项目 红包记录
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoLog extends Model
{
    static function config($user_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('parameter_error');
        $r['result'] = null;

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config) || !isset($hongbao_config['hongbao_currency_mark'])) return $r;

        $hongbao_currency = Currency::where(['currency_mark'=>$hongbao_config['hongbao_currency_mark']])->field('currency_id,currency_mark,currency_name')->find();
        if(empty($hongbao_currency)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }


        $time = time();
        $today_begin = todayBeginTimestamp();
        $real_has_count = $has_count = self::where(['user_id'=>$user_id,'create_time'=>['egt',$today_begin],'super_num'=>0])->count();
        $has_sum = self::where(['user_id'=>$user_id,'create_time'=>['egt',$today_begin]])->sum('super_num');
        if($has_sum) $has_count += $has_sum;

        //昨日下级直推数量
        $yestoday = date('Y-m-d',$time-86400);
        $child_num = FlopChildNum::getTodayTotalNum('hongbao',$user_id,$yestoday);
        $total_count = $hongbao_config['hongbao_day_count'] + $child_num;
        $yu_count = $total_count-$has_count;

        $super_check = 2;
        if($yu_count>0) {
            $super_check_flag = self::super_check($user_id,$hongbao_currency['currency_id'],$hongbao_config);
            if($super_check_flag) $super_check = 1;
        }

        $common_check = 1;
        if($real_has_count>=$hongbao_config['hongbao_day_count']){
            $common_check = 2;
        }

        $hongbao_users_currency = CurrencyUser::getCurrencyUser($user_id,$hongbao_currency['currency_id']);

        $result = [
            'all_hongbao_limit' => $total_count,
            'today_hongbao' => $has_count,
            'yu_hongbao' =>  $yu_count,
            'min_num' => $hongbao_config['hongbao_min_num'],
            'max_num' => $hongbao_config['hongbao_max_num'],
            'user_num' => $hongbao_users_currency ? $hongbao_users_currency['num'] : 0,
            'currency_name' => $hongbao_currency['currency_name'],
            'hb_name' => lang('hongbao'),
            'is_open' => $hongbao_config['hongbao_is_open'],
            'super_check' => $super_check,
            'common_check' => $common_check,
            'super_desc' => lang('hongbao_super_desc'),
        ];

        //20200628增加 买50单以后 每次要扣除1DNC
        $result['new_cost'] = FlopDncNum::checkHongbaoNotice($user_id,$hongbao_config);

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        return $r;
    }

    //添加红包订单
    static function add_log($user_id,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if(!isInteger($num)) return $r;

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config) || !isset($hongbao_config['hongbao_currency_mark'])) return $r;

        if($hongbao_config['hongbao_is_open']!=1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $time = time();
        $open_time = strtotime($hongbao_config['hongbao_auto_open']);
        if($time<$open_time) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if($hongbao_config['hongbao_min_num']>0 && $num<$hongbao_config['hongbao_min_num']) {
            $r['message'] = lang('lan_num_not_less_than').$hongbao_config['hongbao_min_num'];
            return $r;
        }
        if($hongbao_config['hongbao_max_num']>0 && $num>$hongbao_config['hongbao_max_num']) {
            $r['message'] = lang('lan_num_not_greater_than').$hongbao_config['hongbao_max_num'];
            return $r;
        }

        //每日红包订单数量限制
        $today_begin = todayBeginTimestamp();
        $yestoday = date('Y-m-d',$time-86400);
        $real_has_count = $has_count = self::where(['user_id'=>$user_id,'create_time'=>['egt',$today_begin]])->count();
        $child_num = FlopChildNum::getTodayTotalNum('hongbao',$user_id,$yestoday);


        if($hongbao_config['hongbao_day_count']>0) {
            if($real_has_count>=$hongbao_config['hongbao_day_count'])  {
                $r['message'] = lang('hongbao_limit_count',['num'=>$hongbao_config['hongbao_day_count']]);
                return $r;
            }
        }

//        if($hongbao_config['hongbao_day_count']>0) {
//            $has_sum = self::where(['user_id'=>$user_id,'create_time'=>['egt',$today_begin]])->sum('super_num');
//            if($has_sum) $has_count += $has_sum;
//
//            $total_count = $hongbao_config['hongbao_day_count'] + $child_num;
//            if($has_count>=$total_count) {
//                $r['message'] = lang('hongbao_limit_count',['num'=>$total_count]);
//                return $r;
//            }
//        }

        $hongbao_currency = Currency::where(['currency_mark'=>$hongbao_config['hongbao_currency_mark']])->field('currency_id,currency_mark')->find();
        if(empty($hongbao_currency)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $hongbao_users_currency = CurrencyUser::getCurrencyUser($user_id,$hongbao_currency['currency_id']);
        if(empty($hongbao_users_currency) || $hongbao_users_currency['num']<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //检测是否需要支付DNC
        $checkHongbaoPay = FlopDncNum::checkHongbaoPay($user_id,$hongbao_config);

        try{
            self::startTrans();

            //扣除下级贡献数量
            if($has_count>=$hongbao_config['hongbao_day_count']) {
                $flag = FlopChildNum::where(['type'=>'hongbao','user_id'=>$user_id,'today'=>$yestoday,'child_num'=>$child_num])->setDec('child_avail_num',1);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            $is_can_release = 1;
            if($checkHongbaoPay['new_start_pay']==1) {
                if($checkHongbaoPay['new_cost_currency_total']>=$checkHongbaoPay['new_cost_currency_num']) {
                    //添加今日扣除记录
                    $flag = FlopDncNum::addItem($user_id,'hongbao',date('Y-m-d'));
                    if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                    //添加DNC账本 扣除DNC资产
                    $flag = AccountBook::add_accountbook($checkHongbaoPay['new_cost_currency_user']['member_id'],$checkHongbaoPay['new_cost_currency_user']['currency_id'],953,'hongbao_buy','out',$checkHongbaoPay['new_cost_currency_num'],0,0);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                    $flag = CurrencyUser::where(['cu_id'=>$checkHongbaoPay['new_cost_currency_user']['cu_id'],'num'=>$checkHongbaoPay['new_cost_currency_user']['num']])->setDec('num',$checkHongbaoPay['new_cost_currency_num']);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                } else {
                    $is_can_release = 0;
                }
            }

            //添加红包记录
            $insert_id = self::insertGetId([
                'user_id' => $user_id,
                'currency_id' => $hongbao_currency['currency_id'],
                'num' => $num,
                'open_num' => 0,
                'is_back' => 0,
                'create_time' => $time,
                'last_open' => $time,
                'code' => self::create_code(),
                'is_can_release' => $is_can_release,
            ]);
            if(!$insert_id) throw new Exception(lang('operation_failed_try_again'));

            //添加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$hongbao_currency['currency_id'],950,'hongbao','out',$num,$insert_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$hongbao_users_currency['cu_id'],'num'=>$hongbao_users_currency['num']])->setDec('num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //添加超级红包订单
    static function add_super_log($user_id,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if(!isInteger($num)) return $r;

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config) || !isset($hongbao_config['hongbao_currency_mark'])) return $r;

        if($hongbao_config['hongbao_is_open']!=1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $time = time();
        $open_time = strtotime($hongbao_config['hongbao_auto_open']);
        if($time<$open_time) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if($hongbao_config['hongbao_min_num']>0 && $num<$hongbao_config['hongbao_min_num']) {
            $r['message'] = lang('lan_num_not_less_than').$hongbao_config['hongbao_min_num'];
            return $r;
        }
        if($hongbao_config['hongbao_max_num']>0 && $num>$hongbao_config['hongbao_max_num']) {
            $r['message'] = lang('lan_num_not_greater_than').$hongbao_config['hongbao_max_num'];
            return $r;
        }

        $yestoday = date('Y-m-d',$time-86400);
        $child_num = FlopChildNum::getTodayNum('hongbao',$user_id,$yestoday);
        if($child_num<=0) {
            $r['message'] = lang('hongbao_super_day_limit');
            return $r;
        }

        $hongbao_currency = Currency::where(['currency_mark'=>$hongbao_config['hongbao_currency_mark']])->field('currency_id,currency_mark')->find();
        if(empty($hongbao_currency)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if(!self::super_check($user_id,$hongbao_currency['currency_id'],$hongbao_config)) {
            $r['message'] = lang('hongbao_super_limit');;
            return $r;
        }

        $hongbao_users_currency = CurrencyUser::getCurrencyUser($user_id,$hongbao_currency['currency_id']);
        if(empty($hongbao_users_currency) || $hongbao_users_currency['num']<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        try{
            self::startTrans();

            //扣除超级订单数量
            $flag = FlopChildNum::where(['type'=>'hongbao','user_id'=>$user_id,'today'=>$yestoday,'child_avail_num'=>$child_num])->setDec('child_avail_num',$child_num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //添加红包记录
            $insert_id = self::insertGetId([
                'user_id' => $user_id,
                'currency_id' => $hongbao_currency['currency_id'],
                'num' => $num,
                'open_num' => 0,
                'is_back' => 0,
                'create_time' => $time,
                'last_open' => $time,
                'super_num' => $child_num,
                'code' => self::create_code(),
            ]);
            if(!$insert_id) throw new Exception(lang('operation_failed_try_again'));

            //添加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$hongbao_currency['currency_id'],950,'hongbao','out',$num,$insert_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$hongbao_users_currency['cu_id'],'num'=>$hongbao_users_currency['num']])->setDec('num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //拆红包
    static function open_hongbao($user_id,$hongbao_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        $hongbao_config = HongbaoConfig::get_key_value();
        if(empty($hongbao_config) || empty($hongbao_config['hongbao_continue_time'])) return $r;

        $hongbao = self::where(['user_id'=>$user_id,'id'=>$hongbao_id])->find();
        if(empty($hongbao)) return $r;

        //已经返回 失效
        if($hongbao['is_back']==1) {
            $r['message'] = lang('hongbao_has_back');
            return $r;
        }

        $time = time();
        //拆红包时间未到
        $timeSetting = HongbaoTimeSetting::getTimeSetting();
        $next_time_start = HongbaoTimeSetting::getNextTime($timeSetting,$hongbao['last_open'],$hongbao_config['hongbao_continue_time']);
        $next_time_stop = $next_time_start + $hongbao_config['hongbao_continue_time'];
        if($time<$next_time_start || $time>$next_time_stop) {
            $r['message'] = lang('hongbao_time_has_not_arrived');
            return $r;
        }

        //本次周期已经拆过
        if($hongbao['last_open']>=$next_time_start) {
            $r['message'] = lang('hongbao_time_has_not_arrived');
            return $r;
        }

        //待返还中
        if($next_time_start > ($hongbao['create_time']+$hongbao_config['hongbao_back_time'])){
            $r['message'] = lang('hongbao_wait_back');
            return $r;
        }

        //最多拆红包次数限制
        if($hongbao['open_count']>=$hongbao_config['hongbao_chai_total']) {
            $r['message'] = lang('hongbao_next');
            return $r;
        }

        //随机比例
        $hongbao_base_num = $hongbao['num'];
        if($hongbao['super_num']>0) {
            $hongbao_min_percent = isset($hongbao_config['hongbao_super_min_percent']) ? $hongbao_config['hongbao_super_min_percent'] : 0;
            $hongbao_max_percent = isset($hongbao_config['hongbao_super_max_percent']) ? $hongbao_config['hongbao_super_max_percent'] : 0;
            $hongbao_base_num = $hongbao_base_num * $hongbao['super_num'];
        } else {
            $hongbao_min_percent = isset($hongbao_config['hongbao_min_percent']) ? $hongbao_config['hongbao_min_percent'] : 0;
            $hongbao_max_percent = isset($hongbao_config['hongbao_max_percent']) ? $hongbao_config['hongbao_max_percent'] : 0;
        }
        $percent = keepPoint(randomFloat($hongbao_min_percent,$hongbao_max_percent)/$hongbao_config['hongbao_chai_total'],2);
        $open_num = keepPoint($percent/100 * $hongbao_base_num,6);

        try{
            self::startTrans();
            $flag = HongbaoChaiLog::add_award($hongbao['id'],$hongbao['user_id'],$hongbao['currency_id'],$open_num,$hongbao_base_num,$percent);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = self::where(['id'=>$hongbao['id'],'open_count'=>$hongbao['open_count']])->update([
                'open_num' => ['inc',$open_num],
                'open_base_num' => ['inc',$open_num],
                'open_percent' => ['inc',$percent],
                'open_count' => ['inc',1],
                'last_open' => $time,
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = [
                'currency_name' => $hongbao_config['hongbao_currency_mark'],
                'num' => $open_num,
            ];
        } catch(Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    static function get_list_chai_log($user_id,$page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $hongbao_config = HongbaoConfig::get_key_value();
            if(empty($hongbao_config)) return $r;

            $timeSetting = HongbaoTimeSetting::getTimeSetting();
            $hongbao_back_time = isset($hongbao_config['hongbao_back_time']) ? intval($hongbao_config['hongbao_back_time']) : 0;
            $hongbao_continue_time = isset($hongbao_config['hongbao_continue_time']) ? intval($hongbao_config['hongbao_continue_time']) : 0;
            $where = [
                'a.user_id' => $user_id,
            ];
            $field = "a.id,a.num,a.code,a.open_num,a.open_base_num,a.open_percent,a.is_back,a.create_time,a.last_open,a.open_count,a.super_num,b.currency_name";
            $list = self::field($field)->alias('a')->with('chai')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                $time = time();
                foreach ($list as &$value){
                    $back_time = $value['create_time']+$hongbao_back_time;
                    $value['is_end'] = $back_time<$time ? 1 : 0;
                    $value['all_next'] = HongbaoTimeSetting::get_all_next($value,$timeSetting,$hongbao_continue_time);
                    $value['create_time'] = date('m-d H:i',$value['create_time']);
                    $value['code'] = substr($value['code'],8);
                    if($value['super_num']>0) $value['num'] = intval($value['num'] * $value['super_num']);
                    unset($value['chai']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        }
        return $r;
    }

    static function get_list($user_id,$page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $hongbao_config = HongbaoConfig::get_key_value();
            if(empty($hongbao_config)) return $r;

            $timeSetting = HongbaoTimeSetting::getTimeSetting();
            $hongbao_back_time = isset($hongbao_config['hongbao_back_time']) ? intval($hongbao_config['hongbao_back_time']) : 0;
            $hongbao_continue_time = isset($hongbao_config['hongbao_continue_time']) ? intval($hongbao_config['hongbao_continue_time']) : 0;
            $where = [
                'a.user_id' => $user_id,
            ];
            $field = "a.id,a.num,a.code,a.open_num,a.open_base_num,a.open_percent,a.is_back,a.create_time,a.last_open,a.open_count,b.currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                $time = time();
                foreach ($list as &$value){
                    $next_time_start = HongbaoTimeSetting::getNextTime($timeSetting,$value['last_open'],$hongbao_continue_time);
                    //下一次拆红包时间  0为待返还
                    $value['next_time_start'] = ($next_time_start < ($value['create_time']+$hongbao_back_time)) ? $next_time_start : 0;
                    $value['next_time_stop'] = $value['next_time_start']>0 ? $value['next_time_start']+$hongbao_continue_time : 0;
                    $value['server_time'] = $time;
                    $value['create_time'] = date('m-d H:i',$value['create_time']);
                    $value['code'] = substr($value['code'],8);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        }
        return $r;
    }

    static function create_code(){
        $code=date("YmdHi").randNum();
        $find=self::where(['code'=>$code])->field("code")->find();
        if(empty($find)){
            return $code;
        }else{
            return self::create_code();
        }
    }

    //红包资产返还 红包币种io 红包自动返还币种 USDT
    static function back_hongbao($hongbao,$hongbao_config) {
        $hongbao_fee = isset($hongbao_config['hongbao_fee']) ? $hongbao_config['hongbao_fee'] : 0;
        $hongbao_award_percent = [
            'percent1' => isset($hongbao_config['hongbao_award_percent1']) ? $hongbao_config['hongbao_award_percent1'] : 0,
            'percent2' => isset($hongbao_config['hongbao_award_percent2']) ? $hongbao_config['hongbao_award_percent2'] : 0,
        ];

        $hongbao_open_num = keepPoint($hongbao['open_base_num'] - $hongbao['open_base_num'] * $hongbao_fee/100,6); //扣除手续后的金额

        //如果设置拆开红包无效 则拆开红包不到账
        if($hongbao['is_can_release']!=1) {
            $hongbao['open_base_num'] = $hongbao_open_num = 0;
        }

        $hongbao_total_num = $hongbao['num'];

        //到可用总数量
        $hongbao_total_num_num = $hongbao_total_num;
        //到云攒金总数量
        $hongbao_total_ari_num = 0;
        if($hongbao_open_num>0) {
            //拆开红包到可用数量
            $hongbao_open_num_num = keepPoint($hongbao_open_num*$hongbao_config['hongbao_back_num_percent']/100,6);
            //拆开红包到云攒金数量
            $hongbao_open_ari_num = keepPoint($hongbao_open_num - $hongbao_open_num_num,6);
            $hongbao_open_ari_num = $hongbao_open_ari_num>0 ? $hongbao_open_ari_num : 0;

            //红包总数量
            $hongbao_total_num = keepPoint($hongbao['num'] + $hongbao_open_num,6);
            //到可用总数量
            $hongbao_total_num_num = keepPoint($hongbao['num'] + $hongbao_open_num_num,6);
            //到云攒金总数量
            $hongbao_total_ari_num = $hongbao_open_ari_num;
        }

        $hongbao_back_currency_id = $hongbao_config['hongbao_back_currency_id']; //返回币种USDT
        $hongbap_back_total_currency_num = keepPoint($hongbao_total_num*$hongbao_config['hongbao_currency_ratio'],6); //返还的USDT数量
        $hongbao_back_currency_num = keepPoint($hongbao_total_num_num*$hongbao_config['hongbao_currency_ratio'],6); //返还的USDT数量
        $hongbao_back_currency_air_num = keepPoint($hongbao_total_ari_num*$hongbao_config['hongbao_currency_ratio'],6); //返还的云攒金数量

        $hongbao_users_currency = CurrencyUser::getCurrencyUser($hongbao['user_id'],$hongbao_back_currency_id);
        if(empty($hongbao_users_currency)) return false;

        try{
            self::startTrans();
            $flag = self::where(['id'=>$hongbao['id'],'is_back'=>0])->update(['open_num'=>$hongbao_open_num,'is_back'=>1,'back_time'=>time(),'fee'=>$hongbao_fee,'back_currency_id'=>$hongbao_back_currency_id,'back_num'=>$hongbap_back_total_currency_num]);
            if(!$flag) throw new Exception("红包记录返还失败");

            //红包返还 添加账本 添加资产
            $flag = AccountBook::add_accountbook($hongbao_users_currency['member_id'],$hongbao_users_currency['currency_id'],951,'hongbao_back','in',$hongbao_back_currency_num,$hongbao['id'],0);
            if(!$flag) throw new Exception("添加账本错误");

            if($hongbao_back_currency_air_num>0) {
                $flag = HongbaoAirNumLog::add_log('hongbao',$hongbao_users_currency['member_id'],$hongbao_users_currency['currency_id'],$hongbao_back_currency_air_num,$hongbao['id'],$hongbao_open_num,keepPoint(100-$hongbao_config['hongbao_back_num_percent'],2));
                if(!$flag) throw new Exception("添加云攒金记录失败");
            }

            $flag = CurrencyUser::where(['cu_id'=>$hongbao_users_currency['cu_id'],'num'=>$hongbao_users_currency['num']])->update([
                'num' => ['inc',$hongbao_back_currency_num],
                'air_num' => ['inc',$hongbao_back_currency_air_num],
            ]);
            if(!$flag) throw new Exception("增加资产失败");

            //如果拆开红包则需要给上级奖励 转化为USDT
            $award_base_num =  keepPoint($hongbao['open_base_num'] *$hongbao_config['hongbao_currency_ratio'],6);
            if($award_base_num>0) {
                $curr_user_id = $hongbao['user_id'];
                for ($count=1;$count<=2;$count++) {
                    $curr_user = Member::where(['member_id'=>$curr_user_id])->field('member_id,pid')->find();
                    if(!$curr_user || $curr_user['pid']==0) break;

                    $curr_user_id = $curr_user['pid'];

                    $award_users_currency = CurrencyUser::getCurrencyUser($curr_user['pid'],$hongbao_back_currency_id);
                    if(empty($award_users_currency)) throw new Exception("获取用户资产失败");

                    $perecent = $hongbao_award_percent['percent'.$count];
                    $award_num = $award_base_num * $perecent / 100;
                    $award_num = keepPoint($award_num - $award_num * $hongbao_fee/100,6); //扣除手续后的金额
                    if($award_num>=0.000001) {
                        $flag = HongbaoAwardLog::add_award($award_users_currency['member_id'],$award_users_currency['currency_id'],$award_num,$award_base_num,$perecent,$hongbao_fee,$hongbao['id']);
                        if(!$flag) throw new Exception("增加红包赠送记录失败");

                        //红包奖励 添加账本 添加资产
                        $flag = AccountBook::add_accountbook($award_users_currency['member_id'],$award_users_currency['currency_id'],952,'hongbao_t','in',$award_num,$hongbao['id'],0);
                        if(!$flag) throw new Exception("添加账本错误");

                        $flag = CurrencyUser::where(['cu_id'=>$award_users_currency['cu_id'],'num'=>$award_users_currency['num']])->setInc('num',$award_num);
                        if(!$flag) throw new Exception("添加账本失败");
                    }
                }
            }

            self::commit();
        } catch(Exception $e) {
            self::rollback();
            Log::write("红包资产返还错误：".$e->getMessage());
        }
    }

    static function hongbao_index($user_id) {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = null;

        $member_info = Member::where(['member_id'=>$user_id])->find();
        $last_title =
        $result = [
            'member_id' => $user_id,
            'invitation_code' => $member_info['invit_code'],
            'invit_url' => url('mobile/Invite/index',['id'=>$member_info['invit_code']],false,true),
        ];
        return $r;
    }

    static function super_check($user_id,$currency_id,$config) {
        if($config['hongbao_super_min_orders']>0) {
            $count = self::where(['user_id'=>$user_id,'currency_id'=>$currency_id,'create_time'=>['egt',todayBeginTimestamp()]])->count();
            if($count<$config['hongbao_super_min_orders']) return false;
        }

//        if($config['hongbao_super_min_num']>0) {
//            $sum = self::where(['user_id'=>$user_id,'currency_id'=>$currency_id,'create_time'=>['egt',todayBeginTimestamp()]])->sum('num');
//            if($sum<$config['hongbao_super_min_num']) return false;
//        }
        return true;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function backcurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'back_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function chai() {
        return $this->hasMany('app\\common\\model\\HongbaoChaiLog', 'log_id', 'id')->field('log_id,num,create_time');
    }
}
