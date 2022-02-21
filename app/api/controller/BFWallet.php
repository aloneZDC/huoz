<?php

namespace app\api\controller;

use app\common\model\BfwCurrencyTransfer;
use app\common\model\BfwCurrencyConfig;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\FilMining;
use app\common\model\GroupMiningForzen;
use app\common\model\GroupMiningLog;
use app\common\model\GroupMiningUser;
use app\common\model\Kline;
use app\common\model\Trade;
use think\Db;
use think\Log;

class BFWallet extends Base
{
    /**
     * 钱包轮播
     */
    public function wallet_carousel()
    {
        $abfPrice = CurrencyPriceTemp::BBFirst_currency_price(Currency::ABF_BB_ID);
        $activation_fee = Config::get_value('bfw_activation', 0);

        // 今日个人矿产
        $today_assets = GroupMiningLog::where(['user_id' => $this->member_id, 'result' => 1, 'date' => date('Y-m-d')])->sum('forzen_num');

        // 全网累计矿产
        $all_assets = GroupMiningLog::where(['result' => 1])->sum('forzen_num');

        $data = [
            'today_assets' => $today_assets, // 今日个人矿产
            'all_assets' => $all_assets,   // 全网累计矿产
            'price_assets' => $abfPrice,   // 今日价格
            'activation_fee' => $activation_fee   // 激活手续费
        ];
        $this->output(SUCCESS, lang('data_success'), $data);
    }

    /**
     * 帳戶頁面
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function account_index()
    {
        $data = [];
        $rate_type = 'USD'; // 價格類型

        $Currency_type = Currency::where(['is_line' => 1, 'is_app_currency' => 1, 'status' => 1])->group("account_type")
            ->order('currency_id', 'asc')->field('account_type')->select();
        foreach ($Currency_type as $value) {
//            if ($value['account_type'] == 'wallet') continue;

            $data1 = [
                'account_type' => $value['account_type'],
                'account_name' => lang('bfw_' . $value['account_type']) /*. '（' . ($rate_type == 'USD' ? '$' : '￥') . '）'*/,
                'account_count' => 0, // 总USDT
                'account_count_cny' => 0, // 总人民币
                'account_list' => [],
            ];

            $Currency = Currency::where(['is_line' => 1, 'account_type' => $value['account_type'], 'is_app_currency' => 1, 'status' => 1])
                ->field(['currency_id', 'currency_name', 'currency_logo', 'currency_mark', 'currency_transfer_switch',
                    'take_switch', 'recharge_switch', 'is_lock', 'exchange_switch', 'withdraw_switch',
                    'mutual_switch', 'subscribe_switch', 'account_type',
                    'trade_currency_id', 'is_trade_currency', 'recharge_switch'
                ])->order("sort asc")->select();
            $account_count = 0;
            $account_count_cny = 0;
            foreach ($Currency as &$val) {
                $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $val['currency_id']);
