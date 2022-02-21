<?php
//传统矿机
namespace app\api\controller;

use app\common\model\CommonMiningIncome;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use \app\common\model\CommonMiningPay;
use \app\common\model\CommonMiningProduct;
use think\Db;

class CommonMining extends Base
{
    protected $public_action = ['index', 'product_list', 'product_info', 'calculator', 'calculator_index'];

    // 区块信息  交易所信息
    public function index()
    {
        // 获取区块信息（FIL）
        $fil_block_info = cache('fil_block_info');
        if (!empty($fil_block_info)) {
            if (isset($fil_block_info['fields']['fil_circulation'])) unset($fil_block_info['fields']['fil_circulation']);
            if (isset($fil_block_info['fields']['fil_pledge'])) unset($fil_block_info['fields']['fil_pledge']);

            foreach ($fil_block_info['fields'] as &$item) {
                $item['name'] = lang($item['name']);
            }
            $fil_block_info['fields'] = array_values($fil_block_info['fields']);

//            $fgas_task = AloneMiningProduct::average_out();
//            $fgas_task_result = [
//                ['name' => '当前扇区质押量', 'value' => $fgas_task['payment_1T'] . ' FIL/TiB', 'number' => $fgas_task['payment_1T']],
//                //, 'preGas64' => keepPoint($fgas_task['preGas_1T'] / 2, 6)
//                ['name' => '新增GAS消耗（FIL/TiB）', 'value' => $fgas_task['preGas_1T'], 'number' => $fgas_task['preGas_1T']],
//            ];
//            $fil_block_info['fields'] = array_merge($fil_block_info['fields'], $fgas_task_result);
        } else {
            $fil_block_info = null;
        }

        // 获取区块信息（Chia）
        $chia_block_info = cache('chia_block_info');
        if (!empty($chia_block_info)) {
            foreach ($chia_block_info['fields'] as &$item) {
                $item['name'] = lang($item['name']);
            }
            $chia_block_info['fields'] = array_values($chia_block_info['fields']);
        } else {
            $chia_block_info = null;
        }

        // 获取区块信息（BTC）
        $btc_block_info = cache('btc_block_info');
        if (!empty($btc_block_info)) {
            foreach ($btc_block_info['fields'] as &$item) {
                $item['name'] = lang($item['name']);
            }
            $btc_block_info['fields'] = array_values($btc_block_info['fields']);
        } else {
            $btc_block_info = null;
        }

        // 获取区块信息（ETH）
        $eth_block_info = cache('eth_block_info');
        if (!empty($eth_block_info)) {
            foreach ($eth_block_info['fields'] as &$item) {
                $item['name'] = lang($item['name']);
            }
            $eth_block_info['fields'] = array_values($eth_block_info['fields']);
        } else {
            $eth_block_info = null;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'fil_block_info' => $fil_block_info,
//            'chia_block_info' => $chia_block_info,
//            'btc_block_info' => $btc_block_info,
//            'eth_block_info' => $eth_block_info,
            'exchange_fil' => Db::name('common_mining_currency_price')->where(['mining_currency_id' => 81, 'status' => 1])->field('mining_currency_name,platform,platform_logo,usdt,cny')->select(),
//            'exchange_xch' => Db::name('common_mining_currency_price')->where(['mining_currency_id' => 94, 'status' => 1])->field('mining_currency_name,platform,platform_logo,usdt,cny')->select(),
//            'exchange_btc' => Db::name('common_mining_currency_price')->where(['mining_currency_id' => 1, 'status' => 1])->field('mining_currency_name,platform,platform_logo,usdt,cny')->select(),
//            'exchange_eth' => Db::name('common_mining_currency_price')->where(['mining_currency_id' => 3, 'status' => 1])->field('mining_currency_name,platform,platform_logo,usdt,cny')->select(),
        ];
        $this->output_new($r);
    }

    // 产品列表
    public function product_list()
    {
        $page = input('page', 1, 'intval');
//        $res = CommonMiningProduct::getProductList($this->lang, $page);
        $res = CommonMiningProduct::product_list($page);
        $this->output_new($res);
    }

    // 商品详情 -- 下单时获取
    public function product_info()
    {
        $product_id = input('product_id', 0, 'intval');
//        $res = CommonMiningProduct::getProductInfo($this->member_id, $product_id, $this->lang);
        $res = CommonMiningProduct::product_info($this->member_id, $product_id);
        $this->output_new($res);
    }

    // 入金
    public function product_buy()
    {
        $product_id = input('product_id', 0, 'intval');
        $amount = input('amount', 0, 'floatval');
        $quan_id = input('quan_id', '', 'strval');
        $pay_type = input('pay_type', '', 'intval');
        $pay_id = input('pay_id', '', 'intval');
        $res = CommonMiningPay::product_buy($this->member_id, $product_id, $amount, $pay_type, $pay_id, $quan_id);
//        $res = \app\common\model\CommonMiningPay::buy($this->member_id, $product_id, $amount, $pay_type, $pay_id, $quan_id);
        $this->output_new($res);
    }

    // 我的购买列表
    public function buy_list()
    {
//        $page = intval(input('page'));
//        $res = \app\common\model\CommonMiningPay::getList($this->member_id, $this->lang, $page);
        $page = input('page', 1, 'intval');
        $res = CommonMiningPay::buy_list($this->member_id, $page);
        return $this->output_new($res);
    }

