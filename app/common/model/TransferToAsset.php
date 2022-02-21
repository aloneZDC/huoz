<?php
//资产包

namespace app\common\model;


use think\Exception;
use think\Model;

class TransferToAsset extends Model
{
    //兑换资产包 USDT兑换IWC
    public static function exchange($user_id,$from_currency_id,$from_num,$to_currency_id,$asset_type) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(empty($asset_type) || !is_numeric($from_currency_id) || !is_numeric($from_num) || !is_numeric($to_currency_id) || $from_num<=0) return $r;

        $config = TransferToAssetConfig::get_config($from_currency_id,$to_currency_id,$asset_type);
        if(empty($config)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        //需要投票才能兑换
        if($config['is_need_vote']==1) {

        }

        $from_cny = $currency_price = CurrencyPriceTemp::get_price_currency_id($from_currency_id,'CNY');
        $to_cny = $currency_price = CurrencyPriceTemp::get_price_currency_id($to_currency_id,'CNY');
        if($to_cny<=0) return $r;

        $fee = 0;
        if($config['fee']>0) $fee = keepPoint($from_num * $config['fee']/100,6);

        $from_to_num = $from_num - $fee;
        $to_num = keepPoint($from_to_num * $from_cny/$to_cny,6);
        if($to_num<0.000001) return $r;

        $from_users_currency = CurrencyUser::getCurrencyUser($user_id,$from_currency_id);
        if(empty($from_users_currency) || $from_users_currency['num']<$from_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $to_users_currency = CurrencyUser::getCurrencyUser($user_id,$to_currency_id);
        if(empty($to_users_currency)) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        try{
            self::startTrans();
            //增加兑换记录
            $log_id = self::add_asset('in',$user_id,$to_currency_id,$to_num,$user_id,$from_currency_id,$from_num,$fee,$config['asset_type']);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //添加账本
            $accountbook_log = AccountBook::add_accountbook($user_id, $from_users_currency['currency_id'], 301, 'exchange_asset', "out", $from_num, $log_id,$fee);
            if(!$accountbook_log) throw new Exception(lang('operation_failed_try_again'));

            //扣除资产
            $flag = CurrencyUser::where(['cu_id'=>$from_users_currency['cu_id'],'num'=>$from_users_currency['num']])->setDec('num',$from_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加资产包资产
            $currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],false);
            $flag = CurrencyUser::where(['cu_id'=>$to_users_currency['cu_id'],$currency_field=>$to_users_currency[$currency_field]])->setInc($currency_field,$to_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();

            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['code'] = ERROR2;
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //资产包互转
    public static function transfer($user_id,$to_account,$to_member_id,$currency_id,$num,$asset_type) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(empty($asset_type) || !is_numeric($currency_id) || !is_numeric($num) || $num<=0) return $r;

        $config = TransferToAssetConfig::get_config_by_to($currency_id,$asset_type);
        if(empty($config) || $config['to_is_transfer']!=1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $currency = Currency::where(['currency_id'=>$currency_id])->find();
        if(empty($currency)) return $r;

        $where = [];
        if(checkEmail($to_account)) {
            $where['email'] = $to_account;
        } else {
            $where['phone'] = $to_account;
        }
        $to_user = Member::where($where)->field('member_id')->find();
        if(empty($to_user)) {
            $r['message'] = lang('account_not_exists');
            return $r;
        }

        if($to_user['member_id']!=$to_member_id){
            $r['message'] = lang('account_nick_not_exists');
            return $r;
        }

        if($to_user['member_id']==$user_id) {
            $r['message'] = lang('can_not_transfer_yourself');
            return $r;
        }

        $currency_field = TransferToAssetConfig::get_currency_field($asset_type,false);

        $users_currency = CurrencyUser::getCurrencyUser($user_id,$currency_id);
        if(empty($users_currency) || $users_currency[$currency_field]<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $other_users_currency = CurrencyUser::getCurrencyUser($to_user['member_id'],$currency_id);
        if(empty($other_users_currency)) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        try{
            self::startTrans();
            //增加用户转出记录
            $log_id = self::add_asset('transfer_out',$user_id,$currency_id,$num,$to_user['member_id'],$currency_id,$num,0,$asset_type);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //减少资产包资产
            $flag = CurrencyUser::where([ 'cu_id'=>$users_currency['cu_id'],$currency_field=>$users_currency[$currency_field] ])->setDec($currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加用户转入记录
            $log_id = self::add_asset('transfer_in',$to_user['member_id'],$currency_id,$num,$user_id,$currency_id,$num,0,$asset_type);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //增加资产包资产
            $flag = CurrencyUser::where(['cu_id'=>$other_users_currency['cu_id'],$currency_field=>$other_users_currency[$currency_field]])->setInc($currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            self::commit();

            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['code'] = ERROR2;
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //转到理财包
    public static function transfer_to_financial($user_id,$currency_id,$num,$asset_type) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(empty($asset_type) || !is_numeric($currency_id) || !is_numeric($num) || $num<=0) return $r;

        $config = TransferToAssetConfig::get_config_by_to($currency_id,$asset_type);
        if(empty($config) || $config['to_is_financial']!=1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $currency = Currency::where(['currency_id'=>$currency_id])->find();
        if(empty($currency)) return $r;

        $asset_currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],false);
        $financial_currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],true);
        $users_currency = CurrencyUser::getCurrencyUser($user_id,$currency_id);
        if(empty($users_currency) || $users_currency[$asset_currency_field]<$num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        try{
            self::startTrans();
            //增加用户转出记录
            $log_id = self::add_asset('out',$user_id,$currency_id,$num,0,0,0,0,$asset_type);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //减少资产包资产
            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],$asset_currency_field=>$users_currency[$asset_currency_field]])->setDec($asset_currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加 理财包记录
            $log_id = TransferToFinancial::add_financial('in',$user_id,$currency_id,$num,0,0,0,$config['asset_type']);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //增加 理财包资产
            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],$financial_currency_field=>$users_currency[$financial_currency_field]])->setInc($financial_currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //兑换上级有奖励  1代5% 2代3% 3代0%
            $cur_user_id = $user_id;
            for ($no=1;$no<=3;$no++){
                //当前用户
                $users = Member::where(['member_id'=>$cur_user_id])->field('member_id,pid')->find();
                if(!$users || $users['pid']<=0) {
                    break;
                }

                $cur_user_id = $users['pid'];

                //推荐奖励的用户资产
                $award_to_users_currency = CurrencyUser::getCurrencyUser($users['pid'],$currency_id);
                if(empty($award_to_users_currency)) throw new Exception(lang('operation_failed_try_again'));

                if($config['recommand_min_num']>0 && $award_to_users_currency[$financial_currency_field]<$config['recommand_min_num']) continue;

                if(isset($config['recommand_percent'.$no]) && $config['recommand_percent'.$no]>0){
                    $award_num = keepPoint($num * $config['recommand_percent'.$no]/100,6);
                    if($award_num>=0.000001){
                        //添加奖励记录
                        $award_id = TransferToFinancial::add_financial('award',$users['pid'],$currency_id,$award_num,$config['recommand_percent'.$no],$num,$log_id,$config['asset_type']);
                        if(!$award_id) throw new Exception(lang('operation_failed_try_again'));

                        //添加资产包资产 推荐奖励到理财包
                        $flag = CurrencyUser::where(['cu_id'=>$award_to_users_currency['cu_id'],$financial_currency_field=>$award_to_users_currency[$financial_currency_field]])->setInc($financial_currency_field,$award_num);
                        if(!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                }
            }

            self::commit();

            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['code'] = ERROR2;
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * @param $type in转入资产包 out转出到理财 transfer_in互转转入 transfer_out互转转出 award下级兑换奖励
     * @param $user_id
     * @param $currency_id
     * @param $num
     * @param $from_user_id
     * @param $from_currency_id
     * @param $from_num
     * @param $fee
     * @param $asset_type
     * @return int|string
     */
    public static function add_asset($type,$user_id,$currency_id,$num,$other_user_id,$other_currency_id,$other_num,$fee,$asset_type) {
        return self::insertGetId([
            'tta_type' => $type,
            'tta_user_id' => $user_id,
            'tta_currency_id' => $currency_id,
            'tta_num' => $num,

            'tta_other_user_id' => $other_user_id,
            'tta_other_currency_id' => $other_currency_id,
            'tta_other_num' => $other_num,
            'tta_fee' => $fee,
            'tta_time' => time(),

            'tta_asset_type' => $asset_type,
        ]);
    }

    static function get_list($user_id,$asset_type,$currency_id=0,$type='',$income_type='',$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (isInteger($user_id) && $rows <= 100 && !empty($asset_type)) {
            $where = [
                'a.tta_user_id' => $user_id
            ];
            if(!empty($currency_id)) {
                $where['a.tta_currency_id'] = $currency_id;
            }
            if(!empty($type) && in_array($type,['in','out','transfer_in','transfer_out','award'])) {
                $where['a.tta_type'] = $type;
            }
            if(!empty($income_type)) {
                if($income_type=='in') {
                    $where['a.tta_type'] = [ 'in',['in','transfer_in','award'] ];
                } elseif($income_type=='out') {
                    $where['a.tta_type'] = [ 'in',['out','transfer_out'] ];
                }
            }
            $where['a.tta_asset_type'] = $asset_type;
            $field = "a.tta_type,a.tta_currency_id,a.tta_num,a.tta_time,b.currency_mark,b.currency_logo";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.tta_currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.tta_id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['tta_time'] = date('Y-m-d H:i:s', $value['tta_time']);
                    $value['title'] = '';
                    if ($value['tta_type'] == 'in') {
                        $value['title'] = lang('asset_in');
                    } elseif ($value['tta_type'] == 'out') {
                        $value['title'] = lang('asset_out');
                    } elseif ($value['tta_type'] == 'transfer_in') {
                        $value['title'] = lang('asset_transfer_in');
                    } elseif ($value['tta_type'] == 'transfer_out') {
                        $value['title'] = lang('asset_transfer_out');
                    } elseif ($value['tta_type'] == 'award') {
                        $value['title'] = lang('asset_transfer_award');
                    }
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'tta_user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function tousers() {
        return $this->belongsTo('app\\common\\model\\Member', 'tta_other_user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'tta_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function tocurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'tta_other_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}