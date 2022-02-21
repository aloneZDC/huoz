<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
// 发送验证码
namespace app\common\model;
use PDOStatement;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\Exception;
use think\Db;

class Sender extends Base {
	public function auto_send($member_id,$type) {
        $phone_user = Db::name('member')->field('phone,country_code,email,login_type,send_type')->where(['member_id'=>$member_id])->find();
        if(!$phone_user) return lang('lan_Account_does_not_exist');

        //1:手机  2:邮箱
        $login_type = $phone_user['send_type'];
        if(empty($login_type)) {
            if(!empty($phone_user['phone'])) {
                $login_type = 1;
            } elseif(!empty($phone_user['email'])) {
                $login_type = 2;
            }
        }

        if($login_type==1) {
            return $this->send_phone($phone_user['country_code'],$phone_user['phone'],$type);
        } elseif($login_type==2) {
            return $this->send_email($phone_user['email'],$type);
        }
        return lang('lan_Account_does_not_exist');
    }

    /**
     * @param $member_id        用户id
     * @param $type             类型
     * @param $code             验证码
     * @param bool $isUsed      是否设为已使用
     * @return array|false|mixed|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function auto_check($member_id,$type,$code,$isUsed=false) {
        $phone_user = Db::name('member')->field('phone,country_code,email,login_type,send_type')->where(['member_id'=>$member_id])->find();
        if(!$phone_user) return lang('lan_Account_does_not_exist');

        $login_type = $phone_user['send_type'];
        if(empty($login_type)) {
            if(!empty($phone_user['phone'])) {
                $login_type = 1;
            } elseif(!empty($phone_user['email'])) {
                $login_type = 2;
            }
        }

        if($login_type==1) {
        	return $this->check_log(1,$phone_user['phone'],$type,$code,$isUsed);
        } elseif($login_type==2) {
        	return $this->check_log(2,$phone_user['email'],$type,$code,$isUsed);
        }

        return lang('lan_login_code_expired');
    }

	//发送手机验证码
	public function send_phone($country_code,$phone,$type) {
        if(empty($phone)) return lang('lan_please_enter_the_correct_mobile_number');

		switch ($type) {
            case 'login':
                $flag = Db::name('member')->field('member_id')->where('phone', $phone)->find();
                if (!$flag) return lang('account_not_register');
                break;
            case 'register':
                //return lang('lan_close');
                //$flag = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
                //if ($flag) return lang('lan_reg_phone_being');
                $checkRegMax = model('Member')->checkRegMax('phone', $phone);
                if(is_string($checkRegMax)) return $checkRegMax;
                //$regMax = Config::get_value('reg_phone_max_num', 10);
                //$count = Db::name('member')->where(['phone'=>$phone])->count();
                //if ($count >= $regMax) return lang('lan_phone_reg_num_max');
                break;
            case 'bindphone':
//                $flag = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
//                if ($flag) return lang('lan_reg_phone_being');

                //一个手机可以绑定10个账户
                $checkRegMax = model('Member')->checkRegMax('phone', $phone);
                if(is_string($checkRegMax)) return $checkRegMax;
                //$regMax = Config::get_value('reg_phone_max_num', 10);
                //$count = Db::name('member')->where(['phone'=>$phone])->count();
                //if ($count >= $regMax) return lang('lan_phone_reg_num_max');
                break;
            case 'findpwd':
                $flag = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
                if (!$flag) return lang('lan_Account_does_not_exist');
                break;
            case 'modifypwd': //修改密码
                break;
            case 'retradepwd': //修改交易密码
            	break;
            case 'tcoin': //提币
            	break;
            case 'modifyphone': //解绑手机
            	$flag = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
                if (!$flag) return lang('lan_Account_does_not_exist');
            	break;
            case 'air':
                $flag = Db::name('member')->field('member_id')->where(['phone'=>$phone])->find();
                if(!$flag) return lang('lan_Account_does_not_exist');
                break;
            case 'google_verify':
                break;
            default:
                break;
        }

        if(empty($country_code)) return lang('lan_No_incoming_country_code');
        $country = Db::name('countries_code')->where(['phone_code'=>$country_code,'status'=>1])->find();
        if(empty($country)) return lang('lan_No_incoming_country_code');
        if($country_code==86 && !checkMobile($phone)) return lang('lan_please_enter_the_correct_mobile_number');

        //发送频率过快
        if(!$this->last_check(1,$phone,40)) return lang('SMS_operation_later');

        $flag = $this->addLog(1,$phone,$country_code,$type);
        if($flag===false) return lang('lan_network_busy_try_again');

        return $flag;
	}

	//发送邮箱验证码
	public function send_email($email,$type) {
		if(empty($email) || !checkEmail($email)) return lang('lan_emial_format_incorrect');

        switch ($type) {
            case 'login':
                $flag = Db::name('member')->field('member_id')->where('email', $email)->find();
                if (!$flag) return lang('account_not_register');
                break;
            case 'register':
                //$flag = Db::name('member')->field('member_id')->where(['email'=>$email])->find();
                //if($flag) return lang('lan_reg_mailbox_already_exists');
                $checkRegMax = model('Member')->checkRegMax('email', $email);
                if(is_string($checkRegMax)) return $checkRegMax;
                //$regMax = Config::get_value('reg_email_max_num', 10);
                //$count = Db::name('member')->where(['email'=>$email])->count();
                //if ($count >= $regMax) return lang('lan_email_reg_num_max');
                break;
            case 'bindemail':
//                $flag = Db::name('member')->field('member_id')->where(['email'=>$email])->find();
//                if($flag) return lang('lan_reg_mailbox_already_exists');

                $checkRegMax = model('Member')->checkRegMax('email', $email);
                if(is_string($checkRegMax)) return $checkRegMax;
                //$regMax = Config::get_value('reg_email_max_num', 10);
                //$count = Db::name('member')->where(['email'=>$email])->count();
                //if ($count >= $regMax) return lang('lan_email_reg_num_max');
                break;
            case 'findpwd':
                $flag = Db::name('member')->field('member_id')->where(['email'=>$email])->find();
                if(!$flag) return lang('lan_Account_does_not_exist');
                break;
            case 'modifyemail': //解绑邮箱
            	$flag = Db::name('member')->field('member_id')->where(['email'=>$email])->find();
                if(!$flag) return lang('lan_Account_does_not_exist');
            	break;
            case 'air': // 云梯
                $flag = Db::name('member')->field('member_id')->where(['email'=>$email])->find();
                if(!$flag) return lang('lan_Account_does_not_exist');
                break;
            case 'google_verify':
                break;
            default:
                break;
        }

        if(in_array($type,['register','bindemail']) && !EmailDomain::checkEmail($email)) return lang('not_support_email');

        //发送频率过快
        if(!$this->last_check(2,$email,60)) return lang('SMS_operation_later');

        $flag = $this->addLog(2,$email,0,$type);
        if($flag===false) return lang('lan_network_busy_try_again');

        return $flag;
	}

	/**
	 *加入发送队列
	 *@param send_type 1:短信  2:email
	 *@param account 手机or邮箱
	 *@param country_code 国家编码 手机号时必填
	 *@param type 模块类型
	 *@param param 参数,例:指定参数等
	 */
    public function addLog($send_type,$account,$country_code=0,$type='',$param=[]) {
        if(!empty($param['code'])) {
            $code = $param['code'];
        } else {
            $code = rand(100000,999999);
        }

        $time = time();

        try{
        	$send_id = Db::name('sender')->insertGetId([
	        	'send_type' => $send_type,
	        	'send_time' => $time,
	        	'log_type' => $type,
	        	'log_captcha' =>$code,
	        	'log_account' => $account,
	        	'country_code' => $country_code,
	        	'status' => 0,
	        ]);

	        $data = [
	        	'code' => $code,
	            'type' => $type,
	            'add_time' => $time,
	        ];

	        if($send_type==1){
	        	$send_type = 'phone';
	        	$data['country_code'] = $country_code;
	        	$data['phone'] = $account;
	        } else {
	        	$send_type = 'email';
	        	$data['email'] = $account;
	        }
	        $task_id = Db::name($send_type.'_task')->insertGetId($data);
        } catch (Exception $e) {
        	return false;
        }

        return ['code'=>$code];
    }


