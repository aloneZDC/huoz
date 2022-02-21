<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿冻结记录表
 * Class GroupMiningLog
 * @package app\common\model
 */
class GroupMiningForzenLog extends Model
{

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function RewardCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'reward_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }

    //释放记录
    static function getList($user_id, $level_id, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($level_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);//拼团挖矿币种id
            $currency = Currency::get($currencyId);
            $where = ['user_id' => $user_id, 'level_id' => $level_id, 'currency_id' => $currencyId, 'type' => 2];
            $list = self::where($where)->page($page, $rows)->order("add_time desc")->select();

            if (!empty($list)) {
                $log_list = [];
                foreach ($list as $value) {
                    $flag = $value['type'] == 1 ? '' : '-';//类型 1-冻结 2-释放
                    $log_list[] = [
                        'id'=>$value['id'],
                        'add_time'=>date('m-d H:i', $value['add_time']),
                        'num'=>$flag.$value['num'],
                        'type'=>$value['type'],
                        'currency_name'=>$currency['currency_name'],
                    ];
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $log_list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }
}
