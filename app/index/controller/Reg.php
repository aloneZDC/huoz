<?php
namespace app\index\controller;

use Think\Db;

class Reg extends Base
{
	protected $public_action = ['index','findpass','resetpass'];
	public function index(){
		if(!empty($this->member_id)) $this->redirect(url('user/safe'));

        $pid = input('pid','','strval');
        if($this->lang=="tc")$this->lang="cn";
        $countries = Db::name("countries_code")->field($this->lang.'_name as name,phone_code as countrycode')->where('status=1')->order('sort asc')->select();
        $this->assign(['countries'=>$countries,'pid'=>$pid]);
		return $this->fetch();
	}
    /**
     * ajax验证邮箱
     * @param string $email 规定传参数的结构
     *
     */
    public function ajaxCheckEmail($email){
        $email = urldecode(trim($email));
        $data = array();
        if(!checkEmail($email)){
            $data['status'] = 0;
            $data['msg'] = lang('lan_emial_format_incorrect');
        }else{
            $M_member =Db::name('member');
            $where['email']  = $email;
            $r = $M_member->where($where)->find();
            if($r){
                $data['status'] = 2;
                $data['msg'] = lang('lan_reg_mailbox_already_exists');
            }else{
                $data['status'] = 1;
                $data['msg'] = "";
            }
        }
        $this->mobileAjaxReturn($data);
    }

    /**
     * ajax验证手机
     * @param string $email 规定传参数的结构
     *
     */
    public function ajaxCheckPhone($email){
        $email = urldecode(trim($email));
        $data = array();

        $country_code = input('country_code',0,'intval');
        if(empty($email)) $this->mobileAjaxReturn(['status'=>0,'msg'=>lang('lan_please_enter_the_correct_mobile_number')]);
        if($country_code==86 && !checkMobile($email)) $this->mobileAjaxReturn(['status'=>0,'msg'=>lang("lan_please_enter_the_correct_mobile_number")]);

        $r = Db::name('Member')->where(['phone'=>$email])->find();
        if($r) $this->mobileAjaxReturn(['status'=>2,'msg'=>lang('lan_reg_phone_being')]);

        $this->mobileAjaxReturn(['status'=>1,'msg'=>'']);
    }

    //找回密码
    public function findpass() {
        if(!empty($this->member_id)) $this->redirect(url('user/safe'));

        return $this->fetch();
    }

    //重置密码,根据token
    public function resetpass() {
        if(!empty($this->member_id)) $this->redirect(url('user/safe'));
        
        $token = input('token');
        $this->assign('token',$token);
        return $this->fetch();
    }
}