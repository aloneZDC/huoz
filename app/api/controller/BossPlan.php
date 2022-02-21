<?php
/**
 *发送邮件
 */

namespace app\api\controller;

use app\common\model\Member;
use think\Db;
use think\Exception;

class BossPlan extends Base
{
    protected $public_action = ['banklist'];
    protected $is_method_filter = true;
    private $boss_plan_public=['banklist','index'];
    public function _initialize()
    {
        parent::_initialize();

        if(!empty($this->member_id)){
            $action = strtolower($this->request->action());
            if(!in_array($action, $this->boss_plan_public) && !$this->lock_user()){
                $this->output(10001,lang('lan_lock_user'),[]);
            };
        }
    }
    //@标
    public function lock_user(){
        $this->member_id;
        $info = Db::name('boss_plan')->field('lock_status')->where(['member_id'=>$this->member_id])->find();
        if($info['lock_status']==2){
            return false;
        }
        return true;
    }
    //获取用户状态
    public function index()
    {
        $info = Db::name('boss_plan')->where(['member_id' => $this->member_id])->find();
        if (!$info) {
            $status = -1;
        } else {
            $status = $info['status'];
        }

        $pid_phone = '';
        $pid_member_id = '';
        $pid = Db::name('member')->where(['member_id'=>$this->member_id])->value('pid');
        $pid = intval($pid);
        if($pid>0){
            $pidInfo = Db::name('member')->field('member_id,phone,email')->where(['member_id'=>$pid])->find();
            if($pidInfo) {
                $boss_plan_info = Db::name('boss_plan')->where(['member_id'=>$pid,'status'=>3])->find();
                if($boss_plan_info) {
                    if(empty($pidInfo['phone'])) $pidInfo['phone'] = $pidInfo['email'];
                    $pid_phone = $pidInfo['phone'];
                    $pid_member_id = $pidInfo['member_id'];
                } 
            }
        }

        $return = [
            'title' => lang('lan_boss_plan_title_question'),
            'desc' => lang('lan_boss_plan_desc'),
            'img1' => 'http://ruibooss.oss-cn-hongkong.aliyuncs.com/huandeng_pics/2018-12-14/cff5f6e38c2c2e2c.png',
            'img2' => 'http://ruibooss.oss-cn-hongkong.aliyuncs.com/article_pics/2018-12-26/56a2825f06b512d5.jpg',
            'status' => $status,
            'lock_status' => empty($info['lock_status'])?1:$info['lock_status'],
            'pid_phone' => $pid_phone,
            'pid_member_id' => $pid_member_id,
        ];

        //-1:申请 2待确认 3:进入 其他:审核中
        $this->output(10000, lang('lan_operation_success'), $return);
    }

    //1.搜索老板计划人员
    public function apply_search()
    {
        $pid = intval(input('member_id'));
        $invit_code = input('invit_code', '', 'strval');

        $member = model('BossPlan')->apply($this->member_id, $pid, $invit_code);
        if (is_string($member)) $this->output(10001, $member);

        $this->output(10000, lang('lan_operation_success'), $member);
    }

    //2.与邀约人绑定
    public function bind()
    {
        $pid = intval(input('member_id'));
        $invit_code = input('invit_code', '', 'strval');

        $member = model('BossPlan')->bind($this->member_id, $pid, $invit_code);
        if (is_string($member)) $this->output(10001, $member);

        $this->output(10000, lang('lan_operation_success'));
    }


    //3.激活社员
    public function activation_search()
    {
        $phone = input('phone', '');
        $other_member_id = intval(input('member_id'));

        $result = model('BossPlan')->activation_search($this->member_id, $other_member_id, $phone);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'), $result['user_info']);
    }


    //4.激活用户 冻结资产
    public function activation_user()
    {
        $other_member_id = intval(input('member_id'));
        $step_votes = intval(input('step_votes'));
        $pwd = strval(input('pwd'));
        $xrp_num = floatval(input('xrp_num')); //XRP数量
        $xrpz_num = floatval(input('xrpz_num')); //瑞波钻数量
        $xrpj_num = floatval(input('xrpj_num')); //瑞波金数量

        $result = model('BossPlan')->activation($this->member_id, $other_member_id, $step_votes, $pwd, $this->config, $xrp_num, $xrpz_num, $xrpj_num);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'));
    }

    //4.下级用户激活确认 
    public function activation_confirm()
    {
        $result = model('BossPlan')->activation_confirm($this->member_id, $this->config['pid_add_time']);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'));
    }

