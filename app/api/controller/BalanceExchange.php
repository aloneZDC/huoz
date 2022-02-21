<?php
/**
 *内购
 */
namespace app\api\controller;
use think\Db;
use think\Exception;

class BalanceExchange extends Base
{
//    protected $public_action = ['index','to_buy','buy_log','log'];
//    protected $is_method_filter = true;

    protected $from_currency = 'XRP';
    protected $to_currency = 'GAC';

    //GAC内购首页
    public function index() {
       //$this->member_id=806889;
       // $plan_info = Db::name('boss_plan_info')->field('num')->where(['member_id'=>$this->member_id])->find();
        $config_time=Db::name('boss_config')->field('value')->where(['key'=>'boss_old_user_confirm_time'])->find();
        if(empty($config_time)){
            $config_time['value']='1555344000';//2019-04-16
        }
        $where['status']=1;
        $where['add_time']=array('<',$config_time['value']);
        $where['member_id']=$this->member_id;
        $plan_info = Db::name('boss_plan_buy')->field('max(total) as num')->where($where)->find();
        $bouns=Db::name('boss_bouns_log')->field('sum(num) as num')->where(['member_id'=>$this->member_id,'receive_status'=>1])->find();
        $has_buy = Db::name('xrp_exchange_gac')->where(['member_id'=>$this->member_id])->find();
        if(!empty($has_buy)){
            $user_num='0.00';
        }elseif(!empty($plan_info['num'])&&($plan_info['num']>$bouns['num'])){
            $user_num=$plan_info['num']-$bouns['num'];
        }else{
            $user_num='0.00';
        }
        $cny_price = $this->getCnyPrice();
        if(empty($cny_price) || $cny_price['from_currency_cny']<=0 || $cny_price['to_currency_cny']<=0) $this->output(10001,lang('lan_Network_request_failed'));
        $ratio = keepPoint($cny_price['from_currency_cny']/$cny_price['to_currency_cny'],3);
        $this->output(10000,lang('lan_operation_success'),[
            'from_name'=>lang('lan_principal_from_name'),
            'from_currency'=> $cny_price['from_currency'],
            'to_currency'=> $cny_price['to_currency'],
            'from_cny' => $cny_price['from_currency_cny'],
            'to_cny' => $cny_price['to_currency_cny'],
            'ratio' => $ratio,
            'from_user_num' => $user_num,
        ]);
    }

