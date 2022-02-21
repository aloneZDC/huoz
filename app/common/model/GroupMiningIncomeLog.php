<?php
namespace app\common\model;
use think\Exception;
use think\Model;

/**
 * 拼团挖矿记录表
 * Class GroupMiningIncomeLog
 * @package app\common\model
 */
class GroupMiningIncomeLog extends Model
{
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function member() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,ename,nick,email,phone');
    }

    // 奖励记录
    public static function getList($user_id, $type, $page = 1, $rows = 10)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang("not_data"),
            'result' => null
        ];
        if (isInteger($user_id) && isInteger($type) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['user_id' => $user_id, 'type' => $type];
            $list = self::with(['currency'])->where($where)->page($page, $rows)->order("add_time desc")->select();

            if (!empty($list)) {
                $log_list = [];
                $type_name = [1 => lang('income_coupon'), 2 => lang('income_fee'), 3 => lang('income_gold'),
                    4 => lang('income_dividend'), 5 => lang('income_airdrop'), 6 => lang('income_open_up')];
                foreach ($list as $value) {
                    $isDetail = 0;//是否有详情 0-无 1-有
                    $detail = [];
                    if ($value['type'] == 1) {//1-拼团券奖励
                        /*$detail1 = GroupMiningIncomeDetail::where(['type'=>1,'income_id'=>$value['id']])->sum('reward_num') ? : 0;
                        $detail[] = [
                            'detail_name'=>lang('income_coupon_detail1'),
                            'num'=>floattostr($detail1),
                        ];*/
                        $isDetail = 1;
                    }
                    else if ($value['type'] == 2) {//2-矿工费奖励
                        /*$detail1 = GroupMiningIncomeDetail::where(['type'=>2,'income_id'=>$value['id']])->sum('reward_num') ? : 0;
                        $detail[] = [
                            'detail_name'=>lang('income_fee_detail1'),
                            'num'=>floattostr($detail1),
                        ];
                        $detail2 = GroupMiningIncomeFeeDetail::where(['income_id'=>$value['id']])->sum('reward_num') ? : 0;
                        $detail[] = [
                            'detail_name'=>lang('income_fee_detail2'),
                            'num'=>floattostr($detail2),
                        ];*/
                        $isDetail = 1;
                    }
                    else if ($value['type'] == 3) {//3-拼团金奖励
                        /*$detail1 = GroupMiningIncomeDetail::where(['type'=>3,'income_id'=>$value['id']])->sum('reward_num') ? : 0;
                        $detail[] = [
                            'detail_name'=>lang('income_gold_detail1'),
                            'num'=>floattostr($detail1),
                        ];
                        $detail2 = GroupMiningIncomeGoldDetail::where(['income_id'=>$value['id']])->sum('reward_num') ? : 0;
                        $detail[] = [
                            'detail_name'=>lang('income_gold_detail2'),
                            'num'=>floattostr($detail2),
                        ];*/
                        $isDetail = 1;
                    }
                    $log_list[] = [
                        'id'=>$value['id'],
                        'num'=>$value['num'],
                        'type'=>$value['type'],
                        'type_name'=>$type_name[$value['type']],
                        'currency_name'=>$value['currency']['currency_name'],
                        'add_time'=>date('Y-m-d H:i', $value['add_time']),
                        'is_detail'=>$isDetail,
                        //'detail'=>$detail,
                    ];
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $log_list;
            }
            else {
                $r['message'] = lang("lan_No_data");
            }
        }
        else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }

    // 奖励详情
    public static function getDetail($user_id, $income_id)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang("not_data"),
            'result' => null
        ];
        if (isInteger($user_id) && isInteger($income_id)) {

            $find = self::with(['currency'])->where('id', $income_id)->order("add_time desc")->find();

            if (!empty($find)) {

                $type_name = [1 => lang('income_coupon'), 2 => lang('income_fee'), 3 => lang('income_gold'),
                    4 => lang('income_dividend'), 5 => lang('income_airdrop'), 6 => lang('income_open_up')];

                $detail = [];
                if ($find['type'] == 1) {//1-拼团券奖励
                    $detail1 = GroupMiningIncomeDetail::where(['type'=>1,'income_id'=>$find['id']])->sum('reward_num') ? : 0;
                    $detail[] = [
                        'detail_name'=>lang('income_coupon_detail1'),
                        'num'=>floattostr($detail1),
                    ];
                }
                else if ($find['type'] == 2) {//2-矿工费奖励
                    $detail1 = GroupMiningIncomeDetail::where(['type'=>2,'income_id'=>$find['id']])->sum('reward_num') ? : 0;
                    $detail[] = [
                        'detail_name'=>lang('income_fee_detail1'),
                        'num'=>floattostr($detail1),
                    ];
                    $detail2 = GroupMiningIncomeFeeDetail::where(['income_id'=>$find['id']])->sum('reward_num') ? : 0;
                    $detail[] = [
                        'detail_name'=>lang('income_fee_detail2'),
                        'num'=>floattostr($detail2),
                    ];
                }
                else if ($find['type'] == 3) {//3-拼团金奖励
                    $detail1 = GroupMiningIncomeDetail::where(['type'=>3,'income_id'=>$find['id']])->sum('reward_num') ? : 0;
                    $detail[] = [
                        'detail_name'=>lang('income_gold_detail1'),
                        'num'=>floattostr($detail1),
                    ];
                    $detail2 = GroupMiningIncomeGoldDetail::where(['income_id'=>$find['id']])->sum('reward_num') ? : 0;
                    $detail[] = [
                        'detail_name'=>lang('income_gold_detail2'),
                        'num'=>floattostr($detail2),
                    ];
                }
                $result = [
                    'num'=>$find['num'],
                    'type'=>$find['type'],
                    'type_name'=>$type_name[$find['type']],
                    'currency_name'=>$find['currency']['currency_name'],
                    'detail'=>$detail,
                ];
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $result;
            }
            else {
                $r['message'] = lang("lan_No_data");
            }
        }
        else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }
}
