<?php
//理财包

namespace app\common\model;


use think\Exception;
use think\Log;
use think\Model;

class TransferToFinancial extends Model
{
    /**
     * 理财包释放
     * @param $users_currency
     * @param $percent 释放比例
     * @param $is_static_award 是否奖励
     * @return mixed
     * @throws \think\exception\PDOException
     */
    public static function release($users_currency,$config,$award_config,$award_config_max,$today_config) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $percent = $config['to_is_financial_percent'];
        $base_num = $users_currency['uc_financial'];
        $num = keepPoint($base_num * $percent /100,6);
        if($num<0.000001) return $r;

        $currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],true);
        try{
            self::startTrans();
            //增加释放记录
            $log_id = self::add_financial('out',$users_currency['member_id'],$users_currency['currency_id'],$num,$percent,$base_num,$users_currency['cu_id'],$config['asset_type']);
            if(!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //减少 理财包资产
            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],$currency_field=>$users_currency[$currency_field]])->setDec($currency_field,$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加账本
            $accountbook_log = AccountBook::add_accountbook($users_currency['member_id'], $users_currency['currency_id'], 302, 'financial_release', "in", $num, $log_id,0);
            if(!$accountbook_log) throw new Exception(lang('operation_failed_try_again'));

            //增加资产
            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],'num'=>$users_currency['num']])->setInc('num',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();

            //是否开启上级奖励 添加奖励明细
            if($config['is_static_award']==1) {
                self::release_award($users_currency,$num,$config,$award_config,$award_config_max,$today_config);
            }

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
     * 理财宝上级释放奖励
     * @param $user_id
     * @param $base_num
     */
    public static function release_award($users_currency,$base_num,$config,$award_config,$award_config_max,$today_config) {
        if(empty($award_config)) return false;

        //获取上级用户
        $list = MemberBind::field('a.member_id,a.level,m.recommand_num')->alias('a')->where([
                    'a.child_id' => $users_currency['member_id'],
                    'a.level' => ['elt',$award_config_max['max_level']],
                ])->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
                ->order('a.level asc')->select();
        if(count($list)<=0) return [];

        foreach ($list as $member) {
            //根据上级推荐人数获取配置
            $member_config = self::get_member_award_config($member,$award_config,$award_config_max);
            if(empty($member_config)) continue;

            //当前用户是我的几代  当前用户能拿几代
            if($member['level']<=$member_config['level']){
                if($config['recommand_min_num']>0) {
                    $currency_field = TransferToAssetConfig::get_currency_field($config['asset_type'],true);
                    $member_users_currency = CurrencyUser::getCurrencyUser($member['member_id'],$users_currency['currency_id']);
                    if(empty($member_users_currency) ||$member_users_currency[$currency_field]<$config['recommand_min_num']) continue;
                }

                $num = keepPoint($base_num * $member_config['percent']/100,6);
                if($num>=0.000001) {
                    //添加奖励明细
                    TransferFinancialAwardDetail::add_item($member['member_id'],$users_currency['currency_id'],$num,$config['asset_type'],$today_config['today_start'],$users_currency['member_id'],$base_num,$member_config['percent'],$member['level']);
                }
            }
        }
    }

    public static function get_member_award_config($member,$award_config,$award_config_max){
        $member_level_config = [];
        foreach ($award_config as $config) {
            if($config['level']==$member['level']) $member_level_config = $config;
        }
        if($member['recommand_num']>$award_config_max['max_total']) $member['recommand_num'] = $award_config_max['max_total'];

        if(!isset($award_config[$member['recommand_num']])) return [];

        $config = $award_config[$member['recommand_num']];
        //我能拿的代数 比  当前代数低   没有奖励
        if($config['level']<$member['level']) return [];

        //我能拿的代数 比  当前代数高  以当前代数为准
        if($config['level']>=$member['level'] && !empty($member_level_config)) return $member_level_config;

        return $config;
    }

    /**
     * @param $type in转入理财包 out理财包释放 award理财包奖励
     * @param $user_id
     * @param $currency_id
     * @param $num
     * @param $percent
     * @param $third_id
     * @return int|string
     */
    public static function add_financial($type,$user_id,$currency_id,$num,$percent,$base_num,$third_id,$asset_type) {
        return self::insertGetId([
            'ttf_type' => $type,
            'ttf_user_id' => $user_id,
            'ttf_currency_id' => $currency_id,
            'ttf_num' => $num,

            'ttf_percent' => $percent,
            'ttf_time' => time(),

            'ttf_base_num' => $base_num,
            'ttf_third_id' => $third_id,
            'ttf_asset_type' => $asset_type,
        ]);
    }

    static function get_list($user_id,$asset_type,$currency_id=0,$type='',$income_type,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (!empty($asset_type) && isInteger($user_id) && $rows <= 100) {
            $where = [
                'a.ttf_user_id' => $user_id
            ];
            if(!empty($currency_id)) {
                $where['a.ttf_currency_id'] = $currency_id;
            }

            if(!empty($type) && in_array($type,['in','out','award'])) {
                $where['a.ttf_type'] = $type;
            }
            if(!empty($income_type)) {
                if($income_type=='in') {
                    $where['a.ttf_type'] = [ 'in',['in','award'] ];
                } elseif($income_type=='out') {
                    $where['a.ttf_type'] = 'out';
                }
            }
            $where['a.ttf_asset_type'] = $asset_type;
            $field = "a.ttf_type,a.ttf_currency_id,a.ttf_num,a.ttf_time,b.currency_mark,b.currency_logo,a.ttf_percent";
            $list = self::field($field)->alias('a')->where($where)
                ->join(config("database.prefix") . "currency b", "a.ttf_currency_id=b.currency_id", "LEFT")
                ->page($page, $rows)->order("a.ttf_id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['ttf_time'] = date('Y-m-d H:i:s',$value['ttf_time']);
                    $value['title'] = '';
                    if ($value['ttf_type'] == 'in') {
                        $value['title'] = lang('financial_in');
                    } elseif ($value['ttf_type'] == 'out') {
                        $value['title'] = lang('financial_out');
                    } elseif ($value['ttf_type']=='award') {
                        $value['title'] = lang('financial_award');
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

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'ttf_user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'ttf_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}