<?php
//管理员充提币钱包地址列表
namespace app\admin\controller;

use think\Db;
use think\Request;

class WalletAdminAddress extends Admin
{
    protected $type = [
        'recharge' => '充币',
        'take' => '提币',
    ];

    public function index() {
        $list = \app\common\model\WalletAdminAddress::with('currency')->select();
        $type = $this->type;
        return $this->fetch(null, compact('list','type'));
    }

    public function add(){
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\WalletAdminAddress::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $type = $this->type;
            $currency = \app\common\model\Currency::field('currency_id,currency_name')->select();
            return $this->fetch(null,compact('currency','type'));
        }
    }

    public function edit() {
        if($this->request->isPost()){
            $waa_id = input('waa_id');
            $form = input('post.');
            $flag = \app\common\model\WalletAdminAddress::where(['waa_id'=>$waa_id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $waa_id = input('waa_id');
            $info = \app\common\model\WalletAdminAddress::where(['waa_id'=>$waa_id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name')->select();
            $type = $this->type;
            return $this->fetch(null,compact('currency','info','type'));
        }
    }

    public function del() {
        $waa_id = input('waa_id');
        $flag = \app\common\model\WalletAdminAddress::where(['waa_id'=>$waa_id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
        }
    }
}
