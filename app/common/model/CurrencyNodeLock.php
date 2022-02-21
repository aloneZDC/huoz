<?php
/**
节点需求文档
1.入金：
    100 - 3999 【3倍杠杆】
    4000-4999 【4倍杠杆】
    5000-5999 【5倍杠杆】
    6000-6999【6倍杠杆】
    7000-7999【7倍杠杆】
    8000-8999【8倍杠杆】
    9000-9999【9倍杠杆】
    10000及以上 【10倍杠杆】
2.推荐奖：【1代拿本金的50%， 2代拿本金的50%】
    A.考核：累计入金大于等于4000
    B.入金100-3999的不是节点订单 上级不享受该笔入金的推荐奖
    C.无烧伤
    D.不紧缩
 */
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class CurrencyNodeLock extends Model
{
    const LOCK_FIELD = 'node_lock'; //进入的锁仓字段
    const PAY_CURRENCY_ID = 38; //支付币种ID
    const MASTER = 'master'; //主舱
    const SLAVE = 'slave'; //子舱

    static function move_config($member_id) {
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');

        //最低值限制
        $min_num = Config::get_value('node_lock_min_num',0);
        $config = Db::name('currency_node_lock_setting')->field('min_num,max_num,ratio,is_award')->order('min_num asc')->select();
        if(!$config) $config = [];

        foreach ($config as &$item) {
            if($item['max_num']==10000000) $item['max_num'] = 0;//不限制
        }
        $pay_currency_user = CurrencyUser::getCurrencyUser($member_id,self::PAY_CURRENCY_ID);

        $node_lock_big_open = Config::get_value('node_lock_big_open',0);
        $node_lock_small_open = Config::get_value('node_lock_small_open',0);
        $node_auto_start_time = Config::get_value('node_auto_start_time',0);
        $r['result'] = [
            'node_auto_start_time' => time()>$node_auto_start_time ? 1 : 0,
            'node_lock_big_open' => $node_lock_big_open,
            'node_lock_small_open' => $node_lock_small_open,
            'user_num' => $pay_currency_user ? $pay_currency_user['num'] : 0,
            'min_num' => $min_num,
            'config' => $config,
            'summary' => CurrencyNodeLockSummary::getItem($member_id),
        ];
        return $r;
    }

    static function node_lock_list($member_id,$income_type,$page) {
        return CurrencyLockBook::get_list($member_id,self::LOCK_FIELD,$income_type,['award','t_award','release'],$page);
    }

    static function move_list($user_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100 ) {
            $where = [
                'a.user_id' => $user_id,
            ];
            $node_lock_recommand_num =  Config::get_value('node_lock_recommand_num',0);
            $field = "a.type,a.actual_num,a.pay_num,a.ratio,a.create_time,b.currency_name,c.currency_name as pay_currency_name";
            $list = self::field($field)->alias('a')
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->join(config("database.prefix") . "currency c", "a.pay_currency_id=c.currency_id", "LEFT")
                ->where($where)->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
                    if($value['pay_num']<$node_lock_recommand_num) {
                        $value['is_small'] = 1;
                    } else {
                        $value['is_small'] = 2;
                    }
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

    //从可用转到 节点锁仓 放大
    static function move($member_id,$num,$pwd) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(!is_numeric($num) || $num<=0) return $r;

        $check_pwd = Member::verifyPaypwd($member_id,$pwd);
        if($check_pwd['code']!=SUCCESS) {
            $r['message'] = $check_pwd['message'];
            return $r;
        }

        $node_auto_start_time = Config::get_value('node_auto_start_time',0);
        if(time()<$node_auto_start_time) {
            $r['code'] = ERROR11;
            $r['message'] = lang('start_time',['time'=>date('Y-m-d H:i:s',$node_auto_start_time)]);
            return $r;
        }

        $min_num = Config::get_value('node_lock_min_num',0);
        if($min_num>0 && $num<$min_num){
            $r['message'] = lang('flop_min_num',['num'=>$min_num]);
            return $r;
        }

        $node_lock_stop_time = Config::get_value('node_lock_stop_time',1598889600);
        if($node_lock_stop_time<=todayBeginTimestamp()) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        $currency_id = CurrencyLockBook::FIELD_CURRENCY_ID[self::LOCK_FIELD];
        $master = self::where(['user_id'=>$member_id,'currency_id'=>$currency_id,'type'=>self::MASTER])->find();

        $pay_currency_user = CurrencyUser::getCurrencyUser($member_id,self::PAY_CURRENCY_ID);
        if(!$pay_currency_user || $pay_currency_user['num']<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(!$currency_user) return $r;

        $ratio = Db::name('currency_node_lock_setting')->where(['min_num'=>['elt',$num],'max_num'=>['egt',$num] ])->order('min_num asc')->find();
        if(!$ratio) return $r;

        //如果入金<4000不是节点 上级拿不到推荐奖励
        $is_deal = 0;
        $node_lock_recommand_num =  Config::get_value('node_lock_recommand_num',0);
        if($num<$node_lock_recommand_num) {
            $is_deal = 1;
            //小天使开关
            $node_lock_small_open = Config::get_value('node_lock_small_open',0);
            if($node_lock_small_open!=1) {
                $r['code'] = ERROR12;
                $r['message'] = lang('node_convert_full');
                return $r;
            }
        } else {
            //大天使开关
            $node_lock_big_open = Config::get_value('node_lock_big_open',0);
            if($node_lock_big_open!=1) {
                $r['code'] = ERROR12;
                $r['message'] = lang('node_convert_full');
                return $r;
            }
        }

        $actual_num = $num * $ratio['ratio'];
        try{
            self::startTrans();

            //增加汇总记录
            $flag = CurrencyNodeLockSummary::addItem($member_id,$currency_id);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加总累计入金
            $flag = CurrencyNodeLockSummary::where(['member_id'=>$member_id])->update([
                'total_num' => ['inc',$num],
                'total_lock_num' => ['inc',$actual_num],
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加兑换记录
            $insert_id = self::insertGetId([
                'type' => $master ? self::SLAVE : self::MASTER,
                'user_id' => $member_id,
                'currency_id' => $currency_id,
                'actual_num' => $actual_num,
                'ratio' => $ratio['ratio'],
                'pay_currency_id' => $pay_currency_user['currency_id'],
                'pay_num' => $num,
                'create_time' => time(),
                'is_deal' => $is_deal,
            ]);
            if(!$insert_id) throw new Exception(lang('operation_failed_try_again'));

            //增加锁仓变动记录
            $flag = CurrencyLockBook::add_log(self::LOCK_FIELD,'convert',$member_id,$currency_id,$actual_num,$insert_id,$num,$ratio['ratio']);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($pay_currency_user['member_id'],$pay_currency_user['currency_id'],2600,'node_convert','out',$num,$insert_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$pay_currency_user['cu_id'],'num'=>$pay_currency_user['num']])->setDec('num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加锁仓资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],self::LOCK_FIELD=>$currency_user[self::LOCK_FIELD]])->setInc(self::LOCK_FIELD,$actual_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //上级推荐奖
    static function award_task($nodelock,$award_config) {
        if($nodelock['is_deal']==1) return;

        $first_member_id =0;
        if($nodelock['type']==self::SLAVE) {
            //如果是子仓 直推是自己
            $first_member_id = $nodelock['user_id'];
        } else {
            $member = Member::where(['member_id'=>$nodelock['user_id']])->field('pid')->find();
            if($member) $first_member_id = $member['pid'];
        }

        $flag = self::where(['id'=>$nodelock['id'],'is_deal'=>0])->setField('is_deal',1);
        if(!$flag) return;

        for ($count=1;$count<=2;$count++) {
            if($first_member_id<=0) break;

            $award_type = 'award';
            if($count==2) $award_type = 't_award';
            $percent = $award_config['node_lock_percent'.$count];
            self::award($first_member_id,$percent,$nodelock,$award_type,$award_config);

            $member = Member::where(['member_id'=>$first_member_id])->field('pid')->find();
            if(!$member) break;

            $first_member_id = $member['pid'];
        }
    }

    static function award($first_member_id,$percent,$nodelock,$award_type,$award_config) {
        if($first_member_id<=0) return;

        $award_base_num = $nodelock['pay_num'];
        $award_num = keepPoint($award_base_num * $percent / 100,6);
        if($award_num<0.000001) return;

        $currency_user = CurrencyUser::getCurrencyUser($first_member_id,$nodelock['currency_id']);
        if(!$currency_user) return;

        //上级入金考核 累计4000才能拿推荐奖
        $first_node_lock = self::where(['user_id'=>$first_member_id,'currency_id'=>$nodelock['currency_id']])->sum('pay_num');
        if($first_node_lock<$award_config['node_lock_recommand_num']) return;

        try{
            self::startTrans();

            //添加累计奖励
            $flag = CurrencyNodeLockSummary::where(['member_id'=>$first_member_id])->update(['total_recommand' => ['inc',$award_num]]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加锁仓变动记录
            $flag = CurrencyLockBook::add_log(self::LOCK_FIELD,$award_type,$first_member_id,$nodelock['currency_id'],$award_num,$nodelock['id'],$award_base_num,$percent);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加锁仓资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],self::LOCK_FIELD=>$currency_user[self::LOCK_FIELD]])->setInc(self::LOCK_FIELD,$award_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
        }catch (Exception $e) {
            self::rollback();
            Log::write($nodelock['id']." 推荐奖励失败:".$e->getMessage());
            return false;
        }
    }

    static function release($currency_user,$percent,$is_all) {
        $total_summary = CurrencyNodeLockSummary::where(['member_id'=>$currency_user['member_id']])->find();
        if(!$total_summary) return;

        $award_base_num = $total_summary['total_lock_num'] + $total_summary['total_recommand'];

        $award_num = keepPoint($award_base_num * $percent / 100,6);
        $award_num = min($award_num,$currency_user[self::LOCK_FIELD]);
        if($is_all) $award_num = $currency_user[self::LOCK_FIELD];
        if($award_num<0.000001) return;

        try{
            self::startTrans();

            //添加累计释放
            $flag = CurrencyNodeLockSummary::where(['member_id'=>$currency_user['member_id']])->update(['total_release' => ['inc',$award_num]]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加锁仓变动记录
            $flag = CurrencyLockBook::add_log(self::LOCK_FIELD,'release',$currency_user['member_id'],$currency_user['currency_id'],$award_num,$currency_user['cu_id'],$award_base_num,$percent);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加账本
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2601,'node_release','in',$award_num,$currency_user['cu_id'],0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //扣除冻结资产 增加可用资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],self::LOCK_FIELD=>$currency_user[self::LOCK_FIELD]])->update([
                'num' => ['inc',$award_num],
                self::LOCK_FIELD => ['dec',$award_num]
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            self::commit();
        }catch (Exception $e) {
            self::rollback();
            Log::write($currency_user['cu_id']." 释放失败:".$e->getMessage());
            return false;
        }
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function paycurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
