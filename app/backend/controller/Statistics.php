<?php


namespace app\backend\controller;

use think\Db;
use think\Request;
use think\Exception;
use app\common\model\GoodsMainOrders;

class Statistics extends AdminQuick
{
    //统计列表
    public function list() {
        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $today_end = $today_start + 86399;
        $today_member_num = Db::name('member')->where(['reg_time' => ['between', [$today_start, $today_end]]])->count('member_id');
        $total_member_num = Db::name('member')->count('member_id');

        $today_num = GoodsMainOrders::where(['gmo_pay_time' => ['between', [$today_start, $today_end]], 'gmo_status' => ['in', [GoodsMainOrders::STATUS_PAID, GoodsMainOrders::STATUS_SHIPPED, GoodsMainOrders::STATUS_COMPLETE]]])->sum('gmo_total_price');
        $total_num = GoodsMainOrders::where(['gmo_status' => ['in', [GoodsMainOrders::STATUS_PAID, GoodsMainOrders::STATUS_SHIPPED, GoodsMainOrders::STATUS_COMPLETE]]])->sum('gmo_total_price');

        $today_pay_num = Db::name('pay')->where(['add_time' => ['between', [$today_start, $today_end]]])->sum('money');
        $total_pay_num = Db::name('pay')->sum('money');
        $list = [
            'today_member_num' => $today_member_num,
            'total_member_num' => $total_member_num,
            'today_num' => $today_num,
            'total_num' => $total_num,
            'today_pay_num' => $today_pay_num,
            'total_pay_num' => $total_pay_num,
        ];
        return $this->fetch('', ['list' => $list]);
    }

    //提现统计
    public function withdraw_info() {
        $start_time = input('start_time');
        $end_time = input('end_time');
        $where = [];
        if ($start_time && $end_time) {
            $where['add_time'] = ['between', [strtotime($start_time), strtotime($end_time)]];
        }
        $count_num = Db::name('wechat_transfer')->where($where)->count('id');
        $count_money = Db::name('wechat_transfer')->where($where)->sum('amount');

        $first_count_num = Db::name('wechat_transfer')->where($where)->where(['check_status' => 1])->count('id');
        $first_count_money = Db::name('wechat_transfer')->where($where)->where(['check_status' => 1])->sum('amount');

        $second_count_num = Db::name('wechat_transfer')->where($where)->where(['check_status' => 2])->count('id');
        $second_count_money = Db::name('wechat_transfer')->where($where)->where(['check_status' => 2])->sum('amount');

        $third_count_num = Db::name('wechat_transfer')->where($where)->where(['check_status' => 0])->count('id');
        $third_count_money = Db::name('wechat_transfer')->where($where)->where(['check_status' => 0])->sum('amount');

        $list = [
            'count_num' => $count_num,
            'count_money' => $count_money,
            'first_count_num' => $first_count_num,
            'first_count_money' => $first_count_money,
            'second_count_num' => $second_count_num,
            'second_count_money' => $second_count_money,
            'third_count_num' => $third_count_num,
            'third_count_money' => $third_count_money,
        ];
        return $this->fetch('', ['list' => $list]);
    }
}