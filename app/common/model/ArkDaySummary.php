<?php


namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ArkDaySummary extends Model
{
    /**
     * 新增每日预约汇总
     * @param int $time    时间
     * @param number $num      预约数量
     */
    static function addItem($time, $num) {
        $res = self::where(['count_time' => $time])->find();
        if ($res) {
            $data = [
                'num' => ['inc', $num],
            ];
            $item_id = self::where(['id' => $res['id']])->update($data);
        } else {
            $data = [
                'num' => $num,
                'count_time' => $time,
                'add_time' => time()
            ];
            $item_id = self::insertGetId($data);
        }
        return $item_id;
    }

    /**
     * 合格考核
     * @param int $member_id    用户id
     * @param int $yestday      合格时间
     */
    static function handle_reward($member_id, $yestday) {
        $currency_id = ArkConfig::getValue('subscribe_currency_id');//预约池币种
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);

        $game_num = ArkOrder::where(['member_id' => $member_id, 'status' => 0])->sum('money');
        $num = sprintf('%.6f', $currency_user['num'] + $game_num);
        $is_reward = 0;
        $qualified = ArkConfig::getValue('check_reward_qualified');//获取奖励资格
        if ($num >= $qualified) {
            $is_reward = 1;
        }
        $data = [
            'member_id' => $member_id,
            'subscribe_num' => $currency_user['num'],
            'game_num' => $game_num,
            'is_reward' => $is_reward,
            'count_time' => $yestday,
            'add_time' => time()
        ];
        $flag = Db::name('ark_summary')->insertGetId($data);
        return $flag;
    }
}