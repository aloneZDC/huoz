<?php

namespace app\admin\controller;


use app\common\model\AccountBook;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

class CurrencyUser extends Admin {
	public function _empty() {
		header ( "HTTP/1.0 404 Not Found" );
		$this->display ( 'Public:404' );
	}

	/**
	 * 会员钱包充值地址列表
	 */
	public function MemberQianbaoChongzhiUrl(){

		$where=array();
		$cuid=input('cuid');
		$email=input('email');
		$member_id=input('member_id');
		$url=input('url');
		if(!empty($cuid)){
			$where['cu.currency_id']=input("cuid");
		}
        $this->assign("id",input("cuid"));
        if(!empty($email)){
			$where['m.email']=$email;
		}
        $this->assign("email",$email);
		if(!empty($member_id)){
		    $where['cu.member_id']=$member_id;
		}
        $this->assign("member_id",input("member_id"));

        if(!empty($url)){
		    $where['cu.chongzhi_url']=$url;
		}
        $this->assign("url",input("url"));

		$field="cu.*,m.email,c.currency_name";
		$list =  Db::name("Currency_user")->alias("cu")
            ->join(config("database.prefix")."member m","m.member_id=cu.member_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=cu.currency_id","LEFT")
		->field($field)
		->where($where)->order('cu.cu_id desc')->paginate(20,null,['query'=>input()]);
		$show=$list->render();
		$this->assign('list',$list);// 赋值数据集
		$this->assign('page',$show);// 赋值分页输出

		$curr=Db::name("Currency")->select();
		$this->assign("curr",$curr);
		return $this->fetch();
	}

	/**
	 * 会员钱包提积分地址列表
	 */
	public function MemberQianbaoTibiUrl(){

		$where=array();
		$cuid=input('cuid');
		$email=input('email');
		$member_id=input('member_id');
        if(!empty($member_id)){
            $where['qa.user_id']=$member_id;
        }
        $this->assign("member_id",$member_id);
		if(!empty($cuid)){
			$where['qa.currency_id']=input("cuid");
		}
        $this->assign("id",input("cuid"));
		if(!empty($email)){
			$where['m.email']=$email;
		}
        $this->assign("email",$email);
		$field="qa.*,m.email,c.currency_name";
		$list =  Db::name("Qianbao_address")->alias("qa")
            ->join(config("database.prefix")."member m","m.member_id=qa.user_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=qa.currency_id","LEFT")
		->field($field)
		->where($where)->order('qa.id desc')
        ->paginate(20,null,['query'=>input()]);
        $show=$list->render();
		$this->assign('list',$list);// 赋值数据集
		$this->assign('page',$show);// 赋值分页输出


		$curr=Db::name("Currency")->select();
		$this->assign("curr",$curr);
		return $this->fetch();
	}

	//按数量排行榜前500
	public function num_top() {
		$currency_id = intval(input('currency_id'));
		$list = Db::query('select *,(num+forzen_num) as total_num from '.config("database.prefix").'currency_user where member_id not in(
			select member_id from '.config("database.prefix").'flop_white
		) and currency_id='.$currency_id.' order by total_num desc limit 500');
		$this->assign('list',$list);
		return $this->fetch(null, compact('num_top'));
	}

