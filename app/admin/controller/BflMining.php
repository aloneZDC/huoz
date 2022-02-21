<?php
namespace app\admin\controller;
use app\common\model\BflMiningBonusDetail;
use app\common\model\BflMiningCurrencyConfig;
use app\common\model\BflMiningLevelConfig;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

class BflMining extends Admin
{
    //质押列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) $where['currency_id'] = $currency_id;

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

        $id = $this->request->get('id');
        if($id!='') $where['id'] = intval($id);

        $list = \app\common\model\BflMining::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BflMiningCurrencyConfig::getList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //日挖矿收益
    public function release(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

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

        $currency_id = $request->get('currency_id');
        if($currency_id) $where['currency_id'] = $currency_id;

        $list = \app\common\model\BflMiningRelease::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BflMiningCurrencyConfig::with('currency')->select();;
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //下级收益
    public function bonus(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

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

        $currency_id = $request->get('currency_id');
        if($currency_id) $where['currency_id'] = $currency_id;

        $list = \app\common\model\BflMiningBonus::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BflMiningCurrencyConfig::with('currency')->select();;
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //下级收益
    public function bonus_detail(Request $request) {
        $where = [];

        $currency_id = $request->get('currency_id');
        if($currency_id) $where['currency_id'] = $currency_id;

        $add_day = $request->get('add_day');
        if($add_day) $where['add_day'] = $add_day;


        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\BflMiningBonusDetail::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = BflMiningCurrencyConfig::with('currency')->select();;
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    public function currencys(Request $request) {
        $list = BflMiningCurrencyConfig::with('currency')->select();
        return $this->fetch(null, compact('list'));
    }

    public function currency_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $form['auto_start_time'] = strtotime($form['auto_start_time']);
            $flag = BflMiningCurrencyConfig::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where('account_type','mining')->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function currency_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $form['auto_start_time'] = strtotime($form['auto_start_time']);
            $flag = BflMiningCurrencyConfig::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = BflMiningCurrencyConfig::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where('account_type','mining')->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function currency_delete() {
        $id = intval(input('id'));
        $flag = BflMiningCurrencyConfig::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function level_config(Request $request) {
        $currency_id = intval(input('currency_id'));
        $list = BflMiningLevelConfig::with('currency')->where('currency_id',$currency_id)->select();
        return $this->fetch(null, compact('list','currency_id'));
    }

    public function level_config_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $flag = BflMiningLevelConfig::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency_id = intval(input('currency_id'));
            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where([
                'currency_id'=>$currency_id,
                'account_type'=> 'mining',
            ])->find();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function level_config_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $flag = BflMiningLevelConfig::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = BflMiningLevelConfig::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where('currency_id',$info['currency_id'])->where('account_type','mining')->find();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function level_config_delete() {
        $id = intval(input('id'));
        $flag = BflMiningLevelConfig::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
