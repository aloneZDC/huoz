<?php
namespace app\mobile\controller;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\EmailDomain;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoKeepLog;
use app\common\model\Member;
use app\common\model\MemberBindTask;
use think\captcha\Captcha;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Response;

class Reg extends Base{
	public function index(){
		return $this->fetch();
	}

	public function mobile(){
//	    exit('Wellcome');
        $invit_code = input('invit_code');
        $countries_field="cn";
        if($this->lang=="en")$countries_field="en";
        $countries = Db::name("countries_code")->field($countries_field.'_name as name,phone_code as countrycode')->where('status=1')->order('sort asc')->select();

//        $hongbao_config = HongbaoConfig::get_key_value();
//        $reg_award_currency = isset($hongbao_config['reg_award_currency']) ? $hongbao_config['reg_award_currency']:'';
//        $reg_award_num = isset($hongbao_config['reg_award_num']) ? $hongbao_config['reg_award_num']:'';

        $this->assign(['countries'=>$countries,'pid'=>$invit_code,'down_url'=>Version_ios_DowUrl,
//            'reg_award_currency'=>$reg_award_currency,'reg_award_num'=>$reg_award_num
        ]);
		return $this->fetch('reg/mobile');
	}

	public function addReg() {
//        return $this->output(10001, lang('lan_close'), null);
		$this->method_filter('post');

        $type = input('type','','strval');
        if($type=='email') {
            $this->emailAddReg();
        } elseif($type=='phone') {
        	$this->phoneAddReg();
        } else {
        	$this->output(10001,lang('lan_Illegal_operation'));
        }
	}

	//手机注册 和 Api接口代码保持一致
	private function phoneAddReg()
    {
//        return $this->output(10001, lang('lan_close'), null);
        $country_code = intval(input('post.country_code'));
        if(empty($country_code)) $this->output(10001, lang("lan_No_incoming_country_code"));    //没有传入国家编码

        $country = Db::name('countries_code')->where(['phone_code'=>$country_code,'status'=>1])->find();
        if(empty($country)) $this->output(10001, lang("lan_No_incoming_country_code"));    //没有传入国家编码

        $phone = input("post.phone", '', 'strval,trim');
        if(empty($phone)) $this->output(10005, lang('lan_please_enter_the_correct_mobile_number'));


        $username = input('post.username', '', 'strval,trim');
        if (empty($username)) {
            $username = $phone;
        }
        //if (empty($username) || !checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));

        $model = model('member');
//        $r = $model->field('member_id')->where(['phone'=>$phone])->find();
//        if ($r) $this->output(10008, lang('lan_reg_phone_being'));

        $r = $model->field('member_id')->where(['ename' => $username])->find();
        if ($r) $this->output(10008, lang("lan_reg_username_already_exists"));

        $checkRegMax = model('Member')->checkRegMax('phone', $phone);
        if(is_string($checkRegMax)) $this->output(10009, $checkRegMax);
//        $regMax = Config::get_value('reg_phone_max_num', 10);
//        $count = $model->where(['phone'=>$phone])->count();
//        if ($count >= $regMax) $this->output(10009, lang('lan_phone_reg_num_max'));

        $phone_code = input("post.phone_code", '', 'strval');
        if(empty($phone_code)) $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));

        $senderLog = model('Sender')->check_log(1,$phone,'register',$phone_code);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');

        $pwdtrade = input('post.pwdtrade', 'strval');
        $repwdtrade = input('post.repwdtrade', '', 'strval');
        $pid = input('post.pid', '', 'strval');//2019-10-23 邀请码,不是用户id
       if(!empty($pid)){
           $find_member=Db::name("member")->field("member_id,invit_code")->where(['invit_code'=>$pid])->find();
           if(empty($find_member)){
               return $this->output(ERROR1,lang('lan_invitation_code_does_not_exist'));
           }
       }

        $flag = $model->checkReg($pwd,$repwd,$pwdtrade,$repwdtrade,$pid,true); //邀约注册不需要填写交易密码
        if(is_string($flag)) $this->output(10001,$flag);

        $data = [
            'ename' => $username,
            'country_code' => $country_code,
            'phone' => $phone,
            'head' => '',
            'pwd' => $pwd,
            'pwdtrade' => $pwdtrade,
            'pid' => $pid,
            'ip' => get_client_ip_extend(),
            'reg_time' => time(),
            'status' => 1,
            'send_type' => 1,
        ];
