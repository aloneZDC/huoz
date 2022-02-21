<?php
namespace app\index\controller;

use app\common\model\Config;
use think\captcha\Captcha;
use Think\Db;
use think\Response;

class Index extends Base
{
	protected $public_action = ['index','set_langues','new_guide','guide','help', 'captcha','code','checkphone','email_code','checkemail','check',
        'email_check','findpass','resetpass'];

    public function index()
    {
        die('welcome hzyc');
        $lang = $this->getLang();
        if(empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,'.$lang.'_title as title,'.$lang.'_content as content,art_pic,add_time';
        }
        //新闻资讯
        $article=Db::name('article')->field($field)->where(['position_id'=>129])->order('add_time desc')->limit(30)->select();
        $new_arr=[];
        if(!empty($article)){
            foreach ($article as $key => $value) {
                $value['title'] = strip_tags(html_entity_decode($value['title']));
                $lenth = strlen($value['content']);
                if ($lenth >= 30) {
                    $value['content'] = mb_substr(strip_tags(html_entity_decode($value['content'])),0,100);
                } else {
                    $value['content'] = trim(strip_tags(html_entity_decode($value['content'])));
                }
                $value['add_time'] = date('m/d H:i',$value['add_time']);
                if($key<=9){
                    $new_arr[0][]= $value;
                }elseif($key> 9&&$key<= 19){
                    $new_arr[1][]= $value;
                }elseif($key>19&&$key<= 29){
                    $new_arr[2][]= $value;
                }

            }
        }
        if(empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,'.$lang.'_title as title,add_time';
        }
        //新闻公告
        $ad_list=Db::name('article')->field($field)->where(['position_id'=>1])->order('add_time desc')->select();
        $ad_arr=[];
        if(!empty($ad_list)){
             $i=1;
             $j=0;
            foreach ($ad_list as $val){
                 $ad_arr[$j][]=$val;
                 if( $i%3==0){
                   $j++;
                 }
                $i++;
            }
        }
        return $this->fetch('index/index',['article'=>$new_arr,'ad_arr'=>$ad_arr]);
    }


    public function set_langues(){
		$lang = input('lang');
		if(!empty($lang)) cookie('think_language',$lang);
		$this->output(10000,'');
    }

    /**
     * 二级分类下的文章
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/8/31 9:12
     */
    function new_guide(){
        $id=input("id");
        $page=input("page",1);
        $page=$page>0?$page:1;
        $list=null;
        $info=null;
        $one=null;
        $all_page=1;
        $lang = cookie('think_language');//默认null则为简体中文
        if(is_numeric($id)){
            $info=Db::name("article_category")->where(['id'=>$id])->find();//当前分类
            if(!empty($info)){
                $one=Db::name("article_category")->where(['id'=>$info['parent_id']])->find();//上级分类
                if($lang=='en-us'){
                    $info['name']=$info['name_en'];
                    $one['name']=$one['name_en'];
                }
            }
            $list=Db::name("article")->where(['position_id'=>$id])->order("sort asc")->page($page,10)->select();//当前分类下的文章
            if(!empty($list)){
                foreach ($list as &$value){
                    if($lang=="en-us"){
                        $value['tc_title']=$value['en_title'];
                    }
                }
            }
            $coun=Db::name("article")->where(['position_id'=>$id])->count("article_id");//当前分类下的文章总数
            $all_page=ceil($coun/10);

        }
        return $this->fetch("",['list'=>$list,'info'=>$info,'one'=>$one,'all_page'=>$all_page,'page'=>$page]);
    }
    function guide(){
        $list=null;
        $id=input("id");
        if(is_numeric($id)){
            $find=Db::name("article_category")->where(['id'=>$id])->find();
            if(!empty($find)){
                $list=Db::name("article_category")->where(['parent_id'=>$find['id']])->select();
            }
        }
        return $this->fetch();
    }

