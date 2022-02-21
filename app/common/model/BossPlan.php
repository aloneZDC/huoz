<?php
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;
/**
 *XRP社区管理计划层级关系
 */
class BossPlan extends Base {
	//获取申请信息
	public function apply_info($member_id) {
		$return = [];

		$info = Db::name('boss_plan')->where(['member_id'=>$member_id])->find();
        if(!$info) return lang('lan_boss_plan_none');

        $member = $this->getMemberInfo($info['pid']);
        if(!$member) return lang('lan_boss_plan_none');
        
        $member['status'] = $info['status'];
        if($member) $return['parent'] = $member;

        if($info['status']==2) {
        	$log = Db::name('boss_plan_buy')->field('member_id,pid,pay_id,votes,total,status')->where(['member_id'=>$member_id,'status'=>['lt',2],'type'=>0])->find();
        	if($log) $return['audit'] = $log; 
        }

        return $return;
	}

	/**
     *1.申请加入老板计划
	 *@param member_id 用户ID
	 *@param pid 邀约人ID
	 *@param invit_code 邀约人邀请码 改为账号
     */
	public function apply($member_id,$pid,$invit_code){
		if(empty($pid) || empty($invit_code)) return lang("lan_invit_pid_empty");

		//$invit_code = strtoupper($invit_code);
		//查询用户及邀请码是否匹配
		$member = $this->getMemberInfo($pid);
		if(!$member || ($member['phone']!=$invit_code && $member['email']!=$invit_code)) return lang("lan_invit_pid_not_exists");

		//查询是否在老板计划里
		$boss = Db::name('boss_plan')->where(['member_id'=>$pid])->find();
		if(!$boss || $boss['status']!=3) return lang("lan_invit_pid_not_exists");

		return $member;
	}

