<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

//兑换
class Exchange extends Base {
    /**
     *GAC转账
     *@param type 4锁仓转账 5赠送转账 6内购转账,7GAC转账(剩余本金)
     *@param account 用户账户
     *@param member_id 用户ID
     *@param to_member_id 接收ID
     *@param num 数量
     *@param pwd 安全密码
     *@param phone_code 手机验证码,暂不验证
     */
    public function transfer($type,$account,$member_id,$to_member_id,$num,$pwd,$phone_code=''){
        if(!in_array($type, [4,6,7])) return lang("lan_modifymember_parameter_error");

        if(empty($pwd)) return lang('lan_Incorrect_transaction_password');
        $checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd);
        if(is_string($checkPwd)) return $checkPwd;

        $num = keepPoint($num,3);
        if($num<=0) return lang('lan_num_no_format');
        if($num<0.001) return lang("lan_decimal_limit");


        if($type==4) {
            $config=Db::name('boss_config')->field('value')->where(['key'=>'gac_lock_switch'])->find();
            if(!$config ||$config['value']==2) return lang("lan_transaction_pause");
        } elseif ($type==6) {
            $config=Db::name('boss_config')->field('value')->where(['key'=>'gac_internal_buy_switch'])->find();
            if(!$config ||$config['value']==2) return lang("lan_transaction_pause");
        }elseif ($type==7) {
            $config=Db::name('boss_config')->field('value')->where(['key'=>'gac_transfer_switch'])->find();
            if(!$config ||$config['value']==2) return lang("lan_transaction_pause");
        }

        if(empty($to_member_id) || empty($account)) return lang('lan_account_member_not_exist');
        if($member_id==$to_member_id) return lang("lan_can_not_transfer_yourself");

        //个人信息
        $member = Db::name('member')->field('member_id,phone,email')->where(['member_id'=>$member_id])->find();
        if(!$member) return lang('lan_account_member_not_exist');

        //接收用户信息
        $to_member = Db::name('member')->field('member_id,phone,email')->where(['member_id'=>$to_member_id])->find();
        if(!$to_member) return lang('lan_account_member_not_exist');
        if($account!=$to_member['phone'] && $account!=$to_member['email']) return lang('lan_account_member_not_exist');

        $currency = Db::name('currency')->field('currency_id')->where(['currency_mark'=>'GAC'])->find();
        if(!$currency) return lang("lan_modifymember_parameter_error");

        $fee = 0;
        if($type==4) {
            $config_fee =Db::name('boss_config')->field('value')->where(['key'=>'gac_lock_fee'])->find();
            if($config_fee) $fee = $config_fee['value'];
        } elseif ($type==6) {
            $config_fee =Db::name('boss_config')->field('value')->where(['key'=>'gac_internal_buy_fee'])->find();
            if($config_fee) $fee = $config_fee['value'];
        }elseif ($type==7) {
            $config_fee =Db::name('boss_config')->field('value')->where(['key'=>'gac_xrp_exchange_gac_fee'])->find();
            if($config_fee) $fee = $config_fee['value'];
        }

        Db::startTrans();
        try{
            $total = keepPoint($num + $num*$fee,6);

            if($type==4) {
                $currency_user = Db::name('currency_user')->field('lock_num')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
                if(!$currency_user || $currency_user['lock_num']<$total) throw new Exception(lang('lan_your_credit_is_running_low'));

                //减少资产记录
                $flag = Db::name('currency_gac_forzen')->insertGetId([
                    'member_id' => $member_id,
                    'num' => $total,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 1,
                    'third_id' => $to_member_id,
                    'title' => substr($account, 0,3).'****'.substr($account, -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
                //减少资产
                $flag = Db::name('currency_user')->field('lock_num')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->setDec('lock_num',$total);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产记录
                if(empty($member['phone'])) $member['phone'] = $member['email'];
                $flag = Db::name('currency_gac_forzen')->insertGetId([
                    'member_id' => $to_member_id,
                    'num' => $num,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 2,
                    'third_id' => $member_id,
                    'title' => substr($member['phone'], 0,3).'****'.substr($member['phone'], -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产
                $flag = false;
                $target_currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->find();
                if($target_currency_user) {
                    $flag = Db::name('currency_user')->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->setInc('lock_num',$num);
                } else {
                    $flag = Db::name('currency_user')->insertGetId([
                        'member_id'=>$to_member_id,
                        'currency_id'=>$currency['currency_id'],
                        'lock_num' => $num,
                    ]);
                }
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            } elseif ($type==6) {
                $currency_user = Db::name('currency_user')->field('internal_buy')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
                if(!$currency_user || $currency_user['internal_buy']<$total) throw new Exception(lang('lan_your_credit_is_running_low'));

                //减少资产记录
                $flag = Db::name('currency_gac_internal_buy')->insertGetId([
                    'member_id' => $member_id,
                    'num' => $total,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 1,
                    'third_id' => $to_member_id,
                    'title' => substr($account, 0,3).'****'.substr($account, -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //减少资产
                $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->setDec('internal_buy',$total);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产记录
                if(empty($member['phone'])) $member['phone'] = $member['email'];
                $flag = Db::name('currency_gac_internal_buy')->insertGetId([
                    'member_id' => $to_member_id,
                    'num' => $num,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 2,
                    'third_id' => $member_id,
                    'title' => substr($member['phone'], 0,3).'****'.substr($member['phone'], -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产
                $flag = false;
                $target_currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->find();
                if($target_currency_user) {
                    $flag = Db::name('currency_user')->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->setInc('internal_buy',$num);
                } else {
                    $flag = Db::name('currency_user')->insertGetId([
                        'member_id'=>$to_member_id,
                        'currency_id'=>$currency['currency_id'],
                        'internal_buy' => $num,
                    ]);
                }
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            }elseif ($type==7) {
                $currency_user = Db::name('currency_user')->field('remaining_principal')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
                if(!$currency_user || $currency_user['remaining_principal']<$total) throw new Exception(lang('lan_your_credit_is_running_low'));

                //减少资产记录
                $flag = Db::name('currency_gac_log')->insertGetId([
                    'member_id' => $member_id,
                    'num' => $total,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 1,
                    'third_id' => $to_member_id,
                    'title' => substr($account, 0,3).'****'.substr($account, -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //减少资产
                $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->setDec('remaining_principal',$total);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产记录
                if(empty($member['phone'])) $member['phone'] = $member['email'];
                $flag = Db::name('currency_gac_log')->insertGetId([
                    'member_id' => $to_member_id,
                    'num' => $num,
                    'ratio' => 0,
                    'from_num' => 0,
                    'type' => 2,
                    'third_id' => $member_id,
                    'title' => substr($member['phone'], 0,3).'****'.substr($member['phone'], -3),
                    'add_time' => time(),
                ]);
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

                //增加资产
                $flag = false;
                $target_currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->find();
                if($target_currency_user) {
                    $flag = Db::name('currency_user')->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->setInc('remaining_principal',$num);
                } else {
                    $flag = Db::name('currency_user')->insertGetId([
                        'member_id'=>$to_member_id,
                        'currency_id'=>$currency['currency_id'],
                        'remaining_principal' => $num,
                    ]);
                }
                if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            }

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
}
