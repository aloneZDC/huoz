<?php
/**
 * 红包项目 - 节点奖励
 * 考核：
 * 1. 有10个直推，每个直推资产大于100
 * 2. 本人资产大于500
 * 3. 3个部门 1个大于5W 一个大于3W 一个大于2W
 *
 * 奖励（有级差）：
 * 下级有N个人资产大于100, 奖励数量 N*奖励基数
 * N小于200 奖励基数0.5
 * N小于500 奖励基数1
 * N小于1000 奖励基数1.5
 * N小于5000 奖励基数2
 */
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoNodeAward extends Model
{
    //获取奖励总数量
    static function getSumNum($user_id) {
        return self::where(['user_id'=>$user_id])->sum('num');
    }

    //用户
    static function award_level_info($user_id) {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");

        //该用户所有下级
        $config = HongbaoConfig::get_key_value();
        $currency = Currency::where(['currency_mark'=>$config['node_currency_mark']])->find();

        $all_child = MemberBind::where(['member_id'=>$user_id])->count();
        $all_award = self::getSumNum($user_id);
        $currency_id = $currency ? $currency['currency_id'] : 0;
        $level_info = HongbaoNodeCurrencyUser::getYestdayLevel($user_id,$currency_id,$config);
        $r['result'] = [
            'all_child' => $all_child,
            'all_award' => $all_award,
            'currency_name' => $currency ? $currency['currency_name'] : '',
            'level' => $level_info['level'],
            'level_name' => $level_info['level_name'],
        ];
        return $r;
    }

    static function award($user_id,$currency_id,$award_num,$child_num,$child_count,$all_child_count) {
        $currency_user = CurrencyUser::getCurrencyUser($user_id,$currency_id);
        if(empty($currency_user)) return;

        //释放本人
        try{
            self::startTrans();
            $insert_id = self::insertGetId([
                'user_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $award_num,
                'create_time' => time(),
                'child_num' => $child_num,
                'child_count' => $child_count,
                'all_child_count' => $all_child_count,
            ]);
            if(!$insert_id) throw new Exception("奖励记录失败");

            //添加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],1200,'node_award','in',$award_num,$insert_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$award_num);
            if(!$flag) throw new Exception("增加资产失败");

            self::commit();
            return true;
        }catch (Exception $e) {
            self::rollback();
            Log::write($currency_user['member_id']."节点奖励错误:".$e->getMessage());
            return false;
        }
    }

    static function get_list($user_id,$page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $where = [
                'a.user_id' => $user_id,
            ];

            $field = "a.id,a.num,a.create_time,a.all_child_count,b.currency_name,b.currency_logo";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['create_time'] = date('m-d H:i',$value['create_time']);
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

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
