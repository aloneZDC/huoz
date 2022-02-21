<?php


namespace app\admin\controller;


use app\common\model\CurrencyUser as CurrencyUserModel;
use app\common\model\HongbaoKeepLog;
use app\common\model\Loan as LoanModel;
use think\Db;
use think\Exception;
use think\Request;

class Loan extends Admin
{
    protected $loanModel;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->loanModel = new LoanModel();
    }

    public function index(Request $request)
    {
        $status = $request->get('status');
        $userId = $request->get('user_id');
        $where = [];
        if ($status) {
            $where['status'] = $status;
        }
        if ($userId) {
            $where['user_id'] = $userId;
        }

        $list = $this->loanModel->where($where)->with(['user', 'currency'])->order('id', 'desc')->paginate(null, false, $request->get());
        $statusEnum = LoanModel::STATUS_ENUM;
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'statusEnum', 'count'));
    }

    public function successful(Request $request)
    {
        $id = $request->post('id');
        $examineMoney = $request->post('examine_money', 0, 'floatval');

        if (empty($id) or empty($examineMoney) or $examineMoney < 0) {
            return successJson(ERROR1, '参数错误');
        }
        Db::startTrans();
        try {
            if (!$this->loanModel->isWaitStatus($id)) {
                throw new Exception('该数据不是审核状态，请刷新页面后重试!');
            }
            $flag = $this->loanModel->setStatus($id, LoanModel::STATUS_SUCCESS);
            if ($flag === false) {
                throw new Exception('系统错误，请稍后再试!');
            }

            // 添加用户金额
            $data = $this->loanModel->where('id', $id)->find();
            $flag = HongbaoKeepLog::add_log('loan', $data['user_id'] , $data['currency_id'], $examineMoney, $data['id']);
            if (empty($flag)) {
                throw new Exception("系统错误，请稍后再试!");
            }
            $userCurrency = CurrencyUserModel::getCurrencyUser($data['user_id'], $data['currency_id']);
            $userCurrency['keep_num'] += $examineMoney;
            $data['examine_money'] = $examineMoney;
            if (!$userCurrency->save() or !$data->save()) {
                throw new Exception("系统错误，请稍后再试!");
            }

            Db::commit();
            return successJson(SUCCESS, '审核成功!');
        } catch (Exception $exception) {
            Db::rollback();
            return successJson(ERROR2, $exception->getMessage());
        }

    }

    public function fail(Request $request)
    {
        $id = $request->post('id');
        $reasonsRefusal = $request->post('reasons_refusal');

        if (empty($id)) {
            return successJson(ERROR1, '参数错误');
        }

        if (!$this->loanModel->isWaitStatus($id)) {
            return successJson(ERROR2, '该数据不是审核状态，请刷新页面后重试!');
        }


        $flag = $this->loanModel->setStatus($id, LoanModel::STATUS_FAIL, $reasonsRefusal);

        if ($flag === false) {
            return successJson(ERROR3, '系统错误，请稍后再试!');
        }
        return successJson(SUCCESS, '审核成功!');
    }
}