	/**
     *2.与邀约人绑定
	 *@param member_id 用户ID
	 *@param pid 邀约人ID
	 *@param invit_code 邀约人邀请码
     */
	public function bind($member_id,$pid,$invit_code) {
		$member = $this->apply($member_id,$pid,$invit_code);
		if(is_string($member)) return $member;

		try{
			Db::startTrans();

			$self = Db::name('boss_plan')->lock(true)->where(['member_id'=>$member_id])->find();
			if($self) throw new Exception(lang('lan_boss_plan_has_bind'));

			$data = [
				'member_id' => $member_id,
            	'pid' => $pid,
            	'status' => 1,
            	'create_time' => time(),
			];
            $flag = Db::name('boss_plan')->insertGetId($data);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            $data = [
				'member_id' => $member_id,
            	'pid' => $pid,
			];
            $flag = Db::name('boss_plan_info')->insertGetId($data);
            if($flag===false) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
	}
	
	public function leader_bind($member_id,$child_phone,$child_id,$pid_phone,$pid) {
		$pid_plan = Db::name('boss_plan')->where(['member_id'=>$pid])->find();
		if(!$pid_plan || $pid_plan['status']!=3)  return lang('lan_invit_pid_not_exists');

		//查询新用户信息是否匹配
		$childInfo = $this->getMemberInfo($child_id);
		if(!$childInfo || ($childInfo['phone']!=$child_phone && $childInfo['email']!=$child_phone)) return lang("lan_boss_plan_not_exists");

		$boss_plan_info = Db::name('boss_plan')->where(['member_id'=>$child_id])->find();
        if($boss_plan_info) {
        	//已经绑定
            if($boss_plan_info['status']==3) return lang('lan_boss_plan_has_bind');

            //更新PID
            $flag =  Db::name('boss_plan')->where(['member_id'=>$child_id])->setField('pid',$pid);
            if($flag===false) return lang('lan_network_busy_try_again');

            $flag =  Db::name('boss_plan_info')->where(['member_id'=>$child_id])->setField('pid',$pid);
            if($flag===false) return lang('lan_network_busy_try_again');

            return ['flag'=>true];
        } else {
        	//直接进行绑定操作
        	return $this->bind($child_id,$pid,$pid_phone);
        }
	}

	/**
     *3.搜索下级可激活用户
	 *@param member_id 用户ID
	 *@param other_member_id 下级用户ID
	 *@param phone 下级用户手机或邮箱,为false时不验证
     */
	public function activation_search($member_id,$other_member_id,$phone=false) {
		if(empty($other_member_id)) return lang("lan_account_empty");
		if($phone!==false && empty($phone)) return lang("lan_account_empty");

		$userInfo = $this->getMemberInfo($other_member_id);
		if(!$userInfo) return lang("lan_boss_plan_not_exists");

		if($phone!==false){
			if (checkEmail($phone)) {
	            if($phone!=$userInfo['email']) return lang("lan_boss_plan_not_exists");
	        } else {
	            if($phone!=$userInfo['phone']) return lang("lan_boss_plan_not_exists");
	        }
		}

		$boss_plan = Db::name('boss_plan')->field('pid,status')->where(['member_id'=>$other_member_id])->find();
		if(!$boss_plan) return lang("lan_boss_plan_not_exists");
		if($boss_plan['status']!=1) return lang("lan_boss_plan_has_bind");

		//是否是直属上级
		//2019.1.11新增,自己激活自己
		if($member_id!=$other_member_id && $member_id!=$boss_plan['pid']) {
			$is_parent = Db::name('member_bind')->where(['member_id'=>$member_id,'child_id'=>$boss_plan['pid']])->find();
			$is_parent2 = Db::name('member_bind')->where(['member_id'=>$boss_plan['pid'],'child_id'=>$member_id])->find();
			if(!$is_parent && !$is_parent2) return lang("lan_boss_plan_not_exists");
		}

		return ['user_info'=>$userInfo,'boss_plan'=>$boss_plan];
	}

	/**
     *3.激活用户,需要冻结资产(2019.1.3改为直接扣除资产,无需用户确认)
	 *@param member_id 用户ID
	 *@param other_member_id 下级用户ID
	 *@param step_votes 激活票数
	 *@param pwd 交易密码一次md5加密
	 *@param config['pid_add_time'] 上级激活用户加奖励过期时间（单位天）
	 *@param xrp_num 选择支付的XRP数量,下个版本
	 *@param xrpz_num 选择支付的瑞波钻数量,下个版本
	 *@param xrpj_num 选择支付的瑞波金数量,下个版本
     */
	public function activation($member_id,$other_member_id,$step_votes,$pwd,$config=[],$xrp_num=0,$xrpz_num=0,$xrpj_num=0) {
		if(date('Y-m-d')=='2019-04-16') return lang('lan_close');

	    $checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd,true);
	    if(is_string($checkPwd)) return $checkPwd;

		$search_result = $this->activation_search($member_id,$other_member_id);
		if(is_string($search_result)) return $search_result;

		$boss_plan = $search_result['boss_plan'];

		$step_info = model('BossPlanStep')->step_check($step_votes);
		if(is_string($step_info)) return $step_info;


        //默认全部通过XRP支付
		$result = model('BossPlanStep')->activeChildByNumConfirm($member_id,$other_member_id,$boss_plan['pid'],$step_info,$config,$xrp_num,$xrpz_num,$xrpj_num);
		if(is_string($result)) return $result;

		return $result;
	}

	/**
     *4.下级用户确认 扣除资产
     *@pid_add_time 上级激活用户加奖励过期时间（单位天）
     */
	public function activation_confirm($member_id,$pid_add_time=15) {
		$result = model('BossPlanStep')->activeChildConfirm($member_id,$pid_add_time);
		if(is_string($result)) return $result;

		return $result;
	}

	/**
     *5.下级用户撤销激活
	 *@param member_id 用户ID
     */
	public function activation_cancel_by_child($member_id) {
		$result = model('BossPlanStep')->activeChildCancel($member_id);
		if(is_string($result)) return $result;

		return $result;
	}

	/**
     *5.激活者--撤销激活
	 *@param member_id 用户ID
     */
	public function activation_cancel_by_payer($member_id,$child_id) {		
		$boss_plan_buy = Db::name('boss_plan_buy')->lock(true)->where(['member_id'=>$child_id,'pay_id'=>$member_id,'status'=>0,'type'=>0])->find();
        if(!$boss_plan_buy) return lang('lan_network_busy_try_again');

		$result = model('BossPlanStep')->activeChildCancel($child_id);
		if(is_string($result)) return $result;

		return $result;
	}

