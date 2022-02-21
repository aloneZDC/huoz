<?php
//线下商家兑换记录
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class StoresConvertLog extends Model {
    //从可用 兑换到卡包锁仓I券
    static function convert_num_to_card($user_id,$currency_id,$to_currency_id,$from_num) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(!isInteger($currency_id) || !isInteger($to_currency_id) || !is_numeric($from_num) || $from_num<=0) return $r;

        $flag = false;
        $stores_status = StoresList::apply_status($user_id);
        if($stores_status==1) {
            $flag = true;
        } else {
            $users_votes = UsersVotes::where(['user_id'=>$user_id])->find();
            if($users_votes && $users_votes['num']>0) $flag = true;
        }

        if(!$flag) {
            $r['message'] = lang('apply_or_vip');
            return $r;
        }

        $stores_convert_config = StoresConvertConfig::num_to_card_one($currency_id,$to_currency_id);
        if(empty($stores_convert_config)) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        if($stores_convert_config['min_num']>0 && $from_num<$stores_convert_config['min_num']) {
            $r['message'] = lang('lan_num_not_less_than').$stores_convert_config['min_num'];
            return $r;
        }
        if($stores_convert_config['max_num']>0 && $from_num>$stores_convert_config['max_num']) {
            $r['message'] = lang('lan_num_not_greater_than').$stores_convert_config['max_num'];
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['currency_id']);
        if(!$currency_user || $currency_user[StoresConvertConfig::NUM_FIELD]<$from_num) {
            $r['message'] = lang('insufficient_balance'); //余额不足
            return $r;
        }

        $to_currency_user = $currency_user;
        if($stores_convert_config['currency_id']!=$stores_convert_config['to_currency_id']){
            $to_currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['to_currency_id']);
            if(!$to_currency_user) return $r;
        }

        $to_num = $from_num * $stores_convert_config['ratio'];
        $fee = 0; //扣除手续费
        if($stores_convert_config['fee']>0) {
            $fee = keepPoint($to_num * $stores_convert_config['fee'], 6);
            $to_num = $to_num - $fee;
        }
        $to_num = keepPoint($to_num * $stores_convert_config['to_currency_inc_percent'] / 100,6);
        try{
            self::startTrans();
            $log_id = self::insertGetId([
                'config_id' => $stores_convert_config['id'],
                'user_id' => $user_id,
                'currency_id' => $stores_convert_config['currency_id'],
                'currency_field' => $stores_convert_config['currency_field'],
                'to_currency_id' => $stores_convert_config['to_currency_id'],
                'to_currency_field' => $stores_convert_config['to_currency_field'],
                'to_currency_inc_percent' => $stores_convert_config['to_currency_inc_percent'],
                'number' => $from_num,
                'to_number' => $to_num,
                'ratio' => $stores_convert_config['ratio'],
                'fee' => $fee,
            ]);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$currency_user['currency_id'],700,'convert_card','out',$from_num,$log_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],StoresConvertConfig::NUM_FIELD=>$currency_user[StoresConvertConfig::NUM_FIELD]])->setDec(StoresConvertConfig::NUM_FIELD,$from_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加卡包记录 增加卡包资产
            $flag = StoresCardLog::add_log('convert',$user_id,$to_currency_user['currency_id'],$to_num,$log_id);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::CARD_FIELD=>$to_currency_user[StoresConvertConfig::CARD_FIELD]])->setInc(StoresConvertConfig::CARD_FIELD,$to_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch(Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //从卡包锁仓I券 兑换到 理财包锁仓O券
    static function convert_card_to_financial($user_id,$currency_id,$to_currency_id,$from_num) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(!isInteger($currency_id) || !isInteger($to_currency_id) || !is_numeric($from_num) || $from_num<=0) return $r;

        $stores_convert_config = StoresConvertConfig::card_to_financial_one($currency_id,$to_currency_id);
        if(empty($stores_convert_config)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if($stores_convert_config['min_num']>0 && $from_num<$stores_convert_config['min_num']) {
            $r['message'] = lang('lan_num_not_less_than').$stores_convert_config['min_num'];
            return $r;
        }
        if($stores_convert_config['max_num']>0 && $from_num>$stores_convert_config['max_num']) {
            $r['message'] = lang('lan_num_not_greater_than').$stores_convert_config['max_num'];
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['currency_id']);
        if(!$currency_user || $currency_user[StoresConvertConfig::CARD_FIELD]<$from_num) {
            $r['message'] = lang('insufficient_balance'); //余额不足
            return $r;
        }

        $to_currency_user = $currency_user;
        if($stores_convert_config['currency_id']!=$stores_convert_config['to_currency_id']){
            $to_currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['to_currency_id']);
            if(!$to_currency_user) return $r;
        }

        $to_num = $from_num * $stores_convert_config['ratio'];
        $fee = 0; //扣除手续费
        if($stores_convert_config['fee']>0) {
            $fee = keepPoint($to_num * $stores_convert_config['fee'], 6);
            $to_num = $to_num - $fee;
        }
        $to_num = keepPoint($to_num * $stores_convert_config['to_currency_inc_percent'] / 100,6);

        try{
            self::startTrans();
            $log_id = self::insertGetId([
                'config_id' => $stores_convert_config['id'],
                'user_id' => $user_id,
                'currency_id' => $stores_convert_config['currency_id'],
                'currency_field' => $stores_convert_config['currency_field'],
                'to_currency_id' => $stores_convert_config['to_currency_id'],
                'to_currency_field' => $stores_convert_config['to_currency_field'],
                'to_currency_inc_percent' => $stores_convert_config['to_currency_inc_percent'],
                'number' => $from_num,
                'to_number' => $to_num,
                'ratio' => $stores_convert_config['ratio'],
                'fee' => $fee,
            ]);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //增加卡包记录 减少卡包资产
            $flag = StoresCardLog::add_log('transfer_financial',$user_id,$currency_user['currency_id'],$from_num,$log_id);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],StoresConvertConfig::CARD_FIELD=>$currency_user[StoresConvertConfig::CARD_FIELD]])->setDec(StoresConvertConfig::CARD_FIELD,$from_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加理财包记录 增加理财包资产
            $financial_log_id = StoresFinancialLog::add_log('transfer_financial',$user_id,$to_currency_user['currency_id'],$to_num,$log_id);
            if(!$financial_log_id) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::FINANCIAL_FIELD=>$to_currency_user[StoresConvertConfig::FINANCIAL_FIELD]])->setInc(StoresConvertConfig::FINANCIAL_FIELD,$to_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //推荐奖励
            $result = Db::name('stores_financial_award_task')->insertGetId([
                'third_id' => $financial_log_id,
                'is_award' => 0,
            ]);
            if(!$result) throw new Exception(lang('operation_failed_try_again5'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch(Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //从可用兑换到可用
    static function convert_num_to_num($user_id,$currency_id,$to_currency_id,$from_num) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        if(!isInteger($currency_id) || !isInteger($to_currency_id) || !is_numeric($from_num) || $from_num<=0) return $r;

        $stores_convert_config = StoresConvertConfig::num_to_card_one($currency_id,$to_currency_id);
        if(empty($stores_convert_config)) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if($stores_convert_config['min_num']>0 && $from_num<$stores_convert_config['min_num']) {
            $r['message'] = lang('lan_num_not_less_than').$stores_convert_config['min_num'];
            return $r;
        }
        if($stores_convert_config['max_num']>0 && $from_num>$stores_convert_config['max_num']) {
            $r['message'] = lang('lan_num_not_greater_than').$stores_convert_config['max_num'];
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['currency_id']);
        if(!$currency_user || $currency_user[StoresConvertConfig::NUM_FIELD]<$from_num) {
            $r['message'] = lang('insufficient_balance'); //余额不足
            return $r;
        }

        //不能同币种可用 到 同币种可用
        if($stores_convert_config['currency_id']==$stores_convert_config['to_currency_id']) {
            return $r;
        }

        $to_currency_user = CurrencyUser::getCurrencyUser($user_id,$stores_convert_config['to_currency_id']);
        if(!$to_currency_user) return $r;

        $to_num = $from_num * $stores_convert_config['ratio'];
        $fee = 0; //扣除手续费
        if($stores_convert_config['fee']>0) {
            $fee = keepPoint($to_num * $stores_convert_config['fee'], 6);
            $to_num = $to_num - $fee;
        }
        $to_num = keepPoint($to_num * $stores_convert_config['to_currency_inc_percent'] / 100,6);

        try{
            self::startTrans();
            $log_id = self::insertGetId([
                'config_id' => $stores_convert_config['id'],
                'user_id' => $user_id,
                'currency_id' => $stores_convert_config['currency_id'],
                'currency_field' => $stores_convert_config['currency_field'],
                'to_currency_id' => $stores_convert_config['to_currency_id'],
                'to_currency_field' => $stores_convert_config['to_currency_field'],
                'to_currency_inc_percent' => $stores_convert_config['to_currency_inc_percent'],
                'number' => $from_num,
                'to_number' => $to_num,
                'ratio' => $stores_convert_config['ratio'],
                'fee' => $fee,
            ]);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$currency_user['currency_id'],700,'convert_card','out',$from_num,$log_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],StoresConvertConfig::NUM_FIELD=>$currency_user[StoresConvertConfig::NUM_FIELD]])->setDec(StoresConvertConfig::NUM_FIELD,$from_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($user_id,$to_currency_user['currency_id'],701,'convert_num','in',$to_num,$log_id,$fee);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::NUM_FIELD=>$to_currency_user[StoresConvertConfig::NUM_FIELD]])->setInc(StoresConvertConfig::NUM_FIELD,$to_num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch(Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    static function convert_list($user_id,$type='', $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $configs = [];
            if($type=='num_to_card') {
                $configs = StoresConvertConfig::num_to_card();
            } elseif ($type=='num_to_num') {
                $configs = StoresConvertConfig::num_to_num();
            } elseif ($type=='card_to_financial'){
                $configs = StoresConvertConfig::card_to_financial();
            }

            if(!empty($configs)) {
                $configs_ids = array_column($configs,'id');
                $where['a.config_id'] = ['in',$configs_ids];
            }
            $where = [
                'a.user_id' => $user_id,
            ];
            $field = "a.currency_id,a.to_currency_id,a.number,a.to_number,a.ratio,a.fee,a.create_time,b.currency_name,c.currency_name as to_currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->join(config("database.prefix") . "currency c", "a.to_currency_id=c.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
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
}