<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿记录表
 * Class GroupMiningLog
 * @package app\common\model
 */
class GroupMiningLog extends Model
{
    public function MoneyCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'money_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function ForzenCurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'forzen_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    public function sourceLevel() {
        return $this->belongsTo('app\\common\\model\\GroupMiningSourceLevel', 'level_id', 'level_id')->field('level_id,level_name');
    }

    //挖矿记录
    static function getList($user_id,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['user_id' => $user_id];
            $list = self::where($where)->with(['MoneyCurrency','ForzenCurrency'])->page($page, $rows)->order("id desc")->select();

            if (!empty($list)) {
                $log_list = [];
                foreach ($list as $value) {
                    $result = '';
                    if ($value['result'] == 1) {//结果 1-赢 2-输
                        $result = '荣享'.$value['forzen_num'].$value['ForzenCurrency']['currency_name'];
                    }
                    else if ($value['result'] == 2) {
                        $result = '补偿'.$value['reward_num'].$value['MoneyCurrency']['currency_name'];
                    }
                    else {
                        if ($value['result_type'] == 2) {//结算类型 1-挖矿结算(每局) 2-补偿金结算(每轮) 3-补偿金失效(每天)
                            $result = '补偿金结算'.$value['reward_num'].$value['MoneyCurrency']['currency_name'];
                        }
                        else if ($value['result_type'] == 3) {//结算类型 1-挖矿结算(每局) 2-补偿金结算(每轮) 3-补偿金失效(每天)
                            $result = '补偿金失效-'.$value['reward_num'].$value['MoneyCurrency']['currency_name'];
                        }
                    }
                    $log_list[] = [
                        'id'=>$value['id'],
                        'add_time'=>date('m-d H:i', $value['add_time']),
                        'result'=>$result,
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
