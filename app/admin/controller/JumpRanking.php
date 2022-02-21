<?php
namespace app\admin\controller;
use app\common\model\JumpRankingConfig;
use app\common\model\JumpRankingCurrencyConfig;
use app\common\model\JumpRankingCurrencyUser;
use app\common\model\JumpRankingMemberSummary;
use app\common\model\JumpRankingPower;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

class JumpRanking extends Admin
{
    //太空计划
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

        $list = \app\common\model\JumpRankingMemberSummary::with(['users','currency'])->where($where)->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = JumpRankingCurrencyConfig::getAllList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //收益记录
    public function ranking_income(Request $request) {
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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\JumpRankingIncome::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = JumpRankingCurrencyConfig::getAllList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    //推广算力收益记录
    public function power_income(Request $request) {
        $where = [];
        $user_id = input('user_id');
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

        $list = \app\common\model\JumpRankingPowerIncome::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $currency_list = JumpRankingCurrencyConfig::getAllList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    public function power_income_from(Request $request) {
        $where = [];

        $today = $request->get('today');
        $user_id = $member_id = intval($request->get('user_id'));
        $currency_id = intval($request->get('currency_id'));
        $ename = trim($request->get('ename',''));

        $page = intval($request->get('page',1));
        $list = [];
        $total = 0;
        if($today) {
            $res = JumpRankingMemberSummary::myTeambyDay($today,$member_id, $currency_id, $ename, $page, 10);
            if ($res['code'] == SUCCESS) {
                $list = $res['result'];

                $get = $request->get();
                if(isset($get['/'.$request->path()])) {
                    unset($get['/'.$request->path()]);
                }
                $page = new Bootstrap($res['total'],10,$page,$res['total'],false,[
                    'path'=>url(''),
                    'query' => $get,
                ]);
                $total = $res['total'];
                $page = $page->render();
            }
        }
        return $this->fetch(null, compact('list', 'page','today','user_id','currency_id','ename','total'));
    }

    //汇总
    public function summary(Request $request) {
        $currency_id = intval($request->get('currency_id'));
        $where = [];
        if(!empty($currency_id)) $where['currency_id'] = $currency_id;
        $list = \app\common\model\JumpRankingSummary::with(['currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $currency_list = JumpRankingCurrencyConfig::getAllList();
        return $this->fetch(null, compact('list', 'page','currency_list'));
    }

    public function currencys(Request $request) {
        $currency_list = JumpRankingCurrencyConfig::getAllList();
        $currency_ids = array_column($currency_list,'currency_id',null);
        $currency_user_changes = Db::name('currency_user_change')->alias('a')->field('a.*,c.currency_name')
            ->join(config("database.prefix").'currency c','a.currency_id = c.currency_id',"LEFT")
            ->order('id desc')->limit(count($currency_ids))->select();

        $yestdoday_currency_user_changes = Db::name('currency_user_change')->alias('a')->field('a.*,c.currency_name')
            ->join(config("database.prefix").'currency c','a.currency_id = c.currency_id',"LEFT")
            ->where(['a.add_time' => ['lt',todayBeginTimestamp()]])->order('id desc')->limit(count($currency_ids))->select();;

        $list = JumpRankingCurrencyConfig::with('currency')->select();
        return $this->fetch(null, compact('list','currency_user_changes','yestdoday_currency_user_changes'));
    }

    public function currency_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $form['auto_start_time'] = strtotime($form['auto_start_time']);
            $flag = JumpRankingCurrencyConfig::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function currency_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $form['auto_start_time'] = strtotime($form['auto_start_time']);
            $flag = JumpRankingCurrencyConfig::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = JumpRankingCurrencyConfig::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function currency_delete() {
        $id = intval(input('id'));
        $flag = JumpRankingCurrencyConfig::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

}
