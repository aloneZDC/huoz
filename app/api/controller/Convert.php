<?php

namespace app\api\controller;


use app\common\model\AccountBook as AccountBookModel;
use app\common\model\ConvertConfig;
use app\common\model\ConvertKeepConfig;
use app\common\model\ConvertKeepLog;
use app\common\model\ConvertLog;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\MtkCurrencyPrice;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;

class Convert extends Base
{
    /**
     * @var ConvertConfig
     */
    protected $convertConfig;

    /**
     * @var ConvertLog
     */
    protected $convertLog;

    /**
     * Convert constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        $this->convertConfig = new ConvertConfig();
        $this->convertLog = new ConvertLog();
        parent::__construct($request);
    }

    /**
     * 兑换配置
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function indexV1(Request $request)
    {
        // $currencyId = $request->post('currency_id');
        /*if (!$currencyId) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('parameter_error')]);
        }*/

        $currencyId = $request->post('currency_id', null);
        $where = [];
        if (!is_null($currencyId)) {
            $where['currency_id'] = $currencyId;
        }

        // day_max_num,
        $configs = $this->convertConfig->where($where)->field('id, currency_id, fee')->with(['currency', 'to_config'])->group('currency_id')->select();
        if (empty($configs)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('unopened_exchange')]);
        }

        foreach ($configs as $key => &$config) {
            /**
             * @var ConvertConfig $config
             */
            $config['number'] = (CurrencyUser::getCurrencyUser($this->member_id, $config['currency_id']))['num'];

            foreach ($config['to_config'] as $k => $item) {
                /**
                 * @var ConvertConfig $item
                 */
                $toDayNum = $this->convertLog->where([
                    'user_id' => $this->member_id,
                    'cc_id' => $item['id'],
                ])->whereTime('create_time', 'today')->sum('to_number'); // 今日兑换数量
                $item['day_max_num'] -= $toDayNum;

                try {
                    $item['ratio'] = $item->getRatio();
                    unset($item['currency_price_type']);
                    unset($item['currency_bb_id']);
                    unset($item['to_currency_bb_id']);
                } catch (\Exception $exception) {
                    return $this->output_new(['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null]);
                }
            }
        }

        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $configs]);
    }

    /**
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(Request $request)
    {
//        return $this->output_new(['code' => ERROR1, 'msg' => 'deprecated', 'result' => null]);
        $currencyId = $request->post('currency_id',null);
//        if (!$currencyId) {
//            return $this->output_new(['code' => ERROR1, 'message' => lang('parameter_error')]);
//        }
        $where = [];
        if (!is_null($currencyId)) {
            $where['currency_id'] = $currencyId;
        }

        $configs = $this->convertConfig->field('id, currency_id, to_currency_id, day_max_num')
            ->with(['currency', 'toCurrency'])
//            ->where('currency_id', $currencyId)
            ->where($where)
            ->select();
        if (empty($configs)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('unopened_exchange')]);
        }
        foreach ($configs as $key => &$config) {
            $toDayNum = $this->convertLog->where([
                'user_id' => $this->member_id,
                'cc_id' => $config['id'],
            ])->whereTime('create_time', 'today')->sum('number'); // 今日兑换数量
            $config['day_max_num'] -= $toDayNum;
//            $ratio = $this->getRatio($config['currency_id'], $config['to_currency_id']);
//            $config['ratio'] = MtkCurrencyPrice::yesterday_price(93);

            if ($config['currency_id'] == 5) {
                $currencyPrice = 1;
                $toCurrencyPrice = MtkCurrencyPrice::yesterday_price(93);
                $config['ratio'] =  keepPoint($currencyPrice / $toCurrencyPrice, 4);
            }
            else if ($config['to_currency_id'] == 5) {
                $currencyPrice = MtkCurrencyPrice::yesterday_price(93);
                $toCurrencyPrice = 1;
                $config['ratio'] =  keepPoint($currencyPrice / $toCurrencyPrice, 4);
            }

            $config['number'] = (CurrencyUser::getCurrencyUser($this->member_id, $config['currency_id']))['num'];
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $configs]);
    }

    /*public function indexV2()
    {
        $configs = $this->convertConfig->field('id, currency_id, day_max_num, fee')->with(['currency', 'to_config'])->group('currency_id')->select();
        if (empty($configs)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('unopened_exchange')]);
        }

        foreach ($configs as $key => &$config) {
            $toDayNum = $this->convertLog->where([
                'user_id' => $this->member_id,
                'cc_id' => $config['id'],
            ])->whereTime('create_time', 'today')->sum('number'); // 今日兑换数量
            $config['day_max_num'] -= $toDayNum;
            $config['number'] = (CurrencyUser::getCurrencyUser($this->member_id, $config['currency_id']))['num'];

            foreach ($config['to_config'] as $k => $item) {
                $ratio = $this->getRatio($config['currency_id'], $item['to_currency_id']);
                $item['ratio'] = $ratio;
            }
        }
        return $this->output_new($configs);
    }*/

    /**
     * 提交兑换
     * @param Request $request
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function store(Request $request)
    {
        $number = keepPoint($request->post('number'));
        $id = $request->post('id');

        if (!$number or !$id) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null]);
        }
        if ($number <= 0) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('token_price_error'), 'result' => null]);
        }
        $config = $this->convertConfig->where('id', $id)->find();
        if (empty($config)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('unopened_exchange')]);
        }

        $ratio = $config->getRatio();
        $toNumber = keepPoint($number * $ratio, 6);

        $toDayNum = $this->convertLog->where([
            'user_id' => $this->member_id,
            'cc_id' => $config['id'],
        ])->whereTime('create_time', 'today')->sum('number'); // 今日兑换数量
        $config['day_max_num'] -= $toDayNum - $number;

        if ($config['day_max_num'] <= 0) {
            return $this->output_new(['code'=> ERROR2, 'message' => lang('maximum_convertibility_today'), 'result' => null]);
        }

        try {
            Db::startTrans();
            $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $config['currency_id']);
            $fee = $toNumber * ((double) $config['fee'] * 0.01);
            $actualNumber = $toNumber - $fee;
            if ($usersCurrency['num'] < $number) {
                throw new Exception(lang('insufficient_balance'));
            }

            // 纪录
            $convertLog = ConvertLog::create([
                'currency_id' => $config['currency_id'],
                'to_currency_id' => $config['to_currency_id'],
                'user_id' => $this->member_id,
                'cc_id' => $config['id'],
                'number' => $number,
                'to_number' => $actualNumber,
                'status' => 1,
                'fee' => $config['fee'],
                'create_time' => time(),
            ]);
            $res = AccountBookModel::add_accountbook($this->member_id, $config['currency_id'], 38, 'convert', 'out', $number, $convertLog['id'], $fee, $config['currency_id']);
            // $feeRes = AccountBookModel::add_accountbook($this->member_id, $config['currency_id'], 39, 'convert_fee', 'out', $fee, $convertLog['id']);
            $toRes = AccountBookModel::add_accountbook($this->member_id, $config['to_currency_id'], 38, 'convert', 'in', $actualNumber, $convertLog['id']);
            if (!$res or !$toRes/* or !$feeRes*/) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 操作用户币种余额
            $usersCurrency->num -= $number;
            if (!$usersCurrency->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $toUsersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $config['to_currency_id']);
            $toUsersCurrency->num += $actualNumber;
            if (!$toUsersCurrency->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            Db::commit();
            return $this->output_new(['code' => SUCCESS, 'message' => lang('success_operation'), 'result' => null]);
        } catch (Exception $e) {
            Db::rollback();

            return $this->output_new(['code' => ERROR9, 'message' => $e->getMessage(), 'result' => null]);
        }
    }


    /**
     * 获取两个币种的兑换比列
     * @deprecated
     * @see ConvertConfig::getRatio()
     * @param int $currencyId
     * @param int $toCurrencyId
     * @return string
     */
    public function getRatio($currencyId, $toCurrencyId)
    {
        $currencyPrice = CurrencyPriceTemp::get_price_currency_id($currencyId, "CNY");
        $toCurrencyPrice = CurrencyPriceTemp::get_price_currency_id($toCurrencyId, "CNY");

        return keepPoint($currencyPrice / $toCurrencyPrice, 6);
    }


    //兑换到赠送金配置 临时功能
    public function convert_keep_config() {
        $currencyId = intval(input('post.currency_id'));
        $res = ConvertKeepConfig::getConvertConfig($this->member_id,$currencyId);
        return $this->output_new($res);
    }

    //兑换到赠送金 临时功能
    public function convert_keep_store() {
        $id = intval(input('post.id'));
        $number = input('post.number');
        $res = ConvertKeepLog::convert($this->member_id,$id,$number);
        return $this->output_new($res);
    }
}