    //5.下级用户撤销激活
    public function activation_cancel()
    {
        $result = model('BossPlan')->activation_cancel_by_child($this->member_id);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'));
    }

    //申请绑定详情
    public function apply_info()
    {
        $result = model('BossPlan')->apply_info($this->member_id);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'), $result);
    }

    //认购列表
    public function buy_step_list()
    {
        $result = model('BossPlanStep')->getStepList($this->member_id, $this->lang);
        $this->output(10000, lang('lan_operation_success'), $result);
    }

    //老板计划激活列表
    public function active_step_list()
    {
        $result = model('BossPlanStep')->getActiveStepList($this->member_id, $this->lang);
        $this->output(10000, lang('lan_operation_success'), $result);
    }

    //激活收银台详情
    public function step_info()
    {
        $other_member_id = intval(input('member_id'));
        $step_votes = intval(input('step_votes'));

        $result = model('BossPlanStep')->step_check($step_votes);
        if (is_string($result)) $this->output(10001, $result);

        $cur_number = 0;
        $cur_votes = 0;

        if (empty($other_member_id)) $other_member_id = $this->member_id;
        $cur_info = Db::name('boss_plan_info')->field('votes,num')->where(['member_id' => $other_member_id])->find();
        if ($cur_info) {
            $cur_number = $cur_info['num'];
            $cur_votes = $cur_info['votes'];
        }

        $pay_number = $result['number'] * ($step_votes - $cur_votes);
        $boss_plan = Db::name('boss_plan_info')->where(['member_id' => $this->member_id])->find();
        $user_num = model('CurrencyUser')->getCurrencyUser($this->member_id, 8, 'num');

        $wallet = [
            'status' => $this->config['boss_plan_buy_xrp_status'],
            'wallet_min_percent' => 100,
            'wallet_num' => $user_num['num'],
        ];
        $xrpz = [
            'status' => $this->config['boss_plan_buy_xrpz_status'],
            'wallet_min_percent' => (100 - $this->config['boss_plan_buy_xrpz']), //钱包支付最低百分比
            'wallet_num' => $user_num['num'], //用户钱包数量
            'user_xrpz_num' => $boss_plan['xrpz_num'], //用户瑞波钻数量
            'max_xrpz_num' => 0, //最多瑞波钻数量
        ];
        $xrpz['max_xrpz_num'] = $pay_number * $this->config['boss_plan_buy_xrpz'] / 100;

        $xrpj = [
            'status' => $this->config['boss_plan_buy_xrpj_status'],
            'wallet_min_percent' => (100 - $this->config['boss_plan_buy_xrpj']), //钱包支付最低百分比
            'wallet_num' => $user_num['num'], //用户钱包数量
            'user_xrpj_num' => $boss_plan['xrpj_num'], //用户瑞波金数量
            'max_xrpj_num' => 0, //最多瑞波金数量
        ];
        $xrpj['max_xrpj_num'] = $pay_number * $this->config['boss_plan_buy_xrpj'] / 100;
        if ($xrpj['user_xrpj_num'] <= 0) {
            $xrpj['status'] = 0;
        }

        $return = [
            'info' => [
                'votes' => $step_votes, //当前票数
                'number' => $result['number'] * $step_votes, //当前数量
                'cur_votes' => $cur_votes,
                'pay_number' => $pay_number,
            ],
            'pay_choose' => [
                'wallet' => $wallet,
                'xrpz' => $xrpz,
                'xrpj' => $xrpj,
            ],
        ];
        $this->output(10000, lang('lan_operation_success'), $return);
    }

    //认购
    public function user_buy()
    {
        $step_votes = intval(input('step_votes'));
        $pwd = strval(input('pwd'));
        $xrp_num = floatval(input('xrp_num')); //XRP数量
        $xrpz_num = floatval(input('xrpz_num')); //瑞波钻数量
        $xrpj_num = floatval(input('xrpj_num')); //瑞波金数量

        $result = model('BossPlan')->user_buy($this->member_id, $step_votes, $pwd, $this->config, $xrp_num, $xrpz_num, $xrpj_num);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'));
    }

    //激活列表
    public function active_list()
    {
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $status = input('status', '');

        $result = model('BossPlan')->activation_list($this->member_id, $status, $page, $page_size);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'), $result);
    }

    //我的社员
    public function my_invite_list()
    {
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $phone = input('phone', '');

        $result = model('BossPlan')->my_invite_list($this->member_id, $phone, $page, $page_size);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_operation_success'), $result);
    }

