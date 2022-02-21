<?php
/**
 *泰国会议报名
 */
namespace app\mobile\controller;
use think\Db;
use think\Exception;

class Meeting extends Base{
	public function index(){
        $key = cookie('token');
        $token_id = cookie('uuid');
        $think_language = cookie('think_language');
        if(!empty($key)) cookie('token',$key);
        if(!empty($token_id)) cookie('uuid',$token_id);
        if(!empty($think_language)) cookie('think_language',$think_language);

        $currency_id = intval($this->config['attend_currency_id']);
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_id'=>$currency_id])->find();


        $stop = '';
        $time = time();
        $start_time = strtotime($this->config['attend_start_time']);
        if($time<$start_time) $stop = lang('lan_start_time').$this->config['attend_start_time'];

        $stop_time = strtotime($this->config['attend_stop_time']) + 86400 - 1;
        if($time>$stop_time) $stop = lang('lan_stop');

		return $this->fetch('meeting/index',['pay_num'=>$this->config['attend_pay_num'],'currency'=>$currency,'stop'=>$stop]);
	}

	public function attend(){
		$member_id = $this->checkLogin();
        if($member_id===false) $this->output(10100,lang('lan_modifymember_please_login_again'));
        $member_id = intval($member_id);

        $time = time();
        $start_time = strtotime($this->config['attend_start_time']);
        if($time<$start_time) $this->output(10001,lang('lan_not_start'));

        $stop_time = strtotime($this->config['attend_stop_time']) + 86400 - 1; //截止到当天23:59:59
        if($time>$stop_time) $this->output(10001,lang('lan_stop'));

        $member = Db::name('member')->where(['member_id'=>$member_id])->find();
        if(!$member) $this->output(10100,lang('lan_modifymember_please_login_again'));

        $target_member_id = intval(input('member_id'));
        if(empty($target_member_id)) $this->output(10001,lang('lan_invit_pid_not_exists'));

        $username = input('username');
        if(empty($username)) $this->output(10001,lang('lan_enter_account'));

        $name = input('name');
        $idcard = input('idcard');
        $target = Db::name('member')->where(['member_id'=>$target_member_id])->find(); 
        if(empty($target)) $this->output(10001,lang('lan_invit_pid_not_exists'));
        if($target['phone']!=$username && $target['email']!=$username) $this->output(10001,lang('lan_invit_pid_not_exists'));

        if(empty($name)) $this->output(10001,lang('lan_no_real_name'));
        if(empty($idcard) || !is_idcard($idcard)) $this->output(10001,lang('lan_member_idcard_error'));
        //if(empty($target['name']) || $target['name']!=$name) $this->output(10001,lang('lan_user_name_not_true'));
        //if(empty($target['idcard']) || $target['idcard']!=$idcard) $this->output(10001,lang('lan_user_idcard_not_true'));

        $phone = input('phone');
        if(empty($phone)) $this->output(10001,lang('lan_mobil_phone_empty'));

        $age = intval(input('age'));
        $passport = input('passport','','strval');
        $sex = intval(input('sex'));
        if($sex!=1) $sex = 0;

        $is_pass = Db::name('meeting_pass')->where(['member_id'=>$target['member_id']])->find();
        if(!$is_pass) {
            $target_boss_plan = Db::name('boss_plan')->where(['member_id'=>$target['member_id']])->find();
            if(!$target_boss_plan || $target_boss_plan['status']!=3) $this->output(10001,lang('lan_user_cannot_attend'));
        }

        $currency_id = intval($this->config['attend_currency_id']);
        $currency = Db::name('currency')->where(['currency_id'=>$currency_id])->find();
        if(!$currency) $this->output(10001,lang('lan_stop'));

        $pay_num = intval($this->config['attend_pay_num']);
        $attend_total = intval($this->config['attend_stop_number']); //报名截止人数
        Db::startTrans();
        try{
            $total_num = Db::name('meeting')->lock(true)->count();
            if($total_num>=$attend_total) throw new Exception(lang('lan_user_cannot_attend_full'));

            $meetingInfo = Db::name('meeting')->lock(true)->where(['member_id'=>$target['member_id']])->find();
            if($meetingInfo) throw new Exception(lang('lan_user_acctend_metting_hased'));

            $user_num = model('CurrencyUser')->getNum($member_id, $currency_id , 'num', true);
            $all_num= $pay_num;
            if($user_num<$all_num) throw new Exception(lang('lan_insufficient_balance'));

            $metting_id = Db::name('meeting')->insertGetId([
                'trade_no' => time().rand(1000,9999),
                'member_id' => $target['member_id'],
                'pay_id' => $member_id,
                'pay_number' => $all_num,
                'currency_id' => $currency_id,
                'name' => $name,
                'phone' => $phone,
                'idcard' => $idcard,
                'age' => $age,
                'passport' => $passport,
                'sex' => $sex,
                'add_time' => $time,
                'status' => 0,
            ]);
            if(!$metting_id) throw new Exception(lang('lan_network_busy_try_again'));

            //添加账本信息
            $result = Model('AccountBook')->addLog([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'type'=> 25,
                'content' => 'lan_user_acctend_metting',
                'number_type' => 2,
                'number' => $all_num,
                'fee' => 0,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $metting_id,
            ]);
            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency_id])->update([
                'num' => ['dec',$all_num],
                'forzen_num'=> ['inc',$all_num],    
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            
            Db::commit();
            $this->output(10000,lang('lan_user_acctend_success'));
        } catch (Exception $e) {
            Db::rollback();
            $this->output(10100,$e->getMessage());
        }
	}

    public function attend_list() {
        if($this->request->isGet()) {
            return $this->fetch('meeting/attend_list');
        } else {
            $currency_id = intval($this->config['attend_currency_id']);
            $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_id'=>$currency_id])->find();

            $page = intval(input('page',1));
            if($page<1) $page = 1;
            $page_size = 10;

            $list = [];
            $member_id = $this->checkLogin();
            if(!empty($member_id) && $currency) {
                $list = Db::name('meeting')->field('trade_no,phone,name,idcard,age,sex,passport,add_time,pay_number as pay_num')->where(['pay_id'=>$member_id])->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
                if($list) {
                    foreach ($list as $key => &$value) {
                        $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
                        $value['start_time'] = $this->config['attend_start_time'].' ~ '.$this->config['attend_stop_time'];
                        $value['pay_num'] = $value['pay_num'].' '.$currency['currency_name'];
                        $value['qq'] = $this->config['qq'];
                        if($value['sex']==1) {
                            $value['sex'] = lang('lan_senior_sex_man');
                        } else {
                            $value['sex'] = lang('lan_senior_sex_girl');
                        }
                    }
                }
            }

            if(empty($list)) {
                $this->output(10001,'');
            } else {
                $this->output(10000,'',$list);
            }
        }
    }

    private function checkLogin() {
        $key = urldecode(cookie('token'));
        $uuid = urldecode(cookie('uuid'));
        if(empty($key) || empty($uuid)) return false;

        $token = cache('uuid_'.$uuid,'',$this->login_keep_time);
        if(empty($token)) return false;
        if(!isset($token['user_id'])) return false;

        $user_id = intval($token['user_id']);
        if(empty($user_id)) return false;

        //防止多端登录
        $token_c = cache('auto_login_' . $user_id, '', $this->login_keep_time);
        if(empty($token_c) || $token_c!=$key) return false;

        return $user_id;
    }
}