//                $CurrencyPrice = $this->getPrice($val['currency_id'], $val['account_type']);
//                $val['price'] = $CurrencyPrice;
                $val['num'] = $currencyUser['num'];
                $val['lock_num'] = $currencyUser['lock_num']; // 推荐冻结
                $val['release_lock'] = $currencyUser['release_lock']; // 产出冻结
                $val['forzen_num'] = $currencyUser['forzen_num']; // 其他冻结

                if ($val['currency_mark'] == 'ABFPT') {
                    //$userInfo = GroupMiningUser::getUserInfo($this->member_id, true);
                    //$val['lock_num'] = $userInfo['total_forzen_num'];
                    $lockNum = GroupMiningForzen::where('user_id', $this->member_id)->sum('forzen_num') ?: 0;
                    $val['lock_num'] = $lockNum;
                }
                $val['is_tibi_status'] = $val['recharge_switch'] == 1 ? 1 : 0;

                $currency_id = $val['currency_id'] == 85 ? 81 : $val['currency_id'];
                $trade_currency_id = explode(',', $val['trade_currency_id']);
                if (isset($val['is_trade_currency']) == 1 && !empty($trade_currency_id[0])) {
                    $val['cny_price'] = Trade::getCurrencyRealMoney($currency_id, $trade_currency_id[0], 'CNY');
                    $val['usdt_price'] = Trade::getCurrencyRealMoney($currency_id, $trade_currency_id[0], 'USD');
                } else {
                    $val['cny_price'] = CurrencyPriceTemp::get_price_currency_id($currency_id, 'CNY');
                    $val['usdt_price'] = CurrencyPriceTemp::get_price_currency_id($currency_id, 'USD');
                }
                $allNum = $currencyUser['num'] + $currencyUser['lock_num'] + $currencyUser['release_lock'] + $currencyUser['forzen_num'];
                $val['all_num'] = keepPoint($allNum, 6);
                $val['cny_num'] = bcmul($val['all_num'], $val['cny_price'], 2);
                $val['usdt_num'] = bcmul($val['all_num'], $val['usdt_price'], 6);

