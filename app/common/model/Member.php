<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use google\GoogleAuthenticator;
use think\Log;
use think\Model;
use think\Exception;
use think\Db;

class Member extends Base {
    //修改绑定邮箱
    public function modifyemail($member_id,$email_code,$new_email,$new_email_code) {
        $info = Db::name('member')->where(['member_id'=>$member_id])->find();
        //if(!$info || empty($info['email'])) return lang('lan_input_personal_info');
        if(!$info || empty($info['email'])) return lang('lan_reg_phone');

        //旧邮箱验证码
        //$senderLog = model('Sender')->check_log(2,$info['email'],'modifyemail',$email_code);
        //if(is_string($senderLog)) return $senderLog;

        //绑定新手机
        $flag = $this->bindEmail($member_id,$new_email,$new_email_code,true);
        if(is_string($flag)) return $flag;

        return ['flag'=>true];
    }

    /**
     *绑定邮箱
     *@param member_id用户ID
     *@param email 邮箱
     *@param email_code 邮箱验证码
     *@param is_modify 已存在邮箱是否允许修改
     */
    public function bindEmail($member_id,$email,$email_code,$is_modify=false) {
        if(empty($email) || !checkEmail($email)) return lang('lan_emial_format_incorrect');
        if(empty($email_code)) return lang('lan_validation_incorrect');

        $senderLog = model('Sender')->check_log(2,$email,'bindemail',$email_code);
        if(is_string($senderLog)) return $senderLog;

        $checkRegMax = $this->checkRegMax('email', $email);
        if(is_string($checkRegMax)) return $checkRegMax;
        //$regMax = Config::get_value('reg_email_max_num', 10);
        //$count = Db::name('member')->where(['email'=>$email])->count();
        //if ($count >= $regMax) return lang('lan_email_reg_num_max');

        Db::startTrans();
        try{
//            $r = Db::name('member')->lock(true)->field('member_id')->where(['email'=>$email])->find();
//            if ($r) throw new Exception(lang('lan_reg_mailbox_already_exists'));

            if(!$is_modify) {
                $flag = Db::name('member')->where(['member_id'=>$member_id])->value('email');
                if($flag && !empty($flag)) throw new Exception(lang('lan_bind_exists'));
            }

            $result = Db::name('member')->where(['member_id'=>$member_id])->setField('email',$email);
            if($result===false) throw new Exception(lang('lan_network_busy_try_again'));

            model('Sender')->hasUsed($senderLog['id']);
            Db::commit();

            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //修改绑定手机
    public function modifyphone($member_id,$phone_code,$new_phone,$new_phone_code,$country_code) {
        $info = Db::name('member')->where(['member_id'=>$member_id])->find();
        //if(!$info || empty($info['phone'])) return lang('lan_input_personal_info');
        if(!$info || empty($info['phone'])) return lang('lan_reg_email');

        //旧手机验证码
        $senderLog = model('Sender')->check_log(1,$info['phone'],'modifyphone',$phone_code);
        if(is_string($senderLog)) return $senderLog;

        //绑定新手机
        $flag = $this->bindphone($member_id,$new_phone,$new_phone_code,$country_code,true);
        if(is_string($flag)) return $flag;

        return ['flag'=>true];
    }

    /**
     *绑定手机
     *@param member_id用户ID
     *@param phone 手机号
     *@param phone_code 手机验证码
     *@param country_code 国家编码
     *@param is_modify 已存在手机是否允许修改
     */
    public function bindphone($member_id,$phone,$phone_code,$country_code,$is_modify=false) {
        if(empty($country_code)) return lang("lan_No_incoming_country_code");
        $country = Db::name('countries_code')->where(['phone_code'=>$country_code,'status'=>1])->find();
        if(!$country) return lang("lan_No_incoming_country_code");

        if(empty($phone)) return lang('lan_please_enter_the_correct_mobile_number');
        if($country_code==86 && !checkMobile($phone)) return lang('lan_please_enter_the_correct_mobile_number');

        $senderLog = model('Sender')->check_log(1,$phone,'bindphone',$phone_code);
        if(is_string($senderLog)) return $senderLog;

        $checkRegMax = $this->checkRegMax('phone', $phone);
        if(is_string($checkRegMax)) return $checkRegMax;
        //$regMax = Config::get_value('reg_phone_max_num', 10);
        //$count = Db::name('member')->where(['phone'=>$phone])->count();
        //if ($count >= $regMax) return lang('lan_phone_reg_num_max');

        Db::startTrans();
        try{
//            $r = Db::name('member')->lock(true)->field('member_id')->where(['phone'=>$phone])->find();
//            if ($r) throw new Exception(lang('lan_reg_phone_being'));

            if(!$is_modify) {
                $flag = Db::name('member')->lock(true)->where(['member_id'=>$member_id])->value('phone');
                if($flag && !empty($flag)) throw new Exception(lang('lan_bind_exists'));
            }


            $result = Db::name('member')->where(['member_id'=>$member_id])->update(['phone'=>$phone,'country_code'=>$country_code]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            model('Sender')->hasUsed($senderLog['id']);
            Db::commit();

            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //注册检测
    public function checkReg($pwd,$repwd,$pwdtrade,$repwdtrade,$pid,$is_check_pwdtrade=true) {
        $flag = $this->checkPwdModif($pwd,$repwd);
        if(is_string($flag)) return $flag;

        if($is_check_pwdtrade) {
            $flag = $this->checkPwdTradeModif($pwdtrade,$repwdtrade);
            if(is_string($flag)) return $flag;
        }

        if(!empty($pid)) {
            $pid = $this->toMemberId($pid);
            if(empty($pid)) return lang('lan_invitation_code_does_not_exist'); //邀請碼不正確
//             $pidInfo = Db::name('member')->where(['member_id'=>$pid])->find();
//             if(!$pidInfo) return lang('lan_invitation_code_does_not_exist'); //邀請碼不正確
        }
        else {
            return lang('lan_invitation_code_does_not_exist'); //邀請碼必填
        }

        return ['flag'=>true];
    }

    /**
     *pwd 密码
     *repwd 重复密码
     *old 是否不能和旧密码一样
     */
    public function checkPwdModif($pwd,$repwd,$old='') {
        if(empty($pwd)) return lang('lan_password_not_empty');
        if($pwd!=$repwd) return lang('lan_password_is_different');
        if(!$this->checkPwd($pwd)) return lang('lan_password_format_error');
        if(!empty($old) && $pwd==$old) return lang('lan_user_password_mismatch');

        return ['flag'=>true];
    }

    /**
     *修改交易密码
     *pwd 密码
     *repwd 重复密码
     *old 是否不能和旧密码一样
     */
    public function checkPwdTradeModif($pwdtrade,$repwdtrade,$old='') {
        if(empty($pwdtrade)) return lang('lan_user_Transaction_password_empty1'); //交易密碼不能為空
        if($pwdtrade!=$repwdtrade) return lang('lan_user_Transaction_password_tow_same'); //兩次交易密碼不一致
        if(!$this->checkPwdTrade($pwdtrade)) return lang('lan_user_Transaction_password_space');
        if(!empty($old) && $pwdtrade==$old) return lang('lan_user_password_mismatch');

        return ['flag'=>true];
    }

    /**
     *注册
     *@param type 1手机 2邮箱 3用户名
     *@param data 注册
     *@param $coin_currency_reg 赠送积分类型
     *@param $coin_number_reg 赠送积分数量
     *@param $coin_invit_num 邀请赠送人数限制
     *@param $coin_currency 邀请赠送积分类型
     *@param $coin_number 邀请赠送积分数量
     */
    public function addReg($type,$data,$config=[]) {
        Db::startTrans();
        try{
            $data['pwd'] = $this->password($data['pwd']);
            $data['pwdtrade'] = $this->password($data['pwdtrade']);
            $data['pid'] = $this->toMemberId($data['pid']);

            $where = [];
            $msg = '';
            if($type==1) {
                $where['phone'] = $data['phone'];
                $msg = lang('lan_reg_phone_being');
                $data['nick'] = substr($data['phone'],0,3).'****'.substr($data['phone'],-4);
            } else if($type==2) {
                $where['email'] = $data['email'];
                $msg = lang('lan_reg_mailbox_already_exists');
                $data['nick'] = substr($data['email'],0,3).'****'.substr($data['email'],-7);
            } else {
                $where['ename'] = $data['ename'];
                $msg = lang('lan_reg_username_already_exists');
                $data['nick'] = $data['ename'];
            }

            $r = Db::name('member')->lock(true)->field('member_id')->where($where)->find();
            if($r) throw new Exception($msg);

            $member_id = Db::name('member')->insertGetId($data);
            if(!$member_id) throw new Exception(lang('lan_network_busy_try_again'));

            //生成邀请码
            $invit_code = $this->getInviteCode($member_id);
            $log_id = Db::name('member')->where(['member_id'=>$member_id])->setField('invit_code',$invit_code);
            if($log_id===false) throw new Exception(lang('lan_network_busy_try_again'));

            //添加用户上下级关系定时任务
            $flag = MemberBindTask::add_task($member_id);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            // 注册赠送800火米到锁仓
            $is_register_handsel = \app\common\model\RocketConfig::getValue('is_register_handsel');
            $reward_currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
            $register_reward = \app\common\model\RocketConfig::getValue('register_reward');
            $register_recommend_reward = \app\common\model\RocketConfig::getValue('register_recommend_reward');
            if($is_register_handsel == 1 && $reward_currency_id) {
                //新用户注册奖励
                $currency_user = CurrencyUser::getCurrencyUser($member_id, $reward_currency_id);
                if(empty($currency_user)) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7118, 'register_reward1', 'in', $register_reward, 1);
                if ($flag === false) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'lock_num' => $currency_user['lock_num']])->setInc('lock_num', $register_reward);
                if (!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $res = self::where(['member_id' => $data['pid']])->find();
                if ($res) {//推荐新用户奖励
                    $currency_user = CurrencyUser::getCurrencyUser($res['member_id'], $reward_currency_id);
                    if(empty($currency_user)) throw new Exception(lang('lan_network_busy_try_again'));

                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7119, 'register_reward2', 'in', $register_recommend_reward, 1);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'lock_num' => $currency_user['lock_num']])->setInc('lock_num', $register_recommend_reward);
                    if (!$flag) throw new Exception(lang('lan_network_busy_try_again'));
                }
            }
            //生成火箭用户汇总
            $flag = \app\common\model\RocketMember::addItem($member_id, 0);
            if(!$flag) {
                throw new Exception(lang('operation_failed_try_again'));
            }
            //生成方舟用户汇总
            $flag = \app\common\model\ArkMember::addItem($member_id, 0);
            if(!$flag) {
                throw new Exception(lang('operation_failed_try_again'));
            }

            //新人专区购买数量
            $buy_shop_num = RocketConfig::getValue('buy_shop_num');
            if ($buy_shop_num > 0) {
                $flag = RocketMember::where(['member_id' => $member_id])->update(['buy_shop_num' => $buy_shop_num]);
                if ($flag === false) {
                    throw new Exception(lang('operation_failed_try_again'));
                }
            }

            //推荐新人获得新人专区购买次数
            $recommend_shop_num = RocketConfig::getValue('recommend_shop_num');
            $res = RocketMember::where(['member_id' => $data['pid']])->find();
            if ($res && $recommend_shop_num > 0) {
                $flag = RocketMember::where(['member_id' => $data['pid']])->setInc('buy_shop_num', $recommend_shop_num);
                if ($flag === false) {
                    throw new Exception(lang('operation_failed_try_again'));
                }
            }

            Db::commit();
            return ['log_id'=>$member_id];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 注册赠送USDT
     * @param int $member_id
     * @param int $number
     * @param int|null $regUserId
     * @throws Exception
     * @return bool
     */
    private function giveU($member_id, $number = 1, $regUserId = null)
    {
        if (4 == $number) {
//            return "4 退出"; // debug
             return true; // 退出递归
        }
        $user = $this->where('member_id', $member_id)->find();
        $regAwardCurrencyId = Config::get_value(CurrencyUser::KEY_REG_AWARD_CURRENCY_ID);
        $currencyUser = CurrencyUser::getCurrencyUser($member_id, $regAwardCurrencyId);
        $regUserId = empty($regUserId) ? $member_id : $regUserId;
        switch ($number) {
            case 1:
                // 注册 赠送 3USDT 到锁仓
                $award = (double)Config::get_value(CurrencyUserAwardLog::KEY_REG_AWARD);
                $type = CurrencyUserAwardLog::TYPE_REG;
                $number = 2;
                break;
            case 2:
                // 直推 赠送 2USDT 到锁仓
                $award = (double)Config::get_value(CurrencyUserAwardLog::KEY_ONE_INVITE_AWARD);
                $type = CurrencyUserAwardLog::TYPE_ONE_INVITE;
                $number = 3;
                break;
            case 3:
                $award = (double)Config::get_value(CurrencyUserAwardLog::KEY_TWO_INVITE_AWARD);
                $type = CurrencyUserAwardLog::TYPE_TWO_INVITE;
                $number = 4;
                break;
            default:
                return true;
        }

        $flag = CurrencyUserAwardLog::addLog($user['member_id'], $regUserId, $award, $regAwardCurrencyId, $type);
        if (empty($flag)) {
//            return "记录保存失败"; // debug
            return false;
        }
        // 增加赠送金额
        $currencyUser->num_award += $award;
        if (!$currencyUser->save()) {
//            return "金额保存失败"; // debug
            return false;
        }
        if (empty($user['pid'])) {
            // 无上级, 退出递归
//            return "empty pid";// debug
            return true;
        }
        // 递归
        return $this->giveU($user['pid'], $number, $regUserId);
    }

    //检测用户交易密码
    //is_md5 是否已经一次md5
    public function checkMemberPwdTrade($member_id,$pwdtrade,$is_md5=false,$pwd_error_max=5) {
        if(empty($pwdtrade)) return lang('lan_Incorrect_transaction_password');

        $user_pwd = Db::name('member')->field('pwdtrade,pwdtrade_error')->where(['member_id'=>$member_id])->find();
        if(!$user_pwd || $user_pwd['pwdtrade']=='') return lang('lan_Incorrect_transaction_password');

        $pwd_error_max = intval($pwd_error_max);
        if($pwd_error_max>0 && $user_pwd['pwdtrade_error']>=$pwd_error_max) return lang('lan_user_pwd_trade_error_max');

        $password = $this->password($pwdtrade,$is_md5);
        if($user_pwd['pwdtrade']!=$password) {
          Db::name('member')->where(['member_id'=>$member_id])->setInc('pwdtrade_error');
          return lang('lan_Incorrect_transaction_password');
        }

        if($user_pwd['pwdtrade_error']>0) Db::name('member')->where(['member_id'=>$member_id])->setField('pwdtrade_error',0);

        return ['flag'=>true];
    }

    //检测密码格式
    public function checkPwd($pwd) {
        $length = strlen($pwd);
        if($length<6 || $length>20) return false;

        if (!preg_match('/^[a-zA-z0-9]{8,20}$/', $pwd) || preg_match('/^[A-Za-z]+$/', $pwd) || preg_match('/^[0-9]+$/', $pwd)) {
            return false;
        }
        return true;
    }

    //检测交易密码个数
    public function checkPwdTrade($pwd) {
        if (preg_match('/^[0-9]{6}$/', $pwd)) {
            return true;
        }

        return false;
    }

    public function checkPassword($value,$password){
        $pwd = $this->password($value);
        if($pwd!=$password) {
            return false;
        }
        return true;
    }

    //is_md5 是否已经md5一次
    public function password($value,$is_md5=false)
    {
        if(!$is_md5) $value = md5($value);
        return md5(substr(md5($value.config('extend.password_halt')),8));
    }

    //邀请码转成用户ID
    public function toMemberId($invit_code) {
//        if(empty($invit_code)) return 4;
        if(empty($invit_code)) return 0;

        $invit_code = strtoupper($invit_code);
        $member_id = Db::name('member')->where(['invit_code'=>$invit_code])->value('member_id');
//        if(!$member_id) return 4;
        if(!$member_id) return 0;

        return $member_id;
    }


    public function getInviteCode($num) {
        //$num的最大值在1069999999左右
        //生成当前用户的邀请码 当前时间戳-1500050846+邀请人id 再进行32进制的转换
        $nowrand = time() - 1500050846 + intval($num);
        $inviteCode = $this->enid($nowrand);
        return $inviteCode;
    }

    //十进制转换三十二进制
    private function enid($num) {
        $num = intval($num);
        if ($num <= 0) return false;

        $charArr = array("3","4","5","6","7","8","9",'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y');
        $char = '';
        do {
            $key = ($num - 1) % 30;
            $char= $charArr[$key] . $char;
            $num = floor(($num - $key) / 30);
        } while ($num > 0);
        return $char;
    }

    /**
     * 验证支付密码
     * @param int $member_id            用户id
     * @param string $paypwd               支付密码
     * @return mixed
     * Created by Red.
     * Date: 2018/12/13 18:43
     */
    static function verifyPaypwd($member_id,$paypwd){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if(!empty($member_id)&&!empty($paypwd)){
          $member=self::where(['member_id'=>$member_id])->field("pwdtrade,pwdtrade_error")->find();
          if(!empty($member)){
              //未设置密码
              if(empty($member->pwdtrade)){
                  $r['message']=lang("lan_no_payment_password_set");
              }elseif($member['pwdtrade_error']>=5){
                $r['message']=lang("lan_user_pwd_trade_error_max");
              }else{
                  $m=new Member();
                  $pass=$m->password($paypwd);
                  if($pass==$member->pwdtrade){
                      Db::name('member')->where(['member_id'=>$member_id])->setField('pwdtrade_error',0);
                      $r['code']=SUCCESS;
                      $r['message']=lang("lan_verification_success");
                  }else{
                        Db::name('member')->where(['member_id'=>$member_id])->setInc('pwdtrade_error');
                     $r['message']=lang("lan_Password_error");
                  }
              }
          }
        }
        return $r;
    }

    /**
     * 我的好友@标
     * @param $member_id
     */
    public function my_friend($member_id,$page=1,$page_size=10){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_not_data");
        $member=Db::name('member')->field('member_id,phone,nick,head,reg_time,email')->where(['pid'=>$member_id])->limit(($page - 1) * $page_size, $page_size)->select();
        $count=Db::name('member')->where(['pid'=>$member_id])->count();
        if(!empty($member)){
            foreach ($member as $key =>$val){
                 if(empty($val['nick'])){
                     $member[$key]['nick']=$val['phone'];
                    // $member[$key]['nick']=substr($val['phone'],0,3).'****'.substr($val['phone'],-4);
                     if(empty($val['phone'])){
                         $member[$key]['nick']=$val['email'];
                     // $member[$key]['nick']=substr($val['email'],0,3).'****'.substr($val['email'],-7);;
                     }

                 }
                 if(empty($val['head'])){
                     $member[$key]['head']=$this->default_head;
                 }
                $member[$key]['phone']=$val['phone'];
                 //$member[$key]['phone']=substr($val['phone'],0,3).'****'.substr($val['phone'],-4);
                if(empty($val['phone'])){
                    $member[$key]['phone']=$val['email'];
                     //$member[$key]['phone']=substr($val['email'],0,3).'****'.substr($val['email'],-7);;
                 }

                $member[$key]['add_time']=date('Y-m-d H:i:s',$val['reg_time']);
             unset($member[$key]['reg_time']);
            }
            $r['message'] = lang("lan_data_success");
            $r['code'] = SUCCESS;
        }
        $r['result']['member'] =empty($member)?[]:$member;
        $r['result']['count'] =$count>0?$count:0;
        return $r;
    }
     //添加实名身份证信息
    public function add_verify($post=array()){
        $r['result']=[];
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");

        $member_id=$post['member_id'];
        $name = $post['name'];
        $idcard = trim($post['idcard']);
        $pic1 = $post['pic1'];
        $pic2 = $post['pic2'];
        $pic3 = $post['pic3'];
        $cardtype = $post['cardtype'];//1=身份证2=护照 5= 驾照
        $country_code = $post['country_code'];
        $nation_id = $post['nation_id'];
        $sex=$post['sex'];
        $nation=$this->nation('nation_id');
        $nation_arr=array_column($nation,'nation_id');
        $nation_arr[]=0;
        $phone_code=$this->countries_code('phone_code');
        $phone_code_arr=array_column($phone_code,'phone_code');
        //校验真实姓名
        if(empty(trim($name))){
            $r['message'] = lang("lan_no_real_name");
            return $r;
        }
        //校验证件图片
        if(empty($pic1)||empty($pic2)||empty($pic3)){
            $r['message'] = lang("lan_no_certificates_pic");
            return $r;
        }
        //校验证件类型、民族、国家代码、性别
        if(!in_array($cardtype,[1,2,5])||!in_array($nation_id,$nation_arr)||!in_array($country_code,$phone_code_arr)||!in_array($sex,[1,2])){
         return $r;
        }
        //校验证件号是否为空
        if(empty($idcard)){
            $r['code'] = ERROR2;
            $r['message'] = lang("lan_certificates_error");
            return $r;
        }
        //校验证件号是否存在
        $verify=Db::name('verify_file')->field('idcard,member_id,verify_state')->where(['idcard'=>$idcard])->find();
        if(!empty($verify)&&$verify['verify_state']!=0){
            $r['code'] = ERROR2;
            $r['message'] = lang("lan_certificates_exist");
            return $r;
        }
        //校验证件号格式是否正确
        if ($cardtype == 1 || $cardtype == 5) {//1=身份证2=护照 5= 驾照
            if($post['country_code']=='86'){
                if (!is_idcard($idcard)) {
                    $r['code'] = ERROR3;
                    $r['message'] = lang("lan_certificates_error");
                    return $r;
                }
            }

        }

        $country_id=Db::name('countries_code')->where(['phone_code'=>$country_code])->field('id')->find();
        $insert_data=[
            'member_id' =>$member_id ,
            'name' => $name,
            'pic1' => $pic1,
            'pic2' => $pic2,
            'pic3' => $pic3,
            'addtime' => time(),
            'verify_state' => 2,
            'cardtype' => $cardtype,
            'idcard' => $idcard,
            'country_code' => $country_code,
            'sex'=>$sex,
            'nation_id'=>$nation_id,
            'country_id'=>$country_id['id'],
        ];
        //校验用户实名验证数据是否已存在
        $verify2=Db::name('verify_file')->where(['member_id'=>$member_id])->find();
        if(empty($verify2)){//不存在则添加
            $res=Db::name('verify_file')->insert($insert_data);
        }else{//已存在则修改
            $res=Db::name('verify_file')->where(['member_id'=>$member_id])->update($insert_data);
        }

        if(!$res){
            $r['code'] = ERROR4;
            $r['message'] = lang("lan_modifymember_parameter_error");
            return $r;
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_operation_success");
        return $r;
    }

    //民族信息@标
    public function nation($field='*'){
        return Db::name('nation')->field($field)->select();
    }
    //国家信息@标
    public function countries_code($field='*'){
       $country=Db::name('countries_code')->field($field)->select();
        $country_arr=[];
       if($country){
           foreach ($country as $key =>$val){
               if($val['phone_code']=='86'||$val['phone_code']=='852'||$val['phone_code']=='853'||$val['phone_code']=='886'){
                   $country_arr[]=$val;
                   unset($country[$key]);
               }
           }
           $country=array_merge($country_arr,$country);
       }

        return $country ;
    }
   //单个用户信息
    public function member_info($member_id,$ield="member_id,phone,nick,head,reg_time,email"){
        $member=Db::name('member')->field($ield)->where(['member_id'=>$member_id])->find();
        if(!empty($member)){
            if(empty($member['nick'])){
                $member['nick']=substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
                if(empty($member['phone'])){
                    $member['nick']=substr($member['email'],0,3).'****'.substr($member['email'],-7);;
                }

            }
            if(empty($member['head'])){
                $member['head']=$this->default_head;
            }
            $member['phone']=substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
            if(empty($val['phone'])){
                $member['phone']=substr($member['email'],0,3).'****'.substr($member['email'],-7);;
            }
        }

        return empty($member)?[]:$member;
    }

    public function getUserName($member_id) {
      $member=Db::name('member')->field('member_id,phone,nick,head,reg_time,email,name')->where(['member_id'=>$member_id])->find();
      if(empty($member['nick'])) {
        if(!empty($member['phone'])){
          $member['username'] = substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
        } else {
          $member['username'] = substr($member['email'],0,3).'****'.substr($member['email'],-7);
        }
      } else {
        $member['username'] = $member['nick'];
      }
      if(empty($member['head'])) $member['head']=$this->default_head;
      return $member;
    }

    public function addLoginLog($member_id,$uuid,$login_ip,$platform='',$status=1) {
      $type = in_array($platform, ['ios', 'android']) ? 1 : 0;
      $status = intval($status);

      Db::name('member_login')->insertGetId([
        'member_id' => $member_id,
        'type' => $type,
        'uuid' => $uuid,
        'login_ip' => $login_ip,
        'platform' => $platform,
        'login_time' => time(),
        'status' => $status,
      ]);
    }

    //检测是否需要验证码登录
    public function checkIsCode($member_id,$uuid){
      //暂时关闭
      return false;

      if(empty($uuid)) return true;
      $info = Db::name('member_login')->where(['member_id'=>$member_id,'type'=>1,'status'=>1])->order('id desc')->find();
      if(!$info) return true;

      if(empty($info['uuid']) || $info['uuid']!=$uuid) return true;

      return false;
    }

    //增加登录验证码错误次数
    public function pwdErrorInc($member_id) {
      $member_id = intval($member_id);
      Db::name('member')->where(['member_id'=>$member_id])->setInc('pwd_error');
    }

    public function pwdErrorReset($member_id) {
      $member_id = intval($member_id);
      Db::name('member')->where(['member_id'=>$member_id])->setField('pwd_error',0);
    }

    public function checkRegMax($type, $account)
    {
        $find = Db::name('RegWhitelist')->where('account', $account)->find();
        if (!$find) {
            if ($type == 'phone') {
                //$regMaxIgnoreList = explode(',', Config::get_value('reg_phone_max_ignore_list', ''));
                //if (!in_array($account, $regMaxIgnoreList)) {
                $regMax = Config::get_value('reg_phone_max_num', 0);
                if ($regMax > 0) {
                    $count = Db::name('member')->where(['phone' => $account])->count();
                    if ($count >= $regMax) return lang('lan_phone_reg_num_max');
                }
                //}
            }
            else if ($type == 'email') {
                //$regMaxIgnoreList = explode(',', Config::get_value('reg_email_max_ignore_list', ''));
                //if (!in_array($account, $regMaxIgnoreList)) {
                $regMax = Config::get_value('reg_email_max_num', 0);
                if ($regMax > 0) {
                    $count = Db::name('member')->where(['email' => $account])->count();
                    if ($count >= $regMax) return lang('lan_email_reg_num_max');
                }
                //}
            }
            else {
                return lang('参数错误');
            }
        }
        return true;
    }

    /**
     * 创建用户的google验证私钥
     * @param $user_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add_secret($user_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id)) {
            $google = new GoogleAuthenticator();
            $secret = $google->createSecret();
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_operation_success");
            $r['result'] = $secret;
        }
        return $r;
    }

    /**
     * 谷歌认证
     * @param $user_id              用户id
     * @param $google_secret        谷歌密钥
     * @param $google_code          谷歌验证码
     * @return mixed
     * @throws \think\exception\PDOException
     */
    public function google_verify($user_id, $google_secret, $google_code)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && !empty($google_secret) && !empty($google_code)) {
            self::startTrans();
            try {
                $google = new GoogleAuthenticator();
                $verify = $google->verifyCode($google_secret, $google_code);
                //google验证成功则保存到用户表
                if ($verify) {
                    $flag = Db::name('member')->where(['member_id' => $user_id])->setField('google_secret', $google_secret);
                    if ($flag !== false) {
                        $r['code'] = SUCCESS;
                        $r['message'] = lang("lan_operation_success");
                    } else {
                        throw new Exception(lang("system_error_please_try_again_later"));
                    }
                }else{
                    throw new Exception(lang("google_verification_failed"));
                }
                self::commit();
            } catch (Exception $exception) {
                $r['message'] = $exception->getMessage();
                self::rollback();
            }
        }
        return $r;
    }

    /**
     * 谷歌验证
     * @param $user_id              用户id
     * @param $google_code          谷歌验证码
     * @return mixed
     * @throws \think\exception\PDOException
     */
    public function google_check($user_id, $google_code)
    {
        $member = Db::name('member')->where(['member_id' => $user_id])->find();
        if (empty($member['google_secret'])) return lang('google_not_verified');
        $google = new GoogleAuthenticator();
        $verify = $google->verifyCode($member['google_secret'], $google_code);
        //google验证成功则保存到用户表
        if (!$verify) return lang("google_verification_failed");
        return true;
    }


    /**
     * 公链注册 A在区块上给B激活  则B自动创建账户  B自动注册为A的下级
     * name 公链地址名称
     * public_address公链公钥
     * pid_name 上级公链地址名称
     */
    public function public_chain_reg($name,$public_address,$pid_name) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if(empty($name) || empty($public_address) || empty($pid_name)) return $r;

        try{
            $pid_info = Member::where(['ename'=>$pid_name])->find();
            if(empty($pid_info)) {
                $r['message'] = "public_chain_reg:" . $pid_name." not exists";
                return $r;
            }

            $member = Db::name('member')->field('member_id')->where(['ename' => $name])->find();
            if(!empty($member)) {
                $r['message'] = "public_chain_reg:". $name." has reg";
                return $r;
            }

            $time = time();
            $data = [
                'ename' => $name,
                'nick' => $name,
                'email' => '',
                'head' => '',
                'pwd' => substr(md5($name.md5($public_address)),0,16),
                'pwdtrade' => '573678',
                'pid' => $pid_info['member_id'],
                'ip' => '',
                'reg_time' => $time,
                'status' => 1,
                'send_type'=>2,
                'active_status' => 1,
                'active_time' => $time,
            ];

            $data['pwd'] = $this->password($data['pwd']);
            $data['pwdtrade'] = $this->password($data['pwdtrade']);

            //添加新用户
            $member_id = Db::name('member')->insertGetId($data);
            if(!$member_id) {
                $r['message'] = "public_chain_reg:". $name." reg fail";
                return $r;
            }

            //添加上下级定时任务
            $flag = MemberBindTask::add_task($member_id);
            if(!$flag) {
                $r['message'] = "public_chain_reg:". $name." MemberBindTask fail";
                return $r;
            }

            //保存公钥地址
            $currency_user = CurrencyUser::getCurrencyUser($member_id,Currency::PUBLIC_CHAIN_ID);
            if(empty($currency_user)) {
                $r['message'] = "public_chain_reg:". $name." currency_user create fail";
                return $r;
            }

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id']])->setField('secret',$public_address);
            if(!$flag) {
                $r['message'] = "public_chain_reg:". $name." currency_user save public address fail";
                return $r;
            }

            //增加激活记录
            $flag = Db::name('member_active_log')->insertGetId([
                'member_id' => $pid_info['member_id'],
                'active_member_id' => $member_id,
                'currency_id' => 0,
                'num' => 0,
                'give_num' => 0,
                'fee' => 0,
                'add_time' => time(),
            ]);
            if(!$flag) {
                $r['message'] = "public_chain_reg:". $name." member_active_log fail";
                return $r;
            }

            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_operation_success");
        }catch (Exception $e) {
            $r['message'] = "public_chain_reg:" . $name." ".$e->getMessage();
        }
        return $r;
    }

