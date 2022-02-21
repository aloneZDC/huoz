<?php
namespace app\index\controller;
use think\Db;
use think\Exception;

class TradeOtc extends Base
{
    protected $public_action = [];
    protected $public_action2 = ['trade_list']; //不需验证是否实名认证

    public function _initialize() {
        parent::_initialize();

        $action = strtolower($this->request->action());
        if(!in_array($action, $this->public_action2)) {
            $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
            if(!$verify_info) $this->output(30100,lang('lan_user_authentication_first'));
            if($verify_info['verify_state']==2) $this->output(40100,lang('lan_user_authentication_first_wait'));
            if($verify_info['verify_state']!=1) $this->output(30100,lang('lan_user_authentication_first'));

            $nick = Db::name('member')->where(['member_id'=>$this->member_id])->value('nick');
            if(empty($nick)) $this->output(20100,lang('lan_nickname_first'));
        }

    }

    //发布广告 step1
    public function publish_ad()
    {
        $this->method_filter('get');

        $currency_type = input("currency_type",'sell');

        $currencys = model('OrdersOtc')->otc_list();
        $currency_id = intval(input('currency_id'));
        if(empty($currency_id)) {
            $curr = current($currencys);
            $currency_id = $curr['currency_id'];
        }

        $currency = model('OrdersOtc')->otc_info($currency_id,$this->config['otc_cancel_limit']);
        if(is_string($currency)) $this->output(10001,$currency);

        $user = [];
        $user['currency_num'] = model('CurrencyUser')->getNum($this->member_id,$currency_id);
        $user['currency_num'] = keepPoint($user['currency_num'],6);
        $user['sell_num'] = keepPoint($user['currency_num'] /(1 + $currency['currency_otc_sell_fee'] / 100),6);

        $banklist = model('Bank')->getList($this->member_id,$this->lang,true);

        $this->assign(['currencys'=>$currencys,'currency'=>$currency,'user'=>$user,'banklist'=>$banklist,'currency_type'=>$currency_type,'currency_id'=>$currency_id]);
        return $this->fetch('otc/publish_ad');

    }

    //发布广告 step2
    public function add_ad() {
        $this->method_filter('get');

        $currency_type = input("currency_type",'sell');

        $currencys = model('OrdersOtc')->otc_list();
        $currency_id = intval(input('currency_id'));
        if(empty($currency_id)) {
            $curr = current($currencys);
            $currency_id = $curr['currency_id'];
        }

        $currency = model('OrdersOtc')->otc_info($currency_id,$this->config['otc_cancel_limit']);
        if(is_string($currency)) $this->output(10001,$currency);

        $user = [];
        $user['currency_num'] = model('CurrencyUser')->getNum($this->member_id,$currency_id);
        $user['currency_num'] = keepPoint($user['currency_num'],6);
        $user['sell_num'] = keepPoint($user['currency_num'] /(1 + $currency['currency_otc_sell_fee'] / 100),6);

        $banklist = model('Bank')->getList($this->member_id,$this->lang,true);
        $this->assign(['currencys'=>$currencys,'currency'=>$currency,'user'=>$user,'banklist'=>$banklist]);

        return $this->fetch('otc/add_ad');
    }

    //获取币种OTC详情及用户资产
    public function icon_info() {
        $this->method_filter('get');

        $currency_id = intval(input('post.currency_id'));

        $currency = model('OrdersOtc')->otc_info($currency_id,$this->config['otc_cancel_limit']);
        if(is_string($currency)) $this->output(10001,$currency);

        $user = [];
        $user['currency_num'] = model('CurrencyUser')->getNum($this->member_id,$currency_id);
        $user['currency_num'] = keepPoint($user['currency_num'],6);
        $user['sell_num'] = keepPoint($user['currency_num'] /(1 + $currency['currency_otc_sell_fee'] / 100),6);

        $this->assign(['currency'=>$currency,'user'=>$user]);
        return $this->fetch();
    }

    //用户的交易列表
    public function trade_list() {
        $this->method_filter('get');

        $status = input('status','');
        $page = input('page',1,'intval,filter_page');
        $page_size = input('page_size',10,'intval,filter_page');
        $orders_id = input('orders_id',0);

        $count = true;
        $list = model('TradeOtc')->trade_list($this->member_id,$status,$page,$page_size,$orders_id,$count);

        $pages = $this->getPages($count,$page,$page_size);
        $this->assign(['list'=>$list,'pages'=>$pages,'orders_id'=>$orders_id]);
        return $this->fetch('otc/trade_list');
    }

