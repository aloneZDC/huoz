<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class BossPlanStep extends Base {
    //获取激活列表
    public function getActiveStepList($member_id,$lang='tc') {
        $return = [];

        $current = ['votes'=>0,'number'=>0,'min_votes'=>0,'max_votes'=>0];

        $time = time();
        $boss_number = 0;
        $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->find();
        if($boss_plan_info) {
            $boss_number = $boss_plan_info['num'];
            $current = ['votes'=>$boss_plan_info['votes'],'number'=>$boss_plan_info['num'],'min_votes'=>0,'max_votes'=>0];
        }

        $stepList = Db::name('boss_plan_step')->field('step_id,name_'.$lang.' as name,start_time,number,min_votes,max_votes,is_open,start_time')->select();
        if($stepList) {
            foreach ($stepList as $key => $value) {
                if($value['is_open']!=1) {
                    $value['status'] = 0;
                } elseif ($value['start_time']>0 && $time<$value['start_time']) {
                    $value['status'] = 0; //未开放
                } else {
                    $value['status'] = 1; //开放中
                }

                if($value['status']==1){
                    $current['min_votes'] = min($value['min_votes'],$current['min_votes']);
                    $current['max_votes'] = max($value['max_votes'],$current['max_votes']);
                }
                $return[$value['step_id']] = $value;
            }
        }

        if($current['min_votes']<$current['max_votes']) {
            $current['min_votes'] += 1;
        } else {
            $current['min_votes'] = $current['max_votes'] = $current['votes'];
        }
        return $current;
    }

    /**
     *认购列表
     */
    public function getStepList($member_id,$lang='tc') {
        $return = [];
        $current = ['votes'=>0,'number'=>0,'min_votes'=>0,'max_votes'=>0];

        $time = time();
        $boss_number = 0;
        $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->find();
        if($boss_plan_info) {
            $boss_number = $boss_plan_info['num'];
            $current = ['votes'=>$boss_plan_info['votes'],'number'=>$boss_plan_info['num'],'min_votes'=>$boss_plan_info['votes'],'max_votes'=>0];
        }

        $stepList = Db::name('boss_plan_step')->field('step_id,name_'.$lang.' as name,start_time,number,min_votes,max_votes,is_open,start_time')->select();
        if($stepList) {
            foreach ($stepList as $key => $value) {
                if($value['is_open']!=1) {
                    $value['status'] = 0;
                } elseif ($value['start_time']>0 && $time<$value['start_time']) {
                    $value['status'] = 0; //未开放
                } else {
                    $value['status'] = 1; //开放中
                }

                if($value['status']==1){
                    $current['max_votes'] = max($value['max_votes'],$current['max_votes']);
                }

                $return[$value['step_id']] = $value;
            }

            if($current['min_votes']<$current['max_votes']) {
                $current['min_votes'] += 1;
            } else {
                $current['min_votes'] = $current['max_votes'] = $current['votes'];
            }
        }

        return $current;
    }

    //检测是否合法 votes 票数
    public function step_check($votes) {
        $time = time();
        $votes = intval($votes);
        if($votes<=0) return lang('lan_votes_lt_0');

        $step_list = Db::name('boss_plan_step')->where('is_open=1 and start_time<'.$time.' and  (min_votes<='.$votes.' or max_votes>='.$votes.')')->select();
        if(!$step_list) return lang("illegal_votes");

        $result = current($step_list);
        return ['number'=>$result['number'],'votes'=>$votes];
    }

    /**
     *认购--钱包支付
     *@param member_id 用户ID
     *@param child_id 下级ID, 个人认购为0 为下级激活为下级用户ID
     *@param config 配置
     *@param xrp_num 消耗的XRP数量
     *@param xrpz_num 消耗的瑞波钻数量
     *@param xrpj_num 消耗的瑞波金数量
     */
    public function buyByNum($member_id,$step_info,$config,$xrp_num,$xrpz_num,$xrpj_num) {
        $currency_id = Db::name('currency')->field('currency_id')->where(['currency_mark'=>'XRP'])->value('currency_id');
        $currency_id = intval($currency_id);

        try{
            Db::startTrans();
            $boss_plan = Db::name('boss_plan_info')->lock(true)->where(['member_id'=>$member_id])->find();
            if(!$boss_plan) throw new Exception(lang('lan_network_busy_try_again'));
            if($step_info['votes']<=$boss_plan['votes']) throw new Exception(lang('lan_network_busy_try_again'));

            $pay_number = $step_info['number'] * ($step_info['votes']-$boss_plan['votes']);
            //瑞波钻+钱包组合支付
            if($xrpz_num>0) {
                if($config['boss_plan_buy_xrpz_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                //最大的瑞波钻比例
                $max_xrpz_num = $pay_number * $config['boss_plan_buy_xrpz']/100;
                if($xrpz_num>$max_xrpz_num) throw new Exception(lang('lan_boss_plan_buy_xrpz_max_num').$config['boss_plan_buy_xrpz'].'%'); //超出比例

                $member_xrpz_num = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->value('xrpz_num');
                if(!$member_xrpz_num || $member_xrpz_num<$xrpz_num) throw new Exception(lang('lan_xrpz_insufficient_balance')); //XRP+ 余额不足

                $xrp_num = $pay_number - $xrpz_num;
                $xrpj_num = 0;
            } elseif($xrpj_num>0) {
                if($config['boss_plan_buy_xrpj_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                //瑞波金+钱包组合支付
                $max_xrpj_num = $pay_number * $config['boss_plan_buy_xrpj']/100;
                if($xrpj_num>$max_xrpj_num) throw new Exception(lang('lan_boss_plan_buy_xrpj_max_num').$config['boss_plan_buy_xrpj'].'%'); //超出比例

                $member_xrpj_num = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->value('xrpj_num');
                if(!$member_xrpj_num || $member_xrpj_num<$xrpj_num) throw new Exception(lang('lan_xrpj_insufficient_balance')); //XRPG 余额不足

                $xrp_num = $pay_number - $xrpj_num;
                $xrpz_num = 0;
            } else {
                if($config['boss_plan_buy_xrp_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                $xrp_num = $pay_number;
                $xrpz_num = 0;
                $xrpj_num = 0;
            }

            $user_num = model('CurrencyUser')->getNum($member_id, $currency_id, 'num', true); //currency_id 8 XRP
            if($user_num<$xrp_num) throw new Exception(lang('lan_insufficient_balance'));

            //改变用户入金金额
            $total_step_num = $step_info['votes'] * $step_info['number'];
            $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->update([
                'votes' => $step_info['votes'],
                'num' => $total_step_num,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //添加认购记录
            $buy_id = Db::name('boss_plan_buy')->insertGetId([
                'type' => 1,
                'member_id' => $member_id,
                'pid' => $boss_plan['pid'],
                'pay_id' => $member_id,
                'votes' => $step_info['votes'],
                'total' => $total_step_num,
                'pay_number' => $pay_number,
                'xrp_num' => $xrp_num,
                'xrpz' => $xrpz_num,
                'xrpj' => $xrpj_num,
                'add_time' => time(),
                'status' => 1,
            ]);
            if($buy_id===false) throw new Exception(lang('lan_network_busy_try_again'));

            //为后台任务加记录
            // $flag = Db::name('boss_level_task')->insertGetId([
            //     'member_id' => $member_id,
            //     'money' => $pay_number,
            //     'add_time' => time(),
            // ]);
            // if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));


            if($xrp_num>0) {
                //增加财务日志
                $result = Model('AccountBook')->addLog([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'type'=> 19,
                    'content' => 'lan_boss_plan_buy',
                    'number_type' => 2,
                    'number' => $xrp_num,
                    'fee' => 0,
                    'third_id' => $member_id,
                    'name' => $step_info['votes'],
                ]);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                //扣除资产
                $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency_id])->setDec('num',$xrp_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            if($xrpz_num>0) {
                //扣除资产
                $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setDec('xrpz_num',$xrpz_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                //增加瑞波钻记录
                $insert_id = Db::name('xrp_log')->insertGetId([
                    'l_member_id' => $member_id,
                    'l_value' => '-'.$xrpz_num,
                    'l_time' => time(),
                    'l_title' => $member_id,
                    'l_votes' => $step_info['votes'],
                    'l_type' => 12,
                    'l_type_explain' => 'lan_accountbook_boss_plan_buy',
                ]);
                if(!$insert_id) throw new Exception(lang('lan_network_busy_try_again'));
            }

            if($xrpj_num>0) {
                $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setDec('xrpj_num',$xrpj_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $insert_id = Db::name('xrpj_log')->insertGetId([
                    'l_member_id' => $member_id,
                    'l_value' => '-'.$xrpj_num,
                    'l_time' => time(),
                    'l_title' => $member_id,
                    'l_votes' => $step_info['votes'],
                    'l_type' => 12,
                    'l_type_explain' => 'lan_accountbook_boss_plan_buy',
                ]);
                if(!$insert_id) throw new Exception(lang('lan_network_busy_try_again'));
            }

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     *激活下级--钱包支付--直接激活不用用户确认
     *@param member_id 用户ID
     *@param child_id 下级ID
     *@param child_pid 下级ID绑定的直接父ID
     *@param step_info 激活选项详情
     *@param config['pid_add_time'] 上级激活用户加奖励过期时间（单位天）
     *@param xrp_num 消耗的XRP数量
     *@param xrpz_num 消耗的瑞波钻数量
     *@param xrpj_num 消耗的瑞波金数量
     *@param
     */
    public function activeChildByNumConfirm($member_id,$child_id,$child_pid,$step_info,$config,$xrp_num,$xrpz_num,$xrpj_num) {
        $currency_id = Db::name('currency')->field('currency_id')->where(['currency_mark'=>'XRP'])->value('currency_id');
        $currency_id = intval($currency_id);

        try{
            Db::startTrans();
            $pay_number = $step_info['number'] * $step_info['votes'];

            //瑞波钻+钱包组合支付
            if($xrpz_num>0) {
                if($config['boss_plan_buy_xrpz_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                //最大的瑞波钻比例
                $max_xrpz_num = $pay_number * $config['boss_plan_buy_xrpz']/100;
                if($xrpz_num>$max_xrpz_num) throw new Exception(lang('lan_boss_plan_buy_xrpz_max_num').$config['boss_plan_buy_xrpz'].'%'); //超出比例

                $member_xrpz_num = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->value('xrpz_num');
                if(!$member_xrpz_num || $member_xrpz_num<$xrpz_num) throw new Exception(lang('lan_xrpz_insufficient_balance')); //XRP+ 余额不足

                $xrp_num = $pay_number - $xrpz_num;
                $xrpj_num = 0;
            } elseif($xrpj_num>0) {
                if($config['boss_plan_buy_xrpj_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                //瑞波金+钱包组合支付
                $max_xrpj_num = $pay_number * $config['boss_plan_buy_xrpj']/100;
                if($xrpj_num>$max_xrpj_num) throw new Exception(lang('lan_boss_plan_buy_xrpj_max_num').$config['boss_plan_buy_xrpj'].'%'); //超出比例

                $member_xrpj_num = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->value('xrpj_num');
                if(!$member_xrpj_num || $member_xrpj_num<$xrpj_num) throw new Exception(lang('lan_xrpj_insufficient_balance')); //XRPG 余额不足

                $xrp_num = $pay_number - $xrpj_num;
                $xrpz_num = 0;
            } else {
                if($config['boss_plan_buy_xrp_status']!=1) throw new Exception(lang('lan_network_busy_try_again'));

                $xrp_num = $pay_number;
                $xrpz_num = 0;
                $xrpj_num = 0;
            }

            $user_num = model('CurrencyUser')->getNum($member_id, $currency_id, 'num', true);
            if($user_num<$xrp_num) throw new Exception(lang('lan_insufficient_balance')); //钱包余额不足

            //直属上级信息
            $pidBossPlan = Db::name('boss_plan_info')->lock(true)->where(['member_id'=>$child_pid])->find();
            if(!$pidBossPlan) throw new Exception(lang('lan_network_busy_try_again'));
            //1为永不过期
            $pid_add_time = intval($config['pid_add_time']);
            if($pidBossPlan['overdue_time']!=1 && $pid_add_time>0) {
                $time = time();
                $one_time = $pid_add_time * 86400; //15天
                if($pidBossPlan['overdue_time']<$time){
                    $pidBossPlan['overdue_time'] = $time + $one_time; //已过期的现有日期加
                } else {
                    $pidBossPlan['overdue_time'] += $one_time; //未过期的累加
                }
                $pidBossPlan['overdue_time'] = intval($pidBossPlan['overdue_time']);
            }

            //上级加入幸运分红列表
            if($pidBossPlan['push_num']==2) {
                $flag = Db::name('boss_plan_lucky')->insertGetId([
                    'member_id' => $child_pid,
                    'add_time' => time(),
                    'is_stop' => 0,
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            }

            //增加成功推荐人数
            $flag = Db::name('boss_plan_info')->where(['member_id'=>$child_pid])->update([
                'overdue_time' => $pidBossPlan['overdue_time'],
                'push_num'=> ['inc',1],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //改变下级用户状态
            $flag = Db::name('boss_plan')->where(['member_id'=>$child_id,'status'=>['lt',3]])->update([
                'status' => 3,
                'activate_time' => time(),
                'confirm_time' => time(),
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //改变用户入金金额
            $flag = Db::name('boss_plan_info')->where(['member_id'=>$child_id])->update([
                'votes' => $step_info['votes'],
                'num' => $pay_number,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //添加激活记录
            $buy_id = Db::name('boss_plan_buy')->insertGetId([
                'type' => 0,
                'member_id' => $child_id,
                'pid' => $child_pid,
                'pay_id' => $member_id,
                'votes' => $step_info['votes'],
                'total' => $pay_number,
                'pay_number' => $pay_number,
                'xrp_num' => $xrp_num,
                'xrpz' => $xrpz_num,
                'xrpj' => $xrpj_num,
                'status' => 1,
                'add_time' => time(),
            ]);
            if($buy_id===false) throw new Exception(lang('lan_network_busy_try_again'));

            $childInfo = model('BossPlan')->getMemberInfo($child_id,true);
            if($childInfo){
                $child_name = $childInfo['phone'];
            } else {
                $child_name = '';
            }

            if($xrp_num>0) {
                //增加财务日志
                $result = Model('AccountBook')->addLog([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'type'=> 20,
                    'content' => 'lan_boss_plan_active',
                    'number_type' => 2,
                    'number' => $xrp_num,
                    'fee' => 0,
                    'third_id' => $child_id,
                    'name' => $child_name.'::'.$step_info['votes'],
                ]);
                if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                //扣除资产
                $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency_id])->setDec('num',$xrp_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            if($xrpz_num>0) {
                //扣除资产
                $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setDec('xrpz_num',$xrpz_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                //增加瑞波钻记录
                $insert_id = Db::name('xrp_log')->insertGetId([
                    'l_member_id' => $member_id,
                    'l_value' => '-'.$xrpz_num,
                    'l_time' => time(),
                    'l_title' => $child_id,
                    'l_votes' => $step_info['votes'],
                    'l_type' => 11,
                    'l_type_explain' => 'lan_accountbook_boss_plan_active',
                ]);
                if(!$insert_id) throw new Exception(lang('lan_network_busy_try_again'));
            }

            if($xrpj_num>0) {
                $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setDec('xrpj_num',$xrpj_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $insert_id = Db::name('xrpj_log')->insertGetId([
                    'l_member_id' => $member_id,
                    'l_value' => '-'.$xrpj_num,
                    'l_time' => time(),
                    'l_title' => $child_id,
                    'l_votes' => $step_info['votes'],
                    'l_type' => 11,
                    'l_type_explain' => 'lan_accountbook_boss_plan_active',
                ]);
                if(!$insert_id) throw new Exception(lang('lan_network_busy_try_again'));
            }

            //为后台任务（团队入金）添加记录
            // $flag = Db::name('boss_level_task')->insertGetId([
            //     'member_id' => $child_id,
            //     'money' => $pay_number,
            //     'add_time' => time(),
            // ]);
            // if($flag===false) throw new Exception(lang("lan_network_busy_try_again"));

            //为后台任务（更新用户关系）添加记录
            $flag = Db::name('boss_reg_task')->insertGetId([
                'member_id' => $child_id,
                'add_time' => time(),
            ]);
            if($flag===false) throw new Exception(lang("lan_network_busy_try_again"));

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     *下级用户取消激活
     *@param member_id 下级用户ID
     *@param pid 下级用户父ID
     */
    public function activeChildCancel($member_id) {
        $currency_id = Db::name('currency')->field('currency_id')->where(['currency_mark'=>'XRP'])->value('currency_id');
        $currency_id = intval($currency_id);

        try{
            Db::startTrans();

            $info = Db::name('boss_plan')->lock(true)->where(['member_id'=>$member_id])->find();
            if(!$info || $info['status']>2) throw new Exception(lang("lan_boss_plan_success_cannot_cancel"));

            $boss_info = Db::name('boss_plan_info')->lock(true)->where(['member_id'=>$member_id])->find();
            if(!$boss_info) throw new Exception(lang('lan_network_busy_try_again'));

            if($boss_info['xrpz_num']>0 || $boss_info['xrpz_forzen']>0) throw new Exception(lang('lan_xrpz_gt_cannot_cancel'));
            if($boss_info['xrpj_num']>0 || $boss_info['xrpj_forzen']>0) throw new Exception(lang('lan_xrpj_gt_cannot_cancel'));

            $flag = Db::name('boss_plan')->where(['member_id'=>$member_id])->delete();
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->delete();
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //添加激活撤销记录
            $buy_id = Db::name('boss_plan_buy')->insertGetId([
                'type' => 0,
                'member_id' => $member_id,
                'pid' => $info['pid'],
                'pay_id' => $info['pid'],
                'votes' => 0,
                'total' => 0,
                'pay_number' => 0,
                'status' => 2,
                'add_time' => time(),
            ]);
            if($buy_id===false) throw new Exception(lang('lan_network_busy_try_again'));

            //如果已经冻结资产,则返还资产
            if($info['status']==2) {
                $boss_plan_buy = Db::name('boss_plan_buy')->lock(true)->where(['member_id'=>$member_id,'status'=>0,'type'=>0])->find();
                if(!$boss_plan_buy) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = Db::name('boss_plan_buy')->where(['id'=>$boss_plan_buy['id'],'status'=>0,'type'=>0])->setField('status',2);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                //返还用户资产
                if($boss_plan_buy['xrp_num']>0) {
                    //增加财务日志
                    $result = Model('AccountBook')->addLog([
                        'member_id' => $boss_plan_buy['pay_id'],
                        'currency_id' => $currency_id,
                        'type'=> 20,
                        'content' => 'lan_boss_plan_active_cancel',
                        'number_type' => 1,
                        'number' => $boss_plan_buy['xrp_num'],
                        'fee' => 0,
                        'third_id' => $member_id,
                    ]);
                    if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

                    $flag = Db::name('currency_user')->where(['member_id'=>$boss_plan_buy['pay_id'],'currency_id'=>$currency_id])->update([
                        'num' => ['inc',$boss_plan_buy['xrp_num']],
                        'forzen_num'=> ['dec',$boss_plan_buy['xrp_num']],
                    ]);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                }

                //返还用户瑞波钻
                if($boss_plan_buy['xrpz']>0) {
                    $flag = Db::name('boss_plan_info')->where(['member_id'=>$boss_plan_buy['pay_id']])->update([
                        'xrpz_num' => ['inc',$boss_plan_buy['xrp_num']],
                        'xrpz_forzen'=> ['dec',$boss_plan_buy['xrp_num']],
                    ]);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                }

                //返还用户瑞波金
                if($boss_plan_buy['xrpj']>0) {
                    $flag = Db::name('boss_plan_info')->where(['member_id'=>$boss_plan_buy['pay_id']])->update([
                        'xrpj_num' => ['inc',$boss_plan_buy['xrp_num']],
                        'xrpj_forzen'=> ['dec',$boss_plan_buy['xrp_num']],
                    ]);
                    if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                }
            }

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
}
