<?php


namespace app\h5\controller;


use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\DcPrice;
use app\common\model\HongbaoNodeCurrencyUser;
use app\common\model\ResonanceLog;
use think\Db;
use think\Exception;
use think\Request;

class Node extends Base
{

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        return $this->output_new(['code' => ERROR10, 'message' => lang('lan_close')]);
    }

    public function index(Request $request)
    {
        $nodeInfo = HongbaoNodeCurrencyUser::node_info($this->member_id);

        return $this->output_new($nodeInfo);
    }


    /*public function convertInfo()
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('system_error_please_try_again_later'),
            'result' => null
        ];
        $nodeInfo = HongbaoNodeCurrencyUser::node_info($this->member_id);
        $nodeInfo = $nodeInfo['result'];
        if (!$nodeInfo) {
            return $this->output_new($r);
        }
        $maxNumber = $nodeInfo['node_currency_user']['echange_num_max'];
        $usedNumber = $this->getUsedNumber($this->member_id);
        $surplusNumber = $maxNumber - $usedNumber;

        $convertCurrencyId = Config::get_value('price_line_currency');
        $currency = Currency::where('currency_id', $convertCurrencyId)->field('currency_id, currency_name')->find();

        // 兑换比例
        $currencyPrice = CurrencyPriceTemp::get_price_currency_id($nodeInfo['node_currency_user']['currency_id'], $this->exchange_rate_type);
        $toCurrencyPrice = DcPrice::getPrice(todayBeginTimestamp(), $this->exchange_rate_type);
        $radio = $currencyPrice / $toCurrencyPrice;
        $fee = ((double)Config::get_value('dc_convert_fee_ratio') * 0.01);
        $minLimit = Config::get_value('dc_convert_min_num');
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'max_number' => $maxNumber,
            'used_number' => $usedNumber,
            'surplus_number' => $surplusNumber,
            'currency_price' => $currencyPrice,
            'to_currency_info' => $currency,
            'to_currency_price' => $toCurrencyPrice,
            'to_currency_unit' => $this->exchange_rate_type,
            'radio' => keepPoint($radio, 6),
            'fee' => $fee,
            'min_limit' => $minLimit
        ];
        return $this->output_new($r);
    }*/


    /*public function priceLine(Request $request)
    {
        $page = $request->post('page', 1);
        $rows = $request->post('rows', 15);

        $data = DcPrice::field('id, currency_id, price, FROM_UNIXTIME(add_time, "%Y-%m-%d") as day')->page($page, $rows)->order('add_time', 'DESC')->select();
        $todayTimestamp = todayBeginTimestamp();
        $yesterdayTimestamp = $todayTimestamp - 86400;
        $todayPrice = DcPrice::getPrice($todayTimestamp, $this->exchange_rate_type);
        $yesterdayPrice = DcPrice::getPrice($yesterdayTimestamp, $this->exchange_rate_type);


        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => [
                'list' => $data,
                'today_price' => $todayPrice,
                'yesterday_price' => $yesterdayPrice,
                'unit' => $this->exchange_rate_type,
            ]
        ]);
    }*/

    /*public function convert(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $toNumber = $request->post('to_number', 0);
        if (!$toNumber or $toNumber < 0) {
            return $this->output_new($r);
        }
        // 兑换比例
        $nodeInfo = HongbaoNodeCurrencyUser::node_info($this->member_id);
        $nodeInfo = $nodeInfo['result'];
        if (!$nodeInfo) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($r);
        }

        $maxNumber = $nodeInfo['node_currency_user']['echange_num_max'];
        $usedNumber = $this->getUsedNumber($this->member_id);
        $surplusNumber = $maxNumber - $usedNumber;

        if ($toNumber > $surplusNumber) {
            $r['message'] = lang('insufficient_convert_num');
            return $this->output_new($r);
        }

        $minLimit = Config::get_value('dc_convert_min_num');
        if ($toNumber < $minLimit) {
            $r['message'] = lang('flop_min_num', ['num' => $minLimit]);
            return $this->output_new($r);
        }

        // 余额判断
        $radio = $this->getRadio($nodeInfo['node_currency_user']['currency_id'], $this->exchange_rate_type);
        $fee = ((double)Config::get_value('dc_convert_fee_ratio') * 0.01);
        $number = keepPoint($toNumber / $radio, 2); // XRP+数量
        $feeNumber = $toNumber * $fee; // 手续费数量
        $totalNumber = $number + $feeNumber;
        if ($nodeInfo['node_currency_user']['num'] < $totalNumber) {
            $r['message'] = lang('insufficient_balance');
            return $this->output_new($r);
        }

        // 添加共振记录
        try {
            Db::startTrans();
            $toCurrencyId = Config::get_value('price_line_currency');
            $resonanceLog = ResonanceLog::create([
                'member_id' => $this->member_id,
                'currency_id' => $nodeInfo['node_currency_user']['currency_id'],
                'to_currency_id' => $toCurrencyId,
                'number' => $number,
                'to_number' => $toNumber,
                'fee' => $fee,
                'radio' => $radio,
                'add_time' => time()
            ]);

            if (empty($resonanceLog)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 账本 & 资产变动
            $currencyId = $nodeInfo['node_currency_user']['currency_id'];
            $accountBook = AccountBook::add_accountbook($this->member_id, $currencyId, AccountBookType::SUB_FOR_RESONANCE,
                'sub_for_resonance', 'out', $number, $resonanceLog['id'], $fee);

            if (empty($accountBook)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currencyId);
            $currencyUser['num'] -= $totalNumber;
            if (!$currencyUser->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $toAccountBook = AccountBook::add_accountbook($this->member_id, $toCurrencyId, AccountBookType::ADD_FOR_RESONANCE,
                'add_for_resonance', 'in', $toNumber, $resonanceLog['id']);
            if (empty($toAccountBook)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $toCurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $toCurrencyId);
            $toCurrencyUser['num'] += $toNumber;
            if (!$toCurrencyUser->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }
            // End
            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            return $this->output_new($r);
        } catch (\Exception $e) {
            Db::rollback();
            $r['code'] = ERROR3;
            $r['message'] = $e->getMessage();
            return $this->output_new($r);
        }
    }*/

   /* private function getUsedNumber($member_id)
    {
        return ResonanceLog::where('member_id', $member_id)->sum('to_number');
    }*/

    private function getRadio($currencyId, $unit)
    {
        $currencyPrice = CurrencyPriceTemp::get_price_currency_id($currencyId, $unit);
        $toCurrencyPrice = DcPrice::getPrice(todayBeginTimestamp(), $unit);

        return keepPoint($currencyPrice / $toCurrencyPrice, 6);
    }
}