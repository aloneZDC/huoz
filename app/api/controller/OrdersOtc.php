<?php
namespace app\api\controller;
use app\common\model\CurrencyPriceTemp;
use think\Db;
use think\Exception;

class OrdersOtc extends Base
{
    protected $public_action = ['currencys','user','buy_order','sell_order','member_order'];
    protected $is_method_filter = true;

    //检测是否是银商
    public function agent_check() {
        $flag = model('OtcAgent')->is_check($this->member_id);
        if(is_string($flag)) $this->output(10001,$flag);

        $this->output(10000,lang('lan_operation_success'));
    }

    //银商申请详情
    public function agent_info() {
        $agent_status = model('OtcAgent')->check_info($this->member_id);
        $agent_status['title'] = lang('lan_agent_apply_title');
        $agent_status['txt'] = lang('lan_agent_apply_content');
        $agent_status['number'] = model('OtcAgent')->getRandNum($this->member_id);
        $this->output(10000,lang('lan_operation_success'),$agent_status);
    }

    //银商申请
    public function agent_apply() {
        $number = input('number','');
        $attachments = input('post.attachments', '', 'trim,strval');

        $video_url = '';
        if(!empty($attachments)) {
            $attachments_list = $this->oss_base64_upload($attachments, 'agent_apply', 'video');
            if (!empty($attachments_list)) {
                if ($attachments_list['Code'] == 0 || !is_array($attachments_list['Msg']) || count($attachments_list['Msg']) == 0) $this->output(10001,$attachments_list['Msg']);
                $video_url = $attachments_list['Msg'][0];
            }
        }
        if(empty($video_url)) $this->output(10001,lang('lan_please_video'));

        $result = model('OtcAgent')->apply($this->member_id,$video_url,$number);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //获取可交易的货币
    public function currencys() {
        $currency = model('OrdersOtc')->otc_list();
        $this->output(10000,lang('lan_operation_success'),$currency);
    }

    //商家主页
    public function user() {
        $member_id = input('member_id',0,'intval');
        $member = model('OrdersOtc')->seller_info($member_id);
        if(is_string($member)) $this->output(10001,$member);

        $this->output(10000,lang('lan_operation_success'),$member);
    }

    //更新可交易数量
    public function updateavail() {
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
            if(empty($bank_list)) $this->output(10001,lang('please_add_paytype'));
        }

        $this->output(10000,lang('lan_operation_success'),['avail'=>$ordersInfo['avail_num'],'sell_num'=>$sell_num,'bank_list'=>$bank_list]);
    }

    //查看广告详情
    public function orders_info() {
        $orders_id=intval(input('orders_id'));
        $ordersInfo = model('OrdersOtc')->orders_info($this->member_id,$orders_id);
        if(is_string($ordersInfo)) $this->output(10001,$ordersInfo);

        $complete = intval(input('complete'));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $list = model('TradeOtc')->order_trade_list($ordersInfo,$complete,$page,$page_size);

        $this->output(10000,lang('lan_operation_success'),['orders_info'=>$ordersInfo,'list'=>$list]);
    }

    public function orders_trade_log() {
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
        $orders_id=intval(input('orders_id'));
        $result = model('OrdersOtc')->cancel($this->member_id,$orders_id,$this->config['otc_cancel_limit']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }


    //获取OTC当前购买列表
    //buy_order
    public function sell_order(){
        $where = [];
        $currency_id = intval(input('currency_id'));
        $where['a.currency_id'] = $currency_id;
        $where['a.type'] = 'buy';
        $where['a.avail_num'] = ['gt',0];
        $status = intval(input('status'));
        if (!empty($status) && in_array($status, [0,1])) {
            $where['a.status'] = $status;
        } else {
            $where['a.status'] = ['lt',2];
        }
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

//        $price = CurrencyPriceTemp::get_price_currency_id($currency_id,'CNY');
//        if($price>0) {
//            $min_num = keepPoint(100/$price,6);
//            $where['a.avail_num'] = ['gt',$min_num];
//        }

        $list = model('OrdersOtc')->getList($where,'a.price desc,a.orders_id desc',$this->config['repeal_time'],$page,$page_size);

        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //获取OTC当前出售列表
    //sell_order
    public function buy_order() {
        $currency_id = intval(input('currency_id'));
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
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

//        $price = CurrencyPriceTemp::get_price_currency_id($currency_id,'CNY');
//        if($price>0) {
//            $min_num = keepPoint(100/$price,6);
//            $where['a.avail_num'] = ['gt',$min_num];
//        }

        $list = model('OrdersOtc')->getList($where,'a.price asc,a.orders_id desc',$this->config['repeal_time'],$page,$page_size);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //某一商户的当前广告
    public function member_order() {
        $where = [];

        $member_id = input('member_id',0,'intval');
        $where['a.member_id'] = $member_id;
        //$where['a.type'] = 'sell';
        $where['a.avail_num'] = ['gt',0];
        $where['a.status'] = ['lt',2];
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //查询我的当前订单
    public function my_order() {
        $where = [];
        $where['a.currency_id'] = intval(input('currency_id'));
        $where['a.member_id'] = $this->member_id;
        $where['a.status'] = ['lt',2];
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //查询我的历史订单 2已成交 3已撤销
    public function myhistory_order() {
        $where = [];
        $where['a.currency_id'] = intval(input('currency_id'));
        $where['a.member_id'] = $this->member_id;

        $where['a.status'] = ['egt',2];
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');

        $list = model('OrdersOtc')->getList($where,'a.orders_id desc',$this->config['repeal_time'],$page,$page_size);
        $this->output(10000,lang('lan_operation_success'),$list);
    }
}
