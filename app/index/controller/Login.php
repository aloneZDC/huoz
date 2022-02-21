<?php
namespace app\index\controller;

use think\Db;
use think\Exception;

class Login extends Base
{
	protected $public_action = ['index','checkIp','checkLog','loginOut','is_login_code'];
	public function index(){
        if(!empty($this->member_id)) $this->redirect(url('user/safe'));
        
		return $this->fetch();
	}

	public function checkIp() {
//        $this->output(10001,lang('数据统计中，将于2019-08-01开放，敬请期待...'));


        $username = input('post.username', '', 'strval,trim,strtolower');
        if (empty($username)) $this->output(10001,lang('lan_Please_enter_the_correct'));

        $email = $username;
        $where = [];
        $login_type = 0;
        if (checkEmail($email)) {
            $where['email'] = $email;
            $login_type = 2;
        } else {
            $where['phone'] = $email;
            $login_type = 1;
        }
        $model = model('member');
        $userInfo = Db::name('member')->where($where)->find();
        if (!$userInfo) $this->output(10001,lang('lan_Account_does_not_exist'));//帐号不存在

        $this->output(10000,lang('lan_operation_success'));
	}

    //检测是否需要验证码登录
    public function is_login_code() {
        $NECaptchaValidate = input("validate");
        if (!$this->verifyCaptcha($NECaptchaValidate)) $this->output(10001, lang('lan_Picture_verification_refresh'));
        // if (!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));
        
        $this->doLogin(1);
    }

    public function checkLog(){ 
        $this->doLogin(2);
    }

	//登录
    public function doLogin($step){
        //$this->output(10001,lang('数据统计中，将于2019-08-01开放，敬请期待...'));
        if(!PC_OPEN) $this->output(10001,'PC端暂时关闭,请下载手机APP使用');

        $username = input('post.username', '', 'strval,trim,strtolower');
        $password = input('post.password', '', 'strval');
        if (empty($username)) $this->output(10001,lang('lan_Please_enter_the_correct'));
        if (empty($password)) $this->output(10001,lang('lan_login_please_enter_your_password'));

        $email = $username;
        $where = [];
        $login_type = 0;
        if (checkEmail($email)) {
            $where['email'] = $email;
            $login_type = 2;
        } else {
            $where['phone'] = $email;
            $login_type = 1;
        }
        $model = model('member');
        $userInfo = Db::name('member')->where($where)->find();
        if (!$userInfo) $this->output(10001,lang('lan_Account_does_not_exist'));//帐号不存在

        $pwd_error_max = intval($this->config['pwd_error_max']);
        if($pwd_error_max>0 && $userInfo['pwd_error']>=$this->config['pwd_error_max']) $this->output(10001,lang('lan_user_pwd_error_max'));//密码错误 

        $login_ip = get_client_ip();
        if(!$model->checkPassword($password,$userInfo['pwd'])) {
            model('member')->addLoginLog($userInfo['member_id'],'',$login_ip,'pc',0); //增加登录记录
            model('Member')->pwdErrorInc($userInfo['member_id']); //增加密码错误次数
            $this->output(10001,lang('lan_Password_error'));//密码错误 
        }

        if($userInfo['status']==2) $this->output(10001,lang('lan_The_account_is_locked_and_no_entry'));//帐号被锁定，禁止登陆
        if ($userInfo['is_lock']) $this->output(10001,lang('lan_The_account_is_locked_and_no_entry'));//帐号被锁定，禁止登陆

        //检测是否需要验证码
        // $isCode = model('Member')->checkIsCode($userInfo['member_id'],$uuid);
        $isCode = false;
        if($isCode) {
            if($step==1){
                //自动发送验证码
                if($login_type==1) {
                    $send_result = model('Sender')->send_phone($userInfo['country_code'],$email,'login');
                } else {
                    $send_result = model('Sender')->send_email($email,'login');
                }
                if(is_string($send_result)) $this->output(10001,$send_result);

                $this->output(11000,'please input code',['type'=>$login_type]);
            } else {
                $phone_code = input('phone_code','','strval');
                if(empty($phone_code)) {
                    if($login_type==1) {
                        $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));
                    } else {
                        $this->output(10001, lang("lan_validation_incorrect"));
                    }
                }
                $senderLog = model('Sender')->check_log($login_type,$email,'login',$phone_code);
                if(is_string($senderLog)) $this->output(10001,$senderLog);
            }  
        }
        unset($userInfo['pwd']);

        //如果当前操作Ip和上次不同更新登录IP以及登录时间
        $data['login_ip'] = $login_ip;
        $data['login_time'] = time();
        $data['login_type'] = $login_type;
        $update = $model->where(['member_id' => $userInfo['member_id']])->update($data);
        if ($update === false) $this->output(10001,lang('lan_network_busy_try_again'));

        $head = empty($userInfo['head']) ? model('member')->default_head : $userInfo['head'];

        $verify_state = Db::name('verify_file')->where(['member_id' => $this->member_id])->value('verify_state');
        if(!$verify_state && !is_numeric($verify_state)) $verify_state = -1;

        model('member')->pwdErrorReset($userInfo['member_id']); //重置密码错误次数
        model('member')->addLoginLog($userInfo['member_id'],'',$data['login_ip'],'pc'); //增加登录记录

        //REDIS缓存登录
        $token_data = [
            'platform' => 'pc', //平台类型
            'uuid' => $this->uuid, //UUID
            'member_id' => $userInfo['member_id'], //用户ID
        ];
        //生成自动登录签名
        $rsa= new \encrypt\Rsa();
        $token_data = $rsa->joinMapValue($token_data);
        $userInfo['token'] = base64_encode(md5($token_data.time()). "|" . $userInfo['member_id']);

        //缓存写入Redis
        $token = cache('pc_auto_login_'.$userInfo['member_id'],$userInfo['token'],$this->login_keep_time);

        session('USER_KEY_ID',$userInfo['member_id']);
        session('USER_KEY_TOKEN',$userInfo['token']);

        if(isset($senderLog)) model('Sender')->hasUsed($senderLog['id']);

        $this->output(10000,lang('lan_operation_success'));
    }

    public function loginOut(){
        session('USER_KEY_ID',null);
        session('USER_KEY_TOKEN',null);
        $this->redirect('index/index');
    }
}