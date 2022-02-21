<?php
/**
 *内购
 */
namespace app\api\controller;
use think\Db;
use think\Exception;

class InternalBuy extends Base
{
    protected $is_method_filter = true;
    protected $from_currency = 'XRP';
    protected $to_currency = 'GAC';

    //GAC内购首页
    public function index() {
        $user_num = 0;
        $from_currency_info = Db::name('currency')->field('currency_id,currency_name,currency_mark')->where(['currency_mark'=>$this->from_currency])->find();
        if($from_currency_info) {
            $currency_user = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$from_currency_info['currency_id']])->find();
            if($currency_user) $user_num = $currency_user['num'];
        }

        $cny_price = $this->getCnyPrice();
        if(empty($cny_price) || $cny_price['from_currency_cny']<=0 || $cny_price['to_currency_cny']<=0) $this->output(10001,lang('lan_Network_request_failed'));

        $ratio = keepPoint($cny_price['from_currency_cny']/$cny_price['to_currency_cny'],3);

        $limit_num = $this->getLimit();
        $has_buy = $this->getHasBuy();
        if($limit_num>=$has_buy) {
            $yu = keepPoint($limit_num - $has_buy,3);
        } else {
            $yu = 0;
        }
        $this->output(10000,lang('lan_operation_success'),[
            'from_name'=>lang('lan_internal_buy_from_name'),
            'from_currency'=> $cny_price['from_currency'],
            'to_currency'=> $cny_price['to_currency'],
            'from_cny' => $cny_price['from_currency_cny'],
            'to_cny' => $cny_price['to_currency_cny'],
            'ratio' => $ratio,
            'from_user_num' => $user_num,
            'limit_num' => ''.$limit_num,
            'has_buy' => ''.$has_buy,
            'yu' => ''.$yu,
        ]);
    }

    //内购操作
    public function to_buy() {
        $time = time();
        if($time<strtotime($this->config['internal_buy_start_time'])) {
            $this->output(10001,lang('lan_start_time').$this->config['internal_buy_start_time']);
        } elseif($time>strtotime($this->config['internal_buy_stop_time'])) {
            $this->output(10001,lang('lan_stop'));
        }
        
        $from_num = keepPoint(floatval(input('from_num',0)),3);
        if($from_num<=0) $this->output(10001,lang('lan_number_must_gt_0'));

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
            
        $limit_num = $this->getLimit();
        $has_buy = $this->getHasBuy();
        $yu = keepPoint($limit_num - $has_buy,3);
        if($yu<=0) $this->output(10001,lang('lan_internal_buy_gt_limit'));

        $to_num = keepPoint($from_num * $ratio,3);
        $fee = 0;
        if($cny_price['fee']>0) $fee = keepPoint($to_num * $cny_price['fee'],3);
        $to_actual = keepPoint($to_num - $fee,3);

        if($to_num>$yu) $this->output(10001,lang('lan_internal_buy_gt_limit2'));

        Db::startTrans();
        try{
            $currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$this->member_id,'currency_id'=>$from_currency['currency_id']])->find();
            if(!$currency_user || $from_num>$currency_user['num']) throw new Exception(lang('lan_your_credit_is_running_low'));
            
            //增加内购记录
            $buy_id = Db::name('gac_internal_buy')->insertGetId([
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
            if(!$buy_id) throw new Exception(lang('lan_network_busy_try_again'));

            //账本减少记录
            $result = model('AccountBook')->addLog([
                'member_id' => $this->member_id,
                'currency_id' => $from_currency['currency_id'],
                'type'=> 29,
                'content' => 'lan_internal_buy',
                'number_type' => 2,
                'number' => $from_num,
                'fee' => $fee,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $buy_id,
            ]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            //减少资产
            $flag = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$from_currency['currency_id']])->setDec('num',$from_num);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            
            //增加GAC内购资产
            $currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->find();
            $flag = false;
            if($currency_user) {
                $flag = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$to_currency['currency_id']])->setInc('internal_buy',$to_actual);
            } else {
                $flag = Db::name('currency_user')->insertGetId([
                    'member_id' => $this->member_id,
                    'currency_id' => $to_currency['currency_id'],
                    'num' => 0,
                    'internal_buy' => $to_actual,
                ]);
            }
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            //增加GAC内购日志
            $flag = Db::name('currency_gac_internal_buy')->insertGetId([
                'member_id' => $this->member_id,
                'num' => $to_actual,
                'ratio' => $ratio,
                'from_num' => $from_num,
                'type' => 20,
                'title' => 'lan_internal_buy_title_log',
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
        $member =  Db::name('member')->field('phone,email')->where(['member_id'=>$this->member_id])->find();
        if(!$member) $this->output(10001,lang('lan_Network_request_failed'));
        if(empty($member['phone'])) {
            $member['phone'] = substr($member['email'],0,3).'****'.substr($member['email'],-7);
        } else {
            $member['phone'] = substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
        }
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('gac_internal_buy')
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
        $release_ratio = $this->config['gac_internal_buy_release'] * 100;
        $user_num = 0;
        $start_time = strtotime(date('Y-m-d'));
        $today_num = Db::name('currency_gac_internal_buy')->where(['member_id'=>$this->member_id,'add_time'=>['gt',$start_time],'type'=>30])->sum('num');

        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>$this->to_currency])->find();
        if($currency) {
            $currency_user = Db::name('currency_user')->where(['member_id'=>$this->member_id,'currency_id'=>$currency['currency_id']])->find();
            if($currency_user) $user_num = $currency_user['internal_buy'];
        }
            
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = Db::name('currency_gac_internal_buy')->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
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

    //获取内购额
    // 2、V5可购买5万枚；
    // 3、V4可购买3万枚；
    // 4、V3可购买1万枚；
    // 5、V2可购买8000枚；
    // 6、V1可购买5000枚；
    // 7、凡2019.4.16日（不含16日）之前所有投30票的，都有资格可购买2000枚；
    private function getLimit() {
        $level = 0;
        $boss_plan_info = Db::name('boss_plan_info')->field('level,votes')->where(['member_id'=>$this->member_id])->find();
        if($boss_plan_info) $level = $boss_plan_info['level'];

        $limit_num = 0;
        if($level>0) {
            if(isset($this->config['internal_buy_limit_v'.$level])) $limit_num = intval($this->config['internal_buy_limit_v'.$level]);
        } else {
            if($boss_plan_info['votes']>=30) {
                $stop_time = strtotime($this->config['internal_buy_stop_time']);
                $boss_plan_buy = Db::name('boss_plan_buy')->where(['member_id'=>$this->member_id,'status'=>1,'votes'=>['egt',30]])->order('id asc')->find();
                if($boss_plan_buy && $boss_plan_buy['add_time']<=$stop_time) $limit_num = intval($this->config['internal_buy_limit_votes30']);
            }
        }

        return $limit_num;
    }

    private function getHasBuy() {
        $num = 0;
        $has_buy = Db::name('gac_internal_buy')->where(['member_id'=>$this->member_id])->sum('num');
        if($has_buy) $num = $has_buy;

        return $num;
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
        if(empty($this->config['internal_buy_gac_price_cny'])) {
            $avg_price = 0;
    
            $today_time = strtotime(date('Y-m-d'));
            $yestday_time = $today_time - 86400;

            $to_from_price = Db::name('trade')->where(['currency_id'=>$to_currency_info['currency_id'],'currency_trade_id'=>$from_currency_info['currency_id'],'type'=>'buy','add_time'=>['between',[$yestday_time,$today_time]]])->avg('price');
            if(!$to_from_price) {
                $to_from_price = Db::name('trade')->where(['currency_id'=>$to_currency_info['currency_id'],'currency_trade_id'=>$from_currency_info['currency_id'],'type'=>'buy'])->order('trade_id desc')->value('price');
            }
            if($to_from_price) $avg_price = $to_from_price;
            if($avg_price>0) $to_cny = $from_cny * $avg_price;
        } else {
            $to_cny = $this->config['internal_buy_gac_price_cny']; //自定义人民币价格
        }

        return [
            'from_currency_cny' => keepPoint($from_cny,2),
            'to_currency_cny' => keepPoint($to_cny,2),
            'fee' => $fee,
            'from_currency' => $from_currency_info['currency_name'],
            'to_currency' => $to_currency_info['currency_name'],
        ];
    }
}