    /**
     * 帮助中心二级分类页面
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/8/30 16:35
     */
    function help(){
        $find=Db::name("article_category")->where(['keywords'=>'help'])->find();
       $list=null;
        $lang = cookie('think_language');//默认null则为简体中文
        if(!empty($find)){
            //帮助中心二级分类
            $list=Db::name("article_category")->where(['parent_id'=>$find['id']])->order("sort asc")->select();
            if(!empty($list)){
                foreach ($list as &$value){
                    if($lang=='en-us'){
                    $value['name']=$value['name_en'];
                    }
                }

            }
        }
        $problem_article=Db::name("article")->alias("a")->where(['ac.keywords'=>"problem"])->field("a.tc_title,a.article_id,a.en_title")
            ->join(config("database.prefix")."article_category ac","ac.id=a.position_id","LEFT")->page(1,6)->order("id desc")->select();
        if(!empty($problem_article)){
            foreach ($problem_article as &$value){
                if($lang=="en-us"){
                    $value['tc_title']=$value['en_title'];
                }
            }
        }
        $this->assign("list",$list);
        $this->assign("art_list",$problem_article);
        return $this->fetch();
    }


    /**
     * 验证码
     * @return Response
     */
    public function captcha()
    {
        $config =  [
            'fontSize' => 13,              // 验证码字体大小(px)
            'useCurve' => true,            // 是否画混淆曲线
            'useNoise' => false,            // 是否添加杂点
            'imageH' => 35,               // 验证码图片高度
            'imageW' => 80,               // 验证码图片宽度
            'length' => 3,               // 验证码位数
            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
        ];
        return (new Captcha($config))->entry();
    }

    /**
     * 发送验证码
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/6 11:42
     */
    public function code() {
        //图片验证验证码
//        $mark=input("post.validate");
//        if (!$this->verifyCaptcha($mark)){
//            return $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }

        $r = ['code' => ERROR1, 'message' => lang('lan_operation_success'), 'result' => null];
        $phone = input("post.phone", '', 'strval');
        $country_code = intval(input('post.country_code'));
        $type = input("post.type","","strval");

        $email = input("post.email", '', 'strval');
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
        }

