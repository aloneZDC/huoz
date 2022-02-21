<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\api\controller;

use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\CurrencyUserAwardLog;
use app\common\model\EmailDomain;
use app\common\model\FilMiningConfig;
use app\common\model\FilMiningLevel;
use app\common\model\GoodsMainOrders;
use app\common\model\GoodsOrders;
use app\common\model\HongbaoKeepLog;
use app\common\model\Member;
use app\common\model\MemberBindTask;
use app\common\model\MemberContract;
use app\common\model\RocketConfig;
use app\common\model\ShopConfig;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use Think\Exception;
use think\exception\DbException;
use think\Log;

class Account extends Upload
{
    protected $public_action = ['checkphone', 'checkemail', 'checkusername', 'login', 'phoneaddreg', 'emailaddreg', 'countrylist', 'findpass', 'resetpass', 'is_login_code', 'email_domain', 'findpass_code', 'submit_reg', 'import', 'importReg', 'get_integral', 'integral_log'];
    protected $is_method_filter = true;

    //修改昵称
    public function modifynick()
    {
        $nick = input('post.nick', 'strval');
        if (empty($nick)) $this->output(10001, lang('lan_The_nickname_empty'));

        $nick = str_replace(' ', '', $nick);
        if (strlen($nick) < 4 || strlen($nick) > 30) $this->output(10001, lang('lan_nickname_format_error'));

        $flag = Db::name('member')->field('member_id')->where(['nick' => $nick])->find();
        if ($flag) $this->output(10001, lang('lan_modifymember_nicknames_to_take_up'));

        //$info = Db::name('member')->field('member_id,nick')->where(['member_id'=>$this->member_id])->find();
        //if(!$info || !empty($info['nick'])) $this->output(10001,lang('lan_nickname_cannot_modify'));

        $flag = Db::name('member')->where(['member_id' => $this->member_id])->setField('nick', $nick);
        if ($flag) {
            $this->output(10000, lang('lan_operation_success'));
        } else {
            $this->output(10001, lang('lan_operation_failure'));
        }
    }

    //上传头像
    public function touxiang()
    {
        $img = input('post.img', '');
        if (empty($img)) $this->output(10102, lang('lan_data_error'));

        if (!$this->checkFileSize($img)) $this->output(10102, lang('lan_picture_to_big'));

        $attachments_list = $this->oss_base64_upload($img, 'tuoxiang');
        if (empty($attachments_list) || $attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0) {
            $this->output(ERROR1, lang('lan_network_busy_try_again'));
        }

        $img = $attachments_list['Msg'][0];
        $result = Db::name('member')->where(['member_id' => $this->member_id])->setField('head', $img);
        if ($result) {
            $this->output(10000, lang('lan_operation_success'));
        } else {
            $this->output(10106, lang('lan_network_busy_try_again'));
        }
    }

    //检测手机号是否被注册
    public function checkphone()
    {
        $phone = input("post.phone", '', 'strval,trim');
        if (empty($phone)) $this->output(10001, lang('lan_please_enter_the_correct_mobile_number'));

        $country_code = intval(input('post.country_code'));
        if ($country_code == 86 && !checkMobile($phone)) $this->output(10001, lang('lan_please_enter_the_correct_mobile_number'));

        $reg_phone_max_num = Config::get_value('reg_phone_max_num', 0);
        if ($reg_phone_max_num > 0) {
            $user_count = Db::name('member')->field('member_id')->where(['phone' => $phone])->count();
            if ($user_count >= $reg_phone_max_num) $this->output(10001, lang('lan_phone_reg_num_max'));
        }
//        $r = Db::name('member')->field('member_id')->where(['phone' => $phone])->find();
//        if ($r) $this->output(10001,lang('lan_reg_phone_being'));

//        $username = $phone;
        $username = input("post.username", '', 'strval,trim');
        if (!empty($username)) {
            if (!checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));

            $result = Db::name('member')->field('member_id')->where(['ename' => $username])->find();
            if ($result) $this->output(10001, lang('lan_reg_username_already_exists'));
        } else {
            $this->output(10001, lang("lan_Please_enter_the_correct"));
        }

        $pid = input("post.pid", '', 'strval,trim');
        if (!empty($pid)) {
            $pid = model('member')->toMemberId($pid);
            if (empty($pid)) {
                $this->output(10001, lang('lan_invitation_code_does_not_exist'));//邀請碼不正確
            }
        } else {
            $this->output(10001, lang('lan_invitation_code_does_not_exist'));//邀請碼必填
        }

