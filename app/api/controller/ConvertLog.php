<?php

namespace app\api\controller;



use app\common\model\ConvertLog as ConvertLogModel;
use app\common\model\CurrencyPriceTemp;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;
use think\response\Json;

class ConvertLog extends Base
{
    /**
     * @var ConvertLogModel
     */
    protected $convertLog;

    /**
     * ConvertLog constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->convertLog = new ConvertLogModel();
    }


    /**
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function index(Request $request)
    {
        $page = $request->post('page', 1);
        $length = $request->post('length', 10);

        $start = ($page - 1) * $length;
        $data = $this->convertLog->with(['currency', 'toCurrency'])->field('id, currency_id, to_currency_id, status, to_number, create_time')->where('user_id', $this->member_id)->limit($start, $length)->order('id', 'desc')->select();
        foreach ($data as &$item) {
            $item['create_time'] = date("Y-m-d H:i:s", $item['create_time']);
        }
        if (empty($data)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('lan_No_data'), 'data' => null]);
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'data' => $data]);
    }

    /**
     * @param Request $request
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function detail(Request $request)
    {
        $data = $this->convertLog->with(['currency', 'toCurrency'])->where(['user_id' => $this->member_id, 'id' => $request->post('id')])->find();
        $data['create_time'] = date("Y-m-d H:i:s", $data['create_time']);
        $data['user_id'] = '';
        $data['currency_cny_price'] = CurrencyPriceTemp::get_price_currency_id($data['currency_id'], 'CNY');
        $data['to_currency_cny_price'] = CurrencyPriceTemp::get_price_currency_id($data['to_currency_id'], 'CNY');
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'data' => $data]);
    }
}