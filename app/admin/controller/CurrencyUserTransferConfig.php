<?php


namespace app\admin\controller;

use think\Exception;
use think\Request;
class CurrencyUserTransferConfig extends Admin
{
    protected $type_list = [
        'num' => '可用',
        'game_lock' => 'io券',
        'uc_card' => 'i券',
        'uc_card_lock' => 'o券',
        'dnc_lock' => 'DNC锁仓',
    ];
    //线下上级列表
    public function index(Request $request) {
        $where = [];
        $count = \app\common\model\CurrencyUserTransferConfig::where($where)->count();
        $list = \app\common\model\CurrencyUserTransferConfig::where($where)->with(['currency'])->order('id desc')->paginate(null, $count, ['query' => $request->get()]);
        $page = $list->render();
        $type_list = $this->type_list;
        return $this->fetch(null, compact('list', 'page', 'count','type_list'));
    }

    public function add(Request $request) {
        if($request->isAjax()) {
            $currency_id = intval(input('currency_id',0));
            if(empty($currency_id)) $this->ajaxReturn("",'请选择币种',0);

            $fee = input('fee');
            if(!is_numeric($fee) || $fee<0 || $fee>100)  $this->ajaxReturn("",'请填写正确的手续费百分比',0);

            $data = [
                'currency_id' => $currency_id,
                'type' => input('type',''),
                'min_num' => input('min_num'),
                'max_num' => input('max_num'),
                'fee' => $fee,
            ];
            $flag = \app\common\model\CurrencyUserTransferConfig::insertGetId($data);
            if(!$flag) $this->ajaxReturn("",'添加失败,请重试',0);

            $this->ajaxReturn("",'添加成功',10000);
        } else {
            $currency = \app\common\model\Currency::select();
            $type_list = $this->type_list;
            return $this->fetch(null, compact('currency', 'type_list'));
        }
    }

    public function edit(Request $request) {
        $id = intval(input('id',0));
        if($request->isAjax()) {
            $currency_id = intval(input('currency_id',0));
            if(empty($currency_id)) $this->ajaxReturn("",'请选择币种',0);

            $fee = input('fee');
            if(!is_numeric($fee) || $fee<0 || $fee>100)  $this->ajaxReturn("",'请填写正确的手续费百分比',0);

            $data = [
                'currency_id' => $currency_id,
                'type' => input('type',''),
                'min_num' => input('min_num'),
                'max_num' => input('max_num'),
                'fee' => $fee,
            ];
            $flag = \app\common\model\CurrencyUserTransferConfig::where('id',$id)->update($data);
            if($flag===false) $this->ajaxReturn("",'添加失败,请重试',0);

            $this->ajaxReturn("",'添加成功',10000);
        } else {
            $info = \app\common\model\CurrencyUserTransferConfig::where('id',$id)->find();
            $currency = \app\common\model\Currency::select();
            $type_list = $this->type_list;
            return $this->fetch(null, compact('currency', 'type_list','info'));
        }

    }

    public function quick_switch(){
        $id = intval(input('id'));
        $field = input('field');
        if(empty($field) || !in_array($field,['is_open'])) $this->ajaxReturn("",'特殊配置,不支持快捷开关',0);

        $status = intval(input('status'));
        $flag = \app\common\model\CurrencyUserTransferConfig::where(['id'=>$id])->setField($field,$status);
        if(!$flag) $this->ajaxReturn("",'操作失败',0);

        $this->ajaxReturn("",'操作成功',10000);
    }

    public function delete() {
        $id = intval(input('id'));
        $flag = \app\common\model\CurrencyUserTransferConfig::where(['id'=>$id])->delete();
        if($flag===false) $this->ajaxReturn("",'操作失败',0);
        $this->ajaxReturn("",'操作成功',10000);
    }
}
