<?php
namespace app\admin\controller;
use app\common\model\AbfTradeCurrency;
use app\common\model\BbfTogetherCurrency;
use app\common\model\BbfTogetherIncome;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

//四币联发
class BbfTogether extends Admin
{
    //挂单列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['release_currency_id'] = $currency_id;
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


        $pledge_status = $this->request->get('pledge_status');
        if($pledge_status!='') $where['pledge_status'] = intval($pledge_status);


        $list = \app\common\model\BbfTogether::with(['users','releasecurrency','lockcurrency','pledgecurrency','paycurrency','payothercurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BbfTogetherCurrency::getListApi();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //成交列表
    public function release(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['release_currency_id'] = $currency_id;
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

        $third_id = $this->request->get('third_id');
        if($third_id!='') $where['third_id'] = intval($third_id);

        $list = \app\common\model\BbfTogetherRelease::with(['users','releasecurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BbfTogetherCurrency::getListApi();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //挂单撤销
    public function release_detail(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['release_currency_id'] = $currency_id;
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

        $third_id = $this->request->get('third_id');
        if($third_id!='') $where['third_id'] = intval($third_id);

        $release_id = $this->request->get('release_id');
        if($release_id!='') {
            //1代 2代
            $income = BbfTogetherIncome::where(['release_id'=>$release_id])->limit(10)->select();
            $this->assign('income',$income);

            $where['release_id'] = $release_id;

            $order = 'id asc';
        } else {
            $order = 'id desc';
        }

        $list = \app\common\model\BbfTogetherIncomeDetail::with(['users','currency'])->where($where)->order($order)->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BbfTogetherCurrency::getListApi();
        return $this->fetch(null, compact('list', 'page','currency_list'));
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
