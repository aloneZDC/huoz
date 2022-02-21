<?php
//红包项目  持仓量记录表
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class HongbaoKeepLog extends Model {
    /**
     * @param string $type reg_award注册赠送 t_reg_award下级注册赠送 loan贷款申请 release翻生释放 air_release云梯释放 air_diff云梯级差奖 air_jackpot云梯周分红 air_fee云梯手续费 dnc_convert DNC兑换 transfer_in 互转转入 transfer_out互转转出
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
    static function reg_award($user_id,$pid) {
        $config = HongbaoConfig::get_key_value();
        if(empty($config)) return true;

        $is_open = isset($config['reg_award_open']) ? $config['reg_award_open'] : 2;
        $award_currency_mark = isset($config['reg_award_currency']) ? $config['reg_award_currency'] : '';
        $award_num = isset($config['reg_award_num']) ? $config['reg_award_num'] : 0;
        $award_num = self::reward_special($award_num);
        $award_num_pids = [
            'award_num1' => isset($config['reg_award_num1']) ? $config['reg_award_num1'] : 0,
            'award_num2' => isset($config['reg_award_num2']) ? $config['reg_award_num2'] : 0,
        ];
        if($is_open!=1 || $award_currency_mark=='') return true;

        $award_currency = Currency::where(['currency_mark'=>$award_currency_mark])->field('currency_id,currency_mark')->find();
        if(!$award_currency) return true;

        try{
            //赠送自己
            if($award_num>0) {
                $users_currency = CurrencyUser::getCurrencyUser($user_id,$award_currency['currency_id']);
                if(!$users_currency) throw new Exception("获取资产错误");

                $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id']])->setInc('keep_num',$award_num);
                if(!$flag) throw new Exception("添加资产错误");

                //添加持仓记录
                $flag = self::add_log('reg_award',$user_id,$award_currency['currency_id'],$award_num,$user_id,0,0);
                if(!$flag) throw new Exception("添加持仓记录错误");
            }

            //修改为本人方舟交易额达到10000时 才赠送上级
//            if($pid>0) {
//                for ($count=1;$count<=2;$count++) {
//                    $cur_award_num = $award_num_pids['award_num'.$count];
//                    if($cur_award_num<0.000001) continue;
//
//                    $users_parent = Member::where(['member_id'=>$pid])->field('member_id,pid')->find();
//                    if(!$users_parent) throw new Exception("用户不存在");
//
//                    $users_currency = CurrencyUser::getCurrencyUser($users_parent['member_id'],$award_currency['currency_id']);
//                    if(!$users_currency) throw new Exception("获取资产错误");
//
//                    $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id']])->setInc('keep_num',$cur_award_num);
//                    if(!$flag) throw new Exception("添加资产错误");
//
//                    //添加持仓记录
//                    $flag = self::add_log('t_reg_award',$users_parent['member_id'],$award_currency['currency_id'],$cur_award_num,$user_id,0,0);
//                    if(!$flag) throw new Exception("添加持仓记录错误");
//
//                    $pid = $users_parent['pid'];
//                    if($pid<=0) break;
//                }
//            }
            return true;
        } catch(Exception $e) {
            Log::write($e->getMessage());
            return false;
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
                if($type=='ins') {
                    $where['a.type'] = [ 'in',['reg_award','t_reg_award','loan','contract_income','dnc_convert','transfer_in'] ];
                } elseif($type=='out') {
                    $where['a.type'] = [ 'in',['release','contract_order', 'air_release', 'air_jackpot', 'air_fee', 'transfer_out'] ];
                }
            }
            $field = "a.number,a.create_time,a.type";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['title'] = '';
                    if($value['type']=='reg_award') {
                        $value['title'] = lang('reg_award');
                    } elseif($value['type']=='t_reg_award'){
                        $value['title'] = lang('t_reg_award');
                    } elseif($value['type']=='release'){
                        $value['title'] = lang('flop_release');
                    } elseif($value['type']=='loan'){
                        $value['title'] = lang('loan');
                    } elseif($value['type']=='contract_order'){
                        $value['title'] = lang('contract_order');
                    } elseif($value['type']=='contract_income'){
                        $value['title'] = lang('contract_income');
                    } elseif($value['type'] == 'air_release') {
                        $value['title'] = lang('air_release');
                    } elseif($value['type'] == 'air_jackpot') {
                        $value['title'] = lang('air_jackpot');
                    } elseif($value['type'] == 'air_fee') {
                        $value['title'] = lang('air_fee');
                    } elseif($value['type'] == 'forever_contract_cancel') {
                        $value['title'] = lang('forever_contract_cancel');
                    } elseif ($value['type']=='dnc_convert') {
                        $value['title'] = lang('asset_in');
                    } elseif ($value['type'] == 'transfer_in') {
                        $value['title'] = lang('asset_transfer_in');
                    } elseif ($value['type'] == 'transfer_out') {
                        $value['title'] = lang('asset_transfer_out');
                    }

                    if(in_array($value['type'],['reg_award','t_reg_award','loan','contract_income','forever_contract_cancel','dnc_convert', 'transfer_in'])) {
                        $value['number'] = '+'.$value['number'];
                    } elseif (in_array($value['type'],['release','contract_order', 'air_release', 'air_jackpot', 'air_fee', 'transfer_out'])) {
                        $value['number'] = '-'.$value['number'];
                    }
                    $value['currency_name'] = lang('keep_num');
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

    static function num($user_id) {
//        $r['code'] = SUCCESS;
//        $r['message'] = lang("data_success");
        $r = [
            'currency_name' => lang('keep_num'),
            'keep_num' => 0,
            'cny' => 0,
            'currency_logo' => FlopOrders::KOIC_LOGO,
            'currency_id' => Currency::XRP_PLUS_ID,
            'exchange_switch' => 2,
            'convert_switch' => 2,
            'exchange_type' => 'keep_num'
        ];

        $hongbao_config = HongbaoConfig::get_key_value();
        $currency = Currency::where(['currency_mark'=>$hongbao_config['reg_award_currency']])->field('currency_id,currency_name,currency_logo')->find();
        if($currency) {
            $user_currency = CurrencyUser::getCurrencyUser($user_id,$currency['currency_id']);
            if($user_currency) {
                $r['keep_num'] = $user_currency['keep_num'];
                $r['currency_logo'] = $currency['currency_logo'];
            }
            $currency_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
            if($currency_price) $r['cny'] = keepPoint($currency_price * $r['keep_num'],2);

            $transfer_config = (new CurrencyUserTransferConfig)->where('currency_id', $currency['currency_id'])->where('type', 'keep_num')->value('is_open');
            if (!empty($transfer_config)) {
                $r['exchange_switch'] = $transfer_config;
            }

            $convert_config = ConvertKeepConfig::where('to_currency_id',$currency['currency_id'])->find();
            if($convert_config) {
                $r['convert_switch'] = 1;
            }
        }
        return $r;
    }

    static function num_list($user_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = [];

        $hongbao_config = HongbaoConfig::get_key_value();
        $currency = Currency::where(['currency_mark'=>$hongbao_config['reg_award_currency']])->field('currency_id,currency_name,currency_logo')->find();
        if($currency) {
            $r['code'] = SUCCESS;
            $r['message'] = '';
            $cny = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
            $user_currency = CurrencyUser::getCurrencyUser($user_id,$currency['currency_id']);
            $keep_num = $user_currency ? $user_currency['keep_num'] : 0;
            $r['result'][] = [
                'currency_mark' => lang('keep_num'),
                'currency_name' => lang('keep_num'),
                'currency_logo' => $currency['currency_logo'],
                'num' => 0,
                'keep_num' => $keep_num,
                'cny' => $cny,
                'money' => keepPoint($keep_num*$cny,2),
            ];
        }
        return $r;
    }

    static function reward_special($award_num) {
        $today = date('Y-m-d');
        $day_award = [
            '2020-04-02' => 12000,
            '2020-04-03' => 11000,
            '2020-04-04' => 10000,
            '2020-04-05' => 10000,
            '2020-04-06' => 10000,
            '2020-04-07' => 9000,
            '2020-04-08' => 8000,
            '2020-04-09' => 7000,
            '2020-04-10' => 6000,
            '2020-04-11' => 5000,
            '2020-04-12' => 4000,
        ];
        if(isset($day_award[$today])) return $day_award[$today];
        return $award_num;
    }

    static function reward_config() {
        $res = [
            'currency_name' => '',
            'award_num' => 0,
        ];

        $config = HongbaoConfig::get_key_value();
        if(empty($config)) return $res;

        $is_open = isset($config['reg_award_open']) ? $config['reg_award_open'] : 2;

        $award_currency_mark = isset($config['reg_award_currency']) ? $config['reg_award_currency'] : '';
        $award_num = isset($config['reg_award_num']) ? $config['reg_award_num'] : 0;
        $award_num = self::reward_special($award_num);
        if($is_open!=1 || $award_currency_mark=='') return $res;

        $award_currency = Currency::where(['currency_mark'=>$award_currency_mark])->field('currency_id,currency_mark,currency_name')->find();
        if($award_currency) {
            $res['currency_name'] = $award_currency['currency_name'];
            $res['award_num'] = $award_num;
        }
        return $res;
    }


    //查询上级是否已经赠送过赠送金
    static function isPidRegAward($user_id) {
        $info = self::where(['third_id' => $user_id, 'type' => 't_reg_award'])->find();
        if($info) return true;
        return false;
    }

    //检测是否满足赠送上级条件
    static function checkPidRegAward($user_id,$hongbao_config){
        $num = FlopTrade::where(['type'=>'buy','member_id'=>$user_id,'currency_id'=>Currency::KOI_ID])->sum('num');
        if($num>=intval($hongbao_config['flop_trade_reg_award_min'])) return true;
        return false;
    }

    //赠送上级赠送金
    static function pidRegAward($user_id,$hongbao_config)
    {
        if (empty($hongbao_config)) return true;

        $is_open = isset($hongbao_config['reg_award_open']) ? $hongbao_config['reg_award_open'] : 2;
        $award_currency_mark = isset($hongbao_config['reg_award_currency']) ? $hongbao_config['reg_award_currency'] : '';
        $award_num_pids = [
            'award_num1' => isset($hongbao_config['reg_award_num1']) ? $hongbao_config['reg_award_num1'] : 0,
            'award_num2' => isset($hongbao_config['reg_award_num2']) ? $hongbao_config['reg_award_num2'] : 0,
        ];
        if ($is_open != 1 || $award_currency_mark == '') return true;

        $award_currency = Currency::where(['currency_mark'=>$award_currency_mark])->field('currency_id,currency_mark')->find();
        if(!$award_currency) return true;

        $cur_user_id = $user_id;
        for ($count = 1; $count <= 2; $count++) {
            $cur_award_num = $award_num_pids['award_num' . $count];
            if ($cur_award_num < 0.000001) continue;

            $users_child = Member::where(['member_id' => $cur_user_id])->field('member_id,pid')->find();
            if (!$users_child || $users_child['pid']<=0) return true;

            $users_currency = CurrencyUser::getCurrencyUser($users_child['pid'], $award_currency['currency_id']);
            if (!$users_currency) return false;

            $flag = CurrencyUser::where(['cu_id' => $users_currency['cu_id']])->setInc('keep_num', $cur_award_num);
            if (!$flag) return false;

            //添加持仓记录
            $flag = self::add_log('t_reg_award', $users_child['pid'], $award_currency['currency_id'], $cur_award_num, $user_id, 0, 0);
            if (!$flag) return false;

            $cur_user_id = $users_child['pid'];
        }
        return true;
    }
}
