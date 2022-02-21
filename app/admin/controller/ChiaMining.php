<?php
namespace app\admin\controller;
use app\common\model\ChiaMiningConfig;
use app\common\model\ChiaMiningProduct;
use app\common\model\ChiaMiningReward;
use app\common\model\ChiaMiningMember;
use app\common\model\ChiaMiningPay;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

//chia矿机
class ChiaMining extends Admin
{
	//矿机列表
	public function product() {
        $list = ChiaMiningProduct::with(['currency','usdtcurrency','cnycurrency'])->select();
        return $this->fetch(null, compact('list'));
    }

    //添加
    public function product_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $form['add_time'] = time();
            $form['status'] = intval($form['status']);
            $flag = ChiaMiningProduct::insertGetID($form);
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

    //修改
    public function product_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $form['status'] = intval($form['status']);
            $flag = ChiaMiningProduct::where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = ChiaMiningProduct::where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name')->where('is_line',1)->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    //删除
    public function product_delete() {
        $id = input('id');
        $flag = ChiaMiningProduct::where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

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

        $list = \app\common\model\ChiaMiningPay::with(['users','product','miningcurrency','paycurrency', 'takes'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }
    
    //推荐奖列表
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

        $list = \app\common\model\ChiaMiningIncome::with(['users','currency','chiaminingpay'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $sum = \app\common\model\ChiaMiningIncome::where($where)->sum('num');
        return $this->fetch(null, compact('list', 'page','sum'));
    }

    //赠送列表
    public function reward(Request $request) {
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


        $list = \app\common\model\ChiaMiningReward::with(['users','currency','chiaminingpay'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $sum = \app\common\model\ChiaMiningReward::where($where)->sum('tnum');
        return $this->fetch(null, compact('list', 'page','sum'));
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

        $list = \app\common\model\ChiaMiningMember::with(['users'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page','count'));
    }

    // 更新等级
    public function update_level(Request $request) {
        if($this->request->isPost()){
            $id = intval(input('id'));
            $level = intval(input('level'));
            $data = \app\common\model\ChiaMiningMember::where(['id'=>$id])->find();
            if(empty($data)) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            }

            $flag = \app\common\model\ChiaMiningMember::where(['id'=>$id])->setField('level',$level);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                \app\common\model\ChiaMiningMember::updateTeamMaxLevel($data['member_id'],$level);
                $remarks = '管理员更新等级，从' . $data['level'] . '到' . $level;
                // 增加升级记录
                Db::name('chia_mining_level_log')->insert([
                    'third_id' => $data['member_id'],
                    'level' => $level,
                    'remarks' => $remarks,
                    'add_time' => time(),
                ]);
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = intval(input('id'));
            $data = \app\common\model\ChiaMiningMember::where(['id'=>$id])->find();
            $levels = \app\common\model\ChiaMiningLevelConfig::getAllLevel();
            return $this->fetch(null,compact('levels','data'));
        }
    }

    //赠送T数
    public function reward_add() {
        if($this->request->isPost()){
            $id = input('id');
            $tnum = input('tnum');
            $remarks = input('remarks');
            $data = \app\common\model\ChiaMiningPay::where(['id'=>$id])->field('member_id,product_id,mining_code,real_pay_currency_id,add_time,start_day,treaty_day')->find();
            $data['tnum'] = $tnum;
            $data['remarks'] = $remarks;
            $data['chia_mining_pay_id'] = $id;
            if (empty($data['tnum'])) {
                $this->ajaxReturn(['result'=>null,'code'=>'请填写赠送T数','message'=>'失败']);
            }
            if (empty($data['remarks'])) {
                $this->ajaxReturn(['result'=>null,'code'=>'请备注赠送原因','message'=>'失败']);
            }
            $data = json_decode($data, true);
            $flag =ChiaMiningReward::insertGetID($data);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\ChiaMiningPay::where(['id'=>$id])->find();

            return $this->fetch(null,compact('info'));
        }
    }

    //更新产币时间
    public function start_day_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $start_day = input('start_day');
            $cycle_time = input('cycle_time');
            $res = \app\common\model\ChiaMiningPay::where(['id'=>$id])->find();
            if (!empty($res['last_release_day']) || empty($res)) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'该订单不能调整产币时间']);
            }
            
            $data['start_day'] = strtotime($start_day);// 开挖时间
            $data['treaty_day'] = $data['start_day'] + ($cycle_time * 86400); // 合约到期时间
            $flag =\app\common\model\ChiaMiningPay::where(['id' => $id])->update($data);//支付订单
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                ChiaMiningReward::where(['chia_mining_pay_id' => $id])->update($data);//赠送订单

                //添加操作日志
                $detail = '开挖时间：从'.date('Y-m-d', $res['start_day']) . '到' .$start_day. '；合约周期：'.$cycle_time;
                \app\common\model\ChiaMiningLog::addLog('更新产币时间', $detail, $id, 'chia_mining_pay');

                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }

        } else {
            $id = input('id');
            $info = \app\common\model\ChiaMiningPay::where(['id'=>$id])->find();
            $info['cycle_time'] = \app\common\model\ChiaMiningProduct::where(['id'=>$info['product_id']])->value('cycle_time');

            return $this->fetch(null,compact('info'));
        }
    }

