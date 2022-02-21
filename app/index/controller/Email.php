<?php
/**
 *发送邮件
 */
namespace app\index\controller;
use think\Db;
use think\Exception;

class Email extends Base
{
    protected $public_action = ['code','check'];
    protected $is_method_filter = true;

    //發送驗證碼
    public function code() {
        //图片验证验证码
        $NECaptchaValidate=input("validate");
        if(!$this->verifyCaptcha($NECaptchaValidate))  mobileAjaxReturn(['status' => 0, 'msg' =>lang('lan_Picture_verification_refresh')]);
        $email = input('post.email');
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) mobileAjaxReturn(['status' => 0, 'msg' =>lang('lan_emial_format_incorrect')]);
        $type = input('type','','strval');

        $result = model('Sender')->send_email($email,$type);
        if(is_string($result))mobileAjaxReturn(['status' => 0, 'msg' =>$result]);
        mobileAjaxReturn(['status' => 1, 'msg' =>lang('lan_reg_mailbox_sent')]);
    }

    //检测验证码
    public function check() {
        $email = input('post.email');
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email))  mobileAjaxReturn(['status' => 0, 'msg' =>lang('lan_emial_format_incorrect')]);

        $email_code = input('post.email_code');
        if(empty($email_code)) mobileAjaxReturn(['status' => 0, 'msg' =>lang('lan_validation_incorrect')]);

        $type = input('type','','strval');
        $result = model('Sender')->check_log(2,$email,$type,$email_code);
        if(is_string($result)) mobileAjaxReturn(['status' => 0, 'msg' =>$result]);
        mobileAjaxReturn(['status' => 1, 'msg' =>lang('lan_operation_success')]);

    }
}