    //社区长绑定
    public function leader_bind() {
        $phone = input('phone');
        $member_id = input('member_id');

        $pid_phone = input('pid_phone');
        $pid = input('pid');

        $member = model('BossPlan')->leader_bind($this->member_id,$phone,$member_id,$pid_phone,$pid);
        if (is_string($member)) $this->output(10001, $member);

        $this->output(10000, lang('lan_operation_success'));
    }

    /**
     * 根据用户ID和帐号搜索用户是否可以绑定
     * @param $id            用户id
     * @param $account       用户帐号(手机或者邮箱)
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/1/19 20:08
     */
    function searchByIDAndaccount()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_invit_pid_empty");
        $r['result'] = [];
        $member_id = input("post.id");
        $account = input("post.account");
        if (!empty($member_id) && !empty($account)) {
            $member = Db::name('member')->where(['member_id' => $member_id])->field("email,phone,pid")->find();
            if (!empty($member)) {
                if ($member['email'] == $account || $member['phone'] == $account) {
                    $bossPlan=Db::name('boss_plan')->where(['member_id' => $member_id])->find();
                    $user=model('BossPlan')->getMemberInfo($member_id);
                    $user['pid_phone'] = '';
                    $user['pid_member_id'] = '';
                    if(!empty($bossPlan)&&$bossPlan['status']==1){
                        $parent=model('BossPlan')->getMemberInfo($bossPlan['pid']);
                        $r['result']['user']=$user;
                        $r['result']['parent']=$parent;
                        $r['code']=SUCCESS;
                        $r['message']=lang("lan_data_success");
                    }elseif (!empty($bossPlan)&&$bossPlan['status']==3){
                        $r['message']=lang("lan_boss_plan_has_bind1");
                    }else{
                        //默认是注册时的上级ID
                        $pid = intval($member['pid']);
                        if($pid>0) {
                            $pidInfo = Db::name('member')->field('member_id,phone,email')->where(['member_id'=>$pid])->find();
                            if($pidInfo) {
                                $boss_plan_info = Db::name('boss_plan')->where(['member_id'=>$pid,'status'=>3])->find();
                                if($boss_plan_info) {
                                    if(empty($pidInfo['phone'])) $pidInfo['phone'] = $pidInfo['email'];
                                    $user['pid_phone'] = $pidInfo['phone'];
                                    $user['pid_member_id'] = $pidInfo['member_id'];
                                }
                            }
                        }

                        $r['code']=SUCCESS;
                        $r['message']=lang("lan_data_success");
                        $r['result']['user']=$user;
                    }

                } else {
                    $r['message'] = lang("lan_invit_pid_not_exists");
                }
            } else {
                $r['message'] = lang("lan_invit_pid_not_exists");
            }
        }
        $this->output_new($r);
    }

    /**
     * 查询用户是否已是老板计划里面
     * @param $id           用户ID
     * @param $account      用户帐号(手机或者邮箱)
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/1/19 21:16
     */
    function isInBossPlan(){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_invit_pid_empty");
        $r['result'] = [];
        $member_id = input("post.id");
        $account = input("post.account");
        if(!empty($member_id)&&!empty($account)){
            $user=model('BossPlan')->getMemberInfo($member_id);
            if(!empty($user)&&($user['phone']==$account||$user['email']==$account)){
                if($member_id!=$this->member_id){
                    $is_parent = Db::name('member_bind')->where(['member_id'=>$this->member_id,'child_id'=>$member_id])->find();
                    $is_parent2 = Db::name('member_bind')->where(['member_id'=>$member_id,'child_id'=>$this->member_id])->find();
                    if($is_parent || $is_parent2) {
                        //查询是否是老板计划里面的
                        $bossPlan= Db::name('boss_plan')->where(['member_id' => $member_id])->find();
                        if(!empty($bossPlan)){
                           $r['code']=SUCCESS;
                           $r['message']=lang("lan_data_success");
                           $r['result']=$user;
                        } else{
                            //没有进入老板计划
                            $r['message']=lang("lan_user_is_not_activated");
                        }
                    } else {
                        $r['message'] = lang("lan_boss_plan_not_exists");
                    }
                } else {
                    $r['code']=SUCCESS;
                    $r['message']=lang("lan_data_success");
                    $r['result']=$user;
                } 
            }else{
                $r['message']=lang("lan_invit_pid_not_exists");
            }

        }
        $this->output_new($r);
    }

}