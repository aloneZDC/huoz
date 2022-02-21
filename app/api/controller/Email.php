<?php
/**
 *发送邮件
 */
namespace app\api\controller;
use think\Db;
use think\Exception;

class Email extends Base
{
    protected $public_action = ['code','check'];
    protected $is_method_filter = true;

    //發送驗證碼
    public function code() {
        //图片验证验证码
//        $NECaptchaValidate=input("validate");
//         if (!$this->verifyCaptcha($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));
        // if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $mark=input("post.validate");
        $img_code=input("post.img_code");
        if(!verify_code($mark,$img_code)){
            return $this->output(10001,lang('lan_Picture_verification_refresh'));
        }

        $email = input('post.email');
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) $this->output(10001,lang('lan_emial_format_incorrect'));
        $type = input('type','','strval');

        $result = model('Sender')->send_email($email,$type);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_reg_mailbox_sent'));
    }

    //检测验证码
    public function check() {
        $email = input('post.email');
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) $this->output(10001,lang('lan_emial_format_incorrect'));

        $email_code = input('post.email_code');
        if(empty($email_code)) $this->output(10001,lang('lan_validation_incorrect'));

        $type = input('type','','strval');
        $result = model('Sender')->check_log(2,$email,$type,$email_code);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }
}