<?php
namespace app\admin\controller;
use think\Request;

//矿源市场
class TboMarketCat extends Admin
{
    public function index(Request $request) {
        $list = \app\common\model\TboMarketCat::select();
        return $this->fetch(null, compact('list'));
    }

    public function add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\TboMarketCat::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            return $this->fetch(null);
        }
    }

    public function edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $flag = \app\common\model\TboMarketCat::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\TboMarketCat::where(['id'=>$id])->find();

            return $this->fetch(null,compact('info'));
        }
    }

    public function delete() {
        $id = intval(input('id'));
        $flag = \app\common\model\TboMarketCat::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
