<?php


namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class RocketGoods extends Base
{
    /**
     * 获取闯关列表
     * @param int $member_id 用户ID
     * @param int $page 页
     * @param int $rows  页数
     */
    static function get_index($member_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null, 'platform_num' => 0, 'lucky_num' => 0];

        $list = self::alias('a')->where(['a.status' => 1])
            //->join([$builSql => 'b'], 'a.id=b.goods_id')
            ->field('a.id,a.name,a.warehouse1,a.run_daynum,a.is_vip')
            ->page($page, $rows)
            ->order("a.id asc")
            ->select();
        if (!$list) return $r;

        $today = time();//当天
        foreach ($list as &$value) {
            $info = RocketGoodsList::where(['goods_id' => $value['id'], 'status' => 1, 'is_show' => 1])->order('id desc')->find();

            $value['rocket_status'] = 0;//闯关状态 0未开启 1开启
            $value['game_start_time'] = date('m-d H:i', $info['start_time']);//闯关时间
            $next_time = keepPoint($info['start_time'] + ($value['run_daynum'] * 86400), 0);
            $value['flicker_status'] = 0;//0不动 1闪烁
            if (sprintf('%.2f', $info['price']) > sprintf('%.2f', $info['finish_money'])) {
                if ($today >= $info['start_time'] && $today <= $info['end_time']) {
                    $value['rocket_status'] = 1;
                } elseif ($today > $info['end_time']) {
                    $value['game_start_time'] = date('m-d H:i', $next_time);//闯关时间
                }
                $subscribe_start_time = $info['start_time'] - 1800;
                $subscribe_end_time = $info['start_time'];
                if ($today >= $subscribe_start_time && $today <= $subscribe_end_time) {
                    $value['flicker_status'] = 1;//闪烁
                } elseif ($today > $subscribe_end_time) {
                    $value['flicker_status'] = 0;//不动
                }
                $max_id = RocketGoodsList::where(['goods_id' => $value['id'], 'status' => 1])->order('id desc')->value('id');
                if ($max_id != $info['id']) {
                    $value['game_start_time'] = '预约中待开启';//闯关时间
                }
            } elseif ($today < $info['start_time']) {
                $value['flicker_status'] = 1;//闪烁
            } else {
                $value['game_start_time'] = '预约中待开启';//闯关时间
            }
            $value['price'] = $info['price'];
            $value['finish_money'] = $info['finish_money'];
        }
        $platform_currency_id = RocketConfig::getValue('platform_currency_id');
        $CurrencyUser = new CurrencyUser();
        $platform_num = $CurrencyUser->getNum($member_id, $platform_currency_id);
        $lucky_num = RocketGoods::where(['status' => 1])->sum('warehouse1');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['platform_num'] = $platform_num;//平台币
        $r['lucky_num'] = $lucky_num;//幸运舱
        return $r;
    }

    /**
     * 获取推进记录
     * @param int $member_id 用户ID
     * @param int $page 页
     * @param int $rows  页数
     */
    static function buy_list($member_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null, 'total_num' => 0, 'integral_num' => 0];

        $list = self::where(['status' => 1])->field('id,name')
            ->page($page, $rows)
            ->order("id asc")
            ->select();
        if (!$list) return $r;

        $total_num = 0;
        foreach ($list as &$value) {
            $goods_list_ids = RocketGoodsList::where(['goods_id' => $value['id']])->column('id');
            $num = RocketOrder::whereIn('goods_list_id', $goods_list_ids)->where(['member_id' => $member_id])->sum('statics_reward');
            $value['num'] = keepPoint($num, 6);
            $total_num = sprintf('%.6f', $total_num + $num);
        }
        $integral_currency_id = RocketConfig::getValue('integral_currency_id');
        $CurrencyUser = new CurrencyUser();
        $integral_num = $CurrencyUser->getNum($member_id, $integral_currency_id);
        if ($total_num == 0) {
            $total_num = 0;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['total_num'] = $total_num;
        $r['integral_num'] = $integral_num;//积分
        return $r;
    }

    /**
     * 获取舱列表
     * @param int $member_id 用户ID
     * @param int $type      1幸运舱 2市值舱 3工具舱
     * @param int $page      页
     * @param int $rows      页数
     */
    static function get_list($member_id, $type, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $list = self::where(['status' => 1])->field('id,name,warehouse1,warehouse2,warehouse3')
            ->page($page, $rows)
            ->order("id asc")
            ->select();
        if (!$list) return $r;
        foreach ($list as &$value) {
            if ($type == 1) {
                $value['warehouse'] = $value['warehouse1'];
            } elseif ($type == 2) {
                $value['warehouse'] = $value['warehouse2'];
            } else {
                $value['warehouse'] = $value['warehouse3'];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;

        return $r;
    }

    /**
     * 获取舱记录
     * @param int $member_id 用户ID
     * @param int $type      1幸运舱 2市值舱 3工具舱
     * @param int $goods_id 主闯关ID
     * @param int $page 页
     * @param int $rows  页数
     */
    static function get_log($member_id, $type, $goods_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        if (!$goods_id) return $r;
        $where = [];
        if ($type == 1) {
            $where['warehouse1'] = ['gt', 0];
        } elseif ($type == 2) {
            $where['warehouse2'] = ['gt', 0];
        } elseif ($type == 3) {
            $where['warehouse3'] = ['gt', 0];
        }
        $list = RocketGoodsList::field('id,name,level,status,warehouse1,warehouse2,warehouse3,last_settlement_time,end_hour')
            ->where(['status' => ['gt', 1], 'rocket_status' => 3, 'goods_id' => $goods_id])
            ->where($where)
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;

        foreach ($list as &$value) {
            $start_date = date('Y-m-d', $value['last_settlement_time']);
            if ($value['level'] == 1) {
                $value['level_name'] = '点火';
            } else {
                $value['level_name'] = '推进' . $value['level'];
            }
            $value['game_start_time'] = $start_date .' '. $value['end_hour'] . ':00';//闯关时间
            if ($type == 1) {
                if ($value['status'] == 2) {
                    $value['warehouse'] = '+' . $value['warehouse1'];
                } else {
                    $value['warehouse'] = '-' . $value['warehouse1'];
                }
            } elseif ($type == 2) {
                $value['warehouse'] = '+' . $value['warehouse2'];
            } else {
                $value['warehouse'] = '+' . $value['warehouse3'];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        
        
        return $r;
    }

    /**
     * 获取游戏类型
     * @param int $member_id 用户ID
     */
    static function get_game_type($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $currency_id = RocketConfig::getValue('reward_currency_id');
        $rocket_currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);

        $ark_currency_id = ArkConfig::getValue('reward_currency_id');
        $ark_currency_user = CurrencyUser::getCurrencyUser($member_id, $ark_currency_id);
        $subscribe_currency_id = ArkConfig::getValue('subscribe_currency_id');
        $subscribe_currency_user = CurrencyUser::getCurrencyUser($member_id, $subscribe_currency_id);
        $is_check = ArkOrder::where(['member_id' => $member_id])->find();

        $is_ark = $is_rocket = 2;//不显示
        if ($rocket_currency_user['num'] > 0) {
            $is_rocket = 1;//显示
        }
        if ($ark_currency_user['num'] > 0 || $subscribe_currency_user['num'] > 0 || $is_check) {
            $is_ark = 1;//显示
        }

        $result = ['is_ark' => $is_ark, 'is_rocket' => $is_rocket];

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;

        return $r;
    }

    /**
     * 获取随机数
     * @param int $num 数量
     */
    static function get_random($num) {
        $random = rand(0, 9);
        $random_num = 0;
        if ($random > 0) {
            if (sprintf('%.2f', $num) > $random_num) {
                $random_num = sprintf('%.2f', '0.0' . $random);
            }
        }
        return $random_num;
    }
}