<?php
namespace app\api\controller;

use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\ContractConfig;
use app\common\model\ContractKline;
use app\common\model\ContractLockLog;
use app\common\model\ContractOrder;
use app\common\model\ContractTrade;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\HongbaoKeepLog;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;

class Contract extends Base
{
    public function _initialize()
    {
        parent::_initialize();
        $action = strtolower($this->request->action());
        $public_fuc = ['index', 'trade_index', 'get_price', 'order_list', 'lock_index', 'lock_log', 'kline_self'];
        if (!in_array($action, $public_fuc)) {
            $contract_switch = ContractConfig::get_value('contract_switch', 0);
            if (!$contract_switch) {
                $result['code'] = ERROR1;$result['message'] = lang("contract_close");$this->output_new($result);
            }
            $contract_open_time = ContractConfig::get_value('contract_open_time', '');
            $now = time();
            if (strtotime($contract_open_time) > $now) {
                $result['code'] = ERROR1;$result['message'] = lang("contract_open_time",['time'=>$contract_open_time]);$this->output_new($result);
            }
            $money_type = input('money_type',0, 'intval');
            if ($money_type > 0) {
                if ($money_type == 1) {//真实账户
                    $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
                    if (!$contract_really_switch) {
                        $result['code'] = ERROR1;$result['message'] = lang("contract_really_close");$this->output_new($result);
                    }
                    $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
                    $currency_id = input('currency_id',0, 'intval');
                    if (!in_array($currency_id, $contract_currency_list_1)) {
                        $result['code'] = ERROR1;$result['message'] = lang("parameter_error");$this->output_new($result);
                    }
                }
                else {//模拟账户
                    $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
                    if (!$contract_virtual_switch) {
                        $result['code'] = ERROR1;$result['message'] = lang("contract_virtual_close");$this->output_new($result);
                    }
                    $contract_currency_list_2 = explode(',', ContractConfig::get_value('contract_currency_list_2', '35'));//合约模拟账户币种列表
                    $currency_id = input('currency_id',0, 'intval');
                    if (!in_array($currency_id, $contract_currency_list_2)) {
                        $result['code'] = ERROR1;$result['message'] = lang("parameter_error");$this->output_new($result);
                    }
                }
            }
        }
    }
    /**
     * 合约初始化
     * @throws DbException
     */
    public function index()
    {
        list($start_time, $end_time) = ContractOrder::get_start_end_time(/*strtotime('2020-06-05 16:00:00')*/);
        list($start_time, $end_time) = ContractOrder::get_start_end_time(strtotime('2020-05-01 15:59:59'));
        list($start_time, $end_time) = ContractOrder::get_start_end_time(strtotime('2020-05-01 16:00:00'));
        list($start_time, $end_time) = ContractOrder::get_start_end_time(strtotime('2020-06-01 15:59:59'));
        list($start_time, $end_time) = ContractOrder::get_start_end_time(strtotime('2020-06-01 16:00:00'));

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $trade_list = ContractTrade::get_trade_list();
        //$currency_id = ContractConfig::get_value('contract_currency_id', 35);
        $virtual_currency_name = ContractConfig::get_value('contract_virtual_currency_name', 'KOIC');
        //$currency = Currency::get($currency_id);
        //$usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $currency_id);
        $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
        $contract_currency_list_2 = explode(',', ContractConfig::get_value('contract_currency_list_2', '35'));//合约模拟账户币种列表
        //var_dump($contract_currency_list_1);
        //var_dump($contract_currency_list_2);
        //真实账户
        foreach ($contract_currency_list_1 as $key => $value) {
            $currency = Currency::get($value);
            $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $value);
            $money_list[] = [
                'money_type'=>'1',
                'currency_id'=>$value,
                'money_name'=>'',//'真实账户',
                'currency_name'=>$currency['currency_name'],
                'money'=>number_format($usersCurrency['num'],2,".",""),
            ];
        }
        //模拟账户
        foreach ($contract_currency_list_2 as $key => $value) {
            $currency = Currency::get($value);
            $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $value);
            $money_list[] = [
                'money_type'=>'2',
                'currency_id'=>$value,
                'money_name'=>$virtual_currency_name,
                'currency_name'=>$currency['currency_name'],
                'money'=>number_format($usersCurrency['keep_num'],2,".",""),
            ];
        }
        /*$money_list = [
            [
                'money_type'=>'1', 'money_name'=>'真实账户', 'currency_name'=>$currency['currency_name'], 'money'=>number_format($usersCurrency['num'],2,".",""),
            ],
            [
                'money_type'=>'2', 'money_name'=>'模拟账户', 'currency_name'=>$virtual_currency_name, 'money'=>number_format($usersCurrency['keep_num'],2,".",""),
            ],
        ];*/
        $result['result'] = ['trade_list'=>$trade_list, 'money_list'=>$money_list];
        $this->output_new($result);
    }

    /**
     * 交易对初始化
     */
    public function trade_index()
    {
        $trade_id = input('trade_id',0, 'intval');
        $money_type = input('money_type',1, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($trade_id)) $trade_id = ContractTrade::get_default_trade_id();
        if (empty($trade_id) || $money_type < 1 || $money_type > 2) $this->output_new($result);

        $tradeFind = ContractTrade::get($trade_id);
        if (!$tradeFind) $this->output_new($result);
        $currency = Currency::get($tradeFind['currency_id']);
        $trade_currency = Currency::get($tradeFind['trade_currency_id']);
        //根据当前时间获取那个时间点分钟整点的开盘价作为成交价，如22点23分50秒，那么获取22点23分的开盘价作为成交价
        $price = 10344.57;
        $price_time = strtotime(date('Y-m-d H:i:00'));
        //$price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time);
        //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
        $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            //$price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time - 60 * $n);
            $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time - 60 * $n);
            $n++;
        }
        $trade_info = [
            'trade_id'=>$tradeFind['id'],
            'currency_name_left'=>$currency['currency_name'],
            'currency_name_right'=>$trade_currency['currency_name'],
            'name'=>$currency['currency_name'].'/'.$trade_currency['currency_name'],
            'desc'=>$tradeFind['desc'],
            'daily_gain'=>ContractKline::get_daily_gain($trade_id),
            //'price_zhang'=>$price,
            'price_zhang'=>0,
            //'price_die'=>$price,
            'price_die'=>0,
            'price_rate'=>$tradeFind['price_rate'],
            'close_price'=>$price,
            'safe_explan'=>ContractConfig::get_value('contract_safe_explan', ''),
            'safe_percent'=>ContractConfig::get_value('contract_safe_percent', 10),
            'price_length'=>$tradeFind['price_length'],
        ];

        $money_min = ContractConfig::get_value('contract_money_min', 100);
        //$lever_list = explode(',', ContractConfig::get_value('contract_lever_list', '5,10,20,30,40,50'));
        $lever_list = array_values(ContractOrder::CLOSEOUT_LEVER_LIST);
        $type_enum = ContractOrder::TYPE_ENUM;
        $type_list = [];
        foreach ($type_enum as $key => $value) {
            $type_list[] = [
                'type'=>$key,
                'name'=>$value,
            ];
        }
        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ['money_min'=>$money_min, 'trade_info'=>$trade_info, 'lever_list'=>$lever_list, 'type_list'=>$type_list,];
        $this->output_new($result);
    }

    /**
     * 获取价格
     */
    public function get_price()
    {
        $trade_id = input('trade_id',0, 'intval');
        $money_type = input('money_type',1, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($trade_id)) $trade_id = ContractTrade::get_default_trade_id();
        if (empty($trade_id) || $money_type < 1 || $money_type > 2) $this->output_new($result);
        $tradeFind = ContractTrade::get($trade_id);
        if (!$tradeFind) $this->output_new($result);

        //根据当前时间获取那个时间点分钟整点的开盘价作为成交价，如22点23分50秒，那么获取22点23分的开盘价作为成交价
        $price = 10344.57;
        $price_time = strtotime(date('Y-m-d H:i:00'));
        //$price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time);
        //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
        $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            //$price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time - 60 * $n);
            $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time - 60 * $n);
            $n++;
        }

        $price_zhang = keepPoint($price * (1 + floatval(mt_rand(0, 10) / 10) * $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
        $price_die = keepPoint($price * (1 - floatval(mt_rand(0, 10) / 10) * $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        //$result['result'] = ['price_zhang'=>$price,'price_die'=>$price,];
        $result['result'] = ['price_zhang'=>$price_zhang,'price_die'=>$price_die,'close_price'=>$price,];
        $this->output_new($result);
    }

    /**
     * 订单列表
     */
    public function order_list()
    {
        $trade_id = input('trade_id',0, 'intval');
        $type = input('type',1, 'intval');//类型 1-持仓 2-委托
        $money_type = input('money_type',1, 'intval');
        $currency_id = input('currency_id',0, 'intval');
        $status = input('status',0, 'intval');//状态 0-历史记录 1-当前
        $page = input('page',1, 'intval');
        $length = input('length',10, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($trade_id) || $money_type < 1 || $money_type > 2 || $currency_id <= 0) $this->output_new($result);
        if ($money_type == 1) {//真实账户
            $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
            if (!in_array($currency_id, $contract_currency_list_1)) $this->output_new($result);
        }
        else {//模拟账户
            $contract_currency_list_2 = explode(',', ContractConfig::get_value('contract_currency_list_2', '35'));//合约模拟账户币种列表
            if (!in_array($currency_id, $contract_currency_list_2)) $this->output_new($result);
        }

        $tradeFind = ContractTrade::get($trade_id);
        if (!$tradeFind) $this->output_new($result);

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ContractOrder::get_order_list($this->member_id, $trade_id, $type, $money_type, $currency_id, $page, $length, $status);
        $this->output_new($result);
    }

    /**
     * 下单初始化
     */
    public function add_order_init()
    {
        $trade_id = input('trade_id',0, 'intval');
        $type = input('type',1, 'intval');
        $money_type = input('money_type',1, 'intval');
        $currency_id = input('currency_id',0, 'intval');
        $buy_type = input('buy_type',1, 'intval');
        $money = input('money',0, 'intval');
        $lever_num = input('lever_num',0, 'intval');
        $price = input('price',0, 'floatval');
        $close_price = input('close_price',0, 'floatval');
        $safe_money = input('safe_money',0, 'intval');
        $trust_price = input('trust_price',0, 'floatval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        $tradeFind = ContractTrade::get($trade_id);
        if (!$tradeFind) $this->output_new($result);
        $money_min = ContractConfig::get_value('contract_money_min', 100);
        $lever_max = ContractConfig::get_value('contract_lever_max', 50);
        $lever_min = ContractConfig::get_value('contract_lever_min', 5);
        $lever_list1 = explode(',', ContractConfig::get_value('contract_lever_list', '5,10,20,30,40,50'));
        if (empty($trade_id) || $type < 1 || $type > 2 || $money_type < 1 || $money_type > 2 || $buy_type < 1 || $buy_type > 2 || $money < $money_min || $money % $money_min != 0 || $lever_num < $lever_min || $lever_num > $lever_max || !in_array($lever_num, $lever_list1) || $currency_id <= 0) $this->output_new($result);

        if ($type == 1) {//1-时时合约
            $contract_cut_order_switch = ContractConfig::get_value('contract_cut_order_switch', 1);
            if (!$contract_cut_order_switch) {
                $result['message'] = lang("contract_cut_close");$this->output_new($result);
            }
            if (empty($price) || empty($close_price)) $this->output_new($result);
            $price_max = keepPoint($close_price * (1 + $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
            $price_min = keepPoint($close_price * (1 - $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
            if (($price > $price_max || $price < $close_price) && $buy_type == 1) {//买涨
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }
            if (($price < $price_min || $price > $close_price) && $buy_type == 2) {//买跌
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }
        }
        else {//2-永续合约
            $contract_forever_order_switch = ContractConfig::get_value('contract_forever_order_switch', 1);
            if (!$contract_forever_order_switch) {
                $result['message'] = lang("contract_forever_close");$this->output_new($result);
            }
            if ($trust_price <= 0 || empty($close_price)) $this->output_new($result);

            $price_time = strtotime(date('Y-m-d H:i:00'));
            //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
            //$close_price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
            $priceWhere = [
                'trade_id'=>$trade_id,
                'type'=>60,
                'add_time'=>$price_time,
            ];
            $priceFind = \app\common\model\ContractKline::where($priceWhere)->find();
            $high_price = $priceFind['hign_price'];
            $low_price = $priceFind['low_price'];
            if ($close_price < $low_price || $close_price > $high_price) {
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }

            $price = $trust_price;
        }

        //$money_currency_id = ContractConfig::get_value('contract_currency_id', 35);
        $money_currency_id = $currency_id;
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $money_currency_id);
        $userMoney = $usersCurrency['num'];
        /*//根据当前时间获取那个时间点分钟整点的开盘价作为成交价，如22点23分50秒，那么获取22点23分的开盘价作为成交价
        $price = 10344.57;
        $price_time = strtotime(date('Y-m-d H:i:00'));
        $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            $price = \app\common\model\ContractKline::get_price($trade_id, 60, 'open_price', $price_time - 60 * $n);
            $n++;
        }*/
        /*$price_time = strtotime(date('Y-m-d H:i:00'));
        //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
        $close_price1 = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
        if ($close_price != $close_price1) {
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }*/
        $usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
        //$currency_cny_price = ContractConfig::get_value('contract_currency_cny_price', 1);
        $currency_cny_price = CurrencyPriceTemp::get_price_currency_id($money_currency_id, 'CNY');
        if ($safe_money > 0) {
            //$safe_percent = ContractConfig::get_value('contract_safe_percent', 10);
            $lever_list = ContractOrder::CLOSEOUT_LEVER_LIST;
            $safe_percent = $lever_list[$lever_num]['safe_percent'];
            if ($safe_percent > 0) {
                $safe_money = keepPoint($money * $safe_percent / 100, 6);
            }
            else {
                $safe_money = 0;
            }
        }
        if ($money_type == 2) {
            //$currency_cny_price = ContractConfig::get_value('contract_virtual_currency_cny_price', 1);
            $userMoney = $usersCurrency['keep_num'];
            $safe_money = 0;//模拟账户不用保险金
        }
        $buy_fee_rate = ContractConfig::get_value('contract_buy_fee_rate', 2);
        //$fee_rate = ContractConfig::get_value('contract_fee_rate', 2);
        $lever_list = ContractOrder::CLOSEOUT_LEVER_LIST;
        $fee_rate = $lever_list[$lever_num]['fee_rate'];
        //手续费改为下单时计算，公式：保证金*杠杆*手续费率/100
        $fee = keepPoint($money * $lever_num * $fee_rate / 100, 6);
        //买入手续费，公式：保证金*杠杆*手续费率/100
        $buy_fee = keepPoint($money * $lever_num * $buy_fee_rate / 100, 6);
        //if ($userMoney < $money) {
        if ($userMoney < ($money + $safe_money + $buy_fee)) {
            $result['message'] = lang('insufficient_balance');$this->output_new($result);
        }
        $number = 0;
        if ($price > 0) {
            //TODO：交易数量=(保证金数量*保证金人民币价格)/USDT人民币价格/交易对价格
            //TODO：2020-03-18 交易数量=((保证金数量-手续费)*保证金人民币价格)/USDT人民币价格/交易对价格
            $number = keepPoint(((($money - $fee) * $lever_num * $currency_cny_price) / $usdt_cny_price) / $price, 3);
            if ($tradeFind['currency_id'] == 8 && ($currency_id == 8 || $currency_id == 35)) {//2020-03-23，KOI改为XRP+，XRP/USDT交易对交易数量=保证金数量
                //2020-05-28，币种为XRP、XRP⁺时，XRP/USDT交易对交易数量=保证金数量*杠杆
                $number = $money * $lever_num;
            }
        }
        $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 80);
        $stop_profit_percent_min = ContractConfig::get_value('contract_stop_profit_percent_min', 20);
        $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 80);
        $stop_loss_percent_min = ContractConfig::get_value('contract_stop_loss_percent_min', 20);

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ['price'=>$price, 'number'=>$number, 'stop_profit_percent_max'=>$stop_profit_percent_max, 'stop_profit_percent_min'=>$stop_profit_percent_min, 'stop_loss_percent_max'=>$stop_loss_percent_max, 'stop_loss_percent_min'=>$stop_loss_percent_min, 'fee_rate'=>floattostr($fee_rate)];
        $this->output_new($result);
    }

    /**
     * 下单
     */
    public function add_order()
    {
        $trade_id = input('trade_id',0, 'intval');
        $type = input('type',1, 'intval');
        $money_type = input('money_type',1, 'intval');
        $currency_id = input('currency_id',0, 'intval');
        $buy_type = input('buy_type',1, 'intval');
        $money = input('money',0, 'intval');
        $lever_num = input('lever_num',0, 'intval');
        $price = input('price',0, 'floatval');
        $close_price = input('close_price',0, 'floatval');
        $stop_profit_percent = input('stop_profit_percent',0, 'intval');
        $stop_loss_percent = input('stop_loss_percent',0, 'intval');
        //$start_time = input('start_time','', 'strval');
        //$end_time = input('end_time','', 'strval');
        $safe_money = input('safe_money',0, 'intval');
        $trust_price = input('trust_price',0, 'floatval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        $tradeFind = ContractTrade::get($trade_id);
        if (!$tradeFind) $this->output_new($result);
        $money_min = ContractConfig::get_value('contract_money_min', 100);
        $lever_max = ContractConfig::get_value('contract_lever_max', 50);
        $lever_min = ContractConfig::get_value('contract_lever_min', 5);
        $lever_list1 = explode(',', ContractConfig::get_value('contract_lever_list', '5,10,20,30,40,50'));
        $stop_profit_percent_default = ContractConfig::get_value('contract_stop_profit_percent_default', 50);
        $stop_loss_percent_default = ContractConfig::get_value('contract_stop_loss_percent_default', 50);
        $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 80);
        $stop_profit_percent_min = ContractConfig::get_value('contract_stop_profit_percent_min', 20);
        $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 80);
        $stop_loss_percent_min = ContractConfig::get_value('contract_stop_loss_percent_min', 20);
        if (empty($stop_profit_percent)) $stop_profit_percent = $stop_profit_percent_default;
        if (empty($stop_loss_percent)) $stop_loss_percent = $stop_loss_percent_default;
        if (empty($trade_id) || $type < 1 || $type > 2 || $money_type < 1 || $money_type > 2 || $buy_type < 1 || $buy_type > 2 || $money < $money_min || $money % $money_min != 0 || $lever_num < $lever_min || $lever_num > $lever_max || !in_array($lever_num, $lever_list1) || $stop_profit_percent < $stop_profit_percent_min || $stop_profit_percent > $stop_profit_percent_max || $stop_loss_percent < $stop_loss_percent_min || $stop_loss_percent > $stop_loss_percent_max || empty($price) || empty($close_price) || $currency_id <= 0) $this->output_new($result);

        if ($type == 1) {//1-时时合约
            $contract_cut_order_switch = ContractConfig::get_value('contract_cut_order_switch', 1);
            if (!$contract_cut_order_switch) {
                $result['message'] = lang("contract_cut_close");$this->output_new($result);
            }
            $price_max = keepPoint($close_price * (1 + $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
            $price_min = keepPoint($close_price * (1 - $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
            if (($price > $price_max || $price < $close_price) && $buy_type == 1) {//买涨
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }
            if (($price < $price_min || $price > $close_price) && $buy_type == 2) {//买跌
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }
        }
        else {//2-永续合约
            $contract_forever_order_switch = ContractConfig::get_value('contract_forever_order_switch', 1);
            if (!$contract_forever_order_switch) {
                $result['message'] = lang("contract_forever_close");$this->output_new($result);
            }
            if ($trust_price <= 0 || empty($close_price)) $this->output_new($result);

            $price_time = strtotime(date('Y-m-d H:i:00'));
            //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
            //$close_price = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
            $priceWhere = [
                'trade_id'=>$trade_id,
                'type'=>60,
                'add_time'=>$price_time,
            ];
            $priceFind = \app\common\model\ContractKline::where($priceWhere)->find();
            $high_price = $priceFind['hign_price'];
            $low_price = $priceFind['low_price'];
            if ($close_price < $low_price || $close_price > $high_price) {
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }
        }

        /*$price_time = strtotime(date('Y-m-d H:i:00'));
        //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
        $close_price1 = \app\common\model\ContractKline::get_price($trade_id, 60, 'close_price', $price_time);
        if ($close_price != $close_price1) {
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }*/

        //$money_currency_id = ContractConfig::get_value('contract_currency_id', 35);
        $money_currency_id = $currency_id;
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $money_currency_id);
        $userMoney = $usersCurrency['num'];
        $safe_percent = 0;
        if ($safe_money > 0) {
            //$safe_percent = ContractConfig::get_value('contract_safe_percent', 10);
            $lever_list = ContractOrder::CLOSEOUT_LEVER_LIST;
            $safe_percent = $lever_list[$lever_num]['safe_percent'];
            if ($safe_percent > 0) {
                $safe_money = keepPoint($money * $safe_percent / 100, 6);
            }
            else {
                $safe_money = 0;
            }
        }
        if ($money_type == 2) {
            $userMoney = $usersCurrency['keep_num'];
            $safe_money = 0;//模拟账户不用保险金
        }
        $buy_fee_rate = ContractConfig::get_value('contract_buy_fee_rate', 2);
        //$fee_rate = ContractConfig::get_value('contract_fee_rate', 2);
        $lever_list = ContractOrder::CLOSEOUT_LEVER_LIST;
        $fee_rate = $lever_list[$lever_num]['fee_rate'];
        //买入手续费，公式：保证金*杠杆*手续费率/100
        $buy_fee = keepPoint($money * $lever_num * $buy_fee_rate / 100, 6);
        //if ($userMoney < $money) {
        if ($userMoney < ($money + $safe_money + $buy_fee)) {
            $result['message'] = lang('insufficient_balance');$this->output_new($result);
        }

        //$result['code'] = SUCCESS;
        //$result['message'] = lang("data_success");
        $result = ContractOrder::add_order($this->member_id, $tradeFind, $type, $money_type, $money_currency_id, $buy_type, $lever_num, $money, $stop_profit_percent, $stop_loss_percent, $price, $safe_money, $safe_percent, $trust_price, $close_price);
        $this->output_new($result);
    }

    /**
     * 设置止盈/止损比例初始化
     */
    public function set_percent_init()
    {
        $order_id = input('order_id',0, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($order_id)) $this->output_new($result);
        $orderFind = ContractOrder::get($order_id);
        $tradeFind = ContractTrade::get($orderFind['trade_id']);
        if (!$orderFind || !$tradeFind) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['status'] != 3) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }

        if ($orderFind['money_type'] == 1) {//真实账户
            $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
            if (!$contract_really_switch) {
                $result['message'] = lang("contract_really_close");$this->output_new($result);
            }
        }
        else {//模拟账户
            $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
            if (!$contract_virtual_switch) {
                $result['message'] = lang("contract_virtual_close");$this->output_new($result);
            }
        }

        $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 80);
        $stop_profit_percent_min = ContractConfig::get_value('contract_stop_profit_percent_min', 20);
        $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 80);
        $stop_loss_percent_min = ContractConfig::get_value('contract_stop_loss_percent_min', 20);

        $usdt_cny_price = $orderFind['usdt_cny_price'];
        $currency_cny_price = $orderFind['money_currency_cny_price'];
        if ($orderFind['buy_type'] == 1) {//买涨
            $stop_profit_price_max = keepPoint((($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
            $stop_profit_price_min = keepPoint((($stop_profit_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);

            $stop_loss_price_max = keepPoint((-($stop_loss_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
            $stop_loss_price_min = keepPoint((-($stop_loss_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
        }
        else {//买跌
            $stop_profit_price_max = keepPoint((-($stop_profit_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
            $stop_profit_price_min = keepPoint((-($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);

            $stop_loss_price_max = keepPoint((($stop_loss_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
            $stop_loss_price_min = keepPoint((($stop_loss_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = [
            'stop_profit_percent_max'=>$stop_profit_percent_max,
            'stop_profit_percent_min'=>$stop_profit_percent_min,
            'stop_loss_percent_max'=>$stop_loss_percent_max,
            'stop_loss_percent_min'=>$stop_loss_percent_min,
            'stop_profit_price_max'=>$stop_profit_price_max,
            'stop_profit_price_min'=>$stop_profit_price_min,
            'stop_loss_price_max'=>$stop_loss_price_max,
            'stop_loss_price_min'=>$stop_loss_price_min,
            'stop_profit_type'=>$orderFind['stop_profit_type'],
            'stop_profit_percent'=>$orderFind['stop_profit_percent'],
            'stop_profit_price'=>floattostr($orderFind['stop_profit_price']),
            'stop_loss_type'=>$orderFind['stop_loss_type'],
            'stop_loss_percent'=>$orderFind['stop_loss_percent'],
            'stop_loss_price'=>floattostr($orderFind['stop_loss_price']),
        ];
        $this->output_new($result);
    }

    /**
     * 设置止盈/止损比例
     */
    public function set_percent()
    {
        $order_id = input('order_id',0, 'intval');
        $stop_profit_type = input('stop_profit_type',1, 'intval');
        $stop_profit_percent = input('stop_profit_percent',0, 'intval');
        $stop_profit_price = input('stop_profit_price',0, 'intval');
        $stop_loss_type = input('stop_loss_type',1, 'intval');
        $stop_loss_percent = input('stop_loss_percent',0, 'intval');
        $stop_loss_price = input('stop_loss_price',0, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($order_id)) $this->output_new($result);
        $orderFind = ContractOrder::get($order_id);
        $tradeFind = ContractTrade::get($orderFind['trade_id']);
        if (!$orderFind || !$tradeFind) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['status'] != 3) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }

        if ($orderFind['money_type'] == 1) {//真实账户
            $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
            if (!$contract_really_switch) {
                $result['message'] = lang("contract_really_close");$this->output_new($result);
            }
        }
        else {//模拟账户
            $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
            if (!$contract_virtual_switch) {
                $result['message'] = lang("contract_virtual_close");$this->output_new($result);
            }
        }

        $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 80);
        $stop_profit_percent_min = ContractConfig::get_value('contract_stop_profit_percent_min', 20);
        $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 80);
        $stop_loss_percent_min = ContractConfig::get_value('contract_stop_loss_percent_min', 20);
        $usdt_cny_price = $orderFind['usdt_cny_price'];
        $currency_cny_price = $orderFind['money_currency_cny_price'];
        //TODO：止盈/止损平仓价=(止盈比例/100)*保证金数量*保证金人民币价格/USDT人民币价格/交易数量±成交价
        //TODO：2020-03-18 止盈/止损平仓价=(止盈比例/100)*(保证金数量-手续费)*保证金人民币价格/USDT人民币价格/交易数量±成交价
        //TODO：新的止盈/止损平仓价公式(暂未使用)：(杠杆±(止盈(损)比例/100))*成交价格/杠杆 或者：±((止盈(损)比例/100)*成交价格/杠杆)+成交价格

        //2020-05-13，新增止损比例设置条件判断，不能设置小于当前亏损比例
        if ($orderFind['type'] == 1) {//1-时时合约
            $close_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 300, 'close_price', $orderFind['start_time']);
            //$hign_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 300, 'hign_price', $orderFind['start_time']);
            //$low_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 300, 'low_price', $orderFind['start_time']);
        }
        else {//2-永续合约
            $last_closeout_time = $orderFind['last_closeout_time'];
            $closeout_time = $last_closeout_time > 0 ? $last_closeout_time + 60 : strtotime(date('Y-m-d H:i:00', $orderFind['add_time']));
            $price_time = $closeout_time;
            $close_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time);
            //$hign_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'hign_price', $price_time);
            //$low_price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'low_price', $price_time);
        }
        //根据收盘价和交易对的价格涨跌比例，计算出买涨价格
        $zhang_price = keepPoint($close_price * (1 + $tradeFind['price_rate'] / 10000), $tradeFind['price_length']);
        //根据收盘价和交易对的价格涨跌比例，计算出买跌价格
        $die_price = keepPoint($close_price * (1 - $tradeFind['price_rate'] / 10000), $tradeFind['price_length']);
        if ($stop_profit_type == 1) {//止盈类型 1-按比例 2-按价格
            if ($stop_profit_percent < 0 || $stop_profit_percent < $stop_profit_percent_min || $stop_profit_percent > $stop_profit_percent_max) {
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }

            if ($stop_profit_percent != $orderFind['stop_profit_percent']) {
                if ($orderFind['buy_type'] == 1) {//买涨
                    //$stop_profit_price = keepPoint((($orderFind['stop_profit_percent'] * $orderFind['money_currency_num'] / 100) / ($orderFind['currency_num'] * $orderFind['lever_num'])) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $new_stop_profit_price = $stop_profit_percent > 0 ? keepPoint((($stop_profit_percent / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($orderFind['lever_num'] + ($orderFind['stop_profit_percent'] / 100)) * $orderFind['deal_price']) / $orderFind['lever_num'], $tradeFind['price_length']);

                    //2020-05-18，新增止盈比例设置条件判断，不能设置大于当前盈利比例
                    $old_stop_profit_price = $orderFind['stop_profit_price'];
                    $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 500);
                    if ($orderFind['stop_profit_percent'] == 0) $old_stop_profit_price = keepPoint((($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    //var_dump($new_stop_profit_price);
                    //var_dump($old_stop_profit_price);
                    //var_dump($zhang_price);
                    //如果最新止盈平仓价格>旧止盈平仓价格，且旧止盈平仓价格<=买涨价，不允许设置大于当前盈利比例
                    if ($new_stop_profit_price > $old_stop_profit_price && $old_stop_profit_price <= $zhang_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                else {//买跌
                    //$stop_profit_price = keepPoint((-($orderFind['stop_profit_percent'] * $orderFind['money_currency_num'] / 100) / ($orderFind['currency_num'] * $orderFind['lever_num'])) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $new_stop_profit_price = $stop_profit_percent > 0 ? keepPoint((-($stop_profit_percent / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($orderFind['lever_num'] - ($orderFind['stop_profit_percent'] / 100)) * $orderFind['deal_price']) / $orderFind['lever_num'], $tradeFind['price_length']);

                    //2020-05-18，新增止盈比例设置条件判断，不能设置大于当前盈利比例
                    $old_stop_profit_price = $orderFind['stop_profit_price'];
                    $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 500);
                    if ($orderFind['stop_profit_percent'] == 0) $old_stop_profit_price = keepPoint((-($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    //如果最新止盈平仓价格<旧止盈平仓价格，且旧止盈平仓价格>=买跌价，不允许设置大于当前盈利比例
                    if ($new_stop_profit_price < $old_stop_profit_price && $old_stop_profit_price >= $die_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                $orderFind['stop_profit_percent'] = $stop_profit_percent;
                $orderFind['stop_profit_price'] = $new_stop_profit_price;
            }
        }
        else {//止盈类型 1-按比例 2-按价格
            if ($stop_profit_price <= 0) $this->output_new($result);
            if ($orderFind['type'] == 1) $this->output_new($result);//1-时时合约

            if ($stop_profit_price != $orderFind['stop_profit_price']) {
                if ($orderFind['buy_type'] == 1) {//买涨
                    $stop_profit_price_max = keepPoint((($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $stop_profit_price_min = keepPoint((($stop_profit_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    if ($stop_profit_price < $stop_profit_price_min || $stop_profit_price > $stop_profit_price_max) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }

                    $old_stop_profit_price = $orderFind['stop_profit_price'];
                    //如果最新止盈平仓价格>旧止盈平仓价格，且旧止盈平仓价格<=买涨价，不允许设置大于当前盈利比例
                    if ($stop_profit_price > $old_stop_profit_price && $old_stop_profit_price <= $zhang_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                else {//买跌
                    $stop_profit_price_max = keepPoint((-($stop_profit_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $stop_profit_price_min = keepPoint((-($stop_profit_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    //var_dump($stop_profit_price);
                    //var_dump($stop_profit_price_max);
                    //var_dump($stop_profit_price_min);
                    if ($stop_profit_price < $stop_profit_price_min || $stop_profit_price > $stop_profit_price_max) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                    //var_dump(1);
                    //var_dump($die_price);

                    $old_stop_profit_price = $orderFind['stop_profit_price'];
                    //如果最新止盈平仓价格<旧止盈平仓价格，且旧止盈平仓价格>=买跌价，不允许设置大于当前盈利比例
                    if ($stop_profit_price < $old_stop_profit_price && $old_stop_profit_price >= $die_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                $orderFind['stop_profit_percent'] = 0;
                $orderFind['stop_profit_price'] = $stop_profit_price;
            }
        }
        $orderFind['stop_profit_type'] = $stop_profit_type;

        if ($stop_loss_type == 1) {//止损类型 1-按比例 2-按价格
            if ($stop_loss_percent < 0 || $stop_loss_percent < $stop_loss_percent_min || $stop_loss_percent > $stop_loss_percent_max) {
                $result['message'] = lang("contract_price_error");$this->output_new($result);
            }

            if ($stop_loss_percent != $orderFind['stop_loss_percent']) {
                if ($orderFind['buy_type'] == 1) {//买涨
                    //$stop_loss_price = keepPoint((-($orderFind['stop_loss_percent'] * $orderFind['money_currency_num'] / 100) / ($orderFind['currency_num'] * $orderFind['lever_num'])) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $new_stop_loss_price = $stop_loss_percent > 0 ? keepPoint((-($stop_loss_percent / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($orderFind['lever_num'] - ($orderFind['stop_loss_percent'] / 100)) * $orderFind['deal_price']) / $orderFind['lever_num'], $tradeFind['price_length']);

                    //2020-05-13，新增止损比例设置条件判断，不能设置小于当前亏损比例
                    //最新止损平仓价格如果>=买跌价，不允许设置小于当前亏损比例
                    if ($new_stop_loss_price >= $die_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                else {//买跌
                    //$stop_loss_price = keepPoint((($orderFind['stop_loss_percent'] * $orderFind['money_currency_num'] / 100) / ($orderFind['currency_num'] * $orderFind['lever_num'])) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $new_stop_loss_price = $stop_loss_percent > 0 ? keepPoint((($stop_loss_percent / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($orderFind['lever_num'] + ($orderFind['stop_loss_percent'] / 100)) * $orderFind['deal_price']) / $orderFind['lever_num'], $tradeFind['price_length']);

                    //2020-05-13，新增止损比例设置条件判断，不能设置小于当前亏损比例
                    //最新止损平仓价格如果<=买涨价，不允许设置小于当前亏损比例
                    if ($new_stop_loss_price <= $zhang_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                $orderFind['stop_loss_percent'] = $stop_loss_percent;
                $orderFind['stop_loss_price'] = $new_stop_loss_price;
            }
        }
        else {//止损类型 1-按比例 2-按价格
            if ($stop_loss_price <= 0) $this->output_new($result);
            if ($orderFind['type'] == 1) $this->output_new($result);//1-时时合约

            if ($stop_loss_price != $orderFind['stop_loss_price']) {
                if ($orderFind['buy_type'] == 1) {//买涨
                    $stop_loss_price_max = keepPoint((-($stop_loss_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $stop_loss_price_min = keepPoint((-($stop_loss_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    //var_dump($stop_loss_price);
                    //var_dump($stop_loss_price_max);
                    //var_dump($stop_loss_price_min);
                    if ($stop_loss_price < $stop_loss_price_min || $stop_loss_price > $stop_loss_price_max) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                    //var_dump('按价格-买涨');
                    //var_dump($die_price);

                    //最新止损平仓价格如果>=买跌价，不允许设置小于当前亏损比例
                    if ($stop_loss_price >= $die_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }
                else {//买跌
                    $stop_loss_price_max = keepPoint((($stop_loss_percent_max / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    $stop_loss_price_min = keepPoint((($stop_loss_percent_min / 100) * ($orderFind['money_currency_num'] - $orderFind['fee_value']) * $currency_cny_price / $usdt_cny_price / $orderFind['currency_num']) + $orderFind['deal_price'], $tradeFind['price_length']);
                    //var_dump($stop_loss_price);
                    //var_dump($stop_loss_price_max);
                    //var_dump($stop_loss_price_min);
                    if ($stop_loss_price < $stop_loss_price_min || $stop_loss_price > $stop_loss_price_max) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                    //var_dump('按价格-买跌');
                    //var_dump($zhang_price);

                    //最新止损平仓价格如果<=买涨价，不允许设置小于当前亏损比例
                    if ($stop_loss_price <= $zhang_price) {
                        $result['message'] = lang("contract_price_error");$this->output_new($result);
                    }
                }

                $orderFind['stop_loss_percent'] = 0;
                $orderFind['stop_loss_price'] = $stop_loss_price;
            }
        }
        $orderFind['stop_loss_type'] = $stop_loss_type;

        $res = $orderFind->save();
        if ($res === false) {
            $result['message'] = lang('operation_failed_try_again');$this->output_new($result);
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("successful_operation");
        //$result['result'] = ['price'=>$price, 'number'=>$number];
        $this->output_new($result);
    }

    /**
     * 平仓初始化
     */
    public function close_order_init()
    {
        $order_id = input('order_id',0, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($order_id)) $this->output_new($result);
        $orderFind = ContractOrder::get($order_id);
        if (!$orderFind) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['status'] != 3) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }

        if ($orderFind['money_type'] == 1) {//真实账户
            $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
            if (!$contract_really_switch) {
                $result['message'] = lang("contract_really_close");$this->output_new($result);
            }
        }
        else {//模拟账户
            $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
            if (!$contract_virtual_switch) {
                $result['message'] = lang("contract_virtual_close");$this->output_new($result);
            }
        }

        //根据当前时间获取那个时间点分钟整点的收盘价作为平仓价，如22点23分50秒，那么获取22点23分的收盘价作为平仓价
        $price = 10384.57;
        //$price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'open_price');
        $price_time = strtotime(date('Y-m-d H:i:00'));
        $price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            $price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time - 60 * $n);
            $n++;
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ['price'=>$price];
        $this->output_new($result);
    }

    /**
     * 平仓
     */
    public function close_order()
    {
        $order_id = input('order_id',0, 'intval');
        $price = input('price',0, 'floatval');
        $close_price = input('close_price',0, 'floatval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($order_id) || empty($price) || empty($close_price)) $this->output_new($result);
        $orderFind = ContractOrder::get($order_id);
        $tradeFind = ContractTrade::get($orderFind['trade_id']);
        if (!$orderFind || !$tradeFind) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['status'] != 3) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }

        if ($orderFind['money_type'] == 1) {//真实账户
            $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
            if (!$contract_really_switch) {
                $result['message'] = lang("contract_really_close");$this->output_new($result);
            }
        }
        else {//模拟账户
            $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
            if (!$contract_virtual_switch) {
                $result['message'] = lang("contract_virtual_close");$this->output_new($result);
            }
        }

        $price_max = keepPoint($close_price * (1 + $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
        $price_min = keepPoint($close_price * (1 - $tradeFind['price_rate'] / 1000), $tradeFind['price_length']);
        /*if (($price > $price_max || $price < $close_price) && $orderFind['buy_type'] == 2) {//买跌订单平仓价使用买涨价
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }
        if (($price < $price_min || $price > $close_price) && $orderFind['buy_type'] == 1) {//买涨订单平仓价使用买跌价
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }*/
        if (($price < $price_min || $price > $price_max)) {
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }

        /*//根据当前时间获取那个时间点分钟整点的收盘价作为平仓价，如22点23分50秒，那么获取22点23分的收盘价作为平仓价
        $price = 10384.57;
        //$price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'open_price');
        $price_time = strtotime(date('Y-m-d H:i:00'));
        $price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            $price = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time - 60 * $n);
            $n++;
        }*/
        /*$price_time = strtotime(date('Y-m-d H:i:00'));
        //根据当前时间获取那个时间点分钟整点的收盘价作为成交价，如22点23分50秒，那么获取22点23分的收盘价作为成交价
        $close_price1 = \app\common\model\ContractKline::get_price($orderFind['trade_id'], 60, 'close_price', $price_time);
        if ($close_price != $close_price1) {
            $result['message'] = lang("contract_price_error");$this->output_new($result);
        }*/
        //$orderFind['closeout_price'] = $price;
        $orderFind['closeout_price_1'] = $price;
        $orderFind['closeout_type'] = 4;
        $orderFind['closeout_time'] = time();
        //$orderFind['next_closeout_time'] = strtotime(date('Y-m-d H:i:00', $orderFind['next_closeout_time'])) + 60;//设置为当前时间的分钟整点时间+60秒，等待下一次平仓
        $orderFind['next_closeout_time'] = time();//设置为当前时间，等待下一次平仓
        $orderFind['status'] = 4;
        $res = $orderFind->save();
        if ($res === false) {
            $result['message'] = lang('operation_failed_try_again');$this->output_new($result);
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("successful_operation");
        //$result['result'] = ['price'=>$price, 'number'=>$number];
        $this->output_new($result);
    }

    /**
     * 撤销委托
     */
    public function cancel_trust()
    {
        $order_id = input('order_id',0, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        if (empty($order_id)) $this->output_new($result);
        $orderFind = ContractOrder::get($order_id);
        $tradeFind = ContractTrade::get($orderFind['trade_id']);
        if (!$orderFind || !$tradeFind) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['type'] != 2) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['status'] != 6) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }
        if ($orderFind['trust_status'] != 1) {
            $result['message'] = lang('no_data');$this->output_new($result);
        }

        if ($orderFind['money_type'] == 1) {//真实账户
            $contract_really_switch = ContractConfig::get_value('contract_really_switch', 0);
            if (!$contract_really_switch) {
                $result['message'] = lang("contract_really_close");$this->output_new($result);
            }
        }
        else {//模拟账户
            $contract_virtual_switch = ContractConfig::get_value('contract_virtual_switch', 0);
            if (!$contract_virtual_switch) {
                $result['message'] = lang("contract_virtual_close");$this->output_new($result);
            }
        }

        try {
            Db::startTrans();

            $orderFind['trust_status'] = 3;//委托状态 1-委托中 2-已成交 3-已撤销
            $orderFind['trust_cancel_time'] = time();
            $orderFind['status'] = 7;//6-委托中 7-撤销委托

            $res = $orderFind->save();
            if ($res === false) {
                throw new Exception(lang('operation_failed_try_again'));
            }

            // 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $orderFind['money_currency_id']);
            if(!$usersCurrency) throw new Exception("获取资产错误");

            if ($orderFind['money_type'] == 1) {
                $bookType = AccountBookType::FOREVER_CONTRACT_CANCEL;
                $content = 'forever_contract_cancel';
                // 账本
                $flag = AccountBook::add_accountbook($this->member_id, $orderFind['money_currency_id'], $bookType, $content, 'in', $orderFind['money_currency_num'], $order_id);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('num',$orderFind['money_currency_num']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$orderFind['money_currency_num']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }

                if ($orderFind['safe_currency_num'] > 0) {
                    // 账本
                    $flag = AccountBook::add_accountbook($this->member_id, $orderFind['money_currency_id'], AccountBookType::FOREVER_CONTRACT_CANCEL_SAFE_RETURN, 'forever_contract_cancel_safe_return', 'in', $orderFind['safe_currency_num'], $order_id);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('num',$orderFind['safe_currency_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$orderFind['safe_currency_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }
            }
            else {//模拟账户

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('keep_num',$orderFind['money_currency_num']);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                //添加持仓记录
                $flag = HongbaoKeepLog::add_log('forever_contract_cancel',$this->member_id,$orderFind['money_currency_id'],$orderFind['money_currency_num'],$order_id);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            Db::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("successful_operation");
        //$result['result'] = ['price'=>$price, 'number'=>$number];
        $this->output_new($result);
    }

    /**
     * 锁仓币种列表
     */
    public function lock_currency_list()
    {
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表

        $currency_list = [];
        foreach ($contract_currency_list_1 as $key => $value) {
            $currency = Currency::get($value);
            $currency_list[] = [
                'currency_id'=>$currency['currency_id'],
                'currency_name'=>$currency['currency_name'],
                'currency_logo'=>$currency['currency_logo'],
            ];
        }

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = $currency_list;
        $this->output_new($result);
    }

    /**
     * 锁仓初始化
     */
    public function lock_index()
    {
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        $currency_id = input('currency_id',0, 'intval');
        if ($currency_id <= 0) $this->output_new($result);
        $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
        if (!in_array($currency_id, $contract_currency_list_1)) $this->output_new($result);
        $currency = Currency::get($currency_id);
        if (!$currency) $this->output_new($result);

        //$money_currency_id = ContractConfig::get_value('contract_currency_id', 35);
        $money_currency_id = $currency_id;
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $money_currency_id);

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ['contract_lock'=>$usersCurrency['contract_lock'],'currency_id'=>$currency_id,'currency_name'=>$currency['currency_name']];
        $this->output_new($result);
    }

    /**
     * 锁仓记录
     */
    public function lock_log()
    {
        $type = input('type',0, 'intval');
        $page = input('page',1, 'intval');
        $length = input('length',10, 'intval');
        $result['code'] = ERROR1;
        $result['message'] = lang("parameter_error");

        $currency_id = input('currency_id',0, 'intval');
        if ($currency_id <= 0) $this->output_new($result);
        $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
        if (!in_array($currency_id, $contract_currency_list_1)) $this->output_new($result);

        $result['code'] = SUCCESS;
        $result['message'] = lang("data_success");
        $result['result'] = ContractLockLog::get_log_list($this->member_id, $type, $currency_id, $page, $length);
        $this->output_new($result);
    }

    public function trade_list(Request $request)
    {

    }

    public function kline_self()
    {
        $show_length = [
            '1' => 150,
            '5' => 120,
            '15' =>90,
            '30' =>80,
            '60' =>70,
            '1D' =>50,
            'D' =>50,
            '1W' =>50,
            '1M' =>50,
        ];

        $currency_name = input('market','');
        if(empty($currency_name)) self::output(10101, 'b_stop');

        $step = substr(input('range', 60000), 0, -3); //默认1分钟
        $all_time=array('1'=>'60','5'=>'300','15'=>'900','30'=>'1800','60'=>'3600','1D'=>'86400','D'=>'86400','1W'=>'604800','1M'=>'2592000');
        $resolution = array_search($step,$all_time);
        if(!$resolution) self::output(10101, 'b_stop');

        $time = input('time', ''); //时间 格式：H:i

        $currency_name = explode("_",$currency_name);
        if(count($currency_name)!=2) self::output(10101, 'b_stop');

        $currency = Currency::where(['currency_name'=>$currency_name[0]])->whereOr(['currency_mark'=>$currency_name[0]])->find();
        $other_currency = Currency::where(['currency_name'=>$currency_name[1]])->whereOr(['currency_mark'=>$currency_name[1]])->find();
        if(empty($currency) || empty($other_currency)) self::output(10101, 'b_stop');
        $currency['currency_trade_id'] = $other_currency['currency_id'];

        $data = [];
        if (empty($time)) {
            $Sname = 'app_lists_pro' . $currency['currency_id'] . '_' . $currency['currency_trade_id'] . 't' . $step;
            $data = cache($Sname);
        }
        if (empty($data)) {
            $data = [];
            if (empty($time)) {
                $list = Db::name('contract_kline')->where(['type'=>$step,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['currency_trade_id']])->limit(1000)->order('add_time desc')->select();
            }
            else {
                $add_time = strtotime(date('Y-m-d ').$time.':00');
                $where = [
                    'type'=>$step,
                    'currency_id'=>$currency['currency_id'],
                    'currency_trade_id'=>$currency['currency_trade_id'],
                    'add_time'=>['egt', $add_time]
                ];
                $list = Db::name('contract_kline')->where($where)->limit(1000)->order('add_time desc')->select();
            }
            if($list) {
                $timeArr = array_column($list, 'add_time');
                array_multisort($timeArr, SORT_ASC, $list);
                $data = [
                    'ch'=>$list[0]['ch'],
                    'tick'=>[],
                ];
                foreach ($list as $k => $v) {
                    $list_t[$k] =  floatval($v['add_time'] );
                    $list_o[$k] =  floatval($v['open_price']);
                    $list_h[$k] =  floatval($v['hign_price']);
                    $list_l[$k] =  floatval($v['low_price']);
                    $list_c[$k] =  floatval($v['close_price']);
                    $list_v[$k] =  floatval($v['amount']);
                    $list_v[$k] =  floatval($v['count']);

                    //$data[] = [floatval($v['add_time']*1000),floatval($v['open_price']),floatval($v['hign_price']),floatval($v['low_price']),floatval($v['close_price']),floatval($v['num'])];
                    //$data[] = [floatval($v['add_time']*1000),$v['open_price'],$v['hign_price'],$v['low_price'],$v['close_price'],$v['num']];
                    //$data[] = [floatval($v['add_time']*1000),floattostr($v['open_price']),floattostr($v['hign_price']),floattostr($v['low_price']),floattostr($v['close_price']),floattostr($v['amount']),floattostr($v['count'])];
                    $data['tick'][] = [
                        'id'=>$v['add_time'],
                        'amount'=>floattostr($v['amount']),
                        'count'=>floattostr($v['count']),
                        'open'=>floattostr($v['open_price']),
                        'close'=>floattostr($v['close_price']),
                        'low'=>floattostr($v['low_price']),
                        'high'=>floattostr($v['hign_price']),
                        'vol'=>floattostr($v['vol']),
                    ];
                }
                if (empty($time)) {
                    if($step>10){
                        $step=10;
                    }
                    cache($Sname, $data, $step);
                }
            } else {
                $data = [];
            }
        }

        self::output(1000,'請求成功',$data);
    }
}