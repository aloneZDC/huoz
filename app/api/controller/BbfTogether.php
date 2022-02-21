<?php
//ABF类型币币交易
namespace app\api\controller;

use app\common\model\AbfKline;
use app\common\model\AbfTradeCurrency;
use app\common\model\BbfTogetherCurrency;
use think\Db;
use think\Exception;
use think\Request;

class BbfTogether extends Base
{
    //币种列表
    public function currencys() {
        $res = [
            'code' => SUCCESS,
            'message' => lang('success_operation'),
            'result' =>   BbfTogetherCurrency::getListApi($this->member_id),
        ];
        return $this->output_new($res);
    }

    //购买
    public function buy() {
        $id = intval(input('id'));
        $res =  \app\common\model\BbfTogether::buy($this->member_id,$id);
        return $this->output_new($res);
    }

    //解除质押
    public function cancel() {
        $bbf_together_id = intval(input('bbf_together_id'));
        $res =  \app\common\model\BbfTogether::cancel_pledge($this->member_id,$bbf_together_id);
        return $this->output_new($res);
    }


}
