<?php
namespace app\api\controller;
use think\Db;
use think\Exception;
use think\Log;

class Sms extends Base
{
    protected $public_action = ['code','check','up','status'];
    protected $is_method_filter = false;

    //發送驗證碼
    public function code() {
        //图片验证验证码
        $mark=input("post.validate");
        $img_code=input("post.img_code");
//        if(!verify_code($mark,$img_code)){
//           return $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }
//        $NECaptchaValidate=input("validate");
        // if(!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $phone = input("post.phone", '', 'strval');
        $country_code = intval(input('post.country_code'));
        $type = input("post.type","","strval");

        $email = input("post.email", '', 'strval');
        if(!empty($email)) {
            $this->email_send($email,$type);exit;
        }
        if($this->member_id&&(empty($phone)&&empty($email))){
            //判断是手机还是邮箱登录，哪个登录就发哪个验证码
            $member=Db::name("member")->field("login_type,email,phone,country_code,send_type")->where(['member_id'=>$this->member_id,'is_lock'=>0])->find();
            if(!empty($member)){
                $send_type = $member['send_type'];
                if(empty($send_type)) {
                    if(!empty($member['phone'])) {
                        $send_type = 1;
                    } elseif(!empty($member['email'])) {
                        $send_type = 2;
                    }
                }
                if(!empty($member['phone']) && $type=='modifyphone') $send_type = 1;
                if(!empty($member['email']) && $type=='modifyemail') $send_type = 2;
                if($send_type==1) {
                    if ($type != "bindphone") {
                        $phone = $member['phone'];
                        $country_code = $member['country_code'];
                    }
                    $result = model('Sender')->send_phone($country_code,$phone,$type);
                    $account = $phone;
                    if(is_string($result)) $this->output(10001,$result);
                } elseif($send_type==2) {
                    $this->email_send($member['email'],$type);exit;
                }
            }

        }else{
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
            $account = $phone;
            if(is_string($result)) $this->output(10001,$result);
        }
        $r = [];
        if ('tcoin' == $type and isset($account)) {
            $r = ['email' => hideStar($account)];
        }

        $this->output(10000,lang('lan_user_send_success'), $r);
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

        if(empty($phone)) $this->output(10001, lang('lan_please_enter_the_correct_mobile_number'));

        $phone_code = input('phone_code');
        if(empty($phone_code)) $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));
        $type = input("type","","strval");

        $result = model('Sender')->check_log(1,$phone,$type,$phone_code);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //已登录用户发送手机或邮箱
    public function auto_send() {
        //图片验证验证码
        $mark=input("post.validate");
        $img_code=input("post.img_code");
//        if(!verify_code($mark,$img_code)){
//            return $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }

        $type = input('type','','strval');
        $result = model('Sender')->auto_send($this->member_id,$type);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_user_send_success'));
    }

    public function auto_check() {
        $type = input('type','','strval');
        $code = input('code','','strval');
        $result = model('Sender')->auto_check($this->member_id,$type,$code);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //兼容接口错误
    public function email_send($email,$type) {
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) $this->output(10001,lang('lan_emial_format_incorrect'));

        $result = model('Sender')->send_email($email,$type);
        if(is_string($result)) $this->output(10001,$result);
        $r = [];
        if ('tcoin' == $type) {
            $r = ['email' => hideStar($email)];
        }

        $this->output(10000,lang('lan_reg_mailbox_sent'), $r);
    }

    //短信平台商 上行
    public function up() {
        Log::write(json_encode($_POST));
        return 'success';
    }

    //短信平台商 状态上报
    public function status() {
        $data = input('post.');
        if(is_array($data)) {
            foreach ($data as $value) {
                if(isset($value['taskid'])){
                    Db::name('phone_task_history')->where(['third_id'=>strval($value['taskid'])])->setField('third_result',json_encode($value));
                }
            }
        }
        return 'success';
    }
}