        $this->output(10000, lang('lan_operation_success'));
    }

    //检测邮箱是否被注册
    public function checkemail()
    {
        $email = input("post.email", '', 'strval,trim');
        if (empty($email) || !checkEmail($email)) $this->output(10001, lang('lan_emial_format_incorrect'));

        $reg_email_max_num = Config::get_value('reg_email_max_num', 0);
        if ($reg_email_max_num > 0) {
            $user_count = Db::name('member')->field('member_id')->where(['email' => $email])->count();
            if ($user_count >= $reg_email_max_num) $this->output(10001, lang('lan_email_reg_num_max'));
        }
//        $result = Db::name('member')->field('member_id')->where(['email' => $email])->find();
//        if ($result) $this->output(10001,lang('lan_reg_mailbox_already_exists'));

//        $username = $email;
        $username = input("post.username", '', 'strval,trim');
        if (!empty($username)) {
            if (!checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));

            $result = Db::name('member')->field('member_id')->where(['ename' => $username])->find();
            if ($result) $this->output(10001, lang('lan_reg_username_already_exists'));
        } else {
            $this->output(10001, lang("lan_Please_enter_the_correct"));
        }

        $pid = input("post.pid", '', 'strval,trim');
        if (!empty($pid)) {
            $pid = model('member')->toMemberId($pid);
            if (empty($pid)) {
                $this->output(10001, lang('lan_invitation_code_does_not_exist'));//邀請碼不正確
            }
        } else {
            $this->output(10001, lang('lan_invitation_code_does_not_exist'));//邀請碼必填
        }

        $this->output(10000, lang('lan_operation_success'));
    }

    //检测用户名是否被注册
    public function checkusername()
    {
        $username = input('post.username', '', 'strval,trim');
        if (empty($username) || !checkUname($username)) $this->output(10001, lang('lan_username_format_incorrect'));

        $result = Db::name('member')->field('member_id')->where(['ename' => $username])->find();
        if ($result) $this->output(10001, lang('lan_reg_username_already_exists'));

        $this->output(10000, lang('lan_operation_success'));
    }

    //更改绑定手机
    public function modifyphone()
    {
        $phone_code = input("post.phone_code", '', 'strval,trim');
        $new_phone = input("post.new_phone", '', 'strval,trim'); //原手机及验证码
        $new_phone_code = input("post.new_phone_code", '', 'strval,trim');
        $country_code = intval(input('post.country_code'));

        $flag = model('member')->modifyphone($this->member_id, $phone_code, $new_phone, $new_phone_code, $country_code);
        if (is_string($flag)) $this->output(10001, $flag);

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    //更改绑定邮箱
    public function modifyemail()
    {
        $email_code = input("post.email_code", '', 'strval,trim');
        $new_email = input("post.new_email", '', 'strval,trim'); //原手机及验证码
        $new_email_code = input("post.new_email_code", '', 'strval,trim');

        $pwd = input('post.pwd');
        $password = Member::verifyPaypwd($this->member_id, $pwd);
        if ($password['code'] != SUCCESS) return $this->output_new($password);

        $flag = model('member')->modifyemail($this->member_id, $email_code, $new_email, $new_email_code);
        if (is_string($flag)) $this->output(10001, $flag);

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    //綁定手機號
    public function bindphone()
    {
        $country_code = intval(input('post.countrycode'));
        $phone = input("post.phone", '', 'strval');
        $phone_code = input("post.phone_code", '', 'strval');

        $flag = model('member')->bindphone($this->member_id, $phone, $phone_code, $country_code);
        if (is_string($flag)) $this->output(10001, $flag);

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    //綁定郵箱
    public function bindemail()
    {
        $email = input('post.email');
        $email_code = input('email_code');

        $flag = model('member')->bindEmail($this->member_id, $email, $email_code);
        if (is_string($flag)) $this->output(10001, $flag);

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    //基本信息
    public function memberinfo()
    {
        $boss_plan = [
            'title' => lang('lan_boss_plan_title'),
            'info' => lang('lan_boss_plan_info'),
            'support' => lang('lan_boss_plan_support'),
        ];

        $setting = [
            'about' => url('mobile/News/detail', ['position_id' => 118]),
            'support' => url('mobile/News/detail', ['position_id' => 178]),
            'contact' => url('mobile/News/detail', ['position_id' => 179]),
            'kf' => url('mobile/Chat/index'),
        ];

        $member_info = Db::name('member')->where(['member_id' => $this->member_id])->find();
        $country = '';
        if (!empty($member_info['country_code'])) {
            $countrycode = Db::name("countries_code")->field($this->lang . '_name as name')->where(['phone_code' => $member_info['country_code']])->find();
            if ($countrycode) $country = $countrycode['name'];
        }

        $head = empty($member_info['head']) ? model('member')->default_head : $member_info['head'];

        $verify_state = Db::name('verify_file')->where(['member_id' => $this->member_id])->value('verify_state');
        if (!$verify_state && !is_numeric($verify_state)) $verify_state = -1;

        $agent_status = model('OtcAgent')->check_info($this->member_id);
        $send_type = $member_info['send_type'];
        if (empty($send_type)) {
            if (!empty($member_info['phone'])) {
                $send_type = 1;
            } elseif (!empty($member_info['email'])) {
                $send_type = 2;
            }
        }

        // 查询等级
        // $config = FilMiningConfig::get_key_value();
        // $fil_mining = \app\common\model\FilMining::where(['member_id'=>$this->member_id,'currency_id'=>$config['pay_currency_id']])->find();
        $level_name = 'H0';
        // if($fil_mining && $fil_mining['level']>0) {
        //     $fil_level = FilMiningLevel::where(['level_id'=>$fil_mining['level']])->find();
        //     if(!empty($fil_level)) $level_name = $fil_level['level_name'];
        // }
//        $chia_mining = \app\common\model\ChiaMiningMember::where(['member_id'=>$this->member_id])->find();
        $chia_mining = \app\common\model\RocketMember::where(['member_id' => $this->member_id])->find();
        $ark_mining = \app\common\model\ArkMember::where(['member_id' => $this->member_id])->find();
        if ($chia_mining) {
            $level_name = 'H' . $chia_mining['level'] . ' | ' . 'Y' . $ark_mining['level'];
            $area_name = $chia_mining['is_area'] == 1 ? '区县合伙人' : '';
            if ($area_name) {
                $level_name = $level_name . ' | ' . $area_name;
            }
        }

        $result = [
            'member_id' => $this->member_id,
            'username' => $member_info['ename'],
            'invitation_code' => $member_info['invit_code'],
            'invit_url' => url('mobile/Invite/index', ['id' => $member_info['invit_code']], false, true),
            'reg_time' => date("Y-m-d", $member_info['reg_time']),
            'phone' => !empty($member_info['phone']) ? substr($member_info['phone'], 0, 3) . '****' . substr($member_info['phone'], 9, 2) : '',
            'account' => !empty($member_info['phone']) ? $member_info['phone'] : $member_info['email'],
            'idcard' => !empty($member_info['idcard']) ? substr($member_info['idcard'], 0, 3) . '****' . substr($member_info['idcard'], -3) : '',
            'cardtype' => $member_info['cardtype'], //1=身份证2=护照 3=军官证 4=其他
            'email' => !empty($member_info['email']) ? substr($member_info['email'], 0, 3) . '****' . substr($member_info['email'], -7) : '',
            'name' => $member_info['name'],
//            'nick' => $member_info['nick'],
            'nick' => $member_info['ename'],
            'head' => $head,
            'country' => $country,
            'verify_state' => $verify_state, //-1未认证 0未通过 1:已认证 2: 审核中
            'verify_time' => $member_info['verify_time'] > 0 ? date('Y-m-d', $member_info['verify_time']) : '',
            'agent_status' => $agent_status['status'],
            'stores_open' => 1,
            'login_type' => $member_info['send_type'],
            'is_set_safe_pwd' => !empty($member_info['pwdtrade']) ? 1 : 2,
            'send_type' => $send_type,
            'chain_browser' => 'http://www.TFT.ltd/',
            'tft' => '',
            'public_chain' => '',
            'google_verify_status' => $member_info['google_secret'] ? 1 : 2,
            'my_active_count' => db('MemberActiveLog')->where(['member_id' => $this->member_id])->count('id'),
            // 'fil_mining_team' => \app\common\model\FilMining::myOneTeamCount($this->member_id),
            // 'level' => $fil_mining ? $fil_mining['level'] : 0,
            'fil_mining_team' => '',
            'level' => '',
            'level_name' => $level_name,
            'is_lock' => $member_info['is_lock'], // otc 0是正常 1是锁定
        ];
        $createAddress = CurrencyUser::createWalletAddress($this->member_id, Currency::PUBLIC_CHAIN_ID);
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, Currency::PUBLIC_CHAIN_ID);
        if (!empty($currencyUser['chongzhi_url'])) {
            $result['public_chain'] = $currencyUser['chongzhi_url'];
        }

        // ABF官网
        $web_url = Config::get_value('abf_web_url');
        $result['web_url'] = $web_url;

        // 区块浏览器
        $chain_browser = Config::get_value('chain_browser');
        $result['chain_browser'] = $chain_browser;

        // ABF force
        $abf_force = Config::get_value('abf_force');
        $result['abf_force'] = $abf_force;

        // 分享 ABF
        $result['abf_share'] = Version_ios_DowUrl;

        // 翻墙地址
        $abf_overTheWall = Config::get_value('abf_overTheWall');
        $result['abf_overTheWall'] = $abf_overTheWall;

        $config = (new Config())->byField();
        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $config['register_handsel_currency_id']);
        $register_handsel = [
//            'is_register_handsel' => $CurrencyUser['register_lock'] > 0.0001 ? 1 : 0,
            'is_register_handsel' => 0,
            'register_handsel_num' => $config['register_handsel_num'],
            'register_handsel_people' => $config['register_handsel_people'],
        ];

        if (empty($result['nick'])) $result['nick'] = !empty($result['phone']) ? $result['phone'] : $result['email'];
        $this->output(10000, lang('lan_operation_success'), ['member' => $result, 'boss_plan' => $boss_plan, 'setting' => $setting, 'register_handsel' => $register_handsel]);
    }

    //检测是否需要验证码登录
    public function is_login_code()
    {
        //$NECaptchaValidate = input("validate");
        //if (!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        //图片验证验证码
        $mark = input("post.validate");
        $img_code = input("post.img_code");
        if (!verify_code($mark, $img_code)) {
            return $this->output(10001, lang('lan_Picture_verification_refresh'));
        }

        $this->do_login(1);
    }

    //登录
    public function login()
    {
//        $mark=input("post.mark");
//        $img_code=input("post.img_code");
//        if(!verify_code($mark,$img_code)){
//           return $this->output(ERROR1,lang('lan_Picture_verification_refresh'));
//        }
        //$NECaptchaValidate = input("validate");
        //if (!$this->checkNECaptchaValidate($NECaptchaValidate)) $this->output(10001,lang('lan_Picture_verification_refresh'));

        $this->do_login(2);
    }


    private function do_login($step)
    {
//        $this->output(10001,lang('数据统计中，将于2019-08-01开放，敬请期待...'));

        $platform = input('post.platform', '', 'strval,trim,strtolower');
        $username = input('post.username', '', 'strval,trim,strtolower');
        $password = input('post.password', '', 'strval');

        if (empty($platform) || !in_array($platform, ['ios', 'android'])) $this->output(10001, lang('lan_Please_import_platform_type'));
        //if (empty($username)) $this->output(10001,lang('lan_Please_enter_the_correct'));
        if (empty($username) /*|| !checkUname($username)*/) $this->output(10008, lang("lan_username_format_incorrect"));
        if (empty($password)) $this->output(10001, lang('lan_login_please_enter_your_password'));

        $email = $username;
        $where = [];
//        $login_type = 0;
//        if (checkEmail($email)) {
//            $where['email'] = $email;
//            $login_type = 2;
//        } else if (checkUname($email)) {
//            $where['ename'] = $email;
//            $login_type = 3;
//        } else {
//            $where['phone'] = $email;
//            $login_type = 1;
//        }
        $where['ename'] = $email;
        $login_type = 3;
        $model = model('member');
        $userInfo = Db::name('member')->where($where)->find();
        if (!$userInfo) $this->output(10001, lang('lan_Account_does_not_exist'));//帐号不存在

        $uuid = input('post.uuid', '', 'strval');
        if (!empty($uuid)) {
            $this->uuid = $uuid;
            $this->cache_name = 'uuid_' . $this->uuid;
        }

        if (empty($uuid)) $this->output(10001, lang('lan_Illegal_operation'));
        $uuid_deny = Db::name('uuid_deny')->where(['uuid' => $uuid])->find();
        if ($uuid_deny) $this->output(10001, lang('lan_Illegal_operation'));

//        $pwd_error_max = intval($this->config['pwd_error_max']);
//        if($pwd_error_max>0 && $userInfo['pwd_error']>=$this->config['pwd_error_max']) $this->output(10001,lang('lan_user_pwd_error_max'));//密码错误

        $login_ip = get_client_ip();
        if (!$model->checkPassword($password, $userInfo['pwd'])) {
            model('member')->addLoginLog($userInfo['member_id'], $uuid, $login_ip, $platform, 0); //增加登录记录
            model('Member')->pwdErrorInc($userInfo['member_id']); //增加密码错误次数
            $this->output(10001, lang('lan_Password_error'));//密码错误
        }

//        if ($userInfo['active_status'] != 1) $this->output(10021,lang('lan_user_not_active'));//帐号未激活，无法登录
        if ($userInfo['status'] == 2) $this->output(10001, lang('lan_The_account_is_locked_and_no_entry'));//帐号被锁定，禁止登陆
        if ($userInfo['is_lock']) $this->output(10001, lang('lan_The_account_is_locked_and_no_entry'));//帐号被锁定，禁止登陆

        //检测是否需要验证码
//        $isCode = model('Member')->checkIsCode($userInfo['member_id'],$uuid);
        $isCode = true; //登录需要验证码
        if ($isCode) {
            $send_type = $login_type;
            if ($login_type == 3) {
                $send_type = $userInfo['send_type'];
                if (empty($send_type)) {
                    if (!empty($userInfo['phone'])) {
                        $send_type = 1;
                        $email = $userInfo['phone'];
                    } elseif (!empty($userInfo['email'])) {
                        $send_type = 2;
                        $email = $userInfo['email'];
                    }
                }
                if ($send_type == 1) {
                    $email = $userInfo['phone'];
                } else {
                    $email = $userInfo['email'];
                }
            }
            if ($step == 1) {
                //自动发送验证码
                if ($send_type == 1) {
                    $send_result = model('Sender')->send_phone($userInfo['country_code'], $email, 'login');
                    $str = substr($email, 0, 3) . '****' . substr($email, -4);
                } else {
                    $send_result = model('Sender')->send_email($email, 'login');
                    $str = substr($email, 0, 3) . '****' . substr($email, -7);
                }
                if (is_string($send_result)) $this->output(10001, $send_result);

                $result = [
                    'email' => $str,
                ];
                $this->output(10000, lang('lan_user_send_success'), $result);
                //$this->output(11000,''.$login_type,['type'=>$login_type]);
            }
//            else {
//                $phone_code = input('phone_code', '', 'strval');
//                if (empty($phone_code)) {
//                    if ($send_type == 1) {
//                        $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));
//                    } else {
//                        $this->output(10001, lang("lan_validation_incorrect"));
//                    }
//                }
//                $senderLog = model('Sender')->check_log($send_type, $email, 'login', $phone_code);
//                if (is_string($senderLog)) $this->output(10001, $senderLog);
//            }
        }

        unset($userInfo['pwd']);

        $token_data = [
            'platform' => $platform, //平台类型
            'uuid' => $this->uuid, //UUID
            'member_id' => $userInfo['member_id'], //用户ID
        ];
        //如果当前操作Ip和上次不同更新登录IP以及登录时间
        $data['login_ip'] = $login_ip;
        $data['login_time'] = time();
        $data['login_type'] = $login_type;

        //生成自动登录签名
        $rsa = new \encrypt\Rsa();
        $token_data = $rsa->joinMapValue($token_data);
        if (empty($userInfo['token_value'])) {
            $userInfo['token'] = base64_encode(md5($token_data . time()) . "|" . $userInfo['member_id']);
            //缓存写入Redis
            $token = cache('auto_login_' . $userInfo['member_id'], $userInfo['token'], $this->login_keep_time);
            $data['token_value'] = $userInfo['token'];
        } else {
            $userInfo['token'] = $userInfo['token_value'];
        }

        $update = $model->where(['member_id' => $userInfo['member_id']])->update($data);
        if ($update === false) $this->output(10001, lang('lan_network_busy_try_again'));

        $head = empty($userInfo['head']) ? model('member')->default_head : $userInfo['head'];

        $verify_state = Db::name('verify_file')->where(['member_id' => $this->member_id])->value('verify_state');
        if (!$verify_state && !is_numeric($verify_state)) $verify_state = -1;

        model('member')->pwdErrorReset($userInfo['member_id']); //重置密码错误次数
        model('member')->addLoginLog($userInfo['member_id'], $uuid, $data['login_ip'], $platform); //增加登录记录

        if (isset($senderLog)) model('Sender')->hasUsed($senderLog['id']);

        //本版本没有实名认证
        $agent_status = model('OtcAgent')->check_info($userInfo['member_id']);
        $send_type = $userInfo['send_type'];
        if (empty($send_type)) {
            if (!empty($userInfo['phone'])) {
                $send_type = 1;
            } elseif (!empty($userInfo['email'])) {
                $send_type = 2;
            }
        }
        $account = [
            'platform' => $platform, //平台类型
            'token' => $userInfo['token'], //返回token，以便下次自动登录使用
            'user_uuid' => $this->uuid, //uuid
            'user_id' => $userInfo['member_id'], //用户ID
            'member_id' => $userInfo['member_id'], //用户ID
            'username' => $userInfo['ename'],
            'phone' => $userInfo['phone'],
            'email' => $userInfo['email'],
            'idcard' => !empty($userInfo['idcard']) ? substr($userInfo['idcard'], 0, 3) . '****' . substr($userInfo['idcard'], -3) : '',
            'cardtype' => $userInfo['cardtype'], //1=身份证2=护照 3=军官证 4=其他
            'user_head' => $head,
            'user_name' => $userInfo['name'], //真实姓名
            'verify_state' => $verify_state,
            'user_nick' => $userInfo['nick'],
            'hx_username' => 0,
            'hx_password' => 0,
            'login_type' => $login_type,
            'agent_status' => $agent_status['status'],
            'is_set_safe_pwd' => !empty($userInfo['pwdtrade']) ? 1 : 2,
            'send_type' => $send_type,
            'google_verify_status' => $userInfo['google_secret'] ? 1 : 2,
        ];

        if (!empty($this->uuid)) cache($this->cache_name, $account, $this->login_keep_time);

        $this->output(10000, lang('lan_operation_success'), $account);
    }

    /**
     * 手机号注册
     * @return int
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function phoneAddReg()
    {
//        return $this->output_new(ERROR1, lang('lan_close'), null);
        $country_code = intval(input('post.countrycode'));
        if (empty($country_code)) $this->output(10001, lang("lan_No_incoming_country_code"));    //没有传入国家编码

        $country = Db::name('countries_code')->where(['phone_code' => $country_code, 'status' => 1])->find();
        if (empty($country)) $this->output(10001, lang("lan_No_incoming_country_code"));    //没有传入国家编码

        $phone = input("post.phone", '', 'strval,trim');
        if (empty($phone)) $this->output(10005, lang('lan_please_enter_the_correct_mobile_number'));

        $username = input('post.username', '', 'strval,trim');
        if (empty($username) || !checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));
        $model = model('member');
        //$r = $model->field('member_id')->where(['phone'=>$phone])->find();
        //if ($r) $this->output(10008, lang('lan_reg_phone_being'));
        $r = $model->field('member_id')->where(['ename' => $username])->find();
        if ($r) $this->output(10008, lang("lan_reg_username_already_exists"));

        $checkRegMax = model('Member')->checkRegMax('phone', $phone);
        if (is_string($checkRegMax)) $this->output(10009, $checkRegMax);
//        $regMax = Config::get_value('reg_phone_max_num', 10);
//        $count = $model->where(['phone'=>$phone])->count();
//        if ($count >= $regMax) $this->output(10009, lang('lan_phone_reg_num_max'));

        $phone_code = input("post.phone_code", '', 'strval');
        if (empty($phone_code)) $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));

        $senderLog = model('Sender')->check_log(1, $phone, 'register', $phone_code);
        if (is_string($senderLog)) $this->output(10001, $senderLog);

        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');
        $pwdtrade = input('post.pwdtrade', 'strval');
        $repwdtrade = input('post.repwdtrade', '', 'strval');
        $pid = input('post.pid', '', 'strval');
//        $pid = '';
        $flag = $model->checkReg($pwd, $repwd, $pwdtrade, $repwdtrade, $pid, true);
        if (is_string($flag)) $this->output(10001, $flag);

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
        $flag = $model->addReg(3, $data, $this->config);
        if (is_string($flag)) $this->output(10001, $flag);

        //验证码设置为已使用
        model('Sender')->hasUsed($senderLog['id']);
        $this->output(10000, lang('lan_operation_success')/*,HongbaoKeepLog::reward_config()*/);
    }

    /**
     * 郵箱注册
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function emailAddReg()
    {
//        return $this->output_new(ERROR1, lang('lan_close'), null);
        $email = input('post.email', '', 'strval,trim');
        if (empty($email) || !checkEmail($email)) $this->output(10008, lang("lan_emial_format_incorrect"));
        $username = input('post.username', '', 'strval,trim');
        if (empty($username) || !checkUname($username)) $this->output(10008, lang("lan_username_format_incorrect"));

        $model = model('member');
        //$r = $model->field('member_id')->where(['email' => $email])->find();
        //if ($r) $this->output(10008, lang("lan_reg_mailbox_already_exists"));
        $r = $model->field('member_id')->where(['ename' => $username])->find();
        if ($r) $this->output(10008, lang("lan_reg_username_already_exists"));

        $checkRegMax = model('Member')->checkRegMax('email', $email);
        if (is_string($checkRegMax)) $this->output(10009, $checkRegMax);
//        $regMax = Config::get_value('reg_email_max_num', 10);
//        $count = $model->where(['email'=>$email])->count();
//        if ($count >= $regMax) $this->output(10009, lang('lan_email_reg_num_max'));

        $email_code = input('post.email_code');
        if (empty($email_code)) $this->output(10008, lang("lan_validation_incorrect"));

        $senderLog = model('Sender')->check_log(2, $email, 'register', $email_code);
        if (is_string($senderLog)) $this->output(10001, $senderLog);

        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');
        $pwdtrade = input('post.pwdtrade', 'strval');
        $repwdtrade = input('post.repwdtrade', '', 'strval');
        $pid = input('post.pid', '', 'strval');
//        $pid = '';
        $flag = $model->checkReg($pwd, $repwd, $pwdtrade, $repwdtrade, $pid, true);
        if (is_string($flag)) $this->output(10001, $flag);

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
        $flag = $model->addReg(3, $data, $this->config);
        if (is_string($flag)) $this->output(10001, $flag);

        //验证码设置为已使用
        model('Sender')->hasUsed($senderLog['id']);
        $this->output(10000, lang('lan_operation_success')/*,HongbaoKeepLog::reward_config()*/);
    }

    /**
     * 修改交易密码
     */
    public function modifytradepwd()
    {
        $oldPwd = input('post.oldpwd', '', 'strval');
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');

        $model = model('member');
        $flag = $model->checkPwdTradeModif($newPwd, $rePwd, $oldPwd);
        if (is_string($flag)) $this->output(10103, $flag);

        $phone_user = Db::name('member')->field('pwd,pwdtrade')->where(['member_id' => $this->member_id])->find();
        if (!$phone_user) $this->output(10103, lang('lan_Password_error'));

        $old_pwd = $model->password($oldPwd);
        if ($old_pwd != $phone_user['pwdtrade']) $this->output(10103, lang('lan_Password_error'));

        $new_password = $model->password($newPwd);
        if ($new_password == $phone_user['pwd']) $this->output(10001, lang('lan_user_Transaction_password_login')); //交易密碼和登錄密碼不能一致

        $update = $model->where(['member_id' => $this->member_id])->update(['pwdtrade' => $new_password, 'pwdtrade_error' => 0]);
        if ($update === false) $this->output(10106, lang('lan_network_busy_try_again'));

        $this->output(10000, lang('lan_operation_success'));
    }

    /**
     * 找回交易密码
     */
    public function findtradepwd()
    {
        $phone_code = input('post.phone_code', '', 'strval');
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');

        $phone_user = Db::name('member')->field('phone,email,country_code,pwd,pwdtrade,login_type,send_type')->where(['member_id' => $this->member_id])->find();
        if (!$phone_user) $this->output(10001, lang('lan_input_personal_info'));

        if (!empty($phone_user['phone']) && $phone_user['send_type'] == 1) {
            $senderLog = model('Sender')->check_log(1, $phone_user['phone'], 'retradepwd', $phone_code);
            if (is_string($senderLog)) $this->output(10001, $senderLog);
        } else {
            $senderLog = model('Sender')->check_log(2, $phone_user['email'], 'retradepwd', $phone_code);
            if (is_string($senderLog)) $this->output(10001, $senderLog);
        }

        $model = model('member');
        $flag = $model->checkPwdTradeModif($newPwd, $rePwd);
        if (is_string($flag)) $this->output(10103, $flag);

        $new_password = $model->password($newPwd);
        if ($new_password == $phone_user['pwd']) $this->output(10103, lang('lan_user_Transaction_password_login')); //交易密碼和登錄密碼不能一致

        $update = $model->where(['member_id' => $this->member_id])->update(['pwdtrade' => $new_password, 'pwdtrade_error' => 0]);
        if ($update === false) $this->output(10106, lang('lan_network_busy_try_again'));

        model('Sender')->hasUsed($senderLog['id']);
        $this->output(10000, lang('lan_operation_success'));
    }

    //通过手机号修改密码
    public function modifypwd()
    {
        $phone_code = input('post.phone_code', '', 'strval');
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');

        $phone_user = Db::name('member')->field('phone,email,country_code,pwd,pwdtrade,login_type,send_type')->where(['member_id' => $this->member_id])->find();
        if (!$phone_user) $this->output(10001, lang('lan_input_personal_info'));

        if (!empty($phone_user['phone']) && $phone_user['send_type'] == 1) {
            $senderLog = model('Sender')->check_log(1, $phone_user['phone'], 'modifypwd', $phone_code);
            if (is_string($senderLog)) $this->output(10001, $senderLog);
        } else {
            $senderLog = model('Sender')->check_log(2, $phone_user['email'], 'modifypwd', $phone_code);
            if (is_string($senderLog)) $this->output(10001, $senderLog);
        }

        $model = model('member');
        $flag = $model->checkPwdModif($newPwd, $rePwd);
        if (is_string($flag)) $this->output(10103, $flag);

        $new_password = $model->password($newPwd);
        if ($new_password == $phone_user['pwdtrade']) $this->output(10001, lang('lan_user_Transaction_password_login'));

        $update = $model->where(['member_id' => $this->member_id])->setField('pwd', $new_password);
        if ($update === false) $this->output(10106, lang('lan_network_busy_try_again'));
        model('Sender')->hasUsed($senderLog['id']);

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    /**
     * 修改密码
     */
    public function repass()
    {
        $oldPwd = input('post.oldpwd', '', 'strval');
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');

        $model = model('member');
        $flag = $model->checkPwdModif($newPwd, $rePwd, $oldPwd);
        if (is_string($flag)) $this->output(10103, $flag);

        $phone_user = Db::name('member')->field('pwd,pwdtrade')->where(['member_id' => $this->member_id])->find();
        if (!$phone_user) $this->output(10103, lang('lan_Password_error'));

        $old_pwd = $model->password($oldPwd);
        if ($old_pwd != $phone_user['pwd']) $this->output(10103, lang('lan_Password_error'));

        $new_password = $model->password($newPwd);
        if ($new_password == $phone_user['pwdtrade']) $this->output(10001, lang('lan_user_Transaction_password_login')); //交易密碼和登錄密碼不能一致

        $update = $model->where(['member_id' => $this->member_id])->setField('pwd', $new_password);
        if ($update === false) $this->output(10106, lang('lan_network_busy_try_again'));

        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_operation_success'));
    }

    //找回密码 发送验证码
    public function findpass_code()
    {

        //图片验证验证码
        $mark = input("post.validate");
        $img_code = input("post.img_code");
        if (!verify_code($mark, $img_code)) {
            return $this->output(10001, lang('lan_Picture_verification_refresh'));
        }

        $username = input('post.username', '', 'strval,trim,strtolower');
        $where['ename'] = $username;

        $info = Db::name('Member')->where($where)->find();
        if (!$info) $this->output(10001, lang('lan_Account_does_not_exist'));

        $send_type = $info['send_type'];
        if (empty($send_type)) {
            if (!empty($info['phone'])) {
                $send_type = 1;
            } elseif (!empty($info['email'])) {
                $send_type = 2;
            } else {
                $send_type = 1;
            }
        }

        //自动发送验证码
        if ($send_type == 1) {
            $send_result = model('Sender')->send_phone($info['country_code'], $info['phone'], 'findpwd');
            $str = substr($info['phone'], 0, 3) . '****' . substr($info['phone'], -4);
        } else {
            $send_result = model('Sender')->send_email($info['email'], 'findpwd');
            $str = substr($info['email'], 0, 3) . '****' . substr($info['email'], -7);
        }
        if (is_string($send_result)) $this->output(10001, $send_result);

        $result = [
            'email' => $str,
        ];
        $this->output(10000, lang('lan_user_send_success'), $result);
    }

    //找回密码
    public function findpass()
    {
        $phone = input('post.phone');
        if (empty($phone)) $this->output(10106, lang('lan_login_please_enter_your_mobile_number'));
        $phone_code = input('post.phone_code');

        $where = [];
//        $send_type = 1;
//        if(checkEmail($phone)){
//            $where['email'] = $phone;
//            $send_type = 2;
//        } else {
//            $where['phone'] = $phone;
//            $send_type = 1;
//        }
        $where['ename'] = $phone;
        $info = Db::name('Member')->where($where)->find();
        if (!$info) $this->output(10001, lang('lan_Account_does_not_exist'));

        $send_type = $info['send_type'];
        if (empty($send_type)) {
            if (!empty($info['phone'])) {
                $send_type = 1;
            } elseif (!empty($info['email'])) {
                $send_type = 2;
            } else {
                $send_type = 1;
            }
        }
        if ($send_type == 1) {
            $send_url = $info['phone'];
        } else {
            $send_url = $info['email'];
        }

        $senderLog = model('Sender')->check_log($send_type, $send_url, 'findpwd', $phone_code);
//        $senderLog = model('Sender')->check_log($send_type,$phone,'findpwd',$phone_code);
        if (is_string($senderLog)) $this->output(10001, $senderLog);

        $token = strtoupper(md5($phone) . md5(time()));
        $id = Db::name('findpwd')->insertGetID([
            'member_id' => $info['member_id'],
            'token' => $token,
            'add_time' => time(),
        ]);
        if ($id) {
            model('Sender')->hasUsed($senderLog['id']);
            $this->output(10000, lang('lan_operation_success'), ['token' => $token]);
        } else {
            $this->output(10106, lang('lan_network_busy_try_again'));
        }
    }

    //重置密码,根据token
    public function resetpass()
    {
        $pwd = input('post.pwd', 'strval');
        $repwd = input('post.repwd', '', 'strval');

        $model = model('member');
        $flag = $model->checkPwdModif($pwd, $repwd);
        if (is_string($flag)) $this->output(10103, $flag);

        $phone = input('phone');

        $where = [];
//        if(checkEmail($phone)){
//            $where['email'] = $phone;
//        } else {
//            $where['phone'] = $phone;
//        }
        $where['ename'] = $phone;
        $info = Db::name('Member')->where($where)->find();
        if (!$info) $this->output(10001, lang('lan_Account_does_not_exist'));

        $new_password = $model->password($pwd);
        if ($info['pwdtrade'] == $new_password) $this->output(10001, lang('lan_user_Transaction_password_login'));

        $token = input('token');
        if (empty($token)) $this->output(10106, lang('The_has_expired'));

        $token_info = Db::name('findpwd')->where(['member_id' => $info['member_id'], 'token' => $token])->find();

        $stop_time = time() - 24 * 60 * 60;
        if (!$token_info || $token_info['add_time'] < $stop_time) $this->output(10106, lang('The_has_expired'));

        $result = Db::name('member')->where(['member_id' => $info['member_id']])->update(['pwd' => $new_password, 'pwd_error' => 0, 'pwdtrade_error' => 0]);
        if ($result !== false) {
            Db::name('findpwd')->where(['id' => $token_info['id']])->delete();
            $this->output(10000, lang('lan_operation_success'));
        } else {
            $this->output(10106, lang('lan_network_busy_try_again'));
        }
    }

    public function invites()
    {
        $list = Member::where('pid', $this->member_id)->field('member_id, nick, FROM_UNIXTIME(reg_time,"%Y-%m-%d %H:%m:%s") as reg_time')->select();
        $income = CurrencyUserAwardLog::where('type', 'in', [CurrencyUserAwardLog::TYPE_REG, CurrencyUserAwardLog::TYPE_ONE_INVITE, CurrencyUserAwardLog::TYPE_TWO_INVITE])->sum('num');
        $data = [
            'list' => $list,
            'fans' => count($list),
            'total_income' => $income
        ];

        return $this->output(SUCCESS, lang('data_success'), $data);
    }

    /**
     * 我的好友@标
     */
    public function my_friend_list()
    {

        $model_member = new Member();
        $page = input('post.page', 1, 'intval,filter_page');
        $page_size = input('post.page_size', 10, 'intval,filter_page');
        $r = $model_member->my_friend($this->member_id, $page, $page_size);
        $this->output_new($r);
    }

    //国家与民族列表@标
    public function country_list()
    {
        $model_member = new Member();
        $lang = $this->getLang();
        $nation_list = $model_member->nation($lang . "_name as nation_name,nation_id");
        $countries_list = $model_member->countries_code($lang . "_name as name,phone_code");
        $r['result']['nation'] = $nation_list;
        $r['result']['countries'] = $countries_list;
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $this->output_new($r);
    }

    //添加实名验证@标
    public function member_verify()
    {
        $r['result'] = [];
        $r['code'] = ERROR1;
        $model_member = new Member();
        $post = input('post.');
        $post['member_id'] = $this->member_id;
        $post['nation_id'] = input('post.nation_id', 0, 'intval');
        if (!empty($post['pic1'])) {
            $upload = $this->base64Upload($post['pic1']);
            if (is_string($upload)) {
                $r['message'] = lang("lan_no_certificates_pic");
                $this->output_new($r);
            }
            $post['pic1'] = $upload['path'];
        }
        if (!empty($post['pic2'])) {
            $upload = $this->base64Upload($post['pic2']);
            if (is_string($upload)) {
                $r['message'] = lang("lan_no_certificates_pic");
                $this->output_new($r);
            }
            $post['pic2'] = $upload['path'];
        }
        if (!empty($post['pic3'])) {
            $upload = $this->base64Upload($post['pic3']);
            if (is_string($upload)) {
                $r['message'] = lang("lan_no_certificates_pic");
                $this->output_new($r);
            }
            $post['pic3'] = $upload['path'];
        }
        $r = $model_member->add_verify($post);
        $this->output_new($r);
    }


    /**
     * 退出登录
     */
    public function logout()
    {
        $this->_logout($this->member_id);
        $this->output(10000, lang('lan_uc_exit_success'));
    }

    /**
     * 国家列表
     */
    public function countrylist()
    {
        $countries = Db::name("countries_code")->field($this->lang . '_name as name,phone_code as countrycode')->where('status=1')->order('sort asc')->select();
        $this->output(10000, lang('lan_operation_success'), $countries);
    }

    /**
     * 清除登录信息 实现
     * @param $member_id
     */
    private function _logout($member_id)
    {
        cache('auto_login_' . $member_id, null);
        cache($this->cache_name, null);
    }

    /**
     * 是否登陆
     * @param bool $request_type
     * @return bool
     */
    public function is_login()
    {
        $this->output(10000, lang('lan_operation_success'));
    }

    public function email_domain()
    {
        $this->output(10000, lang('lan_operation_success'), EmailDomain::get_list());
    }

    /**
     * 邀请激活初始化
     */
    public function invite_active_init()
    {

        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $result = [
            'number' => $number,
            'give_num' => $giveNum,
            'currency_name' => $currency['currency_name'],
        ];
        $this->output(10000, lang('lan_operation_success'), $result);
    }

    /**
     * 邀请激活
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function invite_active()
    {

        $username = input('post.username', '', 'strval');

        $userInfo = Db::name('member')->where(['member_id' => $this->member_id])->find();
        if ($userInfo['active_status'] != 1) $this->output(10001, lang('lan_user_not_active'));//帐号未激活，无法登录

        $activeUser = null;
        if (checkUname($username)) {
            $activeUser = Db::name('member')->where(['ename' => $username])->find();
        }
        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $currency_id = $currency['currency_id'];
        if (!$activeUser) {
            //从充币地址查找用户
            $where = [
                'currency_id' => $currency_id,
                'chongzhi_url' => $username,
            ];
            $activeUserCurrency = CurrencyUser::where($where)->find();
            $activeUser = Db::name('member')->where('member_id', $activeUserCurrency['member_id'])->find();
        }
        if (!$activeUser) $this->output(10001, lang('lan_Account_does_not_exist'));//帐号不存在

        if ($activeUser['active_status'] == 1) $this->output(10001, lang('lan_user_already_active'));//已激活

        $userCurrency = CurrencyUser::getCurrencyUser($this->member_id, $currency_id);
        $activeUserCurrency = CurrencyUser::getCurrencyUser($activeUser['member_id'], $currency_id);

        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $fee = floattostr(bcsub($number, $giveNum, 6));
        if (bccomp(floattostr($userCurrency['num']), $number, 6) == -1) {
            $this->output(10001, lang('insufficient_balance'));
        }

        try {
            Db::startTrans();

            $flag = Member::where(['member_id' => $activeUser['member_id'], 'active_status' => 0])->update([
                'active_status' => 1,
                'active_time' => time(),
                'pid' => $this->member_id,
            ]);
            if (!$flag) throw new Exception('更新激活用户信息失败-in line:' . __LINE__);

            $flag = $log_id = db('MemberActiveLog')->insertGetId([
                'member_id' => $this->member_id,
                'active_member_id' => $activeUser['member_id'],
                'currency_id' => $currency_id,
                'num' => $number,
                'give_num' => $giveNum,
                'fee' => $fee,
                'add_time' => time(),
            ]);
            if ($flag === false) throw new Exception('添加激活记录失败-in line:' . __LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id' => $this->member_id,
                'currency_id' => $currency_id,
                'number_type' => 2,
                'number' => $number,
                'type' => 2700,
                'content' => "lan_invite_active",
                'fee' => $fee,
                'to_member_id' => $activeUser['member_id'],
                'to_currency_id' => 0,
                'third_id' => $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:' . __LINE__);

            //操作账户
            $flag = setUserMoney($this->member_id, $currency_id, $number, 'dec', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:' . __LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id' => $activeUser['member_id'],
                'currency_id' => $currency_id,
                'number_type' => 1,
                'number' => $giveNum,
                'type' => 2700,
                'content' => "lan_invite_active",
                'fee' => 0,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:' . __LINE__);

            //操作账户
            $flag = setUserMoney($activeUser['member_id'], $currency_id, $giveNum, 'inc', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:' . __LINE__);

            //添加用户上下级关系定时任务
            $flag = MemberBindTask::add_task($activeUser['member_id']);
            if ($flag === false) throw new Exception('添加关系定时任务失败-in line:' . __LINE__);

            Db::commit();
            $this->output(SUCCESS, lang('lan_user_active_success'));
        } catch (Exception $e) {
            Db::rollback();

            $this->output(ERROR1, lang('lan_network_busy_try_again') . ',异常信息:' . $e->getMessage());
        }
    }

    /**
     * 创建一个google验证的私钥
     */
    function create_google_secret()
    {
        $user = Member::where(['member_id' => $this->member_id])->field("member_id,active_status")->find();
        if ($user['active_status'] != 1) $this->output(10021, lang('lan_user_not_active'));//帐号未激活，无法登录
        $result = (new Member())->add_secret($this->member_id);
        $this->output_new($result);
    }

    /**
     * 谷歌认证
     * @return \json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    function google_verify()
    {
        $google_secret = input('google_secret', '', 'strval');
        $google_code = input('google_code', '', 'strval');

        $member = Db::name("member")->field("login_type,email,phone,country_code,send_type")->where(['member_id' => $this->member_id, 'is_lock' => 0])->find();
        if (!$member) $this->output(10001, lang('lan_Account_does_not_exist'));//帐号不存在
        $send_type = $member['send_type'];
        $email = $member['email'];
        if (empty($send_type)) {
            if (!empty($member['phone'])) {
                $send_type = 1;
                $email = $member['phone'];
            } elseif (!empty($member['email'])) {
                $send_type = 2;
                $email = $member['email'];
            }
        } else {
            if ($send_type == 1) {
                $email = $member['phone'];
            }
        }

        $phone_code = input('phone_code', '', 'strval');
        if (empty($phone_code)) {
            if ($send_type == 1) {
                $this->output(10001, lang('lan_The_phone_verification_code_can_not_be_empty'));
            } else {
                $this->output(10001, lang("lan_validation_incorrect"));
            }
        }
        $senderLog = model('Sender')->check_log($send_type, $email, 'google_verify', $phone_code);
        if (is_string($senderLog)) $this->output(10001, $senderLog);

        //谷歌认证
        $google_result = (new Member())->google_verify($this->member_id, $google_secret, $google_code);
        if ($google_result['code'] == SUCCESS) {
            //验证码设置为已使用
            model('Sender')->hasUsed($senderLog['id']);
        }
        $this->output_new($google_result);
    }

    /**
     * 注册账号
     */
    public function submit_reg()
    {
        $uuid = input('uuid');
        $public_key = input('public_key');
        $private_key = input('private_key');
        $username = input('username');
        if (empty($uuid) || empty($public_key) || empty($private_key) || empty($username) || !checkUname($username)) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        $app_version = strval(input('post.cur_version', ''));
        if (intval(str_replace('.', '', $app_version)) < intval(str_replace('.', '', Version_Android))) {
            $isDeny = Config::get_value('low_version_deny_reg', 1);
            if ($isDeny == 1) $this->output(ERROR1, '');
        }

        $ip_limit = intval(Config::get_value('regip_limit', 10));
        $ip_count = 0;
        $ip = get_client_ip();
        $ip_limit_cahce_key = 'regip_' . $ip;
        if ($ip_limit > 0) {
            $ip_count = intval(cache($ip_limit_cahce_key));
            if ($ip_count >= $ip_limit) {
                Log::write("ip limit" . $ip);
                $this->output(ERROR1, lang('注册异常，IP已被限制'));
            }
        }

        // 解密私钥
        $private_key = decryption_private_key($private_key);
        if (strlen($private_key) < 51) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        $create_result = (new Member())->submit_reg($uuid, $public_key, $private_key, $username, $ip_count);
        if ($ip_limit > 0 && $create_result['code'] == SUCCESS) {
            cache($ip_limit_cahce_key, $ip_count + 1);
        }

        $this->output_new($create_result);
    }

    /**
     * 激活账号
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function activation()
    {
        $username = input('username');
        if (empty($username)) {
            $this->output(ERROR1, lang('parameter_error'));
        }
        $create_result = (new Member())->activation($username, $this->member_id);
        $this->output_new($create_result);
    }

    /**
     * 导入私钥
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function import()
    {
        $private_key = input('private_key');
        if (empty($private_key)) $this->output(ERROR1, lang('parameter_error'));

        $private_key = decryption_private_key($private_key); // 解密私钥
        $Member = Member::where(['private_key' => $private_key])->find();
        if (empty($Member)) $this->output(ERROR1, lang('account_not_exists'));

        // 判断用户是否激活
        if (empty($Member['active_status'])) {
            $this->output(ERROR1, lang('lan_user_not_active'));
        }

        $data = [
            'username' => $Member['ename'],
            'public_key' => $Member['public_key'],
        ];
        $this->output(SUCCESS, lang('lan_operation_success'), $data);
    }

    /**
     * 7号矿机导入密钥
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function importReg()
    {
        $platform = input('post.platform', '', 'strval,trim,strtolower');
        if (empty($platform) || !in_array($platform, ['ios', 'android'])) $this->output(10001, lang('lan_Please_import_platform_type'));

        $private_key = input('private_key');
        if (empty($private_key)) $this->output(ERROR1, lang('parameter_error'));

        $private_key = decryption_private_key($private_key); // 解密私钥
        $userInfo = Member::where(['private_key' => $private_key])->find();
        if (empty($userInfo)) $this->output(ERROR1, lang('account_not_exists'));
        if (!empty($userInfo['email']) || !empty($userInfo['phone'])) {
            $this->output(ERROR1, lang('already_not_exists'));
        }

        $username = input('post.username', '', 'strval,trim');
        if (empty($username) || !checkUname($username)) $this->output(ERROR1, lang("lan_username_format_incorrect"));
        $Member_ename = Member::field('member_id')->where(['ename' => $username])->find();
        if ($Member_ename) $this->output(ERROR1, lang("lan_reg_username_already_exists"));
        $data['ename'] = $username;

        $phone = input("phone", '');
        $reg_type = input('reg_type', 1);
        if ($reg_type != 1) { // 邮箱
            if (empty($phone) || !checkEmail($phone)) $this->output(ERROR1, lang("lan_emial_format_incorrect"));
            $data['email'] = $phone;
        } else {
            if (empty($phone)) $this->output(ERROR1, lang('lan_please_enter_the_correct_mobile_number'));
            $country_code = intval(input('post.country_code'));
            if ($country_code == 86 && !checkMobile($phone)) $this->output(ERROR1, lang('lan_emial_format_incorrect'));
            $data['phone'] = $phone;
        }

        $phone_code = input('post.phone_code');
        if (empty($phone_code)) $this->output(ERROR1, lang("lan_validation_incorrect"));
        $senderLog = model('Sender')->check_log($reg_type, $phone, 'register', $phone_code);
        if (is_string($senderLog)) $this->output(ERROR1, $senderLog);

        $password = input('post.password', '', 'strval');
        if (empty($password)) $this->output(ERROR1, lang('lan_login_please_enter_your_password'));
        if (!model('member')->checkPwd($password)) $this->output(ERROR1, lang('lan_password_format_error'));
        $data['pwd'] = model('member')->password($password);

        $pwdtrade = input('post.pwdtrade', '', 'strval');
        if (empty($pwdtrade)) $this->output(ERROR1, lang('lan_user_Transaction_password_empty1'));
        if (!model('member')->checkPwdTrade($pwdtrade)) $this->output(ERROR1, lang('lan_user_Transaction_password_space'));
        $data['pwdtrade'] = model('member')->password($pwdtrade);

        //生成邀请码
        $invit_code = model('member')->getInviteCode($userInfo['member_id']);
        $data['invit_code'] = $invit_code;

        $uuid = input('post.uuid', '', 'strval');
        if (!empty($uuid)) {
            $this->uuid = $uuid;
            $this->cache_name = 'uuid_' . $this->uuid;
        }
        $token_data = [
            'platform' => $platform, //平台类型
            'uuid' => $this->uuid, //UUID
            'member_id' => $userInfo['member_id'], //用户ID
        ];
        //生成自动登录签名
        $rsa = new \encrypt\Rsa();
        $token_data = $rsa->joinMapValue($token_data);
        $userInfo['token'] = base64_encode(md5($token_data . time()) . "|" . $userInfo['member_id']);

        //如果当前操作Ip和上次不同更新登录IP以及登录时间
        $data['login_ip'] = get_client_ip();
        $data['login_time'] = time();
        $data['login_type'] = $reg_type;
        $data['token_value'] = $userInfo['token'];
        $update = Member::where('member_id', $userInfo['member_id'])->update($data);
        if ($update === false) $this->output(ERROR1, lang('lan_network_busy_try_again'));

        //验证码设置为已使用
        model('Sender')->hasUsed($senderLog['id']);

        //缓存写入Redis
        $token = cache('auto_login_' . $userInfo['member_id'], $userInfo['token'], $this->login_keep_time);

        $head = empty($userInfo['head']) ? model('member')->default_head : $userInfo['head'];
        $account = [
            'platform' => $platform, //平台类型
            'token' => $userInfo['token'], //返回token，以便下次自动登录使用
            'user_uuid' => $this->uuid, //uuid
            'member_id' => $userInfo['member_id'], //用户ID
            'username' => $userInfo['ename'],
            'phone' => $userInfo['phone'],
            'email' => $userInfo['email'],
            'user_head' => $head,
            'user_nick' => $userInfo['nick'],
            'login_type' => $reg_type,
        ];
        if (!empty($this->uuid)) cache($this->cache_name, $account, $this->login_keep_time);
        $this->output(SUCCESS, lang('lan_operation_success'), $account);
    }

    // 获取积分信息
    public function get_integral()
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $integral_currency_id = \app\common\model\RocketConfig::getValue('integral_currency_id');
        if (!$integral_currency_id) $this->output_new($r);

        $currency_user = CurrencyUser::getCurrencyUser($this->member_id, $integral_currency_id);
        if (empty($currency_user)) $this->output_new($r);
        $res = CurrencyUser::alias('a')->join('currency b', 'a.currency_id=b.currency_id')
            ->field('a.num, b.currency_id,b.currency_name')
            ->where(['a.currency_id' => $integral_currency_id, 'member_id' => $this->member_id])->find();
        if (empty($res)) $this->output_new($r);

        $give_currency_id = ShopConfig::get_value('give_currency_id');
        if (!$give_currency_id) $this->output_new($r);
        $currency_user = CurrencyUser::getCurrencyUser($this->member_id, $give_currency_id);
        if (empty($currency_user)) $this->output_new($r);
        $res_huo = CurrencyUser::alias('a')->join('currency b', 'a.currency_id=b.currency_id')
            ->field('a.num_award as num, b.currency_id,b.currency_name')
            ->where(['a.currency_id' => $give_currency_id, 'member_id' => $this->member_id])->find();
        if (empty($res_huo)) $this->output_new($r);

        $GoodsMainOrders = GoodsMainOrders::where(['gmo_status' => ['in', [1, 3, 6]], 'gmo_user_id' => $this->member_id])
            ->field('count(*) as tp_count,gmo_status')->group('gmo_status')
            ->select();
        $order_num = [
            'due_out' => 0,
            'due_in' => 0,
            'due_ti' => 0,
        ];
        foreach ($GoodsMainOrders as $item) {
            if ($item['gmo_status'] == 1) {
                $order_num['due_out'] = $item['tp_count'];
            }
            if ($item['gmo_status'] == 3) {
                $order_num['due_in'] = $item['tp_count'];
            }
            if ($item['gmo_status'] == 6) {
                $order_num['due_ti'] = $item['tp_count'];
            }
        }

        $market_warehouse = Db::name('rocket_goods')->sum('warehouse2');
        $tool_warehouse = Db::name('rocket_goods')->sum('warehouse3');
        $show_user_id = explode(',', RocketConfig::getValue('show_user_id'));
        $is_show = 0;
        if (in_array($this->member_id, $show_user_id)) {
            $is_show = 1;
        }

        $ark_market_warehouse = Db::name('ark_goods')->sum('warehouse2');
        $ark_tool_warehouse = Db::name('ark_goods')->sum('warehouse3');
        $ark_show_user_id = explode(',', \app\common\model\ArkConfig::getValue('show_user_id'));
        $is_ark_show = 0;
        if (in_array($this->member_id, $ark_show_user_id)) {
            $is_ark_show = 1;
        }
        $reward_currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
        $currency_user = CurrencyUser::getCurrencyUser($this->member_id, $reward_currency_id);
        if (empty($currency_user)) throw new Exception(lang('lan_network_busy_try_again'));
        $register_num = $currency_user['lock_num'];
        $bonus = \app\common\model\RocketWelfare::where(['id' => 1])->value('num');

        $MemberContract = MemberContract::where(['member_id' => $this->member_id])->find();
        $is_sign = 0;
        if ($MemberContract) $is_sign = 1;

        $r['result'] = [
            'is_sign' => $is_sign,
            'money_jin' => $res,
            'money_huo' => $res_huo,
            'order_num' => $order_num,
            'market' => [
                'market_warehouse' => sprintf('%.2f', $market_warehouse),//市值舱
                'tool_warehouse' => sprintf('%.2f', $tool_warehouse),//工具舱
                'is_show' => $is_show//1显示市值舱、工具舱的用户 0不显示
            ],
            'market_value' => [
                'market_warehouse' => sprintf('%.4f', $ark_market_warehouse),//市值舱
                'tool_warehouse' => sprintf('%.4f', $ark_tool_warehouse),//工具舱
                'is_show' => $is_ark_show//1显示市值舱、工具舱的用户 0不显示
            ],
            'register_num' => $register_num,//大礼包解锁数量
            'bonus' => sprintf('%.6f', $bonus)//抱彩分红
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $this->output_new($r);
    }

    // 积分记录
    public function integral_log()
    {
        $type = input('type');
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $page = input('page', 1);
        $rows = 15;
        if ($type == 3) {
            $this->output_new($r);
        } else if ($type == 2) {
            $type_id = [7118, 7119, 7120];
            $integral_currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
            if (!$integral_currency_id) $this->output_new($r);
        } else {
            $type_id = [600, 6620, 7201, 6505, 115, 6640];
            $integral_currency_id = \app\common\model\RocketConfig::getValue('integral_currency_id');
            if (!$integral_currency_id) $this->output_new($r);
        }

        $res = Db::name('accountbook')->alias('a')->join('currency b', 'a.currency_id=b.currency_id')
            ->field('b.currency_id,b.currency_name,a.number,a.number_type,a.add_time,a.type')
            ->where(['a.currency_id' => $integral_currency_id, 'a.member_id' => $this->member_id])
            ->whereIn('a.type', $type_id)
            ->order('a.id desc')
            ->page($page, $rows)
            ->select();
        if (!$res) $this->output_new($r);
        foreach ($res as &$value) {
            if ($value['number_type'] == 1) {
                $value['num'] = '+' . sprintf('%.4f', $value['number']);
            } else {
                $value['num'] = '-' . sprintf('%.4f', $value['number']);
            }
            if ($value['type'] == 7120) {
                $value['currency_name'] = '积分';
            }
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['title'] = Db::name('accountbook_type')->where(['id' => $value['type']])->value('name_tc');
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $res;
        $this->output_new($r);
    }

    //获取提现按钮状态
    public function withdrawal_status() {
        $currency_id = input('currency_id');
        $config = \app\common\model\WithdrawConfig::where(['currency_id' => $currency_id])->value('data');
        $is_withdrawal = 1;

        if ($config) {
            $is_withdrawal = 0;//是否可提现 0否 1是
            $config = explode(',', $config);
            $week = date("w");
            foreach ($config as $v) {
                $start_time = strtotime(date('Y-m-d') . ' 09:00');
                $end_time = strtotime(date('Y-m-d') . ' 17:30');
                if ($week == $v && time() >= $start_time && time() <= $end_time) {
                    $is_withdrawal = 1;
                    break;
                }
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = ['is_withdrawal' => $is_withdrawal];
        $this->output_new($r);
    }
}
