<?php
//跳跃排名倒序加权算法 - 订单
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class SpacePlan extends Model
{
    //入金币种ID
    const CURRENCY_ID = 38;
    const MASTER = 1;
    const SLAVE = 0;

    const STATUS_OK = 1; //正常
    const STATUS_OUT = 2; //已出舱

    static function open_check($space_plan_config) {
        $hour = intval(date('H'));
        if($hour>=$space_plan_config['space_start_hour'] && $hour<=$space_plan_config['space_stop_hour']) return true;
        return false;
    }

    //开舱
    static function addItems($member_id,$num,$pwd) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($member_id<=0) return $r;

        $check_pwd = Member::verifyPaypwd($member_id,$pwd);
        if($check_pwd['code']!=SUCCESS) {
            $r['message'] = $check_pwd['message'];
            return $r;
        }

        $space_plan_config = SpacePlanConfig::get_key_value();
        if(empty($space_plan_config)) return $r;

        if(!self::open_check($space_plan_config)) {
            $r['message'] = lang('space_open_time',['time1'=>$space_plan_config['space_start_hour'],'time2'=>$space_plan_config['space_stop_hour']]);
            return $r;
        }

        if($space_plan_config['space_transfer_min_mum']>0 && $num<$space_plan_config['space_transfer_min_mum']) {
            $r['message'] = lang('space_in_min_mum',['num'=>$space_plan_config['space_transfer_min_mum']]);
            return $r;
        }

        $time = time();
        if($space_plan_config['space_slave_day_limit']>0) {
            $last = self::where(['member_id'=>$member_id,'type'=>self::SLAVE])->order('id desc')->find();
            if($last) {
                $next_time = $last['add_time'] + 86400*$space_plan_config['space_slave_day_limit'];
                if($next_time>$time) {
                    $r['message'] = lang('space_slave_day_limit',['num'=>date('Y-m-d H:i:s',$next_time)]);
                    return $r;
                }
            }
        }

        $actual_num = $num;
        //开仓费用
        $is_master = self::isMaster($member_id);
        $open_num = $space_plan_config['space_open_master_fee_num'];
        if(!$is_master) $open_num = $space_plan_config['space_open_slave_fee_num'];

        if($open_num>0) $actual_num += $open_num;

        //实际扣除数量
        $currency_user = CurrencyUser::getCurrencyUser($member_id,self::CURRENCY_ID);
        if(!$currency_user || $currency_user['num']<$actual_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $space_num = $num;
        if($is_master && $space_plan_config['space_activity_total']>0) {
            $total = self::where(['type'=>self::MASTER])->count();
            if($total<=$space_plan_config['space_activity_total']){
                $space_num = $space_num + $space_plan_config['space_activity_num'];
            }
        }

        try{
            self::startTrans();

            //添加业绩记录表
            $flag = SpacePlanSummary::addItem($member_id);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加总累计入金
            $flag = SpacePlanSummary::where(['member_id'=>$member_id])->setInc('total_num',$space_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加订单
            $start_time = todayBeginTimestamp() + 86400;
            $stop_time = $start_time + $space_plan_config['space_lock_day'] * 86400;
            $space_plan_type = $is_master ? self::MASTER : self::SLAVE;
            $item_id = self::insertGetId([
                'type' => $space_plan_type,
                'serial_no' => date('YmdH').randNum(6),
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $space_num,
                'total_release' => 0,
                'add_time' => $time,
                'start_time' => $start_time,
                'stop_time' => $stop_time,
                'status' => self::STATUS_OK,
            ]);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            //添加支付记录
            $flag = SpacePlanPay::addPay($currency_user['member_id'],$currency_user['currency_id'],$num,$space_plan_type,$actual_num,$item_id);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($actual_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2501,'space_open','out',$actual_num,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$actual_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //增加上级动力源时间
            if($num>=$space_plan_config['space_pid_add_min_num']){
                $first_member_id =0;
                if(!$is_master) {
                    $first_member_id = $member_id;
                } else {
                    $member = Member::where(['member_id'=>$member_id])->field('pid')->find();
                    if($member) $first_member_id = $member['pid'];
                }
                $space_plan_summary = SpacePlanSummary::where(['member_id'=>$first_member_id])->find();
                if($space_plan_summary){
                    if($space_plan_summary['power_stop_time']<$start_time) $space_plan_summary['power_stop_time'] = $start_time;
                    $power_stop_time = $space_plan_summary['power_stop_time'] + $space_plan_config['space_pid_add_power_day'] * 86400;

                    $flag = SpacePlanSummary::where(['member_id'=>$first_member_id])->setField('power_stop_time',$power_stop_time);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = [
                'award_num' => $space_num>$num ? $space_num-$num : 0
            ];
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;
    }

    //主舱 复投
    static function itemsRestart($member_id,$space_id,$num,$pwd) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($member_id<=0) return $r;

        $check_pwd = Member::verifyPaypwd($member_id,$pwd);
        if($check_pwd['code']!=SUCCESS) {
            $r['message'] = $check_pwd['message'];
            return $r;
        }

        $space_plan_config = SpacePlanConfig::get_key_value();
        if(empty($space_plan_config)) return $r;

        if(!self::open_check($space_plan_config)) {
            $r['message'] = lang('space_open_time',['time1'=>$space_plan_config['space_start_hour'],'time2'=>$space_plan_config['space_stop_hour']]);
            return $r;
        }

        if($space_plan_config['space_transfer_min_mum']>0 && $num<$space_plan_config['space_transfer_min_mum']) {
            $r['message'] = lang('space_in_min_mum',['num'=>$space_plan_config['space_transfer_min_mum']]);
            return $r;
        }

        $space_plan = self::where(['id'=>$space_id,'member_id'=>$member_id,'type'=>self::MASTER,'status'=>self::STATUS_OUT])->find();
        if(empty($space_plan)) return $r;

        $actual_num = $num;

        //实际扣除数量
        $currency_user = CurrencyUser::getCurrencyUser($member_id,self::CURRENCY_ID);
        if(!$currency_user || $currency_user['num']<$actual_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $time = time();
        try{
            self::startTrans();

            //添加总累计入金
            $flag = SpacePlanSummary::where(['member_id'=>$member_id])->setInc('total_num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加订单
            $start_time = todayBeginTimestamp() + 86400;
            $stop_time = $start_time + $space_plan_config['space_lock_day'] * 86400;
            $flag = self::where(['id'=>$space_plan['id'],'status'=>self::STATUS_OUT])->update([
                'num' => $num,
                'total_release' => 0,
                'today_release' => 0,
                'start_time' => $start_time,
                'stop_time' => $stop_time,
                'status' => self::STATUS_OK,
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加支付记录
            $flag = SpacePlanPay::addPay($currency_user['member_id'],$currency_user['currency_id'],$num,$space_plan['type'],$actual_num,$space_plan['id']);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($actual_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2501,'space_open','out',$actual_num,$space_plan['id'],0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$actual_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //增加上级动力源时间
            if($num>=$space_plan_config['space_pid_add_min_num']){
                $first_member_id =0;
                if($space_plan['type']==self::SLAVE) {
                    $first_member_id = $member_id;
                } else {
                    $member = Member::where(['member_id'=>$member_id])->field('pid')->find();
                    if($member) $first_member_id = $member['pid'];
                }
                $space_plan_summary = SpacePlanSummary::where(['member_id'=>$first_member_id])->find();
                if($space_plan_summary){
                    if($space_plan_summary['power_stop_time']<$start_time) $space_plan_summary['power_stop_time'] = $start_time;
                    $power_stop_time = $space_plan_summary['power_stop_time'] + $space_plan_config['space_pid_add_power_day'] * 86400;

                    $flag = SpacePlanSummary::where(['member_id'=>$first_member_id])->setField('power_stop_time',$power_stop_time);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;
    }

    static function isMaster($member_id) {
        $count = self::where(['member_id'=>$member_id])->count();
        return $count<=0  ? true : false;
    }

    static function getSpacePlanDays($today_time,$start_time) {
        return $today_time<$start_time ? 0 : ($today_time-$start_time)/86400 + 1;
    }

    static function getAvailSum($member_id) {
        $sum = self::where(['member_id'=>$member_id,'status'=>self::STATUS_OK])->sum('num');
        return $sum ? $sum : 0;
    }

    //所有的仓位
    static function getList($member_id,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($member_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $space_plan_config = SpacePlanConfig::get_key_value();
            if(empty($space_plan_config)) return $r;

            $day_config = SpacePlanDayConfig::getDayConfig();

            $today = date('Y-m-d');
            $where = ['a.member_id' => $member_id,'a.type'=>self::SLAVE];
            $field = "a.id,a.serial_no,a.type,a.num,a.total_release,a.today,a.today_release,a.add_time,a.start_time,a.stop_time,a.status,b.currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();

            if($page==1) {
                if(!$list) $list = [];

                $where['a.type'] = self::MASTER;
                $master = self::field($field)->alias('a')->where($where)
                    ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                    ->find();
                if($master) array_unshift($list,$master);
            }

            if (!empty($list)) {
                $user_air_level = UserAirLevel::where(['user_id'=>$member_id])->find();

                $today_begin = todayBeginTimestamp();
                foreach ($list as &$value) {
                    $value['days'] = self::getSpacePlanDays($today_begin,$value['start_time']);
                    $value['all_days'] = self::getSpacePlanDays($value['stop_time'],$value['start_time']) - 1;
                    if($value['stop_time']<=$today_begin) $value['status'] = self::STATUS_OUT;

                    $process = [];
                    if($day_config) {
                        foreach ($day_config as $config) {
                            $process[] = [
                                'day' => $config['day'],
                                'day_stop' => date('m-d',$value['start_time'] + $config['day'] * 86400)
                            ];
                        }
                    }
                    $value['process'] = $process;

                    $release_percent = 0;
                    $space_plan_day_config = SpacePlanDayConfig::getSpaceDayConfig($day_config,$value['days'],false);
                    if($space_plan_day_config) {
                        $release_percent = $space_plan_day_config['percent'];
                        if($user_air_level && $user_air_level['level_id']>=$space_plan_config['space_air_min_level_id']){
                            $release_percent = $space_plan_day_config['air_percent'];
                        }
                    }
                    $value['percent'] = $release_percent;

                    $value['add_time'] = date('m-d H:i', $value['add_time']);
                    $value['start_time'] = date('m-d', $value['start_time']);
                    $value['stop_time'] = date('m-d', $value['stop_time']);
                    if ($value['today'] != $today) $value['today_release'] = 0;
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

    //仓位汇总
    static function all_item_total($member_id) {
        $space_plan_config = SpacePlanConfig::get_key_value();

        $result = [
            'total_num' => 0, //总累计入金
            'total_release' => 0, //日燃料
            'total_recommand' => 0, //助力源
            'total_power' =>0, //动力源
            'power_stop_time' => '', //动力源截止时间
            'power_stop_day' => 0,

            'add_item_fee_num' => 0,
            'add_salve_item_fee_num' => 0,

            'currency_name' => '',
            'currency_logo' => '',
            'currency_num' => 0,//资产数量

            'is_open' => self::open_check($space_plan_config) ? 1 : 0,
            'start_hour' => 0,
            'stop_hour' => $space_plan_config['space_start_hour'],
            'space_transfer_min_mum' => $space_plan_config['space_transfer_min_mum'], //进舱最低数量限制
        ];
        $space_plan_summary = SpacePlanSummary::where(['member_id'=>$member_id])->find();
        if($space_plan_summary) {
            $result['total_num'] = $space_plan_summary['total_num'];
            $result['total_release'] = $space_plan_summary['total_release'];
            $result['total_recommand'] = $space_plan_summary['total_recommand'];
            $result['total_power'] = $space_plan_summary['total_power'];
            $result['power_stop_time'] = $space_plan_summary['power_stop_time'] ? date('Y-m-d') : '';
            $result['power_stop_day'] = $space_plan_summary['power_stop_time'] ? ($space_plan_summary['power_stop_time']-todayBeginTimestamp())/86400-1 : 0;
            if($result['power_stop_day']<0) $result['power_stop_day'] = 0;
        }

        $is_master = self::isMaster($member_id);
        $result['add_item_fee_num'] = $space_plan_config['space_open_master_fee_num'];
        if(!$is_master) $result['add_item_fee_num'] = $space_plan_config['space_open_slave_fee_num'];
        $result['add_salve_item_fee_num'] = $space_plan_config['space_open_slave_fee_num'];

        $currency = Currency::where(['currency_id'=>self::CURRENCY_ID])->field('currency_id,currency_name,currency_logo')->find();
        if($currency) {
            $result['currency_name'] = $currency['currency_name'];
            $result['currency_logo'] = $currency['currency_logo'];
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id,self::CURRENCY_ID);
        if($currency_user) $result['currency_num'] = $currency_user['num'];

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $result;
        return $r;
    }

    static function getRadio()
    {
        $bbCurrencyId = Currency::where('trade_transfer_currency', Currency::DNC_ID)->value('currency_id');
        $tradeBbCurrencyId = Currency::where('trade_transfer_currency', Currency::XRP_PLUS_ID)->value('currency_id');
        $radio = Trade::getYestodayMaxPrice($bbCurrencyId, $tradeBbCurrencyId);
        if ($radio <= 0)  return 0;
        return $radio;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
