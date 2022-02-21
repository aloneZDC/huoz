<?php


namespace app\h5\controller;

use app\common\controller\Common;
use think\Db;
use think\captcha\Captcha;
use caption\UtilsCaption;
use think\Request;

class Sms extends Base
{
    private $code_secret="IO_EXCHANGE";
    protected $mark;

    //發送驗證碼
    public function code() {
        //图片验证验证码
//        $img_code = input("validate");
//        $mark = cache('mark_' . $this->member_id);
//        if(!verify_code($mark,$img_code)){
//            $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }

        $phone = input("post.phone", '', 'strval');
        $email = input("post.email", '', 'strval');
        $country_code = intval(input('post.country_code', '86'));
        $type = input("post.type","tcoin","strval");

        $user = Db::name('member')->field('phone,country_code,email')->where(['member_id' => $this->member_id])->find();
        if (empty($email) || !empty($user['email'])) {
            $email = $user['email'];
        }
        if (empty($phone) || !empty($user['phone'])) {
            $phone = $user['phone'];
        }

        if(!empty($email)) {
            $this->email_send($email,$type);exit;
        }

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
            $result = model('Sender')->send_phone($country_code,$phone,$type);
        }

        if(is_string($result)) $this->output(ERROR1, $result);
        $this->output(SUCCESS, lang('lan_user_send_success'));
    }

    /**
     * 发邮件
     * @param $email
     * @param $type
     * Create by: Red
     * Date: 2019/9/6 11:48
     */
    private function email_send($email,$type) {
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) $this->output(10001,lang('lan_emial_format_incorrect'));

        $result = model('Sender')->send_email($email,$type);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_reg_mailbox_sent'));
    }

    /**
     * 验证码
     * @return Response
     */
//    public function captcha()
//    {
//        $config =  [
//            'fontSize' => 13,              // 验证码字体大小(px)
//            'useCurve' => true,            // 是否画混淆曲线
//            'useNoise' => false,            // 是否添加杂点
//            'imageH' => 35,               // 验证码图片高度
//            'imageW' => 80,               // 验证码图片宽度
//            'length' => 4,               // 验证码位数
//            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
//        ];
//        return (new Captcha($config))->entry();
//    }

    /**
     * 产生的随机字符串并和缓存里的不重复
     * @return string|
     * Create by: Red
     * Date: 2019/8/30 9:21
     */
    private function create_mrak()
    {
        $str = getNonceStr();
        $mark = cache("verificationcode_" . $str);
        if (!empty($mark)) {
            return $this->create_mrak();
        }

        return $str;
    }

    /**
     * 产生的随机字符串和现有缓存不重复,并保存到缓存里
     * @return string
     */
    public function create_code()
    {
        $str=$this->create_mrak();
        $code = randNum(4);
        cache("verificationcode_" . $str, $code, 600);
        cache("mark_" . $this->member_id, $str, 600);
        // $result['code'] = $code;
        $r['code']=SUCCESS;
        $r['message']=lang("lan_user_request_successful");
        $r['result'] = ['mark'=>$str,'value'=>md5($this->code_secret.$code)];
        return ajaxReturn($r);
    }

    /**
     * 显示图片验证码
     * @return string|void
     * Create by: Red
     * Date: 2019/8/30 9:34
     */
    public function show_img($mark1=null)
    {
        $mark = input("mark")?input("mark"):$mark1;
        if (!empty($mark)) {
            $v=cache("verificationcode_".$mark);
            if (!empty($v)) {
                //验证码图片存在的,直接从文件里拿
                $field=file_exists(WEB_PATH."/code_img/".$v.".png");//判断图片验证码是否存在
                if($field){
                    $fp=fopen(WEB_PATH."/code_img/".$v.".png", "rb"); //二进制方式打开文件
                    header("Content-type: image/png");
                    fpassthru($fp); // 输出至浏览器
                    die();
                }else{
                    //验证码图片不存在的,创新并保存起来
                    $rsi = new UtilsCaption();
                    $rsi->TFontSize = array(35, 35);
                    $rsi->Width = 100;
                    $rsi->Height = 50;
                    $rsi->EnterCode =$v;
                    $rsi->Draw();
                    die();
                    // return $this->show_img($mark);
                }
            }
        }
        return "";
    }
}