//                $account_count += keepPoint(($val['num'] + $val['lock_num']) * $CurrencyPrice, 6);
//                $account_count += bcmul($val['num'], $CurrencyPrice, 6);
                $account_count += $val['usdt_num'];
                $account_count_cny += $val['cny_num'];
                //显示预约排队按钮
                $val['is_subscribe'] = 0;//0不显示  1显示
                if ($val['currency_id'] == 5 || $val['currency_id'] == 103) {
                    $val['is_subscribe'] = 1;
                }
            }

            if ($Currency) {
                $data1['account_list'] = $Currency;
                $data1['account_count'] = keepPoint($account_count, 6);
                $data1['account_count_cny'] = keepPoint($account_count_cny, 2);
            }
            $data[] = $data1;
        }
        $this->output(SUCCESS, lang('data_success'), $data);
    }

    /**
     * 获取价格
     * @param $currency_id
     * @param $account_type
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getPrice($currency_id, $account_type)
    {
        $currencyPrice = CurrencyPriceTemp::where(['cpt_currency_id' => $currency_id])->find();
        if ($account_type == 'currency'
            && $currency_id != Currency::USDT_ID
            && $currencyPrice['cpt_same_currency_id'] != Currency::USDT_ID) {
            // 如果是币币交易直接获取最新价格
            $autoTrade = Db::name('currency_autotrade')->where(['currency_id' => $currency_id, 'trade_currency_id' => Currency::USDT_BB_ID, 'is_autotrade' => 1, 'kline_huobi' => 1])->find();
            if ($autoTrade) {
                $kline = Kline::where(['type' => 60, 'currency_id' => $currency_id, 'currency_trade_id' => Currency::USDT_BB_ID])->order('add_time', 'DESC')->find();
                if ($kline) return $kline['close_price'];
            }
            return \app\common\model\Trade::getLastTradePrice($currency_id, Currency::USDT_BB_ID);
        } else {
            // 如果有币币账户
            if ($currencyPrice && $currencyPrice['cpt_same_currency_id'] > 0
                && $currencyPrice['cpt_same_currency_id'] != Currency::USDT_ID) {
                $autoTrade = Db::name('currency_autotrade')->where(['currency_id' => $currencyPrice['cpt_same_currency_id'], 'trade_currency_id' => Currency::USDT_BB_ID, 'is_autotrade' => 1, 'kline_huobi' => 1])->find();
                if ($autoTrade) {
                    $kline = Kline::where(['type' => 60, 'currency_id' => $currencyPrice['cpt_same_currency_id'], 'currency_trade_id' => Currency::USDT_BB_ID])->order('add_time', 'DESC')->find();
                    if ($kline) return $kline['close_price'];
                }
                return \app\common\model\Trade::getLastTradePrice($currencyPrice['cpt_same_currency_id'], Currency::USDT_BB_ID);
            }
        }
        return $currencyPrice ? $currencyPrice['cpt_usd_price'] : 0;
    }

    /**
     * 資產帳戶--劃轉頁面
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function account_transfer()
    {
        $cc_type = input('cc_type', '');
        if (empty($cc_type)) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        $data = [];
        $CurrencyConfig = BfwCurrencyConfig::with(['currencytype'])->where(['cc_type' => $cc_type, 'status' => 0])
            ->group('cu_type')->order(['type_sort' => 'ASC', 'cc_id' => 'ASC'])->limit(0, 1000)->select();
        foreach ($CurrencyConfig as $key => $value) {
            $data[$key]['currency_type'] = $value['cu_type'];
            $data[$key]['currency_name'] = $value['currencytype']['currency_mark'];

            $config_data = [];
            $Config = BfwCurrencyConfig::with(['currency'])->where(['cu_type' => $value['cu_type'], 'cc_type' => $cc_type, 'status' => 0])
                ->group('currency_id')->order(['sort' => 'ASC', 'cc_id' => 'ASC'])->limit(0, 1000)->select();
            foreach ($Config as $kk => $val) {
//                if('wallet_transfer' == $cc_type && $val['currency']['account_type'] != 'wallet') continue; // 钱包划转
//                if ('bfw_qb_hz' == $cc_type && $val['currency']['account_type'] == 'wallet') continue; // 劃轉去掉錢包帳戶
                $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $val['currency_id']);
                $config_data[] = [
                    'currency_id' => $val['currency_id'],
                    'account_type' => lang('bfw_' . $val['currency']['account_type']),
//                    'min' => $val['min'],
//                    'max' => $val['max'],
//                    'fee' => $val['is_handle'] ? $val['currency']['tcoin_fee'] : $val['fee'],
                    'is_handle' => $val['is_handle'],
                    'balance' => $currencyUser['num']
                ];
            }
            $data[$key]['currency_list'] = $config_data;
        }

        $this->output(SUCCESS, lang('data_success'), $data);
    }

    /**
     * 劃轉第二項
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function account_to_transfer()
    {
        $currency_id = input('currency_id', 0); // 帳戶ID
        $currency_type = input('currency_type', 0); // 幣種ID
        $cc_type = input('cc_type', 0); // 類型
        if (empty($currency_id) || empty($currency_type) || empty($cc_type)) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        $Config = BfwCurrencyConfig::with(['tocurrency'])
            ->where(['cu_type' => $currency_type, 'cc_type' => $cc_type, 'status' => 0, 'currency_id' => $currency_id])
            ->order('to_sort', 'ASC')->limit(0, 1000)->select();

        $data = [];
        foreach ($Config as $value) {
            $to_transfer = [
                'to_currency_id' => $value['to_currency_id'],
                'to_account_type' => lang('bfw_' . $value['tocurrency']['account_type']),
                'min' => $value['min'],
                'max' => $value['max'],
//                'fee' => $value['is_handle']  ? $value['currency']['tcoin_fee'] : $value['fee'],
                'fee' => $value['fee'],
            ];
            if ($value['is_handle']) $to_transfer['recharge_address'] = $value['tocurrency']['recharge_address'];
            $data[] = $to_transfer;
        }
        $this->output(SUCCESS, lang('data_success'), $data);
    }

    /**
     * 获取配置
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function transfer_config()
    {
        $currency_id = input('currency_id', '');
        $cc_type = input('cc_type', '');
        if (empty($cc_type) || empty($currency_id)) {
            $this->output(ERROR1, lang('parameter_error'));
        }
        $currency_config = BfwCurrencyConfig::alias('a')
            ->join('currency b', 'b.currency_id=a.currency_id', 'left')
            ->where(['a.cc_type' => $cc_type, 'a.currency_id' => $currency_id, 'a.status' => 0])
            ->field('a.cc_id,a.currency_id,a.to_currency_id,a.min,a.max,a.fee,b.currency_name')
            ->find();

        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_config['currency_id']);
        $currency_config['num'] = $CurrencyUser['num'];

        $this->output(SUCCESS, lang('data_success'), $currency_config);
    }

    /**
     * 劃轉/提現頁面
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function transfer()
    {
        // 判斷參數
        $currency_id = input('post.currency_id/d', 0);
        $to_currency_id = input('post.to_currency_id/d', 0);

//        $cc_id = input('post.cc_id/d', 0);
        $number = input('post.number/f', 0);
        $type = input('post.type/s', '');
        if (empty($currency_id) || empty($to_currency_id) || empty($number) || empty($type)) {
//        if (empty($number) || empty($cc_id)) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        // 配寘
        $where = ['currency_id' => $currency_id, 'to_currency_id' => $to_currency_id, 'cc_type' => $type, 'is_handle' => 0];
//        $where = ['cc_id' => $cc_id];
        $res = BfwCurrencyConfig::validateConfig($where, $number);
        if ($res['code'] == ERROR1) {
            return $this->output_new($res);
        }
        $CurrencyAccount = $res['result'];

        // 計算手續費
        $fee = 0;
        if ($CurrencyAccount->fee > 0) {
            $fee = keepPoint($number * $CurrencyAccount->fee / 100, 6);
        }

        // 判斷金額
        $deduct = keepPoint($number + $fee, 6);
        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $CurrencyAccount->currency_id);
        if (bccomp($CurrencyUser->num, $deduct, 6) < 0) {
            $this->output(ERROR1, lang('insufficient_balance'));
        }

        // 基礎數據
        $data = [
            'member_id' => $this->member_id,
            'currency_id' => $CurrencyAccount->currency_id,
            'to_currency_id' => $CurrencyAccount->to_currency_id,
            'ct_type' => $CurrencyAccount->cc_type,
            'number' => $number,
            'deduct' => $deduct,
            'ct_fee' => $fee,
            'add_time' => time(),
        ];

        $res = BfwCurrencyTransfer::transfer($data, $CurrencyAccount);
        return $this->output_new($res);
    }

    /**
     * 帳本記錄
     * @param currency_id 幣種ID
     * @param type 類型 0默認 1收入 2支出
     * @param real_type 帳本類型
     */
    public function account_book()
    {
        $currency_id = input('currency_id', 0, 'intval');
        $type = input('type', 0, 'intval');

        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('row', 10, 'intval,filter_page');

        $real_type = input('real_type', '');
        $where = [];
        switch ($real_type) {
            case 'transfer': // 划转
//                $where['type'] = ['in', [3101, 3103]];
                $where['type'] = 5201;
                break;
            case 'interchange': // 互转account_index
                $where['type'] = 5202;
                break;
            case 'reflect': // 提现

                break;
        }

        if (empty($real_type) && ($currency_id == Currency::TRC20_ID || $currency_id == Currency::ERC20_ID)) {
            $currency_id = Currency::USDT_ID;
            $where['type'] = 6; //默认提币
        }

        $count = false;
        $list = model('AccountBook')->getLog($this->member_id, $currency_id, $type, $page, $page_size, $this->lang, $count, $where);
        $this->output(SUCCESS, lang('data_success'), $list);
    }

    // 钱包头部信息
    public function top_info()
    {
        $currency_id = input('currency_id', 0, 'intval');
        $res = \app\common\model\CurrencyUser::top_info($this->member_id, $currency_id);
        $this->output_new($res);
    }

    // 资产明细
    public function book_list()
    {
        $currency_id = input('currency_id', 0, 'intval');
        $type = input('type', 0, 'intval');
        $page = input('page', 0, 'intval');
        $res = \app\common\model\AccountBook::book_list($this->member_id, $currency_id, $type, $page);
        $this->output_new($res);
    }
}
