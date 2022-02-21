<?php


namespace app\admin\controller;


use app\common\model\ConvertConfig;
use app\common\model\ConvertKeepConfig;
use app\common\model\Currency;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;

/**
 * Class Convert
 * @package app\admin\controller
 */
class Convert extends Admin
{

    /**
     * @var ConvertConfig
     */
    protected $convertConfig;


    /**
     * Convert constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->convertConfig = new ConvertConfig();
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function index(Request $request)
    {
        $list = $this->convertConfig->with(['currency', 'to_currency'])->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $this->convertConfig->count('id');
        return $this->fetch(null, ['list' => $list, 'count' => $count, 'page' => $page]);
    }

    /**
     * @param Request $request
     * @return mixed|Json
     * @throws DbException
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $currencyId = $request->post('currency_id');
            $toCurrencyId = $request->post('to_currency_id');
            $dayMaxNum = $request->post('day_max_num');

            if (!$currencyId or !$toCurrencyId or !$dayMaxNum) {
                $this->error("参数错误!");
                die();
            }

            if ($request->post('currency_id') == $request->post('to_currency_id')) {
                $this->error('币种和兑换币种冲突!');
                die();
            }

            /*$isRepeat = $this->convertConfig->where('currency_id', $currencyId)->find();
            if ($isRepeat) {
                return successJson('该币种已经有一个兑换配置, 请勿重复添加!', null, ERROR1);
            }*/
            $r = $this->convertConfig->create([
                'currency_id' => $currencyId,
                'to_currency_id' => $toCurrencyId,
                'fee' => $request->post('fee', 0),
                'day_max_num' => $dayMaxNum,
                'create_time' => time()
            ]);

            // Currency::where('currency_id', $currencyId)->update(['currency_convert_switch' => 1]);
            if (empty($r)) {
                $this->error("系统错误, 请稍后再试!");
                die();
            }
            $this->success('添加成功!');
        }
        $currency_list = Currency::where('is_line', 1)->select();
        return $this->fetch(null, ['currency_list' => $currency_list]);
    }

    /**
     * @param Request $request
     * @return mixed|Json
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function edit(Request $request)
    {
        if ($request->post()) {
            if ($request->post('currency_id') == $request->post('to_currency_id')) {
                $this->error('币种和兑换币种冲突!');
                die();
            }
            $r = $this->convertConfig->update($request->post());
            if ($r === false) {
                $this->error('系统错误, 请稍后再试!');
                die();
            }
            $this->success('修改成功!');
        }
        $id = $request->param('id');
        $data = $this->convertConfig->where('id', $id)->find();
        $currency_list = Currency::where('is_line', 1)->select();

        return $this->fetch(null, ['data' => $data, 'currency_list' => $currency_list]);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function delete(Request $request)
    {
        $id = $request->param('id');
        // $currencyId = $request->post('currency_id');
        if (!$id/* or !$currencyId*/) {
            $this->error('参数错误');
            die();
        }

//        WalletCurrency::where('currency_id', $currencyId)->update(['currency_convert_switch' => 2]);
        $r = $this->convertConfig->where('id', $id)->delete();

        if (!$r) {
            $this->error('系统错误请稍后再试');
            die();
        }
        $this->success('删除成功!');
    }


    //兑换赠送金
    public function keep_index(Request $request)
    {
        $list = ConvertKeepConfig::with(['currency', 'to_currency'])->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = ConvertKeepConfig::count('id');
        return $this->fetch(null, ['list' => $list, 'count' => $count, 'page' => $page]);
    }

    /**
     * @param Request $request
     * @return mixed|Json
     * @throws DbException
     */
    public function keep_add(Request $request)
    {
        if ($request->isPost()) {
            $currencyId = $request->post('currency_id');
            $toCurrencyId = $request->post('to_currency_id');
            $dayMaxNum = $request->post('day_max_num');
            $ratio  = $request->post('ratio');

            if ($request->post('currency_id') == $request->post('to_currency_id')) {
                $this->error('币种和兑换币种冲突!');
                die();
            }

            $r = ConvertKeepConfig::create([
                'currency_id' => $currencyId,
                'to_currency_id' => $toCurrencyId,
                'fee' => $request->post('fee', 0),
                'day_max_num' => $dayMaxNum,
                'ratio' => $ratio,
                'create_time' => time()
            ]);

            // Currency::where('currency_id', $currencyId)->update(['currency_convert_switch' => 1]);
            if (empty($r)) {
                $this->error("系统错误, 请稍后再试!");
                die();
            }
            $this->success('添加成功!');
        }
        $currency_list = Currency::where('is_line', 1)->select();
        return $this->fetch(null, ['currency_list' => $currency_list]);
    }

    /**
     * @param Request $request
     * @return mixed|Json
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function keep_edit(Request $request)
    {
        if ($request->post()) {
            if ($request->post('currency_id') == $request->post('to_currency_id')) {
                $this->error('币种和兑换币种冲突!');
                die();
            }
            $r = ConvertKeepConfig::update($request->post());
            if ($r === false) {
                $this->error('系统错误, 请稍后再试!');
                die();
            }
            $this->success('修改成功!');
        }
        $id = $request->param('id');
        $data = ConvertKeepConfig::where('id', $id)->find();
        $currency_list = Currency::where('is_line', 1)->select();

        return $this->fetch(null, ['data' => $data, 'currency_list' => $currency_list]);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function keep_delete(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            $this->error('参数错误');
            die();
        }

        $r = ConvertKeepConfig::where('id', $id)->delete();

        if (!$r) {
            $this->error('系统错误请稍后再试');
            die();
        }
        $this->success('删除成功!');
    }
}
