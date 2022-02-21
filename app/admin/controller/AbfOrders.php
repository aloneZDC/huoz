<?php
namespace app\admin\controller;
use app\common\model\AbfTradeCurrency;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

//ABF币币交易
class AbfOrders extends Admin
{
    //挂单列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $currency_id_list = explode('/',$currency_id);
            $where['currency_id'] = $currency_id_list[0];
            $where['currency_trade_id'] = $currency_id_list[1];
        }

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $ename = $request->get('ename');
        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }


        $status = $this->request->get('status');
        if($status!='') $where['status'] = intval($status);

        $type = $this->request->get('type');
        if($type!='') $where['type'] = $type;

        $list = \app\common\model\AbfOrders::with(['users','currency','currencytrade'])->where($where)->order('orders_id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = AbfTradeCurrency::getList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //成交列表
    public function trade(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $currency_id_list = explode(',',$currency_id);
            $where['currency_id'] = $currency_id_list[0];
            $where['currency_trtade_id'] = $currency_id_list[1];
        }

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $ename = $request->get('ename');
        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $orders_id = $this->request->get('orders_id');
        if($orders_id!='') $where['orders_id'] = intval($orders_id);

        $type = $this->request->get('type');
        if($type!='') $where['type'] = $type;

        $list = \app\common\model\AbfTrade::with(['users','otherusers','currency','currencytrade'])->where($where)->order('trade_id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = AbfTradeCurrency::getList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //挂单撤销
    public function orders_cancel() {
        $orders_id = intval(input('orders_id'));
        $abf_orders = \app\common\model\AbfOrders::where(['orders_id'=>$orders_id])->find();
        if(empty($abf_orders)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'挂单不存在']);

        $res = \app\common\model\AbfOrders::cancel($abf_orders['member_id'],$abf_orders['orders_id']);
        if($res['code']!=SUCCESS) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>$res['message']]);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
        }
    }

    public function currencys(Request $request) {
        $list = AbfTradeCurrency::with('currency','currencytrade')->select();
        return $this->fetch(null, compact('list'));
    }

    public function currency_add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = AbfTradeCurrency::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where('account_type','currency')->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function currency_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $flag = AbfTradeCurrency::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = AbfTradeCurrency::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where('account_type','currency')->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function currency_delete() {
        $id = intval(input('id'));
        $flag = AbfTradeCurrency::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
