<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class ChiaMiningLevelIncomeDetail extends Model
{
    /**
     * 添加级差记录
     * @param int $member_id 用户ID
     * @param string $today 日期
     * @param int $currency_id 币种id
     * @param int $level 当前级别
     * @param int $type 级差类型 1业绩级差 2业绩平级 3挖矿级差 4挖矿平级
     * @param int $num  奖励
     * @param int $percent 奖励比例
     * @param int $third_num 奖励基数
     * @param int $third_id 支付表ID
     * @param int $third_member_id 支付表用户ID
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */ 
	static function addIncome($member_id, $today, $currency_id, $level, $type, $num=0, $percent=0, $third_num=0, $third_id=0, $third_member_id=0) {
        $flag = self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'level' => $level,
            'type' => $type,
            'num' => $num,
            'percent' => $percent,
            'third_num' => $third_num,
            'third_id' => $third_id,
            'third_member_id' => $third_member_id,
            'award_time' => strtotime($today),
            'add_time' => time(),
        ]);
        return $flag;
    }
}