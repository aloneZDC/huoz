<?php
namespace app\index\controller;
use app\common\model\CurrencyPriceTemp;
use think\Db;
use think\Exception;

class OrdersOtc extends Base
{
    protected $public_action = [];

    private function currencys(&$currencys,&$currency_id) {
        $currencys = model('OrdersOtc')->otc_list();
        $this->assign('currencys',$currencys);

        $currency_id = intval(input('currency_id'));
        if(empty($currency_id) && !empty($currencys)) {
            $curr = current($currencys);
            $currency_id = $curr['currency_id'];
        }
        $this->assign('currency_id',$currency_id);
    }

    //检测是否是银商
    public function agent_check() {
//        $this->method_filter('post');
//        $flag = model('OtcAgent')->is_check($this->member_id);
//        if(is_string($flag)) $this->output(10001,$flag);

        $this->output(10000,lang('lan_operation_success'));
    }

    //获取可交易的货币
    public function index() {
        $this->method_filter('get');

        $currencys = [];
        $currency_id =0;
        $this->currencys($currencys,$currency_id);

        $currency_type = input('currency_type','sell');
        if(!in_array($currency_type, ['buy','sell'])) $currency_type = 'sell';

        $this->assign('currency_type',$currency_type);
        $this->assign('member_id',$this->member_id);

        $member_status = ['login_status'=>0,'member_name_status'=>0,'member_nick_status'=>0];
        if(!empty($this->member_id)) {
            $member_status['login_status'] = 1;
            $member_status['member_name_status'] = empty($this->member['name']) ? 0 : 1;
            $member_status['member_nick_status'] = empty($this->member['nick']) ? 0 : 1;
        }
        $this->assign('member_status',$member_status);

        $this->assign('my_bank',array_keys(model('Bank')->getList($this->member_id,$this->lang,true)));

        $fun = $currency_type.'_order';
        $list = $this->$fun($currency_id);
        return $this->fetch('otc/index');
    }

    public function check_nick(){
        $this->method_filter('get');

        $nick = Db::name('member')->where(['member_id' => $this->member_id])->getField('nick');
        if(empty($nick)) $this->output(10001,lang('lan_nickname_first'));

        $this->output(10000,'');
    }

    //商家主页
    public function user() {
        $this->method_filter('get');
        $currency_type = input('currency_type','sell');
        if(!in_array($currency_type, ['buy','sell'])) $currency_type = 'sell';

        $member_id = input('member_id',0,'intval');
        $member = model('OrdersOtc')->seller_info($member_id);
        if(is_string($member)) $this->output(10001,$member);

        $where['a.member_id'] = $member_id;
        $where['a.type'] = $currency_type;
        $where['a.avail_num'] = ['gt',0];
        $where['a.status'] = ['lt',2];

        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $count = true;
        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size,$count,true);
        $pages = $this->getPages($count,$page,$page_size);

        $this->assign(['members'=>$member,'list'=>$list,'pages'=>$pages]);

        $this->assign('currency_type',$currency_type);
        $this->assign('member_id',$member_id);

        $member_status = ['login_status'=>0,'member_name_status'=>0,'member_nick_status'=>0];
        if(!empty($this->member_id)) {
            $member_status['login_status'] = 1;
            $member_status['member_name_status'] = empty($this->member['name']) ? 0 : 1;
            $member_status['member_nick_status'] = empty($this->member['nick']) ? 0 : 1;
        }
        $this->assign('member_status',$member_status);

        $this->assign('my_bank',array_keys(model('Bank')->getList($this->member_id,$this->lang,true)));

