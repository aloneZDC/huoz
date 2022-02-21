<?php
//投票 俱乐部 支付记录
namespace app\common\model;

use think\Model;

class UsersVotesPay extends Model
{
    static function add_pay($user_id,$pid,$pay_id,$total_votes,$total_num,$pay_number,$io_num,$score_num) {
        return self::insertGetId([
            'user_id' => $user_id,
            'pid' => $pid,
            'pay_id' => $pay_id,
            'total_votes' => $total_votes,
            'total_num' => $total_num,
            'pay_number' => $pay_number,
            'io_num' => $io_num,
            'score_num' => $score_num,
            'add_time' => time(),
        ]);
    }

    //直推列表
    static function pay_list($user_id, $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $votes_config = UsersVotesConfig::get_key_value();
            if(empty($votes_config)) return $r;

            $where = [
                'a.user_id' => $user_id,
            ];
            $field = "a.pay_number,a.add_time,a.io_num,a.score_num";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                    $value['type'] = $value['io_num']>0 ? 'dou' : 'score';
                    $value['votes'] = intval($value['pay_number']/$votes_config['votes_one_num']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
}