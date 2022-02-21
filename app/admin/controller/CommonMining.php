<?php
namespace app\admin\controller;
use app\common\model\CommonMiningConfig;
use app\common\model\CommonMiningProduct;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

//传统矿机
class CommonMining extends Admin
{
    //挂单列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['currency_id'] = $currency_id;
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

        $level = $request->get('level');
        if($level) {
            $where['level'] = $level;
        }

        $list = \app\common\model\CommonMiningPay::with(['users','product','miningcurrency','paycurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        return $this->fetch(null, compact('list', 'page'));
    }

    //挂单列表
    public function member(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['currency_id'] = $currency_id;
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

        $level = $request->get('level');
        if($level) {
            $where['level'] = $level;
        }

        $list = \app\common\model\CommonMiningMember::with(['users'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $levels = \app\common\model\CommonMiningLevelConfig::getAllLevel();
        return $this->fetch(null, compact('list', 'page','levels'));
    }

    //释放列表
    public function release(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['currency_id'] = $currency_id;
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

        $list = \app\common\model\CommonMiningRelease::with(['users','currency','realcurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $sum = \app\common\model\CommonMiningRelease::where($where)->sum('num');
        return $this->fetch(null, compact('list', 'page','sum'));
    }

    //挂单撤销
    public function level_detail(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['currency_id'] = $currency_id;
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
        if($third_id!='') {
            $where['third_id'] = intval($third_id);
            $order = "id asc";
        } else {
            $order = "id desc";
        }

        $award_time = $this->request->get('award_time');
        if($award_time) {
            $where['award_time'] = $award_time;
        }

        $list = \app\common\model\CommonMiningLevelIncomeDetail::with(['users','currency'])->where($where)->order($order)->paginate(15, false, ['query' => $request->get()]);
        $page = $list->render();

        $sum = \app\common\model\CommonMiningLevelIncomeDetail::where($where)->sum('num');
        return $this->fetch(null, compact('list', 'page','sum'));
    }

    public function income(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $currency_id = $request->get('currency_id');
        if($currency_id) {
            $where['currency_id'] = $currency_id;
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

        $type = input('type');
        if($type) {
            $where['type'] = $type;
        }

        $recommand = input('recommand');
        if($recommand) {
            $where['third_id'] = intval(input('third_id',0));
            $where['type'] = ['in',[1,2,3]];
        }

        $level_income = input('level_income');
        if($level_income) {
            $where['third_id'] = intval(input('third_id',0));
            $where['type'] = ['in',[4]];
        }

        $list = \app\common\model\CommonMiningIncome::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $sum = \app\common\model\CommonMiningIncome::where($where)->sum('num');
        $lock_sum = \app\common\model\CommonMiningIncome::where($where)->sum('lock_num');
        return $this->fetch(null, compact('list', 'page','sum','lock_sum'));
    }

    public function summary(Request $request) {
        $total = Db::name('common_mining_summary')->field('sum(pay_num) as pay_num,sum(release_num) as release_num,sum(team1_num) as team1_num,sum(team2_num) as team2_num,sum(team3_num) as team3_num,sum(team4_num) as team4_num,sum(team5_num) as team5_num,sum(team7_num) as team7_num')->find();

        $list = Db::name('common_mining_summary')->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $lock_num_sum = \app\common\model\CurrencyUser::sum('lock_num');
        $lock_global_sum = \app\common\model\CurrencyUser::sum('global_lock');
        return $this->fetch(null, compact('list', 'page','total','lock_num_sum','lock_global_sum'));
    }

    // 更新等级
    public function update_level(Request $request) {
        if($this->request->isPost()){
            $id = intval(input('id'));
            $level = intval(input('level'));
            $data = \app\common\model\CommonMiningMember::where(['id'=>$id])->find();
            if(empty($data)) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            }

            $flag = \app\common\model\CommonMiningMember::where(['id'=>$id])->setField('level',$level);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                \app\common\model\CommonMiningMember::updateTeamMaxLevel($data['member_id'],$level);
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = intval(input('id'));
            $data = \app\common\model\CommonMiningMember::where(['id'=>$id])->find();
            $levels = \app\common\model\CommonMiningLevelConfig::getAllLevel();
            return $this->fetch(null,compact('levels','data'));
        }
    }


    //投票配置
    public function config() {
        $list = CommonMiningConfig::order('id asc')->select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置更新
    public function config_update() {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = CommonMiningConfig::where(['id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        $data = [$filed=>$value];
        $flag = CommonMiningConfig::where(['id'=>$info['id']])->update($data);

        // 更新配置时  清除缓存
        $today_start = strtotime(date('Y-m-d'));
        cache('mining_price_' . $today_start,null);

        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function product() {
        $list = CommonMiningProduct::with(['currency','usdtcurrency','cnycurrency'])->select();
        return $this->fetch(null, compact('list'));
    }

    public function product_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $form['add_time'] = time();
            $form['status'] = intval($form['status']);
            $flag = CommonMiningProduct::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name')->where('is_line',1)->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function product_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $form['status'] = intval($form['status']);
            $flag = CommonMiningProduct::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = CommonMiningProduct::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name')->where('is_line',1)->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function product_delete() {
        $id = input('id');
        $flag = CommonMiningProduct::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