        $result = model('Sender')->send_phone($country_code,$phone,$type);
        if(is_string($result)) {
            $r['message'] = $result;
            mobileAjaxReturn($r);
        }
        mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('lan_user_send_success'), 'result' => null]);
    }

    /**
     * 发邮件
     * @param $email
     * @param $type
     * Create by: Red
     * Date: 2019/9/6 11:48
     */
    private function email_send($email,$type) {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_success'), 'result' => null];
        if((empty($email) && $this->checkLogin()) ) {
            $email_user = Db::name('member')->where(['member_id'=>$this->member_id])->value('email');
            if($email_user) $email = $email_user;
        }

        if(empty($email) || !checkEmail($email)) $this->output(10001,lang('lan_emial_format_incorrect'));

        $result = model('Sender')->send_email($email,$type);
        if(is_string($result)) {
            $r['message'] = $result;
            mobileAjaxReturn($r);
        }
        mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('lan_reg_mailbox_sent'), 'result' => null]);
    }

    //检测手机号是否被注册
    public function checkphone()
    {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_success'), 'result' => null];
        $phone = input("post.phone", '', 'strval,trim');
        if (empty($phone)) {
            $r['message'] = lang('lan_please_enter_the_correct_mobile_number');
            mobileAjaxReturn($r);
        }

        $country_code = intval(input('post.country_code'));
        if ($country_code == 86 && !checkMobile($phone)) {
            $r['message'] = lang('lan_please_enter_the_correct_mobile_number');
            mobileAjaxReturn($r);
        }

        $reg_phone_max_num = Config::get_value('reg_phone_max_num', 0);
        if ($reg_phone_max_num > 0) {
            $user_count = Db::name('member')->field('member_id')->where(['phone' => $phone])->count();
            if ($user_count >= $reg_phone_max_num) {
                $r['message'] = lang('lan_phone_reg_num_max');
                mobileAjaxReturn($r);
            }
        }
//        $r = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
//        if ($r) $this->output(10001,lang('lan_reg_phone_being'));

//        $this->output(10000,lang('lan_operation_success'));
        mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('lan_operation_success'), 'result' => null]);
    }

    //發送驗證碼
    public function email_code() {
        //图片验证验证码
//        $NECaptchaValidate=input("validate");
//        if (!$this->verifyCaptcha($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

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
    //检测邮箱是否被注册
    public function checkemail() {
        $email = input("post.email", '', 'strval,trim');
        if (empty($email) || !checkEmail($email)) $this->output(10001, lang('lan_emial_format_incorrect'));

        $reg_email_max_num = Config::get_value('reg_email_max_num',0);
        if($reg_email_max_num > 0) {
            $user_count = Db::name('member')->field('member_id')->where(['email' => $email])->count();
            if ($user_count >= $reg_email_max_num) $this->output(10001,lang('lan_email_reg_num_max'));
        }
//        $result = Db::name('member')->field('member_id')->where(['email' => $email])->find();
//        if ($result) $this->output(10001,lang('lan_reg_mailbox_already_exists'));

        $this->output(10000,lang('lan_operation_success'));
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
    //检测验证码
    public function email_check() {
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
    //找回密码
    public function findpass() {
        $phone = input('post.phone');
        if(empty($phone)) $this->output(10106, lang('lan_login_please_enter_your_mobile_number'));
        $phone_code = input('post.phone_code');

        $where = [];
        $send_type = 1;
        if(checkEmail($phone)){
            $where['email'] = $phone;
            $send_type = 2;
        } else {
            $where['phone'] = $phone;
            $send_type = 1;
        }

        $info = Db::name('Member')->where($where)->find();
        if(!$info) $this->output(10001, lang('lan_Account_does_not_exist'));

        $senderLog = model('Sender')->check_log($send_type,$phone,'findpwd',$phone_code);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $token = strtoupper(md5($phone) . md5(time()));
        $id = Db::name('findpwd')->insertGetID([
            'member_id' => $info['member_id'],
            'token' => $token,
            'add_time' => time(),
        ]);
        if($id){
            model('Sender')->hasUsed($senderLog['id']);
            $this->output(10000, lang('lan_operation_success'),['token'=>$token]);
        } else {
            $this->output(10106, lang('lan_network_busy_try_again'));
        }
    }

    //重置密码,根据token
    public function resetpass() {
        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');

        $model = model('member');
        $flag = $model->checkPwdModif($pwd,$repwd);
        if(is_string($flag)) $this->output(10103, $flag);

        $phone = input('phone');

        $where = [];
        if(checkEmail($phone)){
            $where['email'] = $phone;
        } else {
            $where['phone'] = $phone;
        }
        $info = Db::name('Member')->where($where)->find();
        if(!$info) $this->output(10001, lang('lan_Account_does_not_exist'));

        $new_password = $model->password($pwd);
        if($info['pwdtrade']==$new_password) $this->output(10001, lang('lan_user_Transaction_password_login'));

        $token = input('token');
        if(empty($token)) $this->output(10106, lang('The_has_expired'));

        $token_info = Db::name('findpwd')->where(['member_id'=>$info['member_id'],'token'=>$token])->find();

        $stop_time = time() - 24 * 60 * 60;
        if(!$token_info || $token_info['add_time']<$stop_time) $this->output(10106, lang('The_has_expired'));

        $result = Db::name('member')->where(['member_id'=>$info['member_id']])->update(['pwd'=>$new_password,'pwd_error'=>0,'pwdtrade_error'=>0]);
        if($result!==false) {
            Db::name('findpwd')->where(['id'=>$token_info['id']])->delete();
            $this->output(10000, lang('lan_operation_success'));
        } else {
            $this->output(10106, lang('lan_network_busy_try_again'));
        }
    }
}