        return $this->fetch('otc/user');
    }

    //更新可交易数量
    public function updateavail() {
        $this->method_filter('post');

        $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
        if(!$verify_info) $this->output(30100,lang('lan_user_authentication_first'));
        if($verify_info['verify_state']==2) $this->output(40100,lang('lan_user_authentication_first_wait'));
        if($verify_info['verify_state']!=1) $this->output(30100,lang('lan_user_authentication_first'));

        $orders_id=intval(input('orders_id'));
        $ordersInfo = Db::name('orders_otc')->where(['orders_id'=>$orders_id])->find();
        if(!$ordersInfo || $ordersInfo['avail_num']<=0) $this->output(10001,lang('lan_orders_not_exists'));
        if($ordersInfo['member_id']==$this->member_id) $this->output(10001,lang('lan_order_otc_order_self'));

        $bank_list = [];
        $sell_num = 0;
        if($ordersInfo['type']=='buy') {
            $currency = Db::name('currency')->field('currency_otc_sell_fee')->where(['currency_id'=>$ordersInfo['currency_id']])->find();
            if($currency) {
                $currency_num = model('CurrencyUser')->getNum($this->member_id,$ordersInfo['currency_id'],'num');
                $sell_num = keepPoint($currency_num * (1 - $currency['currency_otc_sell_fee'] / 100),6);
            }

            $seller_bank = model('Bank')->getList($this->member_id,$this->lang,true);
            foreach (['bank', 'wechat', 'alipay'] as $type) {
                if (!empty($ordersInfo[$type]) && !empty($seller_bank[$type])) {
                    $seller_bank[$type]['bankname'] = $type;
                    $bank_list[] = $seller_bank[$type];
                }
            }
        }
        $this->output(10000,lang('lan_operation_success'),['avail'=>$ordersInfo['avail_num'],'sell_num'=>$sell_num,'bank_list'=>$bank_list]);
    }

    //查看广告详情
    public function orders_info() {
        $this->method_filter('get');

        $orders_id=intval(input('orders_id'));
        $ordersInfo = model('OrdersOtc')->orders_info($this->member_id,$orders_id,$this->lang,true);
        if(is_string($ordersInfo)) $this->output(10001,$ordersInfo);

        $complete = intval(input('complete'));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');

        $count = true;
        $list = model('TradeOtc')->order_trade_list($ordersInfo,$complete,$page,$page_size,$count);
        $pages = $this->getPages($count,$page,$page_size);

        $cancel_fe= Db::name('currency')->where(['currency_id'=>$ordersInfo['currency_id']])->value('currency_otc_cancel_fee');
        $cancel_fee=floatval($cancel_fe).'%';

        $this->assign(['orders_info'=>$ordersInfo,'list'=>$list,'pages'=>$pages,'cancel_fee'=>$cancel_fee,'complete'=>$complete]);

        return $this->fetch('otc/orders_info');
    }

    public function orders_trade_log() {
        $this->method_filter('get');

        $orders_id=intval(input('orders_id'));
        $ordersInfo = model('OrdersOtc')->orders_info($this->member_id,$orders_id);
        if(is_string($ordersInfo)) $this->output(10001,$ordersInfo);

        $complete = intval(input('complete'));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $list = model('TradeOtc')->order_trade_list($ordersInfo,$complete,$page,$page_size);

        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //撤销广告发布
    public function cancel() {
        $this->method_filter('post');

        $orders_id=intval(input('orders_id'));
        $result = model('OrdersOtc')->cancel($this->member_id,$orders_id,$this->config['otc_cancel_limit']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }


    //获取OTC当前购买列表
    private function buy_order($currency_id){
        $where = [];

        $where['a.currency_id'] = $currency_id;
        $where['a.type'] = 'buy';
        $where['a.avail_num'] = ['gt',0];
        $status = intval(input('status'));
        if (!empty($status) && in_array($status, [0,1])) {
            $where['a.status'] = $status;
        } else {
            $where['a.status'] = ['lt',2];
        }

        $money_type = input('money_type');
        if(!empty($money_type) && in_array($money_type, ['bank','alipay','wechat'])) $where['a.'.$money_type] = ['gt',0];

        $price = floatval(input('price'));
        if(!empty($price)) {
            $where['a.min_money'] = ['elt',$price];
            $where['a.max_money'] = ['egt',$price];
        }

        $price = CurrencyPriceTemp::get_price_currency_id($currency_id,'CNY');
        if($price>0) {
            $min_num = keepPoint(100/$price,6);
            $where['a.avail_num'] = ['gt',$min_num];
        }

        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $count = true;
        $list = model('OrdersOtc')->getList($where,'a.price desc,a.orders_id desc',$this->config['repeal_time'],$page,$page_size,$count);

        $pages = $this->getPages($count,$page,$page_size);
        $this->assign(['list'=>$list,'pages'=>$pages]);
    }

    //获取OTC当前出售列表
    private function sell_order($currency_id) {
        $where = [];
        $where['a.currency_id'] = $currency_id;
        $where['a.type'] = 'sell';
        $where['a.avail_num'] = ['gt',0];
        $status = intval(input('status'));
        if (!empty($status) && in_array($status, [0,1])) {
            $where['a.status'] = $status;
        } else {
            $where['a.status'] = ['lt',2];
        }

        $money_type = input('money_type');
        if(!empty($money_type) && in_array($money_type, ['bank','alipay','wechat'])) $where['a.'.$money_type] = ['gt',0];

        $price = floatval(input('price'));
        if(!empty($price)) {
            $where['a.min_money'] = ['elt',$price];
            $where['a.max_money'] = ['egt',$price];
        }

        $price = CurrencyPriceTemp::get_price_currency_id($currency_id,'CNY');
        if($price>0) {
            $min_num = keepPoint(100/$price,6);
            $where['a.avail_num'] = ['gt',$min_num];
        }

        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $count = true;
        $list = model('OrdersOtc')->getList($where,'a.price asc,a.orders_id desc',$this->config['repeal_time'],$page,$page_size,$count,true);
        $pages = $this->getPages($count,$page,$page_size);
        $this->assign(['list'=>$list,'pages'=>$pages]);

        return $list;
    }

    // 我的广告
    public function my_order(){
        $currencys = [];
        $currency_id =0;
        $this->currencys($currencys,$currency_id);

        $cancel_fee= Db::name('currency')->where(['currency_id'=>$currency_id])->value('currency_otc_cancel_fee');
        if($cancel_fee) {
            $cancel_fee=floatval($cancel_fee).'%';
        } else {
            $cancel_fee = '';
        }

        $where = [];
        $where['a.currency_id'] = $currency_id;
        $where['a.member_id'] = $this->member_id;
        $where['a.status'] = ['lt',2];
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $count = true;
        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size,$count);

        $pages = $this->getPages($count,$page,$page_size);
        $this->assign(['list'=>$list,'pages'=>$pages,'cancel_fee'=>$cancel_fee]);

        return $this->fetch('otc/my_order');
    }

    public function myhistory_order(){
        $currencys = [];
        $currency_id =0;
        $this->currencys($currencys,$currency_id);

        $where = [];
        $where['a.currency_id'] = $currency_id;
        $where['a.member_id'] = $this->member_id;
        $where['a.status'] = ['egt',2];

        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $count = true;
        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size,$count);

        $pages = $this->getPages($count,$page,$page_size);
        $this->assign(['list'=>$list,'pages'=>$pages]);

        return $this->fetch('otc/myhistory_order');
    }
}
