<?php
/**
 *兑换到锁仓
 */
namespace app\api\controller;
use think\Db;
use think\Exception;

class Exchange extends Base
{
    protected $is_method_filter = true;
    protected $from_currency = 'XRP';
    protected $to_currency = 'GAC';

    //创新区首页
    public function index() {
        $user_num = 0;
        $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$this->member_id])->find();
        if($boss_plan_info) $user_num = $boss_plan_info['xrpz_new_num'];

        if($this->config['is_percent_open']==1) {
            $time = time();
            if($time<strtotime('2019-04-11')) {
                $percent = 0.1;
            } else {
                $percent = Boss_pan_receive_xrpz_new;
            }
            $percent_info = Db::name('boss_plan_percent')->where(['member_id'=>$this->member_id])->find();
            if($percent_info) $percent = $percent_info['percent'];
            $percent = $percent *100;
        } else {
            $percent = 0;
        }

        $forzen_num = $reward_num = $internal_buy = 0;
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$this->to_currency])->find();
        if($currency) {
            $currency_user = Db::name('currency_user')->field('lock_num,num_award,internal_buy')->where(['member_id'=>$this->member_id,'currency_id'=>$currency['currency_id']])->find();
            if($currency_user) {
                $forzen_num = $currency_user['lock_num'];
                $reward_num = $currency_user['num_award'];
                $internal_buy = $currency_user['internal_buy'];
            }
        }

        $allow_percent = [];
        for($start=0;$start<=100;$start+=5){
            $allow_percent[] = $start.'%';
        }

        $lang_notice = lang('lan_exchange_time_start');
        $lang_notice = str_replace('START_HOUR', $this->config['start_exchange_hour'], $lang_notice);
        $lang_notice = str_replace('END_HOUR', ($this->config['end_exchange_hour']-12), $lang_notice);

        $is_percent_show = intval($this->config['is_percent_show']); //是否显示 奖励配比
        $is_exchange_show = intval($this->config['is_exchange_show']); //是否显示兑换专区
        $is_reward_show = intval($this->config['is_reward_show']); //是否显示糖果专区
        $is_internal_buy_show = intval($this->config['is_internal_buy_show']); //是否显示糖果专区

        $this->output(10000,lang('lan_operation_success'),[
            'user_num'=>$user_num,'percent'=>$percent,'forzen_num'=>$forzen_num,'reward_num'=>$reward_num,'allow_percent'=>$allow_percent,'internal_buy_num'=>$internal_buy,
            'lang_notice'=>$lang_notice,'gac_exchange_name'=>lang('lan_gac_exchange'),'gac_reward_name'=>lang('lan_gac_reward'),'gac_internal_buy_name' => lang('lan_internal_buy_name'),
            'is_percent_show' => $is_percent_show,'is_exchange_show' => $is_exchange_show,'is_reward_show' => $is_reward_show,'is_internal_buy_show'=>$is_internal_buy_show,
        ]);
    }

    //更改奖励配比
    public function set_percent() {
        if($this->config['is_percent_open']!=1) $this->output(10001,lang('lan_close'));

        $percent = intval(input('percent'));
        $allow_percent = [];
        for($start=0;$start<=100;$start+=5){
            $allow_percent[] = $start;
        }

        if(!in_array($percent, $allow_percent)) $this->output(10000,lang('lan_not_allow_percent'));

        Db::startTrans();
        try{
            $percent_value = keepPoint($percent/100,2);

            $percentInfo= Db::name('boss_plan_percent')->lock(true)->where(['member_id'=>$this->member_id])->find();
            $flag = false;
            if($percentInfo) {
                $flag = Db::name('boss_plan_percent')->where(['member_id'=>$this->member_id])->setField('percent',$percent_value);
            } else {
                $flag = Db::name('boss_plan_percent')->insertGetId([
                    'member_id' => $this->member_id,
                    'percent' => $percent_value,
                ]);
            }
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            $this->output(10000,lang('lan_operation_success'));
        } catch (Exception $e) {
            Db::rollback();
            $this->output(10001,$e->getMessage());
        }
    }

    //兑换GAC首页
    public function exchange_index() {
        $user_num = 0;
        $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$this->member_id])->find();
        if($boss_plan_info) $user_num = $boss_plan_info['xrpz_new_num'];

        $cny_price = $this->getCnyPrice();
        if(empty($cny_price) || $cny_price['from_currency_cny']<=0 || $cny_price['to_currency_cny']<=0) $this->output(10001,lang('lan_Network_request_failed'));

        $ratio = keepPoint($cny_price['from_currency_cny']/$cny_price['to_currency_cny'],3);
        $this->output(10000,lang('lan_operation_success'),[
            'from_name'=>lang('lan_exchange_from_name'),
            'from_currency'=> $cny_price['from_currency'],
            'to_currency'=> $cny_price['to_currency'],
            'from_cny' => $cny_price['from_currency_cny'],
            'to_cny' => $cny_price['to_currency_cny'],
            'ratio' => $ratio,
            'from_user_num' => $user_num,
        ]);
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

        $to_cny = 0;
        if(empty($this->config['gac_price_cny'])) {
            $avg_price = 0;
    
            $today_time = strtotime(date('Y-m-d'));
            $yestday_time = $today_time - 86400;


            $to_from_price = Db::name('trade')->where(['currency_id'=>$to_currency_info['currency_id'],'currency_trade_id'=>$from_currency_info['currency_id'],'type'=>'buy','add_time'=>['between',[$yestday_time,$today_time]]])->avg('price');
            if(!$to_from_price) {
                $to_from_price = Db::name('trade')->where(['currency_id'=>$to_currency_info['currency_id'],'currency_trade_id'=>$from_currency_info['currency_id'],'type'=>'buy'])->order('trade_id desc')->value('price');
            }
            if($to_from_price) $avg_price = $to_from_price;
            if($avg_price>0) $to_cny = $from_cny * $avg_price * $this->config['gac_price_over'];
        } else {
            $to_cny = $this->config['gac_price_cny'] * $this->config['gac_price_over']; //自定义人民币价格
        }

        return [
            'from_currency_cny' => keepPoint($from_cny,2),
            'to_currency_cny' => keepPoint($to_cny,2),
            'fee' => $fee,
            'from_currency' => $from_currency_info['currency_name'],
            'to_currency' => $to_currency_info['currency_name'],
        ];
    }

    //兑换操作
    public function to_exchange() {
        $lang_notice = lang('lan_exchange_time_start');
        $lang_notice = str_replace('START_HOUR', $this->config['start_exchange_hour'], $lang_notice);
        $lang_notice = str_replace('END_HOUR', ($this->config['end_exchange_hour']-12), $lang_notice);

        $da = date("w");
        if ($da == '6' && $this->config['exchange_day6']) $this->output(10001,$lang_notice);
        if ($da == '0' && $this->config['exchange_day7']) $this->output(10001,$lang_notice);

        $hour = intval(date('H'));
        if($hour<$this->config['start_exchange_hour'] || $hour>($this->config['end_exchange_hour']-1)) $this->output(10001,$lang_notice);

        $from_num = keepPoint(floatval(input('from_num',0)),3);
        if($from_num<=0) $this->output(10001,lang('lan_number_must_gt_0'));
        //if($from_num<1) $this->output(10001,lang('lan_tolock_min_number').' 1');

        $cny_price = $this->getCnyPrice();
        if(empty($cny_price) || $cny_price['from_currency_cny']<=0 || $cny_price['to_currency_cny']<=0) $this->output(10001,lang('lan_Network_request_failed'));

        $from_currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$cny_price['from_currency']])->find();
        $to_currency = Db::name('currency')->field('currency_id,currency_name,is_tolock')->where(['currency_mark'=>$cny_price['to_currency']])->find();

        if(!$from_currency || !$to_currency) $this->output(10001,lang('lan_Network_request_failed'));
        if($to_currency['is_tolock']!=1) $this->output(10001,lang('lan_tolock_stop'));

        $ratio = keepPoint($cny_price['from_currency_cny']/$cny_price['to_currency_cny'],3);

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd,true);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);
        
        Db::startTrans();
        try{
            $boss_plan_info = Db::name('boss_plan_info')->lock(true)->where(['member_id'=>$this->member_id])->find();
            if(!$boss_plan_info || $from_num>$boss_plan_info['xrpz_new_num']) throw new Exception(lang('lan_your_credit_is_running_low'));
            
            $to_num = keepPoint($from_num * $ratio,3);
            $fee = 0;
            if($cny_price['fee']>0) $fee = keepPoint($to_num * $cny_price['fee'],3);
            $to_actual = keepPoint($to_num - $fee,3);

            //增加兑换记录
            $flag = Db::name('currency_exchange_log')->insertGetId([
                'member_id' => $this->member_id,
                'currency_id' => $from_currency['currency_id'],
                'to_currency_id' => $to_currency['currency_id'],
                'from_num' => $from_num,
                'from_cny' => $cny_price['from_currency_cny'],
                'to_cny' => $cny_price['to_currency_cny'],
                'ratio' => $ratio,
                'num' => $to_num,
                'actual' => $to_actual,
                'fee' => $cny_price['fee'] * 100,
                'add_time' => time(),
                'update_time' => time(),
                'status' => 1,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //减少创新区资产
            $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$this->member_id])->setDec('xrpz_new_num',$from_num);

            //创新区资产减少记录
            $flag = Db::name('innovate_log')->insertGetId([
                'l_member_id' => $this->member_id,
                'l_value' => '-'.$from_num,
                'l_time' => time(),
                'l_title' => 'lan_exchange_title_log',
                'l_type' => 20,
                'l_type_explain' => 'lan_exchange_title_ratio:'.$ratio.$to_currency['currency_name'],
            ]);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            //增加GAC资产
            $currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->find();
            $flag = false;
            if($currency_user) {
                $flag = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->setInc('lock_num',$to_actual);
            } else {
                $flag = Db::name('currency_user')->insertGetId([
                    'member_id' => $this->member_id,
                    'currency_id' => $to_currency['currency_id'],
                    'num' => 0,
                    'lock_num' => $to_actual,
                ]);
            }
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            //增加GAC冻结记录
            $flag = Db::name('currency_gac_forzen')->insertGetId([
                'member_id' => $this->member_id,
                'num' => $to_actual,
                'ratio' => $ratio,
                'from_num' => $from_num,
                'type' => 20,
                'title' => 'lan_exchange_title_log',
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

    public function gac_forzen() {
        $release_ratio = $this->config['gac_forzen_release'] * 100;
        $user_num = 0;
        $start_time = strtotime(date('Y-m-d'));
        $today_num = Db::name('currency_gac_forzen')->where(['member_id'=>$this->member_id,'add_time'=>['gt',$start_time],'type'=>30])->sum('num');

        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$this->to_currency])->find();
        if($currency) {
            $currency_user = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$currency['currency_id']])->find();
            if($currency_user) $user_num = $currency_user['lock_num'];
        }
            
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('currency_gac_forzen')->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
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
                } elseif ($value['type']<30) { //20
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

    //GAC糖果赠送释放
    public function gac_reward_forzen() {
        $release_ratio = $this->config['gac_reward_forzen_release'] * 100;
        $user_num = 0;
        $start_time = strtotime(date('Y-m-d'));
        $today_num = Db::name('currency_gac_reward_forzen')->where(['member_id'=>$this->member_id,'add_time'=>['gt',$start_time],'type'=>30])->sum('num');

        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$this->to_currency])->find();
        if($currency) {
            $currency_user = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$currency['currency_id']])->find();
            if($currency_user) $user_num = $currency_user['num_award'];
        }
            
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('currency_gac_reward_forzen')->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
        if($list) {
            foreach ($list as $key => &$value) {
                $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                $value['title'] = lang($value['title']);
                if($value['type']<20){
                    $value['middle'] = '';
                    $value['bottom'] = '';
                    $value['num_type'] = '+';
                } elseif ($value['type']<30) {
                    $value['middle'] = lang('lan_exchange_title_ratio').keepPoint($value['ratio'],3).$currency['currency_name'];
                    $value['bottom'] = lang('lan_consumption').keepPoint($value['from_num'],3).$this->from_currency;
                    $value['num_type'] = '+';
                } elseif($value['type']>=30) {
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

    public function exchange_log() {
        $member =  Db::name('member')->field('phone,email')->where(['member_id'=>$this->member_id])->find();
        if(!$member) $this->output(10001,lang('lan_Network_request_failed'));
        if(empty($member['phone'])) {
            $member['phone'] = substr($member['email'],0,3).'****'.substr($member['email'],-7);
        } else {
            $member['phone'] = substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
        }
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('currency_exchange_log')
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
}