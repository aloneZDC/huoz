<?php
namespace app\admin\controller;

use app\common\model\FlopChildNum;
use app\common\model\FlopCurrency;
use app\common\model\FlopOrders;
use app\common\model\FlopTrade;
use app\common\model\FlopTradeRelease;
use app\common\model\FlopTradeReleaseConfig;
use app\common\model\FlopWhite;
use think\Request;

class Flop extends Admin
{
    //投票俱乐部列表
    public function flop_orders(Request $request)
    {
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
        $status = $this->request->get('status');
        if($status!='') $where['status'] = intval($status);
        $is_super = input('is_super');
        if($is_super==1) $where['super_num'] = ['gt',0];
        $list = FlopOrders::with(['users','currency','paycurrency'])->where($where)->order('orders_id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //下架
    public function orders_down() {
        $orders_id = input('orders_id');
        $orders_info = FlopOrders::where(['orders_id'=>$orders_id])->find();
        if(!$orders_info) {
            return $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'广告不存在']);
        }
        $result = FlopOrders::cancel_orders($orders_info['member_id'],$orders_info['orders_id'],$this->admin['admin_id']);
        if($result['code']==SUCCESS){
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败'.$result['message']]);
        }
    }

    //投票俱乐部列表
    public function flop_trade(Request $request)
    {
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
        $is_super = input('is_super');
        if($is_super==1) $where['super_num'] = ['gt',0];
        $list = FlopTrade::with(['users','currency','paycurrency'])->where($where)->order('trade_id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    function release_log() {
        $trade_id = intval(input('trade_id'));
        $where['trade_id'] = $trade_id;
        $list = FlopTradeRelease::with(['users','currency'])->where($where)->order('release_id desc')->select();
        return $this->fetch(null, compact('list'));
    }

    public function flop_currency() {
        $list = FlopCurrency::with('currency')->select();
        return $this->fetch(null, compact('list'));
    }

    public function flop_currency_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $flag = FlopCurrency::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name')->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function flop_currency_edit() {
        if($this->request->isPost()){
            $currency_id = input('currency_id');
            $form = input('post.');
            $flag = FlopCurrency::where(['currency_id'=>$currency_id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency_id = input('currency_id');
            $info = FlopCurrency::where(['currency_id'=>$currency_id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name')->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function flop_currency_delete() {
        $currency_id = intval(input('currency_id'));
        $flag = FlopCurrency::where(['currency_id'=>$currency_id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function flop_release_config() {
        $list = FlopTradeReleaseConfig::select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置更新
    public function flop_release_config_update() {
        $allow_field = ['num','percent','levels','levels_percent','super_percent','num_percent','min_percent'];
        $id = intval(input('id'));
        $info = FlopTradeReleaseConfig::where(['id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        $flag = FlopTradeReleaseConfig::where(['id'=>$info['id']])->update([$filed=>$value]);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function white_list() {
        $list = FlopWhite::select();
        $this->ajaxReturn(['result'=>$list,'code'=>SUCCESS,'message'=>'修改成功']);
    }

    public function add_white() {
        $member_id = intval(input('member_id'));
        FlopWhite::insertGetId([
            'member_id' => $member_id,
        ]);
        $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
    }

    public function delete_white() {
        $member_id = intval(input('member_id'));
        FlopWhite::where(['member_id'=>$member_id])->delete();
        $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
    }

    // 下级直推增加次数
    public function child_num(Request $request)
    {
        $type = $request->get('type', 'all');
        $userId = $request->get('user_id', null);

        $where = [];
        if (in_array($type, ['hongbao', 'flop'])) {
            $where['type'] = $type;
        }

        if ($userId) {
            $where['user_id'] = $userId;
        }

        $list = FlopChildNum::where($where)->with('user')->order('child_num','desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $typeEnum = FlopChildNum::TYPE_ENUM;

        return $this->fetch(null, compact('list', 'page', 'count', 'typeEnum'));
    }
}