    //获取交易订单详情
    public function trade_info() {
        $this->method_filter('get');

        $trade_id = input("trade_id",0,'intval');
        if(empty($trade_id)) $this->output(10001,lang('lan_operation_failure'));

        $tradeInfo = model('TradeOtc')->trade_info($this->member_id,$trade_id,$this->lang,$this->config['repeal_time'],true);
        if(is_string($tradeInfo)) $this->output(10001,$tradeInfo);

        $jim_config = model('Jim')->getTradeConfig($this->member_id,$trade_id);

        $this->assign(['jim_config'=>$jim_config,'tradeInfo'=>$tradeInfo]);
        return $this->fetch('otc/tradeInfo');
    }

    public function sell_num_check() {
        $this->method_filter('post');

        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));
        $type = strval(input('type','sell')); //暂未使用

        $sell_confirm_time = $this->config['sell_confirm_time'];
        if($type =='buy'){
            $sell_confirm_time =  $this->config['buy_confirm_time'];

            $flag = model('OrdersOtc')->buyCheckBefore($this->member_id,$currency_id,$this->config['buy_orders_otc']);
            if(is_string($flag)) $this->output(10001,$flag);
        }

        $currency = model('OrdersOtc')->sellCheck($type,$this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$sell_confirm_time);
        if(is_string($currency)) $this->output(10001,$currency);

        //银商不收手续费
