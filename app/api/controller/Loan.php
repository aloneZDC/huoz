<?php


namespace app\api\controller;


use app\common\model\Currency;
use app\common\model\Loan as LoanModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;

class Loan extends Base
{
    /**
     * @var LoanModel $loanModel
     */
    protected $loanModel;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->loanModel = new LoanModel();
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function index()
    {
        $result = null;
        $currency = (new Currency)->where('currency_id', Currency::KOI_ID)->field('currency_id, currency_name')->find();
        $maxNumber = $this->loanModel->getCreditQuota($this->member_id);
        $result = [
            'currency' => $currency,
            'max_number' => $maxNumber
        ];
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $lossProject = $request->post('loss_project');
        $lossMoney = $request->post('loss_money');
        $money = $request->post('money', 0, 'floatval');
        $response = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$lossProject or !$lossMoney or !$money or $money <= 0) {
            return $this->output_new($response);
        }

//        $creditQuota = $this->loanModel->getCreditQuota($this->member_id);
//        if ($creditQuota < $money) {
//            $response['message'] = lang('insufficient_credit_balance');
//            return $this->output_new($response);
//        }

        $result = $this->loanModel->addApply($this->member_id, $money, $lossMoney, $lossProject);
        if (empty($result)) {
            $response['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($response);
        }

        return $this->output_new(['code' => SUCCESS, 'message' => lang('lan_operation_success'), 'result' => $result]);
    }

    /**
     * 历史记录
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @return mixed
     */
    public function history()
    {
        $history = $this->loanModel->field('id, currency_id, money, status, examine_money, from_unixtime(create_time, "%Y/%m/%d") as create_time')
            ->where('user_id', $this->member_id)
            ->where('currency_id', Currency::KOI_ID)
            ->with('currency')
            ->select();
        if (empty($history)) {
            return $this->output_new(['code' => SUCCESS, 'message' => lang('lan_not_data'), 'result' => null]);
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $history]);
    }

    /**
     * 申请详情
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @return mixed
     */
    public function detail(Request $request)
    {
        $id = $request->post('id');
        $result = $this->loanModel->field('id, currency_id,money, loss_money, loss_project, status, reasons_refusal, examine_money')
            ->where('user_id', $this->member_id)
            ->where('currency_id', Currency::KOI_ID)
            ->where('id', $id)
            ->with('currency')
            ->find();
        if (empty($result)) {
            return $this->output_new(['code' => SUCCESS, 'message' => lang('lan_not_data'), 'result' => null]);
        }

        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $result]);
    }

}