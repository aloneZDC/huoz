<?php
//线下商家 卡包记录表  I券
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class StoresFinancialLog extends Model {
    /**
     * @param string $type transfer_financial从I券划转入 release释放 recommand_award推荐奖励 shop购物扣除 release_award下级释放奖励
     * @param $user_id
     * @param $currency_id
     * @param $number
     * @param int $third_id
     * @param int $base_num
     * @param int $percent
     * @return int|string
     */
    static function add_log($type,$user_id,$currency_id,$number,$third_id=0,$base_num=0,$percent=0){
        return self::insertGetId([
            'type' => $type,
            'user_id' => $user_id,
            'currency_id' => $currency_id,
            'number' => $number,
            'third_id' => $third_id,
            'base_num' => $base_num,
            'percent' => $percent,
            'create_time' => time(),
        ]);
    }

    //释放操作
    static function release($to_currency_user,$number,$base_num,$percent) {
        try{
            self::startTrans();
            //添加理财包记录
            $log_id = self::add_log('release',$to_currency_user['member_id'],$to_currency_user['currency_id'],$number,$to_currency_user['cu_id'],$base_num,$percent);
            if(!$log_id) throw new Exception("添加记录失败");

            //添加账本  增加可用资产
            $flag = AccountBook::add_accountbook($to_currency_user['member_id'],$to_currency_user['currency_id'],702,'financial_release','in',$number,$log_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::NUM_FIELD=>$to_currency_user[StoresConvertConfig::NUM_FIELD]])->setInc(StoresConvertConfig::NUM_FIELD,$number);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //减少理财包资产
            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::FINANCIAL_FIELD=>$to_currency_user[StoresConvertConfig::FINANCIAL_FIELD]])->setDec(StoresConvertConfig::FINANCIAL_FIELD,$number);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
        } catch(Exception $e) {
            self::rollback();
            Log::write("理财包释放错误：".$e->getMessage());
        }
    }

    //从卡包 划转 到理财包 推荐奖励任务
    static function to_financial_award($third_id,$config) {
        $log = self::where(['id'=>$third_id,'type'=>'transfer_financial'])->find();
        if(empty($log)) return false;

        $user = Member::where(['member_id'=>$log['user_id']])->field('member_id,pid')->find();
        if(empty($user)) return false;
        if($user['pid']<=0) return true;

        $first_num = keepPoint($log['number'] * $config['stores_transfer_financial_recommand1']/100,6);
        self::to_financial_award_do($user['pid'],$log['currency_id'],$first_num,$third_id,$log['number'],$config['stores_transfer_financial_recommand1']);

        $base_num = $first_num;
        $percent = $config['stores_transfer_financial_recommand_n'];
        while (true) {
            $award_num = keepPoint($base_num * $percent/100,6);
            if($award_num<1) {
                break;
            }

            $user = Member::where(['member_id'=>$user['pid']])->field('member_id,pid')->find();
            if(empty($user) || $user['pid']<=0) {
                break;
            }
            self::to_financial_award_do($user['pid'],$log['currency_id'],$award_num,$third_id,$award_num,$percent);
            $base_num = $award_num;
        }
    }

    static function to_financial_award_do($user_id,$currency_id,$number,$third_id,$base_num,$percent) {
        try{
            self::startTrans();
            $to_currency_user = CurrencyUser::getCurrencyUser($user_id,$currency_id);
            if(empty($to_currency_user)) throw new Exception("用户资产记录不存在");

            //添加理财包记录
            $log_id = self::add_log('recommand_award',$to_currency_user['member_id'],$to_currency_user['currency_id'],$number,$third_id,$base_num,$percent);
            if(!$log_id) throw new Exception("添加记录失败");

            //增加理财包资产
            $flag = CurrencyUser::where(['cu_id'=>$to_currency_user['cu_id'],StoresConvertConfig::FINANCIAL_FIELD=>$to_currency_user[StoresConvertConfig::FINANCIAL_FIELD]])->setInc(StoresConvertConfig::FINANCIAL_FIELD,$number);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
        } catch(Exception $e) {
            self::rollback();
            Log::write("推荐奖励任务错误：".$e->getMessage());
        }
    }

    static function get_list($user_id,$type='', $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $where = [
                'a.user_id' => $user_id,
            ];
            if(!empty($type)) {
                if($type=='in') {
                    $where['a.type'] = [ 'in',['transfer_financial','recommand_award','transfer_in'] ];
                } elseif($type=='out') {
                    $where['a.type'] = [ 'in',['release','shop','transfer_out'] ];
                }
            }
            $field = "a.number,a.create_time,a.type";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['title'] = '';
                    if($value['type']=='transfer_financial') {
                        $value['title'] = lang('asset_out');
                    } elseif($value['type']=='release'){
                        $value['title'] = lang('financial_release');
                    }elseif($value['type']=='recommand_award'){
                        $value['title'] = lang('asset_transfer_award');
                    }elseif($value['type']=='shop'){
                        $value['title'] = lang('shopping');
                    }elseif($value['type']=='transfer_in'){
                        $value['title'] = lang('asset_transfer_in');
                    }elseif($value['type']=='transfer_out'){
                        $value['title'] = lang('asset_transfer_out');
                    }
                    if(in_array($value['type'],['transfer_financial','recommand_award','transfer_in'])) {
                        $value['number'] = '+'.$value['number'];
                    } elseif (in_array($value['type'],['release','shop','transfer_out'])){
                        $value['number'] = '-'.$value['number'];
                    }
                    $value['currency_name'] = lang('uc_card_lock');
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

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
}