	//下级资产数量
	public function child_currency_num() {
	    $member_id = intval(input('member_id'));

		//下级合约资产数量
		$currency_list = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency,account_type')->where(['is_line'=>1])->select();
		$currency_list = array_column($currency_list->toArray(),null,'currency_id');
		foreach ($currency_list as &$currency) {
			$currency['num'] = $currency['forzen_num'] = $currency['hb_num'] = $currency['dnc_lock'] = $currency['contract_num'] = 0;
		}

		if($member_id) {
			//下级资产总数量
			$num = Db::query('select currency_id,sum(dnc_lock) as dnc_lock,sum(dnc_other_lock) as dnc_other_lock,sum(num) as num,sum(forzen_num) as forzen_num from '.config("database.prefix").'currency_user  
            where member_id in(
                select child_id from '.config("database.prefix").'member_bind where member_id='.$member_id.'
            ) group by currency_id');

			if($num) {
				$num = array_column($num,null,'currency_id');
				foreach ($num as $item) {
					if($item['currency_id'] && isset($currency_list[$item['currency_id']])){
						$currency_list[$item['currency_id']]['num'] = $item['num'];
						$currency_list[$item['currency_id']]['forzen_num'] = $item['forzen_num'];
						$currency_list[$item['currency_id']]['dnc_lock'] = $item['dnc_lock'] + $item['dnc_other_lock'];
					}
				}
			}

			//下级合约冻结数量
			$contract_num = Db::query('select money_currency_id,sum(money_currency_num) as num from '.config("database.prefix").'contract_order
			where  money_type=1 AND `status` IN (1,2,3,4) and member_id in (
				select child_id from '.config("database.prefix").'member_bind where member_id='.$member_id.'
			)');
			if($contract_num) {
				$contract_num = array_column($contract_num,null,'currency_id');
				foreach ($contract_num as $item) {
					if($item['money_currency_id'] && isset($currency_list[$item['money_currency_id']])) $currency_list[$item['money_currency_id']]['contract_num'] = $item['num'];
				}
			}
		}
		return $this->fetch(null, compact('currency_list'));
	}

	//红包项目汇总
	public function hb_project_summary() {
		$online_start_time = strtotime('2020-07-10');

		$page = intval(input('page',1));
		$pageSize = 10;
		$stop_time = todayBeginTimestamp(); //结束时间
		$all_day = ($stop_time - $online_start_time)/86400;

		$real_stop_time = $stop_time - 86400 * $pageSize*($page-1);//开始时间
		$start_time = $real_stop_time - 86400 * $pageSize;//开始时间
		for ($time=$real_stop_time;$time>=$start_time;$time-=86400) {
			$cache_key = 'hbzs_project_summary_'.$time;
			$data = cache($cache_key);
			if(empty($data)) {
				$data = [
					'today' => date('Y-m-d',$time),
					'hb_num' =>0 , //红包入金量
					'hb_open_num' => 0, //红包拆开数量
					'flop_trade_num' => 0, //方舟交易量
					'flop_release_num' => 0, //方舟释放量
					'to_bb' => [],
					'to_wallet' => [],
				];
				//红包入金汇总
				$hb = Db::query("select sum(num) as num,sum(open_num) as open_num from yang_hongbao_log where create_time>=".$time.' and create_time<='.($time+86399));
				if($hb && !empty($hb[0])) {
					$data['hb_num'] = $hb[0]['num'] ?: 0;
					$data['hb_open_num'] = $hb[0]['open_num'] ?: 0;
				}
				//方舟
				$flop = Db::query("select sum(num) as num,sum(release_num) as release_num from yang_flop_trade where add_time>=".$time.' and add_time<='.($time+86399));
				if($flop && !empty($flop[0])) {
					$data['flop_trade_num'] = $flop[0]['num'] ?: 0;
					$data['flop_release_num'] = $flop[0]['release_num'] ?: 0;
				}

				$to_bb = Db::query('select currency_id,sum(num) as num from yang_currency_user_bb_transfer where type=\'to_bb\' and add_time>='.$time.' and add_time<='.($time+86399).' group by currency_id');
				if($to_bb) {
					$data['to_bb'] = $to_bb;
				}

				$to_wallet = Db::query('select currency_id,sum(num) as num from yang_currency_user_bb_transfer where type=\'to_wallet\' and add_time>='.$time.' and add_time<='.($time+86399).' group by currency_id');
				if($to_wallet) {
					$data['to_wallet'] = $to_wallet;
				}
				//今天 昨天的不缓存
				if($time<=$stop_time-86400*2) cache($cache_key,$data);
			}
			$list[] = $data;
		}
		$page = new Bootstrap($list,$pageSize,$page,$all_day,false,['path'=>url('')]);
		$page = $page->render();

		$total = \app\common\model\SpacePlanSummary::field('sum(total_num) as total_num,sum(total_release) as total_release,sum(total_recommand) as total_recommand,sum(total_power) as total_power')->find();
		return $this->fetch(null,compact('list','page','total'));
	}

	function accountbook(Request $request) {
		$type_list = [
			'flop' => ['name'=>'方舟','ids'=>[1000,1001,1002,1003,1005,1006,1007,1008,1009] ],
			'hongbao' => ['name'=>'锦鲤红包','ids'=>[950,951,952] ],
			'contract' => ['name'=>'合约','ids'=>[1100,1101,1102,1103,1104,1105,1106,1107,1108] ],
			'air' => ['name'=>'云梯','ids'=>[1400,1401,1402,1403,1404,1405,1406,1407] ],
		];

		$member_id = intval(input('member_id'));
		$currency_id = input('currency_id');
		$type_get = input('type','');
		if($type_get && isset($type_list[$type_get])) {
			$where['type'] = ['in',$type_list[$type_get]['ids']];
		}
		if (!empty($currency_id) && $currency_id > 0) {
			$where['currency_id'] = $currency_id;
		}
		$where['member_id'] = $member_id;

		$currencyList = \app\common\model\Currency::select();
		$currencyList = array_column($currencyList->toArray(), null, 'currency_id');
		if($request->isAjax()) {
			$page=input("page",1);
			$rows=input("rows",500);
			$new_account_book_total = Db::name('accountbook')->where($where)->count();
			$new_total_page = intval($new_account_book_total/$rows);
			if($new_account_book_total%$rows!=0) $new_total_page++;

			if($page<=$new_total_page) {
				$from = 'new'.$new_total_page.':'.$page;
				$list = Db::name('accountbook')->where($where)->page($page, $rows)->order("id desc")->select();
			} else {
				//查询旧账本
				$old_page = $page - $new_total_page;
				$from = 'old'.$old_page;
				$list = Db::name('accountbook_admin')->where($where)->page($old_page, $rows)->order("id desc")->select();
			}

			if (!empty($list)) {
				$accounType = Db::name("accountbook_type")->field("id,name_tc")->select();
				$typeList = array_column($accounType, null, "id");

				foreach ($list as &$value) {
					$type = $value['type'];
					if($value['type']==24){
						$value['ad_remark'] .= $value['ad_remark']."<a target='_blank' href='".U('BossPlan/bouns_detail',['today'=>date('Y-m-d',$value['add_time']),'member_id'=>$value['member_id']])."'>动态分红详情</a>";
					}
					$value['type'] = isset($typeList[$type]) ?  $typeList[$type]['name_tc'] : '';
					$value['currency_name'] = isset($currencyList[$value['currency_id']]) ? $currencyList[$value['currency_id']]['currency_name'].($currencyList[$value['currency_id']]['is_trade_currency']==1 ? '（币币）' : '') : '';
					$value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
					$value['number'] = $value['number_type'] == 1 ? $value['number'] : -$value['number'];
					$value['after'] = bcadd($value['number'],$value['current'],6);
					$value['change'] = $value['number_type'] == 1 ? "收入" : "支出";
					$value['from_member_id'] = "";
					$value['from_phone'] = "";
					$value['from_email'] = "";
					$value['toMemberId'] = "";
					$value['to_phone'] = "";
					$value['to_email'] = "";
					$value['currency_pair'] = "";
					if ($value['third_id'] > 0 || is_numeric($value['content']) || $value['to_member_id'] > 0) {
						switch ($type) {
							case 5:
								//充币类型
								$tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
								if ($tibi['transfer_type'] == "2") {
									$value['type'] = "平台内" . $value['type'];
								}
								if (!empty($tibi)) {
									if ($tibi['to_member_id'] > 0) {
										$toMember = $this->getMemberInfo($tibi['to_member_id']);
										$value['toMemberId'] = $tibi['to_member_id'];
										$value['to_phone'] = $toMember['phone'];
										$value['to_email'] = $toMember['email'];
									}
									if ($tibi['from_member_id'] > 0) {
										$fromMember = $this->getMemberInfo($tibi['from_member_id']);
										$value['from_member_id'] = $tibi['from_member_id'];
										$value['from_phone'] = $fromMember['phone'];
										$value['from_email'] = $fromMember['email'];
									}
								}
								break;
							case 6:
								//提币类型
								$tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
								if ($tibi['transfer_type'] == "2") {
									$value['type'] = "平台内" . $value['type'];
								}
								if (!empty($tibi)) {
									if ($tibi['to_member_id'] > 0) {
										$toMember = $this->getMemberInfo($tibi['to_member_id']);
										$value['toMemberId'] = $tibi['to_member_id'];
										$value['to_phone'] = $toMember['phone'];
										$value['to_email'] = $toMember['email'];
									}
									if ($tibi['from_member_id'] > 0) {
										$fromMember = $this->getMemberInfo($tibi['from_member_id']);
										$value['from_member_id'] = $tibi['from_member_id'];
										$value['from_phone'] = $fromMember['phone'];
										$value['from_email'] = $fromMember['email'];
									}
								}
								break;
							case 9:
								//otc交易类型
								$tradeOtc = Db::name("trade_otc")->where(['trade_id' => $value['third_id']])->field("member_id,other_member")->find();
								if (!empty($tradeOtc)) {
									if ($tradeOtc['other_member'] > 0) {
										$toMember = $this->getMemberInfo($tradeOtc['other_member']);
										$value['toMemberId'] = $tradeOtc['other_member'];
										$value['to_phone'] = $toMember['phone'];
										$value['to_email'] = $toMember['email'];
									}
									if ($tradeOtc['member_id'] > 0) {
										$fromMember = $this->getMemberInfo($tradeOtc['member_id']);
										$value['from_member_id'] = $tradeOtc['member_id'];
										$value['from_phone'] = $fromMember['phone'];
										$value['from_email'] = $fromMember['email'];
									}
								}

								break;
							case 11:
								//币币交易类型
								if ($value['to_member_id'] > 0) {
									$toMember = $this->getMemberInfo($value['to_member_id']);
									$value['toMemberId'] = $value['to_member_id'];
									$value['to_phone'] = $toMember['phone'];
									$value['to_email'] = $toMember['email'];
								}
								if ($value['member_id'] > 0) {
									$fromMember = $this->getMemberInfo($value['member_id']);
									$value['from_member_id'] = $value['member_id'];
									$value['from_phone'] = $fromMember['phone'];
									$value['from_email'] = $fromMember['email'];
								}
								if($value['to_currency_id']>0){
									$value['currency_pair']=$value['currency_name']."/". $currencyList[$value['to_currency_id']]['currency_name'];
								}
								break;
							case 18:
								//内转帐
								if ($value['to_member_id'] > 0 || is_numeric($value['content'])) {
									$uid = $value['to_member_id'] > 0 ? $value['to_member_id'] : $value['content'];
									$toMember = $this->getMemberInfo($uid);
									$value['toMemberId'] = $uid;
									$value['to_phone'] = $toMember['phone'];
									$value['to_email'] = $toMember['email'];
								}
								if ($value['member_id'] > 0) {
									$fromMember = $this->getMemberInfo($value['member_id']);
									$value['from_member_id'] = $value['member_id'];
									$value['from_phone'] = $fromMember['phone'];
									$value['from_email'] = $fromMember['email'];
								}
								break;


						}
					}
				}
			}
			return $this->ajaxReturn(['result'=>$list,'code'=>SUCCESS,'message'=>'成功','from'=>$from]);
		} else {
			return $this->fetch(null,compact('currencyList','where','type_list'));
		}
	}

	protected function getMemberInfo($member_id, $field = "email,phone")
	{
		if (!empty($member_id)) {
			return Db::name("member")->where(['member_id' => $member_id])->field($field)->find();
		}
		return null;
	}
}

?>
