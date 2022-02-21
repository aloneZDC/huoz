<?php
//投票 俱乐部 支付记录
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class UsersVotesAward extends Model
{
    //级差奖励
    static function award($pay_info,$votes_config,$today_config,$votes_currency,$votes_score_currency) {
        if($pay_info['score_num']<=0) return true;

        $votes_info = UsersVotes::where(['user_id'=>$pay_info['user_id']])->find();
        if(empty($votes_info)) return false;

        $max_level = UsersVotesConfig::getMaxLevel();
        //自己是做高级
        if($votes_info['level']>=$max_level) return false;

        $cur_level = 0;
        $has_percent = 0; //已经分配出去的比例
        while ($cur_level<$max_level) {
            //获取离我最近的 级别比我高的 投过票的上级
            $pid_info = MemberBind::field('a.member_id,b.level,b.num,b.award_num')->alias('a')
                ->join(config('database.prefix').'users_votes b','a.member_id=b.user_id','LEFT')
                ->where([ 'a.child_id'=>$votes_info['user_id'],'b.votes'=>['gt',0],'b.level'=>['gt',$cur_level] ])
                ->order('a.level asc')->find();
            if(empty($pid_info)) break;

            $cur_level = $pid_info['level'];
            if(!isset($votes_config['level'.$pid_info['level']])) break;

            $cur_config = $votes_config['level'.$pid_info['level']];
            //实际奖励比例
            $percent = $cur_config['uvs_percent']-$has_percent;
            if($percent<=0) continue;

            $has_percent = $cur_config['uvs_percent'];
            $award_num = keepPoint($pay_info['score_num'] * $percent/100,6);
            if($votes_config['votes_max_multiple']['uvs_value']>0) {
                $award_yu = keepPoint($pid_info['num'] * $votes_config['votes_max_multiple']['uvs_value'] - $pid_info['award_num'],6); //封顶值 - 已获得总收益
                $award_num = min($award_num,$award_yu);
            }
            if($award_num>=0.000001) {
                self::award_user($pid_info,$award_num,$percent,$pay_info,$cur_config,$today_config,$votes_currency,$votes_score_currency);
            }
        }
    }

    /**
     * @param $pid_info 上级信息 ID及当前等级
     * @param $award_num 奖励数量
     * @param $percent 比例
     * @param $pay_info 下级支付记录
     * @param $cur_config 当前等级配置
     * @param $today_config 日期
     * @param $votes_currency IO豆币种信息
     * @param $votes_score_currency IO积分币种信息
     * @param $votes_game_release_percent U转王游戏冻结币释放比例
     * @return bool
     * @throws \think\exception\PDOException
     */
    static function award_user($pid_info,$award_num,$percent,$pay_info,$cur_config,$today_config,$votes_currency,$votes_score_currency) {
        if($cur_config['uvs_available_percent']>100) return false;

        //根据配置 一部分到IO积分 一部分到IO积分锁仓即游戏仓 默认100%到积分
        $votes_score_currency_num  = keepPoint($award_num * $cur_config['uvs_available_percent']/100,6);
        $votes_currency_num = keepPoint($award_num-$votes_score_currency_num,6);

        try{
            self::startTrans();
            //添加奖励记录
            $log_id = self::insertGetId([
                'user_id' => $pid_info['member_id'],
                'num' => $award_num,
                'io_num' => $votes_currency_num,
                'score_num' => $votes_score_currency_num,
                'user_level' => $pid_info['level'],
                'percent' => $percent,
                'third_id' => $pay_info['id'],
                'third_user_id' => $pay_info['user_id'],
                'third_base_num' => $pay_info['score_num'],
                'today' => $today_config['today'],
                'add_time' => time(),
            ]);
            if(!$log_id) throw new Exception("插入奖励记录失败");

            //增加投票获得的奖励总量
            $flag = UsersVotes::where(['user_id'=>$pid_info['member_id']])->setInc('award_num',$award_num);
            if(!$flag) throw new Exception("更新奖励总量失败");

            //添加IO豆资产
//            if($votes_currency_num>0) {
                //从到IO豆 到IO资产游戏仓
//                $votes_currency_user = CurrencyUser::getCurrencyUser($pid_info['member_id'],$votes_currency['currency_id']);
//                if(empty($votes_currency_user)) throw new Exception("获取IO豆币种失败");
//
//                //添加账本
//                $flag = AccountBook::add_accountbook($pid_info['member_id'],$votes_currency['currency_id'],502,'votes_award','in',$votes_currency_num,$log_id,0);
//                if(!$flag) throw new Exception("IO豆添加账本失败");
//
//                $flag = CurrencyUser::where(['cu_id'=>$votes_currency_user['cu_id'],'num'=>$votes_currency_user['num']])->setInc('num',$votes_currency_num);
//                if(!$flag) throw new Exception(lang('IO豆扣除资产失败'));
//            }

            //添加IO积分资产
            if($votes_score_currency_num>0) {
                $votes_score_currency_user = CurrencyUser::getCurrencyUser($pid_info['member_id'],$votes_score_currency['currency_id']);
                if(empty($votes_score_currency_user)) throw new Exception("获取IO积分币种失败");

                //添加账本
                $flag = AccountBook::add_accountbook($pid_info['member_id'],$votes_score_currency['currency_id'],502,'votes_award','in',$votes_score_currency_num,$log_id,0);
                if(!$flag) throw new Exception("IO积分添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$votes_score_currency_user['cu_id'],'num'=>$votes_score_currency_user['num']])->setInc('num',$votes_score_currency_num);
                if(!$flag) throw new Exception("IO积分扣除资产失败");

                //改为到IO积分锁仓即游戏仓
                if($votes_currency_num>0) {
                    //添加锁仓记录
                    $flag = GameLockLog::add_log('award',$pid_info['member_id'],$votes_currency_num,$log_id,$award_num,(100-$cur_config['uvs_available_percent']));
                    if(!$flag) throw new Exception("IO积分游戏仓添加失败");
                    //增加游戏仓资产
                    $flag = CurrencyUser::where(['cu_id'=>$votes_score_currency_user['cu_id'],'game_lock'=>$votes_score_currency_user['game_lock']])->setInc('game_lock',$votes_currency_num);
                    if(!$flag) throw new Exception("IO积分扣除资产失败");
                }
            }

            self::commit();
        } catch(Exception $e) {
            self::rollback();
            Log::write("投票俱乐部奖励失败:".$e->getMessage());
        }
    }

    static function get_list($user_id, $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $votes_config = UsersVotesConfig::get_key_value();
            if(empty($votes_config)) return $r;
            $votes_currency = Currency::where(['currency_mark'=>$votes_config['votes_currency_mark']])->field('currency_id,currency_name')->find();
            if(empty($votes_currency)) return $r;

            $votes_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_score_currency_mark']])->field('currency_id,currency_name')->find();
            if(empty($votes_score_currency)) return $r;

            $where = [
                'a.user_id' => $user_id,
            ];
            $field = "a.num,a.user_level,a.add_time,a.io_num,a.score_num";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
//                    $value['io_currency_name'] = $votes_currency['currency_name'];
                    $value['title'] = lang('votes_award2');
                    $value['io_currency_name'] = lang('votes_score_lock');
                    $value['score_currency_name'] = $votes_score_currency['currency_name'];
                    $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                    $value['user_level'] =  UsersVotesConfig::getLevelName($value['user_level']);
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