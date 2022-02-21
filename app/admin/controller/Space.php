<?php
namespace app\admin\controller;
use app\common\model\JumpRankingConfig;
use app\common\model\JumpRankingPower;
use app\common\model\SpacePlan;
use app\common\model\SpacePlanConfig;
use app\common\model\SpacePlanDayConfig;
use app\common\model\SpacePlanPay;
use app\common\model\SpacePlanPower;
use app\common\model\SpacePlanRecommand;
use app\common\model\SpacePlanRelease;
use think\paginator\driver\Bootstrap;
use think\Request;

class Space extends Admin
{
    //太空计划
    public function index(Request $request)
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

        $list = \app\common\model\SpacePlan::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    public function pay_index(Request $request) {
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

        $id = $this->request->get('id');
        if($id) $where['id'] = $id;

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\SpacePlanPay::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //推荐奖励
    public function recommand_index(Request $request) {
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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\SpacePlanRecommand::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    public function summary_index(Request $request) {
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


        $list = \app\common\model\SpacePlanSummary::with(['users','currency'])->where($where)->order('member_id asc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $total = \app\common\model\SpacePlanSummary::field('sum(total_num) as total_num,sum(total_release) as total_release,sum(total_recommand) as total_recommand,sum(total_power) as total_power')->find();
        return $this->fetch(null, compact('list', 'page','total'));
    }

    public function release_index(Request $request) {
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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\SpacePlanRelease::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //推荐奖励
    public function power_index(Request $request) {
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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $list = \app\common\model\SpacePlanPower::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //推荐奖励
    public function power_detail(Request $request) {
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

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $add_time = input('add_time');
        if($add_time) $where['add_time'] = $add_time;

        $list = \app\common\model\SpacePlanPowerDetail::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //投票配置
    public function config() {
        $list = SpacePlanConfig::order('id asc')->select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置更新
    public function config_update() {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = SpacePlanConfig::where(['id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $check_filed = $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        $data = [$filed=>$value];
        $flag = SpacePlanConfig::where(['id'=>$info['id']])->update($data);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function summary() {
        $online_start_time = strtotime('2020-07-10');

        $page = intval(input('page',1));
        $pageSize = 10;
        $stop_time = todayBeginTimestamp(); //结束时间
        $all_day = ($stop_time - $online_start_time)/86400;

        $real_stop_time = $stop_time - 86400 * $pageSize*($page-1);//开始时间
        $start_time = $real_stop_time - 86400 * $pageSize;//开始时间
        for ($time=$real_stop_time;$time>=$start_time;$time-=86400) {
            $cache_key = 'space_summary_'.$time;
            $data = cache($cache_key);
            if(empty($data)) {
                //总入金
                $income_total = SpacePlanPay::where(['add_time'=>['between',[$time,$time+86399]  ] ])->sum('num');
                //燃料
                $release = SpacePlanRelease::where(['add_time'=>['between',[$time,$time+86399]  ] ])->sum('num');
                //助力源
                $recommand = SpacePlanRecommand::where(['add_time'=>['between',[$time,$time+86399]  ] ])->sum('num');
                //动力源
                $power = SpacePlanPower::where(['add_time'=>['between',[$time,$time+86399]  ] ])->sum('num');

                $data = [
                    'today' => date('Y-m-d',$time),
                    'income_total' => $income_total,
                    'release' => $release,
                    'recommand' => $recommand,
                    'power' => $power,
                ];
                //今天 昨天的不缓存
                if($time<=$stop_time-86400*2) cache($cache_key,$data);
            }
            $list[] = $data;
        }

        $page = new Bootstrap($list,$pageSize,$page,$all_day,false,['path'=>url('')]);
        $page = $page->render();

        $total = \app\common\model\SpacePlanSummary::field('sum(total_num) as total_num,sum(total_release) as total_release,sum(total_recommand) as total_recommand,sum(total_power) as total_power')->find();
        return $this->fetch(null,compact('list','page','total'));
    }
}
