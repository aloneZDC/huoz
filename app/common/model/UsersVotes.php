<?php
//投票 俱乐部
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class UsersVotes extends Model
{
    static function join_votes($user_id,$pid) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $user_votes = [
            'user_id' => $user_id,
            'pid' => $pid,
            'level' => 0, //默认是0级 还是投多少直接升级
            'votes' => 0,
            'num' => 0,
            'team_num' => 0,
            'award_num' => 0,
            'recommand_total' => 0,
            'recommand_num' => 0,
            'add_time' => time(),
        ];
        $votes_id = self::insertGetId($user_votes);
        if($votes_id===false) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        //增加上级直推业绩
        if($user_votes['pid']>0) {
            //上级可能没有加入俱乐部
            $flag = self::where(['user_id'=>$user_votes['pid']])->setInc('recommand_total',1);
            if($flag===false) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }
        }

        $r['code'] = SUCCESS;
        return $r;
    }

    //检测是否可创建VIP房间 加入俱乐部  并且级别大于指定等级
    static function check_create_vip_room($user_id) {
        $votes = self::where(['user_id'=>$user_id])->find();
        if(!$votes || $votes['votes']<=0) return false;

        $votes_config = UsersVotesConfig::get_key_value();
        if(isset($votes_config['votes_create_game_min_level']) && $votes_config['votes_create_game_min_level']>0 && $votes['level']<$votes_config['votes_create_game_min_level']) return false;
        return true;
    }

    //投票详情
    static function votes_info($user_id) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $votes_config = UsersVotesConfig::get_key_value();
        if(empty($votes_config)) return $r;


        $votes_info = self::where(['user_id'=>$user_id])->field('user_id,level,votes,num,award_num,team_num,yestoday_team_num,yestoday')->find();
        if(empty($votes_info)) return $r;

        $today = date('Y-m-d');
        $yestoday_award = UsersVotesAward::where(['user_id'=>$user_id,'today'=>$today])->sum('num');
        $votes_info['yestoday_award_num'] = $yestoday_award ? $yestoday_award : 0;
        if($votes_info['yestoday']!=$today) $votes_info['yestoday_team_num'] = 0;


        $votes_info['votes_rule'] = $votes_config['votes_rule'];
        $votes_info['votes_one_num'] = $votes_config['votes_one_num'];
        $votes_info['level'] = UsersVotesConfig::getLevelName($votes_info['level']);
        $votes_info['team_num'] = intval(($votes_info['team_num']+$votes_info['num'])/$votes_info['votes_one_num']);
        $votes_info['yestoday_team_num'] = intval($votes_info['yestoday_team_num']/$votes_info['votes_one_num']);
        $votes_info['votes_max_votes'] = $votes_config['votes_max_votes'];

        //投票币种 I盾
        $votes_currency = Currency::where(['currency_mark'=>$votes_config['votes_idun_currency_mark']])->field('currency_id,currency_mark,currency_name')->find();
        if(empty($votes_currency)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }
        $votes_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_currency['currency_id']);
        if(empty($votes_currency_user)) return $r;

        //O盾
        $votes_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_odun_currency_mark']])->field('currency_id,currency_mark,currency_name')->find();
        if(empty($votes_score_currency)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $votes_score_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_score_currency['currency_id']);
        if(empty($votes_score_currency_user)) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'votes_info' => $votes_info,
            'votes_currency' => [
                [
                    'vote_key' => 'idun',
                    'currency_name' => $votes_currency['currency_name'],
                    'num' => $votes_currency_user['num'],
                ],
                [
                    'vote_key' => 'odun',
                    'currency_name' => $votes_score_currency['currency_name'],
                    'num' => $votes_score_currency_user['num'],
                ],
            ],
        ];
        return $r;
    }

    //等级详情
    static function level_info($user_id) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $user_info = Member::where(['member_id'=>$user_id])->field('nick,member_id,head')->find();
        $user_info['head'] = $user_info['head'] ?: '//io-app.oss-cn-shanghai.aliyuncs.com/images/avatar.png';

        $users_votes = self::where(['user_id'=>$user_id])->find();
        $user_info['child_count'] = $users_votes['recommand_total']; //直推人数

        $max_level = UsersVotesConfig::getMaxLevel();
        $votes_config = UsersVotesConfig::get_config();
        $user_info['level_votes'] = UsersVotesConfig::getLevelNeedVotes($users_votes['level'],$votes_config);
        $user_info['next_level'] = UsersVotesConfig::getNextLevel($users_votes['level'],$max_level);
        $user_info['next_level_votes'] = UsersVotesConfig::getLevelNeedVotes($user_info['next_level'],$votes_config);
        $user_info['next_progress'] = $user_info['next_level_votes']>0 ? keepPoint($user_info['level_votes']/$user_info['next_level_votes']*100,2) : 0;
        $user_info['level_list'] = UsersVotesConfig::getLevelNameListArray();
        $user_info['level'] = $users_votes['level'];
        $user_info['level_name'] = UsersVotesConfig::getLevelName($user_info['level']);
        $user_info['next_level'] = UsersVotesConfig::getLevelName($user_info['next_level']);
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $user_info;
        return $r;
    }

    /**
     * 使用盾进行投资
     * @param $user_id 用户ID
     * @param $votes 投票个数
     * @param $votes_type 支付方式 idun odun 后期可能组合支付
     */
    static function vote_by_dun($user_id,$votes,$votes_type,$pwd) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(!isInteger($user_id) || !isInteger($votes)) return $r;
        if(!in_array($votes_type,['idun','odun'])) return $r;

