<?php
//合约订单表
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class ContractOrder extends Base
{
    /**
     * 订单类型枚举
     * @var array
     */
    const TYPE_ENUM = [
        1 => "时时合约",
        2 => "永续合约",
    ];

    /**
     * 账户类型枚举
     * @var array
     */
    const MONEY_TYPE_ENUM = [
        1 => "真实账户",
        2 => "模拟账户",
    ];

    /**
     * 状态枚举
     * @var array
     */
    const STATUS_ENUM = [
        1 => "队列中",
        2 => "队列处理失败",
        3 => "持仓中",
        4 => "结算中",
        5 => "已结算",
        6 => "委托中",
        7 => "撤销委托",
    ];

    /**
     * 买类型枚举
     * @var array
     */
    const BUY_TYPE_ENUM = [
        1 => "买涨",
        2 => "买跌",
    ];

    /**
     * 平仓类型枚举
     * @var array
     */
    const CLOSEOUT_TYPE_ENUM = [
        0 => "未平仓",
        1 => "到期平仓",
        2 => "止盈平仓",
        3 => "止损平仓",
        4 => "手动平仓",
    ];

    /**
     * 委托状态枚举
     * @var array
     */
    const TRUST_STATUS_ENUM = [
        0 => "未委托",
        1 => "委托中",
        2 => "已成交",
        3 => "已撤销",
    ];

    /**
     * 止盈/损类型
     * @var array
     */
    const STOP_ENUM = [
        1 => "按比例",
        2 => "按价格",
    ];

    /**
     * 杠杆枚举
     * @var array
     */
    const CLOSEOUT_LEVER_LIST = [
        '5'=>['lever_num'=>5, 'safe_percent'=>5, 'fee_rate'=>0.05],
        '10'=>['lever_num'=>10, 'safe_percent'=>5, 'fee_rate'=>0.06],
        '20'=>['lever_num'=>20, 'safe_percent'=>10, 'fee_rate'=>0.07],
        '30'=>['lever_num'=>30, 'safe_percent'=>10, 'fee_rate'=>0.08],
        '40'=>['lever_num'=>40, 'safe_percent'=>15, 'fee_rate'=>0.09],
        '50'=>['lever_num'=>50, 'safe_percent'=>15, 'fee_rate'=>0.10],
    ];

    public function moneyCurrency()
    {
        return $this->belongsTo(Currency::class, 'money_currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    public function tradeCurrency()
    {
        return $this->belongsTo(Currency::class, 'trade_currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    /**
     * 获取交易对列表
     * @param integer $trade_id
     * @param integer $type 类型 1-持仓 2-委托
     * @param integer $money_type
     * @param integer $currency_id
     * @param integer $status 状态 0-历史记录 1-当前
     * @return string
     */
    public static function get_order_list($member_id, $trade_id, $type, $money_type, $currency_id, $page, $length, $status = 0)
    {
        $where = [
            'member_id'=>$member_id,
            'trade_id'=>$trade_id,
            //'type'=>$type,
            'money_type'=>$money_type,
            'money_currency_id'=>$currency_id,
        ];
        if ($type == 1) {//1-持仓
            if ($status == 1) {//状态 0-历史记录 1-当前
                $where['status'] = 3;
            }
            else {
                $where['status'] = ['IN', '4,5'];
            }
            $order = ['status'=>'asc', 'add_time'=>'desc'];
        }
        else {//2-委托
            $where['type'] = 2;//查询永续合约
            if ($status == 1) {//状态 0-历史记录 1-当前
                $where['trust_status'] = 1;
            }
            else {
                $where['trust_status'] = ['IN', '2,3'];
            }
            $order = ['trust_time'=>'desc', 'trust_status'=>'asc'];
        }
        $start = ($page - 1) * $length;
        $select = (new self)->where($where)->limit($start, $length)->order($order)->select();
        $order_list = [];
        $tarde = ContractTrade::get($trade_id);
        $currency = Currency::get($tarde['currency_id']);
        $trade_currency = Currency::get($tarde['trade_currency_id']);
        $currency_name_left = $currency['currency_name'];
        $currency_name_right = $trade_currency['currency_name'];
        //$money_currency_id = ContractConfig::get_value('contract_currency_id', 35);
        $money_currency_id = $currency_id;
        $virtual_currency_name = ContractConfig::get_value('contract_virtual_currency_name', 'KOIC');
        $money_currency = Currency::get($money_currency_id);
        $money_currency_name = $money_currency['currency_name'];
        if ($money_type == 2) $money_currency_name .= $virtual_currency_name;
        $income_total = 0;
        $where1 = [
            'member_id'=>$member_id,
            'trade_id'=>$trade_id,
            'type'=>$type,
            'money_type'=>$money_type,
            'money_currency_id'=>$currency_id,
            'status'=>3,
        ];
        $money_total = (new self)->where($where1)->sum('money_currency_num');
        if (count($select) > 0) {
            $income_total = (new self)->where($where1)->sum('income_money');
            foreach ($select as $key => $value) {
                //$income_total += $value['income_money'];
                //$money_total += $value['money_currency_num'];
                $result_time = '';
                if ($value['status'] == 4) $result_time = '待结算中';
                if ($value['status'] == 5) $result_time = date('Y-m-d H:i:s', $value['result_time']);
                $income_money = floattostr($value['income_money']);
                $fee_value = floattostr($value['fee_value']);
                $closeout_price = floattostr($value['closeout_price']);
                if ($value['status'] == 3) {//3-持仓中
                    //根据当前时间获取那个时间点分钟整点的收盘价作为平仓价，如22点23分50秒，那么获取22点23分的收盘价作为平仓价
                    //$closeout_price = 10384.57;
                    //$closeout_price = \app\common\model\ContractKline::get_price($value['trade_id'], 60, 'open_price');
                    /*$price_time = strtotime(date('Y-m-d H:i:00'));
                    $closeout_price = \app\common\model\ContractKline::get_price($value['trade_id'], 60, 'close_price', $price_time);
                    $n = 1;
                    while (!$closeout_price && $n < 10) {
                        //当获取失败时，获取上一个周期的价格返回
                        $closeout_price = \app\common\model\ContractKline::get_price($value['trade_id'], 60, 'close_price', $price_time - 60 * $n);
                        $n++;
                    }
                    list($income_money, $fee_value) = self::get_order_income($value, $closeout_price);*/
                    $closeout_price = 0;
                    $income_money = 0;
                    $fee_value = 0;
                    $income_total += $income_money;
                }
                if ($value['type'] == 1) {//1-交割合约
                    $period = '';
                    $diff = $value['end_time'] - $value['start_time'];
                    if ($diff == 300) {//5分钟
                        $period = date('Y-m-d H:i', $value['start_time']).'-'.date('H:i', $value['end_time']);
                    } else if ($diff == 900) {//15分钟
                        $period = date('Y-m-d H:i', $value['start_time']).'-'.date('H:i', $value['end_time']);
                    } else if ($diff == 1800) {//30分钟
                        $period = date('Y-m-d H:i', $value['start_time']).'-'.date('H:i', $value['end_time']);
                    } else if ($diff == 3600) {//1小时
                        $period = date('Y-m-d H', $value['start_time']).'-'.date('H', $value['end_time']);
                    } else if ($diff == 86400) {//1天
                        $period = date('Y/m/d', $value['start_time']).'-'.date('m/d', $value['end_time']);
                    } else if ($diff == 604800) {//1周
                        $period = date('Y/m/d', $value['start_time']).'-'.date('m/d', $value['end_time']);
                    } else if ($diff >= 2419200 && $diff <= 2678400) {//1月
                        $period = date('Y/m/d', $value['start_time']).'-'.date('m/d', $value['end_time']);
                    }
                }
                else {
                    $period = lang('contract_trust_order');
                }
                if ($type == 1) {//1-持仓
                    $order_list[] = [
                        'id'=>$value['id'],
                        'number'=>$value['number'],
                        //'currency_name_left'=>$currency_name_left,
                        //'currency_name_right'=>$currency_name_right,
                        //'name'=>$currency_name_left.'/'.$currency_name_right,
                        'type'=>$value['type'],
                        'type_name'=>self::TYPE_ENUM[$value['type']],
                        'buy_type'=>$value['buy_type'],
                        'buy_type_name'=>self::BUY_TYPE_ENUM[$value['buy_type']],
                        'income_money'=>floattostr($income_money),
                        'money_currency_num'=>intval($value['money_currency_num']),
                        'safe_currency_num'=>intval($value['safe_currency_num']),
                        'money_currency_name'=>$money_currency_name,
                        'lever_num'=>$value['lever_num'],
                        'currency_num'=>floattostr($value['currency_num']),
                        'deal_price'=>floattostr($value['deal_price']),
                        'usdt_cny_price'=>floattostr($value['usdt_cny_price']),
                        'money_currency_cny_price'=>floattostr($value['money_currency_cny_price']),
                        'closeout_price'=>$closeout_price,
                        'stop_profit_type'=>$value['stop_profit_type'],
                        'stop_profit_percent'=>$value['stop_profit_percent'],
                        'stop_loss_type'=>$value['stop_loss_type'],
                        'stop_loss_percent'=>-$value['stop_loss_percent'],
                        'stop_profit_price'=>floattostr($value['stop_profit_price']),
                        'stop_loss_price'=>floattostr($value['stop_loss_price']),
                        'fee_rate'=>$value['fee_rate'],
                        'buy_fee'=>$value['buy_fee'],
                        'fee_value'=>floattostr($fee_value),
                        'status'=>$value['status'],
                        'add_time'=>date('Y-m-d H:i:s', $value['add_time']),
                        //'add_time'=>$value['add_time'],
                        //'period'=>$value['type'] == 1 ? date('Y-m-d H:i', $value['start_time']).'-'.date('H:i', $value['end_time']) : lang('contract_trust_order'),
                        'period'=>$period,
                        'result_time'=>$value['result_time'] > 0 ? date('Y-m-d H:i:s', $value['result_time']) : '',
                    ];
                }
                else {//2-委托
                    $order_list[] = [
                        'id'=>$value['id'],
                        'number'=>$value['number'],
                        //'currency_name_left'=>$currency_name_left,
                        //'currency_name_right'=>$currency_name_right,
                        //'name'=>$currency_name_left.'/'.$currency_name_right,
                        'type'=>$value['type'],
                        'type_name'=>self::TYPE_ENUM[$value['type']],
                        'buy_type'=>$value['buy_type'],
                        'buy_type_name'=>self::BUY_TYPE_ENUM[$value['buy_type']],
                        'income_money'=>floattostr($income_money),
                        'money_currency_num'=>intval($value['money_currency_num']),
                        'safe_currency_num'=>intval($value['safe_currency_num']),
                        'money_currency_name'=>$money_currency_name,
                        'lever_num'=>$value['lever_num'],
                        'currency_num'=>floattostr($value['currency_num']),
                        'deal_price'=>$value['trust_status'] == 2 ? floattostr($value['deal_price']) : '--',
                        'trust_status'=>$value['trust_status'],
                        'add_time'=>$value['add_time'] > 0 ? date('Y-m-d H:i:s', $value['add_time']) : '',
                        //'add_time'=>$value['add_time'],
                        //'period'=>$value['type'] == 1 ? date('Y-m-d H:i', $value['start_time']).'-'.date('H:i', $value['end_time']) : lang('contract_trust_order'),
                        'period'=>$period,
                        'trust_price'=>floattostr($value['trust_price']),
                        'trust_time'=>date('Y-m-d H:i:s', $value['trust_time']),
                        'trust_cancel_time'=>$value['trust_cancel_time'] > 0 ? date('Y-m-d H:i:s', $value['trust_cancel_time']) : '',
                    ];
                }
            }
        }
        return ['income_total'=>floattostr(keepPoint($income_total, 6)),'money_total'=>floattostr($money_total),'currency_name'=>$money_currency_name,'order_list'=>$order_list];
    }

    /**
     * 下单
     * @param integer $trade_info 交易对信息
     * @param integer $type 订单类型
     * @param integer $money_type 账户类型
     * @param integer $currency_id 币种id
     * @param integer $buy_type 买类型
     * @param integer $lever_num 杠杆
     * @param integer $money_num 保证金
     * @param integer $stop_profit_percent 止盈比例
     * @param integer $stop_loss_percent 止损比例
     * @param float $deal_price 价格
     * @param float $safe_money 保险金
     * @param float $safe_percent 保险金比例
     * @param float $trust_price 委托价
     * @param float $close_price 时时价格
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function add_order($member_id, $trade_info, $type, $money_type, $currency_id, $buy_type, $lever_num, $money_num, $stop_profit_percent, $stop_loss_percent, $deal_price, $safe_money, $safe_percent, $trust_price, $close_price)
    {
        try {
            self::startTrans();

            $now = time();
            $add_time = 0;
            $trust_time = 0;
            $trust_status = 0;//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
            $order_number = self::get_order_number();
            //$money_currency_id = ContractConfig::get_value('contract_currency_id', 35);
            $money_currency_id = $currency_id;
            $buy_fee_rate = ContractConfig::get_value('contract_buy_fee_rate', 2);
            //$fee_rate = ContractConfig::get_value('contract_fee_rate', 2);
            $lever_list = ContractOrder::CLOSEOUT_LEVER_LIST;
            $fee_rate = $lever_list[$lever_num]['fee_rate'];
            //手续费改为下单时计算，公式：保证金*杠杆*手续费率/100
            $fee = keepPoint($money_num * $lever_num * $fee_rate / 100, 6);
            //买入手续费，公式：保证金*杠杆*手续费率/100
            $buy_fee = keepPoint($money_num * $lever_num * $buy_fee_rate / 100, 6);
            /*//根据当前时间获取那个时间点分钟整点的开盘价作为成交价，如22点23分50秒，那么获取22点23分的开盘价作为成交价
            $deal_price = 10344.57;
            //$deal_price = \app\common\model\ContractKline::get_price($trade_info['id'], 60, 'open_price');
            $deal_price = 0;//所有订单进入队列进行处理*/
            //if ($deal_price) {//获取成交价格成功，订单直接成交成为持仓订单
            if (false) {//所有订单都进入队列处理
                $status = 3;
                $usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
                //$currency_cny_price = ContractConfig::get_value('contract_currency_cny_price', 1);
                $currency_cny_price = CurrencyPriceTemp::get_price_currency_id($money_currency_id, 'CNY');
                //if ($money_type == 2) $currency_cny_price = ContractConfig::get_value('contract_virtual_currency_cny_price', 1);
                //TODO：交易数量=(保证金数量*保证金人民币价格)/USDT人民币价格/交易对价格
                //TODO：2020-03-18 交易数量=((保证金数量-手续费)*保证金人民币价格)/USDT人民币价格/交易对价格
                $currency_num = keepPoint(((($money_num - $fee) * $lever_num * $currency_cny_price) / $usdt_cny_price) / $deal_price, 3);
                if ($trade_info['currency_id'] == 8 && ($money_currency_id == 8 || $money_currency_id == 35)) {//2020-03-23，KOI改为XRP+，XRP/USDT交易对交易数量=保证金数量
                    //2020-05-28，币种为XRP、XRP⁺时，XRP/USDT交易对交易数量=保证金数量*杠杆
                    $currency_num = $money_num * $lever_num;
                }
                //TODO：止盈/止损平仓价=(止盈比例/100)*保证金数量*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：2020-03-18 止盈/止损平仓价=(止盈比例/100)*(保证金数量-手续费)*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：新的止盈/止损平仓价公式(暂未使用)：(杠杆±(止盈(损)比例/100))*成交价格/杠杆 或者：±((止盈(损)比例/100)*成交价格/杠杆)+成交价格
                if ($buy_type == 1) {//买涨
                    //$stop_profit_price = keepPoint((($stop_profit_percent * $money_num / 100) / ($currency_num * $lever_num)) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $stop_profit_percent > 0 ? keepPoint((($stop_profit_percent / 100) * ($money_num - $fee) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($lever_num + ($stop_profit_percent / 100)) * $deal_price) / $lever_num, $trade_info['price_length']);
                    //$stop_loss_price = keepPoint((-($stop_loss_percent * $money_num / 100) / ($currency_num * $lever_num)) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $stop_loss_percent > 0 ? keepPoint((-($stop_loss_percent / 100) * ($money_num - $fee) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($lever_num - ($stop_profit_percent / 100)) * $deal_price) / $lever_num, $trade_info['price_length']);
                }
                else {//买跌
                    //$stop_profit_price = keepPoint((-($stop_profit_percent * $money_num / 100) / ($currency_num * $lever_num)) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $stop_profit_percent > 0 ? keepPoint((-($stop_profit_percent / 100) * ($money_num - $fee) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($lever_num - ($stop_profit_percent / 100)) * $deal_price) / $lever_num, $trade_info['price_length']);
                    //$stop_loss_price = keepPoint((($stop_loss_percent * $money_num / 100) / ($currency_num * $lever_num)) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $stop_loss_percent > 0 ? keepPoint((($stop_loss_percent / 100) * ($money_num - $fee) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($lever_num + ($stop_profit_percent / 100)) * $deal_price) / $lever_num, $trade_info['price_length']);
                }
            }
            else {//获取成交价格失败，订单进入队列
                $usdt_cny_price = 0;
                $currency_cny_price = 0;
                $status = 1;
                $currency_num = 0;
                $stop_profit_price = 0;
                $stop_loss_price = 0;
                if ($type == 1) {//1-时时合约
                    $trust_price = 0;
                    $add_time = $now;
                }
                else {//永续合约
                    $trust_status = 1;//委托状态 0-未委托 1-委托中 2-已成交 3-已撤销
                    $trust_time = $now;//委托时间
                    $deal_price = 0;
                    $status = 6;//6-委托中 7-撤销委托
                }
            }
            if ($type == 1) {//1-时时合约
                list($start_time, $end_time) = self::get_start_end_time($now);
            }
            else {//2-永续合约
                $start_time = 0;
                $end_time = 0;
            }
            $order = self::create([
                'member_id'=>$member_id,
                'number'=>$order_number,
                'type'=>$type,
                'money_type'=>$money_type,
                'trade_id'=>$trade_info['id'],
                'money_currency_id'=>$money_currency_id,
                'currency_id'=>$trade_info['currency_id'],
                'trade_currency_id'=>$trade_info['trade_currency_id'],
                'buy_type'=>$buy_type,
                'lever_num'=>$lever_num,
                'money_currency_num'=>$money_num,
                'safe_currency_num'=>$safe_money,
                'safe_percent'=>$safe_percent,
                'currency_num'=>$currency_num,
                'stop_profit_percent'=>$stop_profit_percent,
                'stop_profit_price'=>$stop_profit_price,
                'stop_loss_percent'=>$stop_loss_percent,
                'stop_loss_price'=>$stop_loss_price,
                //'deal_price'=>$deal_price,
                'deal_price_1'=>$deal_price,
                'close_price'=>$close_price,
                'usdt_cny_price'=>$usdt_cny_price,
                'money_currency_cny_price'=>$currency_cny_price,
                'trust_price'=>$trust_price,
                'trust_status'=>$trust_status,
                'trust_time'=>$trust_time,
                'buy_fee_rate'=>$buy_fee_rate,
                'fee_rate'=>$fee_rate,
                'buy_fee'=>$buy_fee,
                'fee_value'=>$fee,
                'start_time'=>$start_time,
                //'last_closeout_time'=>strtotime($start_time),
                //'next_closeout_time'=>$start_time + 60,//下次平仓时间 用于自动平仓计算，默认为订单开始时间+60秒
                'next_closeout_time'=>strtotime(date('Y-m-d H:i:00', $now)) + 60,//下次平仓时间 用于自动平仓计算，默认为订单订单创建时间下一分钟
                'end_time'=>$end_time,
                'add_time'=>$add_time,
                'status'=>$status,
            ]);
            if (empty($order)) {
                throw new Exception(lang('插入记录失败-1'));
            }
            $orderId = $order->getLastInsID();

            // 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($member_id, $money_currency_id);
            if(!$usersCurrency) throw new Exception("获取资产错误");

            //$number = $money_num + $safe_money + $buy_fee;
            $number = $money_num + $buy_fee;
            if ($money_type == 1) {
                $bookType = AccountBookType::CUT_CONTRACT_ORDER;
                $content = 'cut_contract_order';
                if ($type == 2) {
                    $bookType = AccountBookType::FOREVER_CONTRACT_ORDER;
                    $content = 'forever_contract_order';
                }
                // 账本
                $flag = AccountBook::add_accountbook($member_id, $money_currency_id, $bookType, $content, 'out', $number, $orderId, $buy_fee);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('num',$number);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }

                if ($type == 2) {//2-永续合约
                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('forzen_num',$number);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }

                if ($safe_money > 0) {
                    // 账本
                    $flag = AccountBook::add_accountbook($member_id, $money_currency_id, AccountBookType::CONTRACT_SAFE, 'contract_safe', 'out', $safe_money, $orderId);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('num',$safe_money);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    if ($type == 2) {//2-永续合约
                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('forzen_num',$safe_money);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                    }
                }
            }
            else {//模拟账户

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('keep_num',$number);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                //添加持仓记录
                $flag = HongbaoKeepLog::add_log('contract_order',$member_id,$money_currency_id,$number,$orderId);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            //$r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 处理订单
     * @param ContractOrder $order 订单数据
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function deal_order(self $order)
    {
        try {
            self::startTrans();

            $trade_info = ContractTrade::get($order['trade_id']);
            /*//根据订单的成交时间获取那个时间点分钟整点的开盘价作为成交价，如22点23分50秒，那么获取22点23分的开盘价作为成交价
            $deal_price = 10344.57;
            $time = strtotime(date('Y-m-d H:i:00', $order['add_time']));
            $deal_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'open_price', $time);*/
            //根据订单的成交时间获取那个时间点分钟整点获取价格，如22点23分50秒，那么获取22点23分的价格
            $time = strtotime(date('Y-m-d H:i:00', $order['add_time']));
            $priceWhere = [
                'trade_id'=>$order['trade_id'],
                'type'=>60,
                'add_time'=>$time,
            ];
            $priceFind = \app\common\model\ContractKline::where($priceWhere)->find();
            if (!$priceFind) {
                throw new Exception(lang('获取实时价格失败'));
                $r['code'] = ERROR1;
                $r['message'] = lang('获取实时价格失败');
            }
            //获取订单成交价格-用户提交
            $deal_price_1 = $order['deal_price_1'];
            $hign_max = keepPoint($priceFind['hign_price'] * (1 + floatval(mt_rand(0, 10) / 10) * $trade_info['price_rate'] / 1000), $trade_info['price_length']);
            $deal_price = min($deal_price_1, $hign_max);//订单成交价格-用户提交如果大于最高价格，最高价格作为成交价格
            $low_min = keepPoint($priceFind['low_price'] * (1 - floatval(mt_rand(0, 10) / 10) * $trade_info['price_rate'] / 1000), $trade_info['price_length']);
            $deal_price = max($deal_price, $low_min);//成交价格如果小于最低价格，最低价格作为成交价格
            $order['deal_price'] = $deal_price;
            if ($deal_price > 0) {//获取成交价格成功，订单成交成为持仓订单
                $order['deal_price'] = $deal_price;
                $order['status'] = 3;
                $usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
                //$currency_cny_price = ContractConfig::get_value('contract_currency_cny_price', 1);
                $currency_cny_price = CurrencyPriceTemp::get_price_currency_id($order['money_currency_id'], 'CNY');
                //if ($order['money_type'] == 2) $currency_cny_price = ContractConfig::get_value('contract_virtual_currency_cny_price', 1);
                //TODO：交易数量=(保证金数量*保证金人民币价格)/USDT人民币价格/交易对价格
                //TODO：2020-03-18 交易数量=((保证金数量-手续费)*保证金人民币价格)/USDT人民币价格/交易对价格
                $currency_num = keepPoint(((($order['money_currency_num'] - $order['fee_value']) * $order['lever_num'] * $currency_cny_price) / $usdt_cny_price) / $deal_price, 3);
                if ($trade_info['currency_id'] == 8 && ($order['money_currency_id'] == 8 || $order['money_currency_id'] == 35)) {//2020-03-23，KOI改为XRP+，XRP/USDT交易对交易数量=保证金数量
                    //2020-05-28，币种为XRP、XRP⁺时，XRP/USDT交易对交易数量=保证金数量*杠杆
                    $currency_num = $order['money_currency_num'] * $order['lever_num'];
                }
                //TODO：止盈/止损平仓价=(止盈比例/100)*保证金数量*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：2020-03-18 止盈/止损平仓价=(止盈比例/100)*(保证金数量-手续费)*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：新的止盈/止损平仓价公式(暂未使用)：(杠杆±(止盈(损)比例/100))*成交价格/杠杆 或者：±((止盈(损)比例/100)*成交价格/杠杆)+成交价格
                $order['currency_num'] = $currency_num;
                if ($order['buy_type'] == 1) {//买涨
                    //$stop_profit_price = keepPoint((($order['stop_profit_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $order['stop_profit_percent'] > 0 ? keepPoint((($order['stop_profit_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($order['lever_num'] + ($order['stop_profit_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_profit_price'] = $stop_profit_price;
                    //$stop_loss_price = keepPoint((-($order['stop_loss_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $order['stop_loss_percent'] > 0 ? keepPoint((-($order['stop_loss_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    if ($stop_loss_price < 0) $stop_loss_price = 0;
                    //$stop_loss_price = keepPoint((($order['lever_num'] - ($order['stop_loss_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_loss_price'] = $stop_loss_price;
                }
                else {//买跌
                    //$stop_profit_price = keepPoint((-($order['stop_profit_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $order['stop_profit_percent'] > 0 ? keepPoint((-($order['stop_profit_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    if ($stop_profit_price < 0) $stop_profit_price = 0;
                    //$stop_profit_price = keepPoint((($order['lever_num'] - ($order['stop_profit_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_profit_price'] = $stop_profit_price;
                    //$stop_loss_price = keepPoint((($order['stop_loss_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $order['stop_loss_percent'] > 0 ? keepPoint((($order['stop_loss_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($order['lever_num'] + ($order['stop_loss_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_loss_price'] = $stop_loss_price;
                }
                $order['usdt_cny_price'] = $usdt_cny_price;
                $order['money_currency_cny_price'] = $currency_cny_price;
                $order['queue_result'] = 'SUCCESS';
                $order['queue_result'] = 'SUCCESS';
                $order['queue_time'] = time();
                $r['code'] = SUCCESS;
                $r['message'] = lang('successful_operation');
            }
            else {//获取成交价格失败，订单进入队列
                throw new Exception(lang('订单异常成交价格为0'));
                $order['status'] = 2;//状态 1-队列中 2-队列处理失败 3-持仓中 4-结算中 5-已结算
                $order['queue_result'] = 'ERROR:获取成交价格失败';
                $order['queue_time'] = time();
                $r['code'] = ERROR1;
                $r['message'] = lang('获取成交价格失败');
            }

            $res = $order->save();
            if ($res === false) {
                throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
            }

            /*// 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($order['member_id'], $order['money_currency_id']);
            if(!$usersCurrency) throw new Exception("获取资产错误");

            $number = $order['money_currency_num'] + $order['safe_currency_num'] + $order['buy_fee'];
            if ($order['money_type'] == 1) {
                // 账本
                $flag = AccountBook::add_accountbook($order['member_id'], $order['money_currency_id'], AccountBookType::CONTRACT_ORDER, 'contract_order', 'out', $number, $order['id'], $order['buy_fee']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setDec('num',$number);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }
            }
            else {//模拟账户

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('keep_num',$number);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                //添加持仓记录
                $flag = HongbaoKeepLog::add_log('contract_order',$order['member_id'],$order['money_currency_id'],$number,$order['id']);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
            }*/

            self::commit();
            //$r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 处理委托订单
     * @param ContractOrder $order 订单数据
     * @param $flag 标志 1-正常成交 2-实时价成交
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function deal_trust(self $order, $flag = 1)
    {
        try {
            self::startTrans();

            $trade_info = ContractTrade::get($order['trade_id']);
            //获取订单委托价
            $trust_price = $order['trust_price'];
            $deal_price = $trust_price;//订单成交价格=订单委托价
            if ($flag == 2) $deal_price = $order['close_price'];
            $order['deal_price'] = $deal_price;
            if ($deal_price > 0) {//获取成交价格成功，订单成交成为持仓订单
                $order['deal_price'] = $deal_price;
                $order['status'] = 3;
                $order['trust_status'] = 2;
                $order['add_time'] = time();
                $order['next_closeout_time'] = strtotime(date('Y-m-d H:i:00', $order['add_time'])) + 60;//下次平仓时间 用于自动平仓计算，默认为订单订单创建时间下一分钟
                $usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
                //$currency_cny_price = ContractConfig::get_value('contract_currency_cny_price', 1);
                $currency_cny_price = CurrencyPriceTemp::get_price_currency_id($order['money_currency_id'], 'CNY');
                //if ($order['money_type'] == 2) $currency_cny_price = ContractConfig::get_value('contract_virtual_currency_cny_price', 1);
                //TODO：交易数量=(保证金数量*保证金人民币价格)/USDT人民币价格/交易对价格
                //TODO：2020-03-18 交易数量=((保证金数量-手续费)*保证金人民币价格)/USDT人民币价格/交易对价格
                $currency_num = keepPoint(((($order['money_currency_num'] - $order['fee_value']) * $order['lever_num'] * $currency_cny_price) / $usdt_cny_price) / $deal_price, 3);
                if ($trade_info['currency_id'] == 8 && ($order['money_currency_id'] == 8 || $order['money_currency_id'] == 35)) {//2020-03-23，KOI改为XRP+，XRP/USDT交易对交易数量=保证金数量
                    //2020-05-28，币种为XRP、XRP⁺时，XRP/USDT交易对交易数量=保证金数量*杠杆
                    $currency_num = $order['money_currency_num'] * $order['lever_num'];
                }
                //TODO：止盈/止损平仓价=(止盈比例/100)*保证金数量*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：2020-03-18 止盈/止损平仓价=(止盈比例/100)*(保证金数量-手续费)*保证金人民币价格/USDT人民币价格/交易数量±成交价
                //TODO：新的止盈/止损平仓价公式(暂未使用)：(杠杆±(止盈(损)比例/100))*成交价格/杠杆 或者：±((止盈(损)比例/100)*成交价格/杠杆)+成交价格
                $order['currency_num'] = $currency_num;
                if ($order['buy_type'] == 1) {//买涨
                    //$stop_profit_price = keepPoint((($order['stop_profit_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $order['stop_profit_percent'] > 0 ? keepPoint((($order['stop_profit_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_profit_price = keepPoint((($order['lever_num'] + ($order['stop_profit_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_profit_price'] = $stop_profit_price;
                    //$stop_loss_price = keepPoint((-($order['stop_loss_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $order['stop_loss_percent'] > 0 ? keepPoint((-($order['stop_loss_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    if ($stop_loss_price < 0) $stop_loss_price = 0;
                    //$stop_loss_price = keepPoint((($order['lever_num'] - ($order['stop_loss_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_loss_price'] = $stop_loss_price;
                }
                else {//买跌
                    //$stop_profit_price = keepPoint((-($order['stop_profit_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_profit_price = $order['stop_profit_percent'] > 0 ? keepPoint((-($order['stop_profit_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    if ($stop_profit_price < 0) $stop_profit_price = 0;
                    //$stop_profit_price = keepPoint((($order['lever_num'] - ($order['stop_profit_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_profit_price'] = $stop_profit_price;
                    //$stop_loss_price = keepPoint((($order['stop_loss_percent'] * $order['money_currency_num'] / 100) / ($currency_num * $order['lever_num'])) + $deal_price, $trade_info['price_length']);
                    $stop_loss_price = $order['stop_loss_percent'] > 0 ? keepPoint((($order['stop_loss_percent'] / 100) * ($order['money_currency_num'] - $order['fee_value']) * $currency_cny_price / $usdt_cny_price / $currency_num) + $deal_price, $trade_info['price_length']) : 0;
                    //$stop_loss_price = keepPoint((($order['lever_num'] + ($order['stop_loss_percent'] / 100)) * $order['deal_price']) / $order['lever_num'], $trade_info['price_length']);
                    $order['stop_loss_price'] = $stop_loss_price;
                }
                $order['usdt_cny_price'] = $usdt_cny_price;
                $order['money_currency_cny_price'] = $currency_cny_price;
                //$order['queue_result'] = 'SUCCESS';
                //$order['queue_result'] = 'SUCCESS';
                //$order['queue_time'] = time();
                $r['code'] = SUCCESS;
                $r['message'] = lang('successful_operation');
            }
            else {//获取成交价格失败，订单进入队列
                throw new Exception(lang('订单异常成交价格为0'));
                $order['status'] = 2;//状态 1-队列中 2-队列处理失败 3-持仓中 4-结算中 5-已结算
                //$order['queue_result'] = 'ERROR:获取成交价格失败';
                //$order['queue_time'] = time();
                $r['code'] = ERROR1;
                $r['message'] = lang('获取成交价格失败');
            }

            $res = $order->save();
            if ($res === false) {
                throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
            }

            // 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($order['member_id'], $order['money_currency_id']);
            if(!$usersCurrency) throw new Exception("获取资产错误");

            if ($order['money_type'] == 1) {

                if ($order['type'] == 2) {//2-永续合约
                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$order['money_currency_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }

                if ($order['safe_currency_num'] > 0) {

                    if ($order['type'] == 2) {//2-永续合约
                        $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$order['safe_currency_num']);
                        if ($flag === false) {
                            throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                        }
                    }
                }
            }

            self::commit();
            //$r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 订单平仓
     * @param ContractOrder $order 订单数据
     * @param integer $closeout_type 平仓类型 1-到期平仓 2-止盈平仓 3-止损平仓 4-手动平仓
     * @param double $closeout_price 平仓价格
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function order_closeout(self $order, $closeout_type, $closeout_price = 0)
    {
        try {
            self::startTrans();

            $order['closeout_price'] = $closeout_price;
            $order['closeout_type'] = $closeout_type;
            $order['closeout_time'] = time();
            $order['status'] = 4;

            $res = $order->save();
            if ($res === false) {
                throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            //$r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 结算订单
     * @param ContractOrder $order 订单数据
     * @param string $log 日志
     * @return mixed
     * @throws \think\exception\PDOException
     */
    static function result_order(self $order, &$log)
    {
        try {
            self::startTrans();

            $now = time();
            if ($order['closeout_price'] <= 0) {//订单平仓价未获取到，重新进行获取
                $log .= ",平仓价未获取到,重新进行获取";
                if ($order['closeout_type'] != 4) {
                    $log .= ",订单平仓类型异常";
                    $order['closeout_type'] = 0;
                    $order['next_closeout_time'] = $order['add_time'] + 60;//下次平仓时间 用于自动平仓计算，默认为订单订单创建时间下一分钟
                    $res = $order->save();
                    if ($res === false) {
                        throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
                    }
                    throw new Exception(lang('订单平仓类型和价格异常,重新进行平仓处理'));
                }
                else {//手动平仓
                    //根据订单平仓时间获取那个时间点分钟整点的收盘价作为平仓价，如22点23分50秒，那么获取22点23分的收盘价作为平仓价
                    $closeout_price = 10384.57;
                    $price_time = strtotime(date('Y-m-d H:i:00', $order['closeout_time']));
                    //$closeout_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'open_price', $price_time);
                    $closeout_price = \app\common\model\ContractKline::get_price($order['trade_id'], 60, 'close_price', $price_time);
                    if (!$closeout_price) throw new Exception(lang('获取平仓价格失败'));
                    $order['closeout_price'] = $closeout_price;
                }
            }
            $order['result_time'] = $now;
            $income_money = 0;
            //$fee = 0;
            /*//手续费公式改为：保证金*杠杆*手续费率/100
            $fee = keepPoint($order['money_currency_num'] * $order['lever_num'] * $order['fee_rate'] / 100, 6);*/
            //手续费改为下单时计算
            $fee = $order['fee_value'];
            $closeout_price = $order['closeout_price'];
            $log .= ",closeout_price:{$order['closeout_price']}";
            //$income_money = keepPoint(($closeout_price - $order['deal_price']) * $order['currency_num'] * $order['lever_num'], 6);
            //TODO：收益=±交易数量*(平仓价-成交价)*USDT人民币价格/保证金人民币价格
            //$usdt_cny_price = CurrencyPriceTemp::getUsdtCny();
            $usdt_cny_price = $order['usdt_cny_price'];
            //$currency_cny_price = ContractConfig::get_value('contract_currency_cny_price', 1);
            $currency_cny_price = $order['money_currency_cny_price'];
            $income_money = keepPoint($order['currency_num'] * ($closeout_price - $order['deal_price']) * $usdt_cny_price / $currency_cny_price, 6);
            /*//TODO：新的收益公式(暂未使用)：收益=±保证金数量*杠杆*(平仓价-成交价)/成交价
            $income_money = keepPoint($order['money_currency_num'] * $order['lever_num'] * ($closeout_price - $order['deal_price']) / $order['deal_price'], 6);*/
            $stop_profit_income = keepPoint($order['stop_profit_percent'] * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止盈收益
            $stop_loss_income = keepPoint(-$order['stop_loss_percent'] * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止损收益
            $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 500);
            $stop_profit_income_max = keepPoint($stop_profit_percent_max * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止盈收益最大值
            if ($stop_profit_income <= 0) $stop_profit_income = $stop_profit_income_max;
            $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 100);
            $stop_loss_income_max = keepPoint(-$stop_loss_percent_max * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止损收益最大值
            if ($order['closeout_type'] == 2) {//止盈平仓
                $log .= ",止盈平仓,收益为正";
                $closeout_price = $order['stop_profit_price'];
                if ($order['stop_profit_type'] == 1) {//止盈类型 1-按比例
                    $income_money = $stop_profit_income;
                }
                else {//2-按价格
                    $income_money = keepPoint($order['currency_num'] * ($closeout_price - $order['deal_price']) * $usdt_cny_price / $currency_cny_price, 6);
                    $income_money = min($income_money, $stop_profit_income_max);
                }
                //$fee = keepPoint(($order['money_currency_num'] + $income_money) * $order['fee_rate'] / 100, 6);
            }
            else if ($order['closeout_type'] == 3) {//止损平仓
                $log .= ",止损平仓,收益为负";
                $closeout_price = $order['stop_loss_price'];
                if ($order['stop_profit_type'] == 1) {//止损类型 1-按比例
                    $income_money = $stop_loss_income;
                }
                else {//2-按价格
                    $income_money = -abs(keepPoint($order['currency_num'] * ($closeout_price - $order['deal_price']) * $usdt_cny_price / $currency_cny_price, 6));
                    $income_money = max($income_money, $stop_loss_income_max);
                }
                //$fee = keepPoint($order['money_currency_num'] * $order['fee_rate'] / 100, 6);
            }
            else {
                if ($order['deal_price'] == $closeout_price) {//成交价格=平仓价格，收益为0，全部退还保证金
                    $log .= ",成交价格=平仓价格,收益为0,退还保证金";
                    $income_money = 0;
                }
                else if($order['deal_price'] > $closeout_price) {//成交价>平仓价格，说明跌
                    $log .= ",成交价>平仓价格,说明跌";
                    if ($order['buy_type'] == 1) {//买涨，损利
                        $log .= ",buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",收益为负";
                        $income_money = $income_money;
                        //2020-03-25，止损平仓价>0时才需要判断止损收益
                        if ($closeout_price < $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {//平仓价格<止损平仓价格，收益为止损收益
                            $log .= ",平仓价格<止损平仓价格,收益为止损收益";
                            $income_money = $stop_loss_income;
                        }
                        //$fee = keepPoint($order['money_currency_num'] * $order['fee_rate'] / 100, 6);
                    }
                    else {//买跌，盈利
                        $log .= ",buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",收益为正";
                        $income_money = -$income_money;
                        //2020-03-25，止盈平仓价>0时才需要判断止盈收益
                        if ($closeout_price < $order['stop_profit_price'] && $order['stop_profit_price'] > 0) {//平仓价格<止盈平仓价格，收益为止盈收益
                            $log .= ",平仓价格<止盈平仓价格,收益为止盈收益";
                            $income_money = $stop_profit_income;
                        }
                        $income_money = min($income_money, $stop_profit_income);
                        //$fee = keepPoint(($order['money_currency_num'] + $income_money) * $order['fee_rate'] / 100, 6);
                    }
                }
                else if($order['deal_price'] < $closeout_price) {//成交价<平仓价格，说明涨
                    $log .= ",成交价<平仓价格,说明涨";
                    if ($order['buy_type'] == 1) {//买涨，盈利
                        $log .= ",buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",收益为正";
                        $income_money = $income_money;
                        //2020-03-25，止盈平仓价>0时才需要判断止盈收益
                        if ($closeout_price > $order['stop_profit_price'] && $order['stop_profit_price'] > 0) {//平仓价格>止盈平仓价格，收益为止盈收益
                            $log .= ",平仓价格>止盈平仓价格,收益为止盈收益";
                            $income_money = $stop_profit_income;
                        }
                        $income_money = min($income_money, $stop_profit_income);
                        //$fee = keepPoint(($order['money_currency_num'] + $income_money) * $order['fee_rate'] / 100, 6);
                    }
                    else {//买跌，损利
                        $log .= ",buy_type:".ContractOrder::BUY_TYPE_ENUM[$order['buy_type']].",收益为负";
                        $income_money = -$income_money;
                        //2020-03-25，止损平仓价>0时才需要判断止损收益
                        if ($closeout_price > $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {//平仓价格>止损平仓价格，收益为止损收益
                            $log .= ",平仓价格>止损平仓价格,收益为止损收益";
                            $income_money = $stop_loss_income;
                        }
                        //$fee = keepPoint($order['money_currency_num'] * $order['fee_rate'] / 100, 6);
                    }
                }
            }
            if (abs($income_money) <= 0) {
                $fee = 0;
            }
            if ($income_money < 0 && $income_money < $stop_loss_income_max) {//如果收益为负，且小于止损收益最大值，那么收益等于止损收益最大值
                $income_money = $stop_loss_income_max;
            }
            $order['income_money'] = $income_money;
            $order['fee_value'] = $fee;
            $order['status'] = 5;
            $order['result_time'] = $now;
            $log .= ",income_money:{$income_money},fee:{$fee}";

            $lock_currency_num = 0;
            if ($order['money_type'] == 1 && $order['safe_currency_num'] > 0 && $income_money < 0) {//真实账户，交了保险金，收益亏损
                $lock_percent = ContractConfig::get_value('contract_lock_percent', 90);
                if ($lock_percent > 0) {
                    $order['lock_percent'] = $lock_percent;
                    $lock_currency_num = keepPoint(abs($income_money) * $lock_percent / 100, 6);
                    $order['lock_currency_num'] = $lock_currency_num;
                    $log .= ",保险金>0,锁仓,锁仓比例:{$lock_percent}%,锁仓金额:{$lock_currency_num}";
                }
            }

            $res = $order->save();
            if ($res === false) {
                throw new Exception(lang('更新记录失败').'-in line:'.__LINE__);
            }

            $number = keepPoint($order['money_currency_num'] + $income_money - $fee,6);
            $log .= ",number:{$number}";

            // 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($order['member_id'], $order['money_currency_id']);
            if(!$usersCurrency) throw new Exception("获取资产错误");
            if ($number > 0) {
                if ($order['money_type'] == 1) {
                    $bookType = AccountBookType::CUT_CONTRACT_INCOME;
                    $content = 'cut_contract_income';
                    if ($order['type'] == 2) {
                        $bookType = AccountBookType::FOREVER_CONTRACT_INCOME;
                        $content = 'forever_contract_income';
                    }
                    // 账本
                    $flag = AccountBook::add_accountbook($order['member_id'], $order['money_currency_id'], $bookType, $content, 'in', $number, $order['id'], $fee);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'num'=>$usersCurrency['num']])->setInc('num',$number);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }
                else {//模拟账户

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('keep_num',$number);
                    if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                    //添加持仓记录
                    $flag = HongbaoKeepLog::add_log('contract_income',$order['member_id'],$order['money_currency_id'],$number,$order['id']);
                    if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }
            }

            //处理进入锁仓
            if ($order['money_type'] == 1) {
                if ($lock_currency_num > 0) {
                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id'],'contract_lock'=>$usersCurrency['contract_lock']])->setInc('contract_lock',$lock_currency_num);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    //添加锁仓记录
                    $flag = ContractLockLog::add_log(1,$order['member_id'],$order['money_currency_id'],$lock_currency_num);
                    if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
            //$r['result'] = ['room_id'=>$room['rl_room_id']];
        } catch (Exception $exception) {
            self::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 获取合约交易总额-koi
     * @param $member_id
     */
    public static function get_order_total($member_id)
    {
        $where = [
            'member_id'=>$member_id,
            'status'=>['in','3,4,5'],
        ];
        $total = self::where($where)->sum('money_currency_num') ? : 0;
        return $total;
    }

    /**
     * 获取昨天合约交易次数-koi
     * @param $member_id
     */
    public static function get_yestoday_order_num($member_id)
    {
        $yestodayEnd = todayBeginTimestamp() - 1;
        $yestodayStart = $yestodayEnd - 86399;
        $where = [
            'member_id'=>$member_id,
            'status'=>['in','3,4,5'],
            'add_time'=>['between', [$yestodayStart, $yestodayEnd]],
        ];
        $num = self::where($where)->count('id') ? : 0;
        return $num;
    }

    public static function get_order_income(self $order, $closeout_price) {

        $stop_profit_income = keepPoint($order['stop_profit_percent'] * $order['money_currency_num'] / 100, 6);//止盈收益
        $stop_loss_income = keepPoint(-$order['stop_loss_percent'] * $order['money_currency_num'] / 100, 6);//止损收益
        $stop_profit_percent_max = ContractConfig::get_value('contract_stop_profit_percent_max', 500);
        $stop_profit_income_max = keepPoint($stop_profit_percent_max * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止盈收益最大值
        if ($stop_profit_income <= 0) $stop_profit_income = $stop_profit_income_max;
        $stop_loss_percent_max = ContractConfig::get_value('contract_stop_loss_percent_max', 100);
        $stop_loss_income_max = keepPoint(-$stop_loss_percent_max * ($order['money_currency_num'] - $order['fee_value']) / 100, 6);//止损收益最大值
        $income_money = 0;
        if ($closeout_price) {
            //$income_money = keepPoint(($closeout_price - $order['deal_price']) * $order['currency_num'] * $order['lever_num'], 6);
            $usdt_cny_price = $order['usdt_cny_price'];
            $currency_cny_price = $order['money_currency_cny_price'];
            $income_money = keepPoint($order['currency_num'] * ($closeout_price - $order['deal_price']) * $usdt_cny_price / $currency_cny_price, 6);
            if ($order['deal_price'] == $closeout_price) {//成交价格=平仓价格，收益为0，全部退还保证金
                $income_money = 0;
            }
            else if($order['deal_price'] > $closeout_price) {//成交价>平仓价格，说明跌
                if ($order['buy_type'] == 1) {//买涨，损利
                    $income_money = $income_money;
                    //2020-03-25，止损平仓价>0时才需要判断止损收益
                    if ($closeout_price < $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {//平仓价格<止损平仓价格，收益为止损收益
                        $income_money = $stop_loss_income;
                    }
                    //$fee = keepPoint($order['money_currency_num'] * $order['fee_rate'] / 100, 6);
                }
                else {//买跌，盈利
                    $income_money = -$income_money;
                    //2020-03-25，止盈平仓价>0时才需要判断止盈收益
                    if ($closeout_price < $order['stop_profit_price'] && $order['stop_profit_price'] > 0) {//平仓价格<止盈平仓价格，收益为止盈收益
                        $income_money = $stop_profit_income;
                    }
                    //$fee = keepPoint(($order['money_currency_num'] + $income_money) * $order['fee_rate'] / 100, 6);
                }
            }
            else if($order['deal_price'] < $closeout_price) {//成交价<平仓价格，说明涨
                if ($order['buy_type'] == 1) {//买涨，盈利
                    $income_money = $income_money;
                    //2020-03-25，止盈平仓价>0时才需要判断止盈收益
                    if ($closeout_price > $order['stop_profit_price'] && $order['stop_profit_price'] > 0) {//平仓价格>止盈平仓价格，收益为止盈收益
                        $income_money = $stop_profit_income;
                    }
                    $income_money = min($income_money, $stop_profit_income);
                    //$fee = keepPoint(($order['money_currency_num'] + $income_money) * $order['fee_rate'] / 100, 6);
                }
                else {//买跌，损利
                    $income_money = -$income_money;
                    //2020-03-25，止损平仓价>0时才需要判断止损收益
                    if ($closeout_price > $order['stop_loss_price'] && $order['stop_loss_price'] > 0) {//平仓价格>止损平仓价格，收益为止损收益
                        $income_money = $stop_loss_income;
                    }
                    //$fee = keepPoint($order['money_currency_num'] * $order['fee_rate'] / 100, 2);
                }
            }
        }
        if ($income_money < 0 && $income_money < $stop_loss_income_max) {//如果收益为负，且小于止损收益最大值，那么收益等于止损收益最大值
            $income_money = $stop_loss_income_max;
        }
        return floattostr($income_money);
    }

    /**
     * 获取订单编号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static private function get_order_number()
    {
        $order_number = date('YmdHis').randNum(6);
        if (self::where(['number'=>$order_number])->find()) {
            return self::get_order_number();
        }
        return $order_number;
    }

    /*
     * 获取合约账户列表
     */
    public static function get_money_type_list()
    {
        $money_list = [];
        $virtual_currency_name = ContractConfig::get_value('contract_virtual_currency_name', 'KOIC');
        //$contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
        $contract_currency_list_1 = explode(',', '8,35,38');//合约真实账户币种列表
        $contract_currency_list_2 = explode(',', ContractConfig::get_value('contract_currency_list_2', '35'));//合约模拟账户币种列表
        //真实账户
        foreach ($contract_currency_list_1 as $key => $value) {
            $currency = Currency::get($value);
            $str = '1_'.$value;
            $money_list[$str] = $currency['currency_name'];
        }
        //模拟账户
        foreach ($contract_currency_list_2 as $key => $value) {
            $currency = Currency::get($value);
            $str = '2_'.$value;
            $money_list[$str] = $currency['currency_name'].$virtual_currency_name;
        }
        return $money_list;
    }

    /**
     * 获取时时合约订单开始和结束时间
     */
    public static function get_start_end_time($now = 0) {

        $start_time = 0;
        $end_time = 0;
        if ($now == 0) $now = time();
        $contract_cut_order_type = ContractConfig::get_value('contract_cut_order_type', 1);//合约时时订单类型 1-5分钟 2-15分钟 3-30分钟 4-1小时 5-1天 6-1周 7-1月
        if ($contract_cut_order_type == 1 || $contract_cut_order_type == 2 || $contract_cut_order_type == 3 || $contract_cut_order_type == 4) {//1-5分钟 2-15分钟 3-30分钟 4-1小时
            $minute = date('i', $now);
            $time_config = ContractConfig::TIME_CONFIG;
            if ($contract_cut_order_type == 2) $time_config = 15;
            if ($contract_cut_order_type == 3) $time_config = 30;
            if ($contract_cut_order_type == 4) $time_config = 60;
            $minute1 = $minute % ($time_config * 2);
            $num = intval($minute / ($time_config * 2));
            //目前只做5分钟为一期的逻辑，其他分钟期数逻辑需要相应修改
            if ($minute1 < $time_config) {//00-05分钟这期
                $start_time = strtotime(date('Y-m-d H:00:00', $now)) + $num * ($time_config * 2) * 60;
                $end_time = strtotime(date('Y-m-d H:00:00', $now)) + ($num * ($time_config * 2) + $time_config) * 60;
            }
            else {//05-10分钟这期
                $start_time = strtotime(date('Y-m-d H:00:00', $now)) + ($num * ($time_config * 2) + $time_config) * 60;
                $end_time = strtotime(date('Y-m-d H:00:00', $now)) + ($num + 1) * $time_config * 2 * 60;
            }
        } else if ($contract_cut_order_type == 5) {//5-1天
            $contract_cut_order_day_hour = ContractConfig::get_value('contract_cut_order_day_hour', 2);//合约时时订单类型为1天时从几点开始新周期
            $day_time = strtotime(date('Y-m-d', $now).' '.sprintf("%02d", $contract_cut_order_day_hour).':00:00');
            if ($now >= $day_time) {//大于等于2点属于今天这期
                $start_time = $day_time;
                $end_time = $start_time + 86400;
            }
            else {//小于属于昨天这期
                $end_time = $day_time;
                $start_time = $end_time - 86400;
            }
        } else if ($contract_cut_order_type == 6) {//6-1周
            $contract_cut_order_week_hour = ContractConfig::get_value('contract_cut_order_week_hour', '5_16');//合约时时订单类型为1周时从星期几的几点开始新周期
            list($week, $hour) = explode('_', $contract_cut_order_week_hour);
            $today = date('Y-m-d', $now);
            $today_week = date('w', $now);
            if ($today_week == 0) $today_week += 7;
            $diff = $week - $today_week;
            if ($diff < 0) $diff += 7;
            $week_time = strtotime($today) + $diff * 86400 + $hour * 3600;
            if ($now >= $week_time) {//大于等于星期5的16点属于下周这期
                $start_time = $week_time;
                $end_time = $start_time + 86400 * 7;
            }
            else {//小于属于这周这期
                $end_time = $week_time;
                $start_time = $end_time - 86400 * 7;
            }
        } else if ($contract_cut_order_type == 7) {//7-1月
            $contract_cut_order_mon_hour = ContractConfig::get_value('contract_cut_order_mon_hour', '1_16');//合约时时订单类型为1月时从哪天的几点开始新周期
            list($day, $hour) = explode('_', $contract_cut_order_mon_hour);
            $today_mon = date('m', $now);
            $mon_time = strtotime(date('Y-', $now).$today_mon.'-'.sprintf("%02d", $day).' '.$hour.':00:00');
            if ($now >= $mon_time) {//大于等于01号的16点属于这个月这期
                $start_time = $mon_time;
                $end_time = strtotime('+1 month', $start_time);
            }
            else {//小于属于上个月这期
                $end_time = $mon_time;
                $start_time = strtotime('-1 month', $end_time);
            }
        }
        return [$start_time, $end_time];
    }
}