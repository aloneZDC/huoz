<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿冻结表
 * Class GroupMiningLog
 * @package app\common\model
 */
class GroupMiningForzen extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }

    //获取签到释放列表
    public static function getList($user_id)
    {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = [];

        $levels = GroupMiningSourceLevel::order('level_id', 'DESC')->select();
        if (count($levels) <= 0) return $r;
        $list = [];
        $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);//拼团挖矿币种id
        $currency = Currency::get($currencyId);
        $now = time();
        $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
        //$openBuy = GroupMiningSourceBuy::getOpenBuy($user_id);
        foreach ($levels as $key => $value) {
            $forzen = GroupMiningForzen::where(['user_id'=>$user_id,'level_id'=>$value['level_id'],'currency_id'=>$currencyId])->find();
            $forzenNum = 0;//冻结数量
            $todayFree = 0;//今日释放
            $totalFree = 0;//累积释放
            $freeStatus = 0;//释放状态 0-无可释放 1-可以释放 2-已释放
            $freeRateMin = $value['free_rate_min'];
            $freeRateMax = $value['free_rate_max'];
            if ($value['type'] == 1) {//1-申购
                $buyFind1 = GroupMiningSourceBuy::where(['user_id'=>$user_id,'level_id'=>$value['level_id'],'status'=>['in',[1,2]]])->find();
                $buyFind2 = GroupMiningSourceBuy::where(['user_id'=>$user_id,'level_id'=>$value['level_id'],'status'=>3])->find();
                if (!$buyFind1 && $buyFind2) {
                    $freeRateMin = $value['close_free_rate'];
                    $freeRateMax = $value['close_free_rate'];
                }
            }
            /*if ($openBuy) {
                if ($openBuy['level_id'] != $value['level_id']) {
                    if ($value['type'] == 1) {//1-申购
                        if ($buyFind) {
                            $freeRateMin = $value['close_free_rate'];
                            $freeRateMax = $value['close_free_rate'];
                        }
                    }
                }
            }
            else {
                if ($value['type'] == 1) {//1-申购
                    if ($buyFind) {
                        $freeRateMin = $value['close_free_rate'];
                        $freeRateMax = $value['close_free_rate'];
                    }
                }
            }*/
            if ($forzen) {
                $forzenNum = $forzen['forzen_num'];
                $todayFree = $forzen['today_free_num'];
                $totalFree = $forzen['total_free_num'];
                if ($forzen['last_free_time'] < $todayStartTime && $forzen['today_free_num'] > 0) {
                    $flag = self::where('id', $forzen['id'])->setField('today_free_num', 0);
                    $todayFree = 0;
                }
                if ($forzenNum > 0/* && $forzenNum > $totalFree*/) {
                    if ($todayFree <= 0) {
                        $freeStatus = 1;
                    }
                    else {
                        $freeStatus = 2;
                    }
                }
            }
            $index = $value['type'] == 2 ? 0 : $value['level_id'] - 1;
            $list[] = [
                'level_id'=>$value['level_id'],
                'level_name'=>$value['level_name'],
                'free_rate_min'=>$freeRateMin,
                'free_rate_max'=>$freeRateMax,
                'forzen_num'=>$forzenNum,
                'today_free_num'=>$todayFree,
                'total_free_num'=>$totalFree,
                'free_status'=>$freeStatus,
                'currency_name'=>$currency['currency_name'],
                'index'=>$index,
            ];
        }
        $r['result'] = $list;
        return $r;
    }

    //获取冻结信息
    static function getForzenInfo($user_id, $level_id, $currency_id)
    {
        $forzenInfo = self::where(['user_id'=>$user_id, 'level_id'=>$level_id, 'currency_id'=>$currency_id])->find();
        if ($forzenInfo) {
            $now = time();
            $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
            if ($forzenInfo['last_free_time'] < $todayStartTime && $forzenInfo['today_free_num'] > 0) {
                $flag = self::where('id', $forzenInfo['id'])->setField('today_free_num', 0);
                $forzenInfo = self::where('id', $forzenInfo['id'])->find();
            }
        }
        return $forzenInfo;
    }
}