	/**
     *激活用户历史记录
	 *@param member_id 用户ID
     */
	public function activation_list($member_id,$status='',$page=1,$page_size=10) {
		$where = ['a.pay_id'=>$member_id,'a.type'=>0];
		if($status!='') $where['a.status'] = intval($status);

		$list = Db::name('boss_plan_buy')->alias('a')
				->field('a.votes,a.status,b.phone as pid_phone,b.email as pid_email,b.invit_code as pid_invit_code,c.phone as pay_phone,c.email as pay_email,m.phone,m.email,m.nick,m.member_id,m.head')
		        ->where($where)
		        ->join(config('database.prefix').'member m','a.member_id=m.member_id','LEFT')
		        ->join(config('database.prefix').'member b','a.pid=b.member_id','LEFT')
		        ->join(config('database.prefix').'member c','a.pay_id=c.member_id','LEFT')
		        ->limit(($page - 1) * $page_size, $page_size)->order('id desc')->select();
		if(!$list) $list = [];

		foreach ($list as $key => $value) {
			if(empty($value['pid_phone'])) {
				$value['pid_phone'] = substr($value['pid_email'],0,3).'****'.substr($value['pid_email'],-7);
			} else {
				$value['pid_phone'] = substr($value['pid_phone'],0,3).'****'.substr($value['pid_phone'],-4);
			}

			if(empty($value['pay_phone'])) {
				$value['pay_phone'] = substr($value['pay_email'],0,3).'****'.substr($value['pay_email'],-7);
			} else {
				$value['pay_phone'] = substr($value['pay_phone'],0,3).'****'.substr($value['pay_phone'],-4);
			}
			if(empty($value['phone'])) $value['phone'] = $value['email'];
			if(empty($value['head'])) $value['head'] = $this->default_head;
			unset($value['pay_email'],$value['pid_email'],$value['email']);
			$list[$key] = $value;
		}

		return $list;
	}

	/**
     *我的社员
	 *@param member_id 用户ID
     */
	public function my_invite_list($member_id,$phone='',$page=1,$page_size=10) {
		$where = ['a.pid'=>$member_id,'a.status'=>3];
		$count = Db::name('boss_plan')->alias('a')->where($where)->count();

		$my_boss_plan = Db::name('boss_plan_info')->where(['member_id'=>$member_id])->find();

		if(!empty($phone)) {
			if (checkEmail($phone)) {
				$where['m.email'] = $phone;
	        } else {
	            $where['m.phone'] = $phone;
	        }
		}
		$where['e.status'] = 1;
		$where['e.type'] = 0;
		$list  = Db::name('boss_plan')->alias('a')
				->field('a.activate_time,c.phone as pay_phone,c.email as pay_email,m.phone,m.email,m.nick,m.member_id,m.head,d.votes,d.level,d.push_num,e.votes as active_votes')
		        ->where($where)
		        ->join(config('database.prefix').'member m','a.member_id=m.member_id','LEFT')
		        ->join(config('database.prefix').'boss_plan_info d','a.member_id=d.member_id','LEFT')
		        ->join(config('database.prefix').'boss_plan_buy e','a.member_id=e.member_id','LEFT')
		        ->join(config('database.prefix').'member c','e.pid=c.member_id','LEFT')
		        ->limit(($page - 1) * $page_size, $page_size)->order('a.activate_time desc')->select();
		if(!$list) {
			$list = [];
		} else {
			//获取1票对应的入金量
			$time = time();
			$vote_number = Db::name('boss_plan_step')->where(['start_time'=>['lt',$time]])->max('number');
			$vote_number = intval($vote_number);

			$member_ids = array_column($list,'member_id');
			//总业绩
			//$condition = ['member_id'=>['in',$member_ids],'type'=>6];
			//$total_in = Db::name('boss_bouns_log')->field('member_id,sum(child_num) as child_num')->where($condition)->select();
			$total_in = Db::name('boss_bouns_week')->field('member_id,sum(num+child_num) as child_num')->where(['member_id'=>['in',$member_ids]])->group('member_id')->select();
			if($total_in) $total_in = array_column($total_in, null,'member_id');

			//昨日业绩
			$add_time = strtotime(date('Y-m-d')) - 86400;
			// $condition['add_time'] = $add_time;
			// $yestoday_in = Db::name('boss_bouns_log')->field('member_id,sum(child_num) as child_num')->where($condition)->select();
			$yestoday_in = Db::name('boss_bouns_week')->field('member_id,sum(num+child_num) as child_num')->where(['member_id'=>['in',$member_ids],'bonus_time'=>$add_time])->group('member_id')->select();
			if($yestoday_in) $yestoday_in = array_column($yestoday_in, null,'member_id');

			$list_member = array_column($list, null,'member_id');
			foreach ($list_member as &$value) {
				if(!empty($value['pay_phone']) || !empty($value['pay_email'])){
					if(empty($value['pay_phone'])) {
						$value['pay_phone'] = substr($value['pay_email'],0,3).'****'.substr($value['pay_email'],-7);
					} else {
						$value['pay_phone'] = substr($value['pay_phone'],0,3).'****'.substr($value['pay_phone'],-4);
					}
				} else {
					$value['pay_phone'] = '';
				}

				$value['activate_time'] = date('Y-m-d',$value['activate_time']);
				if(empty($value['phone'])) $value['phone'] = $value['email'];
				if(empty($value['active_votes'])) $value['active_votes'] = 0;
				if(empty($value['head'])) $value['head'] = $this->default_head;

				if(isset($total_in[$value['member_id']]) && $vote_number>0) {
					$value['total_in'] = isset($total_in[$value['member_id']]) ? $total_in[$value['member_id']]['child_num']/$vote_number : 0;
				} else {
					$value['total_in'] = 0;
				}
				if(isset($total_in[$value['member_id']]) && $vote_number>0 ) {
					$value['yestoday_in'] = isset($yestoday_in[$value['member_id']]) ? $yestoday_in[$value['member_id']]['child_num']/$vote_number : 0;
				} else {
					$value['yestoday_in'] = 0;
				}
				$value['level_name'] = 'V'.$value['level'];
				unset($value['pay_email'],$value['email']);

				//部门是否有和我同级
				$value['is_same'] = 0;
				if($my_boss_plan['level']==0) {
					if($value['level']>$my_boss_plan['level']) {
						$value['is_same'] = 1;
					} else {
						$value['is_same'] = 0;
					}
				} else {
					if($value['level']>=$my_boss_plan['level']) {
						$value['is_same'] = 1;
					} else {
						$child_has = Db::name('member_bind')->where(['member_id'=>$value['member_id'],'child_level'=>$my_boss_plan['level']])->count();
						if($child_has>0) $value['is_same'] = 1;
					}
				}
			}

			$list = array_values($list_member);
		}
		
		return ['count'=>$count,'list'=>$list];
	}

