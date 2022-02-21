<?php


namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ArkKmtLog extends Model
{
    /**
     * 新增记录
     * @param int $member_id    用户ID
     * @param int $product_id  子闯关ID
     * @param number $num      支付金额
     */
    static function addItem($member_id, $product_id, $num) {
        $info = ArkGoodsList::where(['id' => $product_id])->find();
        if (!$info) return false;

        $data = [
            'member_id' => $member_id,
            'goods_list_id' => $product_id,
            'goods_list_name' => $info['name'],
            'level_num' => $info['level'],
            'num' => $num,
            'add_time' => time()
        ];

        return self::insertGetId($data);
    }

    /**
     * 获取kmt燃料记录
     * @param int $member_id    用户id
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_kmt_log($member_id, $page, $rows= 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $platform_currency_id = ArkConfig::getValue('platform_currency_id');
        $list = Db::name('accountbook')->alias('a')
            ->where(['member_id' => $member_id, 'currency_id' => $platform_currency_id])
            ->join(config('database.prefix') . 'accountbook_type d', 'a.type=d.id', 'LEFT')
            ->field('a.type,a.number_type,a.number,a.add_time,a.third_id, d.name_tc')
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$list) return $r;

        foreach ($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            if ($value['number_type'] == 1) {
                $value['num'] = '+' . $value['number'];
            } else {
                $value['num'] = '-' . $value['number'];
            }
            $value['type_name'] = $value['name_tc'];
            if ($value['type'] == 7105) {//rocket_play_game
                $goods_list_id = ArkOrder::where(['id' => $value['third_id']])->value('goods_list_id');
                $info = ArkGoodsList::where(['id' => $goods_list_id])->find();
                if ($info) {
                    $value['type_name'] = $info['name'] . ' 推进' . $info['level'] . '消耗';
                }
            } elseif ($value['type'] == 600) {
                $info = CurrencyUserTransfer::where(['cut_id' => $value['third_id']])->find();
                if ($value['number_type'] == 2) {
                    $value['type_name'] = '转出至' . $info['cut_target_user_id'];
                } else {
                    $value['type_name'] = $info['cut_user_id'] . '转入';
                }
            }
            $value['currency_name'] = 'L令牌燃料';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;

    }
}