    //兑换操作
    public function to_buy() {
       // $plan_info = Db::name('boss_plan_info')->field('num')->where(['member_id'=>$this->member_id])->find();
        $config_arr['boss_old_user_remain_money_switch']="";
        $config_arr['boss_old_user_confirm_time']="";
        $config_list=Db::name('boss_config')->where(['key'=>array('in',['boss_old_user_confirm_time','boss_old_user_remain_money_switch'])])->select();
        if(!empty($config_list)){
            $config_arr=array_column($config_list,'value','key');
        }
        if(empty($config_arr['boss_old_user_confirm_time'])){
            $config_arr['boss_old_user_confirm_time']='1555344000';//2019-04-16
        }
        //判断开关是否开启
        if(empty($config_arr['boss_old_user_remain_money_switch'])||($config_arr['boss_old_user_remain_money_switch']==2)){
            $this->output(10001,lang('lan_orders_illegal_request'));
        }
        $where['status']=1;
        $where['add_time']=array('<',$config_arr['boss_old_user_confirm_time']);
        $where['member_id']=$this->member_id;
        $plan_info = Db::name('boss_plan_buy')->field('max(total) as num')->where($where)->find();
        $bouns=Db::name('boss_bouns_log')->field('sum(num) as num')->where(['member_id'=>$this->member_id,'receive_status'=>1])->find();
        $has_buy = Db::name('xrp_exchange_gac')->where(['member_id'=>$this->member_id])->find();
        //判断是否兑换过
        if(!empty($has_buy)){
            $this->output(10001,lang('lan_internal_buy_gt_limit'));
        }
        if(!empty($plan_info['num'])&&($plan_info['num']>$bouns['num'])){
            $from_num=$plan_info['num']-$bouns['num'];
        }else{
            $this->output(10001,lang('lan_internal_buy_gt_limit'));
        }
        $cny_price = $this->getCnyPrice();
        if(empty($cny_price) || $cny_price['from_currency_cny']<=0 || $cny_price['to_currency_cny']<=0) $this->output(10001,lang('lan_Network_request_failed'));
        $from_currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$cny_price['from_currency']])->find();
        $to_currency = Db::name('currency')->field('currency_id,currency_name,is_tolock')->where(['currency_mark'=>$cny_price['to_currency']])->find();

        if(!$from_currency || !$to_currency) $this->output(10001,lang('lan_Network_request_failed'));
        $ratio = keepPoint($cny_price['from_currency_cny']/$cny_price['to_currency_cny'],3);
        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);
        $fee = 0;
        $to_num = keepPoint($from_num * $ratio,3);
        $to_actual = keepPoint($to_num - $fee,3);
        Db::startTrans();
        try{

            //增加内购记录
            $buy_id = Db::name('xrp_exchange_gac')->insertGetId([
                'member_id' => $this->member_id,
                'currency_id' => $from_currency['currency_id'],
                'to_currency_id' => $to_currency['currency_id'],
                'from_num' => $from_num,
                'from_cny' => $cny_price['from_currency_cny'],
                'to_cny' => $cny_price['to_currency_cny'],
                'ratio' => $ratio,
                'num' => $to_num,
                'actual' => $to_actual,
                'fee' =>$fee,
                'add_time' => time(),
                'update_time' => time(),
                'status' => 1,
            ]);

            //增加GAC兑换资产
            $currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->find();

            if($currency_user) {

                $flag = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->setInc('remaining_principal',$to_actual);
            } else {

                $flag = Db::name('currency_user')->insertGetId([
                    'member_id' =>$this->member_id,
                    'currency_id' =>$to_currency['currency_id'],
                    'num' => 0,
                    'remaining_principal' =>$to_actual,
                ]);

            }

            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            //增加GAC内购日志
            $flag = Db::name('currency_gac_log')->insertGetId([
                'member_id' => $this->member_id,
                'num' => $to_actual,
                'ratio' => $ratio,
                'from_num' => $from_num,
                'type' => 20,
                'title' => 'lan_remaining_title_log',
                'add_time' => time(),
            ]);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));
            Db::commit();
            $this->output(10000,lang('lan_operation_success'));
        } catch (Exception $e) {
            Db::rollback();
            $this->output(10001,$e->getMessage());
        }
    }

    public function buy_log() {
       //$this->member_id=806889;
        $member =  Db::name('member')->field('phone,email')->where(['member_id'=>$this->member_id])->find();
        if(!$member) $this->output(10001,lang('lan_Network_request_failed'));
        if(empty($member['phone'])) {
            $member['phone'] = substr($member['email'],0,3).'****'.substr($member['email'],-7);
        } else {
            $member['phone'] = substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
        }
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('xrp_exchange_gac')
                ->field('num,add_time,from_num,actual,from_cny,to_cny')
                ->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
        if($list) {
            foreach ($list as $key => &$value) {
                $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                $value['from_currency'] = $this->from_currency;
                $value['to_currency'] = $this->to_currency;
                $value['phone'] = $member['phone'];
                $value['fee'] = $value['num'] - $value['actual'];
                $value['total_cny'] = keepPoint($value['from_num'] * $value['from_cny'],2);
                $value['status_txt'] = lang('lan_complete');
                $value['from_num'] = keepPoint($value['from_num'],3);
                $value['num'] = keepPoint($value['num'],3);
                $value['actual'] = keepPoint($value['actual'],3);
            }
        } else {
            $list = [];
        }

        $this->output(10000,lang('lan_operation_success'),$list);
    }

    public function log() {
//       //$this->member_id=806889;
        $ratio=Db::name('boss_config')->where(['key'=>'xrp_exchange_gac'])->find();
        $release_ratio = $ratio['value'] * 100;
        $user_num = 0;
        $start_time = strtotime(date('Y-m-d'));
        $today_num = Db::name('currency_gac_log')->where(['member_id'=>$this->member_id,'add_time'=>['gt',$start_time],'type'=>30])->sum('num');
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$this->to_currency])->find();
        if($currency) {
            $currency_user = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$currency['currency_id']])->find();
            if($currency_user) $user_num = $currency_user['remaining_principal'];
        }
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');
        $list = Db::name('currency_gac_log')->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();

        if($list) {
            foreach ($list as $key => &$value) {
                $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                $value['title'] = lang($value['title']);
                $value['middle'] = '';
                $value['bottom'] = '';
                if($value['type']==1){
                    $value['title'] = lang('lan_mutual_transfer1').$value['title'];
                    $value['num_type'] = '-';
                } elseif($value['type']==2) {
                    $value['title'] = lang('lan_mutual_transfer2').$value['title'].lang('lan_mutual_transfer3');
                    $value['num_type'] = '+';
                } elseif($value['type']<20) {
                    $value['num_type'] = '+';
                } elseif($value['type']<30) { //20
                    $value['middle'] = lang('lan_exchange_title_ratio').keepPoint($value['ratio'],3).$currency['currency_name'];
                    $value['bottom'] = lang('lan_consumption').keepPoint($value['from_num'],3).$this->from_currency;
                    $value['num_type'] = '+';
                } elseif($value['type']>=30) { //30
                    $value['middle'] = lang('lan_forzen_release_ratio').keepPoint($value['ratio'],2).'%';
                    $value['bottom'] = lang('lan_lan_frozen').keepPoint($value['from_num'],3).$this->to_currency;
                    $value['num_type'] = '-';
                }
                $value['num'] = rtrim(rtrim($value['num'],'0'),'.');
                $value['currency_name'] = $this->to_currency;
                unset($value['ratio'],$value['from_num'],$value['member_id']);
            }
        } else {
            $list = [];
        }
        $this->output(10000,lang('lan_operation_success'),['user_num'=>$user_num,'release_ratio'=>$release_ratio.'%','currency_name'=>$this->to_currency,'today_num'=>$today_num,'list'=>$list]);
    }

    //获取币种对应的人民币价格
    private function getCnyPrice() {
        $fee = 0;
        $from_currency_info = Db::name('currency')->field('currency_id,currency_name,currency_mark')->where(['currency_mark'=>$this->from_currency])->find();
        $to_currency_info = Db::name('currency')->field('currency_id,currency_name,is_tolock')->where(['currency_mark'=>$this->to_currency])->find();
        //获取XRP的人民币价格
        $from_cny = getCnyPrice($from_currency_info['currency_mark'].'USDT'); //XRP的人民币价格
        if(!empty($from_cny['close'])) {
            $from_cny = $from_cny['close'];
        } else {
            $from_cny = 0;
        }
        $to_cny =Db::name('boss_config')->field('value')->where(['key'=>'remain_gac_price_cny'])->find(); //自定义人民币价格
        return [
            'from_currency_cny' => keepPoint($from_cny,2),
            'to_currency_cny' => keepPoint($to_cny['value'],2),
            'fee' => $fee,
            'from_currency' => $from_currency_info['currency_name'],
            'to_currency' => $to_currency_info['currency_name'],
        ];
    }
}