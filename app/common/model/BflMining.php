<?php
namespace app\common\model;
use think\Db;
use think\Exception;
use think\Model;

class BflMining extends Model
{
    const STATUS_OK = 1; //正常状态
    const STATUS_OUT = 2; //出局状态
    const STATUS_CANCEL = 3; //解除状态

    //租赁新矿机
    static function buy($member_id,$currency_id,$pay_num) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($member_id<=0 || $currency_id<=0) return $r;

        $currency_config = BflMiningCurrencyConfig::getCurrencyConfig($currency_id);
        if(empty($currency_config)) return $r;

        //如果存在可用的矿机 则不能新建
        $mining = self::where([
            'member_id' => $member_id,
            'currency_id' => $currency_config['currency_id'],
            'status' => ['in',[self::STATUS_OK,self::STATUS_OUT] ],
        ])->find();
        if(!empty($mining)) {
            $r['message'] = lang('bfl_mining_exists');
            return $r;
        }

        if($pay_num<$currency_config['min_create_num_limit']) {
            $r['message'] = lang('bfl_mining_min_limit',['num'=>$currency_config['min_create_num_limit']]);
            return $r;
        }

        //实际扣除数量
        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_config['currency_id']);
        if(!$currency_user || $currency_user['num']<$pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        try{
            self::startTrans();

            //添加订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $pay_num,
                'add_time' => time(),
                'status' => self::STATUS_OK,
                'total_release' => 0,
                'total_award' => 0,
                'out_num' => $pay_num * $currency_config['out_mul'],
            ]);
            if(!$item_id) throw new Exception(lang('operation_failed_try_again'));

            if($pay_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],5100,'bfl_mining_buy','out',$pay_num,$item_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$pay_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //补充 【正常,出局可操作】
    static function supply($member_id,$mining_id,$pay_num) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($member_id<=0 || $mining_id<=0) return $r;

        //如果存在可用的矿机 则不能新建
        $allow_mining = self::where([
            'id' => $mining_id,
            'member_id' => $member_id,
            'status' => ['in',[self::STATUS_OK,self::STATUS_OUT] ],
        ])->find();
        if(empty($allow_mining)) return $r;

        $currency_config = BflMiningCurrencyConfig::getCurrencyConfig($allow_mining['currency_id']);
        if(empty($currency_config)) return $r;

        if($pay_num<$currency_config['min_num_limit']) {
            $r['message'] = lang('bfl_mining_min_limit',['num'=>$currency_config['min_num_limit']]);
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$allow_mining['currency_id']);
        if(!$currency_user || $currency_user['num']<$pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }


        try{
            self::startTrans();

            //更新状态 增加矿机量
            $flag = self::where(['id'=>$allow_mining['id'],'num'=>$allow_mining['num'],'status'=>$allow_mining['status']])->update([
                'status' => self::STATUS_OK,
                'num' => ['inc',$pay_num],
                'out_num' => ['inc',$pay_num * $currency_config['out_mul']],
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($pay_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],5102,'bfl_mining_buy_add','out',$pay_num,$allow_mining['id'],0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec('num',$pay_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //解除矿机 【正常,出局可操作】
    static function cancel($member_id,$mining_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if($member_id<=0 || $mining_id<=0) return $r;

        //如果存在可用的矿机 则不能新建
        $allow_mining = self::where([
            'id' => $mining_id,
            'member_id' => $member_id,
            'status' => ['in',[self::STATUS_OK,self::STATUS_OUT] ],
        ])->find();
        if(empty($allow_mining)) {
            $r['message'] = lang('bfl_mining_cancel_deny');
            return $r;
        }

        $currency_config = BflMiningCurrencyConfig::getCurrencyConfig($allow_mining['currency_id']);
        if(empty($currency_config)) return $r;

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$allow_mining['currency_id']);
        if(!$currency_user) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        //累计质押数量 - 静态收益 - 动态收益 -  静态收益*30% -  累计质押数量*10%
        $back_num = keepPoint($allow_mining['num'] - $allow_mining['total_release'] - $allow_mining['total_award'] -
            $allow_mining['num'] * $currency_config['cancel_num_percent'] / 100 - $allow_mining['total_release'] * $currency_config['cancel_release_percent'] / 100,6);
        if($back_num<0.000001) $back_num = 0;

        try{
            self::startTrans();

            //更新状态 返还资产
            $flag = self::where(['id'=>$allow_mining['id'],'status'=>self::STATUS_OK])->update([
                'status' => self::STATUS_CANCEL,
                'cancel_time' => time(),
            ]);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            if($back_num>0) {
                //增加账本 扣除资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],5101,'bfl_mining_cancel','in',$back_num,$allow_mining['id'],0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$back_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //所有的矿机
    static function getList($member_id,$page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;
        if (isInteger($member_id) && $rows <= 100) {
            if($page<1) $page = 1;

            $where = ['a.member_id' => $member_id,'a.status'=>['in',[self::STATUS_OK,self::STATUS_OUT] ] ];
            $field = "a.id,a.currency_id,a.num,a.out_num,a.total_release,a.total_award,a.add_time,a.status,b.currency_name";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $level = BflMiningLevelConfig::where(['currency_id'=>$value['currency_id'],'min_num'=>['elt',$value['num']]])->order('min_num desc')->find();
                    $value['level'] = $level ?: new \ArrayObject();

                    $value['total'] = keepPoint($value['total_release']+$value['total_award'],6);
                    $value['add_time'] = date('m-d H:i', $value['add_time']);

                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }

    static function config($member_id) {
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");

        $currency_list = BflMiningCurrencyConfig::getList();
        if($currency_list) {
            foreach ($currency_list as &$currency) {
                $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency['currency_id']);
                if($currency_user) {
                    $currency['currency_num'] = $currency_user['num'];
                } else {
                    $currency['currency_num'] = 0;
                }
            }
        }
        $r['result'] = $currency_list ?: [];
        return $r;
    }

    static function getOKMining($member_id,$currency_id) {
        return self::where([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'status' => self::STATUS_OK,
        ])->find();
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
