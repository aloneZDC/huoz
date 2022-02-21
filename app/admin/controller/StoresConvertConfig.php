<?php


namespace app\admin\controller;

use think\Exception;
use think\Request;
class StoresConvertConfig extends Admin
{
    protected $type_list = [
        'num' => '可用',
        'card' => 'i券',
        'financial' => 'o券',
    ];

    //线下上级列表
    public function index(Request $request) {
        $where = [];
        $count = \app\common\model\StoresConvertConfig::where($where)->count();
        $list = \app\common\model\StoresConvertConfig::where($where)->with(['currency','tocurrency'])->order('id desc')->paginate(null, $count, ['query' => $request->get()]);
        $page = $list->render();
        $type_list = $this->type_list;
        return $this->fetch(null, compact('list', 'page', 'count','type_list'));
    }

    public function add(Request $request) {
        if($request->isAjax()) {
            $currency_id = intval(input('currency_id',0));
            if(empty($currency_id)) $this->ajaxReturn("",'请选择币种',0);

            $to_currency_id = intval(input('to_currency_id',0));
            if(empty($to_currency_id)) $this->ajaxReturn("",'请选择到账币种',0);

            $fee = input('fee');
            if(!is_numeric($fee) || $fee<0 || $fee>100)  $this->ajaxReturn("",'请填写正确的手续费百分比',0);

            $data = [
                'currency_id' => $currency_id,
                'currency_field' => input('currency_field',''),
                'to_currency_id' => $to_currency_id,
                'to_currency_field' => input('to_currency_field',''),
                'to_currency_inc_percent' => input('to_currency_inc_percent',0),
                'min_num' => input('min_num'),
                'max_num' => input('max_num'),
                'fee' => $fee,
            ];
            $flag = \app\common\model\StoresConvertConfig::insertGetId($data);
            if(!$flag) $this->ajaxReturn("",'添加失败,请重试',0);

            $this->ajaxReturn("",'添加成功',10000);
        } else {
            $currency = \app\common\model\Currency::select();
            $type_list = $this->type_list;
            return $this->fetch(null, compact('currency', 'type_list'));
        }
    }

    public function edit(Request $request) {
        $id = intval(input('id'));

        if($request->isAjax()) {
            if(empty($id)) $this->ajaxReturn("",'记录不存在',0);

            $info = \app\common\model\StoresConvertConfig::where(['id'=>$id])->find();
            if(empty($info)) $this->ajaxReturn("",'记录不存在',0);

            $currency_id = intval(input('currency_id',0));
            if(empty($currency_id)) $this->ajaxReturn("",'请选择币种',0);

            $to_currency_id = intval(input('to_currency_id',0));
            if(empty($to_currency_id)) $this->ajaxReturn("",'请选择到账币种',0);

            $fee = input('fee');
            if(!is_numeric($fee) || $fee<0 || $fee>100)  $this->ajaxReturn("",'请填写正确的手续费百分比',0);

            $data = [
                'currency_id' => $currency_id,
                'currency_field' => input('currency_field',''),
                'to_currency_id' => $to_currency_id,
                'to_currency_field' => input('to_currency_field',''),
                'to_currency_inc_percent' => input('to_currency_inc_percent',0),
                'min_num' => input('min_num'),
                'max_num' => input('max_num'),
                'fee' => $fee,
            ];
            $flag = \app\common\model\StoresConvertConfig::where(['id'=>$info['id']])->update($data);
            if(!$flag) $this->ajaxReturn("",'编辑失败,请重试',0);

            $this->ajaxReturn("",'编辑成功',10000);
        } else {
            if(empty($id)) $this->error('记录不存在');

            $info = \app\common\model\StoresConvertConfig::where(['id'=>$id])->find();
            if(empty($info)) $this->error('记录不存在');

            $currency = \app\common\model\Currency::select();
            $type_list = $this->type_list;
            return $this->fetch(null, compact('currency', 'type_list','info'));
        }
    }

    public function delete() {
        $id = intval(input('id'));
        $flag = \app\common\model\StoresConvertConfig::where(['id'=>$id])->delete();
        if($flag===false) $this->ajaxReturn("",'操作失败',0);
        $this->ajaxReturn("",'操作成功',10000);
    }
}