//        $check_pwd = Member::verifyPaypwd($user_id,$pwd);
//        if($check_pwd['code']!=SUCCESS) {
//            $r['message'] = $check_pwd['message'];
//            return $r;
//        }

        $votes_config = UsersVotesConfig::get_key_value();
        if(empty($votes_config) || $votes_config['votes_is_open']!=1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $pay_num = $votes;
        $votes_currency_num = $votes_score_currency_num = $dou_num = $score_num = 0;
        if($votes_type=='idun') {
            $votes_score_currency_num = $pay_num; //I盾对应积分
        } elseif ($votes_type=='odun') {
            $votes_currency_num = $pay_num; //O盾对应 IO豆
        }

        if($votes_score_currency_num>0) {
            $votes_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_idun_currency_mark']])->field('currency_id,currency_mark')->find();
            if(empty($votes_score_currency)) {
                $r['message'] = lang('lan_close');;
                return $r;
            }

            $votes_score_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_score_currency['currency_id']);
            if(empty($votes_score_currency_user) || $votes_score_currency_user['num']<$votes_score_currency_num) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }

            //计算等值的积分数量  计算业绩
            $votes_score_currency_price = CurrencyPriceTemp::get_price_currency_id($votes_score_currency['currency_id'],'CNY');
            $score_currency_price = CurrencyPriceTemp::get_price_mark($votes_config['votes_score_currency_mark'],'CNY');
            $score_num = $votes_score_currency_num * $votes_score_currency_price / $score_currency_price;
        }

        if($votes_currency_num>0) {
            //投票币种 O盾
            $votes_currency = Currency::where(['currency_mark'=>$votes_config['votes_odun_currency_mark']])->field('currency_id,currency_mark')->find();
            if(empty($votes_currency)) {
                $r['message'] = lang('lan_close');;
                return $r;
            }

            $votes_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_currency['currency_id']);
            if(empty($votes_currency_user) || $votes_currency_user['num']<$votes_currency_num) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }

            //计算等值的豆数量 不计算业绩
            $votes_currency_price = CurrencyPriceTemp::get_price_currency_id($votes_currency['currency_id'],'CNY');
            $dou_currency_price = CurrencyPriceTemp::get_price_mark($votes_config['votes_currency_mark'],'CNY');
            $dou_num = $votes_currency_num * $votes_currency_price / $dou_currency_price;
        }

        $total_num = $cur_total_num = $dou_num + $score_num;
        $total_votes = $votes;

        //需要支付的差价
        $user_votes = self::where(['user_id'=>$user_id])->find();
        if(empty($user_votes)) return $r; //改为记录 创建用户时即创建投票记录 没有投票记录返回错误

        if($user_votes) {
            $total_num += $user_votes['num'];
            $total_votes = $votes + $user_votes['votes'];
        }

        if($total_votes<=0 || $total_votes>$votes_config['votes_max_votes']) {
            $r['message'] = lang('votes_max_votes',['num'=>$votes_config['votes_max_votes']]);
            return $r;
        }