//        $currency['currency_otc_buy_fee'] = $currency['currency_otc_sell_fee'] = 0;

        $fee = keepPoint($tradenum * ($currency['currency_otc_sell_fee'] / 100), 6);
        $all_num = keepPoint($tradenum + $fee,6);
        $data = [
            'price' => $price,
            'num' => $tradenum,
            'all_num' => $all_num,
            'fee_num' => $fee,
            'money' => keepPoint($price * $tradenum, 2),
            'min_money' => keepPoint($min_money, 2),
            'max_money' => keepPoint($max_money, 2),
            'fee' => $currency['currency_otc_sell_fee'],
            'type' => $type,
        ];
        $this->output(10000, lang('lan_operation_success'), $data);
    }

    //挂卖单
    public function sell_num(){
        $this->method_filter('post');

        $NECaptchaValidate= input("validate");
        //if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));

        $phone_code = strval(input('phone_code',''));
        $senderLog = model('Sender')->auto_check($this->member_id,'sell_num',$phone_code,false);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $orders_id = model('OrdersOtc')->addSell($this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$this->config['sell_confirm_time']);
        if(is_string($orders_id)) $this->output(10001,$orders_id);

        //设置验证码为已用
        model('Sender')->hasUsed($senderLog['id']);

        $this->output(10000,lang('lan_operation_success'),$orders_id);
    }

    //挂买单
    public function buy_num() {
        $this->method_filter('post');

        $NECaptchaValidate= input("validate");
        //if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10102, lang('lan_Picture_verification_refresh'));

        $price=keepPoint(floatval(input('price')),4);
        $tradenum=keepPoint(floatval(input('num')),6);
        $currency_id=intval(input('currency_id'));
        $min_money = floatval(input('min_money'));
        $max_money = floatval(input('max_money'));
        $order_message=strval(input('order_message'));

        $phone_code = strval(input('phone_code',''));
        $senderLog = model('Sender')->auto_check($this->member_id,'sell_num',$phone_code,false);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));
        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $flag = model('OrdersOtc')->buyCheckBefore($this->member_id,$currency_id,$this->config['buy_orders_otc']);
        if(is_string($flag)) $this->output(10001,$flag);

        $orders_id = model('OrdersOtc')->addBuy($this->member_id,$currency_id,$price,$tradenum,$min_money,$max_money,$order_message,$this->config['buy_confirm_time']);
        if(is_string($orders_id)) $this->output(10001,$orders_id);

        $this->output(10000,lang('lan_operation_success'),$orders_id);
    }

    //买入-某卖单
    public function buy(){
        $this->method_filter('post');

        $NECaptchaValidate= input("validate");
        //if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $buynum= floatval(input('num'));
        $orders_id=intval(input('orders_id'));

        $result = model('TradeOtc')->buy($this->member_id,$orders_id,$buynum,$this->config['otc_day_cancel'],$this->config['otc_trade_online'],$this->config['buy_confirm_time'],$this->config['sell_confirm_time']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),$result);
    }

    //卖出-某买单
    public function sell() {
        $NECaptchaValidate= input("validate");
        //if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $orders_id = intval(input('post.orders_id'));
        $tradenum = floatval(input('post.num'));
        $pwd = input('post.pwd','');
        $money_type = input('money_type','');

        $pwd = md5($pwd);
        $result = model('TradeOtc')->sell($this->member_id,$orders_id,$tradenum,$pwd,$money_type,$this->config['otc_trade_online'],$this->config['buy_confirm_time'],$this->config['sell_confirm_time']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),$result);
    }

    public function day_cancel_count() {
        $time = strtotime(date('Y-m-d'));
        $count = Db::name('trade_otc')->where(['member_id'=>$this->member_id,'status'=>4,'add_time'=>['gt',$time]])->count();
        $flag = $this->config['otc_day_cancel'] - $count;

        $this->output(10000,lang('lan_operation_success'),['flag'=>$flag]);
    }

    //点击取消 --只有买家才能进行操作
    public function cancel() {
        $this->method_filter('post');

        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->user_cancel($this->member_id,$trade_id,$this->config['otc_day_cancel']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //买家支付后取消
    public function cancel_payment() {
        $this->method_filter('post');

        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->user_pay_cancel($this->member_id,$trade_id,$this->config['otc_day_cancel']);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //选择支付方式
    public function choose_bank(){
        $this->method_filter('post');

        $money_type = input('money_type','');
        $trade_id = input("trade_id",0,'intval');


        $tradeInfo = model('TradeOtc')->choose_bank($this->member_id,$trade_id,$money_type);
        if(is_string($tradeInfo)) $this->output(10001,$tradeInfo);
        //买广告,不需要买家选择支付方式
        if(isset($tradeInfo['is_orders_buy'])) $money_type = $tradeInfo['money_type'];

        $money_type = explode(":", $money_type);
        $bankInfo = model('Bank')->getInfoByType($money_type[1],$money_type[0],$this->lang);

        $money_zn = $this->toChineseNumber($tradeInfo['money']);
        $this->output(10000, lang('lan_operation_success'), ['money' => $tradeInfo['money'], 'money_zn' => $money_zn, 'pay_number' => $tradeInfo['pay_number'], 'bank' => $bankInfo]);
    }

    //点击付款 -- 只有买家才能操作
    public function pay() {
        $this->method_filter('post');

        $trade_id = input("trade_id",0,'intval');

        $result = model('TradeOtc')->pay($this->member_id,$trade_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //放行 -- 只有卖家才能操作
    public function fangxing() {
        $this->method_filter('post');

        $NECaptchaValidate= input("validate");
        //if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10102, lang('lan_Picture_verification_refresh'));

        $pwd = input('pwd','','strval');
        if(empty($pwd)) $this->output(10001,lang('lan_Incorrect_transaction_password'));

        $checkPwd = model('Member')->checkMemberPwdTrade($this->member_id,$pwd);
        if(is_string($checkPwd)) $this->output(10001,$checkPwd);

        $trade_id = input("trade_id",0,'intval');
        $result = model('TradeOtc')->seller_fangxing($this->member_id,$trade_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //申诉 已付款状态15分钟后
    public function appeal() {
        $this->method_filter('post');

        $trade_id = input("trade_id",0,'intval');
        $allege_type = input('type',0,'intval');
        $content = input('content','');

        $trade_pic = input('img','');
        if(!empty($trade_pic)) {
            $upload = $this->oss_base64_upload($trade_pic, 'appeal');
            if ($upload['Code'] === 0 || count($upload['Msg']) == 0 || empty($upload)) $this->output(10001,lang('lan_upload_qr_code_error'));
            $trade_pic = $upload['Msg'][0];
        }

        $result = model('TradeOtc')->appeal($this->member_id,$trade_id,$allege_type,$content,$this->config['repeal_time'],$trade_pic);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    private function toChineseNumber($money)
    {
        $money = keepPoint($money, 2);
        $cnynums = array("零", "壹", "贰", "叁", "肆", "伍", "陆", "柒", "捌", "玖");
        $cnyunits = array("圆", "角", "分");
        $cnygrees = array("拾", "佰", "仟", "万", "拾", "佰", "仟", "亿");
        list($int, $dec) = explode(".", $money, 2);

        //解决小数点不正确
        $dec_temp = $dec;

        $dec_text = '';
        if ($dec_temp[0] > 0) {
            $dec_text .= $cnynums[$dec_temp[0]] . '角';
        } elseif ($dec_temp[0] == 0 && $dec_temp[1] > 0) {
            $dec_text .= $cnynums[$dec_temp[0]];
        }
        $dec_text .= $dec_temp[1] > 0 ? $cnynums[$dec_temp[1]] . "分" : '';

        $dec = [0, 0];
        $dec = array_filter(array($dec[1], $dec[0]));
        $ret = array_merge($dec, array(implode("", $this->cnyMapUnit(str_split($int), $cnygrees)), ""));
        $ret = implode("", array_reverse($this->cnyMapUnit($ret, $cnyunits)));
        return str_replace(array_keys($cnynums), $cnynums, $ret) . $dec_text;
    }

    private function cnyMapUnit($list, $units)
    {
        $ul = count($units);
        $xs = array();
        foreach (array_reverse($list) as $x) {
            $l = count($xs);
            if ($x != "0" || !($l % 4)) {
                $n = ($x == '0' ? '' : $x);
                if($l>0) {
                    if(isset($units[($l - 1) % $ul])) {
                        $n .= $units[($l - 1) % $ul];
                    }
                }
            } else{
                if(isset($xs[0]) && isset($xs[0][0]) && is_numeric($xs[0][0])) {
                    $n = $x;
                } else {
                    $n = '';
                }
            }
            array_unshift($xs, $n);
        }
        return $xs;
    }

    ####old

    //交易记录导出 前1000条
    public function trade_export()
    {
        $this->method_filter('get');

        $expCellName = array(
            array('only_number', '订单号'),
            array('type', '交易類型'),
            array('currency_name', '币种'),
            array('money', '總價'),
            array('price', '單價'),
            array('num', '数量'),
            array('fee', '手續費'),
            array('add_time', '時間'),
            array('status', '狀態'),
            array('phone', '交易對象'),
        );

        $expTableData = Db::name('trade_otc')->alias('a')->where(['a.member_id'=>$this->member_id])
                        ->field('a.only_number,a.type,c.currency_name,a.money,a.price,a.num,a.fee,a.add_time,a.status,b.name as username,b.phone,b.email')
                        ->join('__MEMBER__ b','a.other_member=b.member_id','LEFT')
                        ->join('__CURRENCY__ c','a.currency_id=c.currency_id','LEFT')
                        ->order("trade_id desc")->limit(1000)->select();
        if ($expTableData) {
            foreach ($expTableData as $key => $value) {
                $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
                $value['status'] = lang('lan_trade_otc_status' . $value['status']);
                if ($value['type'] == 'sell') {
                    $value['type'] = "出售";
                } elseif ($value['type'] == 'buy') {
                    $value['type'] = "购买";
                }

                if (empty($value['phone'])) {
                    $value['phone'] = substr($value['email'], 0, 3) . '****' . substr($value['email'], -5);
                } else {
                    $value['phone'] = substr($value['phone'], 0, 3) . '****' . substr($value['phone'], 9, 2);
                }
                $value['only_number'] = '#'.$value['only_number'];
                $expTableData[$key] = $value;
            }
        } else {
            $expTableData = [];
        }

        require_once(APP_PATH.'/../extend/PHPExcel/PHPExcel.php');

        $expTitle = iconv('utf-8', 'gb2312', "User");//文件名称
        $fileName = $this->member_id.date('_YmdHis');//or $xlsTitle 文件名称可根据自己情况设定
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);

        $objPHPExcel = new \PHPExcel();
        $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

        $objPHPExcel->getActiveSheet(0)->mergeCells('A1:' . $cellName[$cellNum - 1] . '1');//合并单元格
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle . '  Export time:' . date('Y-m-d H:i:s'));
        for ($i = 0; $i < $cellNum; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '2', $expCellName[$i][1]);
        }

        for ($i = 0; $i < $dataNum; $i++) {
            for ($j = 0; $j < $cellNum; $j++) {
                $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 3), $expTableData[$i][$expCellName[$j][0]]);
            }
        }

        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $fileName . '.xls"');
        header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    /*
    //取消已支付订单
    public  function  cancel_payment(){
        $trade_id = intval(I('trade_id'));
        if (empty($trade_id)) self::outputs(0, '记录不存在');

        $tradeInfo = M('Trade_otc')->where(['trade_id' => $trade_id])->find();
        if (empty($tradeInfo)) self::outputs(0, '记录不存在');

        if ($tradeInfo['status'] != 1) self::outputs(0, '非已支付订单');

        $other_tradeInfo = M('Trade_otc')->where(['trade_id' => $tradeInfo['other_trade_id']])->find();
        if (empty($tradeInfo)) self::outputs(0, '数据异常,被诉方不存在');
        $this->sellWin($tradeInfo, $other_tradeInfo);
        self::output(1, '操作成功');
    }
    */
}
