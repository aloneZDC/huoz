<?php
//U转王 锁仓释放记录
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class GameLockLog extends Model
{
    /**
     * @param string $type 类型 lock锁仓 release释放 award奖励到游戏仓 vote_award投票赠送 shop购物扣除 transfer_from_wallet钱包转入 reg_award注册赠送
     * @param int $member_id 用户ID
     * @param double $num 数量
     * @param int $third_id 第三方记录ID
     * @param int $base_num 释放基础数量
     * @param int $percent 释放比例
     * @throws
     * @return int|string
     */
    static function add_log($type,$member_id,$num,$third_id,$base_num=0,$percent=0){
        return self::insertGetId([
            'type' => $type,
            'member_id' => $member_id,
            'num' => $num,
            'third_id' => $third_id,
            'base_num' => $base_num,
            'percent' => $percent,
            'add_time' => time(),
        ]);
    }

    static function game_release($pay_info,$votes_score_currency,$votes_config) {
        if($pay_info['score_num']<=0) return true;

        $votes_game_release_percent = $votes_config['votes_game_release_percent']['uvs_value'];
        $votes_game_release_level = $votes_config['votes_game_release_level']['uvs_value'];
        if($votes_game_release_percent>0 && $votes_game_release_level>0) {
            //已经释放过
            $is_release = self::where(['type'=>'release','third_id'=>$pay_info['id']])->find();
            if(!empty($is_release)) return true;

            //网上数10层 释放游戏锁仓
            $pid_list = MemberBind::field('a.member_id,a.level')->alias('a')
                ->where([ 'a.child_id'=>$pay_info['user_id'],'a.level'=>['elt',$votes_game_release_level]])
                ->order('a.level asc')->select();
            foreach ($pid_list as $pid_info){
                self::release_game_lock($pid_info,$pay_info,$votes_score_currency,$votes_game_release_percent);
            }
        }
    }

    static function release_game_lock($pid_info,$pay_info,$votes_score_currency,$votes_game_release_percent) {
        $currency_user = CurrencyUser::getCurrencyUser($pid_info['member_id'],$votes_score_currency['currency_id']);
        if(empty($currency_user) || $currency_user['game_lock']<=0) return false;

        //释放当日拿到奖励的5%
        $award_num = UsersVotesAward::where(['user_id'=>$pid_info['member_id'],'third_id'=>$pay_info['id']])->find();
        if(empty($award_num)) return false;

        $release_num = keepPoint($award_num['num'] * $votes_game_release_percent/100,6);
        $release_num = min($release_num,$currency_user['game_lock']);
        if($release_num<0.000001) return true;

        try{
            self::startTrans();
            //增加释放记录
            $log_id = self::add_log('release',$pid_info['member_id'],$release_num,$pay_info['id'],$award_num['num'],$votes_game_release_percent);
            if(!$log_id) throw new Exception("添加释放记录失败");

            //增加账本  503 game_release
            $flag = AccountBook::add_accountbook($pid_info['member_id'],$votes_score_currency['currency_id'],503,'game_release','in',$release_num,$log_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            //增加可用资产 减少冻结资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'game_lock'=>$currency_user['game_lock']])->update([
                'num' => ['inc',$release_num],
                'game_lock' => ['dec',$release_num],
            ]);
            if(!$flag) throw new Exception("增加资产失败");

            self::commit();
        }catch (Exception $e) {
            self::rollback();
            Log::write("游戏锁仓释放失败:".$e->getMessage());
        }
    }

    //类型 lock锁仓 release释放 award奖励到游戏仓 vote_award投票赠送, shop购物扣除
    static function get_list($user_id,$income_type='',$page=1,$rows=10,$is_game=false) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (isInteger($user_id) && $rows <= 100) {
            $currency_user_num = 0;
            $votes_config = UsersVotesConfig::get_key_value();
            $currency_price = 0;
            if(isset($votes_config['votes_score_currency_mark'])) {
                $currency = Currency::where(['currency_mark'=>$votes_config['votes_score_currency_mark']])->field('currency_id,currency_name,currency_mark,currency_logo')->find();
                if($currency) {
                    $currency_user = CurrencyUser::getCurrencyUser($user_id,$currency['currency_id']);
                    if($currency_user) $currency_user_num = $currency_user['game_lock'];

                    $currency_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
                }
            }

            $where = [
                'a.member_id' => $user_id
            ];
            if(!empty($income_type)) {
                if($income_type=='out'){
                    $where['a.type'] = [ 'in',['release','shop'] ];
                } else {
                    $where['a.type'] = [ 'in',['lock','award','vote_award','transfer_from_wallet','reg_award'] ];
                }
            }
            $field = "a.num,a.add_time,a.type";
            $list = self::field($field)->alias('a')->where($where)->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
                    $value['title'] = '';
                    if ($value['type'] == 'lock') {
                        $value['title'] = lang('game_lock'); //U赚王 锁仓
                    } elseif ($value['type'] == 'award') {
                        $value['title'] = lang('votes_pid_award'); //下级奖励
                    } elseif ($value['type'] == 'vote_award') {
                        $value['title'] = lang('votes_game_lock_award');
                    } elseif ($value['type'] == 'release') {
                        $value['title'] = lang('game_release');
                    } elseif ($value['type'] == 'shop') {
                        $value['title'] = lang('shopping');
                    } elseif ($value['type'] == 'transfer_from_wallet') {
                        $value['title'] = lang('transfer_from_wallet');
                    } elseif ($value['type'] == 'reg_award') {
                        $value['title'] = lang('reg_award');
                    }

                    $value['currency_name'] = lang('votes_score_lock');
                    $value['currency_logo'] = lang('votes_score_lock_logo');
                    if(in_array($value['type'],['lock','award','vote_award','transfer_from_wallet','reg_award'])) {
                        $value['num'] = '+'.$value['num'];
                        $value['number_type'] = 1;
                    } elseif(in_array($value['type'],['release','shop'])) {
                        $value['num'] = '-'.$value['num'];
                        $value['number_type'] = 2;
                    }
                }
            }

            $r['code'] = SUCCESS;
            if($is_game && empty($list)) {
                $r['code'] = ERROR1;
                $r['message'] = lang('not_data');
            }
            $r['message'] = lang("data_success");
            $r['result'] = [
                'info' => [
                    'num' => $currency_user_num,
                    'currency_name' => lang('votes_score_lock'),
                    'cny_price' => keepPoint($currency_price * $currency_user_num,2),
                ],
                'list' => $list,
            ];
        }
        return $r;
    }

    static function reg_award($user_id,$pid) {
        $config = Config::where(['key'=>['like','%io_reg_award%'] ])->select();
        if(empty($config)) return true;
        $config = array_column($config,'value','key');

        $is_open = isset($config['io_reg_award_open']) ? $config['io_reg_award_open'] : 2;
        $award_currency_mark = isset($config['io_reg_award_currency_mark']) ? $config['io_reg_award_currency_mark'] : '';
        $award_num = isset($config['io_reg_award_num']) ? $config['io_reg_award_num'] : 0;
        $award_percent = [
            'percent1' => isset($config['io_reg_award_percent1']) ? $config['io_reg_award_percent1'] : 0,
            'percent2' => isset($config['io_reg_award_percent2']) ? $config['io_reg_award_percent2'] : 0,
            'percent3' => isset($config['io_reg_award_percent3']) ? $config['io_reg_award_percent3'] : 0,
        ];
        if($is_open!=1 || $award_currency_mark=='' || $award_num<=0) return true;

        $award_currency = Currency::where(['currency_mark'=>$award_currency_mark])->field('currency_id,currency_mark')->find();
        if(!$award_currency) return true;

        try{
            $users_currency = CurrencyUser::getCurrencyUser($user_id,$award_currency['currency_id']);
            if(!$users_currency) throw new Exception("获取资产错误");

            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id']])->setInc('game_lock',$award_num);
            if(!$flag) throw new Exception("添加资产错误");

            //添加io券记录
            $flag = GameLockLog::add_log('reg_award',$user_id,$award_num,$user_id,$award_num,100);
            if(!$flag) throw new Exception("添加io券资产错误");

            if($pid>0) {
                for ($count=1;$count<=3;$count++) {
                    $percent = $award_percent['percent'.$count];
                    if($percent<=0) continue;

                    $cur_award_num = keepPoint($award_num*$percent/100,6);
                    if($cur_award_num<0.000001) continue;

                    $users_parent = Member::where(['member_id'=>$pid])->field('member_id,pid')->find();
                    if(!$users_parent) throw new Exception("用户不存在");

                    $users_currency = CurrencyUser::getCurrencyUser($users_parent['member_id'],$award_currency['currency_id']);
                    if(!$users_currency) throw new Exception("获取资产错误");

                    $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id']])->setInc('game_lock',$cur_award_num);
                    if(!$flag) throw new Exception("添加资产错误");

                    //添加io券记录
                    $flag = GameLockLog::add_log('reg_award',$users_parent['member_id'],$cur_award_num,$user_id,$award_num,$percent);
                    if(!$flag) throw new Exception("添加io券资产错误");

                    $pid = $users_parent['pid'];
                    if($pid<=0) break;
                }
            }
            return true;
        } catch(Exception $e) {
            Log::write($e->getMessage());
            return false;
        }
    }
}