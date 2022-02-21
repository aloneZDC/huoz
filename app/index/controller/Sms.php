<?php
namespace app\index\controller;
use think\Db;
use think\Exception;

class Sms extends Base
{
    protected $public_action = ['code','check','auto_index_send'];
    protected $is_method_filter = true;

    //發送驗證碼
    public function code() {
        
        //图片验证验证码
        $NECaptchaValidate=input("validate");
        if(!$this->verifyCaptcha($NECaptchaValidate)) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_Picture_verification_refresh')]);
        $phone = input("post.phone", '', 'strval');
        $country_code = intval(input('post.country_code'));
        $type = input("post.type","","strval");

        if( (empty($phone_code) && !empty($phone)) || (empty($phone) && $this->checkLogin()) ) {
            $where = [];
            if(empty($phone_code) && !empty($phone)) {
                $where['phone'] = $phone;
            }
            if(empty($phone) && $this->checkLogin()) {
                $where['member_id'] = $this->member_id;
            }

            $phone_user = Db::name('member')->field('phone,country_code')->where($where)->find();
            if($phone_user) {
                $phone = $phone_user['phone'];
                $country_code = $phone_user['country_code'];
            }
        }

        $result = model('Sender')->send_phone($country_code,$phone,$type);
        if(is_string($result)) mobileAjaxReturn(['status' => 0, 'info' =>$result]);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_user_send_success')]);
    }

    //检测验证码
    public function check() {
        $phone = input('phone');
        if(empty($phone) && $this->checkLogin()) {
            $phone_user = Db::name('member')->field('phone,country_code')->where(['member_id'=>$this->member_id])->find();
            if($phone_user) {
                $phone = $phone_user['phone'];
            }
        }

        if(empty($phone)) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_please_enter_the_correct_mobile_number')]);

        $phone_code = input('phone_code');
        if(empty($phone_code)) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_The_phone_verification_code_can_not_be_empty')]);
        $type = input("type","","strval");

        $result = model('Sender')->check_log(1,$phone,$type,$phone_code);
        if(is_string($result))  mobileAjaxReturn(['status' => 0, 'info' =>$result]);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);

    }

    public function auto_index_send() {
        //图片验证验证码
        $NECaptchaValidate=input("validate");
        if (!$this->verifyCaptcha($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));
        // if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $type = input('type','','strval');
        $phone = input("post.phone", '', 'strval');
        if(empty($phone)) $this->output(10001, lang('lan_Account_does_not_exist'));

        $where = [];
        $send_type = 1;
        if(checkEmail($phone)) {
            $where['email'] = $phone;
            $send_type = 2;
        } else {
            $where['phone'] = $phone;
            $send_type = 1;
        }
        $phone_user = Db::name('member')->field('phone,country_code,email')->where($where)->find();
        if(!$phone_user) $this->output(10001, lang('lan_Account_does_not_exist'));

        if($send_type==1) {
            $result = model('Sender')->send_phone($phone_user['country_code'],$phone_user['phone'],$type);
        } else {
            $result = model('Sender')->send_email($phone_user['email'],$type);
        }

        if(is_string($result)) $this->output(10001, $result);
        $this->output(10000, lang('lan_user_send_success'));
    }
    public function auto_index_check() {
        $type = input('type','','strval');
        $phone = input("post.phone", '', 'strval');
        $code = input('code','','strval');
        if(empty($phone)) $this->output(10001, lang('lan_Account_does_not_exist'));

        $where = [];
        $send_type = 1;
        if(checkEmail($phone)) {
            $where['email'] = $phone;
            $send_type = 2;
        } else {
            $where['phone'] = $phone;
            $send_type = 1;
        }

        $phone_user = Db::name('member')->field('phone,country_code,email')->where($where)->find();
        if(!$phone_user) $this->output(10001, lang('lan_Account_does_not_exist'));

        $result =  model('Sender')->check_log($send_type,$phone,$type,$code);
        if(is_string($result)) $this->output(10001, $result);
        
        $this->output(10000, lang('lan_operation_success'));
    }

    //已登录用户发送手机或邮箱
    public function auto_send() {
        //图片验证验证码
        $NECaptchaValidate=input("validate");
        if (!$this->verifyCaptcha($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));
        // if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $type = input('type','','strval');
        $result = model('Sender')->auto_send($this->member_id,$type);
        if(is_string($result)) $this->output(10001,$result);
        $member=model('member')->where(['member_id'=>$this->member_id])->field("phone,email")->find();
        $msg=lang('lan_user_send_success');
        if(!empty($member['phone'])){
            $msg=lang("lan_mobile_phone_verification_code").$msg;
        }else{
            $msg=lang("lan_email_code").$msg;
        }
        $this->output(10000,$msg);
    }

    public function auto_check() {
        $type = input('type','','strval');
        $code = input('code','','strval');
        $result = model('Sender')->auto_check($this->member_id,$type,$code);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }
}