//        $flag = $model->addReg(1,$data,$this->config);
        $flag = $model->addReg(3,$data,$this->config);
        if(is_string($flag)) $this->output(10001,$flag);

        $this->is_reg_login($flag['log_id']);

        //验证码设置为已使用
        model('Sender')->hasUsed($senderLog['id']);
//        $this->output(10000, lang('lan_operation_success'),HongbaoKeepLog::reward_config());
        $this->output(10000, lang('lan_operation_success'));
    }

    //郵箱注册
    private function emailAddReg(){
//        return $this->output(10001, lang('lan_close'), null);
        $email = input('post.email', '', 'strval,trim');
        if (empty($email) && !checkEmail($email)) $this->output(10008, lang("lan_emial_format_incorrect"));

        $username = input('post.username', '', 'strval,trim');
        if (empty($username)) {
            $username = $email;
        }
        //if (empty($username) || !checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));

        $model = model('member');
//        $r = $model->field('member_id')->where(['email' => $email])->find();
//        if ($r) $this->output(10008, lang("lan_reg_mailbox_already_exists"));

        $r = $model->field('member_id')->where(['ename' => $username])->find();
        if ($r) $this->output(10008, lang("lan_reg_username_already_exists"));

        $checkRegMax = model('Member')->checkRegMax('email', $email);
        if(is_string($checkRegMax)) $this->output(10009, $checkRegMax);
//        $regMax = Config::get_value('reg_phone_max_num', 10);
//        $count = $model->where(['email'=>$email])->count();
//        if ($count >= $regMax) $this->output(10009, lang('lan_phone_reg_num_max'));

        $email_code = input('post.email_code');
        if(empty($email_code)) $this->output(10008, lang("lan_validation_incorrect"));

        $senderLog = model('Sender')->check_log(2,$email,'register',$email_code);
        if(is_string($senderLog)) $this->output(10001,$senderLog);

        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');

        $pwdtrade = input('post.pwdtrade', 'strval');
        $repwdtrade = input('post.repwdtrade', '', 'strval');
        $pid = input('post.pid', '', 'strval');//2019-10-23 邀请码,不是用户id

        $flag = $model->checkReg($pwd,$repwd,$pwdtrade,$repwdtrade,$pid,true); //邀约注册不需要填写交易密码
        if(is_string($flag)) $this->output(10001,$flag);

        $data = [
            'ename' => $username,
            'email' => $email,
            'head' => '',
            'pwd' => $pwd,
            'pwdtrade' => $pwdtrade,
            'pid' => $pid,
            'ip' => get_client_ip_extend(),
            'reg_time' => time(),
            'status' => 1,
            'send_type' => 2,
        ];
//        $flag = $model->addReg(2,$data,$this->config);
        $flag = $model->addReg(3,$data,$this->config);
        if(is_string($flag)) $this->output(10001,$flag);

        $this->is_reg_login($flag['log_id']);

        //验证码设置为已使用
        model('Sender')->hasUsed($senderLog['id']);
//        $this->output(10000, lang('lan_operation_success'),HongbaoKeepLog::reward_config());
        $this->output(10000, lang('lan_operation_success'));
    }

    //注册完是否直接登录状态
    private function is_reg_login($member_id) {
        $platform = input('platform', '', 'strval,trim,strtolower');
//        if($platform=='pc') session('USER_KEY_ID',$member_id);
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

    public function email_domain() {
        $this->output(10000,lang('lan_operation_success'),EmailDomain::get_list());
    }

    public function invitation() {

        $active_name = input('active_name');

        $this->assign(['active_name'=>$active_name]);
        return $this->fetch('reg/invitation');
    }

    /**
     * 邀请激活
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function invite_active_init() {
        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $result = [
            'number'=>$number,
            'give_num'=>$giveNum,
            'currency_name'=>$currency['currency_name'],
        ];
        $this->output(10000,lang('lan_operation_success'),$result);
    }

    /**
     * 邀请激活
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function invite_active() {

        $active_name = input('post.active_name', '', 'strval,trim,strtolower');
        $username = input('post.username', '', 'strval,trim,strtolower');
        $password = input('post.password', '', 'strval');

        if (empty($active_name) || !checkUname($active_name)) $this->output(10001,lang('lan_Please_enter_the_correct'));
        if (empty($username) || !checkUname($username)) $this->output(10001,lang('lan_Please_enter_the_correct'));
        if (empty($password)) $this->output(10001,lang('lan_login_please_enter_your_password'));

        $userInfo = Db::name('member')->where('ename', $username)->find();
        if (!$userInfo) $this->output(10001,lang('lan_Account_does_not_exist'));//帐号不存在
        if ($userInfo['active_status'] != 1) $this->output(10001,lang('lan_user_not_active'));//帐号未激活，无法登录
        if(!(new Member())->checkPassword($password,$userInfo['pwd'])) {
            $this->output(10001,lang('lan_Password_error'));//密码错误
        }

        $activeUser = Db::name('member')->where(['ename'=>$active_name])->find();
        if (!$activeUser) $this->output(10001,lang('lan_Account_does_not_exist'));//帐号不存在
        if ($activeUser['active_status'] == 1) $this->output(10001,lang('lan_user_already_active'));//已激活

        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $currency_id = $currency['currency_id'];
        $member_id = $userInfo['member_id'];
        $active_member_id = $activeUser['member_id'];

        $userCurrency = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        $activeUserCurrency = CurrencyUser::getCurrencyUser($active_member_id, $currency_id);

        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $fee = floattostr(bcsub($number, $giveNum, 6));
        if (bccomp(floattostr($userCurrency['num']), $number, 6) == -1) {
            $this->output(10001,lang('insufficient_balance'));
        }

        try {
            Db::startTrans();

            $flag = Member::where(['member_id'=>$active_member_id, 'active_status'=>0])->update([
                'active_status'=>1,
                'active_time'=>time(),
                'pid'=>$member_id,
            ]);
            if(!$flag) throw new Exception('更新激活用户信息失败-in line:'.__LINE__);

            $flag = $log_id = db('MemberActiveLog')->insertGetId([
                'member_id' => $member_id,
                'active_member_id' => $active_member_id,
                'currency_id' => $currency_id,
                'num' => $number,
                'give_num' => $giveNum,
                'fee' => $fee,
                'add_time' => time(),
            ]);
            if ($flag === false) throw new Exception('添加激活记录失败-in line:'.__LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id'=>$member_id,
                'currency_id'=> $currency_id,
                'number_type'=>2,
                'number'=>$number,
                'type'=>2700,
                'content'=>"lan_invite_active",
                'fee'=> $fee,
                'to_member_id'=>$active_member_id,
                'to_currency_id'=>0,
                'third_id'=> $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = setUserMoney($member_id, $currency_id, $number, 'dec', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id'=>$active_member_id,
                'currency_id'=> $currency_id,
                'number_type'=>1,
                'number'=>$giveNum,
                'type'=>2700,
                'content'=>"lan_invite_active",
                'fee'=> 0,
                'to_member_id'=>0,
                'to_currency_id'=>0,
                'third_id'=> $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = setUserMoney($active_member_id, $currency_id, $giveNum, 'inc', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

            //添加用户上下级关系定时任务
            $flag = MemberBindTask::add_task($active_member_id);
            if($flag === false) throw new Exception('添加关系定时任务失败-in line:'.__LINE__);

            Db::commit();
            $this->output(SUCCESS,lang('lan_user_active_success'));
        }
        catch (Exception $e) {
            Db::rollback();

            $this->output(ERROR1,lang('lan_network_busy_try_again').',异常信息:'.$e->getMessage());
        }
    }

    public function download() {

        $this->assign(['down_url'=>Version_ios_DowUrl]);
        return $this->fetch('reg/download');
    }
}