    //直推级别调整开关
    public function level_open() {
        $member_id = input('member_id');
        $level_open = input('level_open');

        ChiaMiningMember::openMemberLevel($member_id, $level_open);
        $flag = ChiaMiningMember::where(['member_id' => $member_id])->update(['level_open' => $level_open]);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
        } else {
            //添加操作日志
            $status = $level_open == 1 ? '开启' : '关闭';
            $detail = '用户'.$member_id.$status.'直推晋级开关';
            \app\common\model\ChiaMiningLog::addLog('直推级别调整开关', $detail, $member_id, 'chia_mining_member');

            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
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
        $list = \app\common\model\ChiaMiningConfig::order('id asc')->select();
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
        $info = \app\common\model\ChiaMiningConfig::where(['id' => $id])->find();
        if (empty($info)) $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '配置不存在']);

        $filed = input('field');
        if (empty($filed) || !in_array($filed, $allow_field)) $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '不允许修改']);

        $value = input('value');
        $data = [$filed => $value];
        $flag = \app\common\model\ChiaMiningConfig::where(['id' => $info['id']])->update($data);

        // 更新配置时  清除缓存
//        $today_start = strtotime(date('Y-m-d'));
//        cache('mining_price_' . $today_start,null);

        if ($flag === false) {
            $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '修改失败']);
        } else {
            $this->ajaxReturn(['result' => null, 'code' => SUCCESS, 'message' => '修改成功']);
        }
    }

    // 新建订单
    public function order_add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = ChiaMiningPay::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $date = date('Y-m-d');
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
            return $this->fetch(null,compact('date', 'data'));
        }
    }

    //修改订单
    public function order_edit() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = ChiaMiningPay::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = ChiaMiningPay::alias('a')->join('chia_mining_take b', 'a.id=b.third_id', 'left')->field('a.*,b.take_rate,b.service_rate')->where(['a.id'=>$id])->find();
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
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

        $list = \app\common\model\ChiaMiningCommission::with(['users','cusers'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
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
            $flag = \app\common\model\ChiaMiningCommission::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'添加失败，已存在相同的记录']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
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
            $flag = \app\common\model\ChiaMiningCommission::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\ChiaMiningCommission::where(['id'=>$id])->find();
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
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

        $list = \app\common\model\ChiaMiningTake::with(['users'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 新增抽点配置记录
    public function take_add() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\ChiaMiningTake::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'添加失败，已存在相同的记录']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
            return $this->fetch(null,compact('data'));
        }
    }

    //修改抽点配置记录
    public function take_edit() {
        if($this->request->isPost()){
            $form = input('post.');
            $flag = \app\common\model\ChiaMiningTake::addItem($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\ChiaMiningTake::where(['id'=>$id])->find();
            $data = \app\common\model\ChiaMiningConfig::get_key_value();
            return $this->fetch(null,compact('info', 'data'));
        }
    }

    // 产币记录列表
    public function release(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        if ($member_id) $where['member_id'] = $member_id;

        $list = \app\common\model\ChiaMiningRelease::where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
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

        $list = \app\common\model\ChiaMiningIncome::with(['users', 'thirdmember'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}