    /**
     * 保存注册用户数据
     * @param $public_key
     * @param $private_key
     * @param $ename
     * @return mixed
     */
    public function submit_reg($uuid, $public_key, $private_key, $username)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (empty($public_key) || empty($private_key) || empty($username)) return $r;

        // 判断设备注册数量
        $device_limit = Config::get_value('device_limit');
        $member_uuid = Member::where('uuid', $uuid)->count('uuid');
        if($member_uuid >= $device_limit) {
            $r['message'] = lang('bfw_device_limit');
            return $r;
        }

        // 判断用户是否存在
        $member_ename = Member::where('ename', $username)->find();
        if ($member_ename) {
            $r['message'] = lang('lan_reg_username_already_exists');
            return $r;
        }

        $member_private_key = Member::where('private_key', $private_key)->find();
        if ($member_private_key) {
            $r['message'] = lang('lan_reg_username_already_exists');
            return $r;
        }

        try {
            $time = time();
            $data = [
                'uuid' => $uuid,
                'ename' => $username,
                'nick' => $username,
                'pwd' => substr(md5($username . md5($public_key) . $private_key), 0, 16),
                'pwdtrade' => '573678',
                'pid' => 0,
                'reg_time' => $time,
                'active_time' => $time,
                'public_key' => $public_key,
                'private_key' => $private_key,
            ];
            $data['pwd'] = $this->password($data['pwd']);
            $data['pwdtrade'] = $this->password($data['pwdtrade']);

            //添加新用户
            $member_id = Member::insertGetId($data);
            if (!$member_id) {
                $r['message'] = lang('lan_reg_the_network_busy');
                return $r;
            }

            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_operation_success");
        } catch (Exception $e) {
            $r['message'] = lang('lan_reg_the_network_busy');
        }
        return $r;
    }

    /**
     * 激活账号
     * @param $username
     * @param $pid_name
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activation($username,$member_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (empty($username) || empty($member_id)) return $r;

        // 判断用户是否存在
        $pid_info = Member::where(['member_id' => $member_id])->find();
        if (empty($pid_info)) {
            $r['message'] = lang('account_not_exists');
            return $r;
        }

        $member = Member::where(['ename' => $username])->find();
        if (empty($member)) {
            $r['message'] = lang('account_not_exists');
            return $r;
        }

        // 判断用户是否已激活
        if ($member['active_status']) {
            $r['message'] = lang('lan_user_already_active');
            return $r;
        }

        // 判断上级用户余额是否充足
        $activation_fee = Config::get_value('bfw_activation',0); // 激活账号手续费
        $CurrencyUser = CurrencyUser::getCurrencyUser($pid_info['member_id'], Currency::PUBLIC_CHAIN_ID);
        $AccMoney = bcadd($CurrencyUser['num'], $CurrencyUser['forzen_num'], 6);
        if (bccomp($AccMoney, $activation_fee, 6) < 0) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 创建或获取被激活用户的钱包信息
        $BeCurrencyUser = CurrencyUser::getCurrencyUser($member['member_id'], Currency::PUBLIC_CHAIN_ID);
        if (empty($BeCurrencyUser)) {
            $r['message'] = lang('lan_Network_request_failed');
            return $r;
        }

        try {
            Db::startTrans();

            $time = time();
            // 保存激活记录
            $log_id = Db::name('member_active_log')->insertGetId([
                'member_id' => $pid_info['member_id'],
                'active_member_id' => $member['member_id'],
                'currency_id' => 0,
                'num' => 0,
                'give_num' => 0,
                'fee' => $activation_fee,
                'add_time' => $time
            ]);
            if(!$log_id) throw new Exception(lang('lan_reg_the_network_busy'));

            // 激活空投奖励
            $activate_airdrop_reward = Config::get_value('activate_airdrop_reward', 1); // 被激活赠送
            if ($activate_airdrop_reward > 0) {
                // TODO 上线才使用 激活空投奖励
                // BflPool::fromToTask(BflPool::AIRDROP, BflPool::HOLE, $activate_airdrop_reward, 'bfw_activate_reward', $log_id);

//                $flag = AccountBook::add_accountbook($member['member_id'], Currency::PUBLIC_CHAIN_ID, 5302, 'bfw_activate_reward', 'in', $activate_airdrop_reward, $log_id);
//                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

                // 记录账本
                $flag = CurrencyLockBook::add_log('forzen_num', 'award', $member['member_id'], Currency::PUBLIC_CHAIN_ID, $activate_airdrop_reward, $log_id);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

                // 增加余额
                $flag = CurrencyUser::where(['member_id' => $member['member_id'], 'currency_id' => Currency::PUBLIC_CHAIN_ID, 'forzen_num' => $BeCurrencyUser['forzen_num']])
                    ->setInc('forzen_num', $activate_airdrop_reward);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));
            }

            // 赠送体验矿机
            $feel_is_mining = Config::get_value('feel_is_mining',0);
            if($feel_is_mining) {
                $flag = FeelMining::AddLog($member['member_id']);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));
            }

            // 扣除激活手续费
            $forzen_num = bcsub($activation_fee, $CurrencyUser['forzen_num'], 6);
            if($forzen_num <= 0) { // 冻结账户余额足够
                $flag = CurrencyLockBook::add_log('forzen_num', 'activation', $pid_info['member_id'], Currency::PUBLIC_CHAIN_ID, $activation_fee, $log_id);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

                // 扣除数量及手续费
                $flag = CurrencyUser::where(['member_id' => $pid_info['member_id'], 'currency_id' => Currency::PUBLIC_CHAIN_ID, 'forzen_num' => $CurrencyUser['forzen_num']])
                    ->setDec('forzen_num', $activation_fee);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

            }else {// 只能抵扣部分
                if($CurrencyUser['forzen_num'] > 0) {
                    // 创建扣除冻结账户记录
                    $flag = CurrencyLockBook::add_log('forzen_num', 'activation', $pid_info['member_id'], Currency::PUBLIC_CHAIN_ID, $CurrencyUser['forzen_num'], $log_id);
                    if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

                    // 扣除冻结账户余额
                    $flag = CurrencyUser::where(['member_id' => $pid_info['member_id'], 'currency_id' => Currency::PUBLIC_CHAIN_ID, 'forzen_num'=>$CurrencyUser['forzen_num']])
                        ->setDec('forzen_num', $CurrencyUser['forzen_num']);
                    if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));
                }

                // 创建扣除可用余额记录
                $flag = AccountBook::add_accountbook($pid_info['member_id'], Currency::PUBLIC_CHAIN_ID, 5301, 'bfw_activation', 'out', $forzen_num, $log_id);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

                // 扣除可用账户余额
                $flag = CurrencyUser::where(['member_id' => $pid_info['member_id'], 'currency_id' => Currency::PUBLIC_CHAIN_ID, 'num' => $CurrencyUser['num']])
                    ->setDec('num', $forzen_num);
                if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));
            }

//            // 扣手续费
//            $flag = AccountBook::add_accountbook($pid_info['member_id'], Currency::PUBLIC_CHAIN_ID, 5301, 'bfw_activation', 'out', $activation_fee, $log_id);
//            if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));
//
//            // 扣除数量及手续费
//            $flag = CurrencyUser::where(['member_id' => $pid_info['member_id'], 'currency_id' => Currency::PUBLIC_CHAIN_ID])->setDec('num', $activation_fee);
//            if (!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

            // 更新用户数据
            $flag = Member::where(['ename'=>$username])->update([
                'pid' => $pid_info['member_id'],
                'status' => 1,
                'active_status' => 1,
                'active_time' => $time,
            ]);
            if(!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

            //添加上下级定时任务
            $flag = MemberBindTask::add_task($member['member_id']);
            if(!$flag) throw new Exception(lang('lan_reg_the_network_busy'));

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_operation_success");
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = lang('lan_reg_the_network_busy');
        }
        return $r;
    }

}
