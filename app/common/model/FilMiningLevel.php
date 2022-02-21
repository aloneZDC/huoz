<?php
//Fil项目 入金数量及释放比例

namespace app\common\model;

class FilMiningLevel extends Base
{
    const MONTH_DAY = 30;
    static function getJoinMonth($start_time,$today_start=0) {
        if(empty($today_start)) $today_start = todayBeginTimestamp();
        return intval(($today_start - $start_time) / (self::MONTH_DAY * 86400) ) + 1;
    }

    // 获取该月的释放比例
    static function getMonthPercent($start_time,$today_start=0) {
        // 今天是加入的第几个月份
        $join_month = self::getJoinMonth($start_time,$today_start);

        $info = self::where(['month_num' => $join_month])->find();
        if(!$info) {
            return self::getMonthLastPercent();
        }

        return $info['month_percent'];
    }

    // 获取第一个月释放比例
    static function getMonthFirstPercent() {
        $info = self::order('month_num asc')->find();
        if(empty($info)) return 0;

        return $info['month_percent'];
    }

    // 获取最后一个月释放比例
    static function getMonthLastPercent() {
        $info = self::order('month_num desc')->find();
        if(empty($info)) return 0;

        return $info['month_percent'];
    }

    // 获取月份最大释放比例
    static function getMonthMaxPercent() {
        $info = self::order('month_percent desc')->find();
        if(empty($info)) return 0;

        return $info['month_percent'];
    }

    //获取月份所有释放比例
    static function getMonthAllPercent() {
        $all = self::order('month_num asc')->where(['month_percent'=>['gt',0]])->select();
        if(empty($all)) return [];

        return $all;
    }

    //获取月份所有释放比例
    static function getCurMonthPercent($all_month_percent,$start_time,$today_start) {
        $join_month = self::getJoinMonth($start_time,$today_start);

        foreach ($all_month_percent as $percent) {
            if ($join_month==$percent['month_num'] ) {
                return $percent['month_percent'];
            }
        }
        return 0;
    }

    // 获取所有等级
    static function getAllLevel() {
        $all = self::where(['level_id'=>['gt',0]])->order('level_id asc')->select();
        if(empty($all)) return [];

        return $all;
    }

    static function getGlobalLevel() {
        $find = self::where(['level_global'=> 1])->order('level_id desc')->find();
        if(empty($find)) return [];

        return $find;
    }


//    //获取大于改数量的下一个数量
//    static function getNextNum($num) {
//        $info = self::where(['num' => ['gt',$num]])->order('num asc')->find();
//        if(!$info) return 0;
//
//        return $info['num'];
//    }
//
//
//    //获取大于改释放比例的 下一个释放比例
//    //获取不到则是最大释放比例
//    static function getNextPercent($percent) {
//        $info = self::where(['percent' => ['gt',$percent]])->order('percent asc')->find();
//        if(!$info) return $percent;
//
//        return $info['percent'];
//    }
//
//    // 获取最低释放比例
//    static function getFirstPercent() {
//        $info = self::order('percent asc')->find();
//        if(empty($info)) return 0;
//
//        return $info['percent'];
//    }
//
//    // 获取最低释放比例
//    static function getMaxPercent() {
//        $info = self::order('percent desc')->find();
//        if(empty($info)) return 0;
//
//        return $info['percent'];
//    }
//
//    //获取所有释放比例
//    static function getAllPercent() {
//        $all = self::order('percent asc')->select();
//        if(empty($all)) return [];
//
//        return $all;
//    }
//
//    //获取不到则是最大释放比例
//    static function getNextPercentByConfig($allPercent,$percent) {
//        foreach ($allPercent as $p) {
//            if($p['percent']>$percent) return $p['percent'];
//        }
//        return $percent;
//    }
}