//        $cur_level = UsersVotesConfig::getLevelByVotes($total_votes);
        try{
            self::startTrans();

            //插入或者更新投票记录
            if($user_votes) {
                $user_votes_update = [
                    'num' => $total_num,
                    'votes' => $total_votes,
                    'level' => $user_votes['level'],
                ];

                $flag = self::where(['user_id'=>$user_id])->update($user_votes_update);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加上级直推业绩
                if($user_votes['pid']>0) {
                    //上级可能没有加入俱乐部
                    $flag = self::where(['user_id'=>$user_votes['pid']])->setInc('recommand_num',$cur_total_num);
                    if($flag===false) throw new Exception(lang('operation_failed_try_again'));
                }
            } else {
                $users_info = Member::where(['member_id'=>$user_id])->field('pid')->find();
                if(empty($users_info)) throw new Exception(lang('operation_failed_try_again'));

                $user_votes = [
                    'user_id' => $user_id,
                    'pid' => $users_info['member_id'],
                    'level' => 0, //默认是0级 还是投多少直接升级
                    'votes' => $total_votes,
                    'num' => $total_num,
                    'team_num' => 0,
                    'award_num' => 0,
                    'recommand_total' => 0,
                    'recommand_num' => 0,
                    'add_time' => time(),
                ];
                $votes_id = self::insertGetId($user_votes);
                if($votes_id===false) throw new Exception(lang('operation_failed_try_again'));

                //增加上级直推业绩
                if($user_votes['pid']>0) {
                    //上级可能没有加入俱乐部
                    $flag = self::where(['user_id'=>$user_votes['pid']])->update([
                        'recommand_total' => ['inc',1],
                        'recommand_num'=> ['inc',$cur_total_num],
                    ]);
                    if($flag===false) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            //增加支付记录
            $pay_id = UsersVotesPay::add_pay($user_id,$user_votes['pid'],$user_id,$total_votes,$total_num,$cur_total_num,$dou_num,$score_num);
            if(!$pay_id) throw new Exception(lang('operation_failed_try_again'));

            //扣除对应资产 O盾
            if($votes_currency_num>0) {
                //添加账本
                $flag = AccountBook::add_accountbook($user_id,$votes_currency['currency_id'],501,'votes','out',$votes_currency_num,$user_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$votes_currency_user['cu_id'],'num'=>$votes_currency_user['num']])->setDec('num',$votes_currency_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            //扣除对应资产 I盾
            if($votes_score_currency_num>0) {
                $flag = AccountBook::add_accountbook($user_id,$votes_score_currency['currency_id'],501,'votes','out',$votes_score_currency_num,$user_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$votes_score_currency_user['cu_id'],'num'=>$votes_score_currency_user['num']])->setDec('num',$votes_score_currency_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                if($score_num>0 &&isset($votes_config['votes_award_game_lock_percent']) && $votes_config['votes_award_game_lock_percent']>0) {
                    //投票送券 游戏仓
                    //赠送IO积分游戏仓
                    $io_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_score_currency_mark']])->field('currency_id,currency_mark')->find();
                    if(empty($io_score_currency)) throw new Exception(lang('operation_failed_try_again'));

                    $io_score_currency_user = CurrencyUser::getCurrencyUser($user_id,$io_score_currency['currency_id']);
                    if(empty($io_score_currency_user)) throw new Exception(lang('operation_failed_try_again'));

                    //添加锁仓记录
                    $award_num = keepPoint($score_num * $votes_config['votes_award_game_lock_percent']/100,6);
                    $flag = GameLockLog::add_log('vote_award',$user_id,$award_num,$pay_id,$score_num,$votes_config['votes_award_game_lock_percent']);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                    //增加游戏仓资产
                    $flag = CurrencyUser::where(['cu_id'=>$io_score_currency_user['cu_id'],'game_lock'=>$io_score_currency_user['game_lock']])->setInc('game_lock',$award_num);
                    if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        }catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * 使用IO豆进行投资
     * @param $user_id 用户ID
     * @param $votes 投票个数
     * @param $votes_type 支付方式 dou score 后期可能组合支付
     */
//    static function vote_by_dou($user_id,$votes,$votes_type,$pwd) {
//        $r['code']=ERROR1;
//        $r['message']=lang("parameter_error");
//        $r['result']=null;
//
//        if(!isInteger($user_id) || !isInteger($votes)) return $r;
//        if(!in_array($votes_type,['dou','score'])) return $r;
//
//        $check_pwd = Member::verifyPaypwd($user_id,$pwd);
//        if($check_pwd['code']!=SUCCESS) {
//            $r['message'] = $check_pwd['message'];
//            return $r;
//        }
//
//        $votes_config = UsersVotesConfig::get_key_value();
//        if(empty($votes_config) || $votes_config['votes_is_open']!=1) {
//            $r['message'] = lang('lan_close');;
//            return $r;
//        }
//
//        $total_num = $pay_num = $votes * $votes_config['votes_one_num'];
//        $total_votes = $votes;
//
//        //需要支付的差价
//        $user_votes = self::where(['user_id'=>$user_id])->find();
//        if(empty($user_votes)) return $r; //改为记录 创建用户时即创建投票记录 没有投票记录返回错误
//
//        if($user_votes) {
//            $total_num += $user_votes['num'];
//            $total_votes = $votes + $user_votes['votes'];
//        }
//
//        if($total_votes<=0 || $total_votes>=$votes_config['votes_max_votes']) {
//            $r['message'] = lang('votes_max_votes',['num'=>$votes_config['votes_max_votes']]);
//            return $r;
//        }
//
//        $votes_currency_num = $votes_score_currency_num = 0;
//        if($votes_type=='dou') {
//            $votes_currency_num = $pay_num;
//        } elseif ($votes_type=='score') {
//            $votes_score_currency_num = $pay_num;
//        }
//
//        if($votes_currency_num>0) {
//            //投票币种
//            $votes_currency = Currency::where(['currency_mark'=>$votes_config['votes_currency_mark']])->field('currency_id,currency_mark')->find();
//            if(empty($votes_currency)) {
//                $r['message'] = lang('lan_close');;
//                return $r;
//            }
//
//            $votes_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_currency['currency_id']);
//            if(empty($votes_currency_user) || $votes_currency_user['num']<$votes_currency_num) {
//                $r['message'] = lang('insufficient_balance');
//                return $r;
//            }
//        }
//        if($votes_score_currency_num>0) {
//            $votes_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_score_currency_mark']])->field('currency_id,currency_mark')->find();
//            if(empty($votes_score_currency)) {
//                $r['message'] = lang('lan_close');;
//                return $r;
//            }
//
//            $votes_score_currency_user = CurrencyUser::getCurrencyUser($user_id,$votes_score_currency['currency_id']);
//            if(empty($votes_score_currency_user) || $votes_score_currency_user['num']<$votes_score_currency_num) {
//                $r['message'] = lang('insufficient_balance');
//                return $r;
//            }
//        }
//
////        $cur_level = UsersVotesConfig::getLevelByVotes($total_votes);
//        try{
//            self::startTrans();
//
//            //插入或者更新投票记录
//            if($user_votes) {
//                $user_votes_update = [
//                    'num' => $total_num,
//                    'votes' => $total_votes,
//                    'level' => $user_votes['level'],
//                ];
//                $flag = self::where(['user_id'=>$user_id])->update($user_votes_update);
//                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                //增加上级直推业绩
//                if($user_votes['pid']>0) {
//                    //上级可能没有加入俱乐部
//                    $flag = self::where(['user_id'=>$user_votes['pid']])->setInc('recommand_num',$pay_num);
//                    if($flag===false) throw new Exception(lang('operation_failed_try_again'));
//                }
//            } else {
//                $users_info = Member::where(['member_id'=>$user_id])->field('pid')->find();
//                if(empty($users_info)) throw new Exception(lang('operation_failed_try_again'));
//
//                $user_votes = [
//                    'user_id' => $user_id,
//                    'pid' => $users_info['member_id'],
//                    'level' => 0, //默认是0级 还是投多少直接升级
//                    'votes' => $total_votes,
//                    'num' => $total_num,
//                    'team_num' => 0,
//                    'award_num' => 0,
//                    'recommand_total' => 0,
//                    'recommand_num' => 0,
//                    'add_time' => time(),
//                ];
//                $votes_id = self::insertGetId($user_votes);
//                if($votes_id===false) throw new Exception(lang('operation_failed_try_again'));
//
//                //增加上级直推业绩
//                if($user_votes['pid']>0) {
//                    //上级可能没有加入俱乐部
//                    $flag = self::where(['user_id'=>$user_votes['pid']])->update([
//                        'recommand_total' => ['inc',1],
//                        'recommand_num'=> ['inc',$pay_num],
//                    ]);
//                    if($flag===false) throw new Exception(lang('operation_failed_try_again'));
//                }
//            }
//
//            //增加支付记录
//            $flag = UsersVotesPay::add_pay($user_id,$user_votes['pid'],$user_id,$total_votes,$total_num,$pay_num,$votes_currency_num,$votes_score_currency_num);
//            if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//            //扣除对应资产
//            if($votes_currency_num>0) {
//                //添加账本
//                $flag = AccountBook::add_accountbook($user_id,$votes_currency['currency_id'],501,'votes','out',$votes_currency_num,$user_id,0);
//                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                $flag = CurrencyUser::where(['cu_id'=>$votes_currency_user['cu_id'],'num'=>$votes_currency_user['num']])->setDec('num',$votes_currency_num);
//                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//            }
//            if($votes_score_currency_num>0) {
//                $flag = AccountBook::add_accountbook($user_id,$votes_score_currency['currency_id'],501,'votes','out',$votes_score_currency_num,$user_id,0);
//                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                $flag = CurrencyUser::where(['cu_id'=>$votes_score_currency['cu_id'],'num'=>$votes_score_currency['num']])->setDec('num',$votes_score_currency_num);
//                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
//            }
//
//            self::commit();
//            $r['code'] = SUCCESS;
//            $r['message'] = lang('success_operation');
//        }catch (Exception $e) {
//            self::rollback();
//            $r['message'] = $e->getMessage();
//        }
//        return $r;
//    }

    //直推列表
    static function child_list($user_id, $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $votes_config = UsersVotesConfig::get_key_value();
            if(empty($votes_config)) return $r;

            $users_votes = self::where(['user_id'=>$user_id])->find();

            $where = [
                'a.pid' => $user_id,
            ];
            $field = "a.user_id,a.level,a.add_time,b.phone,b.email,a.votes,a.team_num";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "member b", "a.user_id=b.member_id", "LEFT")
                ->page($page, $rows)->order("a.add_time desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                    $value['team_votes'] = intval($value['team_num']/$votes_config['votes_one_num']);
                    $value['is_vip'] = $value['votes']>0 ? 1 : 0;
                    $value['level_name'] =  UsersVotesConfig::getLevelName($value['level']);
                    if($users_votes && $users_votes['change_child_level_open']==1) {
                        if($value['level']>=($users_votes['level']-1)){
                            $value['change_level'] = 2;
                        } else {
                            $value['change_level'] = 1;
                        }
                    } else {
                        $value['change_level'] = 2;
                    }
                    if(empty($value['phone'])) $value['phone'] = $value['email'];
                    unset($value['email']);
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

    public static function change_child_level($user_id,$child_user_id,$level){
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $users_votes = self::where(['user_id'=>$user_id])->find();
        if(empty($users_votes)) return $r;

        if($users_votes['change_child_level_open']!=1) {
            //没有授权
            $r['message'] = lang('votes_no_authority');
            return $r;
        }

        $child_users_votes = self::where(['user_id'=>$child_user_id])->find();
        if(empty($child_users_votes)) return $r;

        if($child_users_votes['pid']!=$user_id) {
            //不存在上下级关系
            $r['message'] = lang('votes_no_authority');
            return $r;
        }

        //不能小于下级原本等级
        if($level<$child_users_votes['level']) {
            $r['message'] = lang('votes_can_not_lt_child_level');
            return $r;
        }

        //不能大于等于自身等级
        if($level>=$users_votes['level']) {
            $r['message'] = lang('votes_can_not_gt_self_level');
            return $r;
        }

        $flag = self::where(['user_id'=>$child_user_id])->setField('level',$level);
        if($flag===false) {
            $r['code'] = ERROR2;
            $r['message'] = lang('operation_failed_try_again');
        } else {
            $r['message'] = lang('success_operation');
            $r['code'] = SUCCESS;
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    //钱包首页
    public static function wallets($user_id) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $votes_config = UsersVotesConfig::get_key_value();
        if(empty($votes_config)) return $r;

        $currency_mark_list = [];
        if(isset($votes_config['votes_currency_mark'])) array_push($currency_mark_list,$votes_config['votes_currency_mark']);
        if(isset($votes_config['votes_score_currency_mark'])) array_push($currency_mark_list,$votes_config['votes_score_currency_mark']);
        if(isset($votes_config['votes_idun_currency_mark'])) array_push($currency_mark_list,$votes_config['votes_idun_currency_mark']);
        if(isset($votes_config['votes_odun_currency_mark'])) array_push($currency_mark_list,$votes_config['votes_odun_currency_mark']);

        $currency_list = Currency::where(['currency_mark'=>['in',$currency_mark_list]])->field('currency_id,currency_name,currency_mark,currency_logo,exchange_switch')->select();
        if(empty($currency_list)) return $r;

        $currency_list = array_column($currency_list->toArray(),null,'currency_mark');

        $currency_user = CurrencyUser::where(['member_id'=>$user_id])->select();
        if($currency_user) $currency_user = array_column($currency_user->toArray(),null,'currency_id');

//        foreach ($currency_list as &$currency) {
//            $currency['num'] = isset($currency_user[$currency['currency_id']]) ? $currency_user[$currency['currency_id']]['num']: 0.000000;
//            if(isset($votes_config['votes_score_currency_mark']) && $currency['currency_mark']==$votes_config['votes_score_currency_mark']){
//                $currency_list[] = [
//                    'currency_id' => $currency['currency_id'],
//                    'currency_name' => lang('votes_score_lock'),
//                    'num' => isset($currency_user[$currency['currency_id']]) ? $currency_user[$currency['currency_id']]['game_lock'] : 0.000000,
//                    'type' => 'game_lock',
//                    'currency_logo' => lang('votes_score_lock_logo'),
//                ];
//            } else {
//                $currency['type'] = 'currency';
//            }
//            unset($currency['currency_mark']);
//        }

        $result = [];
        foreach ($currency_mark_list as $item) {
            if(!isset($currency_list[$item])) continue;

            $currency = $currency_list[$item];
            $currency['num'] = isset($currency_user[$currency['currency_id']]) ? $currency_user[$currency['currency_id']]['num']: 0.000000;
            $temp = false;
            $currency['type'] = 'currency';

            if(isset($votes_config['votes_score_currency_mark']) && $currency['currency_mark']==$votes_config['votes_score_currency_mark']){
                $temp = [
                    'currency_id' => $currency['currency_id'],
                    'currency_name' => lang('votes_score_lock'),
                    'num' => isset($currency_user[$currency['currency_id']]) ? $currency_user[$currency['currency_id']]['game_lock'] : 0.000000,
                    'type' => 'game_lock',
                    'currency_logo' => lang('votes_score_lock_logo'),
                    'exchange_switch' => 2,
                ];
            }
            unset($currency['currency_mark']);
            $result[] = $currency;
            if($temp) $result[] = $temp;
        }

        $r['result'] = $result;
        $r['message'] = lang('data_success');
        $r['code'] = SUCCESS;
        return $r;
    }

    //钱包首页
    public static function wallets_info($user_id,$currency_id,$type='currency')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        $currency = Currency::where(['currency_id' => $currency_id])->field('currency_id,currency_name,currency_logo,exchange_switch')->find();
        if (empty($currency)) return $r;

        $currency['num'] = 0;
        $currency_user = CurrencyUser::getCurrencyUser($user_id, $currency_id);
        if($currency_user) {
            if($type=='currency') {
                $currency['num'] = $currency_user['num'];
            } elseif ($type=='game_lock'){
                $currency['num'] = $currency_user['game_lock'];
            }
        }

        if($type=='currency') {
            $currency['exchange_type'] = 'num';
            $currency['dui_switch'] = 2;
            $convert_config = ConvertConfig::where(['currency_id' => $currency['currency_id']])->find();
            if ($convert_config) $currency['dui_switch'] = 1;
        } elseif($type=='game_lock') {
            //IO券暂时不允许兑换
            $currency['exchange_type'] = 'game_lock';
            $currency['exchange_switch'] = 2;
            $currency['dui_switch'] = 2;
            $currency['currency_name'] = lang('votes_score_lock');
        }

        $r['result'] = $currency;
        $r['message'] = lang('data_success');
        $r['code'] = SUCCESS;
        return $r;
    }
}