	/**
	 *频率检测
	 **@param send_type 1:短信  2:email
	 *@param account 手机or邮箱
	 *@param seconds 秒数
	 */
	public function last_check($send_type,$account,$seconds=60) {
		$seconds = intval($seconds);

		$last = Db::name('sender')->where(['log_account'=>$account])->order('id desc')->find();
//		if($last && $last['status']==1) return true;//已使用的不受频率限制
        if($last && $last['send_time']>(time()-$seconds)) return false;
        return true;
	}

	/**
	 *验证验证码
	 *@param send_type 1:短信  2:email
	 *@param account 手机or邮箱
	 *@param type 模块类型
	 *@param captcha 验证码
	 */
	public function check_log($send_type,$account,$type,$captcha,$isUsed=false) {
	    $stop = 0;
	    $msg = "";
	    if ($send_type == 1) {
	        $stop = time() - 300; //验证码5分钟过期
	        $msg = lang('lan_reg_the_verification_code_incorrect');
	    } else {
	        $stop = time() - 3600; //邮件一个小时过期
	        $msg = lang("lan_email_code_error");
	    }

	    //改需求 只能最新的才可以使用
	    $sender = Db::name('sender')->where(['send_type'=>$send_type,'log_account'=>$account])->order('id desc')->find();
	    if (!$sender) return $msg;

	    if (!empty($type) && ($sender['log_type'] != $type || $sender['log_captcha'] !=$captcha)) return $msg;
	    if ($sender['status'] != 0 || $sender['send_time'] < $stop) return lang('lan_login_code_expired');
	    //设为已使用状态
	    if ($isUsed) Db::name('sender')->where(['id'=>$sender['id']])->setField('status',1);

	    return $sender;
	}

	/**
	 *设置验证码为已使用
	 */
	public function hasUsed($log_id) {
		return Db::name('sender')->where(['id'=>$log_id])->setField('status',1);
	}
}