    // 订单列表 头部统计
    public function release_head()
    {
        $res = CommonMiningPay::release_head($this->member_id);
        $this->output_new($res);
    }

    // 产币 统计
    public function release_count()
    {
        $product_id = input('product_id', 0, 'intval');
        $res = CommonMiningPay::release_count($this->member_id, $product_id);
        $this->output_new($res);
    }

    // 团队奖励统计
    public function team_rewards()
    {
        $res = \app\common\model\CommonMiningMember::teamRewards($this->member_id);
        return $this->output_new($res);
    }

    // 释放列表
    public function release_list()
    {
        $page = intval(input('page'));
        $pay_id = intval(input('pay_id'));
        $res = \app\common\model\CommonMiningRelease::getList($this->member_id, $pay_id, $page);
        return $this->output_new($res);
    }

    // 加权分红统计
    public function global_income()
    {
        $res = \app\common\model\CommonMiningMember::globalIncome($this->member_id);
        return $this->output_new($res);
    }

    // 综合明细
    public function complex_detail()
    {
        $res = \app\common\model\CommonMiningMember::complex_detail($this->member_id);
        $this->output_new($res);
    }

    // 我的收益列表
    public function income_list()
    {
        $page = intval(input('page'));
        $type = intval(input('type'));
        if ($type == 1) {
            // 推荐奖
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [1, 2, 3, 5], $page);
        } elseif ($type == 4) {
            // 线性释放明细
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [4,5], $page);
        } elseif ($type == 3) {
            // 推荐奖励锁仓释放
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [5], $page);
        } // 加速明细
        elseif ($type == 6) {
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [6], $page);
        } // 线性释放明细
        elseif ($type == 7) {
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, 7, $page);
        } elseif ($type == 9) {
            // 合伙股东奖励
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [9], $page);
        } elseif ($type == 10) {
            // 合伙股东技术服务费奖励
            $res = \app\common\model\CommonMiningIncome::getList($this->member_id, [10], $page);
        } else {
            $res = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        }
        return $this->output_new($res);
    }

    // 冻结详情
    public function lock_detail()
    {
        $page = intval(input('page'));
        $type = intval(input('type'));
        if ($type == 1) {
            // 推荐锁仓详情
            $res = \app\common\model\CurrencyLockBook::get_list($this->member_id, 'lock_num', '', '', $page);
        } else {
            // 产出释放详情
            $res = \app\common\model\CurrencyLockBook::get_list($this->member_id, 'release_lock', '', '', $page);
        }
        return $this->output_new($res);
    }

    // 矿机 - 锁仓数量
    public function currency_num()
    {
        $res = \app\common\model\CommonMiningIncome::getLockNum($this->member_id);
        return $this->output_new($res);
    }

    //我的团队
    public function my_team()
    {
        $page = intval(input('page'));
        $res = \app\common\model\CommonMiningMember::myTeam($this->member_id, $page);
        return $this->output_new($res);
    }

    // 用户汇总
    public function member_summary()
    {
        $res = \app\common\model\CommonMiningPay::summary($this->member_id);
        return $this->output_new($res);
    }

    // 计算器首页
    public function calculator_index()
    {

        $block_info = cache('fil_block_info');
        if (empty($block_info)) {
            $block_info = [];
        } else {
            foreach ($block_info['fields'] as &$item) {
                $item['name'] = lang($item['name']);
            }
            $block_info['fields'] = array_values($block_info['fields']);
        }
        $block_info['day'] = date('Y-m-d');

        $fil_cny = 0;
        $currency = Currency::where('currency_mark', 'FIL')->field('currency_id,currency_name')->find();
        if (!empty($currency)) {
            $r['result']['currency_name'] = $currency['currency_name'];
            $fil_cny = CurrencyPriceTemp::get_price_currency_id($currency['currency_id']);
        }
        $block_info['fil_price'] = $fil_cny . " USDT";

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $block_info;
        return $this->output_new($r);
    }

    // fil计算器
    public function calculator()
    {
        $tnum = intval(input('tnum'));
        $cny = intval(input('cny'));

        $total_day = 540;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'total_day' => $total_day,
            'total_num' => 0,
            'total_num_cny' => 0,
            'balance_day' => 0,
            'balance_num' => 0,
            'fil_cost' => 0,
            'currency_name' => '',
        ];

        $fil_block_info = cache('fil_block_info');
        if (!empty($fil_block_info)) {
            $currency = Currency::where('currency_mark', 'FIL')->field('currency_id,currency_name')->find();
            if (!empty($currency)) {
                $r['result']['currency_name'] = $currency['currency_name'];
                $fil_cny = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'], 'CNY');
                // 每日产出
                $per_tnum_price = $fil_block_info['fields']['hour24_avg']['number'];
                $day_cny = $per_tnum_price * $fil_cny;

                // 总产出
                $r['result']['total_num'] = keepPoint($per_tnum_price * $total_day, 6);
                $r['result']['total_num_cny'] = keepPoint($r['result']['total_num'] * $fil_cny, 2);

                // 平衡天数
                $r['result']['balance_day'] = intval($cny / $day_cny) + 1;
                $r['result']['balance_num'] = keepPoint($per_tnum_price * $r['result']['balance_day'], 6);

                // 单FIL成本估算
                $r['result']['fil_cost'] = keepPoint($cny / $r['result']['total_num'], 2);
            }
        }
        return $this->output_new($r);
    }
}