	/**
     *获取用户信息
	 *@param member_id 用户ID
	 *@param is_filter 是否隐藏
     */
    public function getMemberInfo($member_id,$is_filter=false) {
		$member = Db::name('member')->field('member_id,phone,email,nick,invit_code,head,status')->where(['member_id'=>$member_id])->find();
		if($member) {
			if(!empty($member['phone'])){
				if($is_filter) $member['phone'] = substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
			} else {
				if($is_filter) {
					$member['phone'] = substr($member['email'],0,3).'****'.substr($member['email'],-7);
				} else {
					$member['phone'] = $member['email'];
				}
			}
			
			if(empty($member['nick'])) $member['nick'] = $member['phone'];
			if(empty($member['head'])) $member['head'] = $this->default_head;
		}
		return $member;
	}

	/**
     *3.用户认购
	 *@param member_id 用户ID
	 *@param step_votes 认购票数
	 *@param pwd 交易密码一次md5加密
	 *@param xrp_num 选择支付的XRP数量,下个版本
	 *@param xrpz_num 选择支付的瑞波钻数量,下个版本
	 *@param xrpj_num 选择支付的瑞波金数量,下个版本
     */
	public function user_buy($member_id,$step_votes,$pwd,$config,$xrp_num=0,$xrpz_num=0,$xrpj_num=0) {
		if(date('Y-m-d')=='2019-04-16') return lang('lan_close');

		$checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd,true);
        if(is_string($checkPwd)) return $checkPwd;

		$step_info = model('BossPlanStep')->step_check($step_votes);
		if(is_string($step_info)) return $step_info;

		//默认全部通过XRP支付
		$result = model('BossPlanStep')->buyByNum($member_id,$step_info,$config,$xrp_num,$xrpz_num,$xrpj_num);
		if(is_string($result)) return $result;

		return $result;
	}
}