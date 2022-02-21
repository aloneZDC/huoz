<?php
//投票 俱乐部 配置
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoTimeSetting extends Model
{
    static function getTimeSetting() {
        $list = self::order('hour asc,minute asc')->select();
        return $list;
    }

    static function getNextTime($timeSetting,$last_open,$continue_time=0) {
        if(empty($timeSetting)) return 0;
        $time = time();

        $today = date('Y-m-d');
        $add_hour=date("H");
        foreach ($timeSetting as $setting){
            if($setting['hour']>$add_hour) {
                return strtotime($today.' '.$setting['hour'].':'.$setting['minute'].':00');
            }

            if($setting['hour']==$add_hour) {
                $start_time = strtotime($today.' '.$setting['hour'].':'.$setting['minute'].':00');
                $stop_time  = $start_time + $continue_time;
                if($time<=$stop_time){
                    if($last_open>=$start_time && $last_open<=$stop_time) {
                        //当前周期已经拆过
                    } else {
                        return $start_time;
                    }
                }
            }
        }
        return strtotime($today.' '.$timeSetting[0]['hour'].':'.$timeSetting[0]['minute'].':00')+86400; //第二天
    }

    //获取所有的开奖时间
    static function get_all_next($hongbao_log,$timeSetting,$continue_time) {
        if(empty($timeSetting)) return 0;

        $all_next = [];
        $time = time(); //当前时间
        $today = date('Y-m-d',$hongbao_log['create_time']); //当天
        $today_start = strtotime($today.' 00:00:00'); //当天时间戳
        $next_day_start = $today_start + 86400; //次日时间戳
        $next_day = date('Y-m-d',$next_day_start); //次日
        foreach ($timeSetting as $setting) {
            $next_time_start = strtotime($today.' '.$setting['hour'].':'.$setting['minute'].':00');
            $hour = date('H',$next_time_start);
            $minute = date('i',$next_time_start);
            if($next_time_start<$hongbao_log['create_time']) {
                $next_time_start = strtotime($next_day.' '.$setting['hour'].':'.$setting['minute'].':00');
                $hour = lang('next_day').$hour;
            }
            $next_time_stop = $next_time_start>0 ? $next_time_start+$continue_time : 0;

            //已拆记录
            $num = 0;
            foreach ($hongbao_log['chai'] as $log) {
                if($log['create_time']>=$next_time_start && $log['create_time']<=$next_time_stop) $num = $log['num'];
            }

            if($time<$next_time_stop && $num==0) { //当前没有结束 并且没有拆开 正常倒计时

            } else {
                $next_time_start = $next_time_stop = 0; //已结束
            }

            //$next_time_start>0 倒计时   next_time_start=0 && num>0 显示拆开数量  next_time_start=0 && num=0 已过期
            $all_next[] = [
                'hour' => $hour,
                'monute' => $minute,
                'next_time_start' => $next_time_start,
                'next_time_stop' => $next_time_stop,
                'server_time' => $time,
                'num' => $num, //已拆开数量
            ];
        }
        return $all_next;
    }
}
