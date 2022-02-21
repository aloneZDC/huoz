<?php
namespace app\admin\controller;
use app\common\model\AloneMiningConfig;
use app\common\model\AloneMiningProduct;
use app\common\model\AloneMiningMember;
use app\common\model\AloneMiningPay;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;


class AloneMining extends Admin
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

        $list = \app\common\model\AloneMiningPay::with(['users','product','miningcurrency','paycurrency','takes'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 新建订单
    public function order_add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = AloneMiningPay::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $date = date('Y-m-d');
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('date', 'data'));
        }
    }

    //修改订单
    public function order_edit() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = AloneMiningPay::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = AloneMiningPay::alias('a')->join('alone_mining_take b', 'a.id=b.third_id', 'left')->field('a.*,b.take_rate,b.service_rate')->where(['a.id'=>$id])->find();
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('info', 'data'));
        }
    }

    // 提成记录列表
    public function commission_list(Request $request)
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

        $ename = $request->get('ename');
        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $list = \app\common\model\AloneMiningCommission::with(['users','cusers'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 新增提成配置记录
    public function commission_add() {
        if($this->request->isPost()){
            $form = input('post.');
            // 判断下级账户是否存在
            $res = \app\common\model\MemberBind::where(['member_id' => $form['member_id'], 'child_id' => $form['child_id'], 'level' => $form['level_num']])->find();
            if (!$res) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'代数或下级账户不正确']);
            }
            // 判断是否超过第五代
            if ($form['level_num'] > 5) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'只能配置到第五代，请重新填写！']);
            }
            // 添加配置
            $flag = \app\common\model\AloneMiningCommission::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'添加失败，已存在相同的记录']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('data'));
        }
    }

    //修改提成配置记录
    public function commission_edit() {
        if($this->request->isPost()){
            $form = input('post.');
            // 判断下级账户是否存在
            $res = \app\common\model\MemberBind::where(['member_id' => $form['member_id'], 'child_id' => $form['child_id'], 'level' => $form['level_num']])->find();
            if (!$res) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'代数或下级账户不正确']);
            }
            // 判断是否超过第五代
            if ($form['level_num'] > 5) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'只能配置到第五代，请重新填写！']);
            }
            // 更新配置
            $flag = \app\common\model\AloneMiningCommission::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\AloneMiningCommission::where(['id'=>$id])->find();
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('info', 'data'));
        }
    }

    // 抽点记录列表
    public function take_list(Request $request)
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

        $ename = $request->get('ename');
        if($ename) {
            $user = \app\common\model\Member::where(['ename'=>$ename])->find();
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }

        $list = \app\common\model\AloneMiningTake::with(['users'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 新增抽点配置记录
    public function take_add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\AloneMiningTake::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'添加失败，已存在相同的记录']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('data'));
        }
    }

    //修改抽点配置记录
    public function take_edit() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\AloneMiningTake::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\AloneMiningTake::where(['id'=>$id])->find();
            $data = \app\common\model\AloneMiningConfig::get_key_value();
            return $this->fetch(null,compact('info', 'data'));
        }
    }

    /**
     * 矿机配置
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config()
    {
        $list = \app\common\model\AloneMiningConfig::order('id asc')->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 矿机配置更新
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_update()
    {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = \app\common\model\AloneMiningConfig::where(['id' => $id])->find();
        if (empty($info)) $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '配置不存在']);

        $filed = input('field');
        if (empty($filed) || !in_array($filed, $allow_field)) $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '不允许修改']);

        $value = input('value');
        $data = [$filed => $value];
        $flag = \app\common\model\AloneMiningConfig::where(['id' => $info['id']])->update($data);

        // 更新配置时  清除缓存
//        $today_start = strtotime(date('Y-m-d'));
//        cache('mining_price_' . $today_start,null);

        if ($flag === false) {
            $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '修改失败']);
        } else {
            $this->ajaxReturn(['result' => null, 'code' => SUCCESS, 'message' => '修改成功']);
        }
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

        $list = \app\common\model\AloneMiningMember::with(['users', 'pays'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page','count'));
    }

    // 产币记录列表
    public function release(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        if ($member_id) $where['member_id'] = $member_id;

        $list = \app\common\model\AloneMiningRelease::where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $start_time = strtotime(date('Y-m-d',$value['release_time']));
                $end_time = $start_time + 86399;
                $archive = \app\common\model\AloneMiningArchive::where(['member_id' => $value['member_id'], 'add_time' => ['between', [$start_time,$end_time]]])->order('id DESC')->value('real_pay_num');
                $value['real_pay_num'] = $archive == 0 ? 0 : $archive;//质押费
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 奖励记录列表
    public function income_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        if ($member_id) $where['member_id'] = $member_id;
        $where['type'] = 3;//提成奖励

        $list = \app\common\model\AloneMiningIncome::with(['users'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $child_id = Db::name('alone_mining_pay')->where(['id' => $value['third_id']])->value('member_id');
                $value['thirdmember'] = Db::name('member')->field('member_id,email,phone,nick,name,ename')->where(['member_id' => $